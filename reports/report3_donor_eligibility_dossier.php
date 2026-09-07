<?php
// ==============================================================================
// 👤 BUSINESS REPORT 3: INDIVIDUAL DONOR ELIGIBILITY & HISTORY DOSSIER
// Invokes PL/SQL Stored Procedure: rpt_donor_eligibility_summary(:p_did, :p_dcurs, :p_hcurs)
// ==============================================================================
require_once __DIR__ . '/db_connect.php';

$donor_id_filter = isset($_GET['donor_id']) && is_numeric($_GET['donor_id']) ? intval($_GET['donor_id']) : 1;
$error_message = "";
$donor_header = null;
$history_rows = [];

try {
    $dcurs = oci_new_cursor($conn);
    $hcurs = oci_new_cursor($conn);
    $stmt = oci_parse($conn, "BEGIN rpt_donor_eligibility_summary(:p_did, :p_dcurs, :p_hcurs); END;");
    oci_bind_by_name($stmt, ":p_did", $donor_id_filter);
    oci_bind_by_name($stmt, ":p_dcurs", $dcurs, -1, OCI_B_CURSOR);
    oci_bind_by_name($stmt, ":p_hcurs", $hcurs, -1, OCI_B_CURSOR);

    if (@oci_execute($stmt) && @oci_execute($dcurs) && @oci_execute($hcurs)) {
        if ($drow = oci_fetch_assoc($dcurs)) {
            $donor_header = $drow;
        }
        while ($hr = oci_fetch_assoc($hcurs)) {
            $history_rows[] = $hr;
        }
    } else {
        $e = oci_error($stmt);
        $error_message = $e['message'] ?? 'Error executing report procedure.';
    }
} catch (Exception $ex) {
    $error_message = $ex->getMessage();
}

// Fetch all Donors for dropdown selector
$donors = [];
$d_stmt = oci_parse($conn, "SELECT donor_id, full_name, blood_group FROM donors ORDER BY donor_id ASC");
@oci_execute($d_stmt);
while ($dr = oci_fetch_assoc($d_stmt)) {
    $donors[] = $dr;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report 3: Individual Donor Eligibility & Dossier</title>
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
        .title-badge { display: inline-block; background: #e0e7ff; color: #3730a3; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; margin-bottom: 8px; }
        h1 { font-size: 24px; font-weight: 800; color: #1e293b; }
        .subtitle { color: var(--text-muted); font-size: 14px; margin-top: 4px; }
        
        .nav-btns { display: flex; gap: 10px; }
        .btn { padding: 10px 18px; border-radius: 8px; font-weight: 600; font-size: 13.5px; text-decoration: none; cursor: pointer; border: 1px solid transparent; transition: all 0.2s; display: inline-flex; align-items: center; gap: 6px; }
        .btn-primary { background: var(--primary); color: #fff; }
        .btn-primary:hover { background: var(--primary-hover); }
        .btn-outline { background: #fff; color: #334155; border-color: #cbd5e1; }
        .btn-outline:hover { background: #f1f5f9; }

        .filter-bar { background: var(--card); border: 1px solid var(--border); padding: 16px 20px; border-radius: 12px; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
        .filter-form { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
        select { padding: 9px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13.5px; outline: none; background: #fff; }

        .dossier-card { background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%); border: 1px solid var(--border); border-radius: 14px; padding: 25px; margin-bottom: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); }
        .dossier-header-title { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); padding-bottom: 15px; margin-bottom: 20px; }
        .dossier-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 18px; }
        .dossier-item span { display: block; font-size: 11.5px; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 4px; }
        .dossier-item strong { font-size: 15px; color: #0f172a; }

        .table-card { background: var(--card); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.03); }
        table { width: 100%; border-collapse: collapse; text-align: left; font-size: 13.5px; }
        th { background: #f1f5f9; color: #475569; font-weight: 700; padding: 14px 18px; border-bottom: 1px solid var(--border); text-transform: uppercase; font-size: 11.5px; }
        td { padding: 14px 18px; border-bottom: 1px solid #f1f5f9; color: #334155; }
        tr:hover td { background: #f8fafc; }

        .badge-blood { background: #ffebee; color: #b71c1c; padding: 4px 10px; border-radius: 6px; font-weight: 700; border: 1px solid #ffcdd2; font-size: 13px; }
        .badge-status { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 11.5px; font-weight: 700; text-transform: uppercase; }
        .status-success { background: #dcfce7; color: #15803d; }
        .status-warning { background: #fef3c7; color: #b45309; }

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
            <span class="title-badge">PL/SQL BUSINESS REPORT #3</span>
            <h1>Individual Donor Eligibility & Lifetime Dossier Report</h1>
            <p class="subtitle">Complete medical dossier, eligibility status, next eligible donation date, and history timeline.</p>
        </div>
        <div class="nav-btns">
            <span class="plsql-badge">PROCEDURE: rpt_donor_eligibility_summary (DUAL CURSORS)</span>
            <button onclick="window.print()" class="btn btn-outline">&#128438; Print / PDF</button>
            <a href="../manager/manager_interface/manager_dashboard.php" class="btn btn-outline">&#8592; Back to Manager</a>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="filter-bar">
        <form method="GET" class="filter-form">
            <label for="donor_id" style="font-size:13px; font-weight:600; color:#475569;">Select Donor Profile:</label>
            <select name="donor_id" id="donor_id">
                <?php foreach($donors as $d): ?>
                    <option value="<?php echo $d['DONOR_ID']; ?>" <?php echo ($donor_id_filter == $d['DONOR_ID']) ? 'selected' : ''; ?>>
                        #<?php echo $d['DONOR_ID']; ?> - <?php echo htmlspecialchars($d['FULL_NAME']); ?> (Blood Group: <?php echo htmlspecialchars($d['BLOOD_GROUP'] ?? 'Not Set'); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary">&#128100; View Donor Dossier</button>
        </form>
    </div>

    <?php if($donor_header): ?>
        <!-- Dossier Header Card -->
        <div class="dossier-card">
            <div class="dossier-header-title">
                <div>
                    <h2 style="font-size:20px; color:#1e293b;"><?php echo htmlspecialchars($donor_header['FULL_NAME']); ?></h2>
                    <small style="color:#64748b;">Donor ID: #<?php echo $donor_header['DONOR_ID']; ?> | Registered Email: <?php echo htmlspecialchars($donor_header['EMAIL']); ?></small>
                </div>
                <div>
                    <span class="badge-status <?php echo ($donor_header['ELIGIBILITY_BADGE'] === 'success') ? 'status-success' : 'status-warning'; ?>">
                        <?php echo htmlspecialchars($donor_header['ELIGIBILITY_STATUS']); ?>
                    </span>
                </div>
            </div>

            <div class="dossier-grid">
                <div class="dossier-item">
                    <span>Blood Group</span>
                    <strong><span class="badge-blood"><?php echo htmlspecialchars($donor_header['BLOOD_GROUP'] ?? 'Unassigned'); ?></span></strong>
                </div>
                <div class="dossier-item">
                    <span>Age / Gender</span>
                    <strong><?php echo $donor_header['AGE'] ?? 'N/A'; ?> Years (<?php echo htmlspecialchars($donor_header['GENDER'] ?? 'N/A'); ?>)</strong>
                </div>
                <div class="dossier-item">
                    <span>Contact Number</span>
                    <strong><?php echo htmlspecialchars($donor_header['CONTACT_NO'] ?? 'N/A'); ?></strong>
                </div>
                <div class="dossier-item">
                    <span>District & Address</span>
                    <strong><?php echo htmlspecialchars($donor_header['DISTRICT_NAME'] ?? 'N/A'); ?></strong>
                </div>
                <div class="dossier-item">
                    <span>Lifetime Donations</span>
                    <strong style="color:#0284c7;"><?php echo $donor_header['TOTAL_DONATIONS']; ?> Times (<?php echo $donor_header['TOTAL_UNITS_DONATED']; ?> Units)</strong>
                </div>
                <div class="dossier-item">
                    <span>Last Donation Date</span>
                    <strong><?php echo htmlspecialchars($donor_header['LAST_DONATION_DATE'] ?? 'Never Donated'); ?></strong>
                </div>
                <div class="dossier-item">
                    <span>Next Eligible Date</span>
                    <strong style="color:#16a34a;"><?php echo htmlspecialchars($donor_header['NEXT_ELIGIBLE_DATE']); ?></strong>
                </div>
            </div>
        </div>

        <!-- History Timeline Table -->
        <h3 style="font-size:16px; font-weight:700; color:#1e293b; margin-bottom:12px;">Donation History Timeline</h3>
        <div class="table-card">
            <table>
                <thead>
                    <tr>
                        <th>History ID</th>
                        <th>Donation Date</th>
                        <th>Camp Name</th>
                        <th>Venue Location</th>
                        <th>Blood Units Donated</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($history_rows)): ?>
                        <tr>
                            <td colspan="5" style="text-align:center; padding:30px; color:#94a3b8;">
                                No prior donation history recorded for this donor.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($history_rows as $hr): ?>
                            <tr>
                                <td><strong>#<?php echo $hr['HISTORY_ID']; ?></strong></td>
                                <td><?php echo htmlspecialchars($hr['DONATION_DATE']); ?></td>
                                <td><strong><?php echo htmlspecialchars($hr['CAMP_NAME']); ?></strong></td>
                                <td><?php echo htmlspecialchars($hr['VENUE']); ?></td>
                                <td><strong style="color:#b71c1c;"><?php echo $hr['BLOOD_UNITS']; ?> Unit(s)</strong></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

</div>

</body>
</html>
