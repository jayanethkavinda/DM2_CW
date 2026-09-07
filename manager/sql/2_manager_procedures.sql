-- ==============================================================================
-- 2. MANAGER PROCEDURES
-- ==============================================================================

-- 1. Add a new camp
CREATE OR REPLACE PROCEDURE add_new_camp (
    p_camp_id   IN NUMBER,
    p_name      IN VARCHAR2,
    p_date      IN DATE,
    p_venue     IN VARCHAR2,
    p_district  IN NUMBER
)
IS
BEGIN
    INSERT INTO camps (camp_id, camp_name, camp_date, venue, district_id)
    VALUES (p_camp_id, TRIM(p_name), p_date, TRIM(p_venue), p_district);
    COMMIT;
    DBMS_OUTPUT.PUT_LINE('Camp scheduled successfully with ID: ' || p_camp_id);
EXCEPTION
    WHEN DUP_VAL_ON_INDEX THEN
        RAISE_APPLICATION_ERROR(-20001, 'Error: Camp ID already exists.');
    WHEN OTHERS THEN
        ROLLBACK;
        RAISE;
END;
/

-- 2. Record a blood donation (Uses Custom Exception)
CREATE OR REPLACE PROCEDURE add_donation_record (
    p_history_id IN NUMBER,
    p_donor_id   IN NUMBER,
    p_camp_id    IN NUMBER,
    p_units      IN NUMBER
)
IS
    v_donor_exists NUMBER;
    e_donor_not_found EXCEPTION;
BEGIN
    -- Check if donor exists
    SELECT COUNT(*) INTO v_donor_exists FROM donors WHERE donor_id = p_donor_id;
    
    IF v_donor_exists = 0 THEN
        RAISE e_donor_not_found;
    END IF;

    INSERT INTO donation_history (history_id, donor_id, camp_id, donation_date, blood_units)
    VALUES (p_history_id, p_donor_id, p_camp_id, SYSDATE, p_units);
    COMMIT;
    DBMS_OUTPUT.PUT_LINE('Donation record added successfully.');
    
EXCEPTION
    WHEN e_donor_not_found THEN
        RAISE_APPLICATION_ERROR(-20005, 'Donation Failed: Registered Donor Not Found.');
    WHEN DUP_VAL_ON_INDEX THEN
        RAISE_APPLICATION_ERROR(-20006, 'Error: History ID already exists.');
    WHEN OTHERS THEN
        ROLLBACK;
        RAISE;
END;
/

-- 3. Approve Hospital Request
CREATE OR REPLACE PROCEDURE approve_hospital_request (
    p_req_id IN NUMBER
)
IS
    v_req_count NUMBER;
    e_request_not_found EXCEPTION;
BEGIN
    SELECT COUNT(*) INTO v_req_count 
    FROM hospital_requests 
    WHERE request_id = p_req_id AND status = 'Pending';

    IF v_req_count = 0 THEN
        RAISE e_request_not_found;
    END IF;

    UPDATE hospital_requests 
    SET status = 'Approved' 
    WHERE request_id = p_req_id AND status = 'Pending';
    
    COMMIT;
    DBMS_OUTPUT.PUT_LINE('Hospital request approved successfully.');

EXCEPTION
    WHEN e_request_not_found THEN
        RAISE_APPLICATION_ERROR(-20007, 'No pending request found with ID: ' || p_req_id);
    WHEN OTHERS THEN
        ROLLBACK;
        RAISE;
END;
/

-- 4. Reject Hospital Request
CREATE OR REPLACE PROCEDURE reject_hospital_request (
    p_req_id IN NUMBER
)
IS
BEGIN
    UPDATE hospital_requests 
    SET status = 'Rejected' 
    WHERE request_id = p_req_id AND status = 'Pending';
    
    IF SQL%ROWCOUNT = 0 THEN
        RAISE_APPLICATION_ERROR(-20007, 'No pending request found with this ID.');
    ELSE
        COMMIT;
        DBMS_OUTPUT.PUT_LINE('Hospital request rejected.');
    END IF;
END;
/

-- 5. Update Camp Venue
CREATE OR REPLACE PROCEDURE update_camp_venue (
    p_camp_id   IN NUMBER,
    p_new_venue IN VARCHAR2
)
IS
BEGIN
    UPDATE camps 
    SET venue = TRIM(p_new_venue) 
    WHERE camp_id = p_camp_id;
    
    IF SQL%ROWCOUNT = 0 THEN
        RAISE_APPLICATION_ERROR(-20008, 'Camp not found with ID: ' || p_camp_id);
    ELSE
        COMMIT;
        DBMS_OUTPUT.PUT_LINE('Camp venue updated successfully.');
    END IF;
END;
/

-- 6. Assign Staff to Camp (Stored Procedure for Multi-Staff Allocation)
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
    -- Check if staff exists and has 'Staff' role
    SELECT COUNT(*) INTO v_staff_exists 
    FROM users 
    WHERE user_id = p_staff_id AND role = 'Staff';
    
    IF v_staff_exists = 0 THEN
        RAISE e_invalid_staff;
    END IF;

    -- Check if camp exists
    SELECT COUNT(*) INTO v_camp_exists 
    FROM camps 
    WHERE camp_id = p_camp_id;
    
    IF v_camp_exists = 0 THEN
        RAISE e_invalid_camp;
    END IF;

    -- Auto generate unique assignment_id
    SELECT NVL(MAX(assignment_id), 0) + 1 INTO v_assign_id FROM staff_assignments;

    -- Insert assignment record
    INSERT INTO staff_assignments (assignment_id, staff_id, camp_id, task)
    VALUES (v_assign_id, p_staff_id, p_camp_id, NVL(TRIM(p_task), 'General Support'));
    
    COMMIT;
    DBMS_OUTPUT.PUT_LINE('Staff ID ' || p_staff_id || ' assigned to Camp ID ' || p_camp_id || ' successfully.');

EXCEPTION
    WHEN e_invalid_staff THEN
        RAISE_APPLICATION_ERROR(-20012, 'Staff assignment failed: Staff member not found with ID ' || p_staff_id);
    WHEN e_invalid_camp THEN
        RAISE_APPLICATION_ERROR(-20013, 'Staff assignment failed: Camp not found with ID ' || p_camp_id);
    WHEN OTHERS THEN
        ROLLBACK;
        RAISE;
END;
/
