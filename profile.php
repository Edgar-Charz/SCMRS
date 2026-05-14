<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once "config/Database.php";
require_once "classes/User.php";

$db   = new Database();
$conn = $db->connect();
$user = new User($conn);

$userId = (int)$_SESSION['user_id'];
$role   = $_SESSION['user_role'];

// Handle Update Username
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['updateUsernameBTN'])) {
    $newUsername = trim($_POST['new_username'] ?? '');
    try {
        $user->updateUsername($userId, $newUsername);
        $_SESSION['username'] = $newUsername;
        $_SESSION['message'] = "Username updated successfully.";
    } catch (Exception $e) {
        $_SESSION['message_error'] = $e->getMessage();
    }
    header("Location: profile.php");
    exit;
}

// Handle Update Password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['updatePasswordBTN'])) {
    $currentPwd = $_POST['current_password'] ?? '';
    $newPwd     = $_POST['new_password']     ?? '';
    $confirmPwd = $_POST['confirm_password'] ?? '';
    try {
        $user->updatePassword($userId, $currentPwd, $newPwd, $confirmPwd);
        $_SESSION['message'] = "Password changed successfully.";
    } catch (Exception $e) {
        $_SESSION['message_error'] = $e->getMessage();
    }
    header("Location: profile.php");
    exit;
}

// Load full profile
$profile = $user->getFullProfile($userId, $role);

// Determine back-link
$homeLink = match($role) {
    'admin' => 'admin_dashboard.php',
    'staff' => 'staff_dashboard.php',
    default => 'student_dashboard.php',
};
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SCMRS - User Profile</title>
    <link rel="shortcut icon" type="image/x-icon" href="assets/img/favicon.png">
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/animate.css">
    <link rel="stylesheet" href="assets/plugins/select2/css/select2.min.css">
    <link rel="stylesheet" href="assets/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="assets/plugins/fontawesome/css/fontawesome.min.css">
    <link rel="stylesheet" href="assets/plugins/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>
<?php require_once 'includes/flash_toast.php'; ?>

    <div id="loader">
        <div class="loader-content">
            <img src="assets/img/logo.png" alt="UDSM" class="loader-logo">
            <div class="spinner"></div>
            <p class="loader-text">Please wait...</p>
        </div>
    </div>

    <div class="d-flex">

        <!-- Sidebar -->
        <nav id="sidebar">
            <div class="sidebar-header d-flex align-items-center">
                <div class="logo-container me-2">
                    <img src="assets/img/logo.png" alt="UDSM Logo" class="img-fluid rounded circle"
                        style="width: 45px; height: 45px; object-fit: cover; border: 2px solid var(--udsm-yellow);">
                </div>
                <div class="header-text">
                    <h6 class="mb-0 text-white fw-bold">UDSM</h6>
                    <small class="text-warning" style="font-size: 0.7rem;">Complaints System</small>
                </div>
            </div>

            <div class="user-info d-flex align-items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-user me-2"></i>
                </div>
                <div class="flex-grow-1 ms-3">
                    <p class="mb-0 small fw-bold"><?= strtoupper($role); ?></p>
                </div>
            </div>

            <ul class="list-unstyled components">
                <li class="active">
                    <a href="<?= $homeLink ?>">
                        <i class="fas fa-home me-2"></i>
                        <span class="link-text">Back to Home</span>
                    </a>
                </li>
            </ul>
            <div class="sidebar-footer">
                <a href="logout.php" title="Sign Out">
                    <i class="fas fa-sign-out-alt me-2"></i>
                    <span class="link-text">Sign Out</span>
                </a>
            </div>
        </nav>

        <div id="content" class="w-100">

            <?php require_once 'includes/topbar.php'; ?>

            <div class="p-4">

                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item">
                            <a href="#"><i class="fas fa-user" style="color: black;"></i></a>
                        </li>
                        <li class="breadcrumb-item active">User Profile</li>
                    </ol>
                </nav>

                <!-- Profile Header -->
                <div class="profile-header">
                    <div class="avatar">
                        <?php if ($role === 'student'): ?>
                            <i class="fas fa-user-graduate"></i>
                        <?php elseif ($role === 'staff'): ?>
                            <i class="fas fa-chalkboard-teacher"></i>
                        <?php else: ?>
                            <i class="fas fa-user-shield"></i>
                        <?php endif; ?>
                    </div>
                    <h2><?= htmlspecialchars($profile['username'] ?? $_SESSION['username']) ?></h2>
                    <p>
                        <span style="background: rgba(255,255,255,0.2); color: white; padding: 4px 14px; border-radius: 20px; font-size: 0.9rem;">
                            <?= ucfirst($role) ?>
                        </span>
                        <span class="ms-2" style="background: rgba(255,255,255,0.15); color: <?= ($profile['user_status'] ?? '') === 'active' ? '#86efac' : '#fca5a5' ?>; padding: 4px 14px; border-radius: 20px; font-size: 0.85rem;">
                            <?= ucfirst($profile['user_status'] ?? 'active') ?>
                        </span>
                    </p>
                </div>

                <!-- Info Grid -->
                <div class="info-grid">
                    <div class="info-item">
                        <label><i class="fas fa-id-badge me-2"></i>Username</label>
                        <div class="value"><?= htmlspecialchars($profile['username'] ?? '-') ?></div>
                    </div>
                    <div class="info-item">
                        <label><i class="fas fa-envelope me-2"></i>Email</label>
                        <div class="value"><?= htmlspecialchars($profile['user_email'] ?? '-') ?></div>
                    </div>
                    <div class="info-item">
                        <label><i class="fas fa-user-tag me-2"></i>Role</label>
                        <div class="value"><?= ucfirst($role) ?></div>
                    </div>
                    <div class="info-item">
                        <label><i class="fas fa-calendar-alt me-2"></i>Member Since</label>
                        <div class="value">
                            <?= !empty($profile['created_at']) ? date('d M Y', strtotime($profile['created_at'])) : '-' ?>
                        </div>
                    </div>

                    <?php if ($role === 'student'): ?>
                    <div class="info-item">
                        <label><i class="fas fa-id-card me-2"></i>Registration No.</label>
                        <div class="value"><?= htmlspecialchars($profile['student_registration_number'] ?? '-') ?></div>
                    </div>
                    <div class="info-item">
                        <label><i class="fas fa-university me-2"></i>College</label>
                        <div class="value"><?= htmlspecialchars($profile['college_name'] ?? '-') ?></div>
                    </div>
                    <?php if (!empty($profile['student_program'])): ?>
                    <div class="info-item">
                        <label><i class="fas fa-book me-2"></i>Program</label>
                        <div class="value"><?= htmlspecialchars($profile['student_program']) ?></div>
                    </div>
                    <?php endif; ?>

                    <?php elseif ($role === 'staff'): ?>
                    <div class="info-item">
                        <label><i class="fas fa-sitemap me-2"></i>Department</label>
                        <div class="value"><?= htmlspecialchars($profile['department_name'] ?? 'Not assigned') ?></div>
                    </div>
                    <div class="info-item">
                        <label><i class="fas fa-briefcase me-2"></i>Staff Role</label>
                        <div class="value"><?= htmlspecialchars($profile['role_name'] ?? 'Officer') ?></div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Change Username -->
                <div class="container-card shadow-sm mb-4">
                    <h4 class="mb-3 fw-bold"><i class="fas fa-user-edit me-2"></i>Change Username</h4>
                    <form action="profile.php" method="POST">
                        <div class="row">
                            <div class="col-12 col-md-6 mb-3">
                                <label class="form-label fw-bold small">Current Username</label>
                                <input type="text" class="form-control p-3 shadow-sm"
                                    style="border-radius: 10px; border: 1px solid #e0e6ed;"
                                    value="<?= htmlspecialchars($profile['username'] ?? '') ?>" readonly>
                            </div>
                            <div class="col-12 col-md-6 mb-3">
                                <label class="form-label fw-bold small">
                                    New Username <span class="text-danger">*</span>
                                </label>
                                <input type="text" name="new_username" class="form-control p-3 shadow-sm"
                                    style="border-radius: 10px; border: 1px solid #e0e6ed;"
                                    placeholder="Enter new username" required>
                            </div>
                        </div>
                        <button type="submit" name="updateUsernameBTN" class="btn btn-primary p-3 fw-bold"
                            style="border-radius: 10px;">
                            <i class="fas fa-save me-2"></i>Update Username
                        </button>
                    </form>
                </div>

                <!-- Change Password -->
                <div class="container-card shadow-sm mb-4">
                    <h4 class="mb-3 fw-bold"><i class="fas fa-lock me-2"></i>Change Password</h4>
                    <form action="profile.php" method="POST">
                        <div class="row">
                            <div class="col-12 mb-3">
                                <label class="form-label fw-bold small">
                                    Current Password <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <input type="password" name="current_password" id="currentPwd"
                                        class="form-control p-3 shadow-sm"
                                        style="border-radius: 10px 0 0 10px; border: 1px solid #e0e6ed;" required>
                                    <button class="btn btn-outline-secondary" type="button"
                                        onclick="togglePwd('currentPwd', this)"
                                        style="border-radius: 0 10px 10px 0;">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-12 col-md-6 mb-3">
                                <label class="form-label fw-bold small">
                                    New Password <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <input type="password" name="new_password" id="newPwd"
                                        class="form-control p-3 shadow-sm"
                                        style="border-radius: 10px 0 0 10px; border: 1px solid #e0e6ed;"
                                        minlength="8" required>
                                    <button class="btn btn-outline-secondary" type="button"
                                        onclick="togglePwd('newPwd', this)"
                                        style="border-radius: 0 10px 10px 0;">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <small class="text-muted">Minimum 8 characters.</small>
                            </div>
                            <div class="col-12 col-md-6 mb-3">
                                <label class="form-label fw-bold small">
                                    Confirm New Password <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <input type="password" name="confirm_password" id="confirmPwd"
                                        class="form-control p-3 shadow-sm"
                                        style="border-radius: 10px 0 0 10px; border: 1px solid #e0e6ed;" required>
                                    <button class="btn btn-outline-secondary" type="button"
                                        onclick="togglePwd('confirmPwd', this)"
                                        style="border-radius: 0 10px 10px 0;">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <button type="submit" name="updatePasswordBTN" class="btn btn-primary p-3 fw-bold"
                            style="border-radius: 10px;">
                            <i class="fas fa-key me-2"></i>Change Password
                        </button>
                    </form>
                </div>

            </div>
        </div>
    </div>

    <script src="assets/js/jquery-3.6.0.min.js"></script>
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/plugins/sweetalert/sweetalert2.all.min.js"></script>
    <script src="assets/plugins/sweetalert/sweetalerts.min.js"></script>
    <script src="assets/js/script.js"></script>
    <script>
        function togglePwd(inputId, btn) {
            const input = document.getElementById(inputId);
            const icon  = btn.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }
    </script>
</body>
</html>
