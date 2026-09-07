<?php
// ==============================================================================
// ⚠️ BUSINESS REPORT 2: BLOOD INVENTORY & CRITICAL STOCK ANALYSIS REPORT
// Invokes PL/SQL Stored Procedure: rpt_inventory_expiry_alert(:p_thresh, :p_curs)
// ==============================================================================
require_once __DIR__ . '/db_connect.php';

$threshold_param = isset($_GET['threshold']) && is_numeric($_GET['threshold']) ? intval($_GET['threshold']) : 10;
$error_message = "";
$rows = [];
$total_inventory = 0;
$critical_count = 0;

try {
    $curs = oci_new_cursor($conn);
    $stmt = oci_parse($conn, "BEGIN rpt_inventory_expiry_alert(:p_thresh, :p_curs); END;");
    oci_bind_by_name($stmt, ":p_thresh", $threshold_param);
    oci_bind_by_name($stmt, ":p_curs", $curs, -1, OCI_B_CURSOR);

    if (@oci_execute($stmt) && @oci_execute($curs)) {
        while ($r = oci_fetch_assoc($curs)) {
            $rows[] = $r;
            $total_inventory += intval($r['TOTAL_UNITS'] ?? 0);
            if ($r['ALERT_LEVEL'] === 'danger' || $r['ALERT_LEVEL'] === 'warning') {
                $critical_count++;
            }
        }
    } else {
        $e = oci_error($stmt);
        $error_message = $e['message'] ?? 'Error executing report procedure.';
    }
} catch (Exception $ex) {
    $error_message = $ex->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report 2: Blood Inventory & Expiry Risk Analysis</title>
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
        .title-badge { display: inline-block; background: #fef3c7; color: #b45309; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; margin-bottom: 8px; }
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
        select, input { padding: 9px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13.5px; outline: none; background: #fff; }

        .table-card { background: var(--card); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.03); }
        table { width: 100%; border-collapse: collapse; text-align: left; font-size: 13.5px; }
        th { background: #f1f5f9; color: #475569; font-weight: 700; padding: 14px 18px; border-bottom: 1px solid var(--border); text-transform: uppercase; font-size: 11.5px; }
        td { padding: 14px 18px; border-bottom: 1px solid #f1f5f9; color: #334155; }
        tr:hover td { background: #f8fafc; }

        .badge-blood { background: #ffebee; color: #b71c1c; padding: 4px 10px; border-radius: 6px; font-weight: 700; border: 1px solid #ffcdd2; font-size: 13px; }
        .badge-status { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .status-danger { background: #fee2e2; color: #b91c1c; }
        .status-warning { background: #fef3c7; color: #b45309; }
        .status-success { background: #dcfce7; color: #15803d; }

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
            <span class="title-badge">PL/SQL BUSINESS REPORT #2</span>
            <h1>Blood Inventory & Stock Expiry Analysis Report</h1>
            <p class="subtitle">Calculates blood stock levels, tracks restocking days, and highlights critical shortage groups.</p>
        </div>
        <div class="nav-btns">
            <span class="plsql-badge">PROCEDURE: rpt_inventory_expiry_alert</span>
            <button onclick="window.print()" class="btn btn-outline">&#128438; Print / PDF</button>
            <a href="../manager/manager_interface/manager_dashboard.php" class="btn btn-outline">&#8592; Back to Manager</a>
        </div>
    </div>

    <!-- KPI Summary Row -->
    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-icon" style="background:#e0f2fe; color:#0284c7;">&#129514;</div>
            <div>
                <div class="kpi-value"><?php echo $total_inventory; ?> Units</div>
                <div class="kpi-label">Total Central Blood Stock</div>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon" style="background:#fee2e2; color:#dc2626;">&#9888;</div>
            <div>
                <div class="kpi-value"><?php echo $critical_count; ?> Groups</div>
                <div class="kpi-label">Critical / Low Stock Alerts</div>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon" style="background:#fef3c7; color:#d97706;">&#128202;</div>
            <div>
                <div class="kpi-value">< <?php echo $threshold_param; ?> Units</div>
                <div class="kpi-label">Shortage Threshold Applied</div>
            </div>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="filter-bar">
        <form method="GET" class="filter-form">
            <label for="threshold" style="font-size:13px; font-weight:600; color:#475569;">Critical Shortage Threshold (Units):</label>
            <input type="number" name="threshold" id="threshold" value="<?php echo $threshold_param; ?>" min="1" max="100" style="width:100px;">
            <button type="submit" class="btn btn-primary">&#9881; Recalculate Risk</button>
            <?php if($threshold_param != 10): ?>
                <a href="report2_blood_inventory_expiry.php" class="btn btn-outline">&#8635; Reset (Default: 10)</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Result Table -->
    <div class="table-card">
        <table>
            <thead>
                <tr>
                    <th>Blood Group</th>
                    <th>Available Stock</th>
                    <th>Last Updated Date</th>
                    <th>Days Elapsed</th>
                    <th>Inventory Status</th>
                    <th>Automated Recommended Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($rows as $r): ?>
                    <tr>
                        <td><span class="badge-blood"><?php echo htmlspecialchars($r['BLOOD_GROUP']); ?></span></td>
                        <td><strong style="font-size:16px; color:#0f172a;"><?php echo $r['TOTAL_UNITS']; ?></strong> Units</td>
                        <td><?php echo htmlspecialchars($r['LAST_UPDATED_DATE'] ?? 'N/A'); ?></td>
                        <td><strong><?php echo $r['DAYS_SINCE_UPDATE']; ?></strong> Days ago</td>
                        <td>
                            <?php 
                                $lvl = $r['ALERT_LEVEL'];
                                $cls = ($lvl === 'danger') ? 'status-danger' : (($lvl === 'warning') ? 'status-warning' : 'status-success');
                            ?>
                            <span class="badge-status <?php echo $cls; ?>"><?php echo htmlspecialchars($r['STOCK_STATUS']); ?></span>
                        </td>
                        <td>
                            <strong style="color:<?php echo ($lvl === 'danger') ? '#b91c1c' : (($lvl === 'warning') ? '#b45309' : '#15803d'); ?>;">
                                <?php echo htmlspecialchars($r['RECOMMENDED_ACTION']); ?>
                            </strong>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</div>

</body>
</html>
