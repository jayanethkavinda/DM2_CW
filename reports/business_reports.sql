-- ==============================================================================
-- LIFELINECONNECT - 5 PL/SQL BUSINESS REPORTS (SYS_REFCURSOR ARCHITECTURE)
-- Developed for NIBM Data Management 2 Coursework
-- Module: Data Management 2 (HDSE)
-- 
-- Features:
--  1. Oracle SYS_REFCURSOR Output Parameters to return structured datasets to PHP UI.
--  2. PL/SQL Stored Procedures with Input Filters.
--  3. Aggregation, Business Calculations, & Status Classifications.
--  4. Exception Handling for invalid filters or missing data.
-- ==============================================================================


-- ==============================================================================
-- REPORT 1: CAMP BLOOD COLLECTION BY BLOOD GROUP REPORT
-- Description: Returns structured rows for camp blood collection breakdown per blood group.
-- ==============================================================================
CREATE OR REPLACE PROCEDURE rpt_camp_blood_collection (
    p_camp_id IN NUMBER DEFAULT NULL,
    p_cursor  OUT SYS_REFCURSOR
) IS
BEGIN
    OPEN p_cursor FOR
        SELECT 
            c.camp_id,
            c.camp_name,
            c.venue,
            TO_CHAR(c.camp_date, 'YYYY-MM-DD') AS c_date,
            d.blood_group,
            COUNT(dh.history_id) AS donor_count,
            NVL(SUM(dh.blood_units), 0) AS units_collected,
            CASE 
                WHEN NVL(SUM(dh.blood_units), 0) >= 10 THEN 'High Yield'
                WHEN NVL(SUM(dh.blood_units), 0) >= 5  THEN 'Moderate'
                ELSE 'Standard'
            END AS yield_status
        FROM camps c
        JOIN donation_history dh ON c.camp_id = dh.camp_id
        JOIN donors d ON dh.donor_id = d.donor_id
        WHERE (p_camp_id IS NULL OR c.camp_id = p_camp_id)
        GROUP BY c.camp_id, c.camp_name, c.venue, c.camp_date, d.blood_group
        ORDER BY c.camp_id, d.blood_group;
EXCEPTION
    WHEN OTHERS THEN
        OPEN p_cursor FOR SELECT NULL AS error_msg FROM dual WHERE 1=0;
END rpt_camp_blood_collection;
/


-- ==============================================================================
-- REPORT 2: BLOOD INVENTORY & CRITICAL STOCK ANALYSIS REPORT
-- Description: Analyzes inventory stock levels and calculates shortage risks.
-- ==============================================================================
CREATE OR REPLACE PROCEDURE rpt_inventory_expiry_alert (
    p_critical_threshold IN NUMBER DEFAULT 10,
    p_cursor             OUT SYS_REFCURSOR
) IS
BEGIN
    OPEN p_cursor FOR
        SELECT 
            bi.blood_group,
            bi.total_units,
            TO_CHAR(bi.last_updated, 'YYYY-MM-DD HH24:MI') AS last_updated_date,
            NVL(ROUND(SYSDATE - bi.last_updated), 0) AS days_since_update,
            CASE 
                WHEN bi.total_units = 0 THEN 'Empty (Critical)'
                WHEN bi.total_units < p_critical_threshold THEN 'Low Stock Alert'
                WHEN bi.total_units >= 50 THEN 'Surplus Reserve'
                ELSE 'Adequate Stock'
            END AS stock_status,
            CASE 
                WHEN bi.total_units = 0 THEN 'danger'
                WHEN bi.total_units < p_critical_threshold THEN 'warning'
                ELSE 'success'
            END AS alert_level,
            CASE 
                WHEN bi.total_units = 0 THEN 'Immediate Emergency Broadcast Appeal'
                WHEN bi.total_units < p_critical_threshold THEN 'Schedule Targeted Donation Camp'
                ELSE 'Maintain Regular Supply'
            END AS recommended_action
        FROM blood_inventory bi
        ORDER BY bi.total_units ASC;
EXCEPTION
    WHEN OTHERS THEN
        OPEN p_cursor FOR SELECT NULL AS error_msg FROM dual WHERE 1=0;
END rpt_inventory_expiry_alert;
/


-- ==============================================================================
-- REPORT 3: INDIVIDUAL DONOR ELIGIBILITY & LIFETIME DOSSIER REPORT
-- Description: Returns donor metrics (Dossier Header) and history timeline rows.
-- ==============================================================================
CREATE OR REPLACE PROCEDURE rpt_donor_eligibility_summary (
    p_donor_id       IN NUMBER,
    p_donor_cursor   OUT SYS_REFCURSOR,
    p_history_cursor OUT SYS_REFCURSOR
) IS
BEGIN
    -- 1. Main Donor Metrics Cursor
    OPEN p_donor_cursor FOR
        SELECT 
            d.donor_id,
            d.full_name,
            u.email,
            d.blood_group,
            d.gender,
            d.contact_no,
            d.address,
            dist.district_name,
            TO_CHAR(d.dob, 'YYYY-MM-DD') AS formatted_dob,
            TRUNC(MONTHS_BETWEEN(SYSDATE, d.dob)/12) AS age,
            NVL((SELECT COUNT(*) FROM donation_history WHERE donor_id = d.donor_id), 0) AS total_donations,
            NVL((SELECT SUM(blood_units) FROM donation_history WHERE donor_id = d.donor_id), 0) AS total_units_donated,
            TO_CHAR((SELECT MAX(donation_date) FROM donation_history WHERE donor_id = d.donor_id), 'YYYY-MM-DD') AS last_donation_date,
            NVL(ROUND(SYSDATE - (SELECT MAX(donation_date) FROM donation_history WHERE donor_id = d.donor_id)), 999) AS days_since_last,
            TO_CHAR(NVL((SELECT MAX(donation_date) FROM donation_history WHERE donor_id = d.donor_id), SYSDATE - 91) + 90, 'YYYY-MM-DD') AS next_eligible_date,
            CASE 
                WHEN (SELECT COUNT(*) FROM donation_history WHERE donor_id = d.donor_id) = 0 THEN 'Eligible (First-Time Donor)'
                WHEN ROUND(SYSDATE - (SELECT MAX(donation_date) FROM donation_history WHERE donor_id = d.donor_id)) >= 90 THEN 'Eligible to Donate'
                ELSE 'Ineligible (Cooldown Period)'
            END AS eligibility_status,
            CASE 
                WHEN (SELECT COUNT(*) FROM donation_history WHERE donor_id = d.donor_id) = 0 THEN 'success'
                WHEN ROUND(SYSDATE - (SELECT MAX(donation_date) FROM donation_history WHERE donor_id = d.donor_id)) >= 90 THEN 'success'
                ELSE 'warning'
            END AS eligibility_badge
        FROM donors d
        JOIN users u ON d.user_id = u.user_id
        LEFT JOIN districts dist ON d.district_id = dist.district_id
        WHERE d.donor_id = p_donor_id;

    -- 2. History Timeline Cursor
    OPEN p_history_cursor FOR
        SELECT 
            dh.history_id,
            TO_CHAR(dh.donation_date, 'YYYY-MM-DD') AS donation_date,
            c.camp_name,
            c.venue,
            dh.blood_units
        FROM donation_history dh
        JOIN camps c ON dh.camp_id = c.camp_id
        WHERE dh.donor_id = p_donor_id
        ORDER BY dh.donation_date DESC;

EXCEPTION
    WHEN OTHERS THEN
        OPEN p_donor_cursor FOR SELECT NULL AS error_msg FROM dual WHERE 1=0;
        OPEN p_history_cursor FOR SELECT NULL AS error_msg FROM dual WHERE 1=0;
END rpt_donor_eligibility_summary;
/


-- ==============================================================================
-- REPORT 4: HOSPITAL REQUISITION & DISTRIBUTION EFFICIENCY REPORT
-- Description: Returns hospital blood requests and fulfillment status.
-- ==============================================================================
CREATE OR REPLACE PROCEDURE rpt_hospital_request_analysis (
    p_status_filter IN VARCHAR2 DEFAULT NULL,
    p_cursor        OUT SYS_REFCURSOR
) IS
BEGIN
    OPEN p_cursor FOR
        SELECT 
            hr.request_id,
            hr.hospital_name,
            hr.blood_group,
            hr.units_needed,
            TO_CHAR(hr.request_date, 'YYYY-MM-DD') AS req_date,
            hr.status,
            CASE 
                WHEN UPPER(hr.status) = 'APPROVED' THEN 'success'
                WHEN UPPER(hr.status) = 'PENDING'  THEN 'warning'
                ELSE 'danger'
            END AS status_badge
        FROM hospital_requests hr
        WHERE (p_status_filter IS NULL OR UPPER(hr.status) = UPPER(p_status_filter))
        ORDER BY hr.request_date DESC, hr.request_id DESC;
EXCEPTION
    WHEN OTHERS THEN
        OPEN p_cursor FOR SELECT NULL AS error_msg FROM dual WHERE 1=0;
END rpt_hospital_request_analysis;
/


-- ==============================================================================
-- REPORT  5: CAMP STAFF & VOLUNTEER WORKLOAD ALLOCATION REPORT
-- Description: Returns staff deployments across donation camps with task tracking.
-- ==============================================================================
CREATE OR REPLACE PROCEDURE rpt_staff_camp_performance (
    p_staff_id IN NUMBER DEFAULT NULL,
    p_cursor   OUT SYS_REFCURSOR
) IS
BEGIN
    OPEN p_cursor FOR
        SELECT 
            sa.assignment_id,
            u.user_id AS staff_id,
            u.email AS staff_email,
            c.camp_id,
            c.camp_name,
            c.venue,
            TO_CHAR(c.camp_date, 'YYYY-MM-DD') AS camp_date,
            d.district_name,
            sa.task
        FROM staff_assignments sa
        JOIN users u ON sa.staff_id = u.user_id
        JOIN camps c ON sa.camp_id = c.camp_id
        JOIN districts d ON c.district_id = d.district_id
        WHERE (p_staff_id IS NULL OR u.user_id = p_staff_id)
        ORDER BY u.user_id, c.camp_date DESC;
EXCEPTION
    WHEN OTHERS THEN
        OPEN p_cursor FOR SELECT NULL AS error_msg FROM dual WHERE 1=0;
END rpt_staff_camp_performance;
/

COMMIT;
