<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header("Location: login.php");
    exit;
}

$userId = (int)$_SESSION['user_id'];

require_once "config/Database.php";
require_once "classes/User.php";
require_once "classes/Student.php";
require_once "classes/Category.php";
require_once "includes/csrf.php";

$db       = new Database();
$conn     = $db->connect();
$student  = new Student($conn);
$category = new Category($conn);

$studentId   = $student->getStudentId($userId);
$complaintId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($complaintId <= 0) {
    header("Location: track_complaints.php");
    exit;
}

// Load complaint — verify ownership and pending status
$complaint = $student->readStudentComplaint($complaintId);
if (!$complaint || (int)$complaint['student_id'] !== (int)$studentId || $complaint['complaint_status'] !== 'pending') {
    $_SESSION['message_error'] = "Complaint not found or cannot be edited.";
    header("Location: track_complaints.php");
    exit;
}

$message = $error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $title         = trim($_POST['title'] ?? '');
    $description   = trim($_POST['description'] ?? '');
    $categoryId    = (int)($_POST['category_id'] ?? 0);
    $subcategoryId = (int)($_POST['subcategory_id'] ?? 0) ?: null;
    $isAnonymous   = isset($_POST['is_anonymous']) ? 1 : 0;
    $deleteIds     = array_map('intval', $_POST['delete_attachments'] ?? []);

    if (empty($title) || empty($description) || $categoryId <= 0) {
        $error = "Title, category, and description are required.";
    } else {
        try {
            // Update core complaint fields
            $student->updateComplaint($complaintId, $studentId, $title, $description, $categoryId, $subcategoryId, $isAnonymous);

            // Delete selected attachments
            foreach ($deleteIds as $attId) {
                if ($attId > 0) {
                    $student->deleteAttachment($attId, $complaintId, $studentId);
                }
            }

            // Upload new attachments
            if (!empty($_FILES['attachments']['name'][0])) {
                $allowed_types = ['image/jpeg', 'image/png', 'application/pdf'];
                $max_size      = 5 * 1024 * 1024;
                $upload_dir    = 'uploads/complaints/' . $complaintId . '/';

                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                $fileErrors = [];
                foreach ($_FILES['attachments']['tmp_name'] as $key => $tmp_name) {
                    if ($_FILES['attachments']['error'][$key] !== UPLOAD_ERR_OK) {
                        continue;
                    }

                    $finfo     = new finfo(FILEINFO_MIME_TYPE);
                    $file_type = $finfo->file($tmp_name);
                    $file_size = $_FILES['attachments']['size'][$key];

                    if (!in_array($file_type, $allowed_types) || $file_size > $max_size) {
                        $fileErrors[] = basename($_FILES['attachments']['name'][$key]);
                        continue;
                    }

                    $file_name   = basename($_FILES['attachments']['name'][$key]);
                    $unique_name = uniqid() . '_' . time() . '_' . $file_name;
                    $target_path = $upload_dir . $unique_name;

                    if (move_uploaded_file($tmp_name, $target_path)) {
                        $attach_stmt = $conn->prepare(
                            "INSERT INTO complaint_attachments (complaint_id, uploaded_by, file_name, file_path, file_type, file_size)
                             VALUES (?, ?, ?, ?, ?, ?)"
                        );
                        $attach_stmt->bind_param("issssi", $complaintId, $userId, $file_name, $target_path, $file_type, $file_size);
                        $attach_stmt->execute();
                        $attach_stmt->close();
                    }
                }
            }

            $_SESSION['message'] = "Complaint updated successfully.";
            header("Location: student_complaint_details.php?id=$complaintId");
            exit;
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Reload fresh data after any POST
$complaint   = $student->readStudentComplaint($complaintId);
$attachments = $student->readStudentComplaintAttachments($complaintId);
$categories  = $category->getCategories();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Complaint #<?= $complaintId ?> | Student</title>
    <link rel="shortcut icon" type="image/x-icon" href="assets/img/favicon.png">
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/animate.css">
    <link rel="stylesheet" href="assets/plugins/select2/css/select2.min.css">
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
        <?php require_once 'includes/sidebar.php'; ?>

        <div id="content" class="w-100">

            <?php require_once 'includes/topbar.php'; ?>

            <div class="p-4">

                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item">
                            <a href="student_dashboard.php"><i class="fas fa-home" style="color:black;"></i></a>
                        </li>
                        <li class="breadcrumb-item">
                            <a href="track_complaints.php" style="color:black;">Track Complaints</a>
                        </li>
                        <li class="breadcrumb-item">
                            <a href="student_complaint_details.php?id=<?= $complaintId ?>" style="color:black;">Complaint #<?= $complaintId ?></a>
                        </li>
                        <li class="breadcrumb-item active">Edit</li>
                    </ol>
                </nav>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger d-flex align-items-center mb-4" role="alert"
                        style="border-left:5px solid #dc2626;border-radius:10px;">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form action="" method="POST" enctype="multipart/form-data">
                    <?= csrf_field() ?>

                    <!-- Basic Information -->
                    <div class="form-card shadow-sm mb-4">
                        <h4 class="mb-3 fw-bold"><i class="fas fa-info-circle me-2"></i>Basic Information</h4>

                        <div class="row">
                            <div class="col-12 mb-3">
                                <label class="form-label fw-bold">Complaint Title <span class="text-danger">*</span></label>
                                <input type="text" name="title" id="title" class="form-control p-3 shadow-sm"
                                    style="border-radius:10px;border:1px solid #e0e6ed;"
                                    placeholder="e.g. Issue with hostel facilities"
                                    value="<?= htmlspecialchars($complaint['complaint_title']) ?>"
                                    maxlength="200" required>
                                <div class="char-count"><span id="titleCount">0</span>/200 characters</div>
                            </div>

                            <div class="col-12 col-md-6 mb-3">
                                <label class="form-label fw-bold">Category <span class="text-danger">*</span></label>
                                <select name="category_id" id="category_id" class="form-select p-3 shadow-sm"
                                    style="border-radius:10px;border:1px solid #e0e6ed;" required>
                                    <option value="">--- Choose Category ---</option>
                                    <?php
                                    $currentCatId = (int)$complaint['category_id'];
                                    if ($categories) {
                                        while ($cat = $categories->fetch_assoc()) {
                                            $sel = ((int)$cat['category_id'] === $currentCatId) ? 'selected' : '';
                                            echo '<option value="' . (int)$cat['category_id'] . '" ' . $sel . '>'
                                                . htmlspecialchars($cat['category_name']) . '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                                <small class="form-hint">
                                    <i class="fas fa-info-circle"></i> Categorizing helps route your complaint to the right department.
                                </small>
                            </div>

                            <div class="col-12 col-md-6 mb-3">
                                <label class="form-label fw-bold">Sub-Category</label>
                                <select name="subcategory_id" id="subcategory_id" class="form-select p-3 shadow-sm"
                                    style="border-radius:10px;border:1px solid #e0e6ed;" disabled>
                                    <option value="">--- Choose category first ---</option>
                                </select>
                                <small class="form-hint">
                                    <i class="fas fa-info-circle"></i> Categorizing helps route your complaint to the right department.
                                </small>
                            </div>
                        </div>
                    </div>

                    <!-- Description -->
                    <div class="form-card shadow-sm mb-4">
                        <h4 class="mb-3 fw-bold"><i class="fas fa-align-left me-2"></i>Complaint Description</h4>

                        <label class="form-label fw-bold">Description <span class="text-danger">*</span></label>
                        <textarea name="description" class="form-control p-3 shadow-sm" rows="10"
                            style="border-radius:8px;border:1px solid #e0e6ed;"
                            placeholder="Please describe your complaint in detail..." required><?= htmlspecialchars($complaint['complaint_description']) ?></textarea>
                        <small class="form-hint">
                            <i class="fas fa-info-circle"></i> Provide a detailed description. The more information you give, the better we can help.
                        </small>
                    </div>

                    <!-- Privacy Options -->
                    <div class="form-card shadow-sm mb-4">
                        <h4 class="mb-4 fw-bold"><i class="fas fa-user-shield me-2"></i>Privacy Options</h4>

                        <div class="form-group">
                            <label style="display:flex;align-items:center;cursor:pointer;">
                                <input type="checkbox" id="is_anonymous" name="is_anonymous" value="1"
                                    style="width:auto;" class="me-2"
                                    <?= !empty($complaint['is_anonymous']) ? 'checked' : '' ?>>
                                <span class="fw-bold">Submit this complaint anonymously</span>
                            </label>
                            <small class="form-hint">
                                <i class="fas fa-shield-alt small"></i> When enabled, your identity will be hidden from department staff. Administrators can still view your identity for system management purposes.
                            </small>
                        </div>
                    </div>

                    <!-- Existing Attachments -->
                    <?php if (!empty($attachments)): ?>
                    <div class="form-card shadow-sm mb-4">
                        <h4 class="mb-1 fw-bold"><i class="fas fa-paperclip me-2"></i>Existing Attachments</h4>
                        <p class="text-muted small mb-3">Check the files you want to remove, then save.</p>

                        <div class="d-flex flex-column gap-2">
                            <?php foreach ($attachments as $att): ?>
                                <?php $isPdf = $att['file_type'] === 'application/pdf'; ?>
                                <div class="d-flex align-items-center gap-3 p-3 rounded border"
                                    style="background:#fafafa;">
                                    <input type="checkbox" class="form-check-input flex-shrink-0 mt-0 delete-checkbox"
                                        name="delete_attachments[]"
                                        value="<?= (int)$att['attachment_id'] ?>"
                                        id="del_<?= $att['attachment_id'] ?>">
                                    <i class="fas <?= $isPdf ? 'fa-file-pdf text-danger' : 'fa-file-image text-success' ?> fa-lg flex-shrink-0"></i>
                                    <label class="flex-grow-1 mb-0 overflow-hidden" for="del_<?= $att['attachment_id'] ?>"
                                        style="cursor:pointer;">
                                        <div class="fw-semibold text-truncate small"><?= htmlspecialchars($att['file_name']) ?></div>
                                        <div class="text-muted" style="font-size:.75rem;">
                                            <?= number_format($att['file_size'] / 1024, 1) ?> KB &bull;
                                            <?= date('d M Y', strtotime($att['uploaded_at'])) ?>
                                        </div>
                                    </label>
                                    <a href="download_attachment.php?id=<?= $att['attachment_id'] ?>&view=1"
                                        target="_blank"
                                        class="btn btn-sm btn-outline-secondary flex-shrink-0"
                                        title="Preview">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div id="deleteWarning" class="alert alert-warning mt-3 d-none" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong id="deleteCount">0</strong> file(s) marked for removal. This cannot be undone.
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- New Attachments -->
                    <div class="form-card shadow-sm mb-4">
                        <h4 class="mb-2 fw-bold"><i class="fas fa-upload me-2"></i>Add New Attachments</h4>

                        <input type="file" id="attachments" name="attachments[]" multiple
                            accept=".pdf,.jpg,.jpeg,.png" class="form-control p-3 shadow-sm"
                            style="border-radius:10px;border:1px solid #e0e6ed;">
                        <small class="form-hint">
                            <i class="fas fa-info-circle"></i> PDF, JPG, JPEG, PNG — max 5 MB per file.
                        </small>
                        <div id="fileList" style="margin-top:10px;"></div>
                    </div>

                    <!-- Actions -->
                    <div class="d-flex gap-3 justify-content-center mb-4">
                        <button type="submit" name="updateComplaintBTN" class="btn btn-primary p-3 fw-bold"
                            style="border-radius:10px;background-color:var(--udsm-blue);min-width:200px;">
                            <i class="fas fa-save me-1"></i> Save Changes
                        </button>
                        <a href="student_complaint_details.php?id=<?= $complaintId ?>"
                            class="btn btn-outline-secondary p-3 fw-bold"
                            style="border-radius:10px;min-width:140px;">
                            Cancel
                        </a>
                    </div>

                </form>

            </div>
        </div>
    </div>

    <script src="assets/js/jquery-3.6.0.min.js"></script>
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/plugins/sweetalert/sweetalert2.all.min.js"></script>
    <script src="assets/plugins/sweetalert/sweetalerts.min.js"></script>
    <script src="assets/js/script.js"></script>

    <script>
        // Title character counter
        const titleInput = document.getElementById('title');
        const titleCount = document.getElementById('titleCount');
        if (titleInput && titleCount) {
            titleCount.textContent = titleInput.value.length;
            titleInput.addEventListener('input', function () {
                titleCount.textContent = this.value.length;
            });
        }

        // Delete checkbox warning banner
        const deleteWarning = document.getElementById('deleteWarning');
        const deleteCountEl = document.getElementById('deleteCount');
        document.querySelectorAll('.delete-checkbox').forEach(function (cb) {
            cb.addEventListener('change', function () {
                const checked = document.querySelectorAll('.delete-checkbox:checked').length;
                if (deleteWarning) {
                    deleteWarning.classList.toggle('d-none', checked === 0);
                    if (deleteCountEl) deleteCountEl.textContent = checked;
                }
                // Strike through the file row when checked
                const row = this.closest('div[class*="d-flex"]');
                if (row) row.style.opacity = this.checked ? '0.4' : '1';
            });
        });

        // New file upload list with validation
        const fileInput   = document.getElementById('attachments');
        const fileList    = document.getElementById('fileList');
        const MAX_SIZE    = 5 * 1024 * 1024;
        const ALLOWED_EXT = ['.pdf', '.jpg', '.jpeg', '.png'];
        const ALLOWED_MIME = ['application/pdf', 'image/jpeg', 'image/png'];
        let selectedFiles = [];

        function formatBytes(bytes) {
            return (bytes / 1024 / 1024).toFixed(2) + ' MB';
        }

        function syncInput() {
            const dt = new DataTransfer();
            selectedFiles.forEach(f => dt.items.add(f));
            fileInput.files = dt.files;
        }

        function renderList() {
            fileList.innerHTML = '';
            if (!selectedFiles.length) return;

            const ul = document.createElement('ul');
            ul.style.cssText = 'list-style:none;padding:0;margin:0;';

            selectedFiles.forEach(function (file, idx) {
                const li = document.createElement('li');
                li.style.cssText = 'padding:8px 12px;background:#e3e8f3;border-radius:8px;margin-bottom:6px;display:flex;align-items:center;gap:8px;';

                const icon = document.createElement('i');
                icon.className = file.type === 'application/pdf' ? 'fas fa-file-pdf' : 'fas fa-file-image';
                icon.style.color = file.type === 'application/pdf' ? '#dc2626' : '#10b981';
                icon.style.flexShrink = '0';

                const name = document.createElement('span');
                name.textContent = file.name;
                name.style.cssText = 'flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:.9rem;';

                const size = document.createElement('span');
                size.textContent = formatBytes(file.size);
                size.style.cssText = 'color:#6b7280;font-size:.8rem;white-space:nowrap;flex-shrink:0;';

                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.title = 'Remove';
                removeBtn.innerHTML = '<i class="fas fa-times"></i>';
                removeBtn.style.cssText = 'background:none;border:none;color:#dc2626;cursor:pointer;padding:2px 6px;font-size:.95rem;flex-shrink:0;';
                removeBtn.addEventListener('click', function () {
                    selectedFiles.splice(idx, 1);
                    renderList();
                    syncInput();
                });

                li.append(icon, name, size, removeBtn);
                ul.appendChild(li);
            });

            fileList.appendChild(ul);
        }

        if (fileInput) {
            fileInput.addEventListener('change', function () {
                const errors = [];
                Array.from(this.files).forEach(function (file) {
                    const ext = '.' + file.name.split('.').pop().toLowerCase();
                    if (!ALLOWED_EXT.includes(ext) || !ALLOWED_MIME.includes(file.type)) {
                        errors.push('"' + file.name + '" — invalid format.');
                        return;
                    }
                    if (file.size > MAX_SIZE) {
                        errors.push('"' + file.name + '" — exceeds 5 MB (' + formatBytes(file.size) + ').');
                        return;
                    }
                    if (!selectedFiles.some(f => f.name === file.name && f.size === file.size)) {
                        selectedFiles.push(file);
                    }
                });
                this.value = '';
                renderList();
                syncInput();
                if (errors.length) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Some files were rejected',
                        html: errors.map(e => '<div class="text-start small mb-1">' + e + '</div>').join(''),
                        confirmButtonColor: '#1e3a5f'
                    });
                }
            });
        }

        // Category → subcategory AJAX
        (function () {
            var currentSubcatId = <?= (int)($complaint['subcategory_id'] ?? 0) ?>;
            var $cat    = $('#category_id');
            var $subcat = $('#subcategory_id');

            function loadSubcategories(categoryId, preselectId) {
                $subcat.prop('disabled', true).html('<option value="">Loading...</option>');
                $.getJSON('ajax/get_subcategories.php', { category_id: categoryId })
                    .done(function (data) {
                        if (!data || data.success !== true || !Array.isArray(data.items) || !data.items.length) {
                            $subcat.html('<option value="">--- No sub-categories ---</option>');
                            return;
                        }
                        var opts = '<option value="">--- Choose sub-category ---</option>';
                        data.items.forEach(function (item) {
                            var sel = (preselectId && parseInt(item.subcategory_id) === preselectId) ? ' selected' : '';
                            opts += '<option value="' + item.subcategory_id + '"' + sel + '>' + item.subcategory_name + '</option>';
                        });
                        $subcat.html(opts).prop('disabled', false);
                    })
                    .fail(function () {
                        $subcat.html('<option value="">--- Failed to load ---</option>');
                    });
            }

            var initialCat = parseInt($cat.val());
            if (initialCat) loadSubcategories(initialCat, currentSubcatId);

            $cat.on('change', function () {
                var id = parseInt($(this).val());
                if (id) {
                    loadSubcategories(id, 0);
                } else {
                    $subcat.prop('disabled', true).html('<option value="">--- Choose category first ---</option>');
                }
            });
        })();
    </script>

</body>

</html>
