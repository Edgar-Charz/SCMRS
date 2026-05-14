<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header("Location: login.php");
    exit;
} else {
    $userId = $_SESSION['user_id'];
}

require_once "config/Database.php";
require_once "classes/User.php";
require_once "classes/Student.php";
require_once "classes/Category.php";
require_once "classes/Complaint.php";

$db = new Database();
$conn = $db->connect();

$category = new Category($conn);
$student = new Student($conn);
$complaint = new Complaint($conn);

$studentId = $student->getStudentId($userId);
$categories = $category->getCategories();

$message = $error = "";

// Handle complaint submission
if (isset($_POST["submitComplaintBTN"])) {
    try {

        $title          = trim($_POST['title'] ?? '');
        $description      = trim($_POST['description']);
        $category_id    = isset($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $subcategory_id = isset($_POST['subcategory_id']) ? (int)$_POST['subcategory_id'] : null;
        $department_id  = isset($_POST['department_id']) ? (int)$_POST['department_id'] : null;
        $is_anonymous   = isset($_POST['is_anonymous']) ? 1 : 0;
        $student_id     = $studentId;
        $user_id        = $_SESSION['user_id'];

        if ($complaint->createComplaint($title, $description, $category_id, $department_id, $is_anonymous, $student_id, $user_id, $subcategory_id)) {

            $_SESSION['message'] = "Complaint submitted successfully.";
            header("Location: track_complaints.php");
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

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
    <title>Student Dashboard</title>
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
                    <a href="student_dashboard.php" title="Dashboard">
                        <i class="fas fa-chart-pie me-2"></i>
                        <span class="link-text">Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="create_complaint.php" title="Submit Complaint">
                        <i class="fas fa-paper-plane me-2"></i>
                        <span class="link-text">Submit Complaint</span>
                    </a>
                </li>
                <li>
                    <a href="track_complaints.php" title="Track Complaints">
                        <i class="fas fa-search-location me-2"></i>
                        <span class="link-text">Track Complaints</span>
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

                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item">
                            <a href="#"><i class="fas fa-paper-plane" style="color: black;"></i></a>
                        </li>
                        <li class="breadcrumb-item active">Student / Submit Complaint</li>
                    </ol>
                </nav>
 
                <!-- Alert -->
                <div aria-live="polite" aria-atomic="true" class="position-fixed top-0 start-50 translate-middle-x p-3 w-100" style="z-index: 1100; max-width: 800px;">
                    <?php if (!empty($message) || !empty($error)):
                        $type = !empty($message) ? 'success' : 'danger';
                        $text = !empty($message) ? $message : $error;
                        $icon = ($type === 'success') ? 'fa-check-circle' : 'fa-exclamation-circle';
                    ?>
                        <div id="livetoast" class="toast show align-items-center text-white bg-<?php echo $type ?> border-0 w-100" role="alert" aria-live="assertive" aria-atomic="true">
                            <div class="d-flex">
                                <div class="toast-body">
                                    <i class="fas <?php echo $icon; ?> me-2"></i>
                                    <?php echo htmlspecialchars($text); ?>
                                </div>
                                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close">

                                </button>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <!-- / Alert -->

                <form action="" method="POST" enctype="multipart/form-data">
                    <div class="form-card shadow-sm mb-4" style="align-items: center;">
                        <h4 class="mb-3 fw-bold"><i class="fas fa-info-circle me-2"></i>Basic Information</h4>

                        <div class="row">
                            <div class="col-12 col-md-12 col-lg-12 mb-2">
                                <label for="" class="form-label fw-bold">Complaint Title</label>
                                <span class="text-danger">*</span>
                                <input type="hidden" name="student_id" value="<?= $studentId; ?>">
                                <input type="text" name="title" id="title" class="form-control p-3 shadow-sm"
                                    style="border-radius: 10px; border: 1px solid #e0e6ed;"
                                    placeholder="i.e; Issue with Hostel facilities" required>
                                <div class="char-count"><span id="titleCount">0</span>/200 characters</div>
                            </div>

                            <div class="col-12 col-md-6 col-lg-16 mb-2">
                                <label for="" class="form-label fw-bold">Category</label>
                                <span class="text-danger">*</span>
                                <select class="form-select p-3 shadow-sm" name="category_id" id="category_id"
                                    style="border-radius: 10px; border: 1px solid #e0e6ed;">
                                    <option value="" selected disabled>--- Choose Category ---</option>
                                    <?php if ($categories): ?>
                                        <?php while ($category_row = $categories->fetch_assoc()): ?>
                                            <option value="<?= $category_row['category_id']; ?>">
                                                <?= $category_row['category_name']; ?>
                                            </option>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </select>
                                <small class="form-hint">
                                    <i class="fas fa-info-circle"></i> Categorizing helps route your complaint to the right department.
                                </small>
                            </div>

                            <div class="col-12 col-md-6 col-lg-6 mb-2">
                                <label for="" class="form-label fw-bold">Sub-Category</label>
                                <span class="text-danger">*</span>
                                <select class="form-select p-3 shadow-sm" name="subcategory_id" id="subcategory_id" disabled
                                    style="border-radius: 10px; border: 1px solid #e0e6ed;">
                                    <option value="" selected disabled>--- Choose sub-Category ---</option>
                                </select>
                                <small class="form-hint">
                                    <i class="fas fa-info-circle"></i> Categorizing helps route your complaint to the right department.
                                </small>
                            </div>

                            <!-- <div class="col-12 col-md-12 col-lg-12 mb-3">
                            <label for="" class="form-label fw-bold small">Target Department</label>
                            <span class="text-danger">*</span>
                             <select class="form-select p-3 shadow-sm" name="department"
                                    style="border-radius: 10px; border: 1px solid #e0e6ed;">
                                    <option value="" selected disabled>All Departments</option>
                                    <option value="">ABC</option>
                                </select>
                                <small class="form-hint">
                                    <i class="fas fa-info-circle"></i> Your complaint will be automatically routed to this department for review.
                                </small>
                        </div> -->
                        </div>

                    </div>

                    <div class="form-card shadow-sm mb-4">
                        <h4 class="mb-3 fw-bold"><i class="fas fa-align-left me-2"></i>Complaint Description</h4>

                        <div class="row">
                            <div class="col-12 col-md-12 col-lg-12 mb-2">
                                <label for="" class="form-label fw-bold">Description</label>
                                <span class="text-danger">*</span>
                                <textarea name="description" class="form-control p-3 shadow-sm" rows="10"
                                    style="border-radius: 8px; border: 1px solid #e0e6ed;"
                                    placeholder="Please descibe your complaint in detail..."></textarea>
                                <small class="form-hint">
                                    Provide a detailed description of your complaint. The more information you provide, the better we can help you.
                                </small>
                            </div>

                        </div>

                    </div>

                    <div class="form-card shadow-sm mb-4">
                        <h4 class="mb-2 fw-bold"><i class="fas fa-paperclip me-2"></i>Evidence Attachments</h4>

                        <div class="row">
                            <div class="col-12 col-md-12 col-lg-12 mb-2">
                                <label for="" class="form-label fw-bold">Supporting Evidence / Documents</label>
                                <input type="file" id="attachments" name="attachments[]" multiple accept=".pdf,.jpg,.jpeg,.png" class="form-control p-3 shadow-sm"
                                    style="border-radius: 10px; border: 1px solid #e0e6ed;">
                                <small class="form-hint">
                                    <i class="fas fa-info-circle"></i> You can upload multiple files (PDF or images). Maximum file size: 5MB per file. Accepted formats: PDF, JPG, JPEG, PNG.
                                </small>
                                <div id="fileList" style="margin-top: 10px;"></div>
                            </div>

                        </div>

                    </div>

                    <div class="form-card shadow-sm mb-4">
                        <h4 class="mb-4 fw-bold"><i class="fas fa-user-shield me-2"></i>Privacy Options</h4>

                        <div class="row">
                            <div class="form-group">
                                <label style="display: flex; align-items: center; ">
                                    <input type="checkbox" id="is_anonymous" name="is_anonymous" value="1" style="width: auto;" class="me-2">
                                    <span class="fw-bold">Submit this complaint anonymously</span>
                                </label>
                                <small class="form-hint">
                                    <i class="fas fa-shield-alt small"></i> When enabled, your identity will be hidden from department staff. Administrators can still view your identity for system management purposes.
                                </small>
                            </div>
                        </div>

                    </div>

                    <div class="d-rigid gap-2 text-center">
                        <button type="submit" name="submitComplaintBTN" class="btn btn-primary p-3 fw-bold mb-2"
                            style="border-radius: 10px; background-color: var(--udsm-blue); width: 70%;">
                            Submit Complaint
                        </button>
                        <button type="reset" class="btn btn-danger p-3 fw-bold mb-2"
                            style="border-radius: 10px; width: 20%;">
                            Cancel
                        </button>
                    </div>
                </form>

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
        // Character counter for title
        const titleInput = document.getElementById('title');
        const titleCount = document.getElementById('titleCount');

        if (titleInput && titleCount) {
            titleInput.addEventListener('input', function() {
                titleCount.textContent = this.value.length;
            });
            titleCount.textContent = titleInput.value.length;
        }

        // File upload preview
        const fileInput = document.getElementById('attachments');
        const fileList = document.getElementById('fileList');

        if (fileInput && fileList) {
            fileInput.addEventListener('change', function() {
                fileList.innerHTML = '';
                const files = this.files;

                if (files.length > 0) {
                    const list = document.createElement('ul');
                    list.style.listStyle = 'none';
                    list.style.padding = '0';
                    list.style.margin = '0';

                    Array.from(files).forEach((file, index) => {
                        const li = document.createElement('li');
                        li.style.padding = '5px';
                        li.style.background = '#e3e8f3ff';
                        li.style.borderRadius = '8px';
                        li.style.marginBottom = '5px';
                        li.style.display = 'flex';
                        li.style.alignItems = 'center';
                        li.style.gap = '2px';

                        const icon = document.createElement('i');
                        if (file.type === 'application/pdf') {
                            icon.className = 'fas fa-file-pdf';
                            icon.style.color = '#dc2626';
                        } else if (file.type.startsWith('image/')) {
                            icon.className = 'fas fa-file-image';
                            icon.style.color = '#10b981';
                        } else {
                            icon.className = 'fas fa-file';
                            icon.style.color = '#6b7280';
                        }

                        const name = document.createElement('span');
                        name.textContent = file.name;
                        name.style.flex = '1';

                        const size = document.createElement('span');
                        size.textContent = (file.size / 1024 / 1024).toFixed(2) + ' MB';
                        size.style.color = '#646668ff';
                        size.style.fontSize = '0.875rem';

                        li.appendChild(icon);
                        li.appendChild(name);
                        li.appendChild(size);
                        list.appendChild(li);
                    });

                    fileList.appendChild(list);
                }
            });
        }
    </script>
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
    <script>
        $(function() {
            const $category = $("#category_id");
            const $subcat = $("#subcategory_id");

            function resetSubcategories(message) {
                $subcat.prop("disabled", true);
                $subcat.html(`<option value="" selected disabled>${message}</option>`);
            }

            resetSubcategories("--- Choose category first ---");

            $category.on("change", function() {
                const categoryId = $(this).val();

                if (!categoryId) {
                    resetSubcategories("--- Choose category first ---");
                    return;
                }

                resetSubcategories("Loading sub-categories...");

                $.getJSON("ajax/get_subcategories.php", {
                        category_id: categoryId
                    })
                    .done(function(data) {
                        if (!data || data.success !== true) {
                            resetSubcategories("--- No sub-categories found ---");
                            return;
                        }

                        const items = Array.isArray(data.items) ? data.items : [];
                        if (items.length === 0) {
                            resetSubcategories("--- No sub-categories found ---");
                            return;
                        }

                        let options = '<option value="" selected disabled>--- Choose sub-Category ---</option>';
                        items.forEach(function(item) {
                            options += `<option value="${item.subcategory_id}">${item.subcategory_name}</option>`;
                        });
                        $subcat.html(options);
                        $subcat.prop("disabled", false);
                    })
                    .fail(function() {
                        resetSubcategories("--- Failed to load sub-categories ---");
                    });
            });
        });
    </script>

</body>

</html>