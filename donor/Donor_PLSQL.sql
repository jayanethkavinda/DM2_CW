SET SERVEROUTPUT ON;

-- ==============================================================================
-- LIFE LINE CONNECT - BLOOD DONOR MODULE PL/SQL MASTER SCRIPTS
-- Structured Step-by-Step Architecture for Coursework & Viva
-- ==============================================================================


-- ==============================================================================
-- 🔐 SECTION 1: DONOR AUTHENTICATION (REGISTRATION & LOGIN)
-- ==============================================================================

-- ------------------------------------------------------------------------------
-- 1.A. REGISTRATION FLOW
-- ------------------------------------------------------------------------------

-- 1.A.1 Stored Procedure: Register New Donor User (Atomic Insert into Users + Donors)
CREATE OR REPLACE PROCEDURE register_donor_user (
    p_email        IN VARCHAR2,
    p_password     IN VARCHAR2,
    p_full_name    IN VARCHAR2,
    p_out_user_id  OUT NUMBER,
    p_out_donor_id OUT NUMBER
)
IS
    v_new_user_id  NUMBER;
    v_new_donor_id NUMBER;
    v_email_count  NUMBER;
    e_email_exists EXCEPTION;
BEGIN
    -- Check if email is already registered
    SELECT COUNT(*) INTO v_email_count FROM users WHERE LOWER(email) = LOWER(p_email);
    IF v_email_count > 0 THEN
        RAISE e_email_exists;
    END IF;

    -- Generate Next Primary Keys
    SELECT NVL(MAX(user_id), 0) + 1 INTO v_new_user_id FROM users;
    SELECT NVL(MAX(donor_id), 0) + 1 INTO v_new_donor_id FROM donors;

    -- 1. Insert into users table
    INSERT INTO users (user_id, email, password, role)
    VALUES (v_new_user_id, LOWER(TRIM(p_email)), p_password, 'Donor');

    -- 2. Insert initial profile into donors table
    INSERT INTO donors (donor_id, user_id, full_name)
    VALUES (v_new_donor_id, v_new_user_id, TRIM(p_full_name));

    COMMIT; -- Persist both records atomically
    
    p_out_user_id  := v_new_user_id;
    p_out_donor_id := v_new_donor_id;
    DBMS_OUTPUT.PUT_LINE('Donor registered successfully with ID: ' || v_new_donor_id);

EXCEPTION
    WHEN e_email_exists THEN
        RAISE_APPLICATION_ERROR(-20021, 'Registration Failed: An account with this email already exists.');
    WHEN DUP_VAL_ON_INDEX THEN
        RAISE_APPLICATION_ERROR(-20022, 'Registration Failed: Duplicate record detected.');
    WHEN OTHERS THEN
        ROLLBACK;
        RAISE;
END;
/

-- 1.A.2 Trigger: Format User Email to Lowercase automatically on Registration
CREATE OR REPLACE TRIGGER trg_donor_email_lowercase
BEFORE INSERT OR UPDATE OF email ON users
FOR EACH ROW
BEGIN
    :NEW.email := LOWER(TRIM(:NEW.email));
END;
/


-- ------------------------------------------------------------------------------
-- 1.B. LOGIN & CREDENTIALS FLOW
-- ------------------------------------------------------------------------------

-- 1.B.1 Function: Get Donor ID from Logged-in User ID
CREATE OR REPLACE FUNCTION get_donor_id_by_user (
    p_user_id IN NUMBER
) RETURN NUMBER
IS
    v_donor_id NUMBER;
BEGIN
    SELECT donor_id INTO v_donor_id FROM donors WHERE user_id = p_user_id;
    RETURN v_donor_id;
EXCEPTION
    WHEN NO_DATA_FOUND THEN
        RETURN NULL;
END;
/

-- 1.B.2 Stored Procedure: Change Donor Password
CREATE OR REPLACE PROCEDURE change_donor_password (
    p_user_id      IN NUMBER,
    p_old_password IN VARCHAR2,
    p_new_password IN VARCHAR2
)
IS
    v_valid NUMBER;
    e_invalid_old_pass EXCEPTION;
BEGIN
    SELECT COUNT(*) INTO v_valid 
    FROM users 
    WHERE user_id = p_user_id AND password = p_old_password AND role = 'Donor';

    IF v_valid = 0 THEN
        RAISE e_invalid_old_pass;
    END IF;

    UPDATE users 
    SET password = p_new_password 
    WHERE user_id = p_user_id;

    COMMIT;
    DBMS_OUTPUT.PUT_LINE('Password changed successfully.');

EXCEPTION
    WHEN e_invalid_old_pass THEN
        RAISE_APPLICATION_ERROR(-20024, 'Password Change Failed: Incorrect existing password.');
END;
/



-- ==============================================================================
-- 👤 SECTION 2: COMPLETE & MANAGE DONOR PROFILE
-- ==============================================================================

-- ------------------------------------------------------------------------------
-- 2.A. LOAD PROFILE DATA INTO TEXTBOXES (දත්ත Form එකට ලබාගැනීම)
-- ------------------------------------------------------------------------------

-- 2.A.1 View: Donor Profile Summary with User Account & District Info
CREATE OR REPLACE VIEW vw_donor_profile_details AS
SELECT 
    d.donor_id,
    d.user_id,
    u.email,
    d.full_name,
    d.dob,
    TO_CHAR(d.dob, 'YYYY-MM-DD') AS formatted_dob,
    TRUNC(MONTHS_BETWEEN(SYSDATE, d.dob)/12) AS age,
    d.gender,
    d.blood_group,
    d.contact_no,
    d.address,
    d.district_id,
    dist.district_name
FROM donors d
JOIN users u ON d.user_id = u.user_id
LEFT JOIN districts dist ON d.district_id = dist.district_id;
/


-- ------------------------------------------------------------------------------
-- 2.B. UPDATE & COMPLETE PROFILE  
-- ------------------------------------------------------------------------------

-- 2.B.1 Stored Procedure: Update / Complete Donor Profile
CREATE OR REPLACE PROCEDURE update_donor_profile (
    p_donor_id    IN NUMBER,
    p_full_name   IN VARCHAR2,
    p_dob         IN DATE,
    p_gender      IN VARCHAR2,
    p_blood_group IN VARCHAR2,
    p_contact_no  IN VARCHAR2,
    p_address     IN VARCHAR2,
    p_district_id IN NUMBER
)
IS
    v_donor_count     NUMBER;
    v_age             NUMBER;
    e_donor_not_found EXCEPTION;
    e_underage        EXCEPTION;
BEGIN
    -- 1. Check if donor exists
    SELECT COUNT(*) INTO v_donor_count FROM donors WHERE donor_id = p_donor_id;
    IF v_donor_count = 0 THEN
        RAISE e_donor_not_found;
    END IF;

    -- 2. Check minimum legal age threshold (18+ Years)
    IF p_dob IS NOT NULL THEN
        v_age := TRUNC(MONTHS_BETWEEN(SYSDATE, p_dob)/12);
        IF v_age < 18 THEN
            RAISE e_underage;
        END IF;
    END IF;

    -- 3. Update donor details in donors table
    UPDATE donors
    SET full_name   = TRIM(p_full_name),
        dob         = p_dob,
        gender      = p_gender,
        blood_group = p_blood_group,
        contact_no  = TRIM(p_contact_no),
        address     = TRIM(p_address),
        district_id = p_district_id
    WHERE donor_id  = p_donor_id;

    COMMIT;
    DBMS_OUTPUT.PUT_LINE('Donor profile updated successfully.');

EXCEPTION
    WHEN e_donor_not_found THEN
        RAISE_APPLICATION_ERROR(-20023, 'Profile Update Failed: Donor ID not found.');
    WHEN e_underage THEN
        RAISE_APPLICATION_ERROR(-20020, 'Profile Update Failed: Donor must be at least 18 years old.');
    WHEN OTHERS THEN
        ROLLBACK;
        RAISE;
END;
/

-- 2.B.2 Trigger: Validate Donor Age & Block Future Birth Dates (Profile Validation Trigger)
CREATE OR REPLACE TRIGGER trg_validate_donor_dob
BEFORE INSERT OR UPDATE OF dob ON donors
FOR EACH ROW
WHEN (NEW.dob IS NOT NULL)
DECLARE
    v_age NUMBER;
BEGIN
    v_age := TRUNC(MONTHS_BETWEEN(SYSDATE, :NEW.dob)/12);
    IF v_age < 18 THEN
        RAISE_APPLICATION_ERROR(-20020, 'Eligibility Violation: Blood donor must be at least 18 years old.');
    ELSIF :NEW.dob > SYSDATE THEN
        RAISE_APPLICATION_ERROR(-20025, 'Invalid Date: Date of birth cannot be in the future.');
    END IF;
END;
/

-- 2.B.3 Trigger: Audit Logging after Profile Update (Profile Audit Trail Trigger)
CREATE OR REPLACE TRIGGER trg_log_donor_profile_update
AFTER UPDATE ON donors
FOR EACH ROW
BEGIN
    DBMS_OUTPUT.PUT_LINE('Profile updated for Donor ID: ' || :NEW.donor_id || ' (' || :NEW.full_name || ')');
END;
/










-- ==============================================================================
-- 🩺 SECTION 3: DONOR MEDICAL ELIGIBILITY & HEALTH RULES (FUNCTIONS)
-- ==============================================================================

-- 3.1 Function: Full Medical Eligibility Checker (Age & 4-Month Interval Check)
CREATE OR REPLACE FUNCTION check_donor_eligibility_full (
    p_donor_id IN NUMBER
) RETURN VARCHAR2
IS
    v_dob         DATE;
    v_age         NUMBER;
    v_last_date   DATE;
    v_months_diff NUMBER;
BEGIN
    -- 1. Check Date of Birth and Age
    SELECT dob INTO v_dob FROM donors WHERE donor_id = p_donor_id;
    IF v_dob IS NULL THEN
        RETURN 'Profile Incomplete: Please set your Date of Birth';
    END IF;

    v_age := TRUNC(MONTHS_BETWEEN(SYSDATE, v_dob) / 12);
    IF v_age < 18 THEN
        RETURN 'Not Eligible: Minimum age requirement is 18 years (Current: ' || v_age || ')';
    ELSIF v_age > 65 THEN
        RETURN 'Not Eligible: Maximum age limit is 65 years (Current: ' || v_age || ')';
    END IF;

    -- 2. Check last donation date & 4-month waiting rule
    SELECT MAX(donation_date) INTO v_last_date 
    FROM donation_history 
    WHERE donor_id = p_donor_id;

    IF v_last_date IS NULL THEN
        RETURN 'Eligible to Donate (First Time Donor)';
    END IF;

    v_months_diff := MONTHS_BETWEEN(SYSDATE, v_last_date);
    IF v_months_diff >= 4 THEN
        RETURN 'Eligible to Donate (Last donation was > 4 months ago)';
    ELSE
        RETURN 'Not Eligible: Must wait 4 months between donations (Last: ' || TO_CHAR(v_last_date, 'YYYY-MM-DD') || ')';
    END IF;

EXCEPTION
    WHEN NO_DATA_FOUND THEN
        RETURN 'Donor record not found';
    WHEN OTHERS THEN
        RETURN 'Error calculating eligibility';
END;
/

-- 3.2 Function: Calculate Next Eligible Donation Date
CREATE OR REPLACE FUNCTION get_next_eligible_date (
    p_donor_id IN NUMBER
) RETURN DATE
IS
    v_last_date DATE;
    v_next_date DATE;
BEGIN
    SELECT MAX(donation_date) INTO v_last_date 
    FROM donation_history 
    WHERE donor_id = p_donor_id;

    IF v_last_date IS NULL THEN
        RETURN SYSDATE; -- Eligible immediately
    END IF;

    v_next_date := ADD_MONTHS(v_last_date, 4);
    
    IF v_next_date <= SYSDATE THEN
        RETURN SYSDATE;
    ELSE
        RETURN v_next_date;
    END IF;
EXCEPTION
    WHEN OTHERS THEN
        RETURN SYSDATE;
END;
/

-- 3.3 Function: Get Total Blood Units Donated by a Donor
CREATE OR REPLACE FUNCTION get_donor_total_units (
    p_donor_id IN NUMBER
) RETURN NUMBER
IS
    v_total NUMBER;
BEGIN
    SELECT NVL(SUM(blood_units), 0) INTO v_total 
    FROM donation_history 
    WHERE donor_id = p_donor_id;

    RETURN v_total;
END;
/

-- 3.4 Function: Get Total Donation Count for a Donor
CREATE OR REPLACE FUNCTION get_donor_donation_count (
    p_donor_id IN NUMBER
) RETURN NUMBER
IS
    v_count NUMBER;
BEGIN
    SELECT COUNT(*) INTO v_count 
    FROM donation_history 
    WHERE donor_id = p_donor_id;

    RETURN v_count;
END;
/



-- ==============================================================================
-- 🏕️ SECTION 4: DONOR UPCOMING CAMPS & DONATION HISTORY (VIEWS & TRIGGERS)
-- ==============================================================================

-- 4.1 View: Upcoming Camps for Donors with District Name and Status
CREATE OR REPLACE VIEW vw_donor_upcoming_camps AS
SELECT 
    c.camp_id,
    c.camp_name,
    c.camp_date,
    TO_CHAR(c.camp_date, 'YYYY-MM-DD') AS formatted_date,
    c.venue,
    d.district_id,
    d.district_name,
    CASE 
        WHEN c.camp_date = TRUNC(SYSDATE) THEN 'Today'
        WHEN c.camp_date > TRUNC(SYSDATE) THEN 'Upcoming'
        ELSE 'Past'
    END AS camp_status
FROM camps c
JOIN districts d ON c.district_id = d.district_id
WHERE c.camp_date >= TRUNC(SYSDATE);
/

-- 4.2 View: Complete Donation History per Donor with Camp and District Details
CREATE OR REPLACE VIEW vw_donor_donation_history AS
SELECT 
    dh.history_id,
    dh.donor_id,
    dn.full_name AS donor_name,
    dn.blood_group,
    c.camp_id,
    c.camp_name,
    c.venue,
    d.district_name,
    dh.donation_date,
    TO_CHAR(dh.donation_date, 'YYYY-MM-DD') AS formatted_donation_date,
    dh.blood_units
FROM donation_history dh
JOIN donors dn ON dh.donor_id = dn.donor_id
JOIN camps c ON dh.camp_id = c.camp_id
JOIN districts d ON c.district_id = d.district_id;
/

-- 4.3 Trigger: Prevent Future Dates on Donation History (Temporal Integrity)
CREATE OR REPLACE TRIGGER trg_prevent_future_donation
BEFORE INSERT OR UPDATE OF donation_date ON donation_history
FOR EACH ROW
BEGIN
    IF :NEW.donation_date > SYSDATE THEN
        RAISE_APPLICATION_ERROR(-20026, 'Invalid Date: Donation date cannot be in the future.');
    END IF;
END;
/

COMMIT;
