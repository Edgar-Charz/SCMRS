<?php
session_start();

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

// Handle Add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $name = trim($_POST['category_name'] ?? '');
    $desc = trim($_POST['category_description'] ?? '');
    if ($name !== '') {
        $admin->addCategory($name, $desc, $_SESSION['user_id']);
        $_SESSION['message'] = "Category '{$name}' added successfully.";
    } else {
        $_SESSION['message_error'] = "Category name is required.";
    }
    header("Location: manage_categories.php");
    exit;
}

// Handle Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_category'])) {
    $id = (int) ($_POST['category_id'] ?? 0);
    $name = trim($_POST['category_name'] ?? '');
    $desc = trim($_POST['category_description'] ?? '');
    $status = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';
    if ($id && $name !== '') {
        $admin->updateCategory($id, $name, $desc, $status);
        $_SESSION['message'] = "Category updated successfully.";
    } else {
        $_SESSION['message_error'] = "Category name is required.";
    }
    header("Location: manage_categories.php");
    exit;
}

// Handle Delete
if (isset($_GET['delete_category']) && is_numeric($_GET['delete_category'])) {
    $id = (int) $_GET['delete_category'];
    try {
        $admin->deleteCategory($id);
        $_SESSION['message'] = "Category deleted successfully.";
    } catch (Exception $e) {
        $_SESSION['message_error'] = $e->getMessage();
    }
    header("Location: manage_categories.php");
    exit;
}

// Handle Add Subcategory
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_subcategory'])) {
    $catId = (int) ($_POST['subcategory_category_id'] ?? 0);
    $name = trim($_POST['subcategory_name'] ?? '');
    $desc = trim($_POST['subcategory_description'] ?? '');
    if ($catId && $name !== '') {
        try {
            $admin->addSubcategory($catId, $name, $desc, $_SESSION['user_id']);
            $_SESSION['message'] = "Subcategory '{$name}' added successfully.";
        } catch (Exception $e) {
            $_SESSION['message_error'] = $e->getMessage();
        }
    } else {
        $_SESSION['message_error'] = "Category and subcategory name are required.";
    }
    header("Location: manage_categories.php");
    exit;
}

// Handle Edit Subcategory
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_subcategory'])) {
    $id = (int) ($_POST['subcategory_id'] ?? 0);
    $name = trim($_POST['subcategory_name'] ?? '');
    $desc = trim($_POST['subcategory_description'] ?? '');
    $status = in_array($_POST['subcategory_status'] ?? '', ['active', 'inactive']) ? $_POST['subcategory_status'] : 'active';
    if ($id && $name !== '') {
        try {
            $admin->updateSubcategory($id, $name, $desc, $status);
            $_SESSION['message'] = "Subcategory updated successfully.";
        } catch (Exception $e) {
            $_SESSION['message_error'] = $e->getMessage();
        }
    } else {
        $_SESSION['message_error'] = "Subcategory name is required.";
    }
    header("Location: manage_categories.php");
    exit;
}

// Handle Delete Subcategory
if (isset($_GET['delete_subcategory']) && is_numeric($_GET['delete_subcategory'])) {
    $id = (int) $_GET['delete_subcategory'];
    try {
        $admin->deleteSubcategory($id);
        $_SESSION['message'] = "Subcategory deleted successfully.";
    } catch (Exception $e) {
        $_SESSION['message_error'] = $e->getMessage();
    }
    header("Location: manage_categories.php");
    exit;
}

$categories = $admin->getAllCategoriesWithStats();
$subcategories_grouped = $admin->getAllSubcategoriesGrouped();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Categories</title>
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
                        Style="width: 45px; height: 45px; object-fit: cover; border: 2px solid var(--udsm-yellow);">
                </div>
                <div class="header-text">
                    <h6 class="mb-0 text-white fw-bold"> UDSM</h6>
                    <small class="text-warning" style="font-size: 0.7rem;">Complaints System</small>
                </div>
            </div>

            <div class="user-info d-flex align-items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-user me-2"></i>
                </div>
                <div class="flex-grow-1 ms-3">
                    <p class="mb-0 small fw-bold"><?= strtoupper($_SESSION['user_role']); ?></p>
                </div>
            </div>

            <ul class="list-unstyled components">
                <li>
                    <a href="admin_dashboard.php" title="Dashboard">
                        <i class="fas fa-chart-pie me-2"></i>
                        <span class="link-text">Overview</span>
                    </a>
                </li>
                <li>
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
                <li class="active">
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

            <div class="p-4">

                <nav aria-label="breadcrumb" class="d-flex justify-content-between align-items-center">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item">
                            <a href="#"><i class="fas fa-tags" style="color: black;"></i></a>
                        </li>
                        <li class="breadcrumb-item active">Admin / Manage Categories</li>
                    </ol>
                    <button type="button" class="btn btn-add" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                        <i class="fas fa-plus"></i> Add New Category
                    </button>
                </nav>

                <div class="container-card shadow-sm mt-3">
                    <h4 class="mb-1 fw-bold"><i class="fas fa-tags me-2"></i>All Categories</h4>

                    <div class="table-responsive">
                        <table class="table table-stripped" id="categoriesTable">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>CATEGORY NAME</th>
                                    <th>DESCRIPTION</th>
                                    <th class="text-center">COMPLAINTS</th>
                                    <th class="text-center">STATUS</th>
                                    <th class="text-center">SUBCATEGORIES</th>
                                    <th class="text-center">ACTION</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($categories)): ?>
                                    <?php $n = 1;
                                    foreach ($categories as $cat): ?>
                                        <tr>
                                            <td><?= $n++ ?></td>
                                            <td><?= htmlspecialchars($cat['category_name']) ?></td>
                                            <td class="text-muted small">
                                                <?= htmlspecialchars($cat['category_description'] ?? '—') ?>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-primary"><?= $cat['complaint_count'] ?></span>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($cat['status'] === 'active'): ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <?php $subCount = count($subcategories_grouped[$cat['category_id']] ?? []); ?>
                                                <button type="button" class="btn btn-sm btn-outline-info"
                                                    onclick="openSubcategories(<?= $cat['category_id'] ?>, '<?= htmlspecialchars($cat['category_name'], ENT_QUOTES) ?>')"
                                                    data-bs-toggle="modal" data-bs-target="#subcategoriesModal"
                                                    title="manage subcategories">
                                                    <i class="fas fa-list-ul me-1"></i><?= $subCount ?>
                                                </button>
                                            </td>
                                            <td class="text-center">
                                                <div class="d-flex justify-content-center">
                                                    <button type="button" class="btn btn-status btn-outline-secondary me-2"
                                                        onclick="openEditCategory(<?= htmlspecialchars(json_encode($cat)) ?>)"
                                                        data-bs-toggle="modal" data-bs-target="#editCategoryModal" title="edit">
                                                        <i class="fas fa-edit text-dark"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-status btn-outline-secondary"
                                                        onclick="confirmDeleteCat(<?= $cat['category_id'] ?>, '<?= htmlspecialchars($cat['category_name'], ENT_QUOTES) ?>')"
                                                        title="delete">
                                                        <i class="fas fa-trash text-dark"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">No categories found. Add one to
                                            get started.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Add Category Modal -->
            <div class="modal fade" id="addCategoryModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0 shadow-lg" style="border-radius: 5px;">
                        <div class="modal-header text-white"
                            style="background:linear-gradient(135deg,#1e3a5f,#2d6a9f);">
                            <h5 class="modal-title fw-bold">
                                <i class="fas fa-plus me-2"></i>
                                ADD CATEGORY
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <form action="manage_categories.php" method="POST">
                            <div class="modal-body px-4 py-3">
                                <div class="mb-3">
                                    <label class="form-label fw-bold small">Category Name <span
                                            class="text-danger">*</span></label>
                                    <input type="text" name="category_name" class="form-control p-3 shadow-sm"
                                        style="border-radius: 10px;" placeholder="e.g., Academics" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold small">Description</label>
                                    <textarea name="category_description" class="form-control shadow-sm" rows="3"
                                        style="border-radius: 10px;"
                                        placeholder="Brief description of the category..."></textarea>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="submit" name="add_category" class="btn btn-primary fw-bold">
                                    <i class="fas fa-plus me-1"></i> Add Category
                                </button>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Edit Category Modal -->
            <div class="modal fade" id="editCategoryModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0 shadow-lg" style="border-radius: 5px;">
                        <div class="modal-header text-white"
                            style="background:linear-gradient(135deg,#1e3a5f,#2d6a9f);">
                            <h5 class="modal-title fw-bold">
                                <i class="fas fa-edit me-2"></i>
                                EDIT CATEGORY
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <form action="manage_categories.php" method="POST">
                            <input type="hidden" name="category_id" id="edit_cat_id">
                            <div class="modal-body px-4 py-3">
                                <div class="mb-3">
                                    <label class="form-label fw-bold small">Category Name <span
                                            class="text-danger">*</span></label>
                                    <input type="text" name="category_name" id="edit_cat_name"
                                        class="form-control p-3 shadow-sm" style="border-radius: 10px;" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold small">Description</label>
                                    <textarea name="category_description" id="edit_cat_desc"
                                        class="form-control shadow-sm" rows="3" style="border-radius: 10px;"></textarea>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold small">Status</label>
                                    <select name="status" id="edit_cat_status" class="form-select">
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="submit" name="edit_category" class="btn btn-primary fw-bold">
                                    <i class="fas fa-save me-1"></i> Save Changes
                                </button>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Subcategories Modal -->
            <div class="modal fade" id="subcategoriesModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                    <div class="modal-content border-0 shadow-lg" style="border-radius: 5px;">
                        <div class="modal-header text-white"
                            style="background:linear-gradient(135deg,#1e3a5f,#2d6a9f);">
                            <h5 class="modal-title fw-bold" id="sub_modal_title">
                                <i class="fas fa-list me-2"></i>
                                SUBCATEGORIES
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body px-4 py-3">

                            <!-- Subcategory List -->
                            <div class="table-responsive mb-4">
                                <table class="table table-sm table-striped">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>NAME</th>
                                            <th>DESCRIPTION</th>
                                            <th class="text-center">STATUS</th>
                                            <th class="text-center">COMPLAINTS</th>
                                            <th class="text-center">ACTION</th>
                                        </tr>
                                    </thead>
                                    <tbody id="subcategoryRows">
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-3">No subcategories yet.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Add Subcategory Form -->
                            <div>
                                <button class="btn btn-sm btn-outline-primary mb-3" type="button"
                                    data-bs-toggle="collapse" data-bs-target="#addSubForm">
                                    <i class="fas fa-plus me-1"></i> Add New Subcategory
                                </button>
                                <div class="collapse" id="addSubForm">
                                    <div class="card card-body border-0 bg-light shadow-sm" style="border-radius:10px;">
                                        <form action="manage_categories.php" method="POST">
                                            <input type="hidden" name="subcategory_category_id" id="sub_category_id">
                                            <div class="mb-3">
                                                <label class="form-label fw-bold small">Subcategory Name <span
                                                        class="text-danger">*</span></label>
                                                <input type="text" name="subcategory_name"
                                                    class="form-control shadow-sm" style="border-radius:8px;"
                                                    placeholder="e.g., Grade Appeal" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label fw-bold small">Description</label>
                                                <textarea name="subcategory_description" class="form-control shadow-sm"
                                                    rows="2" style="border-radius:8px;"
                                                    placeholder="Brief description..."></textarea>
                                            </div>
                                            <button type="submit" name="add_subcategory"
                                                class="btn btn-primary btn-sm fw-bold">
                                                <i class="fas fa-plus me-1"></i> Add Subcategory
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>

                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Edit Subcategory Modal -->
            <div class="modal fade" id="editSubcategoryModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0 shadow-lg" style="border-radius: 5px;">
                        <div class="modal-header text-white"
                            style="background:linear-gradient(135deg,#1e3a5f,#2d6a9f);">
                            <h5 class="modal-title fw-bold">
                                <i class="fas fa-edit me-2"></i>
                                EDIT SUBCATEGORY
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <form action="manage_categories.php" method="POST">
                            <input type="hidden" name="subcategory_id" id="edit_sub_id">
                            <div class="modal-body px-4 py-3">
                                <div class="mb-3">
                                    <label class="form-label fw-bold small">Subcategory Name <span
                                            class="text-danger">*</span></label>
                                    <input type="text" name="subcategory_name" id="edit_sub_name"
                                        class="form-control p-3 shadow-sm" style="border-radius: 10px;" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold small">Description</label>
                                    <textarea name="subcategory_description" id="edit_sub_desc"
                                        class="form-control shadow-sm" rows="3" style="border-radius: 10px;"></textarea>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold small">Status</label>
                                    <select name="subcategory_status" id="edit_sub_status" class="form-select">
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="submit" name="edit_subcategory" class="btn btn-primary fw-bold">
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
        function openEditCategory(cat) {
            document.getElementById('edit_cat_id').value = cat.category_id;
            document.getElementById('edit_cat_name').value = cat.category_name;
            document.getElementById('edit_cat_desc').value = cat.category_description || '';
            document.getElementById('edit_cat_status').value = cat.status;
        }

        // ── Subcategory helpers ───────────────────────────────────────────────
        const allSubcategories = <?= json_encode($subcategories_grouped) ?>;
        const subById = {};
        Object.values(allSubcategories).forEach(arr => arr.forEach(s => { subById[s.subcategory_id] = s; }));

        function escHtml(str) {
            const d = document.createElement('div');
            d.textContent = str || '';
            return d.innerHTML;
        }

        function openSubcategories(categoryId, categoryName) {
            document.getElementById('sub_modal_title').textContent = 'Subcategories — ' + categoryName;
            document.getElementById('sub_category_id').value = categoryId;

            // Collapse the add form if open
            const collapse = bootstrap.Collapse.getInstance(document.getElementById('addSubForm'));
            if (collapse) collapse.hide();

            const subs = allSubcategories[categoryId] || [];
            const tbody = document.getElementById('subcategoryRows');

            if (subs.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">No subcategories yet.</td></tr>';
            } else {
                tbody.innerHTML = subs.map((s, i) => `
                    <tr>
                        <td>${i + 1}</td>
                        <td>${escHtml(s.subcategory_name)}</td>
                        <td class="text-muted small">${escHtml(s.subcategory_description || '—')}</td>
                        <td class="text-center">
                            <span class="badge ${s.status === 'active' ? 'bg-success' : 'bg-secondary'}">
                                ${s.status === 'active' ? 'Active' : 'Inactive'}
                            </span>
                        </td>
                        <td class="text-center"><span class="badge bg-primary">${s.complaint_count}</span></td>
                        <td class="text-center">
                            <div class="d-flex justify-content-center gap-1">
                                <button class="btn btn-status btn-outline-secondary"
                                    onclick="openEditSubcategory(${s.subcategory_id})" title="edit">
                                    <i class="fas fa-edit text-dark"></i>
                                </button>
                                <button class="btn btn-status btn-outline-secondary"
                                    onclick="confirmDeleteSub(${s.subcategory_id}, '${escHtml(s.subcategory_name)}')" title="delete">
                                    <i class="fas fa-trash text-dark"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `).join('');
            }
        }

        function openEditSubcategory(subId) {
            const sub = subById[subId];
            if (!sub) return;
            // Close subcategories modal, then open edit modal
            const subModal = bootstrap.Modal.getInstance(document.getElementById('subcategoriesModal'));
            if (subModal) subModal.hide();
            document.getElementById('edit_sub_id').value = sub.subcategory_id;
            document.getElementById('edit_sub_name').value = sub.subcategory_name;
            document.getElementById('edit_sub_desc').value = sub.subcategory_description || '';
            document.getElementById('edit_sub_status').value = sub.status;
            new bootstrap.Modal(document.getElementById('editSubcategoryModal')).show();
        }

        function confirmDeleteSub(id, name) {
            Swal.fire({
                icon: 'warning',
                title: 'Delete Subcategory?',
                text: `"${name}" will be permanently removed. Subcategories with existing complaints cannot be deleted.`,
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'manage_categories.php?delete_subcategory=' + id;
                }
            });
        }
        // ─────────────────────────────────────────────────────────────────────

        function confirmDeleteCat(id, name) {
            Swal.fire({
                icon: 'warning',
                title: 'Delete Category?',
                text: `"${name}" will be permanently removed. Categories with existing complaints cannot be deleted.`,
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'manage_categories.php?delete_category=' + id;
                }
            });
        }
    </script>

    <script src="assets/js/jquery-3.6.0.min.js"></script>
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/jquery.dataTables.min.js"></script>
    <script src="assets/js/dataTables.bootstrap4.min.js"></script>
    <script src="assets/plugins/sweetalert/sweetalert2.all.min.js"></script>
    <script src="assets/plugins/sweetalert/sweetalerts.min.js"></script>
    <script src="assets/js/script.js"></script>
    <script>
        $(document).ready(function () {
            if ($("#categoriesTable").length > 0 && !$.fn.DataTable.isDataTable("#categoriesTable")) {
                $("#categoriesTable").DataTable({
                    destroy: true,
                    bFilter: true,
                    sDom: "fBtlpi",
                    pagingType: "numbers",
                    ordering: true,
                    language: {
                        search: " ",
                        sLengthMenu: "_MENU_",
                        searchPlaceholder: "Search Categories...",
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