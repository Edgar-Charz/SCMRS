<?php
require_once 'config/session.php';

require_once "config/Database.php";
require_once "classes/User.php";
require_once "classes/Settings.php";
require_once "includes/csrf.php";

$db = new Database();
$conn = $db->connect();

$user = new User($conn);
$branding = (new Settings($conn))->get();

$message = $error = "";
$lockRemaining = 0;

if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

// User Login
if (isset($_POST["loginBTN"])) {
    csrf_verify();

    // User credentials
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = $_POST['password'] ?? '';

    // Validate user credentials
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    if (empty($email) || empty($password)) {
        $error = "All fields are required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format";
    } elseif ($user->isIpRateLimited($clientIp)) {
        $ipWindow = $branding['ip_rate_limit_window_minutes'] ?? 15;
        $error = "Too many failed login attempts from your IP. Please try again in {$ipWindow} minutes.";
    } else {
        $result = $user->userLogin($email, $password);

        if ($result['status'] === true) {

            // Issue a fresh session ID to prevent session fixation attacks
            session_regenerate_id(true);

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
                case 'student_leader':
                    header("Location: leader_dashboard.php");
                    break;
                default:
                    header("Location: default_dashboard.php");
                    break;
            }
        } else {
            $user->recordFailedIpAttempt($clientIp);
            $error = $result['message'];
            $lockRemaining = (int)($result['lock_remaining'] ?? 0);
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
    <!-- <link rel="stylesheet" href="assets/css/bootstrap.min.css"> -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css">
    <!-- <link rel="stylesheet" href="assets/css/animate.css"> -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/3.7.2/animate.min.css">
    <!-- <link rel="stylesheet" href="assets/plugins/select2/css/select2.min.css"> -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css">
    <!-- <link rel="stylesheet" href="assets/css/dataTables.bootstrap4.min.css"> -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.21/css/dataTables.bootstrap4.min.css">
    <!-- <link rel="stylesheet" href="assets/plugins/fontawesome/css/fontawesome.min.css"> -->
    <!-- <link rel="stylesheet" href="assets/plugins/fontawesome/css/all.min.css"> -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/auth-style.css">
    <style>
        body {
            background-color: #001a52;
            position: relative;
        }

        /* ── Slideshow backgrounds ── */
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

        /* Dark overlay above photos */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            z-index: 1;
            background: linear-gradient(160deg,
                    rgba(0, 10, 40, 0.82) 0%,
                    rgba(0, 35, 100, 0.72) 50%,
                    rgba(0, 60, 100, 0.68) 100%);
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
            box-shadow: 0 24px 64px rgba(0, 0, 0, 0.35), 0 4px 16px rgba(0, 0, 0, 0.2);
            padding-top: 48px;
        }
    </style>
</head>

<body>

    <div id="loader" class="loader-branded">
        <div class="loader-content">
            <img src="<?= htmlspecialchars($branding['institution_logo_path']) ?>?v=<?= @filemtime(__DIR__ . '/' . $branding['institution_logo_path']) ?: 0 ?>" alt="<?= htmlspecialchars($branding['institution_name']) ?>" class="loader-logo">
            <div class="spinner"></div>
            <p class="loader-text">Please wait...</p>
        </div>
    </div>

    <!-- Campus slideshow backgrounds -->
    <div class="slide-bg"></div>
    <div class="slide-bg"></div>
    <div class="slide-bg"></div>
    <div class="slide-bg"></div>
    <div class="slide-bg"></div>

    <div class="auth-wrap">
        <img src="<?= htmlspecialchars($branding['institution_logo_path']) ?>?v=<?= @filemtime(__DIR__ . '/' . $branding['institution_logo_path']) ?: 0 ?>" alt="<?= htmlspecialchars($branding['institution_name']) ?> Logo" class="rounded-circle brand-logo">
        <div class="auth-card text-center">
            <h4 class="fw-bold mb-1">Welcome Back</h4>
            <p class="text-muted small mb-2">Login to manage your complaints</p>

            <!-- Alert -->
            <?php if (!empty($message) || !empty($error)):
                $type = !empty($message) ? 'success' : 'danger';
                $text = !empty($message) ? $message : $error;
                ?>
                <div class="alert alert-<?php echo $type; ?> text-start mb-2" id="loginAlert">
                    <span style="display: flex; align-items: center; gap: 0.5rem; font-size: 15px;">
                        <i class="fas <?php echo $type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                        <?php if ($lockRemaining > 0): ?>
                            Account locked. Try again in <strong id="lockCountdown" style="font-variant-numeric:tabular-nums;letter-spacing:0.03em;"></strong>
                        <?php else: ?>
                            <?php echo htmlspecialchars($text); ?>
                        <?php endif; ?>
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
                <?= csrf_field() ?>
                <div class="mb-2 text-start">
                    <label for="" class="form-label small fw-bold">Email Address</label>
                    <input type="email" name="email" class="form-control" placeholder="username@email.com" autocomplete
                        required>
                </div>
                <div class="mb-2 text-start">
                    <label class="form-label small fw-bold">Password</label>
                    <div class="pwd-wrap">
                        <input type="password" id="loginPwd" name="password" class="form-control" placeholder="........"
                            autocomplete required>
                        <button type="button" class="pwd-eye" onclick="togglePwd('loginPwd',this)" tabindex="-1">
                            <i class="fas fa-eye-slash"></i>
                        </button>
                    </div>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <div class="form-check small">
                        <input type="checkbox" class="form-check-input" id="remember">
                        <label for="remember" class="form-check-label">Remember me</label>
                    </div>
                    <a href="forgot_password.php" class="small text-decoration-none"
                        style="color: var(--udsm-blue);">Forgot
                        password?</a>
                </div>
                <button type="submit" name="loginBTN" class="btn btn-primary w-100 mb-2">Sign In <i
                        class="fas fa-arrow-right"></i></button>
                <p class="small mb-0">Don't have an account? <a href="register.php" class="fw-bold text-decoration-none"
                        style="color: var(--udsm-blue);">Register</a></p>
                <?php if (!empty($branding['institution_contact_email'])): ?>
                    <p class="small text-muted mb-0 mt-2">Trouble signing in? Contact
                        <a href="mailto:<?= htmlspecialchars($branding['institution_contact_email']) ?>"
                            style="color: var(--udsm-blue);"><?= htmlspecialchars($branding['institution_contact_email']) ?></a>
                    </p>
                <?php endif; ?>
            </form>
        </div><!-- /.auth-card -->
    </div><!-- /.auth-wrap -->

    <script src="assets/js/auth-script.js"></script>
    <!-- <script src="assets/js/bootstrap.bundle.min.js"></script> -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>

    <?php if ($lockRemaining > 0): ?>
    <script>
    (function () {
        var secs = <?= (int)$lockRemaining ?>;
        var el   = document.getElementById('lockCountdown');
        if (!el) return;

        function fmt(s) {
            var m = Math.floor(s / 60);
            var r = s % 60;
            return m + ':' + (r < 10 ? '0' : '') + r;
        }

        function tick() {
            if (secs <= 0) {
                var alert = document.getElementById('loginAlert');
                if (alert) {
                    alert.className = 'alert alert-success text-start mb-2';
                    alert.innerHTML = '<span style="display:flex;align-items:center;gap:0.5rem;font-size:15px;">'
                        + '<i class="fas fa-check-circle"></i> Account unlocked. You may try again.</span>';
                }
                return;
            }
            el.textContent = fmt(secs);
            secs--;
            setTimeout(tick, 1000);
        }

        tick();
    })();
    </script>
    <?php endif; ?>
</body>

</html>