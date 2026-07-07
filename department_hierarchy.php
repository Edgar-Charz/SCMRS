<?php
require_once 'config/session.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

require_once "config/Database.php";
require_once "classes/User.php";
require_once "classes/Admin.php";

$db = new Database();
$conn = $db->connect();
$admin = new Admin($conn);

$departmentId = (int) ($_GET['department_id'] ?? 0);
$department = $departmentId ? $admin->getDepartmentById($departmentId) : null;

if (!$department) {
    $_SESSION['message_error'] = "Department not found.";
    header("Location: manage_departments.php");
    exit;
}

$tiers = $admin->getDepartmentStaffHierarchy($departmentId);
$totalStaff = array_sum(array_map(fn($t) => count($t['staff']), $tiers));

// Skip common honorifics so avatars aren't all the same letter (e.g. "Mr.", "Ms." both -> "M")
function staffInitial(string $username): string
{
    $honorifics = ['dr', 'mr', 'mrs', 'ms', 'miss', 'prof', 'professor', 'eng', 'rev'];
    foreach (preg_split('/\s+/', trim($username)) as $word) {
        $clean = strtolower(rtrim($word, '.'));
        if ($clean !== '' && !in_array($clean, $honorifics, true)) {
            return strtoupper($word[0]);
        }
    }
    return strtoupper(substr($username, 0, 1) ?: '?');
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin | Department Hierarchy</title>
    <link rel="shortcut icon" type="image/x-icon" href="assets/img/favicon.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/3.7.2/animate.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .tier {
            position: relative;
            padding: 24px 0;
        }
        .tier:not(:last-child)::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 50%;
            transform: translateX(-50%);
            width: 2px;
            height: 24px;
            background: repeating-linear-gradient(to bottom, #adb5bd 0 4px, transparent 4px 8px);
        }
        .tier-label {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 14px;
        }
        .tier-rank-badge {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: .8rem;
            color: #fff;
            background: linear-gradient(135deg, #1e3a5f, #2d6a9f);
            flex-shrink: 0;
        }
        .tier-title {
            font-weight: 700;
            margin: 0;
        }
        .tier-sub {
            font-size: .78rem;
            color: #6c757d;
        }
        .staff-row {
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
        }
        .staff-card {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #fff;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 10px 14px;
            min-width: 220px;
            box-shadow: 0 1px 3px rgba(0,0,0,.06);
        }
        .staff-avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: linear-gradient(135deg, #2d6a9f, #1e3a5f);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            flex-shrink: 0;
        }
        .staff-name {
            font-weight: 600;
            font-size: .88rem;
            margin: 0;
            line-height: 1.2;
        }
        .staff-email {
            font-size: .75rem;
            color: #6c757d;
        }
        .unassigned-tier .tier-rank-badge {
            background: #adb5bd;
        }
        .flow-hint {
            font-size: .8rem;
            color: #6c757d;
        }
    </style>
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

                <nav aria-label="breadcrumb" class="d-flex justify-content-between align-items-center">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item">
                            <a href="admin_dashboard.php"><i class="fas fa-building" style="color: black;"></i></a>
                        </li>
                        <li class="breadcrumb-item">
                            <a href="admin_dashboard.php" style="color:black;">Admin</a>
                        </li>
                        <li class="breadcrumb-item"><a href="manage_departments.php" style="color:black;">Manage Departments</a></li>
                        <li class="breadcrumb-item active"><?= htmlspecialchars($department['department_name']) ?> Hierarchy</li>
                    </ol>
                    <a href="manage_departments.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Departments
                    </a>
                </nav>

                <div class="container-card shadow-sm mt-3">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        <div>
                            <h5 class="mb-1 fw-bold">
                                <i class="fas fa-sitemap me-2"></i><?= htmlspecialchars($department['department_name']) ?> - Staff Hierarchy
                            </h5>
                            <span class="flow-hint"><i class="fas fa-arrow-up me-1"></i>Complaints escalate upward: staff at the bottom forward unresolved complaints to staff above them.</span>
                        </div>
                        <span class="badge bg-success"><?= $totalStaff ?> Approved Staff</span>
                    </div>

                    <?php if (empty($tiers)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fas fa-users-slash fa-2x mb-2 d-block"></i>
                            No approved staff assigned to this department yet.
                        </div>
                    <?php else: ?>
                        <div class="d-flex flex-column align-items-center">
                            <?php foreach ($tiers as $tier): ?>
                                <div class="tier w-100 text-center <?= $tier['role_rank'] === null ? 'unassigned-tier' : '' ?>">
                                    <div class="tier-label justify-content-center">
                                        <span class="tier-rank-badge">
                                            <?= $tier['role_rank'] !== null ? htmlspecialchars($tier['role_rank']) : '?' ?>
                                        </span>
                                        <div class="text-start">
                                            <p class="tier-title"><?= htmlspecialchars(implode(' / ', $tier['role_names'])) ?></p>
                                            <p class="tier-sub mb-0">
                                                <?= $tier['role_rank'] !== null ? 'Escalation rank ' . htmlspecialchars($tier['role_rank']) . (count($tier['role_names']) > 1 ? ' &middot; peer roles' : '') : 'Not part of the escalation chain until a role is assigned' ?>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="staff-row justify-content-center">
                                        <?php foreach ($tier['staff'] as $person): ?>
                                            <div class="staff-card">
                                                <div class="staff-avatar">
                                                    <?= staffInitial($person['username']) ?>
                                                </div>
                                                <div class="text-start">
                                                    <p class="staff-name"><?= htmlspecialchars($person['username']) ?></p>
                                                    <?php if (count($tier['role_names']) > 1): ?>
                                                        <span class="badge bg-light text-dark border d-block mb-1" style="font-weight:500;"><?= htmlspecialchars($person['role_name'] ?? 'No Role') ?></span>
                                                    <?php endif; ?>
                                                    <span class="staff-email"><?= htmlspecialchars($person['user_email']) ?></span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
    <script src="assets/plugins/sweetalert/sweetalert2.all.min.js"></script>
    <script src="assets/plugins/sweetalert/sweetalerts.min.js"></script>
    <script src="assets/js/script.js"></script>
</body>
</html>
