<?php
session_start();
require_once 'db_connect.php';

// Redirect if already logged in as Manager/Staff
if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && in_array($_SESSION['role'], ['Staff', 'Admin'])) {
    header("Location: manager_dashboard.php");
    exit;
}

$message = "";
$msg_type = "";

// Handle Manager Login
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $message = "Please enter both Email and Password.";
        $msg_type = "error";
    } else {
        $sql = "SELECT user_id, email, password, role FROM users WHERE LOWER(email) = LOWER(:p_email) AND password = :p_pass";
        $stmt = oci_parse($conn, $sql);
        oci_bind_by_name($stmt, ":p_email", $email);
        oci_bind_by_name($stmt, ":p_pass", $password);
        oci_execute($stmt);
        $user = oci_fetch_assoc($stmt);

        if ($user) {
            if (!in_array($user['ROLE'], ['Staff', 'Admin'])) {
                $message = "Access Denied: This portal is reserved for Blood Bank Managers and Staff only.";
                $msg_type = "error";
            } else {
                $_SESSION['user_id'] = $user['USER_ID'];
                $_SESSION['email'] = $user['EMAIL'];
                $_SESSION['role'] = $user['ROLE'];

                header("Location: manager_dashboard.php");
                exit;
            }
        } else {
            $message = "Invalid manager credentials. Please verify your email and password.";
            $msg_type = "error";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Login - LifeLineConnect</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .auth-body {
            background: linear-gradient(135deg, #780B1E 0%, #1A202C 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }
        .auth-container {
            width: 100%;
            max-width: 440px;
        }
        .auth-header {
            text-align: center;
            color: white;
            margin-bottom: 25px;
        }
        .blood-drop-icon {
            font-size: 46px;
            margin-bottom: 5px;
        }
        .auth-header h1 {
            font-size: 26px;
            font-weight: 700;
        }
        .subtitle {
            font-size: 14px;
            color: #FECDD3;
        }
        .auth-card {
            background: white;
            padding: 32px;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
        }
        .auth-footer-text {
            text-align: center;
            margin-top: 20px;
            font-size: 13px;
            color: var(--text-secondary);
        }
        .auth-footer-text a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
        }
    </style>
</head>
<body class="auth-body">

    <div class="auth-container">
        <div class="auth-header">
            <div class="blood-drop-icon">&#129656;</div>
            <h1>LifeLineConnect</h1>
            <p class="subtitle">Blood Bank Manager Portal</p>
        </div>

        <?php if($message != ""): ?>
            <div class="alert <?php echo $msg_type; ?>" id="alertBox">
                <span><?php echo $message; ?></span>
                <span class="close-btn" onclick="closeAlert()">&times;</span>
            </div>
        <?php endif; ?>

        <div class="auth-card">
            <h3 style="margin-bottom: 20px; color: #B71C1C; font-size: 18px; border-bottom: 1px solid #E2E8F0; padding-bottom: 10px;">Manager Authentication</h3>
            <form action="login.php" method="POST">
                <div>
                    <label for="login-email">Manager Email Address</label>
                    <input type="email" id="login-email" name="email" placeholder="staff@lifeline.lk" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>

                <div>
                    <label for="login-pass">Password</label>
                    <input type="password" id="login-pass" name="password" placeholder="Enter your password" required>
                </div>

                <button type="submit" class="btn" style="margin-top: 10px;">Login to Manager Dashboard</button>
            </form>

            <div class="auth-footer-text">
                <p>Looking for Blood Donor Portal? <a href="../../donor/donor_interface/login.php">Donor Login</a></p>
            </div>
        </div>
    </div>

    <script src="script.js"></script>
</body>
</html>
