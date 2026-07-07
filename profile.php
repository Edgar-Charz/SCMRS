<?php
require_once 'config/session.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once "config/Database.php";
require_once "classes/User.php";
require_once "includes/csrf.php";

$db = new Database();
$conn = $db->connect();
$user = new User($conn);

$userId = (int) $_SESSION['user_id'];
$role = $_SESSION['user_role'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
}

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

// Handle Update Contact Info
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['updateContactBTN'])) {
    $newEmail = trim($_POST['new_email'] ?? '');
    $newPhone = trim($_POST['new_phone'] ?? '');
    try {
        $user->updateContact($userId, $newEmail, $newPhone);
        $_SESSION['message'] = "Contact information updated successfully.";
    } catch (Exception $e) {
        $_SESSION['message_error'] = $e->getMessage();
    }
    header("Location: profile.php");
    exit;
}

// Handle Update Password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['updatePasswordBTN'])) {
    $currentPwd = $_POST['current_password'] ?? '';
    $newPwd = $_POST['new_password'] ?? '';
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
$homeLink = match ($role) {
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
    <title>SCMRS | User Profile</title>
    <?php include 'includes/head_assets.php'; ?>
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
        <?php require_once 'includes/sidebar.php'; ?>

        <div id="content" class="w-100">

            <?php require_once 'includes/topbar.php'; ?>

            <div class="p-4">

                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item">
                            <a href="<?= ['admin' => 'admin_dashboard.php', 'staff' => 'staff_dashboard.php', 'student_leader' => 'leader_dashboard.php'][$role] ?? 'student_dashboard.php' ?>"><i class="fas fa-user" style="color: black;"></i></a>
                        </li>
                        <li class="breadcrumb-item"><a href="<?= ['admin' => 'admin_dashboard.php', 'staff' => 'staff_dashboard.php', 'student_leader' => 'leader_dashboard.php'][$role] ?? 'student_dashboard.php' ?>" style="color:black;"><?= ['admin' => 'Admin', 'staff' => 'Staff', 'student_leader' => 'Student Rep'][$role] ?? 'Student' ?></a></li>
                        <li class="breadcrumb-item active">Profile</li>
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
                        <span
                            style="background: rgba(255,255,255,0.2); color: white; padding: 4px 14px; border-radius: 20px; font-size: 0.9rem;">
                            <?= ucfirst($role) ?>
                        </span>
                        <span class="ms-2"
                            style="background: rgba(255,255,255,0.15); color: <?= ($profile['user_status'] ?? '') === 'active' ? '#86efac' : '#fca5a5' ?>; padding: 4px 14px; border-radius: 20px; font-size: 0.85rem;">
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
                        <label><i class="fas fa-phone me-2"></i>Phone</label>
                        <div class="value"><?= htmlspecialchars($profile['user_phone_number'] ?? '-') ?></div>
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

                <!-- Contact Information -->
                <div class="container-card shadow-sm mb-4">
                    <div class="d-flex align-items-center mb-3">
                        <h4> <i class="fas fa-address-card me-2"></i></h4>
                        <div class="ms-3">
                            <h4 class="mb-0 fw-bold">Contact Information</h4>
                            <small class="text-muted">Update your email address and phone number</small>
                        </div>
                    </div>

                    <form action="profile.php" method="POST" id="contactForm" novalidate>
                        <?= csrf_field() ?>
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label class="form-label fw-bold small">Current Email</label>
                                <input type="email" class="form-control p-3 shadow-sm"
                                    style="border-radius: 10px; border: 1px solid #e0e6ed; background:#f8f9fa;"
                                    value="<?= htmlspecialchars($profile['user_email'] ?? '') ?>" readonly>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label fw-bold small">
                                    New Email Address <span class="text-danger">*</span>
                                </label>
                                <input type="email" name="new_email" id="newEmail" class="form-control p-3 shadow-sm"
                                    style="border-radius: 10px; border: 1px solid #e0e6ed;"
                                    placeholder="Enter new email address"
                                    value="<?= htmlspecialchars($profile['user_email'] ?? '') ?>" required>
                                <div class="invalid-feedback" id="emailFeedback"></div>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label fw-bold small">Current Phone</label>
                                <input type="text" class="form-control p-3 shadow-sm"
                                    style="border-radius: 10px; border: 1px solid #e0e6ed; background:#f8f9fa;"
                                    value="<?= htmlspecialchars($profile['user_phone_number'] ?? '') ?>" readonly>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label fw-bold small">New Phone Number</label>
                                <input type="text" name="new_phone" id="newPhone" class="form-control p-3 shadow-sm"
                                    style="border-radius: 10px; border: 1px solid #e0e6ed;"
                                    placeholder="e.g. 0712345678"
                                    value="<?= htmlspecialchars($profile['user_phone_number'] ?? '') ?>" maxlength="10">
                                <div class="invalid-feedback" id="phoneFeedback"></div>
                                <small class="text-muted">10 digits starting with 0. Leave unchanged to keep
                                    current.</small>
                            </div>
                        </div>
                        <div class="mt-4">
                            <button type="submit" name="updateContactBTN" class="btn btn-primary p-3 fw-bold"
                                style="border-radius: 10px; min-width: 200px;">
                                <i class="fas fa-save me-2"></i>Save Contact Info
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Change Username -->
                <div class="container-card shadow-sm mb-4">
                    <h4 class="mb-3 fw-bold"><i class="fas fa-user-edit me-2"></i>Change Username</h4>
                    <form action="profile.php" method="POST">
                        <?= csrf_field() ?>
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
                        <?= csrf_field() ?>
                        <div class="row">
                            <div class="col-12 mb-3">
                                <label class="form-label fw-bold small">
                                    Current Password <span class="text-danger">*</span>
                                </label>
                                <div class="pwd-wrap">
                                    <input type="password" name="current_password" id="currentPwd"
                                        class="form-control p-3 shadow-sm"
                                        style="border-radius: 10px; border: 1px solid #e0e6ed;" required>
                                    <button type="button" class="pwd-eye" onclick="togglePwd('currentPwd',this)"
                                        tabindex="-1">
                                        <i class="fas fa-eye-slash"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-12 col-md-6 mb-3">
                                <label class="form-label fw-bold small">
                                    New Password <span class="text-danger">*</span>
                                </label>
                                <div class="pwd-wrap">
                                    <input type="password" name="new_password" id="newPwd"
                                        class="form-control p-3 shadow-sm"
                                        style="border-radius: 10px; border: 1px solid #e0e6ed;" minlength="8" required>
                                    <button type="button" class="pwd-eye" onclick="togglePwd('newPwd',this)"
                                        tabindex="-1">
                                        <i class="fas fa-eye-slash"></i>
                                    </button>
                                </div>
                                <small class="text-muted">Minimum 8 characters, uppercase, lowercase, number,
                                    symbol.</small>
                            </div>
                            <div class="col-12 col-md-6 mb-3">
                                <label class="form-label fw-bold small">
                                    Confirm New Password <span class="text-danger">*</span>
                                </label>
                                <div class="pwd-wrap">
                                    <input type="password" name="confirm_password" id="confirmPwd"
                                        class="form-control p-3 shadow-sm"
                                        style="border-radius: 10px; border: 1px solid #e0e6ed;" required>
                                    <button type="button" class="pwd-eye" onclick="togglePwd('confirmPwd',this)"
                                        tabindex="-1">
                                        <i class="fas fa-eye-slash"></i>
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

    <?php $useDataTablesJs = false; include 'includes/foot_scripts.php'; ?>
    <script src="assets/plugins/sweetalert/sweetalerts.min.js"></script>
    <script src="assets/js/script.js"></script>
    <script>
        (function () {
            const form = document.getElementById('contactForm');
            const emailEl = document.getElementById('newEmail');
            const phoneEl = document.getElementById('newPhone');
            const emailFb = document.getElementById('emailFeedback');
            const phoneFb = document.getElementById('phoneFeedback');

            function validateEmail(val) {
                if (!val) return 'Email is required.';
                if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) return 'Enter a valid email address.';
                return '';
            }

            function validatePhone(val) {
                if (!val) return '';
                if (!/^0\d{9}$/.test(val)) return 'Must be 10 digits starting with 0 (e.g. 0712345678).';
                return '';
            }

            function setValidity(el, fb, msg) {
                if (msg) {
                    el.classList.add('is-invalid');
                    el.classList.remove('is-valid');
                    fb.textContent = msg;
                } else {
                    el.classList.remove('is-invalid');
                    el.classList.add('is-valid');
                    fb.textContent = '';
                }
            }

            emailEl.addEventListener('input', function () {
                setValidity(emailEl, emailFb, validateEmail(this.value.trim()));
            });

            phoneEl.addEventListener('input', function () {
                setValidity(phoneEl, phoneFb, validatePhone(this.value.trim()));
            });

            form.addEventListener('submit', function (e) {
                const eMsg = validateEmail(emailEl.value.trim());
                const pMsg = validatePhone(phoneEl.value.trim());
                setValidity(emailEl, emailFb, eMsg);
                setValidity(phoneEl, phoneFb, pMsg);
                if (eMsg || pMsg) {
                    e.preventDefault();
                }
            });
        })();

    </script>
</body>

</html>
