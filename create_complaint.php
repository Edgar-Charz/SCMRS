<?php
require_once 'config/session.php';

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
require_once "classes/Notification.php";
require_once "includes/csrf.php";

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
    csrf_verify();
    try {

        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description']);
        $category_id = isset($_POST['category_id']) ? (int) $_POST['category_id'] : null;
        $subcategory_id = isset($_POST['subcategory_id']) ? (int) $_POST['subcategory_id'] : null;
        $department_id = isset($_POST['department_id']) ? (int) $_POST['department_id'] : null;
        $is_anonymous = isset($_POST['is_anonymous']) ? 1 : 0;
        $student_id = $studentId;
        $user_id = $_SESSION['user_id'];

        $newComplaintId = $complaint->createComplaint($title, $description, $category_id, $department_id, $is_anonymous, $student_id, $user_id, $subcategory_id);
        if ($newComplaintId) {
            $notifMsg = $is_anonymous
                ? "A new anonymous complaint has been submitted."
                : "New complaint from " . htmlspecialchars($_SESSION['username']) . ": \"$title\"";
            (new Notification($conn))->notifyAllAdmins(
                $notifMsg,
                'new_complaint',
                "complaint_details.php?id=$newComplaintId",
                $newComplaintId
            );
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
        <?php require_once 'includes/sidebar.php'; ?>

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
                <div aria-live="polite" aria-atomic="true"
                    class="position-fixed top-0 start-50 translate-middle-x p-3 w-100"
                    style="z-index: 1100; max-width: 800px;">
                    <?php if (!empty($message) || !empty($error)):
                        $type = !empty($message) ? 'success' : 'danger';
                        $text = !empty($message) ? $message : $error;
                        $icon = ($type === 'success') ? 'fa-check-circle' : 'fa-exclamation-circle';
                        ?>
                        <div id="livetoast"
                            class="toast show align-items-center text-white bg-<?php echo $type ?> border-0 w-100"
                            role="alert" aria-live="assertive" aria-atomic="true">
                            <div class="d-flex">
                                <div class="toast-body">
                                    <i class="fas <?php echo $icon; ?> me-2"></i>
                                    <?php echo htmlspecialchars($text); ?>
                                </div>
                                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"
                                    aria-label="Close">

                                </button>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <!-- / Alert -->

                <form action="" method="POST" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <div class="form-card shadow-sm mb-4" style="align-items: center;">
                        <h4 class="mb-3 fw-bold"><i class="fas fa-info-circle me-2"></i>Basic Information</h4>

                        <div class="row">
                            <div class="col-12 col-md-12 col-lg-12 mb-2">
                                <label for="" class="form-label fw-bold">Complaint Title</label>
                                <span class="text-danger">*</span>
                                <input type="hidden" name="student_id" value="<?= $studentId; ?>">
                                <input type="text" name="title" id="title" class="form-control p-3 shadow-sm"
                                    style="border-radius: 10px; border: 1px solid #e0e6ed;"
                                    placeholder="i.e; Issue with Hostel facilities" minlength="10" maxlength="200"
                                    required>
                                <div class="char-count"><span id="titleCount">0</span>/200 characters (min 10)</div>
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
                                    <i class="fas fa-info-circle"></i> Categorizing helps route your complaint to the
                                    right department.
                                </small>
                            </div>

                            <div class="col-12 col-md-6 col-lg-6 mb-2">
                                <label for="" class="form-label fw-bold">Sub-Category</label>
                                <span class="text-danger">*</span>
                                <select class="form-select p-3 shadow-sm" name="subcategory_id" id="subcategory_id"
                                    disabled style="border-radius: 10px; border: 1px solid #e0e6ed;">
                                    <option value="" selected disabled>--- Choose sub-Category ---</option>
                                </select>
                                <small class="form-hint">
                                    <!-- <i class="fas fa-info-circle"></i> Categorizing helps route your complaint to the right department. -->
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
                                <textarea name="description" id="description" class="form-control p-3 shadow-sm"
                                    rows="10" style="border-radius: 8px; border: 1px solid #e0e6ed;"
                                    placeholder="Please describe your complaint in detail..." minlength="30"
                                    maxlength="5000" required></textarea>
                                <div class="char-count"><span id="descCount">0</span>/5000 characters (min 30)</div>
                                <small class="form-hint">
                                    <i class="fas fa-info-circle"></i> Provide a detailed description of your complaint.
                                    The more information you provide, the better we can help you.
                                </small>
                            </div>

                        </div>

                    </div>

                    <div class="form-card shadow-sm mb-4">
                        <h4 class="mb-2 fw-bold"><i class="fas fa-paperclip me-2"></i>Evidence Attachments</h4>

                        <div class="row">
                            <div class="col-12 col-md-12 col-lg-12 mb-2">
                                <label for="" class="form-label fw-bold">Supporting Evidence / Documents</label>
                                <input type="file" id="attachments" name="attachments[]" multiple
                                    accept=".pdf,.jpg,.jpeg,.png" class="form-control p-3 shadow-sm"
                                    style="border-radius: 10px; border: 1px solid #e0e6ed;">
                                <small class="form-hint">
                                    <i class="fas fa-info-circle"></i> You can upload multiple files (PDF or images).
                                    Maximum file size: 5MB per file. Accepted formats: PDF, JPG, JPEG, PNG.
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
                                    <input type="checkbox" id="is_anonymous" name="is_anonymous" value="1"
                                        style="width: auto;" class="me-2">
                                    <span class="fw-bold">Submit this complaint anonymously</span>
                                </label>
                                <small class="form-hint">
                                    <i class="fas fa-shield-alt small"></i> When enabled, your identity will be hidden
                                    from department staff. Administrators can still view your identity for system
                                    management purposes.
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
        // Character counters
        function wireCounter(inputId, countId) {
            var el = document.getElementById(inputId);
            var counter = document.getElementById(countId);
            if (!el || !counter) return;
            counter.textContent = el.value.length;
            el.addEventListener('input', function () { counter.textContent = this.value.length; });
        }
        wireCounter('title', 'titleCount');
        wireCounter('description', 'descCount');

        // Inline field validation (blur-based for mobile UX)
        function fieldError(el, msg) {
            el.classList.add('is-invalid');
            var fb = el.nextElementSibling;
            while (fb && !fb.classList.contains('invalid-feedback') && !fb.classList.contains('char-count')) {
                fb = fb.nextElementSibling;
            }
            if (!fb || fb.classList.contains('char-count')) {
                // insert inline span after the element
                fb = el.parentNode.querySelector('.field-inline-error');
                if (!fb) {
                    fb = document.createElement('div');
                    fb.className = 'field-inline-error text-danger small mt-1';
                    el.insertAdjacentElement('afterend', fb);
                }
            }
            if (fb) fb.textContent = msg;
        }
        function fieldOk(el) {
            el.classList.remove('is-invalid');
            var fb = el.parentNode.querySelector('.field-inline-error');
            if (fb) fb.textContent = '';
        }

        var titleEl = document.getElementById('title');
        var descEl = document.getElementById('description');
        var catEl = document.getElementById('category_id');

        if (titleEl) {
            titleEl.addEventListener('blur', function () {
                var v = this.value.trim();
                if (!v) fieldError(this, 'Complaint title is required.');
                else if (v.length < 10) fieldError(this, 'Title must be at least 10 characters (currently ' + v.length + ').');
                else fieldOk(this);
            });
            titleEl.addEventListener('input', function () {
                if (this.classList.contains('is-invalid') && this.value.trim().length >= 10) fieldOk(this);
            });
        }

        if (descEl) {
            descEl.addEventListener('blur', function () {
                var v = this.value.trim();
                if (!v) fieldError(this, 'Description is required.');
                else if (v.length < 30) fieldError(this, 'Description must be at least 30 characters (currently ' + v.length + ').');
                else fieldOk(this);
            });
            descEl.addEventListener('input', function () {
                if (this.classList.contains('is-invalid') && this.value.trim().length >= 30) fieldOk(this);
            });
        }

        if (catEl) {
            catEl.addEventListener('blur', function () {
                if (!this.value) fieldError(this, 'Please select a category.');
                else fieldOk(this);
            });
            catEl.addEventListener('change', function () {
                if (this.value) fieldOk(this);
            });
        }

        // File upload with remove buttons, format & size validation
        const fileInput = document.getElementById('attachments');
        const fileList = document.getElementById('fileList');
        const MAX_SIZE = 5 * 1024 * 1024;
        const ALLOWED = ['application/pdf', 'image/jpeg', 'image/png'];
        const ALLOWED_EXT = ['.pdf', '.jpg', '.jpeg', '.png'];
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
            if (selectedFiles.length === 0) return;

            const ul = document.createElement('ul');
            ul.style.cssText = 'list-style:none;padding:0;margin:0;';

            selectedFiles.forEach((file, idx) => {
                const li = document.createElement('li');
                li.style.cssText = 'padding:8px 12px;background:#e3e8f3;border-radius:8px;margin-bottom:6px;display:flex;align-items:center;gap:8px;';

                const icon = document.createElement('i');
                icon.className = file.type === 'application/pdf' ? 'fas fa-file-pdf' : 'fas fa-file-image';
                icon.style.color = file.type === 'application/pdf' ? '#dc2626' : '#10b981';
                icon.style.flexShrink = '0';

                const name = document.createElement('span');
                name.textContent = file.name;
                name.style.cssText = 'flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:0.9rem;';

                const size = document.createElement('span');
                size.textContent = formatBytes(file.size);
                size.style.cssText = 'color:#6b7280;font-size:0.8rem;white-space:nowrap;flex-shrink:0;';

                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.title = 'Remove file';
                removeBtn.innerHTML = '<i class="fas fa-times"></i>';
                removeBtn.style.cssText = 'background:none;border:none;color:#dc2626;cursor:pointer;padding:2px 6px;font-size:0.95rem;flex-shrink:0;line-height:1;';
                removeBtn.addEventListener('click', function () {
                    selectedFiles.splice(idx, 1);
                    renderList();
                    syncInput();
                });

                li.appendChild(icon);
                li.appendChild(name);
                li.appendChild(size);
                li.appendChild(removeBtn);
                ul.appendChild(li);
            });

            fileList.appendChild(ul);
        }

        if (fileInput && fileList) {
            fileInput.addEventListener('change', function () {
                const errors = [];

                Array.from(this.files).forEach(file => {
                    const ext = '.' + file.name.split('.').pop().toLowerCase();
                    if (!ALLOWED_EXT.includes(ext) || !ALLOWED.includes(file.type)) {
                        errors.push(`"${file.name}" — invalid format. Only PDF, JPG, JPEG, PNG are allowed.`);
                        return;
                    }
                    if (file.size > MAX_SIZE) {
                        errors.push(`"${file.name}" — exceeds the 5 MB limit (${formatBytes(file.size)}).`);
                        return;
                    }
                    const isDuplicate = selectedFiles.some(f => f.name === file.name && f.size === file.size);
                    if (!isDuplicate) {
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
                        html: errors.map(e => `<div class="text-start small mb-1">${e}</div>`).join(''),
                        confirmButtonColor: '#1e3a5f'
                    });
                }
            });
        }
    </script>
    <script>
    // ── Complaint draft autosave (localStorage) ───────────────────────────────
    (function () {
        var DRAFT_KEY = 'complaint_draft';
        var DRAFT_TTL = 24 * 60 * 60 * 1000; // discard drafts older than 24 h

        function saveDraft() {
            try {
                var anon = document.getElementById('is_anonymous');
                localStorage.setItem(DRAFT_KEY, JSON.stringify({
                    title:          document.getElementById('title')?.value        || '',
                    description:    document.getElementById('description')?.value  || '',
                    category_id:    document.getElementById('category_id')?.value  || '',
                    subcategory_id: document.getElementById('subcategory_id')?.value || '',
                    is_anonymous:   anon ? anon.checked : false,
                    saved_at:       Date.now()
                }));
            } catch (e) {}
        }

        function loadDraft() {
            try {
                var raw = localStorage.getItem(DRAFT_KEY);
                if (!raw) return null;
                var d = JSON.parse(raw);
                if (!d || Date.now() - d.saved_at > DRAFT_TTL) {
                    localStorage.removeItem(DRAFT_KEY);
                    return null;
                }
                return d;
            } catch (e) { return null; }
        }

        function clearDraft() {
            try { localStorage.removeItem(DRAFT_KEY); } catch (e) {}
            var b = document.getElementById('draftBanner');
            if (b) b.remove();
        }

        function timeAgo(ms) {
            var s = Math.round((Date.now() - ms) / 1000);
            if (s < 60)   return 'just now';
            if (s < 3600) return Math.round(s / 60) + ' min ago';
            return Math.round(s / 3600) + ' hr ago';
        }

        function showBanner(savedAt) {
            if (document.getElementById('draftBanner')) return;
            var banner = document.createElement('div');
            banner.id = 'draftBanner';
            banner.className = 'alert alert-info d-flex align-items-center gap-2 py-2 mb-3';
            banner.innerHTML =
                '<i class="fas fa-history flex-shrink-0"></i>' +
                '<span class="flex-grow-1 small">Draft restored from <strong>' + timeAgo(savedAt) + '</strong>.</span>' +
                '<button type="button" class="btn-close btn-sm ms-2 flex-shrink-0" id="discardDraftBtn" title="Discard draft"></button>';
            var form = document.querySelector('form[enctype]');
            if (form) form.insertBefore(banner, form.firstChild);
            document.getElementById('discardDraftBtn').addEventListener('click', function () {
                clearDraft();
                // Also reset the form fields that were restored
                var form = document.querySelector('form[enctype]');
                if (form) form.reset();
                var tc = document.getElementById('titleCount');
                var dc = document.getElementById('descCount');
                if (tc) tc.textContent = '0';
                if (dc) dc.textContent = '0';
            });
        }

        // Save on every input change
        ['title', 'description', 'category_id', 'subcategory_id'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.addEventListener('input', saveDraft);
        });
        var anonEl = document.getElementById('is_anonymous');
        if (anonEl) anonEl.addEventListener('change', saveDraft);

        // Also save when subcategory changes (it's set programmatically after AJAX)
        var subcatEl = document.getElementById('subcategory_id');
        if (subcatEl) subcatEl.addEventListener('change', saveDraft);

        // Clear draft when the Cancel / Reset button is clicked
        var resetBtn = document.querySelector('button[type="reset"]');
        if (resetBtn) resetBtn.addEventListener('click', clearDraft);

        // Restore draft on page load
        document.addEventListener('DOMContentLoaded', function () {
            var draft = loadDraft();
            if (!draft) return;

            var titleEl = document.getElementById('title');
            var descEl  = document.getElementById('description');
            var catEl   = document.getElementById('category_id');
            var anonEl  = document.getElementById('is_anonymous');

            if (titleEl && draft.title)       titleEl.value   = draft.title;
            if (descEl  && draft.description) descEl.value    = draft.description;
            if (anonEl)                       anonEl.checked  = !!draft.is_anonymous;

            // Update char counters
            var tc = document.getElementById('titleCount');
            var dc = document.getElementById('descCount');
            if (tc && titleEl) tc.textContent = titleEl.value.length;
            if (dc && descEl)  dc.textContent = descEl.value.length;

            // Restore category, then let the existing AJAX handler restore subcategory
            if (catEl && draft.category_id) {
                catEl.value = draft.category_id;
                if (draft.subcategory_id) window._draftSubcatId = draft.subcategory_id;
                $(catEl).trigger('change'); // fires the subcategory AJAX load
            }

            showBanner(draft.saved_at);
        });
    })();
    </script>

    <script>
        $(document).ready(function () {
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
        $(function () {
            const $category = $("#category_id");
            const $subcat = $("#subcategory_id");

            function resetSubcategories(message) {
                $subcat.prop("disabled", true);
                $subcat.html(`<option value="" selected disabled>${message}</option>`);
            }

            resetSubcategories("--- Choose category first ---");

            $category.on("change", function () {
                const categoryId = $(this).val();

                if (!categoryId) {
                    resetSubcategories("--- Choose category first ---");
                    return;
                }

                resetSubcategories("Loading sub-categories...");

                $.getJSON("ajax/get_subcategories.php", {
                    category_id: categoryId
                })
                    .done(function (data) {
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
                        items.forEach(function (item) {
                            options += `<option value="${item.subcategory_id}">${item.subcategory_name}</option>`;
                        });
                        $subcat.html(options);
                        $subcat.prop("disabled", false);
                        // Restore subcategory value saved in a draft
                        if (window._draftSubcatId) {
                            $subcat.val(window._draftSubcatId);
                            window._draftSubcatId = null;
                        }
                    })
                    .fail(function () {
                        resetSubcategories("--- Failed to load sub-categories ---");
                    });
            });
        });
    </script>

</body>

</html>