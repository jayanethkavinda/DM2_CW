<?php
// ==============================================================================
// 👥 BUSINESS REPORT 5: CAMP STAFF & VOLUNTEER WORKLOAD ALLOCATION
// Invokes PL/SQL Stored Procedure: rpt_staff_camp_performance(:p_sid, :p_curs)
// ==============================================================================
require_once __DIR__ . '/db_connect.php';

$staff_id_filter = isset($_GET['staff_id']) && is_numeric($_GET['staff_id']) ? intval($_GET['staff_id']) : null;
$error_message = "";
$rows = [];
$unique_staff = [];
$unique_camps = [];

try {
    $curs = oci_new_cursor($conn);
    $stmt = oci_parse($conn, "BEGIN rpt_staff_camp_performance(:p_sid, :p_curs); END;");
    oci_bind_by_name($stmt, ":p_sid", $staff_id_filter);
    oci_bind_by_name($stmt, ":p_curs", $curs, -1, OCI_B_CURSOR);

    if (@oci_execute($stmt) && @oci_execute($curs)) {
        while ($r = oci_fetch_assoc($curs)) {
            $rows[] = $r;
            $unique_staff[$r['STAFF_ID']] = true;
            $unique_camps[$r['CAMP_ID']] = true;
        }
    } else {
        $e = oci_error($stmt);
        $error_message = $e['message'] ?? 'Error executing report procedure.';
    }
} catch (Exception $ex) {
    $error_message = $ex->getMessage();
}

// Fetch Staff members for dropdown
$staff_list = [];
$st_stmt = oci_parse($conn, "SELECT user_id, email, role FROM users WHERE role IN ('Staff', 'Admin') ORDER BY user_id ASC");
@oci_execute($st_stmt);
while ($sr = oci_fetch_assoc($st_stmt)) {
    $staff_list[] = $sr;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report 5: Camp Staff Workload Allocation</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #b71c1c;
            --primary-hover: #880e4f;
            --bg: #f8fafc;
            --card: #ffffff;
            --border: #e2e8f0;
            --text-dark: #0f172a;
            --text-muted: #64748b;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { background: var(--bg); color: var(--text-dark); padding: 30px; }
        .container { max-width: 1200px; margin: 0 auto; }
        
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px; }
        .title-badge { display: inline-block; background: #f3e8ff; color: #7e22ce; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; margin-bottom: 8px; }
        h1 { font-size: 24px; font-weight: 800; color: #1e293b; }
        .subtitle { color: var(--text-muted); font-size: 14px; margin-top: 4px; }
        
        .nav-btns { display: flex; gap: 10px; }
        .btn { padding: 10px 18px; border-radius: 8px; font-weight: 600; font-size: 13.5px; text-decoration: none; cursor: pointer; border: 1px solid transparent; transition: all 0.2s; display: inline-flex; align-items: center; gap: 6px; }
        .btn-primary { background: var(--primary); color: #fff; }
        .btn-primary:hover { background: var(--primary-hover); }
        .btn-outline { background: #fff; color: #334155; border-color: #cbd5e1; }
        .btn-outline:hover { background: #f1f5f9; }

        .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px; margin-bottom: 25px; }
        .kpi-card { background: var(--card); border: 1px solid var(--border); padding: 20px; border-radius: 12px; display: flex; align-items: center; gap: 15px; box-shadow: 0 2px 8px rgba(0,0,0,0.03); }
        .kpi-icon { width: 48px; height: 48px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 24px; }
        .kpi-value { font-size: 22px; font-weight: 800; color: #0f172a; }
        .kpi-label { font-size: 12px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; }

        .filter-bar { background: var(--card); border: 1px solid var(--border); padding: 16px 20px; border-radius: 12px; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
        .filter-form { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
        select { padding: 9px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13.5px; outline: none; background: #fff; }

        .table-card { background: var(--card); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.03); }
        table { width: 100%; border-collapse: collapse; text-align: left; font-size: 13.5px; }
        th { background: #f1f5f9; color: #475569; font-weight: 700; padding: 14px 18px; border-bottom: 1px solid var(--border); text-transform: uppercase; font-size: 11.5px; }
        td { padding: 14px 18px; border-bottom: 1px solid #f1f5f9; color: #334155; }
        tr:hover td { background: #f8fafc; }

        .badge-task { background: #f1f5f9; color: #334155; padding: 4px 10px; border-radius: 6px; font-weight: 600; border: 1px solid #e2e8f0; font-size: 12.5px; }

        .plsql-badge { background: #1e293b; color: #38bdf8; font-family: monospace; font-size: 11px; padding: 6px 12px; border-radius: 6px; }
        @media print {
            .nav-btns, .filter-bar { display: none !important; }
            body { padding: 0; background: #fff; }
        }
    </style>
</head>
<body>

<div class="container">
    
    <!-- Top Header -->
    <div class="header">
        <div>
            <span class="title-badge">PL/SQL BUSINESS REPORT #5</span>
            <h1>Camp Staff & Volunteer Workload Allocation Report</h1>
            <p class="subtitle">Tracks staff assignments, assigned duties, camp venues, and district distribution.</p>
        </div>
        <div class="nav-btns">
            <span class="plsql-badge">PROCEDURE: rpt_staff_camp_performance</span>
            <button onclick="window.print()" class="btn btn-outline">&#128438; Print / PDF</button>
            <a href="../manager/manager_interface/manager_dashboard.php" class="btn btn-outline">&#8592; Back to Manager</a>
        </div>
    </div>

    <!-- KPI Summary Row -->
    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-icon" style="background:#f3e8ff; color:#7e22ce;">&#128101;</div>
            <div>
                <div class="kpi-value"><?php echo count($rows); ?> Assignments</div>
                <div class="kpi-label">Total Task Deployments</div>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon" style="background:#e0f2fe; color:#0284c7;">&#128100;</div>
            <div>
                <div class="kpi-value"><?php echo count($unique_staff); ?> Staff Members</div>
                <div class="kpi-label">Staff Represented</div>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon" style="background:#dcfce7; color:#16a34a;">&#127973;</div>
            <div>
                <div class="kpi-value"><?php echo count($unique_camps); ?> Camps</div>
                <div class="kpi-label">Camps Covered</div>
            </div>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="filter-bar">
        <form method="GET" class="filter-form">
            <label for="staff_id" style="font-size:13px; font-weight:600; color:#475569;">Filter by Staff Member:</label>
            <select name="staff_id" id="staff_id">
                <option value="">-- All Staff & Coordinators --</option>
                <?php foreach($staff_list as $st): ?>
                    <option value="<?php echo $st['USER_ID']; ?>" <?php echo ($staff_id_filter == $st['USER_ID']) ? 'selected' : ''; ?>>
                        #<?php echo $st['USER_ID']; ?> - <?php echo htmlspecialchars($st['EMAIL']); ?> (<?php echo htmlspecialchars($st['ROLE']); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary">&#128269; Filter Workload</button>
            <?php if($staff_id_filter): ?>
                <a href="report5_staff_camp_performance.php" class="btn btn-outline">&#8635; Reset Filter</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Result Table -->
    <div class="table-card">
        <table>
            <thead>
                <tr>
                    <th>Assignment ID</th>
                    <th>Staff Member</th>
                    <th>Camp Name & Venue</th>
                    <th>Camp Date</th>
                    <th>District</th>
                    <th>Assigned Task</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($rows)): ?>
                    <tr>
                        <td colspan="6" style="text-align:center; padding:40px; color:#94a3b8;">
                            No staff assignment records match the selected filter.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach($rows as $r): ?>
                        <tr>
                            <td><strong>#<?php echo $r['ASSIGNMENT_ID']; ?></strong></td>
                            <td>
                                <strong><?php echo htmlspecialchars($r['STAFF_EMAIL']); ?></strong><br>
                                <small style="color:#64748b;">Staff ID: #<?php echo $r['STAFF_ID']; ?></small>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($r['CAMP_NAME']); ?></strong><br>
                                <small style="color:#64748b;"><?php echo htmlspecialchars($r['VENUE']); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($r['CAMP_DATE']); ?></td>
                            <td><?php echo htmlspecialchars($r['DISTRICT_NAME']); ?></td>
                            <td><span class="badge-task">&#128203; <?php echo htmlspecialchars($r['TASK']); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

</body>
</html>
