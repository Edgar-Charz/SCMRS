<?php
require_once 'config/session.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$adminId = $_SESSION['user_id'];

require_once "config/Database.php";
require_once "classes/User.php";
require_once "classes/Admin.php";
require_once "includes/csrf.php";

$db = new Database();
$conn = $db->connect();
$admin = new Admin($conn);

$message = $error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action      = $_POST['action'] ?? '';
    $complaintId = isset($_POST['complaint_id']) ? (int) $_POST['complaint_id'] : 0;

    if ($action === 'assign' && $complaintId > 0) {
        $staffId  = trim($_POST['staff_id'] ?? '');
        $priority = in_array($_POST['priority'] ?? '', ['low', 'medium', 'high']) ? $_POST['priority'] : 'medium';
        $note     = trim($_POST['note'] ?? '');

        $target = $admin->getComplaintById($complaintId);
        if ($target && in_array($target['complaint_status'], [STATUS_RESOLVED, STATUS_REJECTED], true)) {
            $error = "Complaint #$complaintId is already closed and cannot be reassigned.";
        } elseif (empty($staffId)) {
            $error = "Please select a staff member.";
        } else {
            try {
                $admin->assignComplaint($complaintId, $staffId, $priority, $note);
                $admin->logActivity($adminId, 'complaint_assigned', 'complaint', $complaintId, "Complaint #$complaintId", "Assigned to staff ID $staffId (priority: $priority)");
                $_SESSION['message'] = "Complaint #$complaintId assigned successfully.";
                header("Location: manage_complaints.php");
                exit;
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }

    } elseif ($action === 'delete' && $complaintId > 0) {
        $reason = trim($_POST['delete_reason'] ?? '');
        try {
            $admin->deleteComplaint($complaintId, $reason);
            $admin->logActivity($adminId, 'complaint_deleted', 'complaint', $complaintId, "Complaint #$complaintId", "Deleted. Reason: $reason");
            $_SESSION['message'] = "Complaint #$complaintId deleted successfully.";
            header("Location: manage_complaints.php");
            exit;
        } catch (Exception $e) {
            $error = $e->getMessage();
        }

    } elseif ($action === 'bulk_assign') {
        $ids      = array_values(array_unique(array_filter(array_map('intval', $_POST['complaint_ids'] ?? []))));
        $staffId  = trim($_POST['staff_id'] ?? '');
        $priority = in_array($_POST['priority'] ?? '', ['low', 'medium', 'high']) ? $_POST['priority'] : 'medium';
        $note     = trim($_POST['note'] ?? '');

        if (empty($ids)) {
            $error = "No complaints selected.";
        } elseif (empty($staffId)) {
            $error = "Please select a staff member.";
        } else {
            $assigned = 0;
            $skipped  = [];
            foreach ($ids as $id) {
                try {
                    $admin->assignComplaint($id, $staffId, $priority, $note);
                    $assigned++;
                } catch (Exception $e) {
                    $skipped[] = "#$id";
                }
            }
            $msg = "$assigned complaint(s) assigned successfully.";
            if ($skipped) $msg .= " Skipped (already closed): " . implode(', ', $skipped) . ".";
            $admin->logActivity($adminId, 'complaint_assigned', 'complaint', 0, "Bulk assign", "Assigned $assigned complaint(s) to staff ID $staffId (priority: $priority)");
            $_SESSION['message'] = $msg;
            header("Location: manage_complaints.php");
            exit;
        }

    } elseif ($action === 'bulk_delete') {
        $ids    = array_values(array_unique(array_filter(array_map('intval', $_POST['complaint_ids'] ?? []))));
        $reason = trim($_POST['delete_reason'] ?? '');

        if (empty($ids)) {
            $error = "No complaints selected.";
        } elseif (empty($reason)) {
            $error = "Please provide a reason for deletion.";
        } else {
            $deleted = 0;
            $skipped = [];
            foreach ($ids as $id) {
                try {
                    $admin->deleteComplaint($id, $reason);
                    $deleted++;
                } catch (Exception $e) {
                    $skipped[] = "#$id";
                }
            }
            $msg = "$deleted complaint(s) deleted.";
            if ($skipped) $msg .= " Skipped (not pending): " . implode(', ', $skipped) . ".";
            $admin->logActivity($adminId, 'complaint_deleted', 'complaint', 0, "Bulk delete", "Deleted $deleted complaint(s). Reason: $reason");
            $_SESSION['message'] = $msg;
            header("Location: manage_complaints.php");
            exit;
        }
    }
}

if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

// Fire overdue notifications for any newly-past-deadline complaints
try {
    $admin->notifyOverdueComplaints();
} catch (Throwable $e) {
    error_log('[SCMRS] notifyOverdueComplaints: ' . $e->getMessage());
}

try {
    $complaints = $admin->getComplaints();
} catch (Throwable $e) {
    error_log('[SCMRS] getComplaints: ' . $e->getMessage());
    $complaints = [];
}

try {
    $staffList = $admin->getApprovedStaff();
} catch (Throwable $e) {
    error_log('[SCMRS] getApprovedStaff: ' . $e->getMessage());
    $staffList = [];
}

try {
    $departments = $admin->getAllDepartments();
} catch (Throwable $e) {
    error_log('[SCMRS] getAllDepartments: ' . $e->getMessage());
    $departments = [];
}

$now = new DateTime();

// Build unique filter lists from complaint data
$filterCategories = array_values(array_filter(array_unique(array_column($complaints, 'category_name'))));
sort($filterCategories);
$filterDepartments = array_values(array_filter(array_unique(array_column($complaints, 'department_name'))));
sort($filterDepartments);

function statusBadge($status)
{
    $map = [
        STATUS_PENDING => ['bg-warning text-dark',  'Pending'],
        STATUS_IN_PROGRESS => ['bg-info text-white',    'In Progress'],
        STATUS_AWAITING_RESPONSE => ['bg-primary text-white', 'Awaiting Response'],
        STATUS_RESOLVED => ['bg-success text-white', 'Resolved'],
        STATUS_REJECTED => ['bg-danger text-white',  'Rejected'],
        STATUS_REOPENED => ['bg-warning text-white',  'Reopened'],
        'on_hold'       => ['bg-secondary text-white', 'On Hold'],
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
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        #bulkToolbar {
            transition: opacity .15s;
        }
        .row-check { cursor: pointer; width: 16px; height: 16px; }
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

            <!-- Toast Notifications -->
            <div aria-live="polite" aria-atomic="true" class="position-fixed top-0 start-50 translate-middle-x p-3"
                style="z-index: 1100; pointer-events: none;">
                <?php if (!empty($message) || !empty($error)):
                    $type = !empty($message) ? 'success' : 'danger';
                    $text = !empty($message) ? $message : $error;
                    $icon = ($type === 'success') ? 'fa-check-circle' : 'fa-exclamation-circle';
                    ?>
                    <div class="toast show align-items-center text-white bg-<?= $type ?> border-0" role="alert"
                        aria-live="assertive" aria-atomic="true" style="pointer-events: auto;">
                        <div class="d-flex">
                            <div class="toast-body">
                                <i class="fas <?= $icon ?> me-2"></i>
                                <?= htmlspecialchars($text) ?>
                            </div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"
                                aria-label="Close"></button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="p-4">

                <nav aria-label="breadcrumb" class="d-flex justify-content-between align-items-center mb-3">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item">
                            <a href="admin_dashboard.php"><i class="fas fa-file-invoice" style="color: black;"></i></a>
                        </li>
                        <li class="breadcrumb-item"><a href="admin_dashboard.php" style="color:black;">Admin</a></li>
                        <li class="breadcrumb-item active">Manage Complaints</li>
                    </ol>
                </nav>

                <!-- Bulk action toolbar (hidden until ≥1 checkbox ticked) -->
                <div id="bulkToolbar" class="d-none alert alert-secondary py-2 px-3 mb-3 d-flex align-items-center gap-2 flex-wrap">
                    <span class="fw-semibold me-2" id="selectedCount">0 selected</span>
                    <button type="button" class="btn btn-sm btn-primary"
                            data-bs-toggle="modal" data-bs-target="#bulkAssignModal">
                        <i class="fas fa-user-tag me-1"></i> Assign Selected
                    </button>
                    <button type="button" class="btn btn-sm btn-danger"
                            data-bs-toggle="modal" data-bs-target="#bulkDeleteModal">
                        <i class="fas fa-trash me-1"></i> Delete Selected
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary ms-auto" id="clearSelectionBtn">
                        <i class="fas fa-times me-1"></i> Clear
                    </button>
                </div>

                <!-- Filter Card -->
                <div class="container-card shadow-sm">
                    <div class="row g-2 align-items-end">
                        <div class="col-12 col-sm-6 col-md-4 col-lg-2">
                            <label class="form-label small fw-semibold mb-1">Status</label>
                            <select id="filterStatus" class="form-select form-select-sm">
                                <option value="">All Statuses</option>
                                <option value="pending">Pending</option>
                                <option value="in_progress">In Progress</option>
                                <option value="awaiting_student_response">Awaiting Response</option>
                                <option value="resolved">Resolved</option>
                                <option value="rejected">Rejected</option>
                                <option value="reopened">Reopened</option>
                                <option value="on_hold">On Hold</option>
                                <option value="overdue">Overdue</option>
                            </select>
                        </div>
                        <div class="col-12 col-sm-6 col-md-4 col-lg-2">
                            <label class="form-label small fw-semibold mb-1">Priority</label>
                            <select id="filterPriority" class="form-select form-select-sm">
                                <option value="">All Priorities</option>
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                            </select>
                        </div>
                        <div class="col-12 col-sm-6 col-md-4 col-lg-2">
                            <label class="form-label small fw-semibold mb-1">Category</label>
                            <select id="filterCategory" class="form-select form-select-sm">
                                <option value="">All Categories</option>
                                <?php foreach ($filterCategories as $cat): ?>
                                    <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-sm-6 col-md-4 col-lg-2">
                            <label class="form-label small fw-semibold mb-1">Department</label>
                            <select id="filterDept" class="form-select form-select-sm">
                                <option value="">All Departments</option>
                                <?php foreach ($filterDepartments as $dept): ?>
                                    <option value="<?= htmlspecialchars($dept) ?>"><?= htmlspecialchars($dept) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-sm-6 col-md-4 col-lg-2">
                            <label class="form-label small fw-semibold mb-1">Date From</label>
                            <input type="date" id="filterDateFrom" class="form-control form-control-sm">
                        </div>
                        <div class="col-12 col-sm-6 col-md-4 col-lg-2">
                            <label class="form-label small fw-semibold mb-1">Date To</label>
                            <input type="date" id="filterDateTo" class="form-control form-control-sm">
                        </div>
                        <div class="col-auto d-flex align-items-end">
                            <button type="button" id="clearFilters" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-times me-1"></i>Clear
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Table Card -->
                <div class="container-card shadow-sm">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0 fw-bold"><i class="fas fa-file-invoice me-2"></i>Student Complaints</h4>
                        <div class="search-input"></div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-striped" id="complaintsTable">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:36px;">
                                        <input type="checkbox" id="selectAll" class="row-check" title="Select all visible">
                                    </th>
                                    <th>#</th>
                                    <th>TITLE</th>
                                    <th class="text-center">STUDENT</th>
                                    <th class="text-center">CATEGORY</th>
                                    <th class="text-center">PRIORITY</th>
                                    <th class="text-center">STATUS</th>
                                    <th class="text-center">ASSIGNED TO</th>
                                    <th class="text-center">DATE</th>
                                    <th class="text-center">ACTION</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($complaints)): ?>
                                    <tr>
                                        <td colspan="10" class="text-center text-muted py-4">No complaints found.</td>
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

                                        $isClosed = in_array($c['complaint_status'], [STATUS_RESOLVED, STATUS_REJECTED], true);
                                        $isOverdue = !$isClosed
                                            && !empty($c['target_resolution_date'])
                                            && new DateTime($c['target_resolution_date']) < $now;
                                        ?>
                                        <tr data-status="<?= htmlspecialchars($c['complaint_status']) ?>"
                                            data-priority="<?= htmlspecialchars($c['priority']) ?>"
                                            data-category="<?= htmlspecialchars($c['category_name']) ?>"
                                            data-dept="<?= htmlspecialchars($c['department_name'] ?? '') ?>"
                                            data-overdue="<?= $isOverdue ? '1' : '0' ?>"
                                            data-date="<?= date('Y-m-d', strtotime($c['created_at'])) ?>"
                                            style="cursor:pointer;"
                                            onclick="window.location.href='complaint_details.php?id=<?= $c['complaint_id'] ?>'">
                                            <td onclick="event.stopPropagation()">
                                                <input type="checkbox" class="row-check"
                                                       value="<?= $c['complaint_id'] ?>">
                                            </td>
                                            <td><?= $c['complaint_id'] ?></td>
                                            <td><?= htmlspecialchars($c['complaint_title']) ?></td>
                                            <td class="text-center"><?= $studentName ?></td>
                                            <td class="text-center"><?= htmlspecialchars($c['category_name']) ?></td>
                                            <td class="text-center">
                                                <span class="badge <?= $priClass ?>">
                                                    <?= ucfirst($c['priority']) ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <?= statusBadge($c['complaint_status']) ?>
                                                <?php if ($isOverdue): ?>
                                                    <br><span class="badge bg-danger mt-1">
                                                        <i class="fas fa-clock me-1"></i>Overdue
                                                    </span>
                                                <?php endif; ?>
                                                <?php if (!empty($c['endorsement_count']) && $c['endorsement_count'] > 0): ?>
                                                    <br><span class="badge mt-1" style="background-color:#6f42c1;">
                                                        <i class="fas fa-thumbs-up me-1"></i><?= (int)$c['endorsement_count'] ?> Rep
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <?php if (!empty($c['assigned_staff_name'])): ?>
                                                    <span class="small fw-semibold"><?= htmlspecialchars($c['assigned_staff_name']) ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted small">Unassigned</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <?= date('d M Y', strtotime($c['created_at'])) ?>
                                            </td>
                                            <td class="text-center" onclick="event.stopPropagation()">
                                                <?php $isAssigned = !empty($c['assigned_staff_name']); ?>
                                                <div class="d-flex justify-content-center gap-1">
                                                    <a href="complaint_details.php?id=<?= $c['complaint_id'] ?>"
                                                        class="btn btn-status btn-outline-secondary" title="View Details">
                                                        <i class="fas fa-eye text-dark"></i>
                                                    </a>

                                                    <?php if (!$isClosed && !$isAssigned): ?>
                                                        <a href="complaint_details.php?id=<?= $c['complaint_id'] ?>#respond"
                                                            class="btn btn-status btn-outline-secondary" title="Respond">
                                                            <i class="fas fa-reply text-dark"></i>
                                                        </a>

                                                        <button type="button"
                                                            class="btn btn-status btn-outline-secondary btn-assign"
                                                            data-bs-toggle="modal" data-bs-target="#assignModal"
                                                            data-complaint-id="<?= $c['complaint_id'] ?>"
                                                            data-staff-name=""
                                                            data-priority="<?= htmlspecialchars($c['priority']) ?>"
                                                            data-is-assigned="0"
                                                            data-category-dept-id="<?= (int)($c['category_dept_id'] ?? 0) ?>"
                                                            title="Assign to Staff">
                                                            <i class="fas fa-user-tag text-dark"></i>
                                                        </button>

                                                        <?php if ($c['complaint_status'] === STATUS_PENDING): ?>
                                                            <button type="button" class="btn btn-status btn-outline-secondary"
                                                                onclick="confirmDelete(<?= $c['complaint_id'] ?>)" title="Delete">
                                                                <i class="fas fa-trash text-dark"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    <?php elseif (!$isClosed && $isAssigned): ?>
                                                        <button type="button"
                                                            class="btn btn-status btn-outline-warning btn-assign"
                                                            data-bs-toggle="modal" data-bs-target="#assignModal"
                                                            data-complaint-id="<?= $c['complaint_id'] ?>"
                                                            data-staff-name="<?= htmlspecialchars($c['assigned_staff_name'] ?? '', ENT_QUOTES) ?>"
                                                            data-priority="<?= htmlspecialchars($c['priority']) ?>"
                                                            data-is-assigned="1"
                                                            data-category-dept-id="<?= (int)($c['category_dept_id'] ?? 0) ?>"
                                                            title="Reassign to a different staff member">
                                                            <i class="fas fa-user-edit"></i>
                                                        </button>
                                                    <?php endif; ?>
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

    <!-- Single: Assign / Reassign Staff Modal -->
    <div class="modal fade" id="assignModal" tabindex="-1" aria-labelledby="assignModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content shadow-lg rounded-3">
                <div class="modal-header text-white" id="assignModalHeader" style="background:linear-gradient(135deg,#1e3a5f,#2d6a9f);">
                    <h5 class="modal-title fw-bold text-white" id="assignModalLabel">
                        <i class="fas fa-user-tag me-2" id="assignModalIcon"></i>
                        <span id="assignModalTitleText">Assign Complaint to Staff</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="manage_complaints.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="assign">
                    <input type="hidden" name="complaint_id" id="assignComplaintId">
                    <div class="modal-body">
                        <div id="currentAssigneeInfo" class="alert alert-warning py-2 px-3 mb-3 small d-none">
                            <i class="fas fa-user-check me-1"></i>
                            Currently assigned to: <strong id="currentAssigneeName"></strong>
                            <br><span class="text-muted">Saving will transfer this complaint to the selected staff member.</span>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">
                                Filter by Department
                                <span class="badge bg-info text-white ms-1" id="autoDeptBadge" style="display:none;font-size:0.7rem;">Auto-suggested</span>
                            </label>
                            <select id="assignDeptFilter" class="form-select">
                                <option value="0">- All Departments -</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?= $dept['department_id'] ?>">
                                        <?= htmlspecialchars($dept['department_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted" id="deptFilterHint" style="display:none;">
                                <i class="fas fa-info-circle me-1"></i>Pre-selected from the complaint's category. You can change it.
                            </small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Select Staff <span class="text-danger">*</span></label>
                            <select name="staff_id" id="assignStaffSelect" class="form-select" required>
                                <option value="">-- Choose Staff --</option>
                                <?php foreach ($staffList as $s): ?>
                                    <option value="<?= $s['staff_id'] ?>"
                                        data-dept="<?= (int)($s['staff_department_id'] ?? 0) ?>">
                                        <?= htmlspecialchars($s['username']) ?>
                                        <?= $s['department_name'] ? ' - ' . htmlspecialchars($s['department_name']) : '' ?>
                                        <?= $s['role_name'] ? ' (' . htmlspecialchars($s['role_name']) . ')' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small id="noStaffMsg" class="text-danger d-none">No staff in this department. Switch to "All Departments".</small>
                            <?php if (empty($staffList)): ?>
                                <small class="text-danger">No approved staff available.</small>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Priority</label>
                            <select name="priority" id="assignPriority" class="form-select">
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold" id="noteLabel">
                                Note <span class="text-muted fw-normal">(optional)</span>
                            </label>
                            <textarea name="note" id="assignNote" class="form-control" rows="3"
                                placeholder="Add instructions or details for the staff member..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="assignSubmitBtn">
                            <i class="fas fa-user-tag me-1" id="assignSubmitIcon"></i>
                            <span id="assignSubmitText">Assign</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bulk Assign Modal -->
    <div class="modal fade" id="bulkAssignModal" tabindex="-1" aria-labelledby="bulkAssignModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content shadow-lg rounded-3">
                <div class="modal-header text-white" style="background:linear-gradient(135deg,#1e3a5f,#2d6a9f);">
                    <h5 class="modal-title fw-bold text-white" id="bulkAssignModalLabel">
                        <i class="fas fa-user-tag me-2"></i>Bulk Assign Complaints
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="manage_complaints.php" id="bulkAssignForm">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="bulk_assign">
                    <div id="bulkAssignIds"></div><!-- complaint_ids[] injected by JS -->
                    <div class="modal-body">
                        <p class="text-muted small mb-3">
                            Assigning <strong id="bulkAssignCount">0</strong> complaint(s) to one staff member.
                            Already-closed complaints will be skipped automatically.
                        </p>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Filter by Department</label>
                            <select id="bulkDeptFilter" class="form-select">
                                <option value="0">- All Departments -</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?= $dept['department_id'] ?>">
                                        <?= htmlspecialchars($dept['department_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Select Staff <span class="text-danger">*</span></label>
                            <select name="staff_id" id="bulkStaffSelect" class="form-select" required>
                                <option value="">-- Choose Staff --</option>
                                <?php foreach ($staffList as $s): ?>
                                    <option value="<?= $s['staff_id'] ?>"
                                        data-dept="<?= (int)($s['staff_department_id'] ?? 0) ?>">
                                        <?= htmlspecialchars($s['username']) ?>
                                        <?= $s['department_name'] ? ' - ' . htmlspecialchars($s['department_name']) : '' ?>
                                        <?= $s['role_name'] ? ' (' . htmlspecialchars($s['role_name']) . ')' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small id="bulkNoStaffMsg" class="text-danger d-none">No staff in this department.</small>
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
                            <textarea name="note" class="form-control" rows="2"
                                placeholder="Instructions for the staff member..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-user-tag me-1"></i> Assign All
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Hidden delete form -->
    <form id="deleteForm" method="POST" action="manage_complaints.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="complaint_id" id="deleteComplaintId">
        <input type="hidden" name="delete_reason" id="deleteReason">
    </form>

    <!-- Delete Reason Modal -->
    <div class="modal fade" id="deleteReasonModal" tabindex="-1" aria-labelledby="deleteReasonModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow-lg rounded-3">
                <div class="modal-header text-white" style="background: linear-gradient(135deg, #dc3545, #c82333);">
                    <h5 class="modal-title fw-bold text-white" id="deleteReasonModalLabel">
                        <i class="fas fa-trash me-2"></i>Delete Complaint
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-3">Please provide a reason for deleting this complaint. The student will be notified.</p>
                    <div class="mb-0">
                        <label for="deleteReasonText" class="form-label fw-semibold">Reason <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="deleteReasonText" rows="3"
                            placeholder="Enter reason for deletion..." maxlength="500"></textarea>
                        <div class="invalid-feedback">Please provide a reason before deleting.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
                        <i class="fas fa-trash me-1"></i>Confirm Delete
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bulk Delete Modal -->
    <div class="modal fade" id="bulkDeleteModal" tabindex="-1" aria-labelledby="bulkDeleteModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow-lg rounded-3">
                <div class="modal-header text-white" style="background: linear-gradient(135deg, #dc3545, #c82333);">
                    <h5 class="modal-title fw-bold text-white" id="bulkDeleteModalLabel">
                        <i class="fas fa-trash me-2"></i>Bulk Delete Complaints
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="manage_complaints.php" id="bulkDeleteForm">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="bulk_delete">
                    <div id="bulkDeleteIds"></div><!-- complaint_ids[] injected by JS -->
                    <div class="modal-body">
                        <p class="text-muted mb-2">
                            Deleting <strong id="bulkDeleteCount">0</strong> complaint(s).
                            Only <em>pending</em> complaints can be deleted - others will be skipped.
                        </p>
                        <label class="form-label fw-semibold">Reason <span class="text-danger">*</span></label>
                        <textarea name="delete_reason" id="bulkDeleteReason" class="form-control" rows="3"
                            placeholder="Enter reason for deletion..." maxlength="500" required></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash me-1"></i> Confirm Delete
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- <script src="assets/js/jquery-3.6.0.min.js"></script> -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <!-- <script src="assets/js/bootstrap.bundle.min.js"></script> -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
    <!-- <script src="assets/js/jquery.dataTables.min.js"></script> -->
    <script src="https://cdn.datatables.net/1.10.21/js/jquery.dataTables.min.js"></script>
    <!-- <script src="assets/js/dataTables.bootstrap4.min.js"></script> -->
    <script src="https://cdn.datatables.net/1.10.21/js/dataTables.bootstrap4.min.js"></script>
    <script src="assets/plugins/sweetalert/sweetalert2.all.min.js"></script>
    <script src="assets/plugins/sweetalert/sweetalerts.min.js"></script>
    <script src="assets/js/script.js"></script>

    <script>
        // ── Selection state ──────────────────────────────────────────────────
        var selectedIds = new Set();

        function updateToolbar() {
            var count = selectedIds.size;
            var toolbar = document.getElementById('bulkToolbar');
            if (count > 0) {
                toolbar.classList.remove('d-none');
                document.getElementById('selectedCount').textContent = count + ' selected';
            } else {
                toolbar.classList.add('d-none');
            }
        }

        function updateSelectAll() {
            var visible  = document.querySelectorAll('.row-check:not(#selectAll)');
            var checked  = Array.from(visible).filter(function(cb) { return cb.checked; });
            var selectAll = document.getElementById('selectAll');
            selectAll.indeterminate = checked.length > 0 && checked.length < visible.length;
            selectAll.checked       = visible.length > 0 && checked.length === visible.length;
        }

        // Row checkbox change
        document.getElementById('complaintsTable').addEventListener('change', function(e) {
            if (!e.target.classList.contains('row-check') || e.target.id === 'selectAll') return;
            var id = parseInt(e.target.value, 10);
            if (e.target.checked) selectedIds.add(id);
            else selectedIds.delete(id);
            updateSelectAll();
            updateToolbar();
        });

        // Select-all checkbox
        document.getElementById('selectAll').addEventListener('change', function() {
            var checked  = this.checked;
            document.querySelectorAll('.row-check:not(#selectAll)').forEach(function(cb) {
                cb.checked = checked;
                var id = parseInt(cb.value, 10);
                if (checked) selectedIds.add(id);
                else selectedIds.delete(id);
            });
            updateToolbar();
        });

        // Clear selection button
        document.getElementById('clearSelectionBtn').addEventListener('click', function() {
            selectedIds.clear();
            document.querySelectorAll('.row-check').forEach(function(cb) { cb.checked = false; });
            document.getElementById('selectAll').indeterminate = false;
            updateToolbar();
        });

        // Re-apply check state after DataTable redraws (pagination / search)
        function reapplyChecks(dt) {
            dt.on('draw', function() {
                document.querySelectorAll('.row-check:not(#selectAll)').forEach(function(cb) {
                    cb.checked = selectedIds.has(parseInt(cb.value, 10));
                });
                updateSelectAll();
            });
        }

        // Inject complaint_ids[] hidden inputs into a container element
        function injectIds(containerId) {
            var container = document.getElementById(containerId);
            container.innerHTML = '';
            selectedIds.forEach(function(id) {
                var inp = document.createElement('input');
                inp.type  = 'hidden';
                inp.name  = 'complaint_ids[]';
                inp.value = id;
                container.appendChild(inp);
            });
        }

        // ── Bulk modals ───────────────────────────────────────────────────────
        document.getElementById('bulkAssignModal').addEventListener('show.bs.modal', function() {
            document.getElementById('bulkAssignCount').textContent = selectedIds.size;
            injectIds('bulkAssignIds');
        });

        document.getElementById('bulkDeleteModal').addEventListener('show.bs.modal', function() {
            document.getElementById('bulkDeleteCount').textContent = selectedIds.size;
            document.getElementById('bulkDeleteReason').value = '';
            injectIds('bulkDeleteIds');
        });

        // ── Staff filtering helpers ───────────────────────────────────────────
        function filterStaffByDept(selectEl, noMsgEl, deptId) {
            var deptInt = parseInt(deptId, 10);
            var visible = 0;
            Array.from(selectEl.options).forEach(function(opt) {
                if (!opt.value) { opt.style.display = ''; return; }
                var optDept = parseInt(opt.getAttribute('data-dept') || '0', 10);
                var show    = (deptInt === 0 || optDept === deptInt);
                opt.style.display = show ? '' : 'none';
                if (show) visible++;
            });
            var cur = selectEl.options[selectEl.selectedIndex];
            if (cur && cur.value && cur.style.display === 'none') selectEl.value = '';
            noMsgEl.classList.toggle('d-none', visible > 0 || deptInt === 0);
        }

        document.getElementById('assignDeptFilter').addEventListener('change', function() {
            filterStaffByDept(
                document.getElementById('assignStaffSelect'),
                document.getElementById('noStaffMsg'),
                this.value
            );
        });

        document.getElementById('bulkDeptFilter').addEventListener('change', function() {
            filterStaffByDept(
                document.getElementById('bulkStaffSelect'),
                document.getElementById('bulkNoStaffMsg'),
                this.value
            );
        });

        // ── Single assign modal ───────────────────────────────────────────────
        document.getElementById('assignModal').addEventListener('show.bs.modal', function(event) {
            var btn        = event.relatedTarget;
            var id         = btn.getAttribute('data-complaint-id');
            var staffName  = btn.getAttribute('data-staff-name') || '';
            var priority   = btn.getAttribute('data-priority')   || 'medium';
            var isAssigned = btn.getAttribute('data-is-assigned') === '1';
            var catDeptId  = parseInt(btn.getAttribute('data-category-dept-id') || '0', 10);

            document.getElementById('assignComplaintId').value = id;

            document.getElementById('assignModalTitleText').textContent =
                isAssigned ? 'Reassign Complaint #' + id : 'Assign Complaint #' + id + ' to Staff';
            document.getElementById('assignModalIcon').className =
                'fas ' + (isAssigned ? 'fa-user-edit' : 'fa-user-tag') + ' me-2';

            var banner = document.getElementById('currentAssigneeInfo');
            if (isAssigned) {
                document.getElementById('currentAssigneeName').textContent = staffName;
                banner.classList.remove('d-none');
            } else {
                banner.classList.add('d-none');
            }

            document.getElementById('assignPriority').value = priority;

            var noteField = document.getElementById('assignNote');
            var noteLabel = document.getElementById('noteLabel');
            noteField.value = '';
            if (isAssigned) {
                noteLabel.innerHTML = 'Reason for Reassignment <span class="text-danger">*</span>';
                noteField.placeholder = 'Explain why this complaint is being reassigned...';
                noteField.required = true;
            } else {
                noteLabel.innerHTML = 'Note <span class="text-muted fw-normal">(optional)</span>';
                noteField.placeholder = 'Add instructions or details for the staff member...';
                noteField.required = false;
            }

            var deptFilter = document.getElementById('assignDeptFilter');
            var badge      = document.getElementById('autoDeptBadge');
            var hint       = document.getElementById('deptFilterHint');
            if (catDeptId > 0) {
                deptFilter.value    = catDeptId;
                badge.style.display = '';
                hint.style.display  = '';
            } else {
                deptFilter.value    = '0';
                badge.style.display = 'none';
                hint.style.display  = 'none';
            }
            filterStaffByDept(
                document.getElementById('assignStaffSelect'),
                document.getElementById('noStaffMsg'),
                deptFilter.value
            );

            document.getElementById('assignStaffSelect').value = '';
            document.getElementById('assignSubmitIcon').className =
                'fas ' + (isAssigned ? 'fa-user-edit' : 'fa-user-tag') + ' me-1';
            document.getElementById('assignSubmitText').textContent = isAssigned ? 'Reassign' : 'Assign';
        });

        // ── Single delete modal ───────────────────────────────────────────────
        function confirmDelete(complaintId) {
            document.getElementById('deleteComplaintId').value = complaintId;
            document.getElementById('deleteReasonText').value  = '';
            document.getElementById('deleteReasonText').classList.remove('is-invalid');
            new bootstrap.Modal(document.getElementById('deleteReasonModal')).show();
        }

        document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
            var reason = document.getElementById('deleteReasonText').value.trim();
            if (!reason) {
                document.getElementById('deleteReasonText').classList.add('is-invalid');
                return;
            }
            document.getElementById('deleteReason').value = reason;
            document.getElementById('deleteForm').submit();
        });

        // ── Custom row filter (runs before DataTables draws) ─────────────────
        $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
            if (settings.nTable.id !== 'complaintsTable') return true;
            var rowNode  = settings.aoData[dataIndex] ? settings.aoData[dataIndex].nTr : null;
            if (!rowNode) return true;
            var row      = $(rowNode);
            var rStatus  = row.data('status')   || '';
            var rPri     = row.data('priority') || '';
            var rCat     = row.data('category') || '';
            var rDept    = row.data('dept')     || '';
            var rOverdue = row.data('overdue')  === '1';
            var rDate    = row.data('date')     || '';

            var fStatus   = $('#filterStatus').val()   || '';
            var fPriority = $('#filterPriority').val() || '';
            var fCategory = $('#filterCategory').val() || '';
            var fDept     = $('#filterDept').val()     || '';
            var fFrom     = $('#filterDateFrom').val() || '';
            var fTo       = $('#filterDateTo').val()   || '';

            if (fStatus === 'overdue'  && !rOverdue)          return false;
            if (fStatus && fStatus !== 'overdue' && rStatus !== fStatus) return false;
            if (fPriority && rPri  !== fPriority)             return false;
            if (fCategory && rCat  !== fCategory)             return false;
            if (fDept     && rDept !== fDept)                 return false;
            if (fFrom     && rDate < fFrom)                   return false;
            if (fTo       && rDate > fTo)                     return false;
            return true;
        });

        // ── DataTable ─────────────────────────────────────────────────────────
        var complaintsTable;
        $(document).ready(function() {
            if ($("#complaintsTable").length > 0 && !$.fn.DataTable.isDataTable("#complaintsTable")) {
                complaintsTable = $("#complaintsTable").DataTable({
                    destroy:     true,
                    bFilter:     true,
                    sDom:        "fBtlpi",
                    pagingType:  "numbers",
                    ordering:    true,
                    columnDefs:  [{ orderable: false, targets: [0, 9] }],
                    language: {
                        search:            " ",
                        sLengthMenu:       "_MENU_",
                        searchPlaceholder: "Search complaints...",
                        info:              "_START_ - _END_ of _TOTAL_ items"
                    },
                    initComplete: function() {
                        $(".dataTables_filter").appendTo(".search-input");
                    }
                });

                reapplyChecks(complaintsTable);

                // Bind filter controls
                $('#filterStatus, #filterPriority, #filterCategory, #filterDept').on('change', function() {
                    complaintsTable.draw();
                });
                $('#filterDateFrom, #filterDateTo').on('change', function() {
                    complaintsTable.draw();
                });
                $('#clearFilters').on('click', function() {
                    $('#filterStatus, #filterPriority, #filterCategory, #filterDept').val('');
                    $('#filterDateFrom, #filterDateTo').val('');
                    complaintsTable.search('').draw();
                });
            }
        });
    </script>

</body>
</html>
