<?php
require_once 'db_connect.php';

$report_type = $_GET['report_type'] ?? '1';
$param_val = $_GET['param_val'] ?? '';
$error_message = "";

// Data containers
$rows = [];
$donor_header = null;
$kpis = [];

try {
    switch ($report_type) {
        case '1': // Camp Blood Collection
            $camp_id_param = (!empty($param_val) && is_numeric($param_val)) ? intval($param_val) : null;
            $curs = oci_new_cursor($conn);
            $stmt = oci_parse($conn, "BEGIN rpt_camp_blood_collection(:p_cid, :p_curs); END;");
            oci_bind_by_name($stmt, ":p_cid", $camp_id_param);
            oci_bind_by_name($stmt, ":p_curs", $curs, -1, OCI_B_CURSOR);
            
            if (@oci_execute($stmt) && @oci_execute($curs)) {
                while ($r = oci_fetch_assoc($curs)) {
                    $rows[] = $r;
                }
                $total_units = 0;
                $total_donors = 0;
                $camps_set = [];
                foreach ($rows as $row) {
                    $total_units += intval($row['UNITS_COLLECTED'] ?? 0);
                    $total_donors += intval($row['DONOR_COUNT'] ?? 0);
                    $camps_set[$row['CAMP_ID']] = true;
                }
                $kpis = [
                    ['label' => 'Total Blood Units Collected', 'val' => $total_units . ' Units', 'color' => '#1e293b', 'icon' => '&#129514;'],
                    ['label' => 'Total Donors Participated', 'val' => $total_donors . ' Donors', 'color' => '#16a34a', 'icon' => '&#128100;'],
                    ['label' => 'Camps Included in Report', 'val' => count($camps_set) . ' Camps', 'color' => '#0284c7', 'icon' => '&#127973;']
                ];
            } else {
                $e = oci_error($stmt);
                $error_message = $e['message'] ?? 'Unable to execute report procedure.';
            }
            break;

        case '2': // Blood Inventory & Stock Alert
            $threshold = (!empty($param_val) && is_numeric($param_val)) ? intval($param_val) : 10;
            $curs = oci_new_cursor($conn);
            $stmt = oci_parse($conn, "BEGIN rpt_inventory_expiry_alert(:p_thresh, :p_curs); END;");
            oci_bind_by_name($stmt, ":p_thresh", $threshold);
            oci_bind_by_name($stmt, ":p_curs", $curs, -1, OCI_B_CURSOR);
            
            if (@oci_execute($stmt) && @oci_execute($curs)) {
                while ($r = oci_fetch_assoc($curs)) {
                    $rows[] = $r;
                }
                $total_units = 0;
                $critical_cnt = 0;
                foreach ($rows as $row) {
                    $total_units += intval($row['TOTAL_UNITS'] ?? 0);
                    if ($row['ALERT_LEVEL'] === 'danger' || $row['ALERT_LEVEL'] === 'warning') {
                        $critical_cnt++;
                    }
                }
                $kpis = [
                    ['label' => 'Total Inventory Stock', 'val' => $total_units . ' Units', 'color' => '#0284c7', 'icon' => '&#129514;'],
                    ['label' => 'Critical / Low Stock Groups', 'val' => $critical_cnt . ' Groups', 'color' => '#dc2626', 'icon' => '&#9888;'],
                    ['label' => 'Critical Threshold Applied', 'val' => '< ' . $threshold . ' Units', 'color' => '#d97706', 'icon' => '&#128202;']
                ];
            } else {
                $e = oci_error($stmt);
                $error_message = $e['message'] ?? 'Unable to execute report procedure.';
            }
            break;

        case '3': // Donor Eligibility Dossier
            $donor_id = (!empty($param_val) && is_numeric($param_val)) ? intval($param_val) : 1;
            $donor_curs = oci_new_cursor($conn);
            $hist_curs = oci_new_cursor($conn);
            
            $stmt = oci_parse($conn, "BEGIN rpt_donor_eligibility_summary(:p_did, :p_dcurs, :p_hcurs); END;");
            oci_bind_by_name($stmt, ":p_did", $donor_id);
            oci_bind_by_name($stmt, ":p_dcurs", $donor_curs, -1, OCI_B_CURSOR);
            oci_bind_by_name($stmt, ":p_hcurs", $hist_curs, -1, OCI_B_CURSOR);
            
            if (@oci_execute($stmt) && @oci_execute($donor_curs) && @oci_execute($hist_curs)) {
                $donor_header = oci_fetch_assoc($donor_curs);
                while ($r = oci_fetch_assoc($hist_curs)) {
                    $rows[] = $r;
                }
                if ($donor_header) {
                    $kpis = [
                        ['label' => 'Lifetime Total Donations', 'val' => ($donor_header['TOTAL_DONATIONS'] ?? 0) . ' Times (' . ($donor_header['TOTAL_UNITS_DONATED'] ?? 0) . ' Units)', 'color' => '#1e293b', 'icon' => '&#127942;'],
                        ['label' => 'Current Eligibility', 'val' => $donor_header['ELIGIBILITY_STATUS'] ?? 'Unknown', 'color' => ($donor_header['ELIGIBILITY_BADGE'] === 'success' ? '#16a34a' : '#d97706'), 'icon' => '&#9989;'],
                        ['label' => 'Next Eligible Date', 'val' => $donor_header['NEXT_ELIGIBLE_DATE'] ?? 'N/A', 'color' => '#0284c7', 'icon' => '&#128197;']
                    ];
                }
            } else {
                $e = oci_error($stmt);
                $error_message = $e['message'] ?? 'Unable to execute report procedure.';
            }
            break;

        case '4': // Hospital Requisitions
            $filter = !empty($param_val) ? $param_val : null;
            $curs = oci_new_cursor($conn);
            $stmt = oci_parse($conn, "BEGIN rpt_hospital_request_analysis(:p_stat, :p_curs); END;");
            oci_bind_by_name($stmt, ":p_stat", $filter);
            oci_bind_by_name($stmt, ":p_curs", $curs, -1, OCI_B_CURSOR);
            
            if (@oci_execute($stmt) && @oci_execute($curs)) {
                while ($r = oci_fetch_assoc($curs)) {
                    $rows[] = $r;
                }
                $tot_req = count($rows);
                $app_cnt = 0;
                $tot_units = 0;
                foreach ($rows as $row) {
                    if (strtoupper($row['STATUS'] ?? '') === 'APPROVED') {
                        $app_cnt++;
                        $tot_units += intval($row['UNITS_NEEDED'] ?? 0);
                    }
                }
                $rate = $tot_req > 0 ? round(($app_cnt / $tot_req) * 100, 1) : 0;
                $kpis = [
                    ['label' => 'Total Requisitions Processed', 'val' => $tot_req . ' Requests', 'color' => '#1e293b', 'icon' => '&#128221;'],
                    ['label' => 'Approved / Dispatched Units', 'val' => $app_cnt . ' (' . $tot_units . ' Units)', 'color' => '#16a34a', 'icon' => '&#128652;'],
                    ['label' => 'Fulfillment Success Rate', 'val' => $rate . '%', 'color' => '#0284c7', 'icon' => '&#128200;']
                ];
            } else {
                $e = oci_error($stmt);
                $error_message = $e['message'] ?? 'Unable to execute report procedure.';
            }
            break;

        case '5': // Staff Camp Allocation
            $staff_id_param = (!empty($param_val) && is_numeric($param_val)) ? intval($param_val) : null;
            $curs = oci_new_cursor($conn);
            $stmt = oci_parse($conn, "BEGIN rpt_staff_camp_performance(:p_sid, :p_curs); END;");
            oci_bind_by_name($stmt, ":p_sid", $staff_id_param);
            oci_bind_by_name($stmt, ":p_curs", $curs, -1, OCI_B_CURSOR);
            
            if (@oci_execute($stmt) && @oci_execute($curs)) {
                while ($r = oci_fetch_assoc($curs)) {
                    $rows[] = $r;
                }
                $staff_set = [];
                foreach ($rows as $row) {
                    $staff_set[$row['STAFF_ID']] = true;
                }
                $kpis = [
                    ['label' => 'Total Staff Deployments', 'val' => count($rows) . ' Assignments', 'color' => '#1e293b', 'icon' => '&#128188;'],
                    ['label' => 'Active Staff Members', 'val' => count($staff_set) . ' Members', 'color' => '#16a34a', 'icon' => '&#128101;'],
                    ['label' => 'Operations Status', 'val' => 'Active & Assigned', 'color' => '#0284c7', 'icon' => '&#127973;']
                ];
            } else {
                $e = oci_error($stmt);
                $error_message = $e['message'] ?? 'Unable to execute report procedure.';
            }
            break;
    }
} catch (Exception $ex) {
    $error_message = $ex->getMessage();
}

// Fetch Dropdown Helpers
$camps = [];
$c_stmt = oci_parse($conn, "SELECT camp_id, camp_name FROM camps ORDER BY camp_id ASC");
@oci_execute($c_stmt);
while ($r = oci_fetch_assoc($c_stmt)) $camps[] = $r;

$donors = [];
$d_stmt = oci_parse($conn, "SELECT donor_id, full_name, blood_group FROM donors ORDER BY donor_id ASC");
@oci_execute($d_stmt);
while ($r = oci_fetch_assoc($d_stmt)) $donors[] = $r;

$staff_list = [];
$st_stmt = oci_parse($conn, "SELECT user_id, email, role FROM users WHERE role IN ('Staff', 'Admin') ORDER BY user_id ASC");
@oci_execute($st_stmt);
while ($r = oci_fetch_assoc($st_stmt)) $staff_list[] = $r;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin PL/SQL Reports - LifeLineConnect</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .report-grid {
            display: grid;
            grid-template-columns: 310px 1fr;
            gap: 25px;
            margin-top: 20px;
        }
        .report-menu-card {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
            height: fit-content;
        }
        .report-btn {
            display: block;
            width: 100%;
            text-align: left;
            padding: 12px 15px;
            margin-bottom: 10px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            color: #1e293b;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
        }
        .report-btn:hover, .report-btn.active {
            background: #1e293b;
            color: #fff;
            border-color: #1e293b;
            transform: translateX(4px);
        }
        .report-btn.active small {
            color: #94a3b8;
        }
        .kpi-cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .kpi-box {
            background: #fff;
            padding: 16px 20px;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .kpi-box .icon {
            font-size: 28px;
            background: #f1f5f9;
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
        }
        .kpi-box h3 {
            font-size: 20px;
            font-weight: 700;
            margin: 2px 0 0 0;
        }
        .kpi-box p {
            font-size: 12px;
            color: #64748b;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .report-content-card {
            background: #fff;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            padding: 25px;
        }
        .report-controls-bar {
            background: #f8fafc;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border: 1px solid #e2e8f0;
            flex-wrap: wrap;
            gap: 12px;
        }
        .report-filter-form {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .badge-tag {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .badge-tag.success { background: #dcfce7; color: #15803d; }
        .badge-tag.warning { background: #fef3c7; color: #b45309; }
        .badge-tag.danger  { background: #fee2e2; color: #b91c1c; }
        .badge-blood {
            background: #fee2e2;
            color: #991b1b;
            padding: 3px 8px;
            border-radius: 6px;
            font-weight: 700;
            font-size: 12px;
            border: 1px solid #fecaca;
        }
        .donor-dossier-header {
            background: linear-gradient(135deg, #f8fafc 0%, #edf2f7 100%);
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        .dossier-item span {
            display: block;
            font-size: 12px;
            color: #64748b;
            text-transform: uppercase;
        }
        .dossier-item strong {
            font-size: 15px;
            color: #1e293b;
        }
        .table-responsive {
            overflow-x: auto;
        }
        .styled-report-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13.5px;
        }
        .styled-report-table th {
            background: #f1f5f9;
            color: #334155;
            text-align: left;
            padding: 12px 15px;
            font-weight: 700;
            border-bottom: 2px solid #e2e8f0;
        }
        .styled-report-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #f1f5f9;
            color: #1e293b;
        }
        .styled-report-table tr:hover {
            background: #f8fafc;
        }
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #94a3b8;
        }
        .empty-state i {
            font-size: 40px;
            display: block;
            margin-bottom: 10px;
        }
        .alert-error {
            background: #fee2e2;
            color: #b91c1c;
            padding: 15px 20px;
            border-radius: 8px;
            border: 1px solid #fca5a5;
            margin-bottom: 20px;
        }
        @media print {
            .sidebar, .report-menu-card, .report-controls-bar, .header {
                display: none !important;
            }
            .main-content, .report-grid {
                display: block !important;
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
            }
        }
    </style>
</head>
<body>

    <!-- Sidebar Navigation -->
    <div class="sidebar">
        <div class="sidebar-brand">
            <span class="brand-icon">&#9881;</span>
            <div>
                <h2>LifeLineConnect</h2>
                <small class="badge-role">System Admin Panel</small>
            </div>
        </div>

        <ul class="nav-menu">
            <li><a href="admin_dashboard.php" class="nav-link"><span class="nav-icon">&#128202;</span> Admin Overview</a></li>
            <li><a href="admin_reports.php" class="nav-link active"><span class="nav-icon">&#128196;</span> PL/SQL Business Reports</a></li>
            <li><a href="admin_dashboard.php#staff-management" class="nav-link"><span class="nav-icon">&#128101;</span> Staff Management</a></li>
            <li><a href="admin_dashboard.php#districts-management" class="nav-link"><span class="nav-icon">&#127757;</span> Districts & Settings</a></li>
            <li class="nav-divider"></li>
            <li><a href="../../donor/donor_interface/login.php" class="nav-link"><span class="nav-icon">&#128281;</span> Main Portal</a></li>
        </ul>
    </div>

    <!-- Main Content Area -->
    <div class="main-content">
        <div class="header">
            <div>
                <h1>System Administrator Reports</h1>
                <p class="header-sub">Returned via Oracle SYS_REFCURSOR Stored Procedures & Business Logic</p>
            </div>
            <span class="badge-tag success" style="font-size: 12px; padding: 6px 14px;">SYS_REFCURSOR Active</span>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="alert-error">
                <strong>Oracle Execution Notice:</strong> <?php echo htmlspecialchars($error_message); ?>
                <div style="font-size: 12px; margin-top: 6px;">
                    Make sure to execute <code>reports/business_reports.sql</code> in Oracle SQL Developer (F5) so the procedures are created.
                </div>
            </div>
        <?php endif; ?>

        <div class="report-grid">
            <!-- Left Side: Report Selector Menu -->
            <div class="report-menu-card">
                <h3 style="margin-bottom: 15px; color: #1e293b;">&#128220; 5 Standard Reports</h3>
                
                <a href="admin_reports.php?report_type=1" class="report-btn <?php echo $report_type == '1' ? 'active' : ''; ?>">
                    1. Camp Blood Collection
                    <small style="display:block; font-size:11px; opacity:0.8;">Blood group totals by camp</small>
                </a>

                <a href="admin_reports.php?report_type=2" class="report-btn <?php echo $report_type == '2' ? 'active' : ''; ?>">
                    2. Inventory & Stock Alert
                    <small style="display:block; font-size:11px; opacity:0.8;">Critical stock warnings</small>
                </a>

                <a href="admin_reports.php?report_type=3" class="report-btn <?php echo $report_type == '3' ? 'active' : ''; ?>">
                    3. Donor Eligibility Dossier
                    <small style="display:block; font-size:11px; opacity:0.8;">Lifetime donor history & status</small>
                </a>

                <a href="admin_reports.php?report_type=4" class="report-btn <?php echo $report_type == '4' ? 'active' : ''; ?>">
                    4. Hospital Requisition Analysis
                    <small style="display:block; font-size:11px; opacity:0.8;">Fulfillment rate & distribution</small>
                </a>

                <a href="admin_reports.php?report_type=5" class="report-btn <?php echo $report_type == '5' ? 'active' : ''; ?>">
                    5. Staff Camp Allocation
                    <small style="display:block; font-size:11px; opacity:0.8;">Workload & duty distribution</small>
                </a>

                <div style="margin-top: 25px; padding: 12px; background: #f1f5f9; border-radius: 8px; font-size: 12px; color: #334155;">
                    <strong>&#127891; Coursework Outcome:</strong>
                    <p style="margin-top: 5px;">Demonstrates PL/SQL Stored Procedures returning <code>SYS_REFCURSOR</code> objects seamlessly mapped to HTML5 UI components.</p>
                </div>
            </div>

            <!-- Right Side: Report UI View -->
            <div>
                <!-- Controls Filter Bar -->
                <div class="report-controls-bar">
                    <form method="GET" action="admin_reports.php" class="report-filter-form">
                        <input type="hidden" name="report_type" value="<?php echo htmlspecialchars($report_type); ?>">
                        
                        <?php if ($report_type == '1'): ?>
                            <label><strong>Filter Camp:</strong></label>
                            <select name="param_val" class="form-control" style="width: 220px; padding: 7px 10px;">
                                <option value="">-- All Camps --</option>
                                <?php foreach($camps as $c): ?>
                                    <option value="<?php echo $c['CAMP_ID']; ?>" <?php echo $param_val == $c['CAMP_ID'] ? 'selected' : ''; ?>>
                                        #<?php echo $c['CAMP_ID']; ?> - <?php echo htmlspecialchars($c['CAMP_NAME']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                        <?php elseif ($report_type == '2'): ?>
                            <label><strong>Critical Stock Threshold:</strong></label>
                            <input type="number" name="param_val" value="<?php echo htmlspecialchars($param_val ?: '10'); ?>" class="form-control" style="width: 100px; padding: 7px 10px;" min="1" max="100">

                        <?php elseif ($report_type == '3'): ?>
                            <label><strong>Select Donor:</strong></label>
                            <select name="param_val" class="form-control" style="width: 240px; padding: 7px 10px;">
                                <?php foreach($donors as $d): ?>
                                    <option value="<?php echo $d['DONOR_ID']; ?>" <?php echo $param_val == $d['DONOR_ID'] ? 'selected' : ''; ?>>
                                        #<?php echo $d['DONOR_ID']; ?> - <?php echo htmlspecialchars($d['FULL_NAME']); ?> (<?php echo $d['BLOOD_GROUP']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>

                        <?php elseif ($report_type == '4'): ?>
                            <label><strong>Filter Status:</strong></label>
                            <select name="param_val" class="form-control" style="width: 160px; padding: 7px 10px;">
                                <option value="">-- All Statuses --</option>
                                <option value="Approved" <?php echo $param_val == 'Approved' ? 'selected' : ''; ?>>Approved</option>
                                <option value="Pending" <?php echo $param_val == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="Rejected" <?php echo $param_val == 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                            </select>

                        <?php elseif ($report_type == '5'): ?>
                            <label><strong>Select Staff Member:</strong></label>
                            <select name="param_val" class="form-control" style="width: 260px; padding: 7px 10px;">
                                <option value="">-- All Staff & Volunteers --</option>
                                <?php foreach($staff_list as $st): ?>
                                    <option value="<?php echo $st['USER_ID']; ?>" <?php echo $param_val == $st['USER_ID'] ? 'selected' : ''; ?>>
                                        #<?php echo $st['USER_ID']; ?> - <?php echo htmlspecialchars($st['EMAIL']); ?> (<?php echo $st['ROLE']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>

                        <button type="submit" class="btn btn-primary" style="padding: 7px 16px; background:#1e293b; color:#fff; border:none; border-radius:6px; cursor:pointer;">Generate Report</button>
                    </form>

                    <button onclick="window.print()" class="btn btn-secondary" style="padding: 7px 14px; cursor:pointer;">&#128424; Print Report</button>
                </div>

                <!-- KPI Cards -->
                <?php if (!empty($kpis)): ?>
                    <div class="kpi-cards-grid">
                        <?php foreach ($kpis as $k): ?>
                            <div class="kpi-box">
                                <div class="icon"><?php echo $k['icon']; ?></div>
                                <div>
                                    <p><?php echo htmlspecialchars($k['label']); ?></p>
                                    <h3 style="color: <?php echo $k['color']; ?>;"><?php echo htmlspecialchars($k['val']); ?></h3>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Main Report Data Card -->
                <div class="report-content-card">

                    <?php if ($report_type == '1'): ?>
                        <!-- REPORT 1: CAMP BLOOD COLLECTION -->
                        <h3 style="margin-bottom: 15px; color: #1e293b;">&#129514; Camp Blood Collection Breakdown</h3>
                        <?php if (empty($rows)): ?>
                            <div class="empty-state"><i>&#128220;</i>No donation collection records found.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="styled-report-table">
                                    <thead>
                                        <tr>
                                            <th>Camp ID</th>
                                            <th>Camp Name</th>
                                            <th>Venue</th>
                                            <th>Camp Date</th>
                                            <th>Blood Group</th>
                                            <th>Total Donors</th>
                                            <th>Units Collected</th>
                                            <th>Yield Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($rows as $r): ?>
                                            <tr>
                                                <td>#<?php echo $r['CAMP_ID']; ?></td>
                                                <td><strong><?php echo htmlspecialchars($r['CAMP_NAME']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($r['VENUE']); ?></td>
                                                <td><?php echo $r['C_DATE']; ?></td>
                                                <td><span class="badge-blood"><?php echo $r['BLOOD_GROUP']; ?></span></td>
                                                <td><strong><?php echo $r['DONOR_COUNT']; ?> Donors</strong></td>
                                                <td style="color: #b71c1c; font-weight: 700;"><?php echo $r['UNITS_COLLECTED']; ?> Units</td>
                                                <td>
                                                    <span class="badge-tag <?php echo $r['YIELD_STATUS'] == 'High Yield' ? 'success' : 'warning'; ?>">
                                                        <?php echo $r['YIELD_STATUS']; ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>

                    <?php elseif ($report_type == '2'): ?>
                        <!-- REPORT 2: INVENTORY & STOCK ALERT -->
                        <h3 style="margin-bottom: 15px; color: #1e293b;">&#9888; Blood Stock Levels & Shortage Risk Analysis</h3>
                        <?php if (empty($rows)): ?>
                            <div class="empty-state"><i>&#128220;</i>No inventory records found.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="styled-report-table">
                                    <thead>
                                        <tr>
                                            <th>Blood Group</th>
                                            <th>Units In Stock</th>
                                            <th>Last Restocked</th>
                                            <th>Days In Storage</th>
                                            <th>Stock Status</th>
                                            <th>Recommended Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($rows as $r): ?>
                                            <tr>
                                                <td><span class="badge-blood" style="font-size: 14px;"><?php echo $r['BLOOD_GROUP']; ?></span></td>
                                                <td><strong style="font-size: 15px;"><?php echo $r['TOTAL_UNITS']; ?> Units</strong></td>
                                                <td><?php echo $r['LAST_UPDATED_DATE'] ?: 'No activity'; ?></td>
                                                <td><?php echo $r['DAYS_SINCE_UPDATE']; ?> Days</td>
                                                <td>
                                                    <span class="badge-tag <?php echo $r['ALERT_LEVEL']; ?>">
                                                        <?php echo $r['STOCK_STATUS']; ?>
                                                    </span>
                                                </td>
                                                <td><em><?php echo htmlspecialchars($r['RECOMMENDED_ACTION']); ?></em></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>

                    <?php elseif ($report_type == '3'): ?>
                        <!-- REPORT 3: DONOR ELIGIBILITY DOSSIER -->
                        <?php if ($donor_header): ?>
                            <div class="donor-dossier-header">
                                <div class="dossier-item">
                                    <span>Donor Name</span>
                                    <strong><?php echo htmlspecialchars($donor_header['FULL_NAME']); ?></strong>
                                </div>
                                <div class="dossier-item">
                                    <span>Blood Group</span>
                                    <span class="badge-blood"><?php echo $donor_header['BLOOD_GROUP']; ?></span>
                                </div>
                                <div class="dossier-item">
                                    <span>Age & Gender</span>
                                    <strong><?php echo $donor_header['AGE']; ?> Years (<?php echo $donor_header['GENDER']; ?>)</strong>
                                </div>
                                <div class="dossier-item">
                                    <span>District & Contact</span>
                                    <strong><?php echo htmlspecialchars($donor_header['DISTRICT_NAME'] ?: 'N/A'); ?> | <?php echo $donor_header['CONTACT_NO']; ?></strong>
                                </div>
                            </div>
                        <?php endif; ?>

                        <h3 style="margin-bottom: 15px; color: #1e293b;">&#128392; Lifetime Donation History Timeline</h3>
                        <?php if (empty($rows)): ?>
                            <div class="empty-state"><i>&#127873;</i>No donation history found. Registered as a first-time voluntary donor!</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="styled-report-table">
                                    <thead>
                                        <tr>
                                            <th>History ID</th>
                                            <th>Donation Date</th>
                                            <th>Camp Name</th>
                                            <th>Venue</th>
                                            <th>Units Donated</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($rows as $r): ?>
                                            <tr>
                                                <td>#<?php echo $r['HISTORY_ID']; ?></td>
                                                <td><strong><?php echo $r['DONATION_DATE']; ?></strong></td>
                                                <td><?php echo htmlspecialchars($r['CAMP_NAME']); ?></td>
                                                <td><?php echo htmlspecialchars($r['VENUE']); ?></td>
                                                <td style="color: #b71c1c; font-weight: 700;"><?php echo $r['BLOOD_UNITS']; ?> Unit(s)</td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>

                    <?php elseif ($report_type == '4'): ?>
                        <!-- REPORT 4: HOSPITAL REQUISITIONS -->
                        <h3 style="margin-bottom: 15px; color: #1e293b;">&#127973; Hospital Blood Requisitions & Distribution Efficiency</h3>
                        <?php if (empty($rows)): ?>
                            <div class="empty-state"><i>&#128220;</i>No hospital requests match criteria.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="styled-report-table">
                                    <thead>
                                        <tr>
                                            <th>Req ID</th>
                                            <th>Hospital Name</th>
                                            <th>Blood Group</th>
                                            <th>Units Needed</th>
                                            <th>Request Date</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($rows as $r): ?>
                                            <tr>
                                                <td>#<?php echo $r['REQUEST_ID']; ?></td>
                                                <td><strong><?php echo htmlspecialchars($r['HOSPITAL_NAME']); ?></strong></td>
                                                <td><span class="badge-blood"><?php echo $r['BLOOD_GROUP']; ?></span></td>
                                                <td><strong><?php echo $r['UNITS_NEEDED']; ?> Units</strong></td>
                                                <td><?php echo $r['REQ_DATE']; ?></td>
                                                <td>
                                                    <span class="badge-tag <?php echo $r['STATUS_BADGE']; ?>">
                                                        <?php echo $r['STATUS']; ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>

                    <?php elseif ($report_type == '5'): ?>
                        <!-- REPORT 5: STAFF ALLOCATION -->
                        <h3 style="margin-bottom: 15px; color: #1e293b;">&#128101; Staff & Volunteer Camp Duty Deployments</h3>
                        <?php if (empty($rows)): ?>
                            <div class="empty-state"><i>&#128220;</i>No staff assignments found.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="styled-report-table">
                                    <thead>
                                        <tr>
                                            <th>Assignment ID</th>
                                            <th>Staff Email</th>
                                            <th>Camp Name</th>
                                            <th>Venue</th>
                                            <th>Camp Date</th>
                                            <th>District</th>
                                            <th>Assigned Task</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($rows as $r): ?>
                                            <tr>
                                                <td>#<?php echo $r['ASSIGNMENT_ID']; ?></td>
                                                <td><strong><?php echo htmlspecialchars($r['STAFF_EMAIL']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($r['CAMP_NAME']); ?></td>
                                                <td><?php echo htmlspecialchars($r['VENUE']); ?></td>
                                                <td><?php echo $r['CAMP_DATE']; ?></td>
                                                <td><?php echo htmlspecialchars($r['DISTRICT_NAME']); ?></td>
                                                <td><span class="badge-tag success"><?php echo htmlspecialchars($r['TASK']); ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>

                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>

</body>
</html>
