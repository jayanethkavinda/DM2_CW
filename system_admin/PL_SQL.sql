SET SERVEROUTPUT ON;

-- ==============================================================================
-- LIFE LINE CONNECT - SYSTEM ADMIN MODULE PL/SQL SCRIPTS
-- Master Consolidated File
-- Contains:
--  1. Virtual Tables (Views)
--  2. Stored Procedures (Add Staff, Remove Staff, Reset Password, Manage Districts)
--  3. Functions (Staff Count, Users Count, Districts Count, Role Getter)
--  4. Database Triggers (User Deletion Logger, Admin Delete Blocker, Role Check)
--  5. Custom Exception Handling Demonstrations
-- ==============================================================================


-- ==============================================================================
-- 1. VIRTUAL TABLES & VIEWS
-- ==============================================================================

-- 1. View: All System Users with Roles
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

-- 2. View: Staff Members Only
CREATE OR REPLACE VIEW vw_staff_members AS
SELECT 
    user_id AS staff_id, 
    email, 
    role
FROM users
WHERE role = 'Staff';

-- 3. View: District Overview with Camp Count
CREATE OR REPLACE VIEW vw_district_summary AS
SELECT 
    d.district_id,
    d.district_name,
    COUNT(c.camp_id) AS total_camps
FROM districts d
LEFT JOIN camps c ON d.district_id = c.district_id
GROUP BY d.district_id, d.district_name;


-- ==============================================================================
-- 2. STORED PROCEDURES
-- ==============================================================================

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

-- 2. Remove Staff User (Includes Custom Exception)
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

-- 3. Update Staff Password
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

-- 4. Add District (System Setup)
CREATE OR REPLACE PROCEDURE add_district (
    p_district_id   IN NUMBER,
    p_district_name IN VARCHAR2
)
IS
BEGIN
    INSERT INTO districts (district_id, district_name)
    VALUES (p_district_id, TRIM(p_district_name));
    
    COMMIT;
    DBMS_OUTPUT.PUT_LINE('District added successfully.');
    
EXCEPTION
    WHEN DUP_VAL_ON_INDEX THEN
        RAISE_APPLICATION_ERROR(-20004, 'Error: District ID already exists.');
    WHEN OTHERS THEN
        ROLLBACK;
        RAISE;
END;
/

-- 5. Delete District
CREATE OR REPLACE PROCEDURE remove_district (
    p_district_id IN NUMBER
)
IS
BEGIN
    DELETE FROM districts WHERE district_id = p_district_id;
    
    IF SQL%ROWCOUNT = 0 THEN
        RAISE_APPLICATION_ERROR(-20005, 'District ID not found.');
    ELSE
        COMMIT;
        DBMS_OUTPUT.PUT_LINE('District removed successfully.');
    END IF;
END;
/


-- ==============================================================================
-- 3. FUNCTIONS
-- ==============================================================================

-- 1. Get Staff Count
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

-- 2. Get Total Users Count
CREATE OR REPLACE FUNCTION get_total_users_count
RETURN NUMBER
IS
    v_count NUMBER;
BEGIN
    SELECT COUNT(*) INTO v_count FROM users;
    RETURN v_count;
END;
/

-- 3. Get Total Registered Districts Count
CREATE OR REPLACE FUNCTION get_districts_count
RETURN NUMBER
IS
    v_count NUMBER;
BEGIN
    SELECT COUNT(*) INTO v_count FROM districts;
    RETURN v_count;
END;
/

-- 4. Get User Role by User ID
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


-- ==============================================================================
-- 4. TRIGGERS
-- ==============================================================================

-- 1. Notify after a User is deleted
CREATE OR REPLACE TRIGGER user_after_delete
AFTER DELETE ON users
FOR EACH ROW
BEGIN 
    -- Audit log message indicating user deletion
    DBMS_OUTPUT.PUT_LINE('User record (ID: ' || :OLD.user_id || ', Role: ' || :OLD.role || ') has been deleted successfully.');
END;
/

-- 2. Prevent Deleting System Administrator Accounts
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

-- 3. Validate User Role on Insert/Update
CREATE OR REPLACE TRIGGER trg_validate_user_role
BEFORE INSERT OR UPDATE OF role ON users
FOR EACH ROW
BEGIN
    IF :NEW.role NOT IN ('Admin', 'Staff', 'Donor', 'Hospital') THEN
        RAISE_APPLICATION_ERROR(-20011, 'Invalid Role: Role must be either Admin, Staff, Donor, or Hospital.');
    END IF;
END;
/

COMMIT;