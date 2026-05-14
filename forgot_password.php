<?php
session_start();

require_once "config/Database.php";
require_once "classes/User.php";

$db   = new Database();
$conn = $db->connect();
$user = new User($conn);

$message = $error = "";

if (isset($_POST['submitBtn'])) {
    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        $error = "Please enter your email address.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        $token = $user->createPasswordResetToken($email);

        if ($token) {
            $resetLink = "http://" . $_SERVER['HTTP_HOST'] . "/scmrs/reset_password.php?token=" . $token;

            $to      = $email;
            $subject = "UDSM SCMRS - Password Reset Request";
            $body    = "Hello,\n\n"
                     . "You requested a password reset for your UDSM Complaints System account.\n\n"
                     . "Click the link below to reset your password (valid for 1 hour):\n"
                     . $resetLink . "\n\n"
                     . "If you did not request this, please ignore this email.\n\n"
                     . "UDSM Student Complaints Management System";
            $headers = "From: noreply@udsm.ac.tz\r\nX-Mailer: PHP/" . phpversion();

            mail($to, $subject, $body, $headers);
        }

        // Always show success to avoid user enumeration
        $message = "If that email is registered, a password reset link has been sent. Check your inbox.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | SCMRS</title>
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
        <h4 class="fw-bold mb-1">Forgot Password?</h4>
        <p class="text-muted small mb-4">Enter your registered email and we'll send you a reset link.</p>

        <?php if (!empty($message)): ?>
            <div class="alert alert-success text-start mb-3">
                <span style="display:flex; align-items:center; gap:0.5rem; font-size:15px;">
                    <i class="fas fa-check-circle"></i>
                    <?= htmlspecialchars($message) ?>
                </span>
            </div>
        <?php endif; ?>

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

        <?php if (empty($message)): ?>
        <form action="" method="POST" onsubmit="showLoader()">
            <div class="mb-3 text-start">
                <label class="form-label small fw-bold">Email Address</label>
                <input type="email" name="email" class="form-control"
                    placeholder="name@example.com"
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                    required>
            </div>
            <button type="submit" name="submitBtn" class="btn btn-primary w-100 mb-3">
                Send Reset Link <i class="fas fa-paper-plane ms-1"></i>
            </button>
        </form>
        <?php endif; ?>

        <p class="small mb-0">
            Remembered your password?
            <a href="login.php" class="fw-bold text-decoration-none" style="color: var(--udsm-blue);">Back to Login</a>
        </p>
    </div>

    <script src="assets/js/auth-script.js"></script>
    <script src="assets/js/bootstrap.bundle.min.js"></script>
</body>

</html>
