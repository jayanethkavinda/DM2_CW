<?php
session_start();
require_once 'db_connect.php';

// Redirect if already logged in as Donor
if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'Donor') {
    header("Location: donor_dashboard.php");
    exit;
}

$message = "";
$msg_type = "";
$active_tab = "login";

// Handle Registration & Login Form Submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // --- 1. DONOR REGISTRATION ---
    if (isset($_POST['action']) && $_POST['action'] === 'register') {
        $active_tab = "register";
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($full_name) || empty($email) || empty($password)) {
            $message = "Please fill in all required fields.";
            $msg_type = "error";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "Please provide a valid email address.";
            $msg_type = "error";
        } elseif ($password !== $confirm_password) {
            $message = "Passwords do not match.";
            $msg_type = "error";
        } elseif (strlen($password) < 4) {
            $message = "Password must be at least 4 characters long.";
            $msg_type = "error";
        } else {
            // Check if email already exists
            $chk_sql = "SELECT COUNT(*) AS CNT FROM users WHERE LOWER(email) = LOWER(:p_email)";
            $chk_stmt = oci_parse($conn, $chk_sql);
            oci_bind_by_name($chk_stmt, ":p_email", $email);
            oci_execute($chk_stmt);
            $row = oci_fetch_assoc($chk_stmt);

            if ($row && $row['CNT'] > 0) {
                $message = "An account with this email address already exists. Please log in.";
                $msg_type = "error";
            } else {
                // Call PL/SQL procedure or execute registration
                $plsql = "BEGIN register_donor_user(:p_email, :p_pass, :p_name, :o_uid, :o_did); END;";
                $stmt = oci_parse($conn, $plsql);
                $out_uid = 0;
                $out_did = 0;

                oci_bind_by_name($stmt, ":p_email", $email);
                oci_bind_by_name($stmt, ":p_pass", $password);
                oci_bind_by_name($stmt, ":p_name", $full_name);
                oci_bind_by_name($stmt, ":o_uid", $out_uid, 10);
                oci_bind_by_name($stmt, ":o_did", $out_did, 10);

                if (@oci_execute($stmt)) {
                    // Registration success: automatic login
                    $_SESSION['user_id'] = $out_uid;
                    $_SESSION['donor_id'] = $out_did;
                    $_SESSION['email'] = strtolower($email);
                    $_SESSION['full_name'] = $full_name;
                    $_SESSION['role'] = 'Donor';

                    header("Location: donor_dashboard.php?registered=1");
                    exit;
                } else {
                    // Fallback to direct SQL insert if procedure is not yet compiled
                    $uid_stmt = oci_parse($conn, "SELECT NVL(MAX(user_id), 0) + 1 AS NEXT_UID FROM users");
                    oci_execute($uid_stmt);
                    $uid_row = oci_fetch_assoc($uid_stmt);
                    $next_uid = $uid_row['NEXT_UID'];

                    $ins_user = oci_parse($conn, "INSERT INTO users (user_id, email, password, role) VALUES (:uid, :email, :pass, 'Donor')");
                    $lower_email = strtolower($email);
                    oci_bind_by_name($ins_user, ":uid", $next_uid);
                    oci_bind_by_name($ins_user, ":email", $lower_email);
                    oci_bind_by_name($ins_user, ":pass", $password);

                    if (@oci_execute($ins_user, OCI_NO_AUTO_COMMIT)) {
                        $did_stmt = oci_parse($conn, "SELECT NVL(MAX(donor_id), 0) + 1 AS NEXT_DID FROM donors");
                        oci_execute($did_stmt);
                        $did_row = oci_fetch_assoc($did_stmt);
                        $next_did = $did_row['NEXT_DID'];

                        $ins_donor = oci_parse($conn, "INSERT INTO donors (donor_id, user_id, full_name) VALUES (:did, :uid, :name)");
                        oci_bind_by_name($ins_donor, ":did", $next_did);
                        oci_bind_by_name($ins_donor, ":uid", $next_uid);
                        oci_bind_by_name($ins_donor, ":name", $full_name);

                        if (@oci_execute($ins_donor, OCI_NO_AUTO_COMMIT)) {
                            oci_commit($conn);
                            $_SESSION['user_id'] = $next_uid;
                            $_SESSION['donor_id'] = $next_did;
                            $_SESSION['email'] = $lower_email;
                            $_SESSION['full_name'] = $full_name;
                            $_SESSION['role'] = 'Donor';

                            header("Location: donor_dashboard.php?registered=1");
                            exit;
                        } else {
                            oci_rollback($conn);
                            $e = oci_error($ins_donor);
                            $message = "Registration error: " . $e['message'];
                            $msg_type = "error";
                        }
                    } else {
                        $e = oci_error($ins_user);
                        $message = "Registration error: " . $e['message'];
                        $msg_type = "error";
                    }
                }
            }
        }
    }

    // --- 2. DONOR LOGIN ---
    if (isset($_POST['action']) && $_POST['action'] === 'login') {
        $active_tab = "login";
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $message = "Please enter both Email and Password.";
            $msg_type = "error";
        } else {
            $sql = "SELECT u.user_id, u.email, u.password, u.role, d.donor_id, d.full_name, d.blood_group 
                    FROM users u 
                    LEFT JOIN donors d ON u.user_id = d.user_id 
                    WHERE LOWER(u.email) = LOWER(:p_email) AND u.password = :p_pass";
            
            $stmt = oci_parse($conn, $sql);
            oci_bind_by_name($stmt, ":p_email", $email);
            oci_bind_by_name($stmt, ":p_pass", $password);
            oci_execute($stmt);
            $user = oci_fetch_assoc($stmt);

            if ($user) {
                if ($user['ROLE'] !== 'Donor') {
                    $message = "Access restricted: This portal is for Blood Donors only.";
                    $msg_type = "error";
                } else {
                    // Set session variables
                    $_SESSION['user_id'] = $user['USER_ID'];
                    $_SESSION['donor_id'] = $user['DONOR_ID'];
                    $_SESSION['email'] = $user['EMAIL'];
                    $_SESSION['full_name'] = $user['FULL_NAME'] ?: 'Blood Donor';
                    $_SESSION['blood_group'] = $user['BLOOD_GROUP'] ?: '';
                    $_SESSION['role'] = 'Donor';

                    header("Location: donor_dashboard.php");
                    exit;
                }
            } else {
                $message = "Invalid email or password. Please try again.";
                $msg_type = "error";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blood Donor Portal - LifeLineConnect</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="auth-body">

    <div class="auth-container">
        <div class="auth-header">
            <div class="blood-drop-icon">&#129656;</div>
            <h1>LifeLineConnect</h1>
            <p class="subtitle">Blood Donor Portal</p>
        </div>

        <?php if($message != ""): ?>
            <div class="alert <?php echo $msg_type; ?>" id="alertBox">
                <span><?php echo $message; ?></span>
                <span class="close-btn" onclick="closeAlert()">&times;</span>
            </div>
        <?php endif; ?>

        <div class="auth-card">
            <!-- Tabs Header -->
            <div class="auth-tabs">
                <button type="button" class="tab-btn <?php echo ($active_tab === 'login') ? 'active' : ''; ?>" onclick="switchAuthTab('login')">Donor Login</button>
                <button type="button" class="tab-btn <?php echo ($active_tab === 'register') ? 'active' : ''; ?>" onclick="switchAuthTab('register')">Register as Donor</button>
            </div>

            <!-- Login Form -->
            <div id="login-form-pane" class="auth-pane <?php echo ($active_tab === 'login') ? 'active' : ''; ?>">
                <form action="login.php" method="POST">
                    <input type="hidden" name="action" value="login">
                    
                    <div class="form-group">
                        <label for="login-email">Email Address</label>
                        <input type="email" id="login-email" name="email" placeholder="example@domain.com" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="login-pass">Password</label>
                        <input type="password" id="login-pass" name="password" placeholder="Enter your password" required>
                    </div>

                    <button type="submit" class="btn btn-primary">Login to Donor Account</button>
                </form>
                <div class="auth-footer-text">
                    <p>New to LifeLineConnect? <a href="javascript:void(0)" onclick="switchAuthTab('register')">Create an account here</a></p>
                </div>
            </div>

            <!-- Register Form -->
            <div id="register-form-pane" class="auth-pane <?php echo ($active_tab === 'register') ? 'active' : ''; ?>">
                <form action="login.php" method="POST">
                    <input type="hidden" name="action" value="register">

                    <div class="form-group">
                        <label for="reg-name">Full Name</label>
                        <input type="text" id="reg-name" name="full_name" placeholder="Kamal Perera" required value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="reg-email">Email Address</label>
                        <input type="email" id="reg-email" name="email" placeholder="kamal@gmail.com" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="reg-pass">Password</label>
                        <input type="password" id="reg-pass" name="password" placeholder="Create a secure password" required>
                    </div>

                    <div class="form-group">
                        <label for="reg-cpass">Confirm Password</label>
                        <input type="password" id="reg-cpass" name="confirm_password" placeholder="Repeat your password" required>
                    </div>

                    <button type="submit" class="btn btn-primary">Complete Registration</button>
                </form>
                <div class="auth-footer-text">
                    <p>Already have an account? <a href="javascript:void(0)" onclick="switchAuthTab('login')">Sign in here</a></p>
                </div>
            </div>
        </div>
    </div>

    <script src="script.js"></script>
</body>
</html>
