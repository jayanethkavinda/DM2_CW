-- ==============================================================================
-- 4. DONOR TRIGGERS
-- ==============================================================================

-- 1. Validate Donor Age on Profile Insert/Update (Must be >= 18 years)
CREATE OR REPLACE TRIGGER trg_validate_donor_dob
BEFORE INSERT OR UPDATE OF dob ON donors
FOR EACH ROW
WHEN (NEW.dob IS NOT NULL)
DECLARE
    v_age NUMBER;
BEGIN
    v_age := TRUNC(MONTHS_BETWEEN(SYSDATE, :NEW.dob)/12);
    IF v_age < 18 THEN
        RAISE_APPLICATION_ERROR(-20020, 'Eligibility Violation: Blood donor must be at least 18 years old.');
    ELSIF :NEW.dob > SYSDATE THEN
        RAISE_APPLICATION_ERROR(-20025, 'Invalid Date: Date of birth cannot be in the future.');
    END IF;
END;
/

-- 2. Format User Email to Lowercase automatically
CREATE OR REPLACE TRIGGER trg_donor_email_lowercase
BEFORE INSERT OR UPDATE OF email ON users
FOR EACH ROW
BEGIN
    :NEW.email := LOWER(TRIM(:NEW.email));
END;
/

-- 3. Prevent Future Dates on Donation History
CREATE OR REPLACE TRIGGER trg_prevent_future_donation
BEFORE INSERT OR UPDATE OF donation_date ON donation_history
FOR EACH ROW
BEGIN
    IF :NEW.donation_date > SYSDATE THEN
        RAISE_APPLICATION_ERROR(-20026, 'Invalid Date: Donation date cannot be in the future.');
    END IF;
END;
/

-- 4. Notify on Donor Profile Update
CREATE OR REPLACE TRIGGER trg_log_donor_profile_update
AFTER UPDATE ON donors
FOR EACH ROW
BEGIN
    DBMS_OUTPUT.PUT_LINE('Profile updated for Donor ID: ' || :NEW.donor_id || ' (' || :NEW.full_name || ')');
END;
/
