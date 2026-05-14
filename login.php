<?php
session_start();

require_once "config/Database.php";
require_once "classes/User.php";

$db = new Database();
$conn = $db->connect();

$user = new User($conn);

$message = $error = "";

if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

// User Login
if (isset($_POST["loginBTN"])) {

    // User credentials
    $email      = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password   = $_POST['password'] ?? '';

    // Validate user credentials
    if (empty($email) || empty($password)) {
        $error = "All fields are required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format";
    } else {
        $result = $user->userLogin($email, $password);

        if ($result['status'] === true) {

            // Session
            $_SESSION['user_id'] = $result['user_id'];
            $_SESSION['username'] = $result['username'];
            $_SESSION['user_email'] = $result['user_email'];
            $_SESSION['user_role'] = $result['user_role'];
            $_SESSION['login_success'] = true;

            // Role based redirection
            $role = strtolower($result['user_role']);

            switch ($role) {
                case 'student':
                    header("Location: student_dashboard.php");
                    break; 
                case 'staff':
                    header("Location: staff_dashboard.php");
                    break;
                case 'admin':
                    header("Location: admin_dashboard.php");
                    break;
                default:
                    header("Location: default_dashboard.php");
                    break;
            }
        } else {
            $error = $result['message'];
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | SCMRS</title>
    <link rel="shortcut icon" type="image/x-icon" href="assets/img/favicon.png">
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/animate.css">
    <link rel="stylesheet" href="assets/plugins/select2/css/select2.min.css">
    <link rel="stylesheet" href="assets/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="assets/plugins/fontawesome/css/fontawesome.min.css">
    <link rel="stylesheet" href="assets/plugins/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="assets/css/auth-style.css">
</head>

<body>

    <div id="loader">
        <div class="spinner"></div>
    </div>

    <div class="auth-card text-center">
        <img src="assets/img/logo.png" alt="UDSM Logo" class="rounded-circle brand-logo">
        <h4 class="fw-bold mb-1">Welcome Back</h4>
        <p class="text-muted small mb-4">Login to manage your complaints</p>

        <!-- Alert -->
        <?php if (!empty($message) || !empty($error)):
            $type = !empty($message) ? 'success' : 'danger';
            $text = !empty($message) ? $message : $error;
            ?>
            <div class="alert alert-<?php echo $type; ?> text-start mb-3" id="loginAlert">
                <span style="display: flex; align-items: center; gap: 0.5rem; font-size: 15px;">
                    <i class="fas <?php echo $type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($text); ?>
                    <button onclick="document.getElementById('loginAlert').style.display='none'"
                        style="background: none; border: none; color: inherit; cursor: pointer; opacity: 0.7; transition: opacity 0.2s; margin-left: auto;"
                        onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.7'">
                        <i class="fas fa-times"></i>
                    </button>
                </span>
            </div>
        <?php endif; ?>
        <!-- / Alert -->

        <form action="" method="POST" onsubmit="showLoader()">
            <div class="mb-3 text-start">
                <label for="" class="form-label small fw-bold">Email Address</label>
                <input type="email" name="email" class="form-control" placeholder="name@example.com" autocomplete required>
            </div>
            <div class="mb-3 text-start">
                <label for="" class="form-label small fw-bold">Password</label>
                <input type="password" name="password" class="form-control" placeholder="........" autocomplete required>
            </div>
            <div class="d-flex justify-content-between mb-4">
                <div class="form-check small">
                    <input type="checkbox" class="form-check-input" id="remember">
                    <label for="remember" class="form-check-label">Remember me</label>
                </div>
                <a href="forgot_password.php" class="small text-decoration-none" style="color: var(--udsm-blue);">Forgot password?</a>
            </div>
            <button type="submit" name="loginBTN" class="btn btn-primary w-100 mb-3">Sign In <i class="fas fa-arrow-right"></i></button>
            <p class="small mb-0">Don't have an account? <a href="register.php" class="fw-bold text-decoration-none" style="color: var(--udsm-blue);">Register</a></p>
        </form>
    </div>

    <script src="assets/js/auth-script.js"></script>
    <script src="assets/js/bootstrap.bundle.min.js"></script>
</body>

</html>