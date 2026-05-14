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
                    <p class="mb-0 small fw-bold">USER</p>
                </div>
            </div>

            <ul class="list-unstyled components">
                <li class="active">
                    <a href="admin_dashboard.php"><i class="fas fa-chart-pie me-2" style="color: black;"></i>
                        Overview
                    </a>
                </li>
                <li>
                    <a href="manage_complaints.php"><i class="fas fa-file-invoice me-2"
                            style="color: black;"></i>Student Complaints</a>
                </li>
                <li>
                    <a href="#userSubmenu" data-bs-toggle="collapse" aria-expanded="false"
                        class="dropdown-toggle d-flex align-items-center justify-content-between">
                        <span><i class="fas fa-user-shield me-2" style="color: black;"></i>User Management</span>
                        <i class="bi bi-chevron-down small"></i>
                    </a>
                    <ul class="collapse show list-unstyled ps-4" id="userSubmenu">
                        <li>
                            <a href="manage_students.php" class="py-2 small">
                                <i class="fa fa-circle me-2" style="font-size: 0.5rem;"></i>Students List
                            </a>
                        </li>
                        <li>
                            <a href="manage_staffs.php" class="py-2 small">
                                <i class="fa fa-circle me-2" style="font-size: 0.5rem;"></i>Staffs List
                            </a>
                        </li>
                        <li>
                            <a href="staff_approval.php" class="py-2 small">
                                <i class="fa fa-circle me-2" style="font-size: 0.5rem;"></i>Staff Approval
                            </a>
                        </li>
                    </ul>
                </li>
                <li>
                    <a href="manage_departments.php"><i class="fas fa-sitemap me-2"
                            style="color: black;"></i>Departments</a>
                </li>
                <li>
                    <a href="manage_categories.php"><i class="fas fa-tags me-2"
                            style="color: black;"></i>Categories</a>
                </li>
                <li>
                    <a href="reports.php"><i class="fas fa-file-contract me-2" style="color: black;"></i>Reports</a>
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
                <nav aria-label="breadcrumb" class="d-flex justify-content-between align-items-center">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item">
                            <a href="#"><i class="fas fa-users" style="color: black;"></i></a>
                        </li>
                        <li class="breadcrumb-item active">Admin / Manage Staffs</li>
                    </ol>

                    <button type="button" class="btn btn-add" data-bs-toggle="modal"
                        data-bs-target="#addDepartmentModal">
                        <i class="fas fa-plus"></i>
                        Add New Staff
                    </button>

                </nav>

                <div class="container-card shadow-sm">
                    <h4 class="mb-1 fw-bold"><i class="fas fa-users me-2"></i>All Staffs</h4>

                    <div class="table-responsive">
                        <table class="table table-stripped" id="departmentsTable">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th class="text-center">STAFF NAME</th>
                                    <th class="text-center">DEPARTMENT</th>
                                    <th class="text-center">ACTIONS</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>#1</td>
                                    <td class="text-center">Itz Mee</td>
                                    <td class="text-center">Library Services</td>
                                    <td class="text-center">
                                        <div class="d-flex justify-content-center">
                                            <!-- View Button -->
                                            <button type="button" class="btn btn-status btn-outline-secondary me-2"
                                                data-bs-toggle="modal" data-bs-target="#viewStaff" title='view'>
                                                <i class="fas fa-eye text-dark"></i>
                                            </button>

                                            <!-- Edit Button -->
                                            <button type="button" class="btn btn-status btn-outline-secondary me-2"
                                                data-bs-toggle="modal" data-bs-target="#editStaff" title="edit">
                                                <i class="fas fa-edit text-dark"></i>
                                            </button>

                                            <!-- Delete Button -->
                                            <button type="button" class="btn btn-status btn-outline-secondary me-2"
                                                onclick="confirmDelete()" title="delete">
                                                <i class="fas fa-trash text-dark"></i>
                                            </button>
                                        </div>

                                    </td>
                                </tr>
                                <tr>
                                    <td>#1</td>
                                    <td class="text-center">Itz Mee</td>
                                    <td class="text-center">Library Services</td>
                                    <td class="text-center">
                                        <div class="d-flex justify-content-center">
                                            <!-- View Button -->
                                            <button type="button" class="btn btn-status btn-outline-secondary me-2"
                                                data-bs-toggle="modal" data-bs-target="#viewStaff" title='view'>
                                                <i class="fas fa-eye text-dark"></i>
                                            </button>

                                            <!-- Edit Button -->
                                            <button type="button" class="btn btn-status btn-outline-secondary me-2"
                                                data-bs-toggle="modal" data-bs-target="#editStaff" title="edit">
                                                <i class="fas fa-edit text-dark"></i>
                                            </button>

                                            <!-- Delete Button -->
                                            <button type="button" class="btn btn-status btn-outline-secondary me-2"
                                                onclick="confirmDelete()" title="delete">
                                                <i class="fas fa-trash text-dark"></i>
                                            </button>
                                        </div>

                                    </td>
                                </tr>

                                <!-- View Staff Modal -->
                                <div class="modal fade" id="viewStaff" tabindex="-1"
                                    aria-labelledby="viewStaffModalLabel" aria-hidden="true">
                                    <div class="modal-dialog modal-xl">
                                        <div class="modal-content shadow-lg rounded-3">
                                            <div class="modal-header bg-secondary text-white">
                                                <h5 class="modal-title fw-bold" id="viewStaffModalLabel">

                                                </h5>
                                                <button type="button" class="btn-close btn-close-white"
                                                    data-bs-dismiss="modal" aria-label="Close">x</button>
                                            </div>

                                            <div class="modal-body">
                                                <div class="container-fluid">
                                                    <div class="row g-4">

                                                        <!-- Staff Info -->
                                                        <div class="col-12">
                                                            <div class="card border-0 shadow-sm">
                                                                <div class="card-body">
                                                                    <h6 class="text-uppercase text-muted mb-3">Staff
                                                                        Information</h6>
                                                                    <div class="row">
                                                                        <div class="col-md-6">
                                                                        </div>
                                                                        <div class="col-md-6">
                                                                            <p>
                                                                                <strong>Status:</strong>
                                                                            </p>
                                                                        </div>
                                                                    </div>
                                                                    <hr>
                                                                </div>
                                                            </div>
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
                                            <div class="modal-header bg-secondary text-white">
                                                <h5 class="modal-title fw-bold" id="editStaffModalLabel">
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

                                    </div>
                                </div>
                    </div>

                    <!--/ Edit Staff Modal -->

                    </tbody>
                    </table>
                </div>
            </div>

            <!-- Add Department Modal -->
            <div class="modal fade" id="addDepartmentModal" tabindex="-1" aria-labelledby="addDepartmentModalLabel"
                aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0 shadow-lg" style="border-radius: 5px;">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title" id="addDepartmentModalLabel">Add Department</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                                aria-label="Close"></button>
                        </div>

                        <div class="modal-body px-4">
                            <form action="" id="departmentForm">
                                <div class="mb-4">
                                    <label for="" class="form-label fw-bold small">Department Name
                                        <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control p-3 shadow-sm"
                                        style="border-radius: 10px; border: 1px solid #e0e6ed;"
                                        placeholder="e.g., Information Technology" required>
                                </div>

                                <div class="mb-4">
                                    <label for="" class="form-label fw-bold small">Description</label>
                                    <textarea name="" id="" class="form-control p-3 shadow-sm" rows="4"
                                        style="border:-radius 10px; border: 1px solid #e0e6ed;"
                                        placeholder="Brief description of the department's responsibilities...'"></textarea>
                                </div>

                                <div class="d-rigid gap-2 pb-3">
                                    <button type="submit" class="btn btn-primary p-3 fw-bold"
                                        style="border-radius: 10px; background-color: var(--udsm-blue);">
                                        Add Department
                                    </button>
                                </div>
                            </form>
                        </div>

                    </div>

                </div>
            </div>
        </div>

        <script>
            function confirmDelete() {
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
                        window.location.href = 'deleteStaff.php?id=' + StaffId;
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
</body>

</html>