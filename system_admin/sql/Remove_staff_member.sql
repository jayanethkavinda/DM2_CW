-- ==============================================================================
-- LIFE LINE CONNECT - SYSTEM ADMIN MODULE
-- Feature: Remove Staff Member
-- (Extracted directly from system_admin/PL_SQL.sql)
-- ==============================================================================

SET SERVEROUTPUT ON;

-- 1. Remove Staff User Stored Procedure (Includes User-Defined Custom Exception)
CREATE OR REPLACE PROCEDURE remove_staff_user (
    p_user_id IN NUMBER
)
IS
     -- Declare user-defined exception for non-existing staff records
    e_user_not_found EXCEPTION;
BEGIN
     -- Delete the staff record matching the ID and role
    DELETE FROM users 
    WHERE user_id = p_user_id AND role = 'Staff';
     -- Check if any row was affected by the DELETE query
    IF SQL%ROWCOUNT = 0 THEN
        RAISE e_user_not_found;
    ELSE
        COMMIT; -- Persist transaction permanently
        DBMS_OUTPUT.PUT_LINE('Staff user ID ' || p_user_id || ' removed successfully.');
    END IF;
    
EXCEPTION
    -- Handle the custom user-defined exception
    WHEN e_user_not_found THEN
        RAISE_APPLICATION_ERROR(-20002, 'Error: Staff member not found or already removed.');
     -- General exception block to rollback any unexpected failure    
    WHEN OTHERS THEN
        ROLLBACK;
        RAISE;
END;
/


-- 2. Trigger: Notify after a User is deleted (Audit Logging)
CREATE OR REPLACE TRIGGER user_after_delete
AFTER DELETE ON users
FOR EACH ROW
BEGIN 
    -- Audit log message indicating user deletion
    DBMS_OUTPUT.PUT_LINE('User record (ID: ' || :OLD.user_id || ', Role: ' || :OLD.role || ') has been deleted successfully.');
END;
/


-- 3. Trigger: Prevent Deleting System Administrator Accounts (Security Protection)
CREATE OR REPLACE TRIGGER trg_prevent_admin_delete
BEFORE DELETE ON users
FOR EACH ROW
BEGIN
     -- Ensure Admin roles cannot be deleted via any delete statement
    IF :OLD.role = 'Admin' THEN
        RAISE_APPLICATION_ERROR(-20010, 'Security Restriction: Primary Administrator accounts cannot be deleted.');
    END IF;
END;
/


-- 4. View: Staff Members Only
CREATE OR REPLACE VIEW vw_staff_members AS
SELECT 
    user_id AS staff_id, 
    email, 
    role
FROM users
WHERE role = 'Staff';
/


-- 5. Function: Get Staff Count
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
