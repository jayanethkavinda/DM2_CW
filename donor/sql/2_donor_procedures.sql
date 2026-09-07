-- ==============================================================================
-- 2. DONOR PROCEDURES
-- ==============================================================================

-- 1. Register a new Donor (Creates User record and Donor record in one transaction)
CREATE OR REPLACE PROCEDURE register_donor_user (
    p_email      IN VARCHAR2,
    p_password   IN VARCHAR2,
    p_full_name  IN VARCHAR2,
    p_out_user_id OUT NUMBER,
    p_out_donor_id OUT NUMBER
)
IS
    v_new_user_id  NUMBER;
    v_new_donor_id NUMBER;
    v_email_count  NUMBER;
    e_email_exists EXCEPTION;
BEGIN
    -- Check if email already registered
    SELECT COUNT(*) INTO v_email_count FROM users WHERE LOWER(email) = LOWER(p_email);
    IF v_email_count > 0 THEN
        RAISE e_email_exists;
    END IF;

    -- Generate Next User ID
    SELECT NVL(MAX(user_id), 0) + 1 INTO v_new_user_id FROM users;

    -- Insert into users table
    INSERT INTO users (user_id, email, password, role)
    VALUES (v_new_user_id, LOWER(TRIM(p_email)), p_password, 'Donor');

    -- Generate Next Donor ID
    SELECT NVL(MAX(donor_id), 0) + 1 INTO v_new_donor_id FROM donors;

    -- Insert initial record into donors table
    INSERT INTO donors (donor_id, user_id, full_name)
    VALUES (v_new_donor_id, v_new_user_id, TRIM(p_full_name));

    COMMIT;
    
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

-- 2. Update / Complete Donor Profile
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
    v_donor_count NUMBER;
    v_age NUMBER;
    e_donor_not_found EXCEPTION;
    e_underage EXCEPTION;
BEGIN
    -- Check if donor exists
    SELECT COUNT(*) INTO v_donor_count FROM donors WHERE donor_id = p_donor_id;
    IF v_donor_count = 0 THEN
        RAISE e_donor_not_found;
    END IF;

    -- Check Age if DOB provided
    IF p_dob IS NOT NULL THEN
        v_age := TRUNC(MONTHS_BETWEEN(SYSDATE, p_dob)/12);
        IF v_age < 18 THEN
            RAISE e_underage;
        END IF;
    END IF;

    -- Update donor details
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

-- 3. Change Donor Password
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

-- 4. Update Donor Contact Information Only
CREATE OR REPLACE PROCEDURE update_donor_contact (
    p_donor_id    IN NUMBER,
    p_contact_no  IN VARCHAR2,
    p_address     IN VARCHAR2,
    p_district_id IN NUMBER
)
IS
BEGIN
    UPDATE donors
    SET contact_no  = TRIM(p_contact_no),
        address     = TRIM(p_address),
        district_id = p_district_id
    WHERE donor_id  = p_donor_id;

    IF SQL%ROWCOUNT = 0 THEN
        RAISE_APPLICATION_ERROR(-20023, 'Donor not found.');
    ELSE
        COMMIT;
    END IF;
END;
/
