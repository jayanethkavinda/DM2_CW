-- ==============================================================================
-- LIFE LINE CONNECT - SYSTEM ADMIN MODULE
-- Feature: Registered Districts List & District Deletion
-- (Extracted directly from system_admin/PL_SQL.sql)
-- ==============================================================================

SET SERVEROUTPUT ON;

-- 1. Remove District Stored Procedure (Includes Custom Exception for Not Found)
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


-- 2. View: District Overview with Camp Count (District Summary View)
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
