-- ==============================================================================
-- 1. MANAGER VIRTUAL TABLES & VIEWS
-- ==============================================================================

-- 1. View: To see upcoming camps with district names
CREATE OR REPLACE VIEW vw_upcoming_camps AS
SELECT 
    c.camp_id, 
    c.camp_name, 
    c.camp_date, 
    TO_CHAR(c.camp_date, 'YYYY-MM-DD') AS formatted_camp_date,
    c.venue, 
    d.district_id,
    d.district_name
FROM camps c
JOIN districts d ON c.district_id = d.district_id
WHERE c.camp_date >= TRUNC(SYSDATE);

-- 2. View: Camp-wise blood collection summary with blood groups
CREATE OR REPLACE VIEW vw_camp_blood_collection AS
SELECT 
    c.camp_name,
    d.district_name,
    dn.blood_group,
    SUM(dh.blood_units) AS total_collected
FROM donation_history dh
JOIN camps c ON dh.camp_id = c.camp_id
JOIN districts d ON c.district_id = d.district_id
JOIN donors dn ON dh.donor_id = dn.donor_id
GROUP BY c.camp_name, d.district_name, dn.blood_group;

-- 3. Materialized View: Monthly Blood Collection Report (Refreshes on demand)
CREATE MATERIALIZED VIEW mv_monthly_collection
BUILD IMMEDIATE
REFRESH ON DEMAND
AS
SELECT 
    c.camp_name, 
    EXTRACT(MONTH FROM d.donation_date) AS month, 
    SUM(d.blood_units) AS total_collected
FROM donation_history d
JOIN camps c ON d.camp_id = c.camp_id
GROUP BY c.camp_name, EXTRACT(MONTH FROM d.donation_date);
