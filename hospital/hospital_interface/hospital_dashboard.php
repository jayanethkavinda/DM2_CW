<?php
session_start();
require_once 'db_connect.php';

// Auth Protection: Check if logged in as Hospital
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'Hospital') {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$hospital_name = $_SESSION['hospital_name'] ?? 'General Hospital';
$email = $_SESSION['email'] ?? '';

$message = "";
$msg_type = "";

// ------------------------------------------------------------------------------
// 1. Handle New Blood Requisition Submission
// ------------------------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_requisition'])) {
    $req_hname = trim($_POST['hospital_name'] ?? $hospital_name);
    $blood_group = $_POST['blood_group'] ?? '';
    $units_needed = intval($_POST['units_needed'] ?? 0);

    if (empty($req_hname) || empty($blood_group) || $units_needed <= 0) {
        $message = "Please fill in all required fields. Units requested must be greater than zero.";
        $msg_type = "error";
    } else {
        // Try calling PL/SQL Stored Procedure submit_hospital_blood_request
        $proc_sql = "BEGIN submit_hospital_blood_request(:p_hname, :p_bg, :p_units, :o_rid); END;";
        $proc_stmt = oci_parse($conn, $proc_sql);
        $out_rid = 0;
        oci_bind_by_name($proc_stmt, ":p_hname", $req_hname);
        oci_bind_by_name($proc_stmt, ":p_bg", $blood_group);
        oci_bind_by_name($proc_stmt, ":p_units", $units_needed);
        oci_bind_by_name($proc_stmt, ":o_rid", $out_rid, 10);

        if (@oci_execute($proc_stmt)) {
            $message = "Blood requisition #$out_rid submitted successfully! The Blood Bank Manager has been notified.";
            $msg_type = "success";
            $_SESSION['hospital_name'] = $req_hname;
            $hospital_name = $req_hname;
        } else {
            // Direct SQL fallback
            $rid_stmt = oci_parse($conn, "SELECT NVL(MAX(request_id), 0) + 1 AS NEXT_RID FROM hospital_requests");
            oci_execute($rid_stmt);
            $rid_row = oci_fetch_assoc($rid_stmt);
            $next_rid = $rid_row['NEXT_RID'];

            $ins_sql = "INSERT INTO hospital_requests (request_id, hospital_name, blood_group, units_needed, request_date, status) 
                        VALUES (:p_req_id, :p_hosp_name, :p_bgroup, :p_req_units, TRUNC(SYSDATE), 'Pending')";
            $ins_stmt = oci_parse($conn, $ins_sql);
            oci_bind_by_name($ins_stmt, ":p_req_id", $next_rid);
            oci_bind_by_name($ins_stmt, ":p_hosp_name", $req_hname);
            oci_bind_by_name($ins_stmt, ":p_bgroup", $blood_group);
            oci_bind_by_name($ins_stmt, ":p_req_units", $units_needed);

            if (@oci_execute($ins_stmt)) {
                $message = "Blood requisition #$next_rid submitted successfully!";
                $msg_type = "success";
                $_SESSION['hospital_name'] = $req_hname;
                $hospital_name = $req_hname;
            } else {
                $e = oci_error($ins_stmt);
                $message = "Submission Failed: " . ($e['message'] ?? 'Could not record requisition.');
                $msg_type = "error";
            }
        }
    }
}

// ------------------------------------------------------------------------------
// 2. Handle Requisition Cancellation
// ------------------------------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'cancel_request' && isset($_GET['request_id'])) {
    $cancel_id = intval($_GET['request_id']);
    
    // Call PL/SQL procedure cancel_hospital_request or fallback update
    $can_plsql = "BEGIN cancel_hospital_request(:p_rid, :p_hname); END;";
    $can_stmt = oci_parse($conn, $can_plsql);
    oci_bind_by_name($can_stmt, ":p_rid", $cancel_id);
    oci_bind_by_name($can_stmt, ":p_hname", $hospital_name);

    if (@oci_execute($can_stmt)) {
        $message = "Requisition #$cancel_id has been cancelled successfully.";
        $msg_type = "success";
    } else {
        $fallback_can = oci_parse($conn, "UPDATE hospital_requests SET status = 'Cancelled' WHERE request_id = :p_rid AND status = 'Pending'");
        oci_bind_by_name($fallback_can, ":p_rid", $cancel_id);
        if (@oci_execute($fallback_can)) {
            $message = "Requisition #$cancel_id has been cancelled.";
            $msg_type = "success";
        } else {
            $message = "Unable to cancel requisition.";
            $msg_type = "error";
        }
    }
}

// ------------------------------------------------------------------------------
// 3. Fetch KPI Metrics for this Hospital
// ------------------------------------------------------------------------------
// Total units requested
$m_req_stmt = oci_parse($conn, "SELECT NVL(SUM(units_needed), 0) AS TOTAL_REQ FROM hospital_requests WHERE LOWER(TRIM(hospital_name)) = LOWER(TRIM(:p_hname)) AND status IN ('Approved', 'Pending')");
oci_bind_by_name($m_req_stmt, ":p_hname", $hospital_name);
oci_execute($m_req_stmt);
$req_m_row = oci_fetch_assoc($m_req_stmt);
$total_requested_units = intval($req_m_row['TOTAL_REQ'] ?? 0);

// Total units approved
$m_app_stmt = oci_parse($conn, "SELECT NVL(SUM(units_needed), 0) AS TOTAL_APP FROM hospital_requests WHERE LOWER(TRIM(hospital_name)) = LOWER(TRIM(:p_hname)) AND status = 'Approved'");
oci_bind_by_name($m_app_stmt, ":p_hname", $hospital_name);
oci_execute($m_app_stmt);
$app_m_row = oci_fetch_assoc($m_app_stmt);
$total_approved_units = intval($app_m_row['TOTAL_APP'] ?? 0);

// Pending requisitions count
$m_pnd_stmt = oci_parse($conn, "SELECT COUNT(*) AS TOTAL_PND FROM hospital_requests WHERE LOWER(TRIM(hospital_name)) = LOWER(TRIM(:p_hname)) AND status = 'Pending'");
oci_bind_by_name($m_pnd_stmt, ":p_hname", $hospital_name);
oci_execute($m_pnd_stmt);
$pnd_m_row = oci_fetch_assoc($m_pnd_stmt);
$total_pending_count = intval($pnd_m_row['TOTAL_PND'] ?? 0);

// Central blood bank inventory total units
$inv_tot_stmt = oci_parse($conn, "SELECT NVL(SUM(total_units), 0) AS BANK_TOTAL FROM blood_inventory");
oci_execute($inv_tot_stmt);
$inv_tot_row = oci_fetch_assoc($inv_tot_stmt);
$central_bank_total = intval($inv_tot_row['BANK_TOTAL'] ?? 0);

// List of Blood Groups
$blood_groups_list = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hospital Dashboard - LifeLineConnect</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <div class="app-container">
        <!-- Sidebar Navigation -->
        <aside class="sidebar">
            <div class="brand-logo">
                <div class="brand-icon">&#127973;</div>
                <div class="brand-text">
                    <h2>LifeLineConnect</h2>
                    <span>Hospital Portal</span>
                </div>
            </div>

            <ul class="nav-menu">
                <li>
                    <a class="nav-link active" onclick="showHospitalTab('tab-overview', this)">
                        <span class="nav-icon">&#128202;</span> Dashboard Overview
                    </a>
                </li>
                <li>
                    <a class="nav-link" onclick="showHospitalTab('tab-new-request', this)">
                        <span class="nav-icon">&#10010;</span> Request Blood Units
                    </a>
                </li>
                <li>
                    <a class="nav-link" onclick="showHospitalTab('tab-requests', this)">
                        <span class="nav-icon">&#128203;</span> My Blood Requisitions
                    </a>
                </li>
                <li>
                    <a class="nav-link" onclick="showHospitalTab('tab-stock', this)">
                        <span class="nav-icon">&#129514;</span> Central Stock Status
                    </a>
                </li>
            </ul>

            <div class="sidebar-footer">
                <div class="hospital-badge-card">
                    <small>Logged in Institution</small>
                    <strong title="<?php echo htmlspecialchars($hospital_name); ?>"><?php echo htmlspecialchars($hospital_name); ?></strong>
                </div>
                <a href="logout.php" class="btn btn-danger btn-small" style="width: 100%;">Sign Out</a>
            </div>
        </aside>

        <!-- Main Content Area -->
        <main class="main-wrapper">
            <header class="top-navbar">
                <div class="page-title">
                    <h1>Hospital Blood Requisition Center</h1>
                    <p>Request emergency blood reserves, track approval statuses, and view live inventory.</p>
                </div>
                <div class="top-user-actions">
                    <div class="user-pill">
                        <div class="user-avatar">&#127973;</div>
                        <span class="user-name"><?php echo htmlspecialchars($hospital_name); ?></span>
                    </div>
                </div>
            </header>

            <div class="content-body">
                <?php if (!empty($message)): ?>
                    <div class="alert alert-<?php echo $msg_type; ?>" id="alertBox">
                        <span><?php echo htmlspecialchars($message); ?></span>
                        <span style="cursor: pointer;" onclick="closeAlert()">&times;</span>
                    </div>
                <?php endif; ?>

                <!-- ======================================================================= -->
                <!-- TAB 1: DASHBOARD OVERVIEW -->
                <!-- ======================================================================= -->
                <div id="tab-overview" class="content-tab active">
                    <!-- Metric Stat Cards Grid -->
                    <div class="stats-grid">
                        <div class="stat-card stat-blue">
                            <div class="stat-icon">&#128221;</div>
                            <div class="stat-info">
                                <span class="stat-label">Total Units Requested</span>
                                <h3 class="stat-value"><?php echo $total_requested_units; ?> <small>Units</small></h3>
                            </div>
                        </div>

                        <div class="stat-card stat-teal">
                            <div class="stat-icon">&#9989;</div>
                            <div class="stat-info">
                                <span class="stat-label">Total Approved Units</span>
                                <h3 class="stat-value"><?php echo $total_approved_units; ?> <small>Units</small></h3>
                            </div>
                        </div>

                        <div class="stat-card stat-amber">
                            <div class="stat-icon">&#9203;</div>
                            <div class="stat-info">
                                <span class="stat-label">Pending Requisitions</span>
                                <h3 class="stat-value"><?php echo $total_pending_count; ?></h3>
                            </div>
                        </div>

                        <div class="stat-card stat-rose">
                            <div class="stat-icon">&#129514;</div>
                            <div class="stat-info">
                                <span class="stat-label">Central Bank Reserves</span>
                                <h3 class="stat-value"><?php echo $central_bank_total; ?> <small>Units</small></h3>
                            </div>
                        </div>
                    </div>

                    <!-- Live Central Blood Stock Preview -->
                    <div class="card">
                        <div class="card-header-flex">
                            <div>
                                <h3>Real-Time Central Blood Bank Stock Availability</h3>
                                <p class="subtitle-card">Current inventory units available at the Central Blood Bank for hospital dispatch.</p>
                            </div>
                            <button class="btn btn-primary btn-small" onclick="showHospitalTab('tab-new-request')">&#10010; Request Blood Now</button>
                        </div>

                        <div class="stock-grid">
                            <?php
                            $stk_stmt = oci_parse($conn, "SELECT blood_group, total_units FROM blood_inventory ORDER BY blood_group ASC");
                            oci_execute($stk_stmt);
                            while ($s_row = oci_fetch_assoc($stk_stmt)):
                            ?>
                                <div class="stock-box">
                                    <div class="stock-bg-name"><?php echo htmlspecialchars($s_row['BLOOD_GROUP']); ?></div>
                                    <div class="stock-units"><?php echo intval($s_row['TOTAL_UNITS']); ?> <small>Units</small></div>
                                    <span class="badge <?php echo ($s_row['TOTAL_UNITS'] > 0) ? 'badge-approved' : 'badge-rejected'; ?>">
                                        <?php echo ($s_row['TOTAL_UNITS'] > 0) ? 'Available' : 'Out of Stock'; ?>
                                    </span>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    </div>

                    <!-- Recent Requisitions Table Preview -->
                    <div class="card">
                        <div class="card-header-flex">
                            <div>
                                <h3>Recent Blood Requisitions</h3>
                                <p class="subtitle-card">Latest requests submitted by <?php echo htmlspecialchars($hospital_name); ?>.</p>
                            </div>
                            <button class="btn btn-primary btn-small" onclick="showHospitalTab('tab-requests')">View All Requisitions</button>
                        </div>

                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Request ID</th>
                                    <th>Blood Group</th>
                                    <th>Units Needed</th>
                                    <th>Request Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $r_rec_stmt = oci_parse($conn, "SELECT request_id, blood_group, units_needed, TO_CHAR(request_date, 'YYYY-MM-DD') AS RDATE, status 
                                                               FROM hospital_requests 
                                                               WHERE LOWER(TRIM(hospital_name)) = LOWER(TRIM(:p_hname)) 
                                                               ORDER BY request_id DESC");
                                oci_bind_by_name($r_rec_stmt, ":p_hname", $hospital_name);
                                oci_execute($r_rec_stmt);
                                $cnt = 0;
                                while (($r_row = oci_fetch_assoc($r_rec_stmt)) && $cnt < 5):
                                    $cnt++;
                                    $badge_cls = 'badge-pending';
                                    if ($r_row['STATUS'] === 'Approved') $badge_cls = 'badge-approved';
                                    elseif ($r_row['STATUS'] === 'Rejected') $badge_cls = 'badge-rejected';
                                    elseif ($r_row['STATUS'] === 'Cancelled') $badge_cls = 'badge-cancelled';
                                ?>
                                    <tr>
                                        <td><strong>#<?php echo $r_row['REQUEST_ID']; ?></strong></td>
                                        <td><span class="badge badge-info" style="font-size:13px; font-weight:700;"><?php echo htmlspecialchars($r_row['BLOOD_GROUP']); ?></span></td>
                                        <td><strong><?php echo $r_row['UNITS_NEEDED']; ?></strong> Unit(s)</td>
                                        <td>&#128197; <?php echo $r_row['RDATE']; ?></td>
                                        <td><span class="badge <?php echo $badge_cls; ?>"><?php echo $r_row['STATUS']; ?></span></td>
                                    </tr>
                                <?php endwhile; ?>
                                <?php if ($cnt === 0): ?>
                                    <tr><td colspan="5" style="text-align: center; color: #94a3b8; padding: 24px;">No requisition records found. Submit a request using the tab above.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>


                <!-- ======================================================================= -->
                <!-- TAB 2: REQUEST BLOOD UNITS -->
                <!-- ======================================================================= -->
                <div id="tab-new-request" class="content-tab">
                    <div class="card full-width">
                        <div class="card-header-flex">
                            <div>
                                <h3>New Hospital Blood Requisition Form</h3>
                                <p class="subtitle-card">Submit a formal request to the Central Blood Bank for emergency clinical or surgery supply.</p>
                            </div>
                        </div>

                        <form action="hospital_dashboard.php" method="POST">
                            <input type="hidden" name="submit_requisition" value="1">

                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="req_hname">Hospital / Institution Name <span style="color:var(--accent)">*</span></label>
                                    <input type="text" id="req_hname" name="hospital_name" required value="<?php echo htmlspecialchars($hospital_name); ?>">
                                </div>

                                <div class="form-group">
                                    <label for="req_bg">Blood Group Required <span style="color:var(--accent)">*</span></label>
                                    <select id="req_bg" name="blood_group" required>
                                        <option value="">-- Select Blood Group --</option>
                                        <?php foreach($blood_groups_list as $bg_opt): ?>
                                            <option value="<?php echo $bg_opt; ?>"><?php echo $bg_opt; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="req_units">Units Needed (Pints / Bags) <span style="color:var(--accent)">*</span></label>
                                    <input type="number" id="req_units" name="units_needed" min="1" max="100" placeholder="e.g. 5" required>
                                </div>

                                <div class="form-group">
                                    <label for="req_urgency">Urgency Classification</label>
                                    <select id="req_urgency" name="urgency">
                                        <option value="Standard">Standard (Within 24 Hours)</option>
                                        <option value="Urgent">Urgent (Within 6 Hours)</option>
                                        <option value="Emergency">Critical Emergency (Immediate Dispatch)</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group" style="margin-top: 20px;">
                                <label for="req_notes">Clinical Reason / Ward Details (Optional)</label>
                                <textarea id="req_notes" rows="3" placeholder="e.g. For scheduled cardiovascular surgeries / Emergency Trauma Unit"></textarea>
                            </div>

                            <div style="margin-top: 24px; display: flex; gap: 12px;">
                                <button type="submit" class="btn btn-primary" style="padding: 12px 28px;">Submit Requisition to Blood Bank</button>
                                <button type="reset" class="btn btn-small" style="background: var(--light); color: var(--dark-muted);">Reset Form</button>
                            </div>
                        </form>
                    </div>
                </div>


                <!-- ======================================================================= -->
                <!-- TAB 3: ALL REQUISITIONS HISTORY -->
                <!-- ======================================================================= -->
                <div id="tab-requests" class="content-tab">
                    <div class="card full-width">
                        <div class="card-header-flex">
                            <div>
                                <h3>My Hospital Blood Requisitions Ledger</h3>
                                <p class="subtitle-card">Complete track of all submitted blood requests and approval decisions.</p>
                            </div>
                            <div style="display: flex; gap: 12px;">
                                <select onchange="filterRequestsByStatus(this.value)" style="padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border);">
                                    <option value="ALL">All Statuses</option>
                                    <option value="Pending">Pending</option>
                                    <option value="Approved">Approved</option>
                                    <option value="Rejected">Rejected</option>
                                    <option value="Cancelled">Cancelled</option>
                                </select>
                                <input type="text" id="requestSearchInput" placeholder="Search blood group..." onkeyup="searchRequestsTable()" style="padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border);">
                            </div>
                        </div>

                        <table class="data-table" id="hospitalRequestsTable" style="margin-top: 16px;">
                            <thead>
                                <tr>
                                    <th>Request ID</th>
                                    <th>Hospital Name</th>
                                    <th>Blood Group</th>
                                    <th>Units Needed</th>
                                    <th>Request Date</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $all_req_stmt = oci_parse($conn, "SELECT request_id, hospital_name, blood_group, units_needed, TO_CHAR(request_date, 'YYYY-MM-DD') AS RDATE, status 
                                                                  FROM hospital_requests 
                                                                  WHERE LOWER(TRIM(hospital_name)) = LOWER(TRIM(:p_hname)) 
                                                                  ORDER BY request_id DESC");
                                oci_bind_by_name($all_req_stmt, ":p_hname", $hospital_name);
                                oci_execute($all_req_stmt);
                                $total_tbl_rows = 0;

                                while ($row = oci_fetch_assoc($all_req_stmt)):
                                    $total_tbl_rows++;
                                    $b_class = 'badge-pending';
                                    if ($row['STATUS'] === 'Approved') $b_class = 'badge-approved';
                                    elseif ($row['STATUS'] === 'Rejected') $b_class = 'badge-rejected';
                                    elseif ($row['STATUS'] === 'Cancelled') $b_class = 'badge-cancelled';
                                ?>
                                    <tr data-status="<?php echo $row['STATUS']; ?>">
                                        <td><strong>#<?php echo $row['REQUEST_ID']; ?></strong></td>
                                        <td><?php echo htmlspecialchars($row['HOSPITAL_NAME']); ?></td>
                                        <td><span class="badge badge-info" style="font-size: 13px; font-weight: 700;"><?php echo htmlspecialchars($row['BLOOD_GROUP']); ?></span></td>
                                        <td><strong><?php echo $row['UNITS_NEEDED']; ?></strong> Unit(s)</td>
                                        <td>&#128197; <?php echo $row['RDATE']; ?></td>
                                        <td><span class="badge <?php echo $b_class; ?>"><?php echo $row['STATUS']; ?></span></td>
                                        <td>
                                            <?php if ($row['STATUS'] === 'Pending'): ?>
                                                <a href="hospital_dashboard.php?action=cancel_request&request_id=<?php echo $row['REQUEST_ID']; ?>" 
                                                   class="btn btn-danger btn-small"
                                                   onclick="return confirm('Are you sure you want to cancel Requisition #<?php echo $row['REQUEST_ID']; ?>?');">
                                                   Cancel
                                                </a>
                                            <?php else: ?>
                                                <span style="color: #94a3b8; font-size: 12px;">Finalized</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                                <?php if ($total_tbl_rows === 0): ?>
                                    <tr><td colspan="7" style="text-align: center; color: #94a3b8; padding: 30px;">No requisitions recorded for this hospital.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>


                <!-- ======================================================================= -->
                <!-- TAB 4: CENTRAL BLOOD STOCK STATUS -->
                <!-- ======================================================================= -->
                <div id="tab-stock" class="content-tab">
                    <div class="card full-width">
                        <div class="card-header-flex">
                            <div>
                                <h3>Central Blood Bank Reserves Telemetry</h3>
                                <p class="subtitle-card">Live stock levels across all blood groups in the central database.</p>
                            </div>
                        </div>

                        <table class="data-table" style="margin-top: 16px;">
                            <thead>
                                <tr>
                                    <th>Blood Group</th>
                                    <th>Available Stock</th>
                                    <th>Status Assessment</th>
                                    <th>Requisition Recommendation</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $stk_all = oci_parse($conn, "SELECT blood_group, total_units FROM blood_inventory ORDER BY blood_group ASC");
                                oci_execute($stk_all);
                                while ($stk = oci_fetch_assoc($stk_all)):
                                    $units = intval($stk['TOTAL_UNITS']);
                                    $badge = 'badge-approved';
                                    $assessment = 'Sufficient Inventory';
                                    $recom = 'Immediate dispatch available';
                                    if ($units === 0) {
                                        $badge = 'badge-rejected';
                                        $assessment = 'Critical Stockout';
                                        $recom = 'Emergency camp collection in progress';
                                    } elseif ($units < 5) {
                                        $badge = 'badge-pending';
                                        $assessment = 'Low Reserve';
                                        $recom = 'Prioritized for emergency cases only';
                                    }
                                ?>
                                    <tr>
                                        <td><span class="badge badge-info" style="font-size: 14px; font-weight: 800;"><?php echo htmlspecialchars($stk['BLOOD_GROUP']); ?></span></td>
                                        <td><strong style="font-size: 16px;"><?php echo $units; ?></strong> Units</td>
                                        <td><span class="badge <?php echo $badge; ?>"><?php echo $assessment; ?></span></td>
                                        <td style="color: var(--dark-muted);"><?php echo $recom; ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <script src="script.js"></script>
</body>
</html>
