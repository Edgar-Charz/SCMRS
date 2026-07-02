<?php
require_once 'config/session.php';

require_once "config/Database.php";
require_once "classes/User.php";
require_once "includes/csrf.php";

$db = new Database();
$conn = $db->connect();
$user = new User($conn);

$token = trim($_GET['token'] ?? '');
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
    csrf_verify();
    $password = $_POST['password'] ?? '';
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
    <!-- <link rel="stylesheet" href="assets/css/bootstrap.min.css"> -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css">
    <!-- <link rel="stylesheet" href="assets/css/animate.css"> -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/3.7.2/animate.min.css">
    <!-- <link rel="stylesheet" href="assets/plugins/fontawesome/css/fontawesome.min.css"> -->
    <!-- <link rel="stylesheet" href="assets/plugins/fontawesome/css/all.min.css"> -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/auth-style.css">
    <style>
        body {
            background-color: #001a52;
            position: relative;
        }

        .slide-bg {
            position: fixed;
            inset: 0;
            background-size: cover;
            background-position: center;
            opacity: 0;
            z-index: 0;
            animation: campusSlide 25s infinite;
        }

        .slide-bg:nth-child(1) {
            background-image: url('assets/img/campus1.jpg');
            animation-delay: 0s;
        }

        .slide-bg:nth-child(2) {
            background-image: url('assets/img/campus2.jpg');
            animation-delay: 5s;
        }

        .slide-bg:nth-child(3) {
            background-image: url('assets/img/campus3.jpg');
            animation-delay: 10s;
        }

        .slide-bg:nth-child(4) {
            background-image: url('assets/img/campus4.jpg');
            animation-delay: 15s;
        }

        .slide-bg:nth-child(5) {
            background-image: url('assets/img/campus5.jpg');
            animation-delay: 20s;
        }

        @keyframes campusSlide {
            0% {
                opacity: 0;
            }

            7% {
                opacity: 1;
            }

            27% {
                opacity: 1;
            }

            33% {
                opacity: 0;
            }

            100% {
                opacity: 0;
            }
        }

        body::before {
            content: '';
            position: fixed;
            inset: 0;
            z-index: 1;
            background: linear-gradient(160deg, rgba(0, 10, 40, .82) 0%, rgba(0, 35, 100, .72) 50%, rgba(0, 60, 100, .68) 100%);
            pointer-events: none;
        }

        body::after {
            content: none;
        }

        .auth-wrap {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 2;
            width: 100%;
            max-width: 375px;
        }

        .brand-logo {
            width: 86px;
            height: 86px;
            border: 3px solid rgba(253, 216, 53, 0.9);
            box-shadow: 0 0 0 5px rgba(253, 216, 53, 0.12), 0 8px 28px rgba(0, 0, 0, 0.5);
            margin-bottom: -30px;
            position: relative;
            z-index: 3;
            background: #fff;
        }

        .auth-card {
            position: relative;
            z-index: 2;
            background: #fff;
            box-shadow: 0 24px 64px rgba(0, 0, 0, .35), 0 4px 16px rgba(0, 0, 0, .2);
            padding-top: 48px;
        }

    </style>
</head>

<body>

    <div id="loader" class="loader-branded">
        <div class="loader-content">
            <img src="assets/img/logo.png" alt="UDSM" class="loader-logo">
            <div class="spinner"></div>
            <p class="loader-text">Please wait...</p>
        </div>
    </div>

    <div class="slide-bg"></div>
    <div class="slide-bg"></div>
    <div class="slide-bg"></div>
    <div class="slide-bg"></div>
    <div class="slide-bg"></div>

    <div class="auth-wrap">
        <img src="assets/img/logo.png" alt="UDSM Logo" class="rounded-circle brand-logo">
        <div class="auth-card text-center">
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
                    <?= csrf_field() ?>
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

                    <div class="mb-3 text-start">
                        <label class="form-label small fw-bold">New Password</label>
                        <div class="pwd-wrap">
                            <input type="password" id="resetPwd" name="password" class="form-control"
                                placeholder="Min. 8 chars, uppercase, number, symbol" required>
                            <button type="button" class="pwd-eye" onclick="togglePwd('resetPwd',this)" tabindex="-1">
                                <i class="fas fa-eye-slash"></i>
                            </button>
                        </div>
                    </div>
                    <div class="mb-4 text-start">
                        <label class="form-label small fw-bold">Confirm New Password</label>
                        <div class="pwd-wrap">
                            <input type="password" id="resetCpwd" name="confirm_password" class="form-control"
                                placeholder="Repeat your new password" required>
                            <button type="button" class="pwd-eye" onclick="togglePwd('resetCpwd',this)" tabindex="-1">
                                <i class="fas fa-eye-slash"></i>
                            </button>
                        </div>
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
                <a href="login.php" class="fw-bold text-decoration-none" style="color: var(--udsm-blue);">Back to
                    Login</a>
            </p>
        </div><!-- /.auth-card -->
    </div><!-- /.auth-wrap -->

    <script src="assets/js/auth-script.js"></script>
    <!-- <script src="assets/js/bootstrap.bundle.min.js"></script> -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
</body>

</html>