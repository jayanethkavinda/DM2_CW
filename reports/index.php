<?php
// ==============================================================================
// 📊 LIFELINECONNECT - 5 PL/SQL BUSINESS REPORTS MASTER HUB
// ==============================================================================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PL/SQL Business Reports Hub - LifeLineConnect</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #b71c1c;
            --bg: #f8fafc;
            --card: #ffffff;
            --border: #e2e8f0;
            --text-dark: #0f172a;
            --text-muted: #64748b;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { background: var(--bg); color: var(--text-dark); padding: 40px 20px; }
        .container { max-width: 1100px; margin: 0 auto; }
        
        .hero { text-align: center; margin-bottom: 40px; }
        .hero-badge { display: inline-block; background: #fee2e2; color: #b71c1c; padding: 6px 16px; border-radius: 20px; font-size: 13px; font-weight: 700; margin-bottom: 12px; }
        .hero h1 { font-size: 32px; font-weight: 800; color: #1e293b; margin-bottom: 10px; }
        .hero p { color: var(--text-muted); font-size: 15px; max-width: 700px; margin: 0 auto; line-height: 1.6; }

        .reports-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px; }
        .report-card { background: var(--card); border: 1px solid var(--border); border-radius: 14px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.04); transition: all 0.25s; display: flex; flex-direction: column; justify-content: space-between; }
        .report-card:hover { transform: translateY(-4px); box-shadow: 0 10px 25px rgba(0,0,0,0.08); border-color: #cbd5e1; }
        
        .card-top { display: flex; align-items: flex-start; gap: 15px; margin-bottom: 15px; }
        .card-icon { width: 52px; height: 52px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 26px; flex-shrink: 0; }
        .card-title { font-size: 17px; font-weight: 700; color: #1e293b; line-height: 1.3; }
        .card-desc { font-size: 13px; color: var(--text-muted); line-height: 1.5; margin-bottom: 15px; }
        
        .code-meta { background: #f1f5f9; border-radius: 8px; padding: 10px 14px; font-size: 12px; font-family: monospace; color: #334155; margin-bottom: 18px; border: 1px solid #e2e8f0; }
        .code-meta strong { color: #0284c7; }

        .btn-open { display: block; text-align: center; background: #1e293b; color: #fff; padding: 11px; border-radius: 8px; font-weight: 600; font-size: 13.5px; text-decoration: none; transition: background 0.2s; }
        .btn-open:hover { background: var(--primary); }

        .back-nav { text-align: center; margin-top: 40px; }
        .btn-back { display: inline-flex; align-items: center; gap: 8px; color: #64748b; text-decoration: none; font-weight: 600; font-size: 14px; padding: 10px 20px; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; }
        .btn-back:hover { color: #0f172a; background: #f1f5f9; }
    </style>
</head>
<body>

<div class="container">

    <div class="hero">
        <span class="hero-badge">&#128202;  </span>
        <h1>LifeLineConnect - 5 PL/SQL Business Reports</h1>
        <p>Complete Enterprise Reporting Architecture utilizing Oracle <strong>SYS_REFCURSOR</strong> Stored Procedures, dynamic filter parameters, and interactive datasets.</p>
    </div>

    <div class="reports-grid">

        <!-- Report 1 -->
        <div class="report-card">
            <div>
                <div class="card-top">
                    <div class="card-icon" style="background:#fee2e2; color:#b71c1c;">&#129514;</div>
                    <div>
                        <span style="font-size:11px; font-weight:700; color:#b71c1c; text-transform:uppercase;">Report #1</span>
                        <h3 class="card-title">Camp Blood Collection Breakdown</h3>
                    </div>
                </div>
                <p class="card-desc">Aggregates blood units and donor turnout by Blood Group across donation camps with yield classification.</p>
                <div class="code-meta">
                    <strong>PL/SQL:</strong> rpt_camp_blood_collection<br>
                    <strong>SQL File:</strong> report1_camp_blood_collection.sql
                </div>
            </div>
            <a href="report1_camp_blood_collection.php" class="btn-open">Open Report 1 &#8594;</a>
        </div>

        <!-- Report 2 -->
        <div class="report-card">
            <div>
                <div class="card-top">
                    <div class="card-icon" style="background:#fef3c7; color:#d97706;">&#9888;</div>
                    <div>
                        <span style="font-size:11px; font-weight:700; color:#d97706; text-transform:uppercase;">Report #2</span>
                        <h3 class="card-title">Blood Inventory & Stock Expiry</h3>
                    </div>
                </div>
                <p class="card-desc">Tracks stock levels, days elapsed since restocking, critical shortage alerts, and automated recommendations.</p>
                <div class="code-meta">
                    <strong>PL/SQL:</strong> rpt_inventory_expiry_alert<br>
                    <strong>SQL File:</strong> report2_blood_inventory_expiry.sql
                </div>
            </div>
            <a href="report2_blood_inventory_expiry.php" class="btn-open">Open Report 2 &#8594;</a>
        </div>

        <!-- Report 3 -->
        <div class="report-card">
            <div>
                <div class="card-top">
                    <div class="card-icon" style="background:#e0e7ff; color:#3730a3;">&#128100;</div>
                    <div>
                        <span style="font-size:11px; font-weight:700; color:#3730a3; text-transform:uppercase;">Report #3</span>
                        <h3 class="card-title">Donor Eligibility & Lifetime Dossier</h3>
                    </div>
                </div>
                <p class="card-desc">Returns complete donor medical metrics, cooldown status, next eligible date, and history timeline via dual cursors.</p>
                <div class="code-meta">
                    <strong>PL/SQL:</strong> rpt_donor_eligibility_summary<br>
                    <strong>SQL File:</strong> report3_donor_eligibility_dossier.sql
                </div>
            </div>
            <a href="report3_donor_eligibility_dossier.php" class="btn-open">Open Report 3 &#8594;</a>
        </div>

        <!-- Report 4 -->
        <div class="report-card">
            <div>
                <div class="card-top">
                    <div class="card-icon" style="background:#e0f2fe; color:#0284c7;">&#127973;</div>
                    <div>
                        <span style="font-size:11px; font-weight:700; color:#0284c7; text-transform:uppercase;">Report #4</span>
                        <h3 class="card-title">Hospital Requisition & Distribution</h3>
                    </div>
                </div>
                <p class="card-desc">Evaluates hospital blood demands, requested units, dates, and fulfillment status (Approved/Pending/Rejected).</p>
                <div class="code-meta">
                    <strong>PL/SQL:</strong> rpt_hospital_request_analysis<br>
                    <strong>SQL File:</strong> report4_hospital_request_analysis.sql
                </div>
            </div>
            <a href="report4_hospital_request_analysis.php" class="btn-open">Open Report 4 &#8594;</a>
        </div>

        <!-- Report 5 -->
        <div class="report-card">
            <div>
                <div class="card-top">
                    <div class="card-icon" style="background:#f3e8ff; color:#7e22ce;">&#128101;</div>
                    <div>
                        <span style="font-size:11px; font-weight:700; color:#7e22ce; text-transform:uppercase;">Report #5</span>
                        <h3 class="card-title">Camp Staff & Volunteer Workload</h3>
                    </div>
                </div>
                <p class="card-desc">Tracks staff allocations, duty tasks, camp venues, and geographical district distribution across Sri Lanka.</p>
                <div class="code-meta">
                    <strong>PL/SQL:</strong> rpt_staff_camp_performance<br>
                    <strong>SQL File:</strong> report5_staff_camp_performance.sql
                </div>
            </div>
            <a href="report5_staff_camp_performance.php" class="btn-open">Open Report 5 &#8594;</a>
        </div>

    </div>

    <div class="back-nav">
        <a href="../manager/manager_interface/manager_dashboard.php" class="btn-back">&#8592; Return to Blood Bank Manager Portal</a>
    </div>

</div>

</body>
</html>
