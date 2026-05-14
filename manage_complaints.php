<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$adminId = $_SESSION['user_id'];

require_once "config/Database.php";
require_once "classes/User.php";
require_once "classes/Admin.php";

$db    = new Database();
$conn  = $db->connect();
$admin = new Admin($conn);

$message = $error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action      = $_POST['action'] ?? '';
    $complaintId = isset($_POST['complaint_id']) ? (int) $_POST['complaint_id'] : 0;

    if ($action === 'assign' && $complaintId > 0) {
        $staffId  = trim($_POST['staff_id'] ?? '');
        $priority = in_array($_POST['priority'] ?? '', ['low', 'medium', 'high']) ? $_POST['priority'] : 'medium';
        $note     = trim($_POST['note'] ?? '');

        if (empty($staffId)) {
            $error = "Please select a staff member.";
        } else {
            try {
                $admin->assignComplaint($complaintId, $staffId, $priority, $note);
                $_SESSION['message'] = "Complaint #$complaintId assigned successfully.";
                header("Location: manage_complaints.php");
                exit;
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
    } elseif ($action === 'delete' && $complaintId > 0) {
        try {
            $admin->deleteComplaint($complaintId);
            $_SESSION['message'] = "Complaint #$complaintId deleted successfully.";
            header("Location: manage_complaints.php");
            exit;
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

$complaints = $admin->getComplaints();
$staffList  = $admin->getApprovedStaff();

function statusBadge($status)
{
    $map = [
        'pending'                   => ['bg-warning text-dark', 'Pending'],
        'in_progress'               => ['bg-info text-white',   'In Progress'],
        'awaiting_student_response' => ['bg-primary text-white', 'Awaiting Response'],
        'resolved'                  => ['bg-success text-white', 'Resolved'],
        'rejected'                  => ['bg-danger text-white',  'Rejected'],
    ];
    [$class, $label] = $map[$status] ?? ['bg-secondary text-white', ucfirst(str_replace('_', ' ', $status))];
    return "<span class=\"badge $class\">$label</span>";
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Complaints | Admin</title>
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
                    <p class="mb-0 small fw-bold">ADMIN</p>
                </div>
            </div>

            <ul class="list-unstyled components">
                <li>
                    <a href="admin_dashboard.php" title="Dashboard">
                        <i class="fas fa-chart-pie me-2"></i>
                        <span class="link-text">Overview</span>
                    </a>
                </li>
                <li class="active">
                    <a href="manage_complaints.php" title="Manage Complaints">
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
                    <a href="manage_departments.php" title="Departments">
                        <i class="fas fa-sitemap me-2"></i>
                        <span class="link-text">Departments</span>
                    </a>
                </li>
                <li>
                    <a href="manage_categories.php" title="Categories">
                        <i class="fas fa-tags me-2"></i>
                        <span class="link-text">Categories</span>
                    </a>
                </li>
                <li>
                    <a href="reports.php" title="Reports">
                        <i class="fas fa-file-contract me-2"></i>
                        <span class="link-text">Reports</span>
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

            <!-- Toast Notifications -->
            <div aria-live="polite" aria-atomic="true"
                class="position-fixed top-0 start-50 translate-middle-x p-3" style="z-index: 1100;">
                <?php if (!empty($message) || !empty($error)):
                    $type = !empty($message) ? 'success' : 'danger';
                    $text = !empty($message) ? $message : $error;
                    $icon = ($type === 'success') ? 'fa-check-circle' : 'fa-exclamation-circle';
                ?>
                    <div class="toast show align-items-center text-white bg-<?= $type ?> border-0"
                        role="alert" aria-live="assertive" aria-atomic="true">
                        <div class="d-flex">
                            <div class="toast-body">
                                <i class="fas <?= $icon ?> me-2"></i>
                                <?= htmlspecialchars($text) ?>
                            </div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto"
                                data-bs-dismiss="toast" aria-label="Close"></button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="p-4">

                <nav aria-label="breadcrumb" class="d-flex justify-content-between align-items-center mb-3">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item">
                            <a href="admin_dashboard.php"><i class="fas fa-home" style="color: black;"></i></a>
                        </li>
                        <li class="breadcrumb-item active">Manage Complaints</li>
                    </ol>
                </nav>

                <div class="container-card shadow-sm">
                    <h4 class="mb-3 fw-bold"><i class="fas fa-file-invoice me-2"></i>Student Complaints</h4>

                    <div class="table-responsive">
                        <table class="table table-striped" id="complaintsTable">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>TITLE</th>
                                    <th class="text-center">STUDENT</th>
                                    <th class="text-center">CATEGORY</th>
                                    <th class="text-center">PRIORITY</th>
                                    <th class="text-center">STATUS</th>
                                    <th class="text-center">DATE</th>
                                    <th class="text-center">ACTION</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($complaints)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-4">No complaints found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($complaints as $c): ?>
                                        <?php
                                        $studentName = $c['is_anonymous']
                                            ? '<em class="text-muted">Anonymous</em>'
                                            : htmlspecialchars($c['student_name']);
                                        $priorityMap = [
                                            'low'    => 'bg-success',
                                            'medium' => 'bg-warning text-dark',
                                            'high'   => 'bg-danger',
                                        ];
                                        $priClass = $priorityMap[$c['priority']] ?? 'bg-secondary';
                                        ?>
                                        <tr>
                                            <td><?= $c['complaint_id'] ?></td>
                                            <td><?= htmlspecialchars($c['complaint_title']) ?></td>
                                            <td class="text-center"><?= $studentName ?></td>
                                            <td class="text-center"><?= htmlspecialchars($c['category_name']) ?></td>
                                            <td class="text-center">
                                                <span class="badge <?= $priClass ?>">
                                                    <?= ucfirst($c['priority']) ?>
                                                </span>
                                            </td>
                                            <td class="text-center"><?= statusBadge($c['complaint_status']) ?></td>
                                            <td class="text-center">
                                                <?= date('d M Y', strtotime($c['created_at'])) ?>
                                            </td>
                                            <td class="text-center">
                                                <div class="d-flex justify-content-center gap-1">
                                                    <a href="complaint_details.php?id=<?= $c['complaint_id'] ?>"
                                                        class="btn btn-status btn-outline-secondary" title="View Details">
                                                        <i class="fas fa-eye text-dark"></i>
                                                    </a>
                                                    <a href="respond_complaint.php?id=<?= $c['complaint_id'] ?>"
                                                        class="btn btn-status btn-outline-secondary" title="Respond">
                                                        <i class="fas fa-reply text-dark"></i>
                                                    </a>
                                                    <button type="button"
                                                        class="btn btn-status btn-outline-secondary btn-assign"
                                                        data-bs-toggle="modal" data-bs-target="#assignModal"
                                                        data-complaint-id="<?= $c['complaint_id'] ?>"
                                                        title="Assign to Staff">
                                                        <i class="fas fa-user-tag text-dark"></i>
                                                    </button>
                                                    <button type="button"
                                                        class="btn btn-status btn-outline-secondary"
                                                        onclick="confirmDelete(<?= $c['complaint_id'] ?>)"
                                                        title="Delete">
                                                        <i class="fas fa-trash text-dark"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div><!-- /p-4 -->

        </div><!-- /content -->

    </div><!-- /d-flex -->

    <!-- Assign Staff Modal -->
    <div class="modal fade" id="assignModal" tabindex="-1" aria-labelledby="assignModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content shadow-lg rounded-3">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold" id="assignModalLabel">
                        <i class="fas fa-user-tag me-2"></i>Assign Complaint to Staff
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="manage_complaints.php">
                    <input type="hidden" name="action" value="assign">
                    <input type="hidden" name="complaint_id" id="assignComplaintId">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Select Staff <span class="text-danger">*</span></label>
                            <select name="staff_id" class="form-select" required>
                                <option value="">-- Choose Staff --</option>
                                <?php foreach ($staffList as $staff): ?>
                                    <option value="<?= $staff['staff_id'] ?>">
                                        <?= htmlspecialchars($staff['username']) ?>
                                        <?= $staff['department_name'] ? ' — ' . htmlspecialchars($staff['department_name']) : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (empty($staffList)): ?>
                                <small class="text-danger">No approved staff available. Approve staff first.</small>
                            <?php endif; ?>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Priority</label>
                            <select name="priority" class="form-select">
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Note <span class="text-muted fw-normal">(optional)</span></label>
                            <textarea name="note" class="form-control" rows="3"
                                placeholder="Add instructions or details for the staff member..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-user-tag me-1"></i>Assign
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Hidden delete form -->
    <form id="deleteForm" method="POST" action="manage_complaints.php">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="complaint_id" id="deleteComplaintId">
    </form>

    <script src="assets/js/jquery-3.6.0.min.js"></script>
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/jquery.dataTables.min.js"></script>
    <script src="assets/js/dataTables.bootstrap4.min.js"></script>
    <script src="assets/plugins/sweetalert/sweetalert2.all.min.js"></script>
    <script src="assets/plugins/sweetalert/sweetalerts.min.js"></script>
    <script src="assets/js/script.js"></script>

    <script>
        // Populate assign modal with the correct complaint ID
        document.getElementById('assignModal').addEventListener('show.bs.modal', function (event) {
            var btn = event.relatedTarget;
            document.getElementById('assignComplaintId').value = btn.getAttribute('data-complaint-id');
        });

        // Delete with SweetAlert confirmation
        function confirmDelete(complaintId) {
            Swal.fire({
                icon: 'warning',
                title: 'Delete Complaint?',
                text: 'Complaint #' + complaintId + ' and all its attachments will be permanently deleted.',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete!',
                cancelButtonText: 'Cancel'
            }).then(function (result) {
                if (result.isConfirmed) {
                    document.getElementById('deleteComplaintId').value = complaintId;
                    document.getElementById('deleteForm').submit();
                }
            });
        }

        // DataTable init
        $(document).ready(function () {
            if ($("#complaintsTable").length > 0 && !$.fn.DataTable.isDataTable("#complaintsTable")) {
                $("#complaintsTable").DataTable({
                    destroy: true,
                    bFilter: true,
                    sDom: "fBtlpi",
                    pagingType: "numbers",
                    ordering: true,
                    language: {
                        search: " ",
                        sLengthMenu: "_MENU_",
                        searchPlaceholder: "Search complaints...",
                        info: "_START_ - _END_ of _TOTAL_ items"
                    },
                    initComplete: function () {
                        $(".dataTables_filter").appendTo(".search-input");
                    }
                });
            }
        });
    </script>

</body>
</html>
