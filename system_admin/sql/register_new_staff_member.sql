-- ==============================================================================
-- LIFE LINE CONNECT - SYSTEM ADMIN MODULE
-- Feature: Register New Staff Member
-- (Extracted directly from system_admin/PL_SQL.sql)
-- ==============================================================================

SET SERVEROUTPUT ON;

-- 1. Add Staff User (Includes In-built Exception 'DUP_VAL_ON_INDEX')
CREATE OR REPLACE PROCEDURE add_staff_user (
    p_user_id  IN NUMBER,
    p_email    IN VARCHAR2,
    p_password IN VARCHAR2
)
IS
BEGIN
     -- Email  trim and lowercase  convert into Role 'Staff'
    INSERT INTO users (user_id, email, password, role)
    VALUES (p_user_id, LOWER(TRIM(p_email)), p_password, 'Staff');
    
    COMMIT;
    DBMS_OUTPUT.PUT_LINE('Staff user added successfully with ID: ' || p_user_id);
    
EXCEPTION
   
    -- 1. System-Defined Exception Handling (Duplicate Key Error)
    WHEN DUP_VAL_ON_INDEX THEN
        RAISE_APPLICATION_ERROR(-20001, 'Error: This Email or User ID already exists.');
    -- 2. General Exception Handling (Rollback to protect ACID properties)    
    WHEN OTHERS THEN
        ROLLBACK;
        RAISE;
END;
/


-- 2. Validate User Role on Insert/Update Trigger
CREATE OR REPLACE TRIGGER trg_validate_user_role
BEFORE INSERT OR UPDATE OF role ON users
FOR EACH ROW
BEGIN
    IF :NEW.role NOT IN ('Admin', 'Staff', 'Donor', 'Hospital') THEN
        RAISE_APPLICATION_ERROR(-20011, 'Invalid Role: Role must be either Admin, Staff, Donor, or Hospital.');
    END IF;
END;
/


-- 3. View: Staff Members Only
CREATE OR REPLACE VIEW vw_staff_members AS
SELECT 
    user_id AS staff_id, 
    email, 
    role
FROM users
WHERE role = 'Staff';
/


-- 4. Function: Get Staff Count
CREATE OR REPLACE FUNCTION get_staff_count
RETURN NUMBER
IS
    v_count NUMBER;
BEGIN
    SELECT COUNT(*) INTO v_count 
    FROM users 
    WHERE role = 'Staff';
    
    RETURN v_count;
END;
/
