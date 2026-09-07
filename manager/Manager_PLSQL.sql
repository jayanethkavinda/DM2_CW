 SET SERVEROUTPUT ON;

-- ==============================================================================
-- VIRTUAL TABLES (VIEWS & MATERIALIZED VIEWS)
-- ==============================================================================

-- 1. View: To see upcoming camps easily (Virtual Table)
CREATE OR REPLACE VIEW vw_upcoming_camps AS
SELECT c.camp_id, c.camp_name, c.camp_date, c.venue, d.district_name
FROM camps c
JOIN districts d ON c.district_id = d.district_id
WHERE c.camp_date >= SYSDATE;

-- 2. Materialized View: For Monthly Blood Collection Report (Refreshes on demand)
CREATE MATERIALIZED VIEW mv_monthly_collection
BUILD IMMEDIATE
REFRESH ON DEMAND
AS
SELECT c.camp_name, EXTRACT(MONTH FROM d.donation_date) AS month, SUM(d.blood_units) AS total_collected
FROM donation_history d
JOIN camps c ON d.camp_id = c.camp_id
GROUP BY c.camp_name, EXTRACT(MONTH FROM d.donation_date);

-- ==============================================================================
-- PROCEDURES (5)
-- ==============================================================================

-- 1. Add a new camp
CREATE OR REPLACE PROCEDURE add_new_camp (
    p_camp_id IN NUMBER,
    p_name IN VARCHAR2,
    p_date IN DATE,
    p_venue IN VARCHAR2,
    p_district IN NUMBER
)
IS
BEGIN
    INSERT INTO camps (camp_id, camp_name, camp_date, venue, district_id)
    VALUES (p_camp_id, p_name, p_date, p_venue, p_district);
    COMMIT;
EXCEPTION
    WHEN DUP_VAL_ON_INDEX THEN
        DBMS_OUTPUT.PUT_LINE('Error: Camp ID already exists.');
END;
/

-- 2. Record a blood donation (Uses Custom Exception)
CREATE OR REPLACE PROCEDURE add_donation_record (
    p_history_id IN NUMBER,
    p_donor_id IN NUMBER,
    p_camp_id IN NUMBER,
    p_units IN NUMBER
)
IS
    v_donor_exists NUMBER;
     -- Declare custom exception for invalid donor references
    e_donor_not_found EXCEPTION;
BEGIN
    -- Validate if the donor exists in the donors table
    SELECT COUNT(*) INTO v_donor_exists FROM donors WHERE donor_id = p_donor_id;
    
    -- Raise exception if donor does not exist
    IF v_donor_exists = 0 THEN
        RAISE e_donor_not_found;
    END IF;
    -- Insert new record into donation_history table
    INSERT INTO donation_history (history_id, donor_id, camp_id, donation_date, blood_units)
    VALUES (p_history_id, p_donor_id, p_camp_id, SYSDATE, p_units);
    COMMIT;-- Save transaction permanently
    
EXCEPTION
    -- Catch custom exception and return descriptive error to client
    WHEN e_donor_not_found THEN
        RAISE_APPLICATION_ERROR(-20005, 'Donation Failed: Registered Donor Not Found.');
END;
/



-- 3. Approve Hospital Request
CREATE OR REPLACE PROCEDURE approve_hospital_request (
    p_req_id IN NUMBER
)
IS
BEGIN
     -- Update status to 'Approved' for the pending record
    UPDATE hospital_requests 
    SET status = 'Approved' 
    WHERE request_id = p_req_id AND status = 'Pending';
    
    IF SQL%ROWCOUNT = 0 THEN
        DBMS_OUTPUT.PUT_LINE('No pending request found with this ID.');
    ELSE
        COMMIT;-- Save transaction
    END IF;
END;
/

-- 4. Reject Hospital Request
CREATE OR REPLACE PROCEDURE reject_hospital_request (
    p_req_id IN NUMBER
)
IS
BEGIN
-- Update status to 'Rejected'
    UPDATE hospital_requests 
    SET status = 'Rejected' 
    WHERE request_id = p_req_id AND status = 'Pending';
    COMMIT;
END;
/



-- 5. Update Camp Venue
CREATE OR REPLACE PROCEDURE update_camp_venue (
    p_camp_id IN NUMBER,
    p_new_venue IN VARCHAR2
)
IS
BEGIN
    UPDATE camps SET venue = p_new_venue WHERE camp_id = p_camp_id;
    COMMIT;
END;
/

-- 6. Assign Staff Member to Camp
CREATE OR REPLACE PROCEDURE assign_staff_to_camp (
    p_staff_id IN NUMBER,
    p_camp_id  IN NUMBER,
    p_task     IN VARCHAR2 DEFAULT 'General Support'
)
IS
    v_assign_id     NUMBER;
    v_staff_exists  NUMBER;
    v_camp_exists   NUMBER;
    e_invalid_staff EXCEPTION;
    e_invalid_camp  EXCEPTION;
BEGIN
    -- Validate staff existence
    SELECT COUNT(*) INTO v_staff_exists 
    FROM users 
    WHERE user_id = p_staff_id AND role = 'Staff';
    
    IF v_staff_exists = 0 THEN
        RAISE e_invalid_staff;
    END IF;

    -- Validate camp existence
    SELECT COUNT(*) INTO v_camp_exists 
    FROM camps 
    WHERE camp_id = p_camp_id;
    
    IF v_camp_exists = 0 THEN
        RAISE e_invalid_camp;
    END IF;

    -- Auto generate unique assignment_id
    SELECT NVL(MAX(assignment_id), 0) + 1 INTO v_assign_id FROM staff_assignments;

    -- Insert assignment
    INSERT INTO staff_assignments (assignment_id, staff_id, camp_id, task)
    VALUES (v_assign_id, p_staff_id, p_camp_id, NVL(TRIM(p_task), 'General Support'));
    
    COMMIT;
EXCEPTION
    WHEN e_invalid_staff THEN
        RAISE_APPLICATION_ERROR(-20012, 'Staff assignment failed: Staff member not found.');
    WHEN e_invalid_camp THEN
        RAISE_APPLICATION_ERROR(-20013, 'Staff assignment failed: Camp not found.');
    WHEN OTHERS THEN
        ROLLBACK;
        RAISE;
END;
/

-- ==============================================================================
-- FUNCTIONS (5)
-- ==============================================================================

-- 1. Get total available units for a specific blood group
CREATE OR REPLACE FUNCTION get_blood_stock (
    p_bg IN VARCHAR2
) RETURN NUMBER
IS
    v_units NUMBER;
BEGIN
    SELECT total_units INTO v_units 
    FROM blood_inventory 
    WHERE blood_group = p_bg;
    -- Return the available units of the specific blood group
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

-- 3.  Function: Count Total Registered Donors
CREATE OR REPLACE FUNCTION get_total_donors
RETURN NUMBER
IS
    v_count NUMBER;
BEGIN
-- Aggregate total registered donors count
    SELECT COUNT(*) INTO v_count FROM donors;
    RETURN v_count;
END;
/

-- 4.Function: Check Donor Medical Eligibility (4-Month Interval Check)
CREATE OR REPLACE FUNCTION check_donor_eligibility (
    p_donor_id IN NUMBER
) RETURN VARCHAR2
IS
    v_last_date DATE;
    v_months_diff NUMBER;
BEGIN

    -- Query the latest donation date for this donor
    SELECT MAX(donation_date) INTO v_last_date 
    FROM donation_history 
    WHERE donor_id = p_donor_id;
    
    -- If the donor has never donated before, they are immediately eligible
    IF v_last_date IS NULL THEN
        RETURN 'Eligible'; -- Never donated before
    END IF;
    
    -- Calculate difference in months from today
    v_months_diff := MONTHS_BETWEEN(SYSDATE, v_last_date);
    
    
    -- Check eligibility based on 4-month rule
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

-- ==============================================================================
-- TRIGGERS (5)
-- ==============================================================================

-- 1. Auto-increase blood inventory when a donation is added tRIGGER
CREATE OR REPLACE TRIGGER trg_increase_inventory
AFTER INSERT ON donation_history
FOR EACH ROW
DECLARE
    v_bg VARCHAR2(5);-- Variable to hold the donor's blood group
BEGIN
    -- sELECT Query the blood group of the donating donor
    SELECT blood_group INTO v_bg FROM donors WHERE donor_id = :NEW.donor_id;
    
    -- Automatically increment blood inventory units and update timestamp
    UPDATE blood_inventory 
    SET total_units = total_units + :NEW.blood_units, last_updated = SYSDATE 
    WHERE blood_group = v_bg;
END;
/

-- 2. Auto-decrease blood inventory when a request is approved
CREATE OR REPLACE TRIGGER trg_decrease_inventory
AFTER UPDATE OF status ON hospital_requests
FOR EACH ROW
WHEN (NEW.status = 'Approved')
BEGIN
    UPDATE blood_inventory 
    SET total_units = total_units - :NEW.units_needed, last_updated = SYSDATE 
    WHERE blood_group = :NEW.blood_group;
END;
/

-- 3. Validate hospital request stock before approval
CREATE OR REPLACE TRIGGER trg_check_stock_before_approve
BEFORE UPDATE OF status ON hospital_requests
FOR EACH ROW
WHEN (NEW.status = 'Approved')
DECLARE
    v_available NUMBER;
BEGIN
    SELECT total_units INTO v_available FROM blood_inventory WHERE blood_group = :NEW.blood_group;
    
    IF v_available < :NEW.units_needed THEN
        RAISE_APPLICATION_ERROR(-20010, 'Cannot approve: Insufficient blood stock for ' || :NEW.blood_group);
    END IF;
END;
/

-- 4. Prevent scheduling camps in the past
CREATE OR REPLACE TRIGGER trg_validate_camp_date
BEFORE INSERT OR UPDATE ON camps
FOR EACH ROW
BEGIN
    IF :NEW.camp_date < TRUNC(SYSDATE) THEN
        RAISE_APPLICATION_ERROR(-20011, 'Camp date cannot be in the past.');
    END IF;
END;
/

-- 5. Alert when inventory drops below 10 units (Example logic)
CREATE OR REPLACE TRIGGER trg_low_stock_alert
AFTER UPDATE ON blood_inventory
FOR EACH ROW
BEGIN
    IF :NEW.total_units < 10 AND :OLD.total_units >= 10 THEN
        DBMS_OUTPUT.PUT_LINE('CRITICAL ALERT: ' || :NEW.blood_group || ' stock is very low!');
    END IF;
END;
/

CREATE OR REPLACE VIEW vw_camp_blood_collection AS
SELECT 
    c.camp_name, 
    d.district_name, 
    don.blood_group, 
    SUM(dh.blood_units) AS total_collected
FROM donation_history dh
JOIN camps c ON dh.camp_id = c.camp_id
JOIN districts d ON c.district_id = d.district_id
JOIN donors don ON dh.donor_id = don.donor_id
GROUP BY c.camp_name, d.district_name, don.blood_group;