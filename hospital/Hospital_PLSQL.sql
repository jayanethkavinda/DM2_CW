-- ==============================================================================
-- 🏥 LIFELINECONNECT - HOSPITAL MODULE PL/SQL PACKAGE
-- Stored Procedures, Functions, Views, and Triggers for Hospital Blood Requisitions
-- ==============================================================================

-- ------------------------------------------------------------------------------
-- 1. UPDATE ROLE VALIDATION TRIGGER TO ALLOW 'Hospital' ROLE
-- ------------------------------------------------------------------------------
CREATE OR REPLACE TRIGGER trg_validate_user_role
BEFORE INSERT OR UPDATE OF role ON users
FOR EACH ROW
BEGIN
    IF :NEW.role NOT IN ('Admin', 'Staff', 'Donor', 'Hospital') THEN
        RAISE_APPLICATION_ERROR(-20011, 'Invalid Role: Role must be either Admin, Staff, Donor, or Hospital.');
    END IF;
END;
/


-- ------------------------------------------------------------------------------
-- 2. STORED PROCEDURES
-- ------------------------------------------------------------------------------

-- 2.1 Procedure: Register Hospital User Account
CREATE OR REPLACE PROCEDURE register_hospital_user (
    p_email         IN VARCHAR2,
    p_password      IN VARCHAR2,
    p_hospital_name IN VARCHAR2,
    o_user_id       OUT NUMBER
)
IS
    v_count NUMBER;
    v_clean_email VARCHAR2(100);
BEGIN
    v_clean_email := LOWER(TRIM(p_email));

    -- Check for duplicate email
    SELECT COUNT(*) INTO v_count FROM users WHERE email = v_clean_email;
    IF v_count > 0 THEN
        RAISE_APPLICATION_ERROR(-20001, 'Duplicate Email: An account with this email already exists.');
    END IF;

    -- Generate Next User ID
    SELECT NVL(MAX(user_id), 0) + 1 INTO o_user_id FROM users;

    -- Insert into users table with 'Hospital' role
    INSERT INTO users (user_id, email, password, role)
    VALUES (o_user_id, v_clean_email, p_password, 'Hospital');

    COMMIT;
EXCEPTION
    WHEN DUP_VAL_ON_INDEX THEN
        RAISE_APPLICATION_ERROR(-20001, 'Duplicate Error: User already exists.');
    WHEN OTHERS THEN
        ROLLBACK;
        RAISE;
END;
/


-- 2.2 Procedure: Submit Hospital Blood Requisition
CREATE OR REPLACE PROCEDURE submit_hospital_blood_request (
    p_hospital_name IN VARCHAR2,
    p_blood_group   IN VARCHAR2,
    p_units_needed  IN NUMBER,
    o_request_id    OUT NUMBER
)
IS
    v_bg_count NUMBER;
BEGIN
    -- Validate units requested
    IF p_units_needed IS NULL OR p_units_needed <= 0 THEN
        RAISE_APPLICATION_ERROR(-20030, 'Invalid Units: Units requested must be greater than zero.');
    END IF;

    -- Validate blood group exists in master table
    SELECT COUNT(*) INTO v_bg_count 
    FROM blood_inventory 
    WHERE blood_group = p_blood_group;

    IF v_bg_count = 0 THEN
        RAISE_APPLICATION_ERROR(-20031, 'Invalid Blood Group: Specified blood group does not exist in inventory.');
    END IF;

    -- Generate Next Request ID
    SELECT NVL(MAX(request_id), 0) + 1 INTO o_request_id FROM hospital_requests;

    -- Insert Request into hospital_requests with 'Pending' status
    INSERT INTO hospital_requests (
        request_id,
        hospital_name,
        blood_group,
        units_needed,
        request_date,
        status
    ) VALUES (
        o_request_id,
        TRIM(p_hospital_name),
        p_blood_group,
        p_units_needed,
        TRUNC(SYSDATE),
        'Pending'
    );

    COMMIT;
EXCEPTION
    WHEN OTHERS THEN
        ROLLBACK;
        RAISE;
END;
/


-- 2.3 Procedure: Cancel Pending Blood Requisition by Hospital
CREATE OR REPLACE PROCEDURE cancel_hospital_request (
    p_request_id    IN NUMBER,
    p_hospital_name IN VARCHAR2
)
IS
    v_status VARCHAR2(20);
BEGIN
    -- Check request existence and ownership
    SELECT status INTO v_status 
    FROM hospital_requests 
    WHERE request_id = p_request_id AND LOWER(TRIM(hospital_name)) = LOWER(TRIM(p_hospital_name));

    IF v_status <> 'Pending' THEN
        RAISE_APPLICATION_ERROR(-20032, 'Cannot Cancel: Only Pending requests can be cancelled.');
    END IF;

    -- Update status to Cancelled
    UPDATE hospital_requests 
    SET status = 'Cancelled' 
    WHERE request_id = p_request_id;

    COMMIT;
EXCEPTION
    WHEN NO_DATA_FOUND THEN
        RAISE_APPLICATION_ERROR(-20033, 'Request Not Found: No matching requisition found for this hospital.');
    WHEN OTHERS THEN
        ROLLBACK;
        RAISE;
END;
/


-- ------------------------------------------------------------------------------
-- 3. FUNCTIONS (ANALYTICS & METRICS)
-- ------------------------------------------------------------------------------

-- 3.1 Function: Get Total Units Requested by a Hospital
CREATE OR REPLACE FUNCTION get_hospital_total_requested (
    p_hospital_name IN VARCHAR2
) RETURN NUMBER
IS
    v_total NUMBER;
BEGIN
    SELECT NVL(SUM(units_needed), 0) INTO v_total
    FROM hospital_requests
    WHERE LOWER(TRIM(hospital_name)) = LOWER(TRIM(p_hospital_name))
      AND status IN ('Approved', 'Pending');

    RETURN v_total;
END;
/

-- 3.2 Function: Get Total Approved Units for a Hospital
CREATE OR REPLACE FUNCTION get_hospital_approved_units (
    p_hospital_name IN VARCHAR2
) RETURN NUMBER
IS
    v_total NUMBER;
BEGIN
    SELECT NVL(SUM(units_needed), 0) INTO v_total
    FROM hospital_requests
    WHERE LOWER(TRIM(hospital_name)) = LOWER(TRIM(p_hospital_name))
      AND status = 'Approved';

    RETURN v_total;
END;
/

-- 3.3 Function: Get Count of Pending Requisitions for a Hospital
CREATE OR REPLACE FUNCTION get_hospital_pending_count (
    p_hospital_name IN VARCHAR2
) RETURN NUMBER
IS
    v_count NUMBER;
BEGIN
    SELECT COUNT(*) INTO v_count
    FROM hospital_requests
    WHERE LOWER(TRIM(hospital_name)) = LOWER(TRIM(p_hospital_name))
      AND status = 'Pending';

    RETURN v_count;
END;
/


-- ------------------------------------------------------------------------------
-- 4. VIEWS
-- ------------------------------------------------------------------------------

-- 4.1 View: Hospital Blood Stock Visualizer (Safe read-only view for hospitals)
CREATE OR REPLACE VIEW vw_hospital_blood_stock AS
SELECT 
    blood_group,
    total_units,
    TO_CHAR(last_updated, 'YYYY-MM-DD HH24:MI') AS formatted_last_updated,
    CASE 
        WHEN total_units = 0 THEN 'Critical Shortage'
        WHEN total_units < 5 THEN 'Low Stock'
        WHEN total_units BETWEEN 5 AND 15 THEN 'Moderate'
        ELSE 'Sufficient'
    END AS stock_status
FROM blood_inventory;
/

-- 4.2 View: Comprehensive Hospital Requests Log
CREATE OR REPLACE VIEW vw_hospital_all_requests AS
SELECT 
    hr.request_id,
    hr.hospital_name,
    hr.blood_group,
    hr.units_needed,
    hr.request_date,
    TO_CHAR(hr.request_date, 'YYYY-MM-DD') AS formatted_request_date,
    hr.status
FROM hospital_requests hr;
/


-- ------------------------------------------------------------------------------
-- 5. TRIGGERS
-- ------------------------------------------------------------------------------

-- 5.1 Trigger: Validate Positive Units on Requisition
CREATE OR REPLACE TRIGGER trg_validate_hospital_units
BEFORE INSERT OR UPDATE OF units_needed ON hospital_requests
FOR EACH ROW
BEGIN
    IF :NEW.units_needed <= 0 THEN
        RAISE_APPLICATION_ERROR(-20034, 'Invalid Quantity: Requisition quantity must be at least 1 unit.');
    END IF;
END;
/

COMMIT;
