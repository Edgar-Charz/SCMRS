<?php
session_start();

require_once "config/Database.php";
require_once "classes/User.php";

$db   = new Database();
$conn = $db->connect();
$user = new User($conn);

$token   = trim($_GET['token'] ?? '');
$message = $error = "";
$validToken = false;

// Validate the token on every page load
if (empty($token)) {
    $error = "No reset token provided.";
} else {
    $emailForToken = $user->validateResetToken($token);
    if ($emailForToken === false) {
        $error = "This reset link is invalid or has expired. Please request a new one.";
    } else {
        $validToken = true;
    }
}

// Handle password reset submission
if ($validToken && isset($_POST['resetBtn'])) {
    $password        = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($password) || empty($confirmPassword)) {
        $error = "Both password fields are required.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $error = "Password must contain at least one uppercase letter.";
    } elseif (!preg_match('/[a-z]/', $password)) {
        $error = "Password must contain at least one lowercase letter.";
    } elseif (!preg_match('/[0-9]/', $password)) {
        $error = "Password must contain at least one number.";
    } elseif (!preg_match('/[\W]/', $password)) {
        $error = "Password must contain at least one special character.";
    } elseif ($password !== $confirmPassword) {
        $error = "Passwords do not match.";
    } else {
        try {
            $user->resetPassword($token, $password);
            $_SESSION['message'] = "Password reset successfully. You can now log in with your new password.";
            header("Location: login.php");
            exit;
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | SCMRS</title>
    <link rel="shortcut icon" type="image/x-icon" href="assets/img/favicon.png">
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/animate.css">
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
        <h4 class="fw-bold mb-1">Reset Password</h4>
        <p class="text-muted small mb-4">Enter and confirm your new password.</p>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger text-start mb-3" id="errorAlert">
                <span style="display:flex; align-items:center; gap:0.5rem; font-size:15px;">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($error) ?>
                    <button onclick="document.getElementById('errorAlert').style.display='none'"
                        style="background:none;border:none;color:inherit;cursor:pointer;opacity:0.7;transition:opacity 0.2s;margin-left:auto;"
                        onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.7'">
                        <i class="fas fa-times"></i>
                    </button>
                </span>
            </div>
        <?php endif; ?>

        <?php if ($validToken): ?>
        <form action="" method="POST" onsubmit="showLoader()">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

            <div class="mb-3 text-start">
                <label class="form-label small fw-bold">New Password</label>
                <input type="password" name="password" class="form-control"
                    placeholder="Min. 8 chars, uppercase, number, symbol" required>
            </div>
            <div class="mb-4 text-start">
                <label class="form-label small fw-bold">Confirm New Password</label>
                <input type="password" name="confirm_password" class="form-control"
                    placeholder="Repeat your new password" required>
            </div>
            <button type="submit" name="resetBtn" class="btn btn-primary w-100 mb-3">
                Reset Password <i class="fas fa-lock ms-1"></i>
            </button>
        </form>
        <?php else: ?>
        <a href="forgot_password.php" class="btn btn-primary w-100 mb-3">
            Request New Reset Link <i class="fas fa-arrow-right ms-1"></i>
        </a>
        <?php endif; ?>

        <p class="small mb-0">
            <a href="login.php" class="fw-bold text-decoration-none" style="color: var(--udsm-blue);">Back to Login</a>
        </p>
    </div>

    <script src="assets/js/auth-script.js"></script>
    <script src="assets/js/bootstrap.bundle.min.js"></script>
</body>

</html>
