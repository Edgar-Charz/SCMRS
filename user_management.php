<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit;
} else {
    $adminId = $_SESSION['user_id'];
}

require_once "config/Database.php";
require_once "classes/User.php";
require_once "classes/Admin.php";

$message = $error = "";

$db = new Database();
$conn = $db->connect();
$admin = new Admin($conn);

// Handle Delete Student
if (isset($_GET['delete_student']) && is_numeric($_GET['delete_student'])) {
    $student_id = (int) $_GET['delete_student'];
    try {
        if ($admin->deleteStudent($student_id)) {
            $_SESSION['message'] = "Student deleted successfully.";
            header("Location: user_management.php#students");
            exit;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Handle Delete Staff
if (isset($_GET['delete_staff']) && is_numeric($_GET['delete_staff'])) {
    $staff_id = (int) $_GET['delete_staff'];
    try {
        if ($admin->deleteStaff($staff_id)) {
            $_SESSION['message'] = "Staff deleted successfully.";
            header("Location: user_management.php#staff");
            exit;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Handle Staff Approval
if (isset($_POST['approve_staff']) && is_numeric($_POST['approve_staff'])) {
    $staff_id = (int) $_POST['approve_staff'];
    $department_id = isset($_POST['staff_department']) ? (int) $_POST['staff_department'] : null;
    $role_id = isset($_POST['staff_role']) ? (int) $_POST['staff_role'] : null;
    try {
        if ($admin->approveStaff($staff_id, $department_id ?: null, $role_id ?: null)) {
            $_SESSION['message'] = "Staff approved successfully.";
            header("Location: user_management.php#approval");
            exit;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Handle Staff Rejection
if (isset($_GET['reject_staff']) && is_numeric($_GET['reject_staff'])) {
    $staff_id = (int) $_GET['reject_staff'];
    try {
        if ($admin->rejectStaff($staff_id)) {
            $_SESSION['message'] = "Staff rejected successfully.";
            header("Location: user_management.php#approval");
            exit;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Handle Demote Staff (set back to pending)
if (isset($_GET['demote_staff']) && is_numeric($_GET['demote_staff'])) {
    $staff_id = (int) $_GET['demote_staff'];
    try {
        $demote_stmt = $conn->prepare("UPDATE staffs SET staff_approval_status = '0' WHERE staff_user_id = ?");
        $demote_stmt->bind_param("i", $staff_id);
        if ($demote_stmt->execute()) {
            $_SESSION['message'] = "Staff demoted successfully.";
            header("Location: user_management.php#approval");
            exit;
        }
        $demote_stmt->close();
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Handle Add Student
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['addStudentBTN'])) {
    $username = trim($_POST['student_username'] ?? '');
    $email = trim($_POST['student_email'] ?? '');
    $regNum = trim($_POST['student_reg_number'] ?? '');
    $collegeId = (int) ($_POST['student_college_id'] ?? 0);
    $phone = trim($_POST['student_phone'] ?? '') ?: null;
    $program = trim($_POST['student_program'] ?? '') ?: null;
    $password = $_POST['student_password'] ?? '';
    $confirm = $_POST['student_confirm_password'] ?? '';
    try {
        if (!$username || !$email || !$regNum || !$password) {
            $_SESSION['message_error'] = "Name, email, registration number and password are required.";
        } elseif ($password !== $confirm) {
            $_SESSION['message_error'] = "Passwords do not match.";
        } else {
            $admin->addStudent($username, $email, $password, $regNum, $collegeId ?: null, $phone, $program);
            $_SESSION['message'] = "Student '{$username}' added successfully.";
        }
    } catch (Exception $e) {
        $_SESSION['message_error'] = $e->getMessage();
    }
    header("Location: user_management.php#students");
    exit;
}

// Handle Add Staff
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['addStaffBTN'])) {
    $username = trim($_POST['staff_username'] ?? '');
    $email = trim($_POST['staff_email'] ?? '');
    $staffIdNum = trim($_POST['staff_id_number'] ?? '') ?: null;
    $phone = trim($_POST['staff_phone'] ?? '') ?: null;
    $deptId = (int) ($_POST['staff_dept_id'] ?? 0);
    $roleId = (int) ($_POST['staff_role_id'] ?? 0);
    $password = $_POST['staff_password'] ?? '';
    $confirm = $_POST['staff_confirm_password'] ?? '';
    try {
        if (!$username || !$email || !$password) {
            $_SESSION['message_error'] = "Name, email and password are required.";
        } elseif ($password !== $confirm) {
            $_SESSION['message_error'] = "Passwords do not match.";
        } else {
            $admin->addStaffAccount($username, $email, $password, $deptId ?: null, $staffIdNum, $phone, $roleId ?: null);
            $_SESSION['message'] = "Staff '{$username}' added and approved successfully.";
        }
    } catch (Exception $e) {
        $_SESSION['message_error'] = $e->getMessage();
    }
    header("Location: user_management.php#staff");
    exit;
}

// Handle Add Role
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_role'])) {
    $name = trim($_POST['role_name'] ?? '');
    $rank = (int) ($_POST['role_rank'] ?? 0);
    try {
        if ($name && $rank > 0) {
            $admin->addStaffRole($name, $rank);
            $_SESSION['message'] = "Role '{$name}' added successfully.";
        } else {
            $_SESSION['message_error'] = "Role name and rank are required.";
        }
    } catch (Exception $e) {
        $_SESSION['message_error'] = $e->getMessage();
    }
    header("Location: user_management.php#roles");
    exit;
}

// Handle Edit Role
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_role'])) {
    $id = (int) ($_POST['role_id'] ?? 0);
    $name = trim($_POST['role_name'] ?? '');
    $rank = (int) ($_POST['role_rank'] ?? 0);
    try {
        if ($id && $name && $rank > 0) {
            $admin->updateStaffRole($id, $name, $rank);
            $_SESSION['message'] = "Role updated successfully.";
        } else {
            $_SESSION['message_error'] = "All fields are required.";
        }
    } catch (Exception $e) {
        $_SESSION['message_error'] = $e->getMessage();
    }
    header("Location: user_management.php#roles");
    exit;
}

// Handle Delete Role
if (isset($_GET['delete_role']) && is_numeric($_GET['delete_role'])) {
    try {
        $admin->deleteStaffRole((int) $_GET['delete_role']);
        $_SESSION['message'] = "Role deleted successfully.";
    } catch (Exception $e) {
        $_SESSION['message_error'] = $e->getMessage();
    }
    header("Location: user_management.php#roles");
    exit;
}

// Handle Assign Role
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_role'])) {
    $staffUserId = (int) ($_POST['assign_staff_user_id'] ?? 0);
    $roleId = (int) ($_POST['assign_role_id'] ?? 0) ?: null;
    try {
        if ($staffUserId) {
            $admin->assignStaffRole($staffUserId, $roleId);
            $_SESSION['message'] = "Role assigned successfully.";
        }
    } catch (Exception $e) {
        $_SESSION['message_error'] = $e->getMessage();
    }
    header("Location: user_management.php#staff");
    exit;
}

// Get data
$registered_students = $admin->getAllStudents();
$registered_staffs = $admin->getAllStaff();
$pending_staffs = $admin->getPendingStaffApprovals();
$approved_staffs = $admin->getApprovedStaff();
$pending_count = $admin->getPendingApprovalsCount();
$approved_count = count($approved_staffs);
$departments = $admin->getAllDepartments();
$colleges = $admin->getAllColleges();
$staff_roles = $admin->getAllStaffRolesWithCount();

// Get message from session
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
    <title>Admin Dashboard</title>
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
                    <p class="mb-0 small fw-bold">
                        <?= strtoupper($_SESSION['user_role']); ?>
                    </p>
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

            <div class="p-4">

                <!-- <h4 class="mb-1">Dashboard Analytics</h4> -->
                <nav aria-label="breadcrumb" class="d-flex justify-content-between align-items-center p-2">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item">
                            <a href="#"><i class="fa fa-users" style="color: black;"></i></a>
                        </li>
                        <li class="breadcrumb-item active">Admin / User Management</li>
                    </ol>
                </nav>

                <ul class="nav nav-pills mb-4 p-2 bg-white shadow-sm rounded-4" id="userManagementTabs" role="tablist">
                    <li class="nav-item me-2" role="presentation">
                        <button class="nav-link fw-bold" onclick="switchTab('students')" id="tab-students">
                            <i class="fas fa-user me-2"></i>
                            Students List
                        </button>
                    </li>

                    <li class="nav-item me-2" role="presentation">
                        <button class="nav-link fw-bold" onclick="switchTab('staff')" id="tab-staff">
                            <i class="fas fa-user me-2"></i>
                            Staffs List
                        </button>
                    </li>

                    <li class="nav-item me-2" role="presentation">
                        <button class="nav-link fw-bold position-relative" onclick="switchTab('approval')"
                            id="tab-approval">
                            <i class="fas fa-check me-2"></i>
                            Staff Approvals
                            <span
                                class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"><?= !empty($pending_count) ? $pending_count : 0 ?></span>
                        </button>
                    </li>

                    <li class="nav-item me-2" role="presentation">
                        <button class="nav-link fw-bold" onclick="switchTab('roles')" id="tab-roles">
                            <i class="fas fa-id-badge me-2"></i>
                            Staff Roles
                        </button>
                    </li>
                </ul>

                <!-- Manage students -->
                <div id="students-section" class="management-content d-none">
                    <div class="container-card border-0 shadow-sm p-4">

                        <div class="d-flex justify-content-between align-items-center p-2">
                            <h4 class="mb-1 fw-bold" style="color: var(--udsm-blue);">
                                <i class="fas fa-users me-2"></i>
                                Registered Students
                            </h4>
                            <button type="button" class="btn btn-add" data-bs-toggle="modal"
                                data-bs-target="#addStudentModal">
                                <i class="fas fa-plus"></i>
                                Add New Student
                            </button>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-stripped" id="studentsTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th class="text-center">STUDENT NAME</th>
                                        <th class="text-center">REG. NUMBER</th>
                                        <th class="text-center">ACTION</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <?php if (!empty($registered_students)): ?>
                                        <?php $n = 1;
                                        foreach ($registered_students as $student): ?>
                                            <tr>
                                                <td><?php echo $n++; ?></td>
                                                <td class="text-center">
                                                    <?php echo htmlspecialchars($student['username']); ?>
                                                </td>
                                                <td class="text-center">
                                                    <?php echo htmlspecialchars($student['student_registration_number']); ?>
                                                </td>
                                                <td class="text-center">
                                                    <div class="d-flex justify-content-center">
                                                        <!-- View Button -->
                                                        <button type="button" class="btn btn-status btn-outline-secondary me-2"
                                                            data-bs-toggle="modal" data-bs-target="#viewStudent"
                                                            onclick="viewStudent(<?php echo htmlspecialchars(json_encode($student)); ?>)"
                                                            title="view">
                                                            <i class="fas fa-eye text-dark"></i>
                                                        </button>

                                                        <!-- Delete Button -->
                                                        <button type="button" class="btn btn-status btn-outline-secondary me-2"
                                                            onclick="confirmDeleteStudent(<?php echo $student['user_id']; ?>)"
                                                            title="delete">
                                                            <i class="fas fa-trash text-dark"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="3" class="text-center py-4">No students registered yet</td>
                                        </tr>
                                    <?php endif; ?>

                                    <!-- View Student Modal -->
                                    <div class="modal fade" id="viewStudent" tabindex="-1"
                                        aria-labelledby="viewStudentModalLabel" aria-hidden="true">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content shadow-lg rounded-3">
                                                <div class="modal-header text-white"
                                                    style="background:linear-gradient(135deg,#1e3a5f,#2d6a9f);">
                                                    <h5 class="modal-title fw-bold" id="viewStudentModalLabel">
                                                        <i class="fas fa-user-graduate me-2"></i>
                                                        STUDENT INFORMATION
                                                    </h5>
                                                    <button type="button" class="btn-close btn-close-white"
                                                        data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>

                                                <div class="modal-body">
                                                    <div class="container-fluid">
                                                        <div class="row g-2">

                                                            <!-- Student Info -->
                                                            <div class="col-lg-6 col-sm-12 col-12">
                                                                <label class="form-label fw-bold">Student Name</label>
                                                                <p class="form-control" id="modal_student_name">-</p>
                                                            </div>
                                                            <div class="col-lg-6 col-sm-12 col-12">
                                                                <label class="form-label fw-bold">Registration
                                                                    Number</label>
                                                                <p class="form-control" id="modal_student_regnum">-</p>
                                                            </div>
                                                            <div class="col-lg-6 col-sm-12 col-12">
                                                                <label class="form-label fw-bold">Email Address</label>
                                                                <p class="form-control" id="modal_student_email">-</p>
                                                            </div>
                                                            <div class="col-lg-6 col-sm-12 col-12">
                                                                <label class="form-label fw-bold">College</label>
                                                                <p class="form-control" id="modal_student_college">-</p>
                                                            </div>

                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Footer -->
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary"
                                                        data-bs-dismiss="modal">Close</button>
                                                </div>

                                            </div>
                                        </div>
                                    </div>
                                    <!-- /View Student Modal -->

                                    <!-- Edit Student Modal -->
                                    <div class="modal fade" id="editStudent" tabindex="-1"
                                        aria-labelledby="editStudentModalLabel" aria-hidden="true">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content shadow-lg rounded-3">
                                                <div class="modal-header text-white"
                                                    style="background: linear-gradient(135deg, #007bff, #0056b3);">
                                                    <h5 class="modal-title fw-bold" id="editStudentModalLabel">
                                                        <i class="fas fa-user-edit me-2"></i>
                                                        EDIT STUDENT INFORMATION
                                                    </h5>
                                                    <button type="button" class="btn-close btn-close-white"
                                                        data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>

                                                <form action="" method="POST" id="update-student-form"
                                                    enctype="multipart/form-data">
                                                    <div class="modal-body">
                                                        <div class="container-fluid">
                                                            <div class="row g-2">
                                                                <!-- Student Info -->
                                                                <div class="col-lg-6 col-sm-12 col-12">
                                                                    <label class="form-label fw-bold">Student
                                                                        Name</label>
                                                                    <input type="text" name="" class="form-control"
                                                                        value="" required>
                                                                </div>
                                                                <div class="col-lg-6 col-sm-12 col-12">
                                                                    <label class="form-label fw-bold">Registration
                                                                        Number</label>
                                                                    <input type="text" name="" class="form-control"
                                                                        value="" required>
                                                                </div>
                                                                <div class="col-lg-6 col-sm-12 col-12">
                                                                    <label class="form-label fw-bold">Email
                                                                        Address</label>
                                                                    <input type="text" name="" class="form-control"
                                                                        value="" required>
                                                                </div>
                                                                <div class="col-lg-6 col-sm-12 col-12">
                                                                    <label class="form-label fw-bold">College</label>
                                                                    <select name="" id="" class="form-select">
                                                                        <option value="">CoICT</option>
                                                                        <option value="">UDBS</option>
                                                                        <option value="">SJMC</option>
                                                                    </select>
                                                                </div>

                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="modal-footer">
                                                        <button type="submit" name="updateStudentBTN"
                                                            class="btn btn-success me-2">Save changes</button>
                                                        <button type="button" class="btn btn-secondary"
                                                            data-bs-dismiss="modal">Close</button>
                                                    </div>


                                                </form>
                                                <!-- /Edit Student Form -->
                                            </div>
                                        </div>
                                    </div>

                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Add Student Modal -->
                    <div class="modal fade" id="addStudentModal" tabindex="-1" aria-labelledby="addStudentModalLabel"
                        aria-hidden="true">
                        <div class="modal-dialog modal-lg modal-dialog-centered">
                            <div class="modal-content border-0 shadow-lg" style="border-radius:12px; overflow:hidden;">
                                <div class="modal-header text-white"
                                    style="background:linear-gradient(135deg,#1e3a5f,#2d6a9f);">
                                    <h5 class="modal-title fw-bold" id="addStudentModalLabel">
                                        <i class="fas fa-user-graduate me-2"></i>
                                        Add New Student
                                    </h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                                        aria-label="Close"></button>
                                </div>

                                <form action="user_management.php" method="POST">
                                    <div class="modal-body p-4">
                                        <div class="row g-3">
                                            <div class="col-12 col-md-6">
                                                <label class="form-label fw-bold small">Full Name <span
                                                        class="text-danger">*</span></label>
                                                <input type="text" name="student_username" class="form-control"
                                                    placeholder="Enter full name" required>
                                            </div>
                                            <div class="col-12 col-md-6">
                                                <label class="form-label fw-bold small">Registration Number <span
                                                        class="text-danger">*</span></label>
                                                <input type="text" name="student_reg_number" class="form-control"
                                                    placeholder="202X-04-XXXXX" pattern="^202[0-9]-04-[0-9]{5}$"
                                                    title="Format: 202X-04-XXXXX" maxlength="13" required
                                                    oninput="this.value=this.value.toUpperCase().replace(/[^0-9-]/g,'').slice(0,13)">
                                            </div>
                                            <div class="col-12 col-md-6">
                                                <label class="form-label fw-bold small">Email Address <span
                                                        class="text-danger">*</span></label>
                                                <input type="email" name="student_email" class="form-control"
                                                    placeholder="email@udsm.ac.tz" required>
                                            </div>
                                            <div class="col-12 col-md-6">
                                                <label class="form-label fw-bold small">Phone Number</label>
                                                <input type="text" name="student_phone" class="form-control"
                                                    placeholder="0XXXXXXXXX" pattern="^0[0-9]{9}$"
                                                    title="10 digits starting with 0" maxlength="10"
                                                    oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,10)">
                                            </div>
                                            <div class="col-12 col-md-6">
                                                <label class="form-label fw-bold small">College</label>
                                                <select name="student_college_id" class="form-select">
                                                    <option value="">-- Select College --</option>
                                                    <?php foreach ($colleges as $col): ?>
                                                        <option value="<?= $col['college_id'] ?>">
                                                            <?= htmlspecialchars($col['college_name']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-12 col-md-6">
                                                <label class="form-label fw-bold small">Programme / Course</label>
                                                <input type="text" name="student_program" class="form-control"
                                                    placeholder="e.g. BSc Computer Science">
                                            </div>
                                            <div class="col-12 col-md-6">
                                                <label class="form-label fw-bold small">Password <span
                                                        class="text-danger">*</span></label>
                                                <input type="password" name="student_password" id="addStudentPwd"
                                                    class="form-control" placeholder="Min 8 characters" required>
                                            </div>
                                            <div class="col-12 col-md-6">
                                                <label class="form-label fw-bold small">Confirm Password <span
                                                        class="text-danger">*</span></label>
                                                <input type="password" name="student_confirm_password"
                                                    id="addStudentPwdConfirm" class="form-control"
                                                    placeholder="Re-enter password" required>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer" style="background:#f8f9fa;">
                                        <button type="button" class="btn btn-outline-secondary"
                                            data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" name="addStudentBTN" class="btn btn-success fw-bold px-4"
                                            onclick="return checkPasswords('addStudentPwd','addStudentPwdConfirm')">
                                            <i class="fas fa-user-plus me-1"></i>Add Student
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <!-- / Add Student modal -->
                </div>

                <!-- Manage staffs -->
                <div id="staff-section" class="management-content d-none">
                    <div class="container-card border-0 shadow-sm p-4">

                        <div class="d-flex justify-content-between align-items-center p-2">
                            <h4 class="mb-1 fw-bold" style="color: var(--udsm-blue);">
                                <i class="fas fa-users me-2"></i>
                                Registered Staffs
                            </h4>
                            <button type="button" class="btn btn-add" data-bs-toggle="modal"
                                data-bs-target="#addStaffModal">
                                <i class="fas fa-plus"></i>
                                Add New Staff
                            </button>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-stripped" id="staffsTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th class="text-center">STAFF NAME</th>
                                        <th class="text-center">DEPARTMENT</th>
                                        <th class="text-center">ROLE</th>
                                        <th class="text-center">ACTION</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($registered_staffs)): ?>
                                        <?php $counter = 1;
                                        foreach ($registered_staffs as $staff): ?>
                                            <tr>
                                                <td><?php echo $counter++; ?></td>
                                                <td class="text-center"><?php echo htmlspecialchars($staff['username']); ?></td>
                                                <td class="text-center">
                                                    <?php echo htmlspecialchars($staff['department_name'] ?? 'N/A'); ?>
                                                </td>
                                                <td class="text-center">
                                                    <?php if (!empty($staff['role_name'])): ?>
                                                        <span
                                                            class="badge bg-primary"><?= htmlspecialchars($staff['role_name']) ?></span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Unassigned</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <div class="d-flex justify-content-center">
                                                        <!-- View Button -->
                                                        <button type="button" class="btn btn-status btn-outline-secondary me-2"
                                                            data-bs-toggle="modal" data-bs-target="#viewStaff"
                                                            onclick="viewStaff(<?php echo htmlspecialchars(json_encode($staff)); ?>)"
                                                            title='view'>
                                                            <i class="fas fa-eye text-dark"></i>
                                                        </button>

                                                        <!-- Assign Role Button -->
                                                        <button type="button" class="btn btn-status btn-outline-secondary me-2"
                                                            data-bs-toggle="modal" data-bs-target="#assignRoleModal"
                                                            onclick="openAssignRole(<?= $staff['user_id'] ?>, '<?= htmlspecialchars($staff['username'], ENT_QUOTES) ?>', <?= $staff['staff_role_id'] ?: 'null' ?>)"
                                                            title="assign role">
                                                            <i class="fas fa-id-badge text-dark"></i>
                                                        </button>

                                                        <!-- Delete Button -->
                                                        <button type="button" class="btn btn-status btn-outline-secondary me-2"
                                                            onclick="confirmDeleteStaff(<?php echo $staff['user_id']; ?>)"
                                                            title="delete">
                                                            <i class="fas fa-trash text-dark"></i>
                                                        </button>
                                                    </div>

                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-4">No staff registered yet</td>
                                        </tr>
                                    <?php endif; ?>

                                    <!-- View Staff Modal -->
                                    <div class="modal fade" id="viewStaff" tabindex="-1"
                                        aria-labelledby="viewStaffModalLabel" aria-hidden="true">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content shadow-lg rounded-3">
                                                <div class="modal-header text-white"
                                                    style="background:linear-gradient(135deg,#1e3a5f,#2d6a9f);">
                                                    <h5 class="modal-title fw-bold" id="viewStaffModalLabel">
                                                        <i class="fas fa-user-tie me-2"></i>
                                                        STAFF INFORMATION
                                                    </h5>
                                                    <button type="button" class="btn-close btn-close-white"
                                                        data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>

                                                <div class="modal-body">
                                                    <div class="container-fluid">
                                                        <div class="row g-2">

                                                            <!-- Staff Info -->
                                                            <div class="col-lg-6 col-sm-12 col-12">
                                                                <label class="form-label fw-bold">Staff Name</label>
                                                                <p class="form-control" id="modal_staff_name">-</p>
                                                            </div>
                                                            <div class="col-lg-6 col-sm-12 col-12">
                                                                <label class="form-label fw-bold">Email Address</label>
                                                                <p class="form-control" id="modal_staff_email">-</p>
                                                            </div>
                                                            <div class="col-lg-6 col-sm-12 col-12">
                                                                <label class="form-label fw-bold">Department</label>
                                                                <p class="form-control" id="modal_staff_department">-
                                                                </p>
                                                            </div>
                                                            <div class="col-lg-6 col-sm-12 col-12">
                                                                <label class="form-label fw-bold">Status</label>
                                                                <p class="form-control" id="modal_staff_status">-</p>
                                                            </div>

                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Footer -->
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary"
                                                        data-bs-dismiss="modal">Close</button>
                                                </div>

                                            </div>
                                        </div>
                                    </div>
                                    <!-- /View Staff Modal -->

                                    <!-- Edit Staff Modal -->
                                    <div class="modal fade" id="editStaff" tabindex="-1"
                                        aria-labelledby="editStaffModalLabel" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content shadow-lg rounded-3">
                                                <div class="modal-header bg-secondary text-white">
                                                    <h5 class="modal-title fw-bold" id="editStaffModalLabel">
                                                        EDIT STAFF INFORMATION
                                                    </h5>
                                                    <button type="button" class="btn-close btn-close-white"
                                                        data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>

                                                <form action="" method="POST" id="update-staff-form"
                                                    enctype="multipart/form-data">
                                                    <div class="modal-body">
                                                        <div class="container-fluid">
                                                            <div class="row g-2">
                                                                <!-- Student Info -->
                                                                <div class="mb-2">
                                                                    <label class="form-label fw-bold">Staff Name</label>
                                                                    <input type="text" name="" class="form-control"
                                                                        value="" required>
                                                                </div>
                                                                <div class="mb-2">
                                                                    <label class="form-label fw-bold">Email
                                                                        Address</label>
                                                                    <input type="email" name="" class="form-control"
                                                                        value="" required>
                                                                </div>
                                                                <div class="mb-2">
                                                                    <label class="form-label fw-bold">Department</label>
                                                                    <select name="" id="" class="form-select">
                                                                        <option value="">IT</option>
                                                                        <option value="">Maintenance</option>
                                                                        <option value="">Accomodation</option>
                                                                    </select>
                                                                </div>

                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="modal-footer">
                                                        <button type="submit" name="updateStudentBTN"
                                                            class="btn btn-success me-2">Save changes</button>
                                                        <button type="button" class="btn btn-secondary"
                                                            data-bs-dismiss="modal">Close</button>
                                                    </div>


                                                </form>
                                                <!-- /Edit Student Form -->
                                            </div>
                                        </div>
                                    </div>
                                </tbody>
                            </table>
                        </div>

                    </div>

                    <!-- Add Staff Modal -->
                    <div class="modal fade" id="addStaffModal" tabindex="-1" aria-labelledby="addStaffModalLabel"
                        aria-hidden="true">
                        <div class="modal-dialog modal-lg modal-dialog-centered">
                            <div class="modal-content border-0 shadow-lg" style="border-radius:12px; overflow:hidden;">
                                <div class="modal-header text-white"
                                    style="background:linear-gradient(135deg,#1e3a5f,#2d6a9f);">
                                    <h5 class="modal-title fw-bold" id="addStaffModalLabel">
                                        <i class="fas fa-user-tie me-2"></i>
                                        Add New Staff
                                    </h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                                        aria-label="Close"></button>
                                </div>

                                <form action="user_management.php" method="POST">
                                    <div class="modal-body p-4">
                                        <div class="row g-3">
                                            <div class="col-12 col-md-6">
                                                <label class="form-label fw-bold small">Full Name <span
                                                        class="text-danger">*</span></label>
                                                <input type="text" name="staff_username" class="form-control"
                                                    placeholder="Enter full name" required>
                                            </div>
                                            <div class="col-12 col-md-6">
                                                <label class="form-label fw-bold small">Staff ID <span
                                                        class="text-danger">*</span></label>
                                                <input type="text" name="staff_id_number" class="form-control"
                                                    placeholder="UDSM-STAFF-XXXXX" pattern="^UDSM-STAFF-[0-9]{5}$"
                                                    title="Format: UDSM-STAFF-XXXXX" maxlength="16" minlength="16"
                                                    oninput="this.value=this.value.toUpperCase().replace(/[^A-Z0-9-]/g,'').slice(0,16)"
                                                    required>
                                            </div>
                                            <div class="col-12 col-md-6">
                                                <label class="form-label fw-bold small">Email Address <span
                                                        class="text-danger">*</span></label>
                                                <input type="email" name="staff_email" class="form-control"
                                                    placeholder="staff@udsm.ac.tz" required>
                                            </div>
                                            <div class="col-12 col-md-6">
                                                <label class="form-label fw-bold small">Phone Number</label>
                                                <input type="text" name="staff_phone" class="form-control"
                                                    placeholder="0XXXXXXXXX" pattern="^0[0-9]{9}$"
                                                    title="10 digits starting with 0" maxlength="10"
                                                    oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,10)">
                                            </div>
                                            <div class="col-12 col-md-6">
                                                <label class="form-label fw-bold small">Department</label>
                                                <select name="staff_dept_id" class="form-select">
                                                    <option value="">-- Select Department --</option>
                                                    <?php foreach ($departments as $dept): ?>
                                                        <option value="<?= $dept['department_id'] ?>">
                                                            <?= htmlspecialchars($dept['department_name']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-12 col-md-6">
                                                <label class="form-label fw-bold small">Role</label>
                                                <select name="staff_role_id" class="form-select">
                                                    <option value="">-- Select Role --</option>
                                                    <?php foreach ($staff_roles as $role): ?>
                                                        <option value="<?= $role['role_id'] ?>">
                                                            <?= htmlspecialchars($role['role_name']) ?>
                                                            (Rank <?= $role['role_rank'] ?>)
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-12 col-md-6">
                                                <label class="form-label fw-bold small">Password <span
                                                        class="text-danger">*</span></label>
                                                <input type="password" name="staff_password" id="addStaffPwd"
                                                    class="form-control" placeholder="Min 8 characters" required>
                                            </div>
                                            <div class="col-12 col-md-6">
                                                <label class="form-label fw-bold small">Confirm Password <span
                                                        class="text-danger">*</span></label>
                                                <input type="password" name="staff_confirm_password"
                                                    id="addStaffPwdConfirm" class="form-control"
                                                    placeholder="Re-enter password" required>
                                            </div>
                                        </div>
                                        <p class="text-muted small mt-3 mb-0">
                                            <i class="fas fa-info-circle me-1"></i>
                                            Staff accounts added here are automatically approved and active.
                                        </p>
                                    </div>
                                    <div class="modal-footer" style="background:#f8f9fa;">
                                        <button type="button" class="btn btn-outline-secondary"
                                            data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" name="addStaffBTN" class="btn btn-success fw-bold px-4"
                                            onclick="return checkPasswords('addStaffPwd','addStaffPwdConfirm')">
                                            <i class="fas fa-user-plus me-1"></i>Add Staff
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <!-- / Add Staff modal -->

                </div>

                <!-- Staff approvals -->
                <div id="approval-section" class="management-content d-none">
                    <!-- <div class="container-card border-0 shadow-sm p-4">

                        <div class="d-flex justify-content-between align-items-center p-2">
                            <h4 class="mb-1 fw-bold" style="color: var(--udsm-blue);">
                                <i class="fas fa-check me-2"></i>
                                Staff Approvals
                            </h4>
                        </div>
                    </div> -->

                    <div class="row g-3 mb-4">
                        <div class="col12 col-md-6 col-lg-6">
                            <div
                                class="stat-card bg-stat p-3 d-flex align-items-center justify-content-between shadow-sm">
                                <i class="fas fa-clock fa-2x"></i>
                                <div class="text-end">
                                    <h2 class="mb-0"><?php echo $pending_count; ?></h2>
                                    <p class="mb-0 fw-bold">PENDING APPROVAL</p>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-md-6 col-lg-6">
                            <div
                                class="stat-card bg-success p-3 d-flex align-items-center justify-content-between shadow-sm">
                                <i class="fas fa-check-circle fa-2x" style="color: black;"></i>
                                <div class="text-end">
                                    <h2 class="mb-0"><?php echo $approved_count; ?></h2>
                                    <p class="mb-0 fw-bold">TOTAL APPROVED</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="container-card shadow-sm mb-4">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h4 class="mb-0 fw-bold"><i class="fas fa-clock me-2"></i>Pending Approval</h4>
                            <?php if (!empty($pending_staffs)): ?>
                                <span class="badge bg-warning text-dark fs-6"><?= count($pending_staffs) ?></span>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($pending_staffs)): ?>
                            <?php foreach ($pending_staffs as $pending_staff): ?>
                                <div class="border rounded-3 mb-4 overflow-hidden shadow-sm">

                                    <!-- Profile header -->
                                    <div class="d-flex align-items-center gap-3 p-3"
                                        style="background:#f8f9fa; border-bottom:1px solid #e9ecef;">
                                        <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold text-white flex-shrink-0"
                                            style="width:52px; height:52px; background:var(--udsm-blue); font-size:1.1rem; letter-spacing:1px;">
                                            <?= strtoupper(substr($pending_staff['username'], 0, 2)) ?>
                                        </div>
                                        <div class="flex-grow-1 min-w-0">
                                            <div class="fw-bold fs-6"><?= htmlspecialchars($pending_staff['username']) ?></div>
                                            <div class="text-muted small font-monospace">
                                                <?= htmlspecialchars($pending_staff['staff_id']) ?>
                                            </div>
                                        </div>
                                        <span class="badge bg-warning text-dark flex-shrink-0">Pending</span>
                                    </div>

                                    <!-- Registration details -->
                                    <div class="p-3">
                                        <div class="row g-3 mb-3">
                                            <div class="col-12 col-sm-4">
                                                <div class="small text-muted fw-semibold mb-1"><i
                                                        class="fas fa-envelope me-1"></i>Email</div>
                                                <div class="small"><?= htmlspecialchars($pending_staff['user_email']) ?></div>
                                            </div>
                                            <div class="col-12 col-sm-4">
                                                <div class="small text-muted fw-semibold mb-1"><i
                                                        class="fas fa-phone me-1"></i>Phone</div>
                                                <div class="small">
                                                    <?= !empty($pending_staff['user_phone_number'])
                                                        ? htmlspecialchars($pending_staff['user_phone_number'])
                                                        : '<span class="text-muted fst-italic">Not provided</span>' ?>
                                                </div>
                                            </div>
                                            <div class="col-12 col-sm-4">
                                                <div class="small text-muted fw-semibold mb-1"><i
                                                        class="fas fa-calendar-alt me-1"></i>Registered</div>
                                                <div class="small">
                                                    <?= date('d M Y, g:i A', strtotime($pending_staff['created_at'])) ?>
                                                </div>
                                            </div>
                                        </div>

                                        <?php if (!empty($pending_staff['department_name'])): ?>
                                            <div class="alert alert-info py-2 px-3 mb-3 small mb-0">
                                                <i class="fas fa-info-circle me-1"></i>
                                                Requested department during registration:
                                                <strong><?= htmlspecialchars($pending_staff['department_name']) ?></strong>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Approval form -->
                                        <form action="" method="POST" class="mt-3">
                                            <div class="row g-3 mb-3">
                                                <div class="col-12 col-md-6">
                                                    <label class="form-label fw-bold small">Assign Department</label>
                                                    <select name="staff_department" class="form-select">
                                                        <option value="">-- Select Department --</option>
                                                        <?php foreach ($departments as $dept): ?>
                                                            <option value="<?= $dept['department_id'] ?>" <?= (int) ($pending_staff['staff_department_id'] ?? 0) === (int) $dept['department_id'] ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars($dept['department_name']) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="col-12 col-md-6">
                                                    <label class="form-label fw-bold small">Assign Role</label>
                                                    <select name="staff_role" class="form-select">
                                                        <option value="">-- Select Role --</option>
                                                        <?php foreach ($staff_roles as $role): ?>
                                                            <option value="<?= $role['role_id'] ?>">
                                                                <?= htmlspecialchars($role['role_name']) ?>
                                                                (Rank <?= $role['role_rank'] ?>)
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="d-flex gap-2">
                                                <button type="submit" name="approve_staff"
                                                    value="<?= $pending_staff['user_id'] ?>"
                                                    class="btn btn-success fw-bold flex-fill p-2" style="border-radius:8px;">
                                                    <i class="fas fa-check me-1"></i>Approve
                                                </button>
                                                <a href="?reject_staff=<?= $pending_staff['user_id'] ?>"
                                                    class="btn btn-danger fw-bold flex-fill p-2" style="border-radius:8px;"
                                                    onclick="return confirm('Reject this staff member? Their account will be permanently deleted.')">
                                                    <i class="fas fa-times me-1"></i>Reject
                                                </a>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-center text-muted py-4">
                                <i class="fas fa-check-circle me-2 text-success"></i>No pending staff approvals
                            </p>
                        <?php endif; ?>
                    </div>

                    <div class="container-card shadow-sm">
                        <h4 class="mb-1 fw-bold"><i class="fas fa-users me-2"></i>Recently Approved Staffs</h4>

                        <div class="table-responsive">
                            <table class="table table-stripped" id="departmentsTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th class="text-center">STAFF NAME</th>
                                        <th class="text-center">DEPARTMENT</th>
                                        <th class="text-center">ACTION</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($approved_staffs)): ?>
                                        <?php $counter = 1;
                                        foreach ($approved_staffs as $staff): ?>
                                            <tr>
                                                <td><?php echo $counter++; ?></td>
                                                <td class="text-center"><?php echo htmlspecialchars($staff['username']); ?></td>
                                                <td class="text-center">
                                                    <?php echo htmlspecialchars($staff['department_name'] ?? 'N/A'); ?>
                                                </td>
                                                <td class="text-center">
                                                    <div class="d-flex justify-content-center">
                                                        <!-- View Button -->
                                                        <button type="button" class="btn btn-status btn-outline-secondary me-2"
                                                            data-bs-toggle="modal" data-bs-target="#viewApprovedStaff"
                                                            onclick="viewApprovedStaff(<?php echo htmlspecialchars(json_encode($staff)); ?>)"
                                                            title='view'>
                                                            <i class="fas fa-eye text-dark"></i>
                                                        </button>

                                                        <!-- Demote Button -->
                                                        <button type="button" class="btn btn-status btn-outline-secondary me-2"
                                                            onclick="confirmDemoteStaff(<?php echo $staff['user_id']; ?>)"
                                                            title="demote">
                                                            <i class="fas fa-user-minus text-dark"></i>
                                                        </button>
                                                    </div>

                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-4">No approved staff yet</td>
                                        </tr>
                                    <?php endif; ?>

                                    <!-- View Approved Staff Modal -->
                                    <div class="modal fade" id="viewApprovedStaff" tabindex="-1"
                                        aria-labelledby="viewApprovedStaffModalLabel" aria-hidden="true">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content shadow-lg rounded-3">
                                                <div class="modal-header text-white"
                                                    style="background:linear-gradient(135deg,#1e3a5f,#2d6a9f);">
                                                    <h5 class="modal-title fw-bold" id="viewApprovedStaffModalLabel">
                                                        <i class="fas fa-user-tie me-2"></i>
                                                        STAFF INFORMATION
                                                    </h5>
                                                    <button type="button" class="btn-close btn-close-white"
                                                        data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>

                                                <div class="modal-body">
                                                    <div class="container-fluid">
                                                        <div class="row g-2">

                                                            <!-- Staff Info -->
                                                            <div class="col-lg-6 col-sm-12 col-12">
                                                                <label class="form-label fw-bold">Staff Name</label>
                                                                <p class="form-control" id="astaff_name">-</p>
                                                            </div>
                                                            <div class="col-lg-6 col-sm-12 col-12">
                                                                <label class="form-label fw-bold">Role</label>
                                                                <p class="form-control" id="astaff_role">-</p>
                                                            </div>
                                                            <div class="col-lg-6 col-sm-12 col-12">
                                                                <label class="form-label fw-bold">Email Address</label>
                                                                <p class="form-control" id="astaff_email">-</p>
                                                            </div>
                                                            <div class="col-lg-6 col-sm-12 col-12">
                                                                <label class="form-label fw-bold">Department</label>
                                                                <p class="form-control" id="astaff_dept">-</p>
                                                            </div>
                                                            <div class="col-lg-6 col-sm-12 col-12">
                                                                <label class="form-label fw-bold">Approved At</label>
                                                                <p class="form-control" id="astaff_approved_at">-</p>
                                                            </div>
                                                            <div class="col-lg-6 col-sm-12 col-12">
                                                                <label class="form-label fw-bold">Status</label>
                                                                <p class="form-control" id="astaff_status">-</p>
                                                            </div>

                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Footer -->
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary"
                                                        data-bs-dismiss="modal">Close</button>
                                                </div>

                                            </div>
                                        </div>
                                    </div>
                                    <!-- /View Staff Modal -->

                                    <!-- Edit Staff Modal -->
                                    <div class="modal fade" id="editStaff" tabindex="-1"
                                        aria-labelledby="editStaffModalLabel" aria-hidden="true">
                                        <div class="modal-dialog modal-xl">
                                            <div class="modal-content shadow-lg rounded-3">
                                                <div class="modal-header text-white"
                                                    style="background:linear-gradient(135deg,#1e3a5f,#2d6a9f);">
                                                    <h5 class="modal-title fw-bold" id="editStaffModalLabel">
                                                        <i class="fas fa-user-edit me-2"></i>
                                                        Edit Staff Information
                                                    </h5>
                                                    <button type="button" class="btn-close btn-close-white"
                                                        data-bs-dismiss="modal" aria-label="Close">x</button>
                                                </div>

                                                <form action="" method="POST" id="update-staff-form"
                                                    enctype="multipart/form-data">
                                                    <div class="modal-body">
                                                        <div class="card">
                                                            <div class="card-body">

                                                            </div>
                                                        </div>
                                                    </div>
                                            </div>

                                            <div class="col-lg-12">
                                                <div class="modal-footer">
                                                    <button type="submit" name="updateStaffBTN"
                                                        class="btn btn-submit me-2">Save
                                                        changes</button>
                                                    <button type="button" class="btn btn-secondary"
                                                        data-bs-dismiss="modal">Close</button>
                                                </div>
                                            </div>

                                            </form>
                                            <!-- /Edit Staff Form -->

                                            <!--/ Edit Staff Modal -->
                                        </div>
                                    </div>
                                </tbody>
                            </table>
                        </div>


                    </div>

                </div>

                <!-- Staff Roles -->
                <div id="roles-section" class="management-content d-none">
                    <div class="container-card border-0 shadow-sm p-4">
                        <div class="d-flex justify-content-between align-items-center p-2 mb-3">
                            <h4 class="mb-0 fw-bold" style="color: var(--udsm-blue);">
                                <i class="fas fa-id-badge me-2"></i>Staff Roles
                            </h4>
                            <button type="button" class="btn btn-add" data-bs-toggle="modal"
                                data-bs-target="#addRoleModal">
                                <i class="fas fa-plus"></i> Add New Role
                            </button>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-stripped" id="rolesTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>ROLE NAME</th>
                                        <th class="text-center">RANK</th>
                                        <th class="text-center">STAFF COUNT</th>
                                        <th class="text-center">ACTION</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($staff_roles)): ?>
                                        <?php $n = 1;
                                        foreach ($staff_roles as $role): ?>
                                            <tr>
                                                <td><?= $n++ ?></td>
                                                <td><?= htmlspecialchars($role['role_name']) ?></td>
                                                <td class="text-center">
                                                    <span class="badge bg-secondary"><?= $role['role_rank'] ?></span>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge bg-primary"><?= $role['staff_count'] ?></span>
                                                </td>
                                                <td class="text-center">
                                                    <div class="d-flex justify-content-center">
                                                        <button type="button" class="btn btn-status btn-outline-secondary me-2"
                                                            onclick="openEditRole(<?= htmlspecialchars(json_encode($role)) ?>)"
                                                            data-bs-toggle="modal" data-bs-target="#editRoleModal" title="edit">
                                                            <i class="fas fa-edit text-dark"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-status btn-outline-secondary"
                                                            onclick="confirmDeleteRole(<?= $role['role_id'] ?>, '<?= htmlspecialchars($role['role_name'], ENT_QUOTES) ?>')"
                                                            title="delete">
                                                            <i class="fas fa-trash text-dark"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-4 text-muted">No roles defined yet.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Add Role Modal -->
                    <div class="modal fade" id="addRoleModal" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content border-0 shadow-lg" style="border-radius: 5px;">
                                <div class="modal-header text-white"
                                    style="background:linear-gradient(135deg,#1e3a5f,#2d6a9f);">
                                    <h5 class="modal-title fw-bold">
                                        <i class="fas fa-id-badge me-2"></i>
                                        ADD STAFF ROLE
                                    </h5>
                                    <button type="button" class="btn-close btn-close-white"
                                        data-bs-dismiss="modal"></button>
                                </div>
                                <form action="user_management.php" method="POST">
                                    <div class="modal-body px-4 py-3">
                                        <div class="mb-3">
                                            <label class="form-label fw-bold small">Role Name <span
                                                    class="text-danger">*</span></label>
                                            <input type="text" name="role_name" class="form-control p-3 shadow-sm"
                                                style="border-radius: 10px;" placeholder="e.g., Supervisor" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label fw-bold small">Rank <span
                                                    class="text-danger">*</span></label>
                                            <input type="number" name="role_rank" class="form-control p-3 shadow-sm"
                                                style="border-radius: 10px;" placeholder="e.g., 2" min="1" required>
                                            <small class="text-muted">Higher rank = more authority. Must be
                                                unique.</small>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="submit" name="add_role" class="btn btn-primary fw-bold">
                                            <i class="fas fa-plus me-1"></i> Add Role
                                        </button>
                                        <button type="button" class="btn btn-secondary"
                                            data-bs-dismiss="modal">Cancel</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Edit Role Modal -->
                    <div class="modal fade" id="editRoleModal" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content border-0 shadow-lg" style="border-radius: 5px;">
                                <div class="modal-header text-white"
                                    style="background:linear-gradient(135deg,#1e3a5f,#2d6a9f);">
                                    <h5 class="modal-title fw-bold">
                                        <i class="fas fa-user-edit me-2"></i>
                                        EDIT STAFF ROLE
                                    </h5>
                                    <button type="button" class="btn-close btn-close-white"
                                        data-bs-dismiss="modal"></button>
                                </div>
                                <form action="user_management.php" method="POST">
                                    <input type="hidden" name="role_id" id="edit_role_id">
                                    <div class="modal-body px-4 py-3">
                                        <div class="mb-3">
                                            <label class="form-label fw-bold small">Role Name <span
                                                    class="text-danger">*</span></label>
                                            <input type="text" name="role_name" id="edit_role_name"
                                                class="form-control p-3 shadow-sm" style="border-radius: 10px;"
                                                required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label fw-bold small">Rank <span
                                                    class="text-danger">*</span></label>
                                            <input type="number" name="role_rank" id="edit_role_rank"
                                                class="form-control p-3 shadow-sm" style="border-radius: 10px;" min="1"
                                                required>
                                            <small class="text-muted">Higher rank = more authority. Must be
                                                unique.</small>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="submit" name="edit_role" class="btn btn-primary fw-bold">
                                            <i class="fas fa-save me-1"></i> Save Changes
                                        </button>
                                        <button type="button" class="btn btn-secondary"
                                            data-bs-dismiss="modal">Cancel</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Assign Role Modal (shared, lives outside tab sections) -->
                <div class="modal fade" id="assignRoleModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content border-0 shadow-lg" style="border-radius: 5px;">
                            <div class="modal-header text-white"
                                style="background:linear-gradient(135deg,#1e3a5f,#2d6a9f);">
                                <h5 class="modal-title fw-bold">
                                    <i class="fas fa-user-plus me-2"></i>
                                    ASSIGN ROLE
                                </h5>
                                <button type="button" class="btn-close btn-close-white"
                                    data-bs-dismiss="modal"></button>
                            </div>
                            <form action="user_management.php" method="POST">
                                <input type="hidden" name="assign_staff_user_id" id="assign_staff_user_id">
                                <div class="modal-body px-4 py-3">
                                    <p class="mb-3">Assigning role to: <strong id="assign_staff_name"></strong></p>
                                    <div class="mb-3">
                                        <label class="form-label fw-bold small">Select Role</label>
                                        <select name="assign_role_id" id="assign_role_id"
                                            class="form-select p-3 shadow-sm" style="border-radius: 10px;">
                                            <option value="">-- No Role --</option>
                                            <?php foreach ($staff_roles as $role): ?>
                                                <option value="<?= $role['role_id'] ?>">
                                                    <?= htmlspecialchars($role['role_name']) ?> (Rank
                                                    <?= $role['role_rank'] ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="submit" name="assign_role" class="btn btn-primary fw-bold">
                                        <i class="fas fa-save me-1"></i> Assign Role
                                    </button>
                                    <button type="button" class="btn btn-secondary"
                                        data-bs-dismiss="modal">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script>
        (function () {
            var VALID_TABS = ['students', 'staff', 'approval', 'roles'];

            window.switchTab = function (tabName) {
                if (VALID_TABS.indexOf(tabName) === -1) tabName = 'students';
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
                if (window.$ && $.fn.DataTable) {
                    $($.fn.dataTable.tables(true)).DataTable().columns.adjust();
                }
            };

            window.addEventListener('hashchange', function () {
                var hash = location.hash.slice(1);
                switchTab(VALID_TABS.indexOf(hash) !== -1 ? hash : 'students');
            });

            var initHash = location.hash.slice(1);
            switchTab(VALID_TABS.indexOf(initHash) !== -1 ? initHash : 'students');
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

        function viewStudent(student) {
            document.getElementById('modal_student_name').textContent = student.username || '-';
            document.getElementById('modal_student_regnum').textContent = student.student_registration_number || '-';
            document.getElementById('modal_student_email').textContent = student.user_email || '-';
            document.getElementById('modal_student_college').textContent = student.college_name || '-';
        }

        function viewStaff(staff) {
            document.getElementById('modal_staff_name').textContent = staff.username || '-';
            document.getElementById('modal_staff_email').textContent = staff.user_email || '-';
            document.getElementById('modal_staff_department').textContent = staff.department_name || '-';
            document.getElementById('modal_staff_status').textContent = staff.user_status || '-';
        }

        function viewApprovedStaff(staff) {
            document.getElementById('astaff_name').textContent = staff.username || '-';
            document.getElementById('astaff_role').textContent = staff.role_name || 'Officer';
            document.getElementById('astaff_email').textContent = staff.user_email || '-';
            document.getElementById('astaff_dept').textContent = staff.department_name || 'Unassigned';
            document.getElementById('astaff_approved_at').textContent = staff.staff_approved_at
                ? new Date(staff.staff_approved_at).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
                : '-';
            document.getElementById('astaff_status').textContent = staff.user_status || '-';
        }

        function confirmDeleteStudent(studentId) {
            Swal.fire({
                icon: 'warning',
                title: 'Are you sure?',
                text: "This action cannot be undone.",
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, delete!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '?delete_student=' + studentId;
                }
            });
        }

        function confirmDeleteStaff(staffId) {
            Swal.fire({
                icon: 'warning',
                title: 'Are you sure?',
                text: "This action cannot be undone.",
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, delete!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '?delete_staff=' + staffId;
                }
            });
        }

        function openEditRole(role) {
            document.getElementById('edit_role_id').value = role.role_id;
            document.getElementById('edit_role_name').value = role.role_name;
            document.getElementById('edit_role_rank').value = role.role_rank;
        }

        function confirmDeleteRole(id, name) {
            Swal.fire({
                icon: 'warning',
                title: 'Delete Role?',
                text: `"${name}" will be permanently removed. Roles assigned to staff cannot be deleted.`,
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'user_management.php?delete_role=' + id;
                }
            });
        }

        function confirmDemoteStaff(staffId) {
            Swal.fire({
                icon: 'warning',
                title: 'Demote Staff?',
                text: 'This will move the staff back to pending approval status.',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, demote',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '?demote_staff=' + staffId;
                }
            });
        }

        function openAssignRole(userId, username, currentRoleId) {
            document.getElementById('assign_staff_user_id').value = userId;
            document.getElementById('assign_staff_name').textContent = username;
            const select = document.getElementById('assign_role_id');
            select.value = currentRoleId || '';
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
            if ($("#studentsTable").length > 0) {
                if (!$.fn.DataTable.isDataTable("#studentsTable")) {
                    $("#studentsTable").DataTable({
                        destroy: true,
                        bFilter: true,
                        sDom: "fBtlpi",
                        pagingType: "numbers",
                        ordering: true,
                        language: {
                            search: " ",
                            sLengthMenu: "_MENU_",
                            searchPlaceholder: "Search Students...",
                            info: "_START_ - _END_ of _TOTAL_ items"
                        },
                        initComplete: function (settings, json) {
                            $(".dataTables_filter").appendTo("#tableSearch");
                            $(".dataTables_filter").appendTo(".search-input");
                        }
                    });
                }
            }
        });
    </script>
    <script>
        $(document).ready(function () {
            if ($("#staffsTable").length > 0) {
                if (!$.fn.DataTable.isDataTable("#staffsTable")) {
                    $("#staffsTable").DataTable({
                        destroy: true,
                        bFilter: true,
                        sDom: "fBtlpi",
                        pagingType: "numbers",
                        ordering: true,
                        language: {
                            search: " ",
                            sLengthMenu: "_MENU_",
                            searchPlaceholder: "Search Staffs...",
                            info: "_START_ - _END_ of _TOTAL_ items"
                        },
                        initComplete: function (settings, json) {
                            $(".dataTables_filter").appendTo("#tableSearch");
                            $(".dataTables_filter").appendTo(".search-input");
                        }
                    });
                }
            }
        });
    </script>
    <script>
        $(document).ready(function () {
            if ($("#departmentsTable").length > 0) {
                if (!$.fn.DataTable.isDataTable("#departmentsTable")) {
                    $("#departmentsTable").DataTable({
                        destroy: true,
                        bFilter: true,
                        sDom: "fBtlpi",
                        pagingType: "numbers",
                        ordering: true,
                        language: {
                            search: " ",
                            sLengthMenu: "_MENU_",
                            searchPlaceholder: "Search Staffs...",
                            info: "_START_ - _END_ of _TOTAL_ items"
                        },
                        initComplete: function (settings, json) {
                            $(".dataTables_filter").appendTo("#tableSearch");
                            $(".dataTables_filter").appendTo(".search-input");
                        }
                    });
                }
            }
        });
    </script>
    <script>
        $(document).ready(function () {
            if ($("#rolesTable").length > 0 && !$.fn.DataTable.isDataTable("#rolesTable")) {
                $("#rolesTable").DataTable({
                    destroy: true,
                    bFilter: true,
                    sDom: "fBtlpi",
                    pagingType: "numbers",
                    ordering: true,
                    language: {
                        search: " ",
                        sLengthMenu: "_MENU_",
                        searchPlaceholder: "Search Roles...",
                        info: "_START_ - _END_ of _TOTAL_ items"
                    }
                });
            }
        });
    </script>
</body>

</html>