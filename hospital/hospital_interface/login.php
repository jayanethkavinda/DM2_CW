<?php
session_start();
require_once 'db_connect.php';

// Redirect if already logged in as Hospital
if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'Hospital') {
    header("Location: hospital_dashboard.php");
    exit;
}

$message = "";
$msg_type = "";
$active_tab = "login";

// Handle Login & Registration Form Submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // --- 1. HOSPITAL REGISTRATION ---
    if (isset($_POST['action']) && $_POST['action'] === 'register') {
        $active_tab = "register";
        $hospital_name = trim($_POST['hospital_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($hospital_name) || empty($email) || empty($password)) {
            $message = "Please fill in all required fields.";
            $msg_type = "error";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "Please enter a valid institutional email address.";
            $msg_type = "error";
        } elseif ($password !== $confirm_password) {
            $message = "Passwords do not match.";
            $msg_type = "error";
        } elseif (strlen($password) < 4) {
            $message = "Password must be at least 4 characters long.";
            $msg_type = "error";
        } else {
            // Check if email already exists
            $chk_stmt = oci_parse($conn, "SELECT COUNT(*) AS CNT FROM users WHERE LOWER(email) = LOWER(:p_email)");
            oci_bind_by_name($chk_stmt, ":p_email", $email);
            oci_execute($chk_stmt);
            $row = oci_fetch_assoc($chk_stmt);

            if ($row && $row['CNT'] > 0) {
                $message = "An account with this email address already exists. Please log in.";
                $msg_type = "error";
            } else {
                // Call PL/SQL procedure register_hospital_user or fallback insert
                $plsql = "BEGIN register_hospital_user(:p_email, :p_password, :p_name, :o_uid); END;";
                $stmt = oci_parse($conn, $plsql);
                $out_uid = 0;
                oci_bind_by_name($stmt, ":p_email", $email);
                oci_bind_by_name($stmt, ":p_password", $password);
                oci_bind_by_name($stmt, ":p_name", $hospital_name);
                oci_bind_by_name($stmt, ":o_uid", $out_uid, 10);

                if (@oci_execute($stmt)) {
                    $_SESSION['user_id'] = $out_uid;
                    $_SESSION['email'] = strtolower($email);
                    $_SESSION['hospital_name'] = $hospital_name;
                    $_SESSION['role'] = 'Hospital';

                    header("Location: hospital_dashboard.php?registered=1");
                    exit;
                } else {
                    // Fallback to direct SQL insert with safe bind variable names
                    $uid_stmt = oci_parse($conn, "SELECT NVL(MAX(user_id), 0) + 1 AS NEXT_UID FROM users");
                    oci_execute($uid_stmt);
                    $uid_row = oci_fetch_assoc($uid_stmt);
                    $next_uid = intval($uid_row['NEXT_UID'] ?? 1);

                    $lower_email = strtolower($email);
                    $ins_user = oci_parse($conn, "INSERT INTO users (user_id, email, password, role) VALUES (:p_uid, :p_email, :p_password, 'Hospital')");
                    oci_bind_by_name($ins_user, ":p_uid", $next_uid);
                    oci_bind_by_name($ins_user, ":p_email", $lower_email);
                    oci_bind_by_name($ins_user, ":p_password", $password);

                    if (@oci_execute($ins_user)) {
                        $_SESSION['user_id'] = $next_uid;
                        $_SESSION['email'] = $lower_email;
                        $_SESSION['hospital_name'] = $hospital_name;
                        $_SESSION['role'] = 'Hospital';

                        header("Location: hospital_dashboard.php?registered=1");
                        exit;
                    } else {
                        $e = oci_error($ins_user);
                        $err_text = $e['message'] ?? 'Database error occurred.';
                        
                        // If error is role validation trigger, give helpful message
                        if (strpos($err_text, '20011') !== false) {
                            $message = "Database Notice: Please run hospital/Hospital_PLSQL.sql in Oracle SQL Developer to enable the 'Hospital' role.";
                        } else {
                            $message = "Registration Failed: " . $err_text;
                        }
                        $msg_type = "error";
                    }
                }
            }
        }
    }

    // --- 2. HOSPITAL LOGIN ---
    if (isset($_POST['action']) && $_POST['action'] === 'login') {
        $active_tab = "login";
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $message = "Please enter both institutional email and password.";
            $msg_type = "error";
        } else {
            $lower_email = strtolower($email);
            // Verify Hospital credentials
            $auth_stmt = oci_parse($conn, "SELECT user_id, email, password, role FROM users WHERE LOWER(email) = :p_email AND role = 'Hospital'");
            oci_bind_by_name($auth_stmt, ":p_email", $lower_email);
            oci_execute($auth_stmt);
            $user = oci_fetch_assoc($auth_stmt);

            if ($user && $user['PASSWORD'] === $password) {
                $_SESSION['user_id'] = $user['USER_ID'];
                $_SESSION['email'] = $user['EMAIL'];
                $_SESSION['role'] = 'Hospital';

                // Try to resolve Hospital Name from past requests or email prefix
                $h_name_stmt = oci_parse($conn, "SELECT hospital_name FROM hospital_requests WHERE LOWER(hospital_name) LIKE '%' || :p_sub || '%' AND ROWNUM = 1");
                $sub_email = explode('@', $lower_email)[0];
                oci_bind_by_name($h_name_stmt, ":p_sub", $sub_email);
                oci_execute($h_name_stmt);
                $h_row = oci_fetch_assoc($h_name_stmt);

                $_SESSION['hospital_name'] = !empty($h_row['HOSPITAL_NAME']) ? $h_row['HOSPITAL_NAME'] : ucfirst($sub_email) . " General Hospital";

                header("Location: hospital_dashboard.php");
                exit;
            } else {
                $message = "Invalid Hospital credentials or unauthorized role.";
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
    <title>Hospital Requisition Portal - LifeLineConnect</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-card">
            <div class="auth-header">
                <h2>&#127973; LifeLineConnect</h2>
                <p>Hospital Blood Requisition Portal</p>
            </div>

            <!-- Tab Buttons -->
            <div class="auth-tabs">
                <button type="button" class="auth-tab-btn <?php echo ($active_tab === 'login') ? 'active' : ''; ?>" onclick="switchAuthTab('login')">Hospital Login</button>
                <button type="button" class="auth-tab-btn <?php echo ($active_tab === 'register') ? 'active' : ''; ?>" onclick="switchAuthTab('register')">New Hospital Register</button>
            </div>

            <div class="auth-body">
                <?php if (!empty($message)): ?>
                    <div class="alert alert-<?php echo $msg_type; ?>" id="alertBox">
                        <span><?php echo htmlspecialchars($message); ?></span>
                        <span style="cursor: pointer;" onclick="closeAlert()">&times;</span>
                    </div>
                <?php endif; ?>

                <!-- LOGIN PANE -->
                <div id="login-pane" class="auth-pane <?php echo ($active_tab === 'login') ? 'active' : ''; ?>">
                    <form action="login.php" method="POST">
                        <input type="hidden" name="action" value="login">

                        <div class="form-group" style="margin-bottom: 16px;">
                            <label for="login_email">Institutional Email</label>
                            <input type="email" id="login_email" name="email" placeholder="e.g. bloodbank@colombogh.lk" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                        </div>

                        <div class="form-group" style="margin-bottom: 24px;">
                            <label for="login_password">Password</label>
                            <input type="password" id="login_password" name="password" placeholder="••••••••" required>
                        </div>

                        <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px;">Sign In to Hospital Portal</button>
                    </form>
                </div>

                <!-- REGISTER PANE -->
                <div id="register-pane" class="auth-pane <?php echo ($active_tab === 'register') ? 'active' : ''; ?>">
                    <form action="login.php" method="POST">
                        <input type="hidden" name="action" value="register">

                        <div class="form-group" style="margin-bottom: 16px;">
                            <label for="reg_hname">Hospital / Institution Name <span style="color:var(--accent)">*</span></label>
                            <input type="text" id="reg_hname" name="hospital_name" placeholder="e.g. National Hospital of Sri Lanka" required value="<?php echo htmlspecialchars($_POST['hospital_name'] ?? ''); ?>">
                        </div>

                        <div class="form-group" style="margin-bottom: 16px;">
                            <label for="reg_email">Official Email Address <span style="color:var(--accent)">*</span></label>
                            <input type="email" id="reg_email" name="email" placeholder="e.g. requisition@hospital.gov.lk" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                        </div>

                        <div class="form-group" style="margin-bottom: 16px;">
                            <label for="reg_password">Password <span style="color:var(--accent)">*</span></label>
                            <input type="password" id="reg_password" name="password" placeholder="Minimum 4 characters" required>
                        </div>

                        <div class="form-group" style="margin-bottom: 24px;">
                            <label for="reg_confirm_password">Confirm Password <span style="color:var(--accent)">*</span></label>
                            <input type="password" id="reg_confirm_password" name="confirm_password" placeholder="Re-type password" required>
                        </div>

                        <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px;">Register Hospital Account</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="script.js"></script>
</body>
</html>
