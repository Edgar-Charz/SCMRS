<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard</title>
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
                <li>
                    <a href="staff_dashboard.php" title="Dashboard">
                        <i class="fas fa-chart-pie me-2"></i>
                        <span class="link-text">Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="assigned_complaints.php" title="Assigned Complaints">
                        <i class="fas fa-comment-dots me-2"></i>
                        <span class="link-text">Assigned Complaints</span>
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
                            <a href="#"><i class="fas fa-comment-dots" style="color: black;"></i></a>
                        </li>
                        <li class="breadcrumb-item active">Staff / Respond Assigned Complaint</li>
                    </ol>

                    <a href="assigned_complaint_details.php" type="button" class="btn btn-add">
                        <i class="fas fa-eye"></i>
                        View Full Details
                    </a>
                </nav>

                <div class="container-card shadow-sm">
                    <div class="mb-4" style="border-bottom: 2px solid rgb(212, 207, 207);">
                        <h4 class="mb-3 fw-bold"><i class="fas fa-file-invoice me-2"></i>Complaint #</h4>
                    </div>

                    <div class="detail-row">
                        <div class="detail-label fw-bold">Student Name:</div>
                        <div class="detail-value">ABC DEF GHI</div>
                    </div>

                    <div class="detail-row">
                        <div class="detail-label fw-bold">Title:</div>
                        <div class="detail-value">ABC DEF GHI</div>
                    </div>

                    <div class="detail-row">
                        <div class="detail-label fw-bold">Department Name:</div>
                        <div class="detail-value">ABC DEF GHI</div>
                    </div>

                    <div class="detail-row">
                        <div class="detail-label fw-bold">Category:</div>
                        <div class="detail-value">ABC DEF GHI</div>
                    </div>

                    <div class="mb-4">
                        <div class="detail-label fw-bold">Description:</div>
                        <textarea name="" id="" class="form-control p-3 shadow-sm" rows="4"
                            style="border:-radius 10px; border: 1px solid #e0e6ed;"
                            placeholder="Brief description of a student's complaint..."></textarea>
                    </div>

                    <div class="detail-row">
                        <div class="detail-label fw-bold">Attachments ():</div>

                    </div>

                    <div class="detail-row">
                        <div class="detail-label fw-bold">Response:</div>

                    </div>

                </div>

                <div class="container-card shadow-sm">
                    <div class="mb-3">
                        <h4 class="mb-3 fw-bold"><i class="fas fa-reply me-2"></i>Response</h4>
                    </div>

                    <form action="">
                        <div class="mb-4">
                            <div class="detail-label fw-bold">Add a Response</div>
                            <textarea name="" id="" class="form-control p-3 shadow-sm" rows="4"
                                style="border:-radius 10px; border: 1px solid #e0e6ed;"
                                placeholder="Add your reponse or reason for denial towards a complaint..." required></textarea>
                        </div>

                        <div class="d-rigid gap-2 text-center">
                            <button type="submit" class="btn btn-primary p-3 fw-bold mb-2"
                                style="border-radius: 10px; background-color: var(--udsm-blue); width: 45%;">
                                Resolve
                            </button>
                            <button type="submit" class="btn btn-danger p-3 fw-bold mb-2"
                                style="border-radius: 10px; width: 45%;">
                                Deny
                            </button>
                        </div>
                    </form>
                </div>

            </div>

        </div>
    </div>

    <script src="assets/js/jquery-3.6.0.min.js"></script>
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/jquery.dataTables.min.js"></script>
    <script src="assets/js/dataTables.bootstrap4.min.js"></script>
    <script src="assets/plugins/sweetalert/sweetalert2.all.min.js"></script>
    <script src="assets/plugins/sweetalert/sweetalerts.min.js"></script>
    <script src="assets/js/script.js"></script>
    <script>
        $(document).ready(function() {
            if ($("#complaintsTable").length > 0) {
                if (!$.fn.DataTable.isDataTable("#complaintsTable")) {
                    $("#complaintsTable").DataTable({
                        destroy: true,
                        bFilter: true,
                        sDom: "fBtlpi",
                        pagingType: "numbers",
                        ordering: true,
                        language: {
                            search: " ",
                            sLengthMenu: "_MENU_",
                            searchPlaceholder: "Search Complaints...",
                            info: "_START_ - _END_ of _TOTAL_ items"
                        },
                        initComplete: function(settings, json) {
                            $(".dataTables_filter").appendTo("#tableSearch");
                            $(".dataTables_filter").appendTo(".search-input");
                        }
                    });
                }
            }
        });
    </script>
    <!-- <script>
        $(document).ready(function () {
            $('#complaintsTable').DataTable({
                responsive: true,
                order: [[3, 'desc']],
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "Search complaints..."
                }
            });

            $('#sidebarCollapse').on('click', function () {
                $('#sidebar').toggleClass('active');
            });
        });
    </script> -->
</body>

</html>