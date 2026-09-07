-- ==============================================================================
-- LIFE LINE CONNECT - SYSTEM ADMIN MODULE
-- Feature: System Accounts & Roles Breakdown
-- (Extracted directly from system_admin/PL_SQL.sql)
-- ==============================================================================

SET SERVEROUTPUT ON;

-- 1. View: All System Users with Roles & Account Types Breakdown
CREATE OR REPLACE VIEW vw_system_users AS
SELECT 
    user_id, 
    email, 
    role,
    -- Transform short role codes into user-friendly descriptive titles
    CASE 
        WHEN role = 'Admin' THEN 'System Administrator'
        WHEN role = 'Staff' THEN 'Blood Bank Staff'
        WHEN role = 'Donor' THEN 'Registered Donor'
        ELSE role
    END AS role_title
FROM users;
/


-- 2. Function: Get Total System Users Count
CREATE OR REPLACE FUNCTION get_total_users_count
RETURN NUMBER
IS
    v_count NUMBER;
BEGIN
    SELECT COUNT(*) INTO v_count FROM users;
    RETURN v_count;
END;
/


-- 3. Function: Get User Role by User ID
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


-- 4. Trigger: Validate User Role on Insert/Update
CREATE OR REPLACE TRIGGER trg_validate_user_role
BEFORE INSERT OR UPDATE OF role ON users
FOR EACH ROW
BEGIN
    IF :NEW.role NOT IN ('Admin', 'Staff', 'Donor', 'Hospital') THEN
        RAISE_APPLICATION_ERROR(-20011, 'Invalid Role: Role must be either Admin, Staff, Donor, or Hospital.');
    END IF;
END;
/
