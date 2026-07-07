<?php
require_once 'config/session.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

require_once "config/Database.php";
require_once "classes/User.php";
require_once "classes/Admin.php";
require_once "includes/csrf.php";

$db = new Database();
$conn = $db->connect();
$admin = new Admin($conn);
$adminId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
}

// Handle Add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_department'])) {
    $name = trim($_POST['department_name'] ?? '');
    if ($name !== '') {
        $admin->addDepartment($name);
        $admin->logActivity($adminId, 'department_added', 'department', 0, $name, "Department '{$name}' added");
        $_SESSION['message'] = "Department '{$name}' added successfully.";
    } else {
        $_SESSION['message_error'] = "Department name is required.";
    }
    header("Location: manage_departments.php");
    exit;
}

// Handle Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_department'])) {
    $id   = (int)($_POST['department_id'] ?? 0);
    $name = trim($_POST['department_name'] ?? '');
    if ($id && $name !== '') {
        $admin->updateDepartment($id, $name);
        $admin->logActivity($adminId, 'department_updated', 'department', $id, $name, "Department updated to '{$name}'");
        $_SESSION['message'] = "Department updated successfully.";
    } else {
        $_SESSION['message_error'] = "Department name is required.";
    }
    header("Location: manage_departments.php");
    exit;
}

// Handle Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_department'])) {
    $id = (int)$_POST['delete_department'];
    try {
        $admin->deleteDepartment($id);
        $admin->logActivity($adminId, 'department_deleted', 'department', $id, "Department #$id", "Department #$id deleted");
        $_SESSION['message'] = "Department deleted successfully.";
    } catch (Exception $e) {
        $_SESSION['message_error'] = $e->getMessage();
    }
    header("Location: manage_departments.php");
    exit;
}

$departments = $admin->getAllDepartmentsWithStats();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin | Manage Departments</title>
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

                <nav aria-label="breadcrumb" class="d-flex justify-content-between align-items-center">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item">
                            <a href="admin_dashboard.php"><i class="fas fa-building" style="color: black;"></i></a>
                        </li>
                        <li class="breadcrumb-item"><a href="admin_dashboard.php" style="color:black;">Admin</a></li>
                        <li class="breadcrumb-item active">Manage Departments</li>
                    </ol>
                    <button type="button" class="btn btn-add" data-bs-toggle="modal"
                        data-bs-target="#addDepartmentModal">
                        <i class="fas fa-plus"></i> Add New Department
                    </button>
                </nav>

                <div class="container-card shadow-sm mt-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <h5 class="mb-0 fw-bold"><i class="fas fa-building me-2"></i>Departments</h5>
                        <div class="search-input"></div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-striped" id="departmentsTable">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>DEPARTMENT NAME</th>
                                    <th class="text-center">COMPLAINTS</th>
                                    <th class="text-center">STAFF</th>
                                    <th class="text-center">ACTION</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($departments)): ?>
                                    <?php $n = 1; foreach ($departments as $dept): ?>
                                    <tr>
                                        <td><?= $n++ ?></td>
                                        <td><?= htmlspecialchars($dept['department_name']) ?></td>
                                        <td class="text-center">
                                            <span class="badge bg-primary"><?= $dept['complaint_count'] ?></span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-success"><?= $dept['staff_count'] ?></span>
                                        </td>
                                        <td class="text-center">
                                            <div class="d-flex justify-content-center">
                                                <a href="department_hierarchy.php?department_id=<?= $dept['department_id'] ?>"
                                                    class="btn btn-status btn-outline-secondary me-2"
                                                    title="view staff hierarchy">
                                                    <i class="fas fa-sitemap text-dark"></i>
                                                </a>
                                                <button type="button" class="btn btn-status btn-outline-secondary me-2"
                                                    onclick="openEditDepartment(<?= htmlspecialchars(json_encode($dept)) ?>)"
                                                    data-bs-toggle="modal" data-bs-target="#editDepartmentModal"
                                                    title="edit">
                                                    <i class="fas fa-edit text-dark"></i>
                                                </button>
                                                <button type="button" class="btn btn-status btn-outline-secondary"
                                                    onclick="confirmDeleteDept(<?= $dept['department_id'] ?>, '<?= htmlspecialchars($dept['department_name'], ENT_QUOTES) ?>')"
                                                    title="delete">
                                                    <i class="fas fa-trash text-dark"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted">No departments found. Add one to get started.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Add Department Modal -->
            <div class="modal fade" id="addDepartmentModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0 shadow-lg" style="border-radius: 5px;">
                        <div class="modal-header text-white" style="background:linear-gradient(135deg,#1e3a5f,#2d6a9f);">
                            <h5 class="modal-title fw-bold text-white">ADD DEPARTMENT</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <form action="manage_departments.php" method="POST">
                            <?= csrf_field() ?>
                            <div class="modal-body px-4 py-3">
                                <div class="mb-3">
                                    <label class="form-label fw-bold small">Department Name <span class="text-danger">*</span></label>
                                    <input type="text" name="department_name" class="form-control p-3 shadow-sm"
                                        style="border-radius: 10px;"
                                        placeholder="e.g., Information Technology" required>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="submit" name="add_department" class="btn btn-primary fw-bold">
                                    <i class="fas fa-plus me-1"></i> Add Department
                                </button>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Edit Department Modal -->
            <div class="modal fade" id="editDepartmentModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0 shadow-lg" style="border-radius: 5px;">
                        <div class="modal-header text-white" style="background:linear-gradient(135deg,#1e3a5f,#2d6a9f);">
                            <h5 class="modal-title fw-bold text-white">EDIT DEPARTMENT</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <form action="manage_departments.php" method="POST">
                            <?= csrf_field() ?>
                            <input type="hidden" name="department_id" id="edit_dept_id">
                            <div class="modal-body px-4 py-3">
                                <div class="mb-3">
                                    <label class="form-label fw-bold small">Department Name <span class="text-danger">*</span></label>
                                    <input type="text" name="department_name" id="edit_dept_name"
                                        class="form-control p-3 shadow-sm" style="border-radius: 10px;" required>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="submit" name="edit_department" class="btn btn-primary fw-bold">
                                    <i class="fas fa-save me-1"></i> Save Changes
                                </button>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script>
        function openEditDepartment(dept) {
            document.getElementById('edit_dept_id').value   = dept.department_id;
            document.getElementById('edit_dept_name').value = dept.department_name;
        }

        function confirmDeleteDept(id, name) {
            Swal.fire({
                icon: 'warning',
                title: 'Delete Department?',
                text: `"${name}" will be permanently removed. This cannot be undone.`,
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('deleteDeptId').value = id;
                    document.getElementById('deleteDeptForm').submit();
                }
            });
        }
    </script>

    <?php $useDataTablesJs = true; include 'includes/foot_scripts.php'; ?>
    <script src="assets/plugins/sweetalert/sweetalerts.min.js"></script>
    <script src="assets/js/script.js"></script>
    <script>
        $(document).ready(function () {
            if ($("#departmentsTable").length > 0 && !$.fn.DataTable.isDataTable("#departmentsTable")) {
                $("#departmentsTable").DataTable({
                    destroy: true,
                    bFilter: true,
                    sDom: "fBtlpi",
                    pagingType: "numbers",
                    ordering: true,
                    language: {
                        search: " ",
                        sLengthMenu: "_MENU_",
                        searchPlaceholder: "Search Departments...",
                        info: "_START_ - _END_ of _TOTAL_ items"
                    },
                    initComplete: function () {
                        $(".dataTables_filter").appendTo(".search-input");
                    }
                });
            }
        });
    </script>

    <form id="deleteDeptForm" method="POST" action="manage_departments.php" style="display:none;">
        <?= csrf_field() ?>
        <input type="hidden" name="delete_department" id="deleteDeptId" value="">
    </form>
</body>
</html>
