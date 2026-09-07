-- ==============================================================================
-- 3. MANAGER FUNCTIONS
-- ==============================================================================

-- 1. Get total available units for a specific blood group
CREATE OR REPLACE FUNCTION get_blood_stock (
    p_bg IN VARCHAR2
) RETURN NUMBER
IS
    v_units NUMBER;
BEGIN
    SELECT total_units INTO v_units FROM blood_inventory WHERE blood_group = p_bg;
    RETURN v_units;
EXCEPTION
    WHEN NO_DATA_FOUND THEN
        RETURN 0;
END;
/

-- 2. Count total pending hospital requests
CREATE OR REPLACE FUNCTION get_pending_requests_count
RETURN NUMBER
IS
    v_count NUMBER;
BEGIN
    SELECT COUNT(*) INTO v_count FROM hospital_requests WHERE status = 'Pending';
    RETURN v_count;
END;
/

-- 3. Count total registered donors
CREATE OR REPLACE FUNCTION get_total_donors
RETURN NUMBER
IS
    v_count NUMBER;
BEGIN
    SELECT COUNT(*) INTO v_count FROM donors;
    RETURN v_count;
END;
/

-- 4. Check donor eligibility based on last donation date (Implicit Cursor)
CREATE OR REPLACE FUNCTION check_donor_eligibility (
    p_donor_id IN NUMBER
) RETURN VARCHAR2
IS
    v_last_date DATE;
    v_months_diff NUMBER;
BEGIN
    SELECT MAX(donation_date) INTO v_last_date 
    FROM donation_history 
    WHERE donor_id = p_donor_id;
    
    IF v_last_date IS NULL THEN
        RETURN 'Eligible'; -- Never donated before
    END IF;
    
    v_months_diff := MONTHS_BETWEEN(SYSDATE, v_last_date);
    
    IF v_months_diff >= 4 THEN
        RETURN 'Eligible';
    ELSE
        RETURN 'Not Eligible (Must wait 4 months)';
    END IF;
END;
/

-- 5. Get hospital name for a request
CREATE OR REPLACE FUNCTION get_hospital_name (
    p_req_id IN NUMBER
) RETURN VARCHAR2
IS
    v_name VARCHAR2(150);
BEGIN
    SELECT hospital_name INTO v_name FROM hospital_requests WHERE request_id = p_req_id;
    RETURN v_name;
EXCEPTION
    WHEN NO_DATA_FOUND THEN
        RETURN 'Unknown Hospital';
END;
/
