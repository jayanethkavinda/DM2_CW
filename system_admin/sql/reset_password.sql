-- ==============================================================================
-- LIFE LINE CONNECT - SYSTEM ADMIN MODULE
-- Feature: Reset Staff Password
-- (Extracted directly from system_admin/PL_SQL.sql)
-- ==============================================================================

SET SERVEROUTPUT ON;

-- 1. Update Staff Password Stored Procedure (Includes Custom Exception Handling)
CREATE OR REPLACE PROCEDURE update_staff_password (
    p_user_id      IN NUMBER,
    p_new_password IN VARCHAR2
)
IS
BEGIN
     -- Update the password for the specified user with 'Staff' role
    UPDATE users 
    SET password = p_new_password 
    WHERE user_id = p_user_id AND role = 'Staff';
   -- Check if any matching row was updated 
    IF SQL%ROWCOUNT = 0 THEN
     -- Raise custom application error if the Staff ID is invalid
        RAISE_APPLICATION_ERROR(-20003, 'Error: Staff user not found.');
    ELSE
        COMMIT;-- Save transaction changes permanently
        DBMS_OUTPUT.PUT_LINE('Staff password updated successfully.');
    END IF;
END;
/


-- 2. Function: Get User Role by User ID
CREATE OR REPLACE FUNCTION get_user_role (
    p_user_id IN NUMBER -- Input Parameter: User ID
) RETURN VARCHAR2       -- Output Type: return role string type
IS
    v_role VARCHAR2(20);
BEGIN
  -- get a role from user table for particluar user id 
    SELECT role INTO v_role FROM users WHERE user_id = p_user_id;
    RETURN v_role;
EXCEPTION
-- not have a role in user table so return Not Found
    WHEN NO_DATA_FOUND THEN
        RETURN 'Not Found';
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
