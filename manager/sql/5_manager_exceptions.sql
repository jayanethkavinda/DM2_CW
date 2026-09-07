-- ==============================================================================
-- 5. MANAGER EXCEPTION HANDLING
-- ==============================================================================

-- Demonstration Block: Safe Blood Inventory Adjustment with Custom Exception Handling
CREATE OR REPLACE PROCEDURE safe_issue_blood_units (
    p_blood_group IN VARCHAR2,
    p_units       IN NUMBER
)
IS
    -- Custom User-defined Exceptions
    e_insufficient_stock EXCEPTION;
    e_invalid_units      EXCEPTION;
    e_invalid_blood_group EXCEPTION;
    
    v_available NUMBER;
    v_bg_exists NUMBER;
BEGIN
    -- Validate Units
    IF p_units <= 0 THEN
        RAISE e_invalid_units;
    END IF;

    -- Validate Blood Group existence
    SELECT COUNT(*) INTO v_bg_exists FROM blood_inventory WHERE blood_group = p_blood_group;
    IF v_bg_exists = 0 THEN
        RAISE e_invalid_blood_group;
    END IF;

    -- Check available stock
    SELECT total_units INTO v_available FROM blood_inventory WHERE blood_group = p_blood_group;
    IF v_available < p_units THEN
        RAISE e_insufficient_stock;
    END IF;

    -- Deduct stock
    UPDATE blood_inventory 
    SET total_units = total_units - p_units, 
        last_updated = SYSDATE 
    WHERE blood_group = p_blood_group;

    COMMIT;
    DBMS_OUTPUT.PUT_LINE('Success: ' || p_units || ' units issued for ' || p_blood_group || '. Remaining: ' || (v_available - p_units));

EXCEPTION
    WHEN e_invalid_units THEN
        RAISE_APPLICATION_ERROR(-20015, 'Validation Error: Units requested must be greater than zero.');
    WHEN e_invalid_blood_group THEN
        RAISE_APPLICATION_ERROR(-20016, 'Validation Error: Blood group does not exist in inventory.');
    WHEN e_insufficient_stock THEN
        RAISE_APPLICATION_ERROR(-20017, 'Inventory Error: Insufficient blood stock for group ' || p_blood_group);
    WHEN OTHERS THEN
        ROLLBACK;
        RAISE_APPLICATION_ERROR(-20019, 'Unexpected Error: ' || SQLERRM);
END;
/
