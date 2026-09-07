-- ==============================================================================
-- 1. DONOR VIRTUAL TABLES & VIEWS
-- ==============================================================================

-- View 1: Upcoming Camps for Donors with District Name and Status
CREATE OR REPLACE VIEW vw_donor_upcoming_camps AS
SELECT 
    c.camp_id,
    c.camp_name,
    c.camp_date,
    TO_CHAR(c.camp_date, 'YYYY-MM-DD') AS formatted_date,
    c.venue,
    d.district_id,
    d.district_name,
    CASE 
        WHEN c.camp_date = TRUNC(SYSDATE) THEN 'Today'
        WHEN c.camp_date > TRUNC(SYSDATE) THEN 'Upcoming'
        ELSE 'Past'
    END AS camp_status
FROM camps c
JOIN districts d ON c.district_id = d.district_id
WHERE c.camp_date >= TRUNC(SYSDATE);

-- View 2: Complete Donation History per Donor with Camp and District Details
CREATE OR REPLACE VIEW vw_donor_donation_history AS
SELECT 
    dh.history_id,
    dh.donor_id,
    dn.full_name AS donor_name,
    dn.blood_group,
    c.camp_id,
    c.camp_name,
    c.venue,
    d.district_name,
    dh.donation_date,
    TO_CHAR(dh.donation_date, 'YYYY-MM-DD') AS formatted_donation_date,
    dh.blood_units
FROM donation_history dh
JOIN donors dn ON dh.donor_id = dn.donor_id
JOIN camps c ON dh.camp_id = c.camp_id
JOIN districts d ON c.district_id = d.district_id;

-- View 3: Donor Profile Summary with User Account Info & District Info
CREATE OR REPLACE VIEW vw_donor_profile_details AS
SELECT 
    d.donor_id,
    d.user_id,
    u.email,
    d.full_name,
    d.dob,
    TO_CHAR(d.dob, 'YYYY-MM-DD') AS formatted_dob,
    TRUNC(MONTHS_BETWEEN(SYSDATE, d.dob)/12) AS age,
    d.gender,
    d.blood_group,
    d.contact_no,
    d.address,
    d.district_id,
    dist.district_name
FROM donors d
JOIN users u ON d.user_id = u.user_id
LEFT JOIN districts dist ON d.district_id = dist.district_id;
