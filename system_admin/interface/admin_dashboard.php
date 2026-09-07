<?php
require_once 'db_connect.php';

$message = "";
$msg_type = "";

// 1. Handle Form Submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Add Staff User
    if (isset($_POST['add_staff'])) {
        $id = $_POST['user_id'];
        $email = $_POST['email'];
        $pass = $_POST['password'];
        
        // 1. call to  Stored Procedure  Using this  PL/SQL Block 
        $sql = "BEGIN add_staff_user(:p_id, :p_email, :p_pass); END;";
        $stmt = oci_parse($conn, $sql);

        // 2.Hnadle SQL Injection  Parameter Binding (oci_bind_by_name) 
        oci_bind_by_name($stmt, ":p_id", $id);
        oci_bind_by_name($stmt, ":p_email", $email);
        oci_bind_by_name($stmt, ":p_pass", $pass);
        
        // 3. Oracle Database  Execute 
        if (@oci_execute($stmt)) {
            $message = "Staff user registered successfully!";
            $msg_type = "success";
        } else {
            //get Exception/Error Message
            $e = oci_error($stmt);
            $message = $e['message'];
            $msg_type = "error";
        }
    }

    // Remove Staff User
    if (isset($_POST['remove_staff'])) {
        $id = $_POST['remove_user_id'];
        
        // 1. Call the Stored Procedure using an anonymous PL/SQL block
        $sql = "BEGIN remove_staff_user(:p_id); END;";
        $stmt = oci_parse($conn, $sql);
        // 2. Bind the input parameter to prevent SQL Injection
        oci_bind_by_name($stmt, ":p_id", $id);
        
        // 3. Execute the statement and evaluate the response
        if (@oci_execute($stmt)) {
            $message = "Staff user removed successfully!";
            $msg_type = "success";
        } else {
        // 4. Retrieve database exceptions and custom error messages    
            $e = oci_error($stmt);
            $message = $e['message'];
            $msg_type = "error";
        }
    }

    // Reset Staff Password
    if (isset($_POST['update_password'])) {
        $id = $_POST['reset_user_id'];
        $new_pass = $_POST['new_password'];

        $sql = "BEGIN update_staff_password(:p_id, :p_pass); END;";
        $stmt = oci_parse($conn, $sql);
        oci_bind_by_name($stmt, ":p_id", $id);
        oci_bind_by_name($stmt, ":p_pass", $new_pass);
        
        if (@oci_execute($stmt)) {
            $message = "Staff password updated successfully!";
            $msg_type = "success";
        } else {
            $e = oci_error($stmt);
            $message = $e['message'];
            $msg_type = "error";
        }
    }

    // Add District
    if (isset($_POST['add_district'])) {
        $dist_id = $_POST['district_id'];
        $dist_name = $_POST['district_name'];
        
        // 1. call to  Stored Procedure  Using this  PL/SQL Block 
        $sql = "BEGIN add_district(:p_id, :p_name); END;";
        $stmt = oci_parse($conn, $sql);

        // 2.Hnadle SQL Injection  Parameter Binding (oci_bind_by_name) 
        oci_bind_by_name($stmt, ":p_id", $dist_id);
        oci_bind_by_name($stmt, ":p_name", $dist_name);
        
        // 3. Oracle Database  Execute 
        if (@oci_execute($stmt)) {
            $message = "District added successfully!";
            $msg_type = "success";

        // 4. Execute() Error  Occurred   
        } else {
            $e = oci_error($stmt);
            $message = $e['message'];
            $msg_type = "error";
        }
    }

    // Remove District
    if (isset($_POST['remove_district_action'])) {
        $dist_id = $_POST['dist_id_del'];
        $sql = "BEGIN remove_district(:p_id); END;";
        $stmt = oci_parse($conn, $sql);
        oci_bind_by_name($stmt, ":p_id", $dist_id);
        
        if (@oci_execute($stmt)) {
            $message = "District deleted successfully!";
            $msg_type = "success";
        } else {
            // Direct delete fallback
            $d_stmt = oci_parse($conn, "DELETE FROM districts WHERE district_id = :p_id");
            oci_bind_by_name($d_stmt, ":p_id", $dist_id);
            if (@oci_execute($d_stmt, OCI_COMMIT_ON_SUCCESS)) {
                $message = "District removed successfully!";
                $msg_type = "success";
            } else {
                $e = oci_error($d_stmt);
                $message = "Could not delete district: " . $e['message'];
                $msg_type = "error";
            }
        }
    }
}

// 2. Fetch System Counters
$staff_count = 0;
$sc_stmt = oci_parse($conn, "SELECT COUNT(*) AS CNT FROM users WHERE role = 'Staff'");
oci_execute($sc_stmt);
if ($r = oci_fetch_assoc($sc_stmt)) $staff_count = $r['CNT'];

$donor_count = 0;
$dc_stmt = oci_parse($conn, "SELECT COUNT(*) AS CNT FROM users WHERE role = 'Donor'");
oci_execute($dc_stmt);
if ($r = oci_fetch_assoc($dc_stmt)) $donor_count = $r['CNT'];

$total_users = 0;
$tu_stmt = oci_parse($conn, "SELECT COUNT(*) AS CNT FROM users");
oci_execute($tu_stmt);
if ($r = oci_fetch_assoc($tu_stmt)) $total_users = $r['CNT'];

$district_count = 0;
$dst_stmt = oci_parse($conn, "SELECT COUNT(*) AS CNT FROM districts");
oci_execute($dst_stmt);
if ($r = oci_fetch_assoc($dst_stmt)) $district_count = $r['CNT'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Admin Dashboard - LifeLineConnect</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <!-- Sidebar Navigation -->
    <div class="sidebar">
        <div class="sidebar-brand">
            <span class="brand-icon">&#129656;</span>
            <div>
                <h2>LifeLineConnect</h2>
                <small class="badge-role">System Admin</small>
            </div>
        </div>

        <div class="sidebar-user">
            <div class="user-avatar">A</div>
            <div class="user-details">
                <strong>System Administrator</strong>
                <small>Full Privileges</small>
            </div>
        </div>

        <ul class="nav-menu">
            <li><a href="#stats-overview" class="nav-link active"><span class="nav-icon">&#128202;</span> Admin Overview</a></li>
            <li><a href="#add-staff" class="nav-link"><span class="nav-icon">&#128100;</span> Add Staff User</a></li>
            <li><a href="#add-district" class="nav-link"><span class="nav-icon">&#128205;</span> Manage Districts</a></li>
            <li><a href="#reset-pass" class="nav-link"><span class="nav-icon">&#128273;</span> Reset Password</a></li>
            <li><a href="#remove-staff" class="nav-link"><span class="nav-icon">&#128465;</span> Remove Staff</a></li>
            <li><a href="#all-users" class="nav-link"><span class="nav-icon">&#128101;</span> System Users List</a></li>
        </ul>
    </div>

    <!-- Main Content Area -->
    <div class="main-content">
        
        <!-- Header Bar -->
        <div class="header">
            <div>
                <h1>System Administration Panel</h1>
                <p class="header-sub">LifeLineConnect User Accounts, Roles & District Configuration</p>
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
                    <span class="stat-label">Total Staff Members</span>
                    <h3 class="stat-value"><?php echo $staff_count; ?></h3>
                </div>
            </div>

            <div class="stat-card stat-burgundy">
                <div class="stat-icon">&#129656;</div>
                <div>
                    <span class="stat-label">Registered Donors</span>
                    <h3 class="stat-value"><?php echo $donor_count; ?></h3>
                </div>
            </div>

            <div class="stat-card stat-green">
                <div class="stat-icon">&#128101;</div>
                <div>
                    <span class="stat-label">Total System Users</span>
                    <h3 class="stat-value"><?php echo $total_users; ?></h3>
                </div>
            </div>

            <div class="stat-card stat-amber">
                <div class="stat-icon">&#128205;</div>
                <div>
                    <span class="stat-label">Registered Districts</span>
                    <h3 class="stat-value"><?php echo $district_count; ?></h3>
                </div>
            </div>
        </div>

        <!-- Main Form Cards Grid -->
        <div class="card-container">
            <!-- Add Staff Form -->
            <div class="card" id="add-staff">
                <h3>Register New Staff Member</h3>
                <form action="admin_dashboard.php" method="POST">
                    <label>Staff User ID</label>
                    <input type="number" name="user_id" placeholder="e.g. 201" required>
                    
                    <label>Email Address</label>
                    <input type="email" name="email" placeholder="staff.colombo@lifeline.lk" required>
                    
                    <label>Password</label>
                    <input type="password" name="password" placeholder="Create staff password" required>
                    
                    <button type="submit" name="add_staff">Register Staff Account</button>
                </form>
            </div>

            <!-- Add District Form -->
            <div class="card" id="add-district">
                <h3>Add New District</h3>
                <form action="admin_dashboard.php" method="POST">
                    <label>District ID</label>
                    <input type="number" name="district_id" placeholder="e.g. 26" required>
                    
                    <label>District Name</label>
                    <input type="text" name="district_name" placeholder="e.g. Kegalle" required>
                    
                    <button type="submit" name="add_district">Save District Record</button>
                </form>
            </div>

            <!-- Reset Password Form -->
            <div class="card" id="reset-pass">
                <h3>Reset Staff Password</h3>
                <form action="admin_dashboard.php" method="POST">
                    <label>Staff User ID</label>
                    <input type="number" name="reset_user_id" placeholder="Enter Staff User ID" required>
                    
                    <label>New Password</label>
                    <input type="password" name="new_password" placeholder="Enter new password" required>
                    
                    <button type="submit" name="update_password">Update Password</button>
                </form>
            </div>

            <!-- Remove Staff Form -->
            <div class="card" id="remove-staff">
                <h3>Remove Staff Member</h3>
                <form action="admin_dashboard.php" method="POST" onsubmit="return confirm('Are you sure you want to remove this staff user? This cannot be undone.');">
                    <label>Staff User ID</label>
                    <input type="number" name="remove_user_id" placeholder="Enter Staff ID to remove" required>
                    
                    <button type="submit" name="remove_staff" class="btn-danger">Remove Staff Account</button>
                </form>
            </div>
        </div>

        <!-- All System Users Table -->
        <div class="card full-width" id="all-users" style="margin-top: 30px;">
            <h3>System Accounts & Roles Breakdown</h3>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>User ID</th>
                        <th>Email Address</th>
                        <th>Role</th>
                        <th>Account Type</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // 1. Prepare and execute SQL query to retrieve all system users sorted by ID
                    $u_stmt = oci_parse($conn, "SELECT user_id, email, role FROM users ORDER BY user_id ASC");
                    oci_execute($u_stmt);
                    // 2. Fetch and render each user record as a table row dynamically
                    while ($user = oci_fetch_assoc($u_stmt)) {
                        $role = $user['ROLE'];
                        // 3. Assign specific UI badge classes based on user role
                        $badge_class = 'role-staff';
                        if ($role === 'Admin') $badge_class = 'role-admin';
                        elseif ($role === 'Donor') $badge_class = 'role-donor';
                        // 4. Output safe HTML table row with proper escaping
                        echo "<tr>";
                        echo "<td>#".$user['USER_ID']."</td>";
                        echo "<td><strong>".htmlspecialchars($user['EMAIL'])."</strong></td>";
                        echo "<td><span class='badge-role-tag ".$badge_class."'>".$role."</span></td>";
                        echo "<td>".($role === 'Admin' ? 'System Administrator' : ($role === 'Staff' ? 'Blood Bank Staff' : 'Blood Donor'))."</td>";
                        echo "</tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <!-- Registered Districts List -->
        <div class="card full-width" style="margin-top: 30px;">
            <h3>Registered Districts List</h3>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>District ID</th>
                        <th>District Name</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $d_stmt = oci_parse($conn, "SELECT district_id, district_name FROM districts ORDER BY district_id ASC");
                    oci_execute($d_stmt);
                    while ($dist = oci_fetch_assoc($d_stmt)) {
                        echo "<tr>";
                        echo "<td>#".$dist['DISTRICT_ID']."</td>";
                        echo "<td><strong>".htmlspecialchars($dist['DISTRICT_NAME'])."</strong></td>";
                        echo "<td>
                                <form method='POST' style='margin:0;' onsubmit='return confirm(\"Are you sure you want to delete this district?\");'>
                                    <input type='hidden' name='dist_id_del' value='".$dist['DISTRICT_ID']."'>
                                    <button type='submit' name='remove_district_action' class='btn-small btn-danger'>Delete</button>
                                </form>
                              </td>";
                        echo "</tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

    </div>

    <script src="script.js"></script>
</body>
</html>