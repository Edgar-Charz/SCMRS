<?php
require_once 'config/session.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student_leader') {
    header("Location: login.php");
    exit;
}

$userId = (int) $_SESSION['user_id'];

require_once 'config/Database.php';
require_once 'classes/StudentLeader.php';
require_once 'classes/Complaint.php';
require_once 'classes/ComplaintRouter.php';
require_once 'classes/Department.php';
require_once 'classes/Notification.php';
require_once 'classes/Settings.php';
require_once 'includes/csrf.php';

$db = new Database();
$conn = $db->connect();
$leader = new StudentLeader($conn, $userId);
$complaint = new Complaint($conn);
$department = new Department($conn);
$settingsSvc = new Settings($conn);

$categories = $complaint->getComplaintCategories();
$departments = $department->getDepartments();
$studentId = $leader->getStudentId();

$maxAttachmentMb = $settingsSvc->getMaxAttachmentSizeMb();
$allowedAttachmentExt = $settingsSvc->getAllowedAttachmentExtensions();
$allowedAttachmentLabel = $settingsSvc->getAllowedAttachmentLabel();
$allowedAttachmentMimes = $settingsSvc->getAllowedAttachmentTypes();

$message = $error = '';

if (isset($_POST['submitComplaintBTN'])) {
    csrf_verify();
    try {
        if (!$studentId) {
            throw new Exception("No student profile found for your account. Please contact an administrator.");
        }

        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category_id = isset($_POST['category_id']) ? (int) $_POST['category_id'] : null;
        $subcategory_id = isset($_POST['subcategory_id']) ? (int) $_POST['subcategory_id'] : null;
        $department_id = isset($_POST['department_id']) ? (int) $_POST['department_id'] : null;
        $is_anonymous = isset($_POST['is_anonymous']) ? 1 : 0;
        $onBehalfStudentId = isset($_POST['on_behalf_student_id']) ? (int) $_POST['on_behalf_student_id'] : 0;

        // Filing for someone else - resolve and validate the target student
        $targetStudentId = $studentId;
        $onBehalfUserId = null;
        $onBehalfUsername = null;
        if ($onBehalfStudentId > 0 && $onBehalfStudentId !== $studentId) {
            $targetStudent = $leader->findStudentById($onBehalfStudentId);
            if (!$targetStudent) {
                throw new Exception("Selected student could not be found.");
            }
            $targetStudentId = (int) $targetStudent['student_id'];
            $onBehalfUserId = (int) $targetStudent['user_id'];
            $onBehalfUsername = $targetStudent['username'];
        }

        $categoryMeta = $category_id ? $complaint->getCategoryRoutingMeta($category_id) : null;
        if (!$categoryMeta) {
            throw new Exception("Please select a valid category.");
        }

        $requiresDeptSelection = (int) $categoryMeta['requires_department_selection'] === 1;
        if ($requiresDeptSelection) {
            if (empty($department_id)) {
                throw new Exception("Please select a department for this category.");
            }
            $effectiveDepartmentId = $department_id;
        } else {
            $effectiveDepartmentId = $categoryMeta['auto_assign_department_id'];
        }

        $newComplaintId = $complaint->createComplaint(
            $title,
            $description,
            $category_id,
            $effectiveDepartmentId,
            $is_anonymous,
            $targetStudentId,
            $userId,
            $subcategory_id
        );

        if ($newComplaintId) {
            $routeResult = (new ComplaintRouter($conn))->routeComplaint(
                $newComplaintId,
                $category_id,
                $subcategory_id,
                $effectiveDepartmentId,
                $categoryMeta['default_priority']
            );

            // Notify admins
            $notifMsg = $is_anonymous
                ? "A new anonymous complaint has been submitted."
                : "New complaint from " . htmlspecialchars($_SESSION['username']) . " (Rep): \"$title\"";
            $notifMsg .= $routeResult['routed']
                ? " (auto-assigned to staff)"
                : " - requires manual assignment.";
            (new Notification($conn))->notifyAllAdmins(
                $notifMsg,
                'new_complaint',
                "complaint_details.php?id=$newComplaintId",
                $newComplaintId
            );

            // Notify other leaders in the department (excluding the leader who just filed it)
            if ($effectiveDepartmentId && $complaint->isLeaderEndorsable($category_id, $subcategory_id)) {
                $leaderMsg = $is_anonymous
                    ? "A new anonymous complaint has been submitted in your department."
                    : "New complaint in your department: \"$title\"";
                $leader->notifyLeadersInDepartment(
                    $effectiveDepartmentId,
                    $leaderMsg,
                    'new_complaint_in_rep_scope',
                    "leader_complaint_details.php?id=$newComplaintId",
                    $newComplaintId,
                    $userId
                );
            }

            // Let the target student know a complaint was filed on their behalf
            if ($onBehalfUserId) {
                (new Notification($conn))->create(
                    $onBehalfUserId,
                    "Student Rep " . htmlspecialchars($_SESSION['username']) . " submitted a complaint on your behalf: \"$title\"",
                    'filed_on_behalf',
                    "student_complaint_details.php?id=$newComplaintId",
                    $newComplaintId
                );
            }

            $_SESSION['message'] = $onBehalfUsername
                ? "Complaint submitted successfully on behalf of {$onBehalfUsername}."
                : "Complaint submitted successfully.";
            header("Location: " . ($onBehalfUserId ? "leader_dashboard.php" : "leader_my_complaints.php"));
            exit;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

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
    <title>Student Rep | Submit Complaint</title>
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
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item">
                            <a href="leader_dashboard.php"><i class="fas fa-chart-pie" style="color:black;"></i></a>
                        </li>
                        <li class="breadcrumb-item"><a href="leader_dashboard.php" style="color:black;">Student Rep</a>
                        </li>
                        <li class="breadcrumb-item active">Submit Complaint</li>
                    </ol>
                </nav>

                <?php if (!$studentId): ?>
                    <div class="container-card shadow-sm text-center py-5">
                        <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                        <h5 class="mb-2">Student Profile Not Found</h5>
                        <p class="text-muted">
                            Your account does not have a linked student profile. Please contact an administrator.
                        </p>
                        <a href="leader_dashboard.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                        </a>
                    </div>
                <?php else: ?>

                    <form action="" method="POST" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <input type="hidden" name="on_behalf_student_id" id="on_behalf_student_id" value="">

                        <div class="form-card shadow-sm mb-4">
                            <h4 class="mb-3 fw-bold"><i class="fas fa-user me-2"></i>Complainant</h4>

                            <div class="mb-2">
                                <label class="d-flex align-items-center mb-2" style="cursor:pointer;">
                                    <input type="radio" name="complainant_mode" value="self" checked class="me-2"
                                        style="width:auto;">
                                    <span class="fw-bold">Myself</span>
                                </label>
                                <label class="d-flex align-items-center" style="cursor:pointer;">
                                    <input type="radio" name="complainant_mode" value="other" class="me-2"
                                        style="width:auto;">
                                    <span class="fw-bold">Another Student</span>
                                </label>
                            </div>

                            <div id="onBehalfWrap" style="display:none;">
                                <label class="form-label fw-bold">Search Student <span class="text-danger">*</span></label>
                                <input type="text" id="studentSearchInput" class="form-control p-3 shadow-sm"
                                    style="border-radius:10px;border:1px solid #e0e6ed;"
                                    placeholder="Search by name or registration number...">
                                <div id="studentSearchResults" class="list-group shadow-sm" style="display:none;position:relative;z-index:5;"></div>
                                <div id="selectedStudentChip" class="alert alert-info py-2 px-3 mt-2 d-none">
                                    <i class="fas fa-user-check me-1"></i>
                                    <span id="selectedStudentText"></span>
                                    <button type="button" id="changeStudentBtn" class="btn btn-sm btn-link p-0 ms-2">Change</button>
                                </div>
                                <small class="form-hint">
                                    <i class="fas fa-info-circle"></i> The student you select will be notified that this
                                    complaint was filed on their behalf.
                                </small>
                            </div>
                        </div>

                        <div class="form-card shadow-sm mb-4">
                            <h4 class="mb-3 fw-bold"><i class="fas fa-info-circle me-2"></i>
                                Basic Information
                            </h4>

                            <div class="row">
                                <div class="col-12 mb-2">
                                    <label class="form-label fw-bold">Complaint Title <span
                                            class="text-danger">*</span></label>
                                    <input type="text" name="title" id="title" class="form-control p-3 shadow-sm"
                                        style="border-radius:10px;border:1px solid #e0e6ed;"
                                        placeholder="e.g. Issue with Hostel facilities" minlength="10" maxlength="200"
                                        required>
                                    <div class="char-count"><span id="titleCount">0</span>/200 characters (min 10)</div>
                                </div>

                                <div class="col-12 col-md-6 mb-2">
                                    <label class="form-label fw-bold">Category <span class="text-danger">*</span></label>
                                    <select class="form-select p-3 shadow-sm" name="category_id" id="category_id"
                                        style="border-radius:10px;border:1px solid #e0e6ed;">
                                        <option value="" selected disabled>--- Choose Category ---</option>
                                        <?php if ($categories): ?>
                                            <?php while ($cat_row = $categories->fetch_assoc()): ?>
                                                <option value="<?= $cat_row['category_id'] ?>"
                                                    data-requires-dept="<?= (int) $cat_row['requires_department_selection'] ?>">
                                                    <?= htmlspecialchars($cat_row['category_name']) ?>
                                                </option>
                                            <?php endwhile; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>

                                <div class="col-12 col-md-6 mb-2">
                                    <label class="form-label fw-bold">Sub-Category <span
                                            class="text-danger">*</span></label>
                                    <select class="form-select p-3 shadow-sm" name="subcategory_id" id="subcategory_id"
                                        disabled style="border-radius:10px;border:1px solid #e0e6ed;">
                                        <option value="" selected disabled>--- Choose category first ---</option>
                                    </select>
                                </div>

                                <div class="col-12 col-md-6 mb-2" id="departmentFieldWrap" style="display:none;">
                                    <label class="form-label fw-bold">Department <span class="text-danger">*</span></label>
                                    <select class="form-select p-3 shadow-sm" name="department_id" id="department_id"
                                        style="border-radius:10px;border:1px solid #e0e6ed;">
                                        <option value="" selected disabled>--- Select Department ---</option>
                                        <?php foreach ($departments as $dept): ?>
                                            <option value="<?= $dept['department_id'] ?>">
                                                <?= htmlspecialchars($dept['department_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="form-hint">
                                        <i class="fas fa-info-circle"></i> This category requires selecting a department
                                        to route the complaint to the right staff member.
                                    </small>
                                </div>
                            </div>
                        </div>

                        <div class="form-card shadow-sm mb-4">
                            <h4 class="mb-3 fw-bold"><i class="fas fa-align-left me-2"></i>Complaint Description</h4>
                            <div class="col-12 mb-2">
                                <label class="form-label fw-bold">Description <span class="text-danger">*</span></label>
                                <textarea name="description" id="description" class="form-control p-3 shadow-sm" rows="10"
                                    style="border-radius:8px;border:1px solid #e0e6ed;"
                                    placeholder="Please describe your complaint in detail..." minlength="30"
                                    maxlength="5000" required></textarea>
                                <div class="char-count"><span id="descCount">0</span>/5000 characters (min 30)</div>
                            </div>
                        </div>

                        <div class="form-card shadow-sm mb-4">
                            <h4 class="mb-2 fw-bold"><i class="fas fa-paperclip me-2"></i>Evidence Attachments</h4>
                            <div class="col-12 mb-2">
                                <label class="form-label fw-bold">Supporting Evidence / Documents</label>
                                <input type="file" id="attachments" name="attachments[]" multiple
                                    accept="<?= htmlspecialchars(implode(',', $allowedAttachmentExt)) ?>" class="form-control p-3 shadow-sm"
                                    style="border-radius:10px;border:1px solid #e0e6ed;">
                                <small class="form-hint">
                                    <i class="fas fa-info-circle"></i> <?= htmlspecialchars($allowedAttachmentLabel) ?> - max <?= $maxAttachmentMb ?> MB each.
                                </small>
                                <div id="fileList" style="margin-top:10px;"></div>
                            </div>
                        </div>

                        <div class="form-card shadow-sm mb-4">
                            <h4 class="mb-4 fw-bold"><i class="fas fa-user-shield me-2"></i>Privacy Options</h4>
                            <div class="form-group">
                                <label style="display:flex;align-items:center;">
                                    <input type="checkbox" id="is_anonymous" name="is_anonymous" value="1"
                                        style="width:auto;" class="me-2">
                                    <span class="fw-bold">Submit this complaint anonymously</span>
                                </label>
                                <small class="form-hint">
                                    <i class="fas fa-shield-alt small"></i>
                                    Your identity will be hidden from department staff when enabled.
                                    Administrators can still view it for system management.
                                </small>
                            </div>
                        </div>

                        <div class="d-rigid gap-2 text-center">
                            <button type="submit" name="submitComplaintBTN" class="btn btn-primary p-3 fw-bold mb-2"
                                style="border-radius:10px;background-color:var(--udsm-blue);width:70%;">
                                Submit Complaint
                            </button>
                            <a href="leader_dashboard.php" class="btn btn-danger p-3 fw-bold mb-2"
                                style="border-radius:10px;width:20%;">Cancel</a>
                        </div>
                    </form>
                <?php endif; ?>

            </div><!-- /p-4 -->
        </div><!-- /content -->
    </div>

    <?php $useDataTablesJs = true;
    include 'includes/foot_scripts.php'; ?>
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

        // File upload with validation
        const fileInput = document.getElementById('attachments');
        const fileList = document.getElementById('fileList');
        const MAX_SIZE = <?= (int) ($maxAttachmentMb * 1024 * 1024) ?>;
        const ALLOWED_EXT = <?= json_encode($allowedAttachmentExt) ?>;
        const ALLOWED_MIME = <?= json_encode($allowedAttachmentMimes) ?>;
        const ALLOWED_LABEL = <?= json_encode($allowedAttachmentLabel) ?>;
        const MAX_SIZE_MB = <?= $maxAttachmentMb ?>;
        let selectedFiles = [];

        function formatBytes(b) { return (b / 1024 / 1024).toFixed(2) + ' MB'; }

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
            selectedFiles.forEach((file, idx) => {
                const li = document.createElement('li');
                li.style.cssText = 'padding:8px 12px;background:#e3e8f3;border-radius:8px;margin-bottom:6px;display:flex;align-items:center;gap:8px;';
                li.innerHTML = `<i class="${file.type === 'application/pdf' ? 'fas fa-file-pdf' : 'fas fa-file-image'}" style="color:${file.type === 'application/pdf' ? '#dc2626' : '#10b981'};flex-shrink:0;"></i>
                    <span style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:.9rem;">${file.name}</span>
                    <span style="color:#6b7280;font-size:.8rem;white-space:nowrap;flex-shrink:0;">${formatBytes(file.size)}</span>
                    <button type="button" style="background:none;border:none;color:#dc2626;cursor:pointer;padding:2px 6px;font-size:.95rem;flex-shrink:0;" title="Remove"><i class="fas fa-times"></i></button>`;
                li.querySelector('button').addEventListener('click', function () {
                    selectedFiles.splice(idx, 1);
                    renderList();
                    syncInput();
                });
                ul.appendChild(li);
            });
            fileList.appendChild(ul);
        }

        if (fileInput) {
            fileInput.addEventListener('change', function () {
                const errors = [];
                Array.from(this.files).forEach(file => {
                    const ext = '.' + file.name.split('.').pop().toLowerCase();
                    if (!ALLOWED_EXT.includes(ext) || !ALLOWED_MIME.includes(file.type)) {
                        errors.push(`"${file.name}" - invalid format. Only ${ALLOWED_LABEL} are allowed.`);
                        return;
                    }
                    if (file.size > MAX_SIZE) {
                        errors.push(`"${file.name}" - exceeds ${MAX_SIZE_MB} MB.`);
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
                        icon: 'warning', title: 'Some files were rejected',
                        html: errors.map(e => `<div class="text-start small mb-1">${e}</div>`).join(''),
                        confirmButtonColor: '#1e3a5f'
                    });
                }
            });
        }
    </script>
    <script>
        $(function () {
            // Subcategory loader
            const $cat = $('#category_id');
            const $subcat = $('#subcategory_id');
            const $deptWrap = $('#departmentFieldWrap');
            const $deptSelect = $('#department_id');

            function resetSubcat(msg) {
                $subcat.prop('disabled', true).html(`<option value="" disabled selected>${msg}</option>`);
            }
            resetSubcat('--- Choose category first ---');

            function toggleDepartmentField(selectEl) {
                const selectedOpt = selectEl.options[selectEl.selectedIndex];
                const requiresDept = !!(selectedOpt && selectedOpt.dataset.requiresDept === '1');
                $deptWrap.toggle(requiresDept);
                $deptSelect.prop('required', requiresDept);
                if (!requiresDept) $deptSelect.val('');
            }

            $cat.on('change', function () {
                const catId = $(this).val();
                toggleDepartmentField(this);
                if (!catId) { resetSubcat('--- Choose category first ---'); return; }
                resetSubcat('Loading...');
                $.getJSON('ajax/get_subcategories.php', { category_id: catId })
                    .done(function (data) {
                        if (!data || !data.success || !data.items || !data.items.length) {
                            resetSubcat('--- No sub-categories found ---');
                            return;
                        }
                        let opts = '<option value="" disabled selected>--- Choose sub-Category ---</option>';
                        data.items.forEach(i => { opts += `<option value="${i.subcategory_id}">${i.subcategory_name}</option>`; });
                        $subcat.html(opts).prop('disabled', false);
                    })
                    .fail(function () { resetSubcat('--- Failed to load ---'); });
            });

            // Complainant: self vs. on behalf of another student
            const $onBehalfWrap = $('#onBehalfWrap');
            const $onBehalfId = $('#on_behalf_student_id');
            const $studentSearchInput = $('#studentSearchInput');
            const $studentSearchResults = $('#studentSearchResults');
            const $selectedChip = $('#selectedStudentChip');
            const $selectedChipText = $('#selectedStudentText');

            function clearSelectedStudent() {
                $onBehalfId.val('');
                $selectedChip.addClass('d-none');
                $studentSearchInput.val('').show();
                $studentSearchResults.hide().empty();
            }

            $('input[name="complainant_mode"]').on('change', function () {
                const isOther = $(this).val() === 'other';
                $onBehalfWrap.toggle(isOther);
                if (!isOther) clearSelectedStudent();
            });

            let searchTimer;
            $studentSearchInput.on('input', function () {
                clearTimeout(searchTimer);
                const q = $(this).val().trim();
                if (q.length < 2) { $studentSearchResults.hide().empty(); return; }
                searchTimer = setTimeout(function () {
                    $.getJSON('ajax/search_leader_students.php', { q: q })
                        .done(function (data) {
                            $studentSearchResults.empty();
                            if (!data || !data.success || !data.items || !data.items.length) {
                                $studentSearchResults.append(
                                    '<div class="list-group-item text-muted small">No students found</div>'
                                ).show();
                                return;
                            }
                            data.items.forEach(function (s) {
                                const $item = $('<button type="button" class="list-group-item list-group-item-action small"></button>')
                                    .text(s.username + ' (' + s.student_registration_number + ')')
                                    .on('click', function () {
                                        $onBehalfId.val(s.student_id);
                                        $selectedChipText.text(s.username + ' - ' + s.student_registration_number);
                                        $selectedChip.removeClass('d-none');
                                        $studentSearchInput.val('').hide();
                                        $studentSearchResults.hide().empty();
                                    });
                                $studentSearchResults.append($item);
                            });
                            $studentSearchResults.show();
                        })
                        .fail(function () { $studentSearchResults.hide().empty(); });
                }, 250);
            });

            $('#changeStudentBtn').on('click', clearSelectedStudent);

            $('form[enctype]').on('submit', function (e) {
                const isOther = $('input[name="complainant_mode"]:checked').val() === 'other';
                if (isOther && !$onBehalfId.val()) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'warning',
                        title: 'Select a student',
                        text: 'Please search and select the student you are filing this complaint for.',
                        confirmButtonColor: '#1e3a5f'
                    });
                }
            });
        });
    </script>
</body>

</html>