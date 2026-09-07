-- Drop existing tables to avoid conflicts during testing
-- Ignore errors if you are running this for the very first time
DROP TABLE staff_assignments CASCADE CONSTRAINTS;
DROP TABLE hospital_requests CASCADE CONSTRAINTS;
DROP TABLE donation_history CASCADE CONSTRAINTS;
DROP TABLE camps CASCADE CONSTRAINTS;
DROP TABLE donors CASCADE CONSTRAINTS;
DROP TABLE blood_inventory CASCADE CONSTRAINTS;
DROP TABLE districts CASCADE CONSTRAINTS;
DROP TABLE users CASCADE CONSTRAINTS;

-- 1. Users table (Stores Admin, Staff, and Donors login details)
CREATE TABLE users (
    user_id NUMBER PRIMARY KEY,
    email VARCHAR2(100) UNIQUE NOT NULL,
    password VARCHAR2(100) NOT NULL,
    role VARCHAR2(20) NOT NULL -- Admin, Staff, or Donor
);

-- 2. Districts table (For dropdowns and filtering)
CREATE TABLE districts (
    district_id NUMBER PRIMARY KEY,
    district_name VARCHAR2(50) NOT NULL
);

-- 3. Blood Inventory table (Master table for blood stocks)
CREATE TABLE blood_inventory (
    blood_group VARCHAR2(5) PRIMARY KEY,
    total_units NUMBER DEFAULT 0,
    last_updated DATE
);

-- 4. Donors table
CREATE TABLE donors (
    donor_id NUMBER PRIMARY KEY,
    user_id NUMBER UNIQUE, -- 1 to 1 relation with users
    full_name VARCHAR2(150) NOT NULL,
    dob DATE,
    gender VARCHAR2(10),
    blood_group VARCHAR2(5),
    contact_no VARCHAR2(15),
    address VARCHAR2(255),
    district_id NUMBER,
    
    CONSTRAINT fk_donor_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_donor_bg FOREIGN KEY (blood_group) REFERENCES blood_inventory(blood_group),
    CONSTRAINT fk_donor_district FOREIGN KEY (district_id) REFERENCES districts(district_id)
);

-- 5. Camps table
CREATE TABLE camps (
    camp_id NUMBER PRIMARY KEY,
    camp_name VARCHAR2(150) NOT NULL,
    camp_date DATE,
    venue VARCHAR2(200),
    district_id NUMBER,
    
    CONSTRAINT fk_camp_district FOREIGN KEY (district_id) REFERENCES districts(district_id)
);

-- 6. Donation History table
CREATE TABLE donation_history (
    history_id NUMBER PRIMARY KEY,
    donor_id NUMBER,
    camp_id NUMBER,
    donation_date DATE,
    blood_units NUMBER,
    
    CONSTRAINT fk_hist_donor FOREIGN KEY (donor_id) REFERENCES donors(donor_id),
    CONSTRAINT fk_hist_camp FOREIGN KEY (camp_id) REFERENCES camps(camp_id)
);

-- 7. Hospital Requests table
CREATE TABLE hospital_requests (
    request_id NUMBER PRIMARY KEY,
    hospital_name VARCHAR2(150) NOT NULL,
    blood_group VARCHAR2(5),
    units_needed NUMBER NOT NULL,
    request_date DATE,
    status VARCHAR2(20) DEFAULT 'Pending',
    
    CONSTRAINT fk_req_bg FOREIGN KEY (blood_group) REFERENCES blood_inventory(blood_group)
);

-- 8. Staff Assignments table
CREATE TABLE staff_assignments (
    assignment_id NUMBER PRIMARY KEY,
    staff_id NUMBER,
    camp_id NUMBER,
    task VARCHAR2(100),
    
    CONSTRAINT fk_assign_staff FOREIGN KEY (staff_id) REFERENCES users(user_id),
    CONSTRAINT fk_assign_camp FOREIGN KEY (camp_id) REFERENCES camps(camp_id)
);

COMMIT;