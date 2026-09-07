<?php
session_start();
require_once 'db_connect.php';
require_once __DIR__ . '/../../donor/donor_interface/mongo_connect.php';

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
    
    // 1. Schedule Camp & Assign Staff
    if (isset($_POST['add_camp'])) {
        $id = $_POST['camp_id'];
        $name = $_POST['camp_name'];
        $date = date('d-M-Y', strtotime($_POST['camp_date'])); 
        $venue = $_POST['venue'];
        $dist = $_POST['district_id'];

        $sql = "BEGIN add_new_camp(:p_id, :p_name, TO_DATE(:p_date, 'DD-MON-YYYY'), :p_venue, :p_dist); END;";
        $stmt = oci_parse($conn, $sql);
        
        // 2. Bind parameters to protect against SQL Injection
        oci_bind_by_name($stmt, ":p_id", $id); 
        oci_bind_by_name($stmt, ":p_name", $name);
        oci_bind_by_name($stmt, ":p_date", $date); 
        oci_bind_by_name($stmt, ":p_venue", $venue);
        oci_bind_by_name($stmt, ":p_dist", $dist);
        
        if (@oci_execute($stmt)) {
            $assigned_count = 0;
            
            // Assign selected staff members
            if (!empty($_POST['staff_ids']) && is_array($_POST['staff_ids'])) {
                foreach ($_POST['staff_ids'] as $s_id) {
                    $s_id = intval($s_id);
                    $s_task = !empty($_POST['staff_task'][$s_id]) ? trim($_POST['staff_task'][$s_id]) : 'General Support';
                    
                    // Call PL/SQL procedure assign_staff_to_camp
                    $assign_sql = "BEGIN assign_staff_to_camp(:p_staff_id, :p_camp_id, :p_task); END;";
                    $assign_stmt = oci_parse($conn, $assign_sql);
                    oci_bind_by_name($assign_stmt, ":p_staff_id", $s_id);
                    oci_bind_by_name($assign_stmt, ":p_camp_id", $id);
                    oci_bind_by_name($assign_stmt, ":p_task", $s_task);
                    
                    if (@oci_execute($assign_stmt)) {
                        $assigned_count++;
                    } else {
                        // Fallback direct insert if needed
                        $fb_sql = "INSERT INTO staff_assignments (assignment_id, staff_id, camp_id, task) 
                                   VALUES ((SELECT NVL(MAX(assignment_id), 0) + 1 FROM staff_assignments), :p_staff_id, :p_camp_id, :p_task)";
                        $fb_stmt = oci_parse($conn, $fb_sql);
                        oci_bind_by_name($fb_stmt, ":p_staff_id", $s_id);
                        oci_bind_by_name($fb_stmt, ":p_camp_id", $id);
                        oci_bind_by_name($fb_stmt, ":p_task", $s_task);
                        if (@oci_execute($fb_stmt, OCI_COMMIT_ON_SUCCESS)) {
                            $assigned_count++;
                        }
                    }
                }
            }

            if ($assigned_count > 0) {
                $message = "Camp scheduled successfully and {$assigned_count} staff member(s) assigned!";
            } else {
                $message = "Camp scheduled successfully!";
            }
            $msg_type = "success";
        } else {
            $e = oci_error($stmt); $message = $e['message']; $msg_type = "error";
        }
    }

    // 2. Update Camp Venue
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

// Fetch Districts for Dropdown
$districts_list = [];
$dist_stmt = oci_parse($conn, "SELECT district_id, district_name FROM districts ORDER BY district_name ASC");
oci_execute($dist_stmt);
while ($row = oci_fetch_assoc($dist_stmt)) {
    $districts_list[] = $row;
}

// Fetch Available Staff Members for Camp Assignment Dropdown / Checkboxes
$staff_members_list = [];
$sm_stmt = oci_parse($conn, "SELECT user_id, email FROM users WHERE role = 'Staff' ORDER BY user_id ASC");
oci_execute($sm_stmt);
while ($s_row = oci_fetch_assoc($sm_stmt)) {
    $staff_members_list[] = $s_row;
}

// Fetch Assigned Staff for all camps
$camp_staff_map = [];
$c_staff_stmt = oci_parse($conn, "SELECT sa.camp_id, sa.staff_id, u.email, sa.task 
                                  FROM staff_assignments sa 
                                  JOIN users u ON sa.staff_id = u.user_id 
                                  ORDER BY sa.camp_id, u.email");
if (@oci_execute($c_staff_stmt)) {
    while ($cs_row = oci_fetch_assoc($c_staff_stmt)) {
        $cid = intval($cs_row['CAMP_ID']);
        $camp_staff_map[$cid][] = [
            'staff_id' => $cs_row['STAFF_ID'],
            'email' => $cs_row['EMAIL'],
            'task' => $cs_row['TASK'] ?? 'General Support'
        ];
    }
}

// Fetch Camp Statistics
$total_camps = 0;
$tc_stmt = oci_parse($conn, "SELECT COUNT(*) AS CNT FROM camps");
oci_execute($tc_stmt);
if ($r = oci_fetch_assoc($tc_stmt)) $total_camps = $r['CNT'];

$upcoming_camps = 0;
$uc_stmt = oci_parse($conn, "SELECT COUNT(*) AS CNT FROM camps WHERE camp_date >= TRUNC(SYSDATE)");
oci_execute($uc_stmt);
if ($r = oci_fetch_assoc($uc_stmt)) $upcoming_camps = $r['CNT'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Camps Management - LifeLineConnect</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Modal Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.65);
            backdrop-filter: blur(4px);
            z-index: 999;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.2s ease-out;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-box {
            background: #FFFFFF;
            width: 90%;
            max-width: 650px;
            max-height: 85vh;
            border-radius: var(--radius-lg);
            box-shadow: 0 20px 40px rgba(0,0,0,0.25);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            border: 1px solid var(--border-color);
        }

        .modal-header {
            padding: 20px 24px;
            background: linear-gradient(135deg, #780B1E, #4A0512);
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            font-size: 18px;
            font-weight: 700;
            margin: 0;
        }

        .modal-close-btn {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            font-size: 18px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0;
        }

        .modal-close-btn:hover {
            background: rgba(255,255,255,0.35);
        }

        .modal-body {
            padding: 24px;
            overflow-y: auto;
            background: #F8FAFC;
        }

        .review-card-item {
            background: #FFFFFF;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 16px 18px;
            margin-bottom: 14px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.04);
        }

        .review-card-item:last-child {
            margin-bottom: 0;
        }

        .review-donor-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .review-donor-name {
            font-weight: 700;
            color: var(--text-primary);
            font-size: 14.5px;
        }

        .review-stars {
            color: #F59E0B;
            font-size: 15px;
            letter-spacing: 2px;
        }

        .review-feedback-text {
            font-size: 13.5px;
            color: #334155;
            line-height: 1.5;
            background: #F8FAFC;
            padding: 10px 12px;
            border-radius: 6px;
            border-left: 3px solid var(--primary-color);
        }

        .review-date {
            font-size: 11.5px;
            color: var(--text-muted);
            margin-top: 8px;
            text-align: right;
        }

        .btn-reviews {
            background: #880E4F !important;
            color: white !important;
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-weight: 600;
        }

        .btn-reviews:hover {
            background: #AD1457 !important;
            box-shadow: 0 2px 8px rgba(136, 14, 79, 0.3) !important;
        }
    </style>
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
            <li><a href="manager_dashboard.php" class="nav-link"><span class="nav-icon">&#128202;</span> Dashboard Stats</a></li>
            <li><a href="../../reports/index.php" class="nav-link"><span class="nav-icon">&#128196;</span> PL/SQL Business Reports</a></li>
            <li><a href="manager_chat.php" class="nav-link"><span class="nav-icon">&#128172;</span> Donor Messages (Chat)</a></li>
            <li><a href="manager_camps.php" class="nav-link active"><span class="nav-icon">&#127973;</span> Camps Management</a></li>
            <li><a href="manager_dashboard.php#inventory-section" class="nav-link"><span class="nav-icon">&#129514;</span> Blood Inventory & Records</a></li>
            <li><a href="manager_dashboard.php#hospital-req" class="nav-link"><span class="nav-icon">&#127973;</span> Hospital Requests</a></li>
            <li><a href="manager_dashboard.php#donor-list" class="nav-link"><span class="nav-icon">&#128100;</span> Registered Donors</a></li>
            <li class="nav-divider"></li>
            <li><a href="logout.php" class="nav-link" style="color: #FECDD3;"><span class="nav-icon">&#128682;</span> Log Out</a></li>
        </ul>
    </div>

    <!-- Main Content Area -->
    <div class="main-content">
        
        <!-- Header Bar -->
        <div class="header">
            <div>
                <h1>Blood Donation Camps Management</h1>
                <p class="header-sub">Schedule new donation drives, manage event locations, and track donor camp reviews</p>
            </div>
            <div style="display: flex; gap: 10px;">
                <span class="badge-role" style="background:#B71C1C; padding: 8px 14px; font-size:12px;">Total Camps: <?php echo $total_camps; ?></span>
                <span class="badge-role" style="background:#16A34A; padding: 8px 14px; font-size:12px;">Upcoming: <?php echo $upcoming_camps; ?></span>
            </div>
        </div>

        <!-- Alert Notifications -->
        <?php if($message != ""): ?>
            <div class="alert <?php echo $msg_type; ?>" id="alertBox">
                <span><?php echo $message; ?></span>
                <span class="close-btn" onclick="closeAlert()">&times;</span>
            </div>
        <?php endif; ?>

        <!-- ======================================================================= -->
        <!-- UNIFIED CAMPS MANAGEMENT SECTION (BORDERED BOX COVERING BOTH) -->
        <!-- ======================================================================= -->
        <div class="camp-management-wrapper" id="camps-section">
            
            <!-- 1. SCHEDULED CAMPS TABLE (INNER CARD 1) -->
            <div class="inner-card" id="scheduled-camps">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                    <h3>Scheduled Blood Donation Camps</h3>
                    <a href="#schedule-camp-form" class="btn btn-small" style="text-decoration: none; width: auto;">+ Schedule New Camp</a>
                </div>
                <p style="font-size: 13px; color: #64748B; margin-bottom: 15px;">List of all ongoing and upcoming blood donation drives with quick venue updating and donor reviews.</p>

                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Camp ID</th>
                            <th>Camp Name</th>
                            <th>Date</th>
                            <th>Venue / Location</th>
                            <th>District</th>
                            <th>Assigned Staff</th>
                            <th>Donor Rating & Reviews</th>
                            <th>Status</th>
                            <th>Update Venue</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php
                        // 1. Fetch all Donor Reviews from MongoDB and calculate Avg Rating & Count per Camp
                        $all_reviews = MongoDataService::getCampReviews();
                        
                        $camp_ratings = [];
                        foreach ($all_reviews as $rev) {
                            $cid = intval($rev['camp_id'] ?? 0);
                            if ($cid > 0) {
                                if (!isset($camp_ratings[$cid])) {
                                    $camp_ratings[$cid] = ['total_stars' => 0, 'count' => 0];
                                }
                                $camp_ratings[$cid]['total_stars'] += intval($rev['rating'] ?? 5);
                                $camp_ratings[$cid]['count']++;
                            }
                        }

                        // 2. Fetch Camps from Oracle Database
                        $sc_stmt = oci_parse($conn, "SELECT c.camp_id, c.camp_name, TO_CHAR(c.camp_date, 'YYYY-MM-DD') AS c_date, 
                                                            c.venue, d.district_name,
                                                            CASE 
                                                                WHEN c.camp_date = TRUNC(SYSDATE) THEN 'Today'
                                                                WHEN c.camp_date > TRUNC(SYSDATE) THEN 'Upcoming'
                                                                ELSE 'Past'
                                                            END AS status_text
                                                     FROM camps c 
                                                     JOIN districts d ON c.district_id = d.district_id");
                        oci_execute($sc_stmt);
                        $camps_list = [];
                        while ($sc_row = oci_fetch_assoc($sc_stmt)) {
                            $cid = intval($sc_row['CAMP_ID']);
                            $count = $camp_ratings[$cid]['count'] ?? 0;
                            $avg = ($count > 0) ? round($camp_ratings[$cid]['total_stars'] / $count, 1) : 0;
                            $sc_row['AVG_RATING'] = $avg;
                            $sc_row['REVIEW_COUNT'] = $count;
                            $camps_list[] = $sc_row;
                        }

                        // 3. Rank Camps by Donor Reviews (Highest Rating & Review Count first)
                        usort($camps_list, function($a, $b) {
                             // 1. Compare by Average Rating (Descending)
                            if ($b['AVG_RATING'] != $a['AVG_RATING']) {
                                return $b['AVG_RATING'] <=> $a['AVG_RATING'];
                            }
                            // 2. Compare by Total Review Count (Descending)
                            if ($b['REVIEW_COUNT'] != $a['REVIEW_COUNT']) {
                                return $b['REVIEW_COUNT'] <=> $a['REVIEW_COUNT'];
                            }
                            // 3. Fallback: Compare by Date (Newest First)
                            return strcmp($b['C_DATE'], $a['C_DATE']);
                        });


                        $has_sc = false;
                        $rank = 1;
                        foreach ($camps_list as $sc_row) {
                            $has_sc = true;
                            $badge_status = ($sc_row['STATUS_TEXT'] === 'Today') ? 'btn-danger' : (($sc_row['STATUS_TEXT'] === 'Upcoming') ? 'btn-success' : 'btn-small');
                            $cid = $sc_row['CAMP_ID'];
                            $cname = htmlspecialchars($sc_row['CAMP_NAME'], ENT_QUOTES);
                            $avg_rating = $sc_row['AVG_RATING'];
                            $rev_count = $sc_row['REVIEW_COUNT'];

                            // Rank Badge Stylings
                            $rank_badge = "<span style='font-weight:700; color:#64748B;'>#".$rank."</span>";
                            if ($avg_rating > 0) {
                                if ($rank == 1) $rank_badge = "<span style='font-size:14px; font-weight:700; color:#D97706; background:#FEF3C7; padding:3px 8px; border-radius:12px;'>🥇 #1 Top</span>";
                                elseif ($rank == 2) $rank_badge = "<span style='font-size:14px; font-weight:700; color:#475569; background:#E2E8F0; padding:3px 8px; border-radius:12px;'>🥈 #2</span>";
                                elseif ($rank == 3) $rank_badge = "<span style='font-size:14px; font-weight:700; color:#B45309; background:#FFEDD5; padding:3px 8px; border-radius:12px;'>🥉 #3</span>";
                            }

                            // Rating Stars Display
                            if ($rev_count > 0) {
                                $rating_html = "<div style='display:flex; flex-direction:column; gap:2px;'>
                                                    <span style='color:#F59E0B; font-weight:700; font-size:13px;'>⭐ ".$avg_rating." <small style='color:#64748B;'>/ 5.0</small></span>
                                                    <small style='color:#64748B;'>(".$rev_count." ".($rev_count == 1 ? 'review' : 'reviews').")</small>
                                                </div>";
                            } else {
                                $rating_html = "<span style='color:#94A3B8; font-size:12px;'>No reviews yet</span>";
                            }

                            // Assigned Staff Badges
                            $assigned_staff = $camp_staff_map[$cid] ?? [];
                            $staff_html = "";
                            if (!empty($assigned_staff)) {
                                $staff_html = "<div style='display:flex; flex-direction:column; gap:4px; max-width:200px;'>";
                                foreach ($assigned_staff as $st) {
                                    $email_short = htmlspecialchars($st['email']);
                                    $task_name = htmlspecialchars($st['task']);
                                    $staff_html .= "<span style='font-size:11.5px; background:#EFF6FF; color:#1E40AF; border:1px solid #BFDBFE; padding:3px 7px; border-radius:4px; line-height:1.3;'>
                                                        <strong>👤 {$email_short}</strong><br>
                                                        <small style='color:#64748B;'>({$task_name})</small>
                                                    </span>";
                                }
                                $staff_html .= "</div>";
                            } else {
                                $staff_html = "<span style='color:#94A3B8; font-size:12px;'>None assigned</span>";
                            }

                            echo "<tr>";
                            echo "<td>".$rank_badge."</td>";
                            echo "<td>#".$sc_row['CAMP_ID']."</td>";
                            echo "<td><strong>".htmlspecialchars($sc_row['CAMP_NAME'])."</strong></td>";
                            echo "<td>&#128197; ".$sc_row['C_DATE']."</td>";
                            echo "<td>&#128205; ".htmlspecialchars($sc_row['VENUE'])."</td>";
                            echo "<td>".htmlspecialchars($sc_row['DISTRICT_NAME'])."</td>";
                            echo "<td>".$staff_html."</td>";
                            echo "<td>".$rating_html."</td>";
                            echo "<td><span class='btn-small ".$badge_status."'>".$sc_row['STATUS_TEXT']."</span></td>";
                            echo "<td>
                                    <form method='POST' style='display:flex; gap:6px; margin:0;'>
                                        <input type='hidden' name='camp_id_edit' value='".$sc_row['CAMP_ID']."'>
                                        <input type='text' name='new_venue_text' placeholder='New venue' required style='margin:0; padding:6px 10px; font-size:12px; width:120px;'>
                                        <button type='submit' name='update_venue_action' class='btn-small' style='white-space:nowrap;'>Update</button>
                                    </form>
                                  </td>";
                            echo "<td>
                                    <button type='button' class='btn-small btn-reviews' onclick='openCampReviewsModal(".$cid.", \"".$cname."\")'>
                                        <span>&#11088; View (".$rev_count.")</span>
                                    </button>
                                  </td>";
                            echo "</tr>";
                            $rank++;
                        }
                        if (!$has_sc) {
                            echo "<tr><td colspan='11' style='text-align: center; color: #94A3B8; padding: 25px;'>No scheduled camps found in the database. Schedule one below.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>

            <!-- 2. SCHEDULE NEW CAMP FORM (INNER CARD 2 - SEPARATED BY GAP) -->
            <div class="inner-card" id="schedule-camp-form">
                <h3>Schedule New Blood Donation Camp</h3>
                <p style="font-size: 13px; color: #64748B; margin-bottom: 18px;">Schedule an upcoming blood donation drive with venue, district, and assigned staff members.</p>
                
                <form action="manager_camps.php" method="POST" style="max-width: 650px;">
                    <label>Camp ID</label>
                    <input type="number" name="camp_id" placeholder="e.g. 105" required>
                    
                    <label>Camp Name</label>
                    <input type="text" name="camp_name" placeholder="e.g. Colombo Central Blood Drive" required>
                    
                    <label>Date of Camp</label>
                    <input type="date" name="camp_date" required>
                    
                    <label>Venue / Location</label>
                    <input type="text" name="venue" placeholder="e.g. Town Hall Auditorium" required>
                    
                    <label>District</label>
                    <select name="district_id" required>
                        <option value="">-- Choose District --</option>
                        <?php foreach($districts_list as $dist): ?>
                            <option value="<?php echo $dist['DISTRICT_ID']; ?>"><?php echo htmlspecialchars($dist['DISTRICT_NAME']); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <!-- Staff Allocation Section (Supports multiple staff per camp) -->
                    <div style="margin-top: 18px; margin-bottom: 18px; border-top: 1px dashed #CBD5E1; padding-top: 14px;">
                        <label style="font-weight: 700; color: #1E293B; margin-bottom: 4px; display: block;">👥 Assign Staff Members & Tasks (Optional / Multi-Select):</label>
                        <p style="font-size: 12px; color: #64748B; margin-bottom: 10px;">Select one or more staff members to deploy for this camp and designate their role/task.</p>

                        <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 12px; max-height: 220px; overflow-y: auto; display: flex; flex-direction: column; gap: 8px;">
                            <?php if (empty($staff_members_list)): ?>
                                <span style="font-size: 13px; color: #94A3B8;">No staff users currently registered in the database.</span>
                            <?php else: ?>
                                <?php foreach ($staff_members_list as $sm): ?>
                                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 10px; background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 6px; padding: 8px 12px;">
                                        <label style="display: flex; align-items: center; gap: 8px; margin: 0; cursor: pointer; font-size: 13px; font-weight: 600; color: #334155; flex: 1;">
                                            <input type="checkbox" name="staff_ids[]" value="<?php echo $sm['USER_ID']; ?>" style="margin: 0; width: 16px; height: 16px; cursor: pointer;">
                                            <span>👤 <?php echo htmlspecialchars($sm['EMAIL']); ?> <small style="color: #64748B; font-weight: normal;">(ID: #<?php echo $sm['USER_ID']; ?>)</small></span>
                                        </label>
                                        <select name="staff_task[<?php echo $sm['USER_ID']; ?>]" style="width: 170px; margin: 0; padding: 4px 8px; font-size: 12px; height: 32px; border-radius: 4px;">
                                            <option value="Blood Collection">Blood Collection</option>
                                            <option value="Donor Registration">Donor Registration</option>
                                            <option value="Medical Screening">Medical Screening</option>
                                            <option value="Camp Coordinator">Camp Coordinator</option>
                                            <option value="Refreshments & Care">Refreshments & Care</option>
                                            <option value="General Support" selected>General Support</option>
                                        </select>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <button type="submit" name="add_camp" style="width: auto; padding: 11px 26px;">Schedule Camp</button>
                </form>
            </div>

        </div>

    </div>

    <!-- ======================================================================= -->
    <!-- CAMP REVIEWS MODAL (MONGODB INTEGRATED) -->
    <!-- ======================================================================= -->
    <div class="modal-overlay" id="campReviewsModal" onclick="handleModalOverlayClick(event)">
        <div class="modal-box">
            <div class="modal-header">
                <div>
                    <h3 id="modalCampTitle">Camp Reviews & Feedback</h3>
                    <small id="modalCampSub" style="color: #FECDD3; font-size:12px;">Connected to Cloud MongoDB Atlas</small>
                </div>
                <button type="button" class="modal-close-btn" onclick="closeCampReviewsModal()">&times;</button>
            </div>
            
            <div class="modal-body" id="modalReviewsContent">
                <div style="text-align:center; padding:30px; color:#94A3B8;">Loading reviews from MongoDB...</div>
            </div>
        </div>
    </div>

    <script>
        async function openCampReviewsModal(campId, campName) {
            const modal = document.getElementById('campReviewsModal');
            const titleEl = document.getElementById('modalCampTitle');
            const subEl = document.getElementById('modalCampSub');
            const contentEl = document.getElementById('modalReviewsContent');

            titleEl.innerText = `Reviews for ${campName}`;
            subEl.innerText = `Camp ID: #${campId} • Loading from MongoDB Atlas...`;
            contentEl.innerHTML = `<div style="text-align:center; padding:35px; color:#64748B;"><span style="font-size:24px;">&#8987;</span><br>Fetching donor ratings and feedback...</div>`;

            modal.classList.add('active');

            try {
                const response = await fetch(`api_manager_reviews.php?action=get_camp_reviews&camp_id=${campId}`);
                const data = await response.json();

                if (data.status === 'success') {
                    renderCampReviews(campName, data);
                } else {
                    contentEl.innerHTML = `<div style="padding:20px; text-align:center; color:#DC2626;">Error: ${data.message || 'Could not load reviews.'}</div>`;
                }
            } catch (err) {
                console.error("Error loading reviews:", err);
                contentEl.innerHTML = `<div style="padding:20px; text-align:center; color:#DC2626;">Failed to connect to MongoDB server.</div>`;
            }
        }

        function renderCampReviews(campName, data) {
            const contentEl = document.getElementById('modalReviewsContent');
            const subEl = document.getElementById('modalCampSub');

            const count = data.count || 0;
            const avg = data.avg_rating || 0;
            subEl.innerText = `Camp ID: #${data.camp_id} • Total Reviews: ${count} • Avg Rating: ⭐ ${avg > 0 ? avg + ' / 5.0' : 'N/A'}`;

            if (!data.reviews || data.reviews.length === 0) {
                contentEl.innerHTML = `
                    <div style="text-align:center; padding:40px 20px; color:#94A3B8;">
                        <span style="font-size:42px; display:block; margin-bottom:10px;">&#128172;</span>
                        <strong style="font-size:15px; color:#475569;">No reviews yet for this camp</strong>
                        <p style="font-size:13px; margin-top:5px;">Donors who attend this camp can submit their ratings and feedback from their donor portal.</p>
                    </div>
                `;
                return;
            }

            let html = `
                <div style="background:#FFFFFF; border:1px solid #E2E8F0; border-radius:8px; padding:12px 18px; margin-bottom:18px; display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <strong style="color:#1E293B; font-size:14px;">Overall Rating:</strong>
                        <span style="color:#F59E0B; font-size:16px; margin-left:6px; font-weight:bold;">${'★'.repeat(Math.round(avg))}${'☆'.repeat(5 - Math.round(avg))} (${avg}/5.0)</span>
                    </div>
                    <span class="badge-role" style="background:#880E4F; font-size:11px;">${count} Donor Review${count > 1 ? 's' : ''}</span>
                </div>
            `;

            data.reviews.forEach(rev => {
                const ratingNum = parseInt(rev.rating || 5);
                const stars = '★'.repeat(ratingNum) + '☆'.repeat(5 - ratingNum);
                const donorName = rev.donor_name || 'Anonymous Donor';
                const feedback = rev.feedback || 'No written feedback.';
                const time = rev.timestamp || 'Recent';

                html += `
                    <div class="review-card-item">
                        <div class="review-donor-header">
                            <span class="review-donor-name">👤 ${escapeHtml(donorName)}</span>
                            <span class="review-stars">${stars}</span>
                        </div>
                        <div class="review-feedback-text">"${escapeHtml(feedback)}"</div>
                        <div class="review-date">Submitted on: ${time}</div>
                    </div>
                `;
            });

            contentEl.innerHTML = html;
        }

        function closeCampReviewsModal() {
            document.getElementById('campReviewsModal').classList.remove('active');
        }

        function handleModalOverlayClick(e) {
            if (e.target.id === 'campReviewsModal') {
                closeCampReviewsModal();
            }
        }

        function escapeHtml(text) {
            const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
            return String(text).replace(/[&<>"']/g, m => map[m]);
        }
    </script>
    <script src="script.js"></script>
</body>
</html>
