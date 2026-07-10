<?php
require_once 'config/session.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit;
} else {
    $adminId = $_SESSION['user_id'];
}

require_once "config/Database.php";
require_once "classes/User.php";
require_once "classes/Admin.php";
require_once "classes/Settings.php";
require_once "includes/csrf.php";

$db = new Database();
$conn = $db->connect();
$admin = new Admin($conn);
$settingsSvc = new Settings($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
}

// Add admin account
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_admin'])) {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '') ?: null;
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    try {
        if (!$username || !$email || !$password) {
            $_SESSION['message_error'] = "Username, email and password are required.";
        } elseif ($password !== $confirm) {
            $_SESSION['message_error'] = "Passwords do not match.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['message_error'] = "Please enter a valid email address.";
        } else {
            $settingsSvc->validatePassword($password);
            $admin->addAdminAccount($username, $email, $password, $phone);
            $admin->logActivity($adminId, 'user_created', 'user', 0, $username, "Admin account created by admin");
            $_SESSION['message'] = "Admin '{$username}' added successfully.";
        }
    } catch (Exception $e) {
        $_SESSION['message_error'] = $e->getMessage();
    }
    header("Location: settings.php#accounts");
    exit;
}

// Activate/deactivate admin account
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_admin_status'])) {
    $targetId = (int) ($_POST['user_id'] ?? 0);
    $newStatus = ($_POST['new_status'] ?? '') === 'active' ? 'active' : 'inactive';

    try {
        if ($targetId === (int) $adminId) {
            $_SESSION['message_error'] = "You cannot change the status of your own account.";
        } else {
            $admin->setAdminStatus($targetId, $newStatus);
            $admin->logActivity($adminId, 'role_changed', 'user', $targetId, '', "Admin account set to {$newStatus}");
            $_SESSION['message'] = "Admin account updated.";
        }
    } catch (Exception $e) {
        $_SESSION['message_error'] = $e->getMessage();
    }
    header("Location: settings.php#accounts");
    exit;
}

// Update email/SMTP settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_email'])) {
    try {
        $settingsSvc->updateEmail(
            trim($_POST['smtp_host'] ?? ''),
            (int) ($_POST['smtp_port'] ?? 0),
            trim($_POST['smtp_username'] ?? ''),
            $_POST['smtp_password'] ?? '',
            trim($_POST['smtp_encryption'] ?? ''),
            trim($_POST['from_email'] ?? ''),
            trim($_POST['from_name'] ?? ''),
            trim($_POST['app_url'] ?? ''),
            (int) ($_POST['email_max_attempts'] ?? 3),
            (int) ($_POST['email_batch_size'] ?? 10),
            $adminId
        );
        $admin->logActivity($adminId, 'settings_updated', 'system_settings', 0, 'Email Settings', 'Updated SMTP/email configuration');
        $_SESSION['message'] = "Email settings updated.";
    } catch (Exception $e) {
        $_SESSION['message_error'] = $e->getMessage();
    }
    header("Location: settings.php#email");
    exit;
}

// Update complaint-routing defaults
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_routing'])) {
    try {
        $settingsSvc->updateRouting(
            (int) ($_POST['sla_high_days'] ?? 2),
            (int) ($_POST['sla_medium_days'] ?? 5),
            (int) ($_POST['sla_low_days'] ?? 10),
            $adminId
        );
        $admin->logActivity($adminId, 'settings_updated', 'system_settings', 0, 'Routing Defaults', 'Updated SLA day thresholds');
        $_SESSION['message'] = "Routing defaults updated.";
    } catch (Exception $e) {
        $_SESSION['message_error'] = $e->getMessage();
    }
    header("Location: settings.php#routing");
    exit;
}

// Update branding
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_branding'])) {
    try {
        $logoPath = $settingsSvc->get()['institution_logo_path'] ?? 'assets/img/logo.png';

        if (!empty($_FILES['institution_logo']['name'])) {
            if ($_FILES['institution_logo']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception("Logo upload failed. Please try again.");
            }
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $fileType = $finfo->file($_FILES['institution_logo']['tmp_name']);
            if ($fileType !== 'image/png') {
                throw new Exception("Logo must be a PNG image.");
            }
            if ($_FILES['institution_logo']['size'] > 2 * 1024 * 1024) {
                throw new Exception("Logo must be under 2 MB.");
            }
            if (!move_uploaded_file($_FILES['institution_logo']['tmp_name'], __DIR__ . '/assets/img/logo.png')) {
                throw new Exception("Could not save the uploaded logo.");
            }
            $logoPath = 'assets/img/logo.png';
        }

        $settingsSvc->updateBranding(
            trim($_POST['institution_name'] ?? ''),
            $logoPath,
            trim($_POST['institution_contact_email'] ?? '') ?: null,
            $adminId
        );
        $admin->logActivity($adminId, 'settings_updated', 'system_settings', 0, 'Branding', 'Updated institution branding');
        $_SESSION['message'] = "Branding updated.";
    } catch (Exception $e) {
        $_SESSION['message_error'] = $e->getMessage();
    }
    header("Location: settings.php#branding");
    exit;
}

// Update security policy
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_security'])) {
    try {
        $allowedTypes = $_POST['allowed_attachment_types'] ?? [];
        if (empty($allowedTypes)) {
            throw new Exception("At least one attachment file type must be allowed.");
        }
        $settingsSvc->updateSecurity(
            (int) ($_POST['max_login_attempts'] ?? 3),
            (int) ($_POST['lockout_duration_minutes'] ?? 15),
            (int) ($_POST['max_attachment_size_mb'] ?? 5),
            $allowedTypes,
            (int) ($_POST['password_min_length'] ?? 8),
            isset($_POST['password_require_upper']) ? 1 : 0,
            isset($_POST['password_require_lower']) ? 1 : 0,
            isset($_POST['password_require_number']) ? 1 : 0,
            isset($_POST['password_require_special']) ? 1 : 0,
            $adminId
        );
        $admin->logActivity($adminId, 'settings_updated', 'system_settings', 0, 'Security Policy', 'Updated login lockout, upload and password policy settings');
        $_SESSION['message'] = "Security settings updated.";
    } catch (Exception $e) {
        $_SESSION['message_error'] = $e->getMessage();
    }
    header("Location: settings.php#security");
    exit;
}

$admins = $admin->getAdmins();
$settings = $settingsSvc->get();

$message = $error = "";
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin | Settings</title>
    <link rel="shortcut icon" type="image/x-icon" href="assets/img/favicon.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/3.7.2/animate.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
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
        <?php require_once 'includes/sidebar.php'; ?>

        <div id="content" class="w-100">

            <?php require_once 'includes/topbar.php'; ?>

            <div class="p-4">

                <nav aria-label="breadcrumb" class="d-flex justify-content-between align-items-center p-2">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item">
                            <a href="admin_dashboard.php"><i class="fa fa-users" style="color: black;"></i></a>
                        </li>
                        <li class="breadcrumb-item"><a href="admin_dashboard.php" style="color:black;">Admin</a></li>
                        <li class="breadcrumb-item active">Settings</li>
                    </ol>
                </nav>

                <ul class="nav nav-pills mb-4 p-2 bg-white shadow-sm rounded-4" id="settingsTabs" role="tablist">
                    <li class="nav-item me-2" role="presentation">
                        <button class="nav-link fw-bold" onclick="switchTab('accounts')" id="tab-accounts">
                            <i class="fas fa-user-shield me-2"></i>
                            Admin Accounts
                        </button>
                    </li>
                    <li class="nav-item me-2" role="presentation">
                        <button class="nav-link fw-bold" onclick="switchTab('email')" id="tab-email">
                            <i class="fas fa-envelope me-2"></i>
                            Email Settings
                        </button>
                    </li>
                    <li class="nav-item me-2" role="presentation">
                        <button class="nav-link fw-bold" onclick="switchTab('routing')" id="tab-routing">
                            <i class="fas fa-route me-2"></i>
                            Routing Defaults
                        </button>
                    </li>
                    <li class="nav-item me-2" role="presentation">
                        <button class="nav-link fw-bold" onclick="switchTab('branding')" id="tab-branding">
                            <i class="fas fa-palette me-2"></i>
                            Branding
                        </button>
                    </li>
                    <li class="nav-item me-2" role="presentation">
                        <button class="nav-link fw-bold" onclick="switchTab('security')" id="tab-security">
                            <i class="fas fa-shield-alt me-2"></i>
                            Security
                        </button>
                    </li>
                </ul>

                <!-- Admin Accounts -->
                <div id="accounts-section" class="management-content d-none">
                    <div class="container-card border-0 shadow-sm p-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h5 class="mb-0 fw-bold" style="color: var(--udsm-blue);">
                                <i class="fas fa-user-shield me-2"></i>Admin Accounts
                            </h5>
                            <button type="button" class="btn btn-add" data-bs-toggle="modal"
                                data-bs-target="#addAdminModal">
                                <i class="fas fa-plus"></i> Add Admin
                            </button>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th class="text-center">USERNAME</th>
                                        <th class="text-center">EMAIL</th>
                                        <th class="text-center">STATUS</th>
                                        <th class="text-center">CREATED</th>
                                        <th class="text-center">ACTION</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $n = 1;
                                    foreach ($admins as $row): ?>
                                        <tr>
                                            <td><?= $n++ ?></td>
                                            <td class="text-center"><?= htmlspecialchars($row['username']) ?></td>
                                            <td class="text-center"><?= htmlspecialchars($row['user_email']) ?></td>
                                            <td class="text-center">
                                                <?php if ($row['user_status'] === 'active'): ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center"><?= htmlspecialchars($row['created_at']) ?></td>
                                            <td class="text-center">
                                                <?php if ((int) $row['user_id'] === (int) $adminId): ?>
                                                    <span class="text-muted small">This is you</span>
                                                <?php else: ?>
                                                    <form action="settings.php" method="POST" class="d-inline">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="user_id"
                                                            value="<?= (int) $row['user_id'] ?>">
                                                        <input type="hidden" name="new_status"
                                                            value="<?= $row['user_status'] === 'active' ? 'inactive' : 'active' ?>">
                                                        <button type="submit" name="toggle_admin_status"
                                                            class="btn btn-sm <?= $row['user_status'] === 'active' ? 'btn-outline-danger' : 'btn-outline-success' ?>">
                                                            <?= $row['user_status'] === 'active' ? 'Deactivate' : 'Activate' ?>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Email Settings -->
                <div id="email-section" class="management-content d-none">
                    <div class="container-card border-0 shadow-sm p-4">
                        <h5 class="mb-3 fw-bold" style="color: var(--udsm-blue);">
                            <i class="fas fa-envelope me-2"></i>Email / SMTP Settings
                        </h5>
                        <form action="settings.php" method="POST">
                            <?= csrf_field() ?>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold small">SMTP Host</label>
                                    <input type="text" name="smtp_host" class="form-control" style="border-radius:10px;"
                                        value="<?= htmlspecialchars($settings['smtp_host']) ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold small">SMTP Port</label>
                                    <input type="number" name="smtp_port" class="form-control"
                                        style="border-radius:10px;" value="<?= (int) $settings['smtp_port'] ?>"
                                        required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold small">SMTP Username</label>
                                    <input type="text" name="smtp_username" class="form-control"
                                        style="border-radius:10px;"
                                        value="<?= htmlspecialchars($settings['smtp_username'] ?? '') ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold small">SMTP Password</label>
                                    <input type="password" name="smtp_password" class="form-control"
                                        style="border-radius:10px;"
                                        value="<?= htmlspecialchars($settings['smtp_password'] ?? '') ?>"
                                        autocomplete="new-password">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold small">Encryption</label>
                                    <select name="smtp_encryption" class="form-select" style="border-radius:10px;">
                                        <?php $enc = $settings['smtp_encryption'] ?? ''; ?>
                                        <option value="" <?= $enc === '' ? 'selected' : '' ?>>None</option>
                                        <option value="tls" <?= $enc === 'tls' ? 'selected' : '' ?>>TLS</option>
                                        <option value="ssl" <?= $enc === 'ssl' ? 'selected' : '' ?>>SSL</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold small">From Email</label>
                                    <input type="email" name="from_email" class="form-control"
                                        style="border-radius:10px;"
                                        value="<?= htmlspecialchars($settings['from_email']) ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold small">From Name</label>
                                    <input type="text" name="from_name" class="form-control" style="border-radius:10px;"
                                        value="<?= htmlspecialchars($settings['from_name']) ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold small">App URL</label>
                                    <input type="text" name="app_url" class="form-control" style="border-radius:10px;"
                                        value="<?= htmlspecialchars($settings['app_url']) ?>" required>
                                    <div class="form-text">No trailing slash. Used to build links in notification
                                        emails.</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold small">Max Send Attempts</label>
                                    <input type="number" name="email_max_attempts" class="form-control"
                                        style="border-radius:10px;" value="<?= (int) $settings['email_max_attempts'] ?>"
                                        min="1" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold small">Batch Size</label>
                                    <input type="number" name="email_batch_size" class="form-control"
                                        style="border-radius:10px;" value="<?= (int) $settings['email_batch_size'] ?>"
                                        min="1" required>
                                    <div class="form-text">Emails sent per shutdown cycle.</div>
                                </div>
                            </div>
                            <button type="submit" name="update_email" class="btn btn-primary fw-bold">
                                <i class="fas fa-save me-1"></i> Save Email Settings
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Routing Defaults -->
                <div id="routing-section" class="management-content d-none">
                    <div class="container-card border-0 shadow-sm p-4">
                        <h5 class="mb-3 fw-bold" style="color: var(--udsm-blue);">
                            <i class="fas fa-route me-2"></i>Complaint Routing Defaults
                        </h5>
                        <form action="settings.php" method="POST">
                            <?= csrf_field() ?>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label fw-bold small">High Priority - SLA (days)</label>
                                    <input type="number" name="sla_high_days" class="form-control"
                                        style="border-radius:10px;" value="<?= (int) $settings['sla_high_days'] ?>"
                                        min="1" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label fw-bold small">Medium Priority - SLA (days)</label>
                                    <input type="number" name="sla_medium_days" class="form-control"
                                        style="border-radius:10px;" value="<?= (int) $settings['sla_medium_days'] ?>"
                                        min="1" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label fw-bold small">Low Priority - SLA (days)</label>
                                    <input type="number" name="sla_low_days" class="form-control"
                                        style="border-radius:10px;" value="<?= (int) $settings['sla_low_days'] ?>"
                                        min="1" required>
                                </div>
                            </div>
                            <div class="form-text mb-3">
                                Sets the target resolution deadline whenever a complaint is assigned to staff, based on
                                its priority.
                            </div>
                            <button type="submit" name="update_routing" class="btn btn-primary fw-bold">
                                <i class="fas fa-save me-1"></i> Save Routing Defaults
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Branding -->
                <div id="branding-section" class="management-content d-none">
                    <div class="container-card border-0 shadow-sm p-4">
                        <h5 class="mb-3 fw-bold" style="color: var(--udsm-blue);">
                            <i class="fas fa-palette me-2"></i>System Branding
                        </h5>
                        <form action="settings.php" method="POST" enctype="multipart/form-data">
                            <?= csrf_field() ?>
                            <div class="mb-3">
                                <label class="form-label fw-bold small">Institution Name</label>
                                <input type="text" name="institution_name" class="form-control"
                                    style="border-radius:10px;"
                                    value="<?= htmlspecialchars($settings['institution_name']) ?>" required>
                                <div class="form-text">Shown in the sidebar, login page and notification emails.</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold small">Logo</label>
                                <div class="d-flex align-items-center gap-3 mb-2">
                                    <img src="<?= htmlspecialchars($settings['institution_logo_path'] ?? 'assets/img/logo.png') ?>?v=<?= @filemtime(__DIR__ . '/' . ($settings['institution_logo_path'] ?? 'assets/img/logo.png')) ?: 0 ?>"
                                        alt="Current logo" style="width:64px;height:64px;object-fit:contain;border:1px solid #e8ecf4;border-radius:8px;padding:4px;">
                                    <input type="file" name="institution_logo" class="form-control" accept="image/png"
                                        style="border-radius:10px;">
                                </div>
                                <div class="form-text">PNG only. Uploading a new logo replaces
                                    <code>assets/img/logo.png</code>. Leave empty to keep the current logo.</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold small">Contact Email</label>
                                <input type="email" name="institution_contact_email" class="form-control"
                                    style="border-radius:10px;"
                                    value="<?= htmlspecialchars($settings['institution_contact_email'] ?? '') ?>">
                                <div class="form-text">Shown on the login page and in notification email footers as
                                    a "need help" contact.</div>
                            </div>
                            <button type="submit" name="update_branding" class="btn btn-primary fw-bold">
                                <i class="fas fa-save me-1"></i> Save Branding
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Security -->
                <div id="security-section" class="management-content d-none">
                    <div class="container-card border-0 shadow-sm p-4">
                        <h5 class="mb-3 fw-bold" style="color: var(--udsm-blue);">
                            <i class="fas fa-shield-alt me-2"></i>Security Policy
                        </h5>
                        <form action="settings.php" method="POST">
                            <?= csrf_field() ?>

                            <h6 class="fw-bold mb-2">Login Lockout</h6>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold small">Max Failed Attempts</label>
                                    <input type="number" name="max_login_attempts" class="form-control"
                                        style="border-radius:10px;" value="<?= (int) $settings['max_login_attempts'] ?>"
                                        min="1" required>
                                    <div class="form-text">Number of wrong passwords before an account is locked.
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold small">Lockout Duration (minutes)</label>
                                    <input type="number" name="lockout_duration_minutes" class="form-control"
                                        style="border-radius:10px;"
                                        value="<?= (int) $settings['lockout_duration_minutes'] ?>" min="1" required>
                                </div>
                            </div>

                            <hr class="my-3">

                            <h6 class="fw-bold mb-2">Attachment Uploads</h6>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold small">Max File Size (MB)</label>
                                    <input type="number" name="max_attachment_size_mb" class="form-control"
                                        style="border-radius:10px;"
                                        value="<?= (int) $settings['max_attachment_size_mb'] ?>" min="1" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold small">Allowed File Types</label>
                                    <?php $allowedTypes = array_map('trim', explode(',', $settings['allowed_attachment_types'])); ?>
                                    <div class="d-flex gap-3 mt-2">
                                        <?php foreach (Settings::getAttachmentTypeCatalog() as $mime => $info): ?>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox"
                                                    name="allowed_attachment_types[]" value="<?= $mime ?>"
                                                    id="attach_<?= md5($mime) ?>"
                                                    <?= in_array($mime, $allowedTypes) ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="attach_<?= md5($mime) ?>">
                                                    <?= htmlspecialchars($info['label']) ?>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>

                            <hr class="my-3">

                            <h6 class="fw-bold mb-2">Password Policy</h6>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold small">Minimum Length</label>
                                    <input type="number" name="password_min_length" class="form-control"
                                        style="border-radius:10px;" value="<?= (int) $settings['password_min_length'] ?>"
                                        min="6" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold small d-block">Required Character Types</label>
                                    <div class="d-flex flex-wrap gap-3 mt-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox"
                                                name="password_require_upper" value="1" id="pwd_upper"
                                                <?= $settings['password_require_upper'] ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="pwd_upper">Uppercase</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox"
                                                name="password_require_lower" value="1" id="pwd_lower"
                                                <?= $settings['password_require_lower'] ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="pwd_lower">Lowercase</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox"
                                                name="password_require_number" value="1" id="pwd_number"
                                                <?= $settings['password_require_number'] ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="pwd_number">Number</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox"
                                                name="password_require_special" value="1" id="pwd_special"
                                                <?= $settings['password_require_special'] ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="pwd_special">Special
                                                character</label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <button type="submit" name="update_security" class="btn btn-primary fw-bold">
                                <i class="fas fa-save me-1"></i> Save Security Settings
                            </button>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Add Admin Modal -->
    <div class="modal fade" id="addAdminModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 5px;">
                <div class="modal-header text-white" style="background:linear-gradient(135deg,#1e3a5f,#2d6a9f);">
                    <h5 class="modal-title fw-bold text-white">
                        <i class="fas fa-plus me-2"></i>
                        ADD ADMIN
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="settings.php" method="POST"
                    onsubmit="return checkPasswords('admin_pwd', 'admin_pwd_confirm');">
                    <?= csrf_field() ?>
                    <div class="modal-body px-4 py-3">
                        <div class="mb-3">
                            <label class="form-label fw-bold small">Username <span class="text-danger">*</span></label>
                            <input type="text" name="username" class="form-control p-3 shadow-sm"
                                style="border-radius: 10px;" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold small">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control p-3 shadow-sm"
                                style="border-radius: 10px;" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold small">Phone</label>
                            <input type="text" name="phone" class="form-control p-3 shadow-sm"
                                style="border-radius: 10px;">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold small">Password <span class="text-danger">*</span></label>
                            <input type="password" name="password" id="admin_pwd" class="form-control p-3 shadow-sm"
                                style="border-radius: 10px;" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold small">Confirm Password <span
                                    class="text-danger">*</span></label>
                            <input type="password" name="confirm_password" id="admin_pwd_confirm"
                                class="form-control p-3 shadow-sm" style="border-radius: 10px;" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" name="add_admin" class="btn btn-primary fw-bold">
                            <i class="fas fa-plus me-1"></i> Add Admin
                        </button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/script.js"></script>
    <script>
        (function () {
            var VALID_TABS = ['accounts', 'email', 'routing', 'branding', 'security'];
            var LS_KEY = 'settings_active_tab';

            window.switchTab = function (tabName) {
                if (VALID_TABS.indexOf(tabName) === -1) tabName = 'accounts';
                VALID_TABS.forEach(function (name) {
                    var s = document.getElementById(name + '-section');
                    var t = document.getElementById('tab-' + name);
                    if (s) s.classList.add('d-none');
                    if (t) t.classList.remove('active');
                });
                var section = document.getElementById(tabName + '-section');
                var tab = document.getElementById('tab-' + tabName);
                if (section) section.classList.remove('d-none');
                if (tab) tab.classList.add('active');
                history.replaceState(null, '', '#' + tabName);
                try { localStorage.setItem(LS_KEY, tabName); } catch (e) { }
            };

            window.addEventListener('hashchange', function () {
                var hash = location.hash.slice(1);
                switchTab(VALID_TABS.indexOf(hash) !== -1 ? hash : 'accounts');
            });

            var hash = location.hash.slice(1);
            var saved = '';
            try { saved = localStorage.getItem(LS_KEY) || ''; } catch (e) { }
            var initTab = VALID_TABS.indexOf(hash) !== -1 ? hash
                : VALID_TABS.indexOf(saved) !== -1 ? saved
                    : 'accounts';
            switchTab(initTab);
        }());

        function checkPasswords(pwdId, confirmId) {
            var pwd = document.getElementById(pwdId).value;
            var confirm = document.getElementById(confirmId).value;
            if (pwd !== confirm) {
                alert('Passwords do not match. Please try again.');
                return false;
            }
            if (pwd.length < 8) {
                alert('Password must be at least 8 characters.');
                return false;
            }
            return true;
        }
    </script>
</body>

</html>