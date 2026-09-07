-- ==============================================================================
-- 5. DONOR EXCEPTION HANDLING
-- ==============================================================================

-- Demonstration Block: Safe Donor Registration with Complete Custom Exception Handling
CREATE OR REPLACE PROCEDURE safe_register_donor_demo (
    p_email      IN VARCHAR2,
    p_password   IN VARCHAR2,
    p_full_name  IN VARCHAR2,
    p_dob        IN DATE,
    p_bg         IN VARCHAR2
)
IS
    -- Custom User-defined Exceptions
    e_underage_donor       EXCEPTION;
    e_invalid_email_format EXCEPTION;
    e_invalid_blood_group  EXCEPTION;
    
    v_age       NUMBER;
    v_bg_exists NUMBER;
    v_new_uid   NUMBER;
    v_new_did   NUMBER;
BEGIN
    -- Check Email Format (Must contain @ and .)
    IF INSTR(p_email, '@') = 0 OR INSTR(p_email, '.') = 0 THEN
        RAISE e_invalid_email_format;
    END IF;

    -- Check Age
    IF p_dob IS NOT NULL THEN
        v_age := TRUNC(MONTHS_BETWEEN(SYSDATE, p_dob)/12);
        IF v_age < 18 THEN
            RAISE e_underage_donor;
        END IF;
    END IF;

    -- Check Blood Group
    IF p_bg IS NOT NULL THEN
        SELECT COUNT(*) INTO v_bg_exists FROM blood_inventory WHERE blood_group = p_bg;
        IF v_bg_exists = 0 THEN
            RAISE e_invalid_blood_group;
        END IF;
    END IF;

    -- Insert User
    SELECT NVL(MAX(user_id), 0) + 1 INTO v_new_uid FROM users;
    INSERT INTO users (user_id, email, password, role) 
    VALUES (v_new_uid, LOWER(TRIM(p_email)), p_password, 'Donor');

    -- Insert Donor
    SELECT NVL(MAX(donor_id), 0) + 1 INTO v_new_did FROM donors;
    INSERT INTO donors (donor_id, user_id, full_name, dob, blood_group)
    VALUES (v_new_did, v_new_uid, p_full_name, p_dob, p_bg);

    COMMIT;
    DBMS_OUTPUT.PUT_LINE('Success: Registered Donor with ID ' || v_new_did);

EXCEPTION
    WHEN e_invalid_email_format THEN
        RAISE_APPLICATION_ERROR(-20030, 'Validation Error: Email address format is invalid.');
    WHEN e_underage_donor THEN
        RAISE_APPLICATION_ERROR(-20031, 'Validation Error: Donor must be at least 18 years old to register.');
    WHEN e_invalid_blood_group THEN
        RAISE_APPLICATION_ERROR(-20032, 'Validation Error: Provided Blood Group does not exist in inventory table.');
    WHEN DUP_VAL_ON_INDEX THEN
        RAISE_APPLICATION_ERROR(-20033, 'Database Error: A user with this email or ID already exists in the system.');
    WHEN OTHERS THEN
        ROLLBACK;
        RAISE_APPLICATION_ERROR(-20039, 'Unexpected Error: ' || SQLERRM);
END;
/
