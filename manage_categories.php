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
$adminId = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
}

// Handle Add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $name = trim($_POST['category_name'] ?? '');
    $desc = trim($_POST['category_description'] ?? '');
    $deptId = (int) ($_POST['auto_assign_department_id'] ?? 0);
    $roleId = (int) ($_POST['default_role_id'] ?? 0);
    $level2RoleId = (int) ($_POST['level2_role_id'] ?? 0);
    $level3RoleId = (int) ($_POST['level3_role_id'] ?? 0);
    $reqDept = isset($_POST['requires_department_selection']) ? 1 : 0;
    $endorsable = isset($_POST['leader_endorsable']) ? 1 : 0;
    $priority = in_array($_POST['default_priority'] ?? '', ['low', 'medium', 'high'], true) ? $_POST['default_priority'] : 'medium';
    if ($name !== '') {
        $admin->addCategory($name, $desc, $adminId, $deptId ?: null, $reqDept, $roleId ?: null, $endorsable, $priority, $level2RoleId ?: null, $level3RoleId ?: null);
        $admin->logActivity($adminId, 'category_added', 'category', 0, $name, "Category '{$name}' added");
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
    $deptId = (int) ($_POST['auto_assign_department_id'] ?? 0);
    $roleId = (int) ($_POST['default_role_id'] ?? 0);
    $level2RoleId = (int) ($_POST['level2_role_id'] ?? 0);
    $level3RoleId = (int) ($_POST['level3_role_id'] ?? 0);
    $reqDept = isset($_POST['requires_department_selection']) ? 1 : 0;
    $endorsable = isset($_POST['leader_endorsable']) ? 1 : 0;
    $priority = in_array($_POST['default_priority'] ?? '', ['low', 'medium', 'high'], true) ? $_POST['default_priority'] : 'medium';
    if ($id && $name !== '') {
        $admin->updateCategory($id, $name, $desc, $status, $deptId ?: null, $reqDept, $roleId ?: null, $endorsable, $priority, $level2RoleId ?: null, $level3RoleId ?: null);
        $admin->logActivity($adminId, 'category_updated', 'category', $id, $name, "Category updated to '{$name}' (status: {$status})");
        $_SESSION['message'] = "Category updated successfully.";
    } else {
        $_SESSION['message_error'] = "Category name is required.";
    }
    header("Location: manage_categories.php");
    exit;
}

// Handle Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_category']) && is_numeric($_POST['delete_category'])) {
    $id = (int) $_POST['delete_category'];
    try {
        $admin->deleteCategory($id);
        $admin->logActivity($adminId, 'category_deleted', 'category', $id, "Category #$id", "Category #$id deleted");
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
    $roleId = (int) ($_POST['default_role_id'] ?? 0);
    $endorsable = isset($_POST['leader_endorsable']) ? 1 : 0;
    if ($catId && $name !== '') {
        try {
            $admin->addSubcategory($catId, $name, $desc, $adminId, $roleId ?: null, $endorsable);
            $admin->logActivity($adminId, 'subcategory_added', 'category', $catId, $name, "Subcategory '{$name}' added to category #$catId");
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
    $roleId = (int) ($_POST['default_role_id'] ?? 0);
    $endorsable = isset($_POST['leader_endorsable']) ? 1 : 0;
    if ($id && $name !== '') {
        try {
            $admin->updateSubcategory($id, $name, $desc, $status, $roleId ?: null, $endorsable);
            $admin->logActivity($adminId, 'subcategory_updated', 'category', $id, $name, "Subcategory updated to '{$name}' (status: {$status})");
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_subcategory']) && is_numeric($_POST['delete_subcategory'])) {
    $id = (int) $_POST['delete_subcategory'];
    try {
        $admin->deleteSubcategory($id);
        $admin->logActivity($adminId, 'subcategory_deleted', 'category', $id, "Subcategory #$id", "Subcategory #$id deleted");
        $_SESSION['message'] = "Subcategory deleted successfully.";
    } catch (Exception $e) {
        $_SESSION['message_error'] = $e->getMessage();
    }
    header("Location: manage_categories.php");
    exit;
}

$categories = $admin->getAllCategoriesWithStats();
$subcategories_grouped = $admin->getAllSubcategoriesGrouped();
$departments = $admin->getAllDepartments();
$staffRoles = $admin->getAllStaffRoles();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin | Manage Categories</title>
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
                            <a href="admin_dashboard.php"><i class="fas fa-tags" style="color: black;"></i></a>
                        </li>
                        <li class="breadcrumb-item"><a href="admin_dashboard.php" style="color:black;">Admin</a></li>
                        <li class="breadcrumb-item active">Manage Categories</li>
                    </ol>
                    <button type="button" class="btn btn-add" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                        <i class="fas fa-plus"></i> Add New Category
                    </button>
                </nav>

                <div class="container-card shadow-sm mt-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <h5 class="mb-0 fw-bold"><i class="fas fa-tags me-2"></i>Categories</h5>
                        <div class="search-input"></div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-striped" id="categoriesTable">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>NAME</th>
                                    <th>DESCRIPTION</th>
                                    <th class="text-center">DEFAULT DEPT</th>
                                    <th class="text-center">LEVEL 2</th>
                                    <th class="text-center">LEVEL 3</th>
                                    <th class="text-center">PRIORITY</th>
                                    <th class="text-center">ENDORSABLE</th>
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
                                                <?= htmlspecialchars($cat['category_description'] ?? '-') ?>
                                            </td>
                                            <td class="text-center">
                                                <?php if (!empty($cat['default_dept_name'])): ?>
                                                    <span class="badge bg-info text-white" style="font-size:0.75rem;">
                                                        <?= htmlspecialchars($cat['default_dept_name']) ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted small">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <?php if (!empty($cat['level2_role_name'])): ?>
                                                    <span class="badge bg-warning text-dark" style="font-size:0.75rem;">
                                                        <?= htmlspecialchars($cat['level2_role_name']) ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted small">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <?php if (!empty($cat['level3_role_name'])): ?>
                                                    <span class="badge bg-danger text-white" style="font-size:0.75rem;">
                                                        <?= htmlspecialchars($cat['level3_role_name']) ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted small">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <?php
                                                $priorityMap = [
                                                    'low' => 'bg-success',
                                                    'medium' => 'bg-warning text-dark',
                                                    'high' => 'bg-danger',
                                                ];
                                                $priClass = $priorityMap[$cat['default_priority']] ?? 'bg-secondary';
                                                ?>
                                                <span
                                                    class="badge <?= $priClass ?>"><?= ucfirst($cat['default_priority']) ?></span>
                                            </td>
                                            <td class="text-center">
                                                <?php if (!empty($cat['leader_endorsable'])): ?>
                                                    <span class="badge bg-success"><i class="fas fa-check"></i></span>
                                                <?php else: ?>
                                                    <span class="text-muted small">-</span>
                                                <?php endif; ?>
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
                                        <td colspan="12" class="text-center py-4 text-muted">No categories found. Add one to
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
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content border-0 shadow-lg" style="border-radius: 5px;">
                        <div class="modal-header text-white"
                            style="background:linear-gradient(135deg,#1e3a5f,#2d6a9f);">
                            <h5 class="modal-title fw-bold text-white">
                                <i class="fas fa-plus me-2"></i>
                                ADD CATEGORY
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <form action="manage_categories.php" method="POST">
                            <?= csrf_field() ?>
                            <div class="modal-body px-4 py-3">
                                <div class="mb-3">
                                    <label class="form-label fw-bold small">Category Name <span
                                            class="text-danger">*</span></label>
                                    <input type="text" name="category_name" class="form-control p-3 shadow-sm"
                                        style="border-radius: 10px;" placeholder="e.g., Academics" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold small">Description</label>
                                    <textarea name="category_description" class="form-control shadow-sm" rows="2"
                                        style="border-radius: 10px;"
                                        placeholder="Brief description of the category..."></textarea>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-bold small">
                                            Default Department
                                            <span class="text-muted fw-normal">(optional - auto-filters staff)</span>
                                        </label>
                                        <select name="auto_assign_department_id" class="form-select"
                                            style="border-radius:10px;">
                                            <option value="0">- No default department -</option>
                                            <?php foreach ($departments as $dept): ?>
                                                <option value="<?= $dept['department_id'] ?>">
                                                    <?= htmlspecialchars($dept['department_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-bold small">
                                            Default Priority
                                            <span class="text-muted fw-normal">(used when auto-routed)</span>
                                        </label>
                                        <select name="default_priority" class="form-select" style="border-radius:10px;">
                                            <option value="low">Low</option>
                                            <option value="medium" selected>Medium</option>
                                            <option value="high">High</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" name="requires_department_selection"
                                            id="cat_requires_dept" value="1">
                                        <label class="form-check-label fw-bold small" for="cat_requires_dept">
                                            Requires student to pick a department at submission
                                        </label>
                                        <div class="form-text">Enable for categories where routing
                                            depends on the student's own department
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" name="leader_endorsable"
                                            id="cat_leader_endorsable" value="1">
                                        <label class="form-check-label fw-bold small" for="cat_leader_endorsable">
                                            Can be endorsed by student leaders
                                        </label>
                                        <div class="form-text">Enable if complaints in this category should be visible to
                                            and endorsable by student leaders.
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-bold small">
                                            Default Staff Role
                                            <span class="text-muted fw-normal">(optional - enables auto-routing)</span>
                                        </label>
                                        <select name="default_role_id" class="form-select" style="border-radius:10px;">
                                            <option value="0">- No auto-routing (admin assigns manually) -</option>
                                            <?php foreach ($staffRoles as $role): ?>
                                                <option value="<?= $role['role_id'] ?>">
                                                    <?= htmlspecialchars($role['role_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-bold small">
                                            Level 2 Escalation Role
                                            <span class="text-muted fw-normal">(who Level 1 staff escalate to)</span>
                                        </label>
                                        <select name="level2_role_id" class="form-select" style="border-radius:10px;">
                                            <option value="0">- No Level 2 escalation -</option>
                                            <?php foreach ($staffRoles as $role): ?>
                                                <option value="<?= $role['role_id'] ?>">
                                                    <?= htmlspecialchars($role['role_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold small">
                                        Level 3 Escalation Role
                                        <span class="text-muted fw-normal">(final escalation tier, e.g. Dean of
                                            Students)</span>
                                    </label>
                                    <select name="level3_role_id" class="form-select" style="border-radius:10px;">
                                        <option value="0">- No Level 3 escalation -</option>
                                        <?php foreach ($staffRoles as $role): ?>
                                            <option value="<?= $role['role_id'] ?>">
                                                <?= htmlspecialchars($role['role_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
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
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content border-0 shadow-lg" style="border-radius: 5px;">
                        <div class="modal-header text-white"
                            style="background:linear-gradient(135deg,#1e3a5f,#2d6a9f);">
                            <h5 class="modal-title fw-bold text-white">
                                <i class="fas fa-edit me-2"></i>
                                EDIT CATEGORY
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <form action="manage_categories.php" method="POST">
                            <?= csrf_field() ?>
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
                                        class="form-control shadow-sm" rows="2" style="border-radius: 10px;"></textarea>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-bold small">
                                            Default Department
                                            <span class="text-muted fw-normal">(optional)</span>
                                        </label>
                                        <select name="auto_assign_department_id" id="edit_cat_dept" class="form-select"
                                            style="border-radius:10px;">
                                            <option value="0">- No default department -</option>
                                            <?php foreach ($departments as $dept): ?>
                                                <option value="<?= $dept['department_id'] ?>">
                                                    <?= htmlspecialchars($dept['department_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-bold small">
                                            Default Priority
                                            <span class="text-muted fw-normal">(used when auto-routed)</span>
                                        </label>
                                        <select name="default_priority" id="edit_cat_priority" class="form-select"
                                            style="border-radius:10px;">
                                            <option value="low">Low</option>
                                            <option value="medium">Medium</option>
                                            <option value="high">High</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" name="requires_department_selection"
                                            id="edit_cat_requires_dept" value="1">
                                        <label class="form-check-label fw-bold small" for="edit_cat_requires_dept">
                                            Requires student to pick a department at submission
                                        </label>
                                    </div>
                                    <div class="col-md-6 mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" name="leader_endorsable"
                                            id="edit_cat_leader_endorsable" value="1">
                                        <label class="form-check-label fw-bold small" for="edit_cat_leader_endorsable">
                                            Can be endorsed by student leaders
                                        </label>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-bold small">
                                            Default Staff Role
                                            <span class="text-muted fw-normal">(optional - enables auto-routing)</span>
                                        </label>
                                        <select name="default_role_id" id="edit_cat_role" class="form-select"
                                            style="border-radius:10px;">
                                            <option value="0">- No auto-routing (admin assigns manually) -</option>
                                            <?php foreach ($staffRoles as $role): ?>
                                                <option value="<?= $role['role_id'] ?>">
                                                    <?= htmlspecialchars($role['role_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-bold small">
                                            Level 2 Escalation Role
                                            <span class="text-muted fw-normal">(who Level 1 staff escalate to)</span>
                                        </label>
                                        <select name="level2_role_id" id="edit_cat_level2_role" class="form-select"
                                            style="border-radius:10px;">
                                            <option value="0">- No Level 2 escalation -</option>
                                            <?php foreach ($staffRoles as $role): ?>
                                                <option value="<?= $role['role_id'] ?>">
                                                    <?= htmlspecialchars($role['role_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-bold small">
                                            Level 3 Escalation Role
                                            <span class="text-muted fw-normal">(final escalation tier, e.g. Dean of
                                                Students)</span>
                                        </label>
                                        <select name="level3_role_id" id="edit_cat_level3_role" class="form-select"
                                            style="border-radius:10px;">
                                            <option value="0">- No Level 3 escalation -</option>
                                            <?php foreach ($staffRoles as $role): ?>
                                                <option value="<?= $role['role_id'] ?>">
                                                    <?= htmlspecialchars($role['role_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-bold small">Status</label>
                                        <select name="status" id="edit_cat_status" class="form-select">
                                            <option value="active">Active</option>
                                            <option value="inactive">Inactive</option>
                                        </select>
                                    </div>
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
                            <h5 class="modal-title fw-bold text-white" id="sub_modal_title">
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
                                            <th class="text-center">ENDORSABLE</th>
                                            <th class="text-center">STATUS</th>
                                            <th class="text-center">COMPLAINTS</th>
                                            <th class="text-center">ACTION</th>
                                        </tr>
                                    </thead>
                                    <tbody id="subcategoryRows">
                                        <tr>
                                            <td colspan="7" class="text-center text-muted py-3">No subcategories yet.
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
                                            <?= csrf_field() ?>
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
                                            <div class="mb-3">
                                                <label class="form-label fw-bold small">
                                                    Default Staff Role
                                                    <span class="text-muted fw-normal">(optional - overrides the
                                                        category's role)</span>
                                                </label>
                                                <select name="default_role_id" class="form-select"
                                                    style="border-radius:8px;">
                                                    <option value="0">- Use category's default role -</option>
                                                    <?php foreach ($staffRoles as $role): ?>
                                                        <option value="<?= $role['role_id'] ?>">
                                                            <?= htmlspecialchars($role['role_name']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="mb-3 form-check">
                                                <input type="checkbox" class="form-check-input" name="leader_endorsable"
                                                    id="sub_leader_endorsable" value="1">
                                                <label class="form-check-label fw-bold small"
                                                    for="sub_leader_endorsable">
                                                    Can be endorsed by student leaders
                                                </label>
                                                <div class="form-text">Only takes effect if the parent category is also
                                                    endorsable.</div>
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
                            <h5 class="modal-title fw-bold text-white">
                                <i class="fas fa-edit me-2"></i>
                                EDIT SUBCATEGORY
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <form action="manage_categories.php" method="POST">
                            <?= csrf_field() ?>
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
                                    <label class="form-label fw-bold small">
                                        Default Staff Role
                                        <span class="text-muted fw-normal">(optional - overrides the category's
                                            role)</span>
                                    </label>
                                    <select name="default_role_id" id="edit_sub_role" class="form-select"
                                        style="border-radius:10px;">
                                        <option value="0">- Use category's default role -</option>
                                        <?php foreach ($staffRoles as $role): ?>
                                            <option value="<?= $role['role_id'] ?>">
                                                <?= htmlspecialchars($role['role_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" name="leader_endorsable"
                                        id="edit_sub_leader_endorsable" value="1">
                                    <label class="form-check-label fw-bold small" for="edit_sub_leader_endorsable">
                                        Can be endorsed by student leaders
                                    </label>
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
            document.getElementById('edit_cat_dept').value = cat.auto_assign_department_id || '0';
            document.getElementById('edit_cat_requires_dept').checked = !!parseInt(cat.requires_department_selection);
            document.getElementById('edit_cat_leader_endorsable').checked = !!parseInt(cat.leader_endorsable);
            document.getElementById('edit_cat_role').value = cat.default_role_id || '0';
            document.getElementById('edit_cat_level2_role').value = cat.level2_role_id || '0';
            document.getElementById('edit_cat_level3_role').value = cat.level3_role_id || '0';
            document.getElementById('edit_cat_priority').value = cat.default_priority || 'medium';
        }

        // Subcategory helpers 
        const allSubcategories = <?= json_encode($subcategories_grouped) ?>;
        const subById = {};
        Object.values(allSubcategories).forEach(arr => arr.forEach(s => { subById[s.subcategory_id] = s; }));

        function escHtml(str) {
            const d = document.createElement('div');
            d.textContent = str || '';
            return d.innerHTML;
        }

        const categoryEndorsable = <?= json_encode(array_column($categories, 'leader_endorsable', 'category_id')) ?>;

        function openSubcategories(categoryId, categoryName) {
            document.getElementById('sub_modal_title').textContent = 'Subcategories - ' + categoryName;
            document.getElementById('sub_category_id').value = categoryId;

            // Default new subcategories to match the parent category's endorsable flag
            document.getElementById('sub_leader_endorsable').checked = !!parseInt(categoryEndorsable[categoryId] || 0);

            // Collapse the add form if open
            const collapse = bootstrap.Collapse.getInstance(document.getElementById('addSubForm'));
            if (collapse) collapse.hide();

            const subs = allSubcategories[categoryId] || [];
            const tbody = document.getElementById('subcategoryRows');

            if (subs.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-3">No subcategories yet.</td></tr>';
            } else {
                tbody.innerHTML = subs.map((s, i) => `
                    <tr>
                        <td>${i + 1}</td>
                        <td>${escHtml(s.subcategory_name)}</td>
                        <td class="text-muted small">${escHtml(s.subcategory_description || '-')}</td>
                        <td class="text-center">
                            ${parseInt(s.leader_endorsable) ? '<span class="badge bg-success"><i class="fas fa-check"></i></span>' : '<span class="text-muted small">-</span>'}
                        </td>
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
            document.getElementById('edit_sub_role').value = sub.default_role_id || '0';
            document.getElementById('edit_sub_leader_endorsable').checked = !!parseInt(sub.leader_endorsable);
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
                    document.getElementById('deleteSubId').value = id;
                    document.getElementById('deleteSubForm').submit();
                }
            });
        }

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
                    document.getElementById('deleteCatId').value = id;
                    document.getElementById('deleteCatForm').submit();
                }
            });
        }
    </script>

    <?php $useDataTablesJs = true;
    include 'includes/foot_scripts.php'; ?>
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

    <form id="deleteCatForm" method="POST" action="manage_categories.php" style="display:none;">
        <?= csrf_field() ?>
        <input type="hidden" name="delete_category" id="deleteCatId" value="">
    </form>
    <form id="deleteSubForm" method="POST" action="manage_categories.php" style="display:none;">
        <?= csrf_field() ?>
        <input type="hidden" name="delete_subcategory" id="deleteSubId" value="">
    </form>
</body>

</html>