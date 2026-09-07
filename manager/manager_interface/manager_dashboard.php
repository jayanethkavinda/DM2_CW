<?php
session_start();
require_once 'db_connect.php';

// Check Manager Authentication
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Staff', 'Admin'])) {
    header("Location: login.php");
    exit;
}

$manager_email = $_SESSION['email'] ?? 'manager@lifeline.lk';
$manager_role = $_SESSION['role'] ?? 'Staff';

$message = "";
$msg_type = "";

// 1. Handle Form Submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // 1. Schedule Camp
    if (isset($_POST['add_camp'])) {
        $id = $_POST['camp_id'];
        $name = $_POST['camp_name'];
        $date = date('d-M-Y', strtotime($_POST['camp_date'])); 
        $venue = $_POST['venue'];
        $dist = $_POST['district_id'];

        $sql = "BEGIN add_new_camp(:p_id, :p_name, TO_DATE(:p_date, 'DD-MON-YYYY'), :p_venue, :p_dist); END;";
        $stmt = oci_parse($conn, $sql);
        oci_bind_by_name($stmt, ":p_id", $id); 
        oci_bind_by_name($stmt, ":p_name", $name);
        oci_bind_by_name($stmt, ":p_date", $date); 
        oci_bind_by_name($stmt, ":p_venue", $venue);
        oci_bind_by_name($stmt, ":p_dist", $dist);
        
        if (@oci_execute($stmt)) {
            $message = "Camp scheduled successfully!"; $msg_type = "success";
        } else {
            $e = oci_error($stmt); $message = $e['message']; $msg_type = "error";
        }
    }

    // 2. Add Donation Record
    if (isset($_POST['add_donation'])) {
        $hist_id = $_POST['history_id'];
        $donor_id = $_POST['donor_id'];
        $camp_id = $_POST['camp_id'];
        $units = $_POST['units'];
        // 1. Prepare anonymous PL/SQL block to invoke the Stored Procedure
        $sql = "BEGIN add_donation_record(:h_id, :d_id, :c_id, :units); END;";
        $stmt = oci_parse($conn, $sql);

        // 2. Safely bind input parameters to prevent SQL Injection
        oci_bind_by_name($stmt, ":h_id", $hist_id); 
        oci_bind_by_name($stmt, ":d_id", $donor_id);
        oci_bind_by_name($stmt, ":c_id", $camp_id); 
        oci_bind_by_name($stmt, ":units", $units);
        
        // 3. Execute the procedure and handle database response
        if (@oci_execute($stmt)) {
            $message = "Donation recorded & Blood Inventory updated!"; $msg_type = "success";
        } else {
        // 4. Capture any Oracle Exception or business constraint failure    
            $e = oci_error($stmt); $message = $e['message']; $msg_type = "error";
        }
    }

    // 3. Approve Hospital Request
    if (isset($_POST['approve_req'])) {
        $req_id = $_POST['request_id'];
        $sql = "BEGIN approve_hospital_request(:r_id); END;";
        $stmt = oci_parse($conn, $sql);
        oci_bind_by_name($stmt, ":r_id", $req_id);
        
        if (@oci_execute($stmt)) {
            $message = "Request Approved! Blood units deducted from inventory."; $msg_type = "success";
        } else {
            $e = oci_error($stmt); $message = $e['message']; $msg_type = "error";
        }
    }

    // 4. Reject Hospital Request
    if (isset($_POST['reject_req'])) {
        $req_id = $_POST['request_id'];
        $sql = "BEGIN reject_hospital_request(:r_id); END;";
        $stmt = oci_parse($conn, $sql);
        oci_bind_by_name($stmt, ":r_id", $req_id);
        
        if (@oci_execute($stmt)) {
            $message = "Hospital Request Rejected."; $msg_type = "success";
        } else {
            $e = oci_error($stmt); $message = $e['message']; $msg_type = "error";
        }
    }

    // 5. Update Camp Venue
    if (isset($_POST['update_venue_action'])) {
        $c_id = $_POST['camp_id_edit'];
        $new_ven = $_POST['new_venue_text'];

        $sql = "BEGIN update_camp_venue(:p_id, :p_venue); END;";
        $stmt = oci_parse($conn, $sql);
        oci_bind_by_name($stmt, ":p_id", $c_id);
        oci_bind_by_name($stmt, ":p_venue", $new_ven);
        
        if (@oci_execute($stmt)) {
            $message = "Camp venue updated successfully!"; $msg_type = "success";
        } else {
            $u_stmt = oci_parse($conn, "UPDATE camps SET venue = :p_ven WHERE camp_id = :p_id");
            oci_bind_by_name($u_stmt, ":p_ven", $new_ven);
            oci_bind_by_name($u_stmt, ":p_id", $c_id);
            if (@oci_execute($u_stmt, OCI_COMMIT_ON_SUCCESS)) {
                $message = "Camp venue updated successfully!"; $msg_type = "success";
            } else {
                $e = oci_error($u_stmt); $message = $e['message']; $msg_type = "error";
            }
        }
    }
}

// 2. Fetch KPI Counters
// Total Donors
$total_donors = 0;
$td_stmt = oci_parse($conn, "SELECT COUNT(*) AS CNT FROM donors");
oci_execute($td_stmt);
if ($r = oci_fetch_assoc($td_stmt)) $total_donors = $r['CNT'];

// Pending Requests
$pending_reqs = 0;
$pr_stmt = oci_parse($conn, "SELECT COUNT(*) AS CNT FROM hospital_requests WHERE status = 'Pending'");
oci_execute($pr_stmt);
if ($r = oci_fetch_assoc($pr_stmt)) $pending_reqs = $r['CNT'];

// Total Blood Units in Stock
$total_stock = 0;
$ts_stmt = oci_parse($conn, "SELECT NVL(SUM(total_units), 0) AS TOTAL_UNITS FROM blood_inventory");
oci_execute($ts_stmt);
if ($r = oci_fetch_assoc($ts_stmt)) $total_stock = $r['TOTAL_UNITS'];

// Upcoming Camps Count
$upcoming_camps_cnt = 0;
$uc_stmt = oci_parse($conn, "SELECT COUNT(*) AS CNT FROM camps WHERE camp_date >= TRUNC(SYSDATE)");
oci_execute($uc_stmt);
if ($r = oci_fetch_assoc($uc_stmt)) $upcoming_camps_cnt = $r['CNT'];




// Fetch Blood Inventory Data for Chart
$bg_labels = [];
$bg_values = [];

// Prepare query to fetch stock units grouped by blood group alphabetically
$chart_stmt = oci_parse($conn, "SELECT blood_group, total_units FROM blood_inventory ORDER BY blood_group ASC");
oci_execute($chart_stmt);

// Traverse database records and populate PHP parallel arrays
while ($crow = oci_fetch_assoc($chart_stmt)) {
    $bg_labels[] = $crow['BLOOD_GROUP'];       // Labels: ['A+', 'A-', 'AB+', 'AB-', 'B+', 'B-', 'O+', 'O-']
    $bg_values[] = intval($crow['TOTAL_UNITS']);  // Stock Values: [1, 0, 0, 0, 0, 0, 0, 0]
}

// Fetch Districts for Dropdown
$districts_list = [];
$dist_stmt = oci_parse($conn, "SELECT district_id, district_name FROM districts ORDER BY district_name ASC");
oci_execute($dist_stmt);
while ($row = oci_fetch_assoc($dist_stmt)) {
    $districts_list[] = $row;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Dashboard - LifeLineConnect</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

    <!-- Sidebar Navigation -->
    <div class="sidebar">
        <div class="sidebar-brand">
            <span class="brand-icon">&#129656;</span>
            <div>
                <h2>LifeLineConnect</h2>
                <small class="badge-role">Manager Panel</small>
            </div>
        </div>

        <div class="sidebar-user">
            <div class="user-avatar">M</div>
            <div class="user-details">
                <strong><?php echo htmlspecialchars($manager_role === 'Admin' ? 'Admin / Manager' : 'Blood Bank Manager'); ?></strong>
                <small><?php echo htmlspecialchars($manager_email); ?></small>
            </div>
        </div>

        <ul class="nav-menu">
            <li><a href="manager_dashboard.php" class="nav-link active"><span class="nav-icon">&#128202;</span> Dashboard Stats</a></li>
            <li><a href="../../reports/index.php" class="nav-link"><span class="nav-icon">&#128196;</span> PL/SQL Business Reports</a></li>
            <li><a href="manager_chat.php" class="nav-link"><span class="nav-icon">&#128172;</span> Donor Messages (Chat)</a></li>
            <li><a href="manager_camps.php" class="nav-link"><span class="nav-icon">&#127973;</span> Camps Management</a></li>
            <li><a href="#inventory-section" class="nav-link"><span class="nav-icon">&#129514;</span> Blood Inventory & Records</a></li>
            <li><a href="#hospital-req" class="nav-link"><span class="nav-icon">&#127973;</span> Hospital Requests</a></li>
            <li><a href="#donor-list" class="nav-link"><span class="nav-icon">&#128100;</span> Registered Donors</a></li>
            <li class="nav-divider"></li>
            <li><a href="logout.php" class="nav-link" style="color: #FECDD3;"><span class="nav-icon">&#128682;</span> Log Out</a></li>
        </ul>
    </div>

    <!-- Main Content Area -->
    <div class="main-content">
        
        <!-- Header Bar -->
        <div class="header">
            <div>
                <h1>Blood Bank Manager Portal</h1>
                <p class="header-sub">LifeLineConnect Central Blood Donation & Inventory Management System</p>
            </div>
        </div>

        <!-- Alert Notifications -->
        <?php if($message != ""): ?>
            <div class="alert <?php echo $msg_type; ?>" id="alertBox">
                <span><?php echo $message; ?></span>
                <span class="close-btn" onclick="closeAlert()">&times;</span>
            </div>
        <?php endif; ?>

        <!-- KPI Stats Counter Cards -->
        <div class="stat-grid" id="stats-overview">
            <div class="stat-card stat-crimson">
                <div class="stat-icon">&#128100;</div>
                <div>
                    <span class="stat-label">Total Donors</span>
                    <h3 class="stat-value"><?php echo $total_donors; ?></h3>
                </div>
            </div>

            <div class="stat-card stat-burgundy">
                <div class="stat-icon">&#129656;</div>
                <div>
                    <span class="stat-label">Blood Stock in Units</span>
                    <h3 class="stat-value"><?php echo $total_stock; ?> <small style="font-size: 13px;">Units</small></h3>
                </div>
            </div>

            <div class="stat-card stat-amber">
                <div class="stat-icon">&#9203;</div>
                <div>
                    <span class="stat-label">Pending Hospital Reqs</span>
                    <h3 class="stat-value"><?php echo $pending_reqs; ?></h3>
                </div>
            </div>

            <div class="stat-card stat-green">
                <div class="stat-icon">&#127973;</div>
                <div>
                    <span class="stat-label">Upcoming Camps</span>
                    <h3 class="stat-value"><?php echo $upcoming_camps_cnt; ?></h3>
                </div>
            </div>
        </div>



        <!-- ======================================================================= -->
        <!-- 2. UNIFIED BLOOD INVENTORY & DONATIONS SECTION (BORDERED BOX COVERING 3) -->
        <!-- ======================================================================= -->
        <div class="inventory-management-wrapper" id="inventory-section">

            <!-- 2.1 RECORD BLOOD DONATION FORM (INNER CARD 1) -->
            <div class="inner-card" id="record-donation">
                <h3>Record Blood Donation (Updates Inventory)</h3>
                <p style="font-size: 13px; color: #64748B; margin-bottom: 18px;">Record blood collected from a registered donor at a specific camp. The trigger will automatically increase the blood stock.</p>
                <form action="manager_dashboard.php" method="POST" style="max-width: 650px;">
                    <label>History Record ID</label>
                    <input type="number" name="history_id" placeholder="e.g. 501" required>
                    
                    <label>Select Donor</label>
                    <select name="donor_id" required>
                        <option value="">-- Choose Donor --</option>
                        <?php
                        $d_stmt = oci_parse($conn, "SELECT donor_id, full_name, blood_group FROM donors ORDER BY full_name ASC");
                        oci_execute($d_stmt);
                        while ($row = oci_fetch_assoc($d_stmt)) {
                            echo "<option value='".$row['DONOR_ID']."'>ID: #".$row['DONOR_ID']." - ".$row['FULL_NAME']." (".$row['BLOOD_GROUP'].")</option>";
                        }
                        ?>
                    </select>

                    <label>Select Camp</label>
                    <select name="camp_id" required>
                        <option value="">-- Choose Camp --</option>
                        <?php
                        $c_stmt = oci_parse($conn, "SELECT camp_id, camp_name, venue FROM camps ORDER BY camp_date DESC");
                        oci_execute($c_stmt);
                        while ($row = oci_fetch_assoc($c_stmt)) {
                            echo "<option value='".$row['CAMP_ID']."'>#".$row['CAMP_ID']." - ".$row['CAMP_NAME']." (".$row['VENUE'].")</option>";
                        }
                        ?>
                    </select>

                    <label>Blood Units Collected</label>
                    <input type="number" name="units" min="1" max="5" value="1" required>
                    
                    <button type="submit" name="add_donation" style="width: auto; padding: 11px 26px;">Submit Donation Record</button>
                </form>
            </div>

            <!-- 2.2 BLOOD INVENTORY CHART (INNER CARD 2) -->
            <div class="inner-card" id="inventory-chart-card">
                <h3>Current Blood Group Stock Inventory</h3>
                <p style="font-size: 13px; color: #64748B; margin-bottom: 15px;">Real-time graphical breakdown of total units available per blood group.</p>
                <div class="chart-wrapper">
                    <canvas id="inventoryChart"></canvas>
                </div>
            </div>

            <!-- 2.3 CAMP-WISE BLOOD COLLECTIONS TABLE (INNER CARD 3) -->
            <div class="inner-card" id="camp-collections">
                <h3>Camp-wise Blood Collection (View)</h3>
                <p style="font-size: 13px; color: #64748B; margin-bottom: 15px;">Aggregated collection statistics grouped by camp and blood group.</p>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Camp Name</th>
                            <th>District</th>
                            <th>Blood Group</th>
                            <th>Total Units Collected</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $vw_stmt = oci_parse($conn, "SELECT * FROM vw_camp_blood_collection ORDER BY camp_name");
                        $has_coll = false;
                        if (@oci_execute($vw_stmt)) {
                            while ($row = oci_fetch_assoc($vw_stmt)) {
                                $has_coll = true;
                                echo "<tr>";
                                echo "<td><strong>".htmlspecialchars($row['CAMP_NAME'])."</strong></td>";
                                echo "<td>".htmlspecialchars($row['DISTRICT_NAME'])."</td>";
                                echo "<td><span class='blood-tag'>".$row['BLOOD_GROUP']."</span></td>";
                                echo "<td>".$row['TOTAL_COLLECTED']." Units</td>";
                                echo "</tr>";
                            }
                        }
                        if (!$has_coll) {
                            echo "<tr><td colspan='4' style='text-align: center; color: #94A3B8; padding: 25px;'>No camp collection data recorded yet.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>

        </div>

        <!-- 3. PENDING HOSPITAL REQUESTS TABLE -->
        <div class="card full-width" id="hospital-req" style="margin-bottom: 30px;">
            <h3>Pending Hospital Blood Requests</h3>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Req ID</th>
                        <th>Hospital Name</th>
                        <th>Blood Group</th>
                        <th>Units Needed</th>
                        <th>Request Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $req_stmt = oci_parse($conn, "SELECT request_id, hospital_name, blood_group, units_needed, TO_CHAR(request_date, 'YYYY-MM-DD') AS r_date FROM hospital_requests WHERE status = 'Pending'");
                    oci_execute($req_stmt);
                    $has_reqs = false;
                    while ($row = oci_fetch_assoc($req_stmt)) {
                        $has_reqs = true;
                        echo "<tr>";
                        echo "<td>#".$row['REQUEST_ID']."</td>";
                        echo "<td><strong>".htmlspecialchars($row['HOSPITAL_NAME'])."</strong></td>";
                        echo "<td><span class='blood-tag'>".$row['BLOOD_GROUP']."</span></td>";
                        echo "<td>".$row['UNITS_NEEDED']." Units</td>";
                        echo "<td>&#128197; ".$row['R_DATE']."</td>";
                        echo "<td style='display:flex; gap:8px;'>
                                <form method='POST' style='margin:0;'>
                                    <input type='hidden' name='request_id' value='".$row['REQUEST_ID']."'>
                                    <button type='submit' name='approve_req' class='btn-small btn-success'>Approve</button>
                                </form>
                                <form method='POST' style='margin:0;'>
                                    <input type='hidden' name='request_id' value='".$row['REQUEST_ID']."'>
                                    <button type='submit' name='reject_req' class='btn-small btn-danger'>Reject</button>
                                </form>
                              </td>";
                        echo "</tr>";
                    }
                    if (!$has_reqs) {
                        echo "<tr><td colspan='6' style='text-align: center; color: #94A3B8; padding: 25px;'>No pending hospital requests currently.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <!-- 4. REGISTERED DONORS TABLE -->
        <div class="card full-width" id="donor-list" style="margin-bottom: 30px;">
            <h3>Registered Donors Details</h3>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Donor ID</th>
                        <th>Full Name</th>
                        <th>Blood Group</th>
                        <th>Contact No</th>
                        <th>Gender</th>
                    </tr>
                </thead>
                <tbody>

                    <?php
                    // 1. Prepare and execute query to retrieve all registered donors sorted by ID
                    $don_stmt = oci_parse($conn, "SELECT donor_id, full_name, blood_group, contact_no, gender FROM donors ORDER BY donor_id ASC");
                    oci_execute($don_stmt);
                    $has_donors = false;
                    // 2. Fetch and render each donor record dynamically into the table
                    while ($row = oci_fetch_assoc($don_stmt)) {
                        $has_donors = true;
                        echo "<tr>";
                        echo "<td>#".$row['DONOR_ID']."</td>";
                        echo "<td><strong>".htmlspecialchars($row['FULL_NAME'])."</strong></td>";
                           // 3. Render blood group tag with distinctive UI badge
                        echo "<td><span class='blood-tag'>".($row['BLOOD_GROUP'] ?: 'N/A')."</span></td>";
                        echo "<td>".htmlspecialchars($row['CONTACT_NO'] ?: 'N/A')."</td>";
                        echo "<td>".htmlspecialchars($row['GENDER'] ?: 'N/A')."</td>";
                        echo "</tr>";
                    }
                    
                   // 4. Display fallback message if no donors are registered
                    if (!$has_donors) {
                        echo "<tr><td colspan='5' style='text-align: center; color: #94A3B8; padding: 25px;'>No registered donors in the system.</td></tr>";
                    }
                    ?>


                </tbody>
            </table>
        </div>

    </div>

    <script>
        const chartLabels = <?php echo json_encode($bg_labels); ?>;
        const chartData = <?php echo json_encode($bg_values); ?>;
    </script>
    <script src="script.js"></script>
</body>
</html>