<?php
session_start();
require_once 'db_connect.php';
require_once 'mongo_connect.php';

// Auth check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'Donor') {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$message = "";
$msg_type = "";

// 1. Fetch or Refresh Donor Profile Information
$donor_query = "SELECT d.donor_id, d.user_id, d.full_name, TO_CHAR(d.dob, 'YYYY-MM-DD') AS dob_str, 
                       d.gender, d.blood_group, d.contact_no, d.address, d.district_id, 
                       u.email, dist.district_name,
                       TRUNC(MONTHS_BETWEEN(SYSDATE, d.dob)/12) AS age
                FROM donors d
                JOIN users u ON d.user_id = u.user_id
                LEFT JOIN districts dist ON d.district_id = dist.district_id
                WHERE d.user_id = :p_uid";

$d_stmt = oci_parse($conn, $donor_query);
oci_bind_by_name($d_stmt, ":p_uid", $user_id);
oci_execute($d_stmt);
$donor = oci_fetch_assoc($d_stmt);

// If donor record was not found for this user_id, create or sync it
if (!$donor) {
    $ins_did = oci_parse($conn, "SELECT NVL(MAX(donor_id), 0) + 1 AS NEXT_DID FROM donors");
    oci_execute($ins_did);
    $row_did = oci_fetch_assoc($ins_did);
    $new_did = $row_did['NEXT_DID'];

    $ins_donor = oci_parse($conn, "INSERT INTO donors (donor_id, user_id, full_name) VALUES (:did, :uid, :name)");
    $donor_name = $_SESSION['full_name'] ?? 'Blood Donor';
    oci_bind_by_name($ins_donor, ":did", $new_did);
    oci_bind_by_name($ins_donor, ":uid", $user_id);
    oci_bind_by_name($ins_donor, ":name", $donor_name);
    oci_execute($ins_donor, OCI_COMMIT_ON_SUCCESS);

    // Re-fetch
    oci_execute($d_stmt);
    $donor = oci_fetch_assoc($d_stmt);
}

$donor_id = $donor['DONOR_ID'] ?? 0;
$_SESSION['donor_id'] = $donor_id;
$_SESSION['full_name'] = $donor['FULL_NAME'] ?? 'Blood Donor';
$_SESSION['blood_group'] = $donor['BLOOD_GROUP'] ?? '';

// 2. Handle Profile Update Submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name']);
    $dob_input = !empty($_POST['dob']) ? $_POST['dob'] : null;
    $gender = $_POST['gender'] ?? '';
    $blood_group = $_POST['blood_group'] ?? '';
    $contact_no = trim($_POST['contact_no']);
    $address = trim($_POST['address']);
    $district_id = !empty($_POST['district_id']) ? intval($_POST['district_id']) : null;

    // Validate DOB if provided
    $dob_valid = true;
    if ($dob_input) {
        $dob_time = strtotime($dob_input);
        $age_calc = (time() - $dob_time) / (365.25 * 86400);
        if ($age_calc < 18) {
            $message = "Eligibility Rule: You must be at least 18 years old to donate blood.";
            $msg_type = "error";
            $dob_valid = false;
        }
    }

    if ($dob_valid) {
        // Try calling stored procedure update_donor_profile
        $proc_sql = "BEGIN update_donor_profile(:p_did, :p_name, TO_DATE(:p_dob, 'YYYY-MM-DD'), :p_gen, :p_bg, :p_contact, :p_addr, :p_dist); END;";
        $proc_stmt = oci_parse($conn, $proc_sql);
        oci_bind_by_name($proc_stmt, ":p_did", $donor_id);
        oci_bind_by_name($proc_stmt, ":p_name", $full_name);
        oci_bind_by_name($proc_stmt, ":p_dob", $dob_input);
        oci_bind_by_name($proc_stmt, ":p_gen", $gender);
        oci_bind_by_name($proc_stmt, ":p_bg", $blood_group);
        oci_bind_by_name($proc_stmt, ":p_contact", $contact_no);
        oci_bind_by_name($proc_stmt, ":p_addr", $address);
        oci_bind_by_name($proc_stmt, ":p_dist", $district_id);

        if (@oci_execute($proc_stmt)) {
            $message = "Profile updated successfully!";
            $msg_type = "success";
        } else {
            // Fallback direct SQL update
            $upd_sql = "UPDATE donors SET 
                            full_name = :p_name, 
                            dob = " . ($dob_input ? "TO_DATE(:p_dob, 'YYYY-MM-DD')" : "NULL") . ", 
                            gender = :p_gen, 
                            blood_group = :p_bg, 
                            contact_no = :p_contact, 
                            address = :p_addr, 
                            district_id = :p_dist 
                        WHERE donor_id = :p_did";
            $upd_stmt = oci_parse($conn, $upd_sql);
            oci_bind_by_name($upd_stmt, ":p_name", $full_name);
            if ($dob_input) oci_bind_by_name($upd_stmt, ":p_dob", $dob_input);
            oci_bind_by_name($upd_stmt, ":p_gen", $gender);
            oci_bind_by_name($upd_stmt, ":p_bg", $blood_group);
            oci_bind_by_name($upd_stmt, ":p_contact", $contact_no);
            oci_bind_by_name($upd_stmt, ":p_addr", $address);
            oci_bind_by_name($upd_stmt, ":p_dist", $district_id);
            oci_bind_by_name($upd_stmt, ":p_did", $donor_id);

            if (@oci_execute($upd_stmt, OCI_COMMIT_ON_SUCCESS)) {
                $message = "Profile updated successfully!";
                $msg_type = "success";
            } else {
                $e = oci_error($upd_stmt);
                $message = "Error updating profile: " . $e['message'];
                $msg_type = "error";
            }
        }

        // Refresh donor details
        oci_execute($d_stmt);
        $donor = oci_fetch_assoc($d_stmt);
        $_SESSION['full_name'] = $donor['FULL_NAME'];
        $_SESSION['blood_group'] = $donor['BLOOD_GROUP'] ?? '';
    }
}

// 3. Check Eligibility & Fetch Metrics
// Last donation query
$hist_sql = "SELECT MAX(donation_date) AS LAST_DON_DATE, 
                    COUNT(*) AS TOTAL_COUNT, 
                    NVL(SUM(blood_units), 0) AS TOTAL_UNITS,
                    ROUND(MONTHS_BETWEEN(SYSDATE, MAX(donation_date)), 1) AS MONTHS_SINCE_LAST
             FROM donation_history 
             WHERE donor_id = :p_did";
$h_stmt = oci_parse($conn, $hist_sql);
oci_bind_by_name($h_stmt, ":p_did", $donor_id);
oci_execute($h_stmt);
$h_data = oci_fetch_assoc($h_stmt);

$total_donations = intval($h_data['TOTAL_COUNT'] ?? 0);
$total_units = intval($h_data['TOTAL_UNITS'] ?? 0);
$lives_saved = $total_units * 3;
$last_donation_raw = $h_data['LAST_DON_DATE'];
$last_donation_formatted = $last_donation_raw ? date('d M Y', strtotime($last_donation_raw)) : "Never Donated";
$months_since_last = $h_data['MONTHS_SINCE_LAST'] !== null ? floatval($h_data['MONTHS_SINCE_LAST']) : 999;

// Try PL/SQL function for eligibility
$eligibility_status = "";
$func_sql = "BEGIN :res := check_donor_eligibility_full(:p_did); END;";
$func_stmt = oci_parse($conn, $func_sql);
oci_bind_by_name($func_stmt, ":p_did", $donor_id);
oci_bind_by_name($func_stmt, ":res", $eligibility_status, 200);

$is_eligible = false;
if (@oci_execute($func_stmt) && !empty($eligibility_status)) {
    if (strpos($eligibility_status, 'Eligible to Donate') !== false) {
        $is_eligible = true;
    }
} else {
    // Pure PHP fallback logic
    $age = !empty($donor['AGE']) ? intval($donor['AGE']) : (!empty($donor['DOB_STR']) ? intval((time() - strtotime($donor['DOB_STR'])) / (365.25 * 86400)) : null);
    if ($age === null) {
        $eligibility_status = "Profile Incomplete: Please set your Date of Birth.";
        $is_eligible = false;
    } elseif ($age < 18) {
        $eligibility_status = "Not Eligible: Minimum age requirement is 18 years (Current: $age yrs).";
        $is_eligible = false;
    } elseif ($age > 65) {
        $eligibility_status = "Not Eligible: Maximum age limit is 65 years (Current: $age yrs).";
        $is_eligible = false;
    } elseif ($last_donation_raw && $months_since_last < 4) {
        $eligibility_status = "Not Eligible: Must wait 4 months between donations (Last: $last_donation_formatted).";
        $is_eligible = false;
    } else {
        $eligibility_status = "Eligible to Donate: You meet all basic donation requirements!";
        $is_eligible = true;
    }
}

// Next eligible date calculation
$next_eligible_date_str = "Immediately";
if ($last_donation_raw) {
    $next_timestamp = strtotime($last_donation_raw . " + 4 months");
    if ($next_timestamp > time()) {
        $next_eligible_date_str = date('d M Y', $next_timestamp);
    }
}

// Profile Completion Progress Calculation
$fields_completed = 0;
$total_fields = 6;
if (!empty($donor['FULL_NAME'])) $fields_completed++;
if (!empty($donor['DOB_STR'])) $fields_completed++;
if (!empty($donor['GENDER'])) $fields_completed++;
if (!empty($donor['BLOOD_GROUP'])) $fields_completed++;
if (!empty($donor['CONTACT_NO'])) $fields_completed++;
if (!empty($donor['DISTRICT_ID'])) $fields_completed++;
$profile_percentage = round(($fields_completed / $total_fields) * 100);

// Fetch Districts for Dropdowns
$districts_list = [];
$dist_stmt = oci_parse($conn, "SELECT district_id, district_name FROM districts ORDER BY district_name ASC");
oci_execute($dist_stmt);
while ($d_row = oci_fetch_assoc($dist_stmt)) {
    $districts_list[] = $d_row;
}

// Fetch Blood Groups from blood_inventory
$blood_groups_list = [];
$bg_stmt = oci_parse($conn, "SELECT blood_group FROM blood_inventory ORDER BY blood_group ASC");
oci_execute($bg_stmt);
while ($b_row = oci_fetch_assoc($bg_stmt)) {
    $blood_groups_list[] = $b_row['BLOOD_GROUP'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donor Dashboard - LifeLineConnect</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <!-- Sidebar Navigation -->
    <div class="sidebar">
        <div class="sidebar-brand">
            <span class="brand-icon">&#129656;</span>
            <div>
                <h2>LifeLineConnect</h2>
                <small class="badge-role">Blood Donor</small>
            </div>
        </div>

        <div class="sidebar-user">
            <div class="user-avatar"><?php echo strtoupper(substr($donor['FULL_NAME'] ?? 'D', 0, 1)); ?></div>
            <div class="user-details">
                <strong><?php echo htmlspecialchars($donor['FULL_NAME'] ?? 'Donor'); ?></strong>
                <span class="user-bg-pill"><?php echo htmlspecialchars($donor['BLOOD_GROUP'] ?: 'Blood: Not Set'); ?></span>
            </div>
        </div>

        <ul class="nav-menu">
            <li><a href="javascript:void(0)" class="nav-link active" onclick="showTab('tab-overview', this)"><span class="nav-icon">&#128202;</span> Overview</a></li>
            <li><a href="javascript:void(0)" class="nav-link" onclick="showTab('tab-profile', this)"><span class="nav-icon">&#128100;</span> Profile Completion</a></li>
            <li><a href="javascript:void(0)" class="nav-link" onclick="showTab('tab-eligibility', this)"><span class="nav-icon">&#9877;</span> Check Eligibility</a></li>
            <li><a href="javascript:void(0)" class="nav-link" onclick="showTab('tab-camps', this)"><span class="nav-icon">&#127973;</span> Find Camps</a></li>
            <li><a href="javascript:void(0)" class="nav-link" onclick="showTab('tab-history', this)"><span class="nav-icon">&#128220;</span> Donation History</a></li>
            <li><a href="javascript:void(0)" class="nav-link" onclick="showTab('tab-chat', this)"><span class="nav-icon">&#128172;</span> Chat with Manager</a></li>
            <li><a href="javascript:void(0)" class="nav-link" onclick="showTab('tab-reviews', this)"><span class="nav-link-badge">New</span><span class="nav-icon">&#11088;</span> Camp Reviews</a></li>
            <li class="nav-divider"></li>
            <li><a href="logout.php" class="nav-link nav-logout"><span class="nav-icon">&#128682;</span> Log Out</a></li>
        </ul>
    </div>

    <!-- Main Content Area -->
    <div class="main-content">
        
        <!-- Header Bar -->
        <div class="header">
            <div>
                <h1>Welcome, <?php echo htmlspecialchars($donor['FULL_NAME'] ?? 'Donor'); ?>!</h1>
                <p class="header-sub">Donor ID: <strong>#<?php echo $donor_id; ?></strong> | District: <strong><?php echo htmlspecialchars($donor['DISTRICT_NAME'] ?? 'Not set'); ?></strong></p>
            </div>
            <div class="header-badges">
                <div class="eligibility-pill <?php echo $is_eligible ? 'pill-eligible' : 'pill-ineligible'; ?>">
                    <span class="pill-dot"></span>
                    <?php echo $is_eligible ? 'Eligible to Donate' : 'Check Eligibility'; ?>
                </div>
            </div>
        </div>

        <!-- Alert Notifications -->
        <?php if($message != ""): ?>
            <div class="alert <?php echo $msg_type; ?>" id="alertBox">
                <span><?php echo $message; ?></span>
                <span class="close-btn" onclick="closeAlert()">&times;</span>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['registered'])): ?>
            <div class="alert success" id="welcomeAlert">
                <span><strong>Welcome to LifeLineConnect!</strong> Your account is registered. Please complete your profile to check eligibility and register for camps.</span>
                <span class="close-btn" onclick="this.parentElement.style.display='none'">&times;</span>
            </div>
        <?php endif; ?>


        <!-- ======================================================================= -->
        <!-- TAB 1: OVERVIEW -->
        <!-- ======================================================================= -->
        <div id="tab-overview" class="content-tab active">
            <!-- Stats Counters -->
            <div class="stat-grid">
                <div class="stat-card stat-crimson">
                    <div class="stat-icon">&#129656;</div>
                    <div class="stat-info">
                        <span class="stat-label">Total Donations</span>
                        <h3 class="stat-value"><?php echo $total_donations; ?></h3>
                    </div>
                </div>

                <div class="stat-card stat-burgundy">
                    <div class="stat-icon">&#129514;</div>
                    <div class="stat-info">
                        <span class="stat-label">Blood Units Donated</span>
                        <h3 class="stat-value"><?php echo $total_units; ?> <small>Units</small></h3>
                    </div>
                </div>

                <div class="stat-card stat-green">
                    <div class="stat-icon">&#128147;</div>
                    <div class="stat-info">
                        <span class="stat-label">Est. Lives Saved</span>
                        <h3 class="stat-value"><?php echo $lives_saved; ?></h3>
                    </div>
                </div>

                <div class="stat-card stat-amber">
                    <div class="stat-icon">&#128197;</div>
                    <div class="stat-info">
                        <span class="stat-label">Next Eligible Date</span>
                        <h3 class="stat-value" style="font-size: 1.2rem;"><?php echo $next_eligible_date_str; ?></h3>
                    </div>
                </div>
            </div>

            <!-- Profile Completion Banner -->
            <?php if($profile_percentage < 100): ?>
            <div class="card banner-card" style="margin-top: 25px;">
                <div class="banner-content">
                    <div>
                        <h3>Complete Your Donor Profile</h3>
                        <p>Your profile is <strong><?php echo $profile_percentage; ?>%</strong> complete. Adding your Date of Birth and District helps us notify you of nearby donation camps!</p>
                        <div class="progress-bar-wrap">
                            <div class="progress-bar-fill" style="width: <?php echo $profile_percentage; ?>%;"></div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-primary" onclick="showTab('tab-profile')">Complete Profile Now</button>
                </div>
            </div>
            <?php endif; ?>

            <!-- Dual Card Section -->
            <div class="card-container" style="margin-top: 25px;">
                <!-- Eligibility Status Box -->
                <div class="card">
                    <h3>Donation Eligibility Status</h3>
                    <div class="status-box-highlight <?php echo $is_eligible ? 'bg-success-light' : 'bg-warning-light'; ?>">
                        <div class="status-icon"><?php echo $is_eligible ? '&#9989;' : '&#9888;'; ?></div>
                        <div>
                            <h4><?php echo $is_eligible ? 'You are Eligible to Donate!' : 'Eligibility Notice'; ?></h4>
                            <p><?php echo htmlspecialchars($eligibility_status); ?></p>
                        </div>
                    </div>
                    <div class="overview-list" style="margin-top: 20px;">
                        <div class="overview-item">
                            <span>Last Donated On:</span>
                            <strong><?php echo $last_donation_formatted; ?></strong>
                        </div>
                        <div class="overview-item">
                            <span>Current Age:</span>
                            <strong><?php echo !empty($donor['AGE']) ? $donor['AGE'] . ' Years' : 'Not specified'; ?></strong>
                        </div>
                        <div class="overview-item">
                            <span>Blood Group:</span>
                            <span class="blood-badge-tag"><?php echo htmlspecialchars($donor['BLOOD_GROUP'] ?: 'Not selected'); ?></span>
                        </div>
                    </div>
                    <button class="btn btn-secondary" style="margin-top: 20px;" onclick="showTab('tab-eligibility')">View Full Eligibility Guidelines</button>
                </div>

                <!-- Upcoming Camps in District -->
                <div class="card">
                    <h3>Upcoming Camps in <?php echo htmlspecialchars($donor['DISTRICT_NAME'] ?? 'Your Area'); ?></h3>
                    <div class="camps-quick-list">
                        <?php
                        $dist_id_param = $donor['DISTRICT_ID'] ?? 0;
                        $c_quick_sql = "SELECT c.camp_id, c.camp_name, c.venue, TO_CHAR(c.camp_date, 'YYYY-MM-DD') AS c_date, d.district_name 
                                        FROM camps c 
                                        JOIN districts d ON c.district_id = d.district_id 
                                        WHERE c.camp_date >= TRUNC(SYSDATE) " . 
                                        ($dist_id_param ? "AND c.district_id = :p_did " : "") . 
                                        "ORDER BY c.camp_date ASC";
                        $cq_stmt = oci_parse($conn, $c_quick_sql);
                        if ($dist_id_param) oci_bind_by_name($cq_stmt, ":p_did", $dist_id_param);
                        oci_execute($cq_stmt);
                        $has_quick_camps = false;
                        while ($cq_row = oci_fetch_assoc($cq_stmt)) {
                            $has_quick_camps = true;
                            echo "<div class='quick-camp-item'>";
                            echo "  <div>";
                            echo "      <strong>".$cq_row['CAMP_NAME']."</strong>";
                            echo "      <p>&#128205; ".$cq_row['VENUE']." (".$cq_row['DISTRICT_NAME'].")</p>";
                            echo "  </div>";
                            echo "  <span class='camp-date-badge'>&#128197; ".$cq_row['C_DATE']."</span>";
                            echo "</div>";
                        }
                        if (!$has_quick_camps) {
                            echo "<p class='text-muted' style='padding: 20px 0;'>No upcoming camps scheduled currently in your selected district.</p>";
                        }
                        ?>
                    </div>
                    <button class="btn btn-primary" style="margin-top: 20px;" onclick="showTab('tab-camps')">Browse All Camps</button>
                </div>
            </div>
        </div>


        <!-- ======================================================================= -->
        <!-- TAB 2: PROFILE COMPLETION -->
        <!-- ======================================================================= -->
        <div id="tab-profile" class="content-tab">
            <div class="card full-width">
                <div class="card-header-flex">
                    <div>
                        <h3>Donor Profile Information</h3>
                        <p class="subtitle-card">Please provide accurate personal details to ensure donor safety and emergency communication.</p>
                    </div>
                    <div class="profile-meter">
                        <span>Profile Completion: <strong><?php echo $profile_percentage; ?>%</strong></span>
                        <div class="progress-bar-wrap" style="width: 160px; height: 10px;">
                            <div class="progress-bar-fill" style="width: <?php echo $profile_percentage; ?>%;"></div>
                        </div>
                    </div>
                </div>

                <form action="donor_dashboard.php" method="POST" class="profile-form">
                    <input type="hidden" name="update_profile" value="1">

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="prof_name">Full Name <span class="req">*</span></label>
                            <input type="text" id="prof_name" name="full_name" required value="<?php echo htmlspecialchars($donor['FULL_NAME'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label for="prof_email">Email Address (Read Only)</label>
                            <input type="email" id="prof_email" value="<?php echo htmlspecialchars($donor['EMAIL'] ?? ''); ?>" disabled style="background-color: #f1f5f9;">
                        </div>

                        <div class="form-group">
                            <label for="prof_dob">Date of Birth <span class="req">*</span></label>
                            <input type="date" id="prof_dob" name="dob" required value="<?php echo htmlspecialchars($donor['DOB_STR'] ?? ''); ?>">
                            <small class="form-hint">Must be at least 18 years old.</small>
                        </div>

                        <div class="form-group">
                            <label for="prof_gender">Gender</label>
                            <select id="prof_gender" name="gender">
                                <option value="">-- Select Gender --</option>
                                <option value="Male" <?php echo ($donor['GENDER'] === 'Male') ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo ($donor['GENDER'] === 'Female') ? 'selected' : ''; ?>>Female</option>
                                <option value="Other" <?php echo ($donor['GENDER'] === 'Other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="prof_bg">Blood Group <span class="req">*</span></label>
                            <select id="prof_bg" name="blood_group" required>
                                <option value="">-- Select Blood Group --</option>
                                <?php foreach($blood_groups_list as $bg_item): ?>
                                    <option value="<?php echo $bg_item; ?>" <?php echo ($donor['BLOOD_GROUP'] === $bg_item) ? 'selected' : ''; ?>><?php echo $bg_item; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="prof_contact">Phone Number <span class="req">*</span></label>
                            <input type="text" id="prof_contact" name="contact_no" placeholder="07X-XXXXXXX" required value="<?php echo htmlspecialchars($donor['CONTACT_NO'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label for="prof_district">District <span class="req">*</span></label>
                            <select id="prof_district" name="district_id" required>
                                <option value="">-- Select District --</option>
                                <?php foreach($districts_list as $dist): ?>
                                    <option value="<?php echo $dist['DISTRICT_ID']; ?>" <?php echo ($donor['DISTRICT_ID'] == $dist['DISTRICT_ID']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dist['DISTRICT_NAME']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group full-span">
                            <label for="prof_address">Residential Address</label>
                            <input type="text" id="prof_address" name="address" placeholder="No, Street Name, City" value="<?php echo htmlspecialchars($donor['ADDRESS'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary btn-wide">Save & Update Profile</button>
                    </div>
                </form>
            </div>
        </div>


        <!-- ======================================================================= -->
        <!-- TAB 3: CHECK ELIGIBILITY -->
        <!-- ======================================================================= -->
        <div id="tab-eligibility" class="content-tab">
            <div class="card full-width">
                <h3>Blood Donation Eligibility Check</h3>
                <p class="subtitle-card">Our system automatically evaluates your age and donation history to confirm whether you can donate safely today.</p>

                <!-- Status Banner -->
                <div class="eligibility-banner <?php echo $is_eligible ? 'banner-eligible' : 'banner-ineligible'; ?>" style="margin-top: 20px;">
                    <div class="banner-icon"><?php echo $is_eligible ? '&#10004;' : '&#9888;'; ?></div>
                    <div>
                        <h2><?php echo $is_eligible ? 'You Are Eligible to Donate!' : 'Currently Ineligible'; ?></h2>
                        <p style="font-size: 1.1rem; margin-top: 5px;"><?php echo htmlspecialchars($eligibility_status); ?></p>
                        <p class="text-muted" style="margin-top: 5px;">Next Eligible Date: <strong><?php echo $next_eligible_date_str; ?></strong></p>
                    </div>
                </div>

                <!-- Criteria Checklist Table -->
                <div class="criteria-section" style="margin-top: 35px;">
                    <h4>Key Eligibility Criteria Checklist</h4>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Requirement</th>
                                <th>Criteria</th>
                                <th>Your Current Value</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>Age Limit</strong></td>
                                <td>18 - 65 Years old</td>
                                <td><?php echo !empty($donor['AGE']) ? $donor['AGE'] . ' Years' : 'DOB not provided'; ?></td>
                                <td>
                                    <?php if (!empty($donor['AGE']) && $donor['AGE'] >= 18 && $donor['AGE'] <= 65): ?>
                                        <span class="badge badge-success">&#10004; Passed</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">&#10008; Ineligible</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Interval Period</strong></td>
                                <td>At least 4 Months (120 Days) between donations</td>
                                <td>
                                    <?php 
                                    if ($last_donation_raw) {
                                        echo "$months_since_last Months since last donation ($last_donation_formatted)";
                                    } else {
                                        echo "First time donor (Never donated before)";
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php if (!$last_donation_raw || $months_since_last >= 4): ?>
                                        <span class="badge badge-success">&#10004; Passed</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">&#9203; Waiting Period</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Body Weight</strong></td>
                                <td>Must be at least 50 kg (110 lbs)</td>
                                <td>Self-Assessment Checked</td>
                                <td><span class="badge badge-success">&#10004; General Rule</span></td>
                            </tr>
                            <tr>
                                <td><strong>General Health</strong></td>
                                <td>Good general health, no recent active infections or antibiotics in past 7 days</td>
                                <td>Pre-donation medical check at Camp</td>
                                <td><span class="badge badge-info">&#8505; Verified at Camp</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Self-Check Interactive Helper -->
                <div class="interactive-check-box" style="margin-top: 35px;">
                    <h4>Quick Pre-Donation Self Assessment Checklist</h4>
                    <div class="check-grid">
                        <label class="check-item"><input type="checkbox" checked disabled> I am feeling healthy and well today.</label>
                        <label class="check-item"><input type="checkbox" checked disabled> I have had sufficient sleep (at least 6 hours) last night.</label>
                        <label class="check-item"><input type="checkbox" checked disabled> I have consumed food and plenty of fluids within the last 4 hours.</label>
                        <label class="check-item"><input type="checkbox" checked disabled> I haven't undergone major surgery or tattoos in the last 6 months.</label>
                    </div>
                </div>
            </div>
        </div>


        <!-- ======================================================================= -->
        <!-- TAB 4: FIND CAMPS -->
        <!-- ======================================================================= -->
        <div id="tab-camps" class="content-tab">
            <div class="card full-width">
                <div class="card-header-flex">
                    <div>
                        <h3>Upcoming Blood Donation Camps</h3>
                        <p class="subtitle-card">Find donation venues near you, view locations, and plan your donation visit.</p>
                    </div>
                    <div class="filter-controls">
                        <select id="districtFilter" onchange="filterCampsByDistrict(this.value)">
                            <option value="ALL">-- All Districts --</option>
                            <?php foreach($districts_list as $dist): ?>
                                <option value="<?php echo htmlspecialchars($dist['DISTRICT_NAME']); ?>" <?php echo ($donor['DISTRICT_ID'] == $dist['DISTRICT_ID']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dist['DISTRICT_NAME']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" id="campSearchInput" placeholder="Search camp name or venue..." onkeyup="searchCampsTable()">
                    </div>
                </div>

                <table class="data-table" id="campsTable" style="margin-top: 20px;">
                    <thead>
                        <tr>
                            <th>Camp ID</th>
                            <th>Camp Name</th>
                            <th>Date</th>
                            <th>Venue / Location</th>
                            <th>District</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $camps_all_sql = "SELECT c.camp_id, c.camp_name, c.venue, TO_CHAR(c.camp_date, 'YYYY-MM-DD') AS c_date, d.district_name,
                                                 CASE 
                                                    WHEN c.camp_date = TRUNC(SYSDATE) THEN 'Today'
                                                    WHEN c.camp_date > TRUNC(SYSDATE) THEN 'Upcoming'
                                                    ELSE 'Past'
                                                 END AS camp_status
                                          FROM camps c 
                                          JOIN districts d ON c.district_id = d.district_id 
                                          ORDER BY c.camp_date ASC";
                        $c_stmt_all = oci_parse($conn, $camps_all_sql);
                        oci_execute($c_stmt_all);
                        $camps_count = 0;
                        while ($crow = oci_fetch_assoc($c_stmt_all)) {
                            $camps_count++;
                            $status_class = ($crow['CAMP_STATUS'] === 'Today') ? 'badge-danger' : 'badge-info';
                            echo "<tr data-district='".htmlspecialchars($crow['DISTRICT_NAME'])."'>";
                            echo "  <td><strong>#".$crow['CAMP_ID']."</strong></td>";
                            echo "  <td><strong>".htmlspecialchars($crow['CAMP_NAME'])."</strong></td>";
                            echo "  <td>&#128197; ".$crow['C_DATE']."</td>";
                            echo "  <td>&#128205; ".htmlspecialchars($crow['VENUE'])."</td>";
                            echo "  <td><span class='district-badge'>".htmlspecialchars($crow['DISTRICT_NAME'])."</span></td>";
                            echo "  <td><span class='badge ".$status_class."'>".$crow['CAMP_STATUS']."</span></td>";
                            echo "  <td>
                                        <button type='button' class='btn-small btn-primary' onclick='openReviewModal(".$crow['CAMP_ID'].", \"".htmlspecialchars(addslashes($crow['CAMP_NAME']))."\")'>Rate / Review</button>
                                    </td>";
                            echo "</tr>";
                        }
                        if ($camps_count === 0) {
                            echo "<tr><td colspan='7' style='text-align:center;'>No blood donation camps found in the system.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>


        <!-- ======================================================================= -->
        <!-- TAB 5: DONATION HISTORY -->
        <!-- ======================================================================= -->
        <div id="tab-history" class="content-tab">
            <div class="card full-width">
                <div class="card-header-flex">
                    <div>
                        <h3>My Blood Donation History</h3>
                        <p class="subtitle-card">Complete lifetime record of your blood donations at LifeLineConnect camps.</p>
                    </div>
                    <div class="stat-badge-inline">
                        Total Units Donated: <strong><?php echo $total_units; ?></strong>
                    </div>
                </div>

                <table class="data-table" style="margin-top: 20px;">
                    <thead>
                        <tr>
                            <th>History ID</th>
                            <th>Camp Name</th>
                            <th>Venue</th>
                            <th>District</th>
                            <th>Donation Date</th>
                            <th>Blood Units</th>
                            <th>Certificate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $hist_query = "SELECT dh.history_id, c.camp_name, c.venue, d.district_name, 
                                              TO_CHAR(dh.donation_date, 'YYYY-MM-DD') AS don_date, 
                                              dh.blood_units 
                                       FROM donation_history dh
                                       JOIN camps c ON dh.camp_id = c.camp_id
                                       JOIN districts d ON c.district_id = d.district_id
                                       WHERE dh.donor_id = :p_did
                                       ORDER BY dh.donation_date DESC";
                        $hq_stmt = oci_parse($conn, $hist_query);
                        oci_bind_by_name($hq_stmt, ":p_did", $donor_id);
                        oci_execute($hq_stmt);
                        $history_rows = 0;

                        while ($hrow = oci_fetch_assoc($hq_stmt)) {
                            $history_rows++;
                            echo "<tr>";
                            echo "  <td>#".$hrow['HISTORY_ID']."</td>";
                            echo "  <td><strong>".htmlspecialchars($hrow['CAMP_NAME'])."</strong></td>";
                            echo "  <td>&#128205; ".htmlspecialchars($hrow['VENUE'])."</td>";
                            echo "  <td>".htmlspecialchars($hrow['DISTRICT_NAME'])."</td>";
                            echo "  <td>&#128197; ".$hrow['DON_DATE']."</td>";
                            echo "  <td><span class='badge badge-success'>".$hrow['BLOOD_UNITS']." Unit(s)</span></td>";
                            echo "  <td><span class='badge-cert'>&#127941; Life Saver</span></td>";
                            echo "</tr>";
                        }

                        if ($history_rows === 0) {
                            echo "<tr><td colspan='7' style='text-align: center; padding: 30px;' class='text-muted'>
                                    You have not recorded any donations yet. Visit a camp to make your first blood donation!
                                  </td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>


        <!-- ======================================================================= -->
        <!-- TAB 6: CHAT WITH MANAGER (MongoDB) -->
        <!-- ======================================================================= -->
        <div id="tab-chat" class="content-tab">
            <div class="card full-width chat-card-wrapper">
                <div class="chat-header">
                    <div>
                        <h3>Chat with Blood Bank Manager</h3>
                        <p class="subtitle-card">Inquire about camp schedules, medical questions, or donation support.</p>
                    </div>
                    <div class="mongo-status-badge">
                        <span class="mongo-dot"></span>
                        <span>MongoDB Realtime Messaging</span>
                    </div>
                </div>

                <div class="chat-container">
                    <div class="chat-messages" id="chatMessagesBox">
                        <div class="chat-loading">Loading message history...</div>
                    </div>

                    <form id="chatForm" onsubmit="sendChatMessage(event)" class="chat-input-form">
                        <input type="text" id="chatInputMessage" placeholder="Type your inquiry for the Manager..." autocomplete="off" required>
                        <button type="submit" class="btn btn-primary btn-send">Send &#10148;</button>
                    </form>
                </div>
            </div>
        </div>


        <!-- ======================================================================= -->
        <!-- TAB 7: CAMP REVIEWS (MongoDB) -->
        <!-- ======================================================================= -->
        <div id="tab-reviews" class="content-tab">
            <div class="card-container">
                <!-- Add Review Card -->
                <div class="card" id="writeReviewCard">
                    <h3>Submit Camp Feedback & Rating</h3>
                    <p class="subtitle-card">Share your donation experience to help us improve future camps (Saved to MongoDB).</p>

                    <form id="reviewForm" onsubmit="submitCampReview(event)">
                        <div class="form-group">
                            <label for="review_camp_select">Select Camp <span class="req">*</span></label>
                            <select id="review_camp_select" required>
                                <option value="">-- Choose Camp --</option>
                                <?php
                                $rc_stmt = oci_parse($conn, "SELECT camp_id, camp_name, venue FROM camps ORDER BY camp_date DESC");
                                oci_execute($rc_stmt);
                                while ($rc = oci_fetch_assoc($rc_stmt)) {
                                    echo "<option value='".$rc['CAMP_ID']."'>".$rc['CAMP_NAME']." (".$rc['VENUE'].")</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Rating (1 - 5 Stars) <span class="req">*</span></label>
                            <div class="star-rating" id="starRatingBox">
                                <span class="star" data-val="1" onclick="setStarRating(1)">&#9733;</span>
                                <span class="star" data-val="2" onclick="setStarRating(2)">&#9733;</span>
                                <span class="star" data-val="3" onclick="setStarRating(3)">&#9733;</span>
                                <span class="star" data-val="4" onclick="setStarRating(4)">&#9733;</span>
                                <span class="star active" data-val="5" onclick="setStarRating(5)">&#9733;</span>
                            </div>
                            <input type="hidden" id="selectedRating" value="5">
                        </div>

                        <div class="form-group">
                            <label for="reviewFeedbackText">Your Feedback / Comments <span class="req">*</span></label>
                            <textarea id="reviewFeedbackText" rows="4" placeholder="How was your donation experience? Were staff members helpful?" required></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary">Post Review to MongoDB</button>
                    </form>
                </div>

                <!-- Reviews Feed -->
                <div class="card">
                    <div class="card-header-flex">
                        <h3>Community Camp Reviews</h3>
                        <span class="badge badge-info">&#127757; Public Feed</span>
                    </div>
                    <div class="reviews-feed" id="reviewsFeedBox">
                        <div class="chat-loading">Loading community reviews...</div>
                    </div>
                </div>
            </div>
        </div>

    </div>


    
    <!-- Review Modal for Quick Rating from Camps Table -->
    <div id="reviewModal" class="modal-overlay" style="display:none;">
        <div class="modal-card">
            <div class="modal-header">
                <h3 id="modalCampTitle">Rate Camp</h3>
                <span class="modal-close" onclick="closeReviewModal()">&times;</span>
            </div>
            <form onsubmit="submitModalReview(event)">
                <input type="hidden" id="modalCampId">
                <div class="form-group">
                    <label>Rating</label>
                    <div class="star-rating" id="modalStarBox">
                        <span class="star" data-val="1" onclick="setModalStar(1)">&#9733;</span>
                        <span class="star" data-val="2" onclick="setModalStar(2)">&#9733;</span>
                        <span class="star" data-val="3" onclick="setModalStar(3)">&#9733;</span>
                        <span class="star" data-val="4" onclick="setModalStar(4)">&#9733;</span>
                        <span class="star active" data-val="5" onclick="setModalStar(5)">&#9733;</span>
                    </div>
                    <input type="hidden" id="modalRatingVal" value="5">
                </div>
                <div class="form-group">
                    <label>Feedback</label>
                    <textarea id="modalFeedbackText" rows="3" placeholder="Share your experience at this camp..." required></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Submit Feedback</button>
            </form>
        </div>
    </div>

    <script src="script.js"></script>
</body>
</html>
