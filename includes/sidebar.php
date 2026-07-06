<?php
$sidebarRole = $_SESSION['user_role'] ?? 'student';
require_once __DIR__ . '/../classes/Settings.php';
$_sidebarBranding = (new Settings($conn))->get();
?>

<!-- Sidebar -->
<nav id="sidebar">
    <div class="sidebar-header d-flex align-items-center">
        <div class="logo-container me-2">
            <img src="<?= htmlspecialchars($_sidebarBranding['institution_logo_path']) ?>" alt="<?= htmlspecialchars($_sidebarBranding['institution_name']) ?> Logo" class="img-fluid rounded circle"
                style="width:45px; height:45px; object-fit:cover; border:2px solid var(--udsm-yellow);">
        </div>
        <div class="header-text">
            <h6 class="mb-0 text-white fw-bold"><?= htmlspecialchars($_sidebarBranding['institution_name']) ?></h6>
            <small class="text-warning" style="font-size:.7rem;">Complaints System</small>
        </div>
    </div>

    <div class="user-info d-flex align-items-center">
        <div class="flex-shrink-0"><i class="fas fa-user me-2"></i></div>
        <div class="flex-grow-1 ms-3">
            <?php
            $_roleLabels = [
                'admin'          => 'ADMINISTRATOR',
                'staff'          => 'STAFF',
                'student'        => 'STUDENT',
                'student_leader' => 'STUDENT REP',
            ];
            ?>
            <p class="mb-0 small fw-bold"><?= $_roleLabels[$sidebarRole] ?? strtoupper($sidebarRole) ?></p>
        </div>
    </div>

    <ul class="list-unstyled components">

        <?php if ($sidebarRole === 'admin'): ?>
            <li>
                <a href="admin_dashboard.php">
                    <i class="fas fa-chart-pie me-2"></i>
                    <span class="link-text">Overview</span>
                </a>
            </li>
            <li>
                <a href="manage_complaints.php">
                    <i class="fas fa-file-invoice me-2"></i>
                    <span class="link-text">Student Complaints</span>
                </a>
            </li>
            <li>
                <a href="user_management.php">
                    <i class="fas fa-user-shield me-2"></i>
                    <span class="link-text">User Management</span>
                </a>
            </li>
            <li>
                <a href="manage_departments.php">
                    <i class="fas fa-sitemap me-2"></i>
                    <span class="link-text">Departments</span>
                </a>
            </li>
            <li>
                <a href="manage_categories.php">
                    <i class="fas fa-tags me-2"></i>
                    <span class="link-text">Categories</span>
                </a>
            </li>
            <li>
                <a href="reports.php">
                    <i class="fas fa-file-contract me-2"></i>
                    <span class="link-text">Reports & Analytics</span>
                </a>
            </li>
            <li>
                <a href="audit_log.php">
                    <i class="fas fa-clipboard-list me-2"></i>
                    <span class="link-text">Audit Log</span>
                </a>
            </li>
            <li>
                <a href="email_queue.php">
                    <i class="fas fa-envelope-open-text me-2"></i>
                    <span class="link-text">Email Queue</span>
                </a>
            </li>
            <li>
                <a href="settings.php">
                    <i class="fas fa-cog me-2"></i>
                    <span class="link-text">Settings</span>
                </a>
            </li>

        <?php elseif ($sidebarRole === 'student_leader'): ?>
            <li>
                <a href="leader_dashboard.php">
                    <i class="fas fa-chart-pie me-2"></i>
                    <span class="link-text">Dashboard</span>
                </a>
            </li>
            <li>
                <a href="leader_complaints.php">
                    <i class="fas fa-file-invoice me-2"></i>
                    <span class="link-text">Department Complaints</span>
                </a>
            </li>
            <li>
                <a href="leader_create_complaint.php">
                    <i class="fas fa-plus-circle me-2"></i>
                    <span class="link-text">Submit Complaint</span>
                </a>
            </li>
            <li>
                <a href="leader_my_complaints.php">
                    <i class="fas fa-search me-2"></i>
                    <span class="link-text">My Complaints</span>
                </a>
            </li>

        <?php elseif ($sidebarRole === 'staff'): ?>
            <?php $_staffSidebarRank = $_staffSidebarRank ?? 0; ?>
            <li>
                <a href="staff_dashboard.php">
                    <i class="fas fa-chart-pie me-2"></i>
                    <span class="link-text">Dashboard</span>
                </a>
            </li>
            <li>
                <a href="assigned_complaints.php">
                    <i class="fas fa-comment-dots me-2"></i>
                    <span class="link-text">Assigned Complaints</span>
                </a>
            </li>
            <?php if ($_staffSidebarRank >= 2): ?>
            <li>
                <a href="department_complaints.php">
                    <i class="fas fa-building me-2"></i>
                    <span class="link-text">Department View</span>
                </a>
            </li>
            <?php endif; ?>

        <?php else: ?>
            <li>
                <a href="student_dashboard.php">
                    <i class="fas fa-chart-pie me-2"></i>
                    <span class="link-text">Dashboard</span>
                </a>
            </li>
            <li>
                <a href="create_complaint.php">
                    <i class="fas fa-plus-circle me-2"></i>
                    <span class="link-text">Submit Complaint</span>
                </a>
            </li>
            <li>
                <a href="track_complaints.php">
                    <i class="fas fa-search me-2"></i>
                    <span class="link-text">Track Complaints</span>
                </a>
            </li>

        <?php endif; ?>
    </ul>

</nav>