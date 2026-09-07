-- ==============================================================================
-- 4. MANAGER TRIGGERS
-- ==============================================================================

-- 1. Auto-increase blood inventory when a donation is added
CREATE OR REPLACE TRIGGER trg_increase_inventory
AFTER INSERT ON donation_history
FOR EACH ROW
DECLARE
    v_bg VARCHAR2(5);
BEGIN
    -- Get blood group of the donor
    SELECT blood_group INTO v_bg FROM donors WHERE donor_id = :NEW.donor_id;
    
    -- Update inventory
    UPDATE blood_inventory 
    SET total_units = total_units + :NEW.blood_units, 
        last_updated = SYSDATE 
    WHERE blood_group = v_bg;
END;
/

-- 2. Auto-decrease blood inventory when a request is approved
CREATE OR REPLACE TRIGGER trg_decrease_inventory
AFTER UPDATE OF status ON hospital_requests
FOR EACH ROW
WHEN (NEW.status = 'Approved')
BEGIN
    UPDATE blood_inventory 
    SET total_units = total_units - :NEW.units_needed, 
        last_updated = SYSDATE 
    WHERE blood_group = :NEW.blood_group;
END;
/

-- 3. Validate hospital request stock before approval
CREATE OR REPLACE TRIGGER trg_check_stock_before_approve
BEFORE UPDATE OF status ON hospital_requests
FOR EACH ROW
WHEN (NEW.status = 'Approved')
DECLARE
    v_available NUMBER;
BEGIN
    SELECT total_units INTO v_available FROM blood_inventory WHERE blood_group = :NEW.blood_group;
    
    IF v_available < :NEW.units_needed THEN
        RAISE_APPLICATION_ERROR(-20010, 'Cannot approve: Insufficient blood stock for ' || :NEW.blood_group || ' (Available: ' || v_available || ', Needed: ' || :NEW.units_needed || ')');
    END IF;
END;
/

-- 4. Prevent scheduling camps in the past
CREATE OR REPLACE TRIGGER trg_validate_camp_date
BEFORE INSERT OR UPDATE ON camps
FOR EACH ROW
BEGIN
    IF :NEW.camp_date < TRUNC(SYSDATE) THEN
        RAISE_APPLICATION_ERROR(-20011, 'Camp date cannot be in the past.');
    END IF;
END;
/

-- 5. Alert when inventory drops below 10 units
CREATE OR REPLACE TRIGGER trg_low_stock_alert
AFTER UPDATE ON blood_inventory
FOR EACH ROW
BEGIN
    IF :NEW.total_units < 10 AND :OLD.total_units >= 10 THEN
        DBMS_OUTPUT.PUT_LINE('CRITICAL ALERT: ' || :NEW.blood_group || ' stock is very low (Units: ' || :NEW.total_units || ')!');
    END IF;
END;
/
