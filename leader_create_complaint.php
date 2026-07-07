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
require_once 'classes/Department.php';
require_once 'classes/Notification.php';
require_once 'includes/csrf.php';

$db = new Database();
$conn = $db->connect();
$leader = new StudentLeader($conn, $userId);
$complaint = new Complaint($conn);
$department = new Department($conn);

$categories = $complaint->getComplaintCategories();
$departments = $department->getDepartments();
$studentId = $leader->getStudentId();

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
        $preferred_staff_id = trim($_POST['preferred_staff_id'] ?? '');
        $is_anonymous = isset($_POST['is_anonymous']) ? 1 : 0;

        $categoryMeta = $category_id ? $complaint->getCategoryRoutingMeta($category_id) : null;
        if (!$categoryMeta) {
            throw new Exception("Please select a valid category.");
        }

        $requiresDeptSelection = (int) $categoryMeta['requires_department_selection'] === 1;
        if ($requiresDeptSelection) {
            if (empty($department_id)) {
                throw new Exception("Please select your department for this category.");
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
            $studentId,
            $userId,
            $subcategory_id
        );

        if ($newComplaintId) {
            // Save preferred staff suggestion if provided
            if (!empty($preferred_staff_id)) {
                $leader->setPreferredStaff($newComplaintId, $preferred_staff_id);
            }

            // Notify admins
            $notifMsg = $is_anonymous
                ? "A new anonymous complaint has been submitted."
                : "New complaint from " . htmlspecialchars($_SESSION['username']) . " (Rep): \"$title\"";
            (new Notification($conn))->notifyAllAdmins(
                $notifMsg,
                'new_complaint',
                "complaint_details.php?id=$newComplaintId",
                $newComplaintId
            );

            $_SESSION['message'] = "Complaint submitted successfully.";
            header("Location: leader_my_complaints.php");
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
                                        <i class="fas fa-info-circle"></i> This category requires your department to route
                                        the complaint to the right staff member.
                                    </small>
                                </div>

                                <div class="col-12 col-md-6 mb-2">
                                    <label class="form-label fw-bold">
                                        Suggest Staff Member
                                        <span class="text-muted fw-normal">(optional)</span>
                                    </label>
                                    <select class="form-select p-3 shadow-sm" name="preferred_staff_id"
                                        id="preferred_staff_id" disabled
                                        style="border-radius:10px;border:1px solid #e0e6ed;">
                                        <option value="">--- Select department first ---</option>
                                    </select>
                                    <small class="form-hint">
                                        <i class="fas fa-info-circle"></i> This is a suggestion only - the final assignment
                                        is made by an administrator.
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
                                    accept=".pdf,.jpg,.jpeg,.png" class="form-control p-3 shadow-sm"
                                    style="border-radius:10px;border:1px solid #e0e6ed;">
                                <small class="form-hint">
                                    <i class="fas fa-info-circle"></i> PDF, JPG, JPEG, PNG - max 5 MB each.
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
        const MAX_SIZE = 5 * 1024 * 1024;
        const ALLOWED_EXT = ['.pdf', '.jpg', '.jpeg', '.png'];
        const ALLOWED_MIME = ['application/pdf', 'image/jpeg', 'image/png'];
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
                        errors.push(`"${file.name}" - invalid format.`);
                        return;
                    }
                    if (file.size > MAX_SIZE) {
                        errors.push(`"${file.name}" - exceeds 5 MB.`);
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

            // Staff loader - loads staff based on selected department
            const $dept = $('#department_id');
            const $staff = $('#preferred_staff_id');

            function resetStaff(msg) {
                $staff.prop('disabled', true).html(`<option value="">${msg}</option>`);
            }
            resetStaff('--- Select department first ---');

            $dept.on('change', function () {
                const deptId = $(this).val();
                if (!deptId) { resetStaff('--- Select department first ---'); return; }
                resetStaff('Loading staff...');
                $.getJSON('ajax/get_staff_by_dept.php', { dept_id: deptId })
                    .done(function (data) {
                        if (!data || !data.success || !data.items || !data.items.length) {
                            resetStaff('--- No staff available in this department ---');
                            return;
                        }
                        let opts = '<option value="">--- No preference (let admin decide) ---</option>';
                        data.items.forEach(s => {
                            const role = s.role_name ? ` (${s.role_name})` : '';
                            opts += `<option value="${s.staff_id}">${s.username}${role}</option>`;
                        });
                        $staff.html(opts).prop('disabled', false);
                    })
                    .fail(function () { resetStaff('--- Failed to load staff ---'); });
            });
        });
    </script>
</body>

</html>