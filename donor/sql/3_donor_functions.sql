-- ==============================================================================
-- 3. DONOR FUNCTIONS
-- ==============================================================================

-- 1. Full Eligibility Checker (Checks Age & 4-month waiting period)
CREATE OR REPLACE FUNCTION check_donor_eligibility_full (
    p_donor_id IN NUMBER
) RETURN VARCHAR2
IS
    v_dob DATE;
    v_age NUMBER;
    v_last_date DATE;
    v_months_diff NUMBER;
BEGIN
    -- Check Donor DOB and Age
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

    -- Check last donation date
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

-- 2. Calculate Next Eligible Donation Date
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

-- 3. Get Total Blood Units Donated by a Donor
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

-- 4. Get Total Donation Count for a Donor
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

-- 5. Get Donor ID from User ID
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
