-- ==============================================================================
-- LIFE LINE CONNECT - SYSTEM ADMIN MODULE
-- Feature: Add New District
-- (Extracted directly from system_admin/PL_SQL.sql)
-- ==============================================================================

SET SERVEROUTPUT ON;

-- 1. Add District Stored Procedure (Includes In-built Exception 'DUP_VAL_ON_INDEX')
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


-- 2. View: District Overview with Camp Count
CREATE OR REPLACE VIEW vw_district_summary AS
SELECT 
    d.district_id,
    d.district_name,
    COUNT(c.camp_id) AS total_camps
FROM districts d
LEFT JOIN camps c ON d.district_id = c.district_id
GROUP BY d.district_id, d.district_name;
/


-- 3. Function: Get Total Registered Districts Count
CREATE OR REPLACE FUNCTION get_districts_count
RETURN NUMBER
IS
    v_count NUMBER;
BEGIN
    SELECT COUNT(*) INTO v_count FROM districts;
    RETURN v_count;
END;
/
