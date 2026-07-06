<?php
require_once 'config/session.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student_leader') {
    header("Location: login.php");
    exit;
}

$userId = (int)$_SESSION['user_id'];

require_once 'config/Database.php';
require_once 'classes/StudentLeader.php';

$db     = new Database();
$conn   = $db->connect();
$leader = new StudentLeader($conn, $userId);

$message = $error = '';

// Handle endorse / remove-endorsement from this list
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $cid  = (int)($_POST['complaint_id'] ?? 0);
    $note = trim($_POST['note'] ?? '');
    if ($cid > 0) {
        if ($leader->isComplaintFinalized($cid)) {
            $_SESSION['message_error'] = "This complaint has already been resolved or rejected and can no longer be endorsed.";
        } elseif ($_POST['action'] === 'endorse') {
            if ($leader->endorse($cid, $note)) {
                $_SESSION['message'] = "Complaint endorsed successfully.";
            } else {
                $_SESSION['message_error'] = "Could not endorse - you may have already endorsed this complaint.";
            }
        } elseif ($_POST['action'] === 'remove_endorsement') {
            $leader->removeEndorsement($cid);
            $_SESSION['message'] = "Endorsement removed.";
        }
    }
    header("Location: leader_complaints.php");
    exit;
}

if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}
if (isset($_SESSION['message_error'])) {
    $error = $_SESSION['message_error'];
    unset($_SESSION['message_error']);
}

$complaints = $leader->getComplaints();
$depts      = $leader->getDepartments();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Department Complaints | Student Rep</title>
    <link rel="shortcut icon" type="image/x-icon" href="assets/img/favicon.png">
    <!-- <link rel="stylesheet" href="assets/css/bootstrap.min.css"> -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css">
    <!-- <link rel="stylesheet" href="assets/css/animate.css"> -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/3.7.2/animate.min.css">
    <!-- <link rel="stylesheet" href="assets/plugins/select2/css/select2.min.css"> -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css">
    <!-- <link rel="stylesheet" href="assets/css/dataTables.bootstrap4.min.css"> -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.21/css/dataTables.bootstrap4.min.css">
    <!-- <link rel="stylesheet" href="assets/plugins/fontawesome/css/fontawesome.min.css"> -->
    <!-- <link rel="stylesheet" href="assets/plugins/fontawesome/css/all.min.css"> -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
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
                            <a href="leader_dashboard.php"><i class="fas fa-chart-pie" style="color:black;"></i></a>
                        </li>
                        <li class="breadcrumb-item"><a href="leader_dashboard.php" style="color:black;">Student Rep</a></li>
                        <li class="breadcrumb-item active">Department Complaints</li>
                    </ol>
                </nav>

                <!-- Filter Card -->
                <div class="container-card shadow-sm">
                    <div class="row g-2 align-items-end">
                        <div class="col-12 col-sm-6 col-md-4 col-lg-2">
                            <label class="form-label small fw-semibold mb-1">Status</label>
                            <select id="filterStatus" class="form-select form-select-sm">
                                <option value="">All Statuses</option>
                                <option value="pending">Pending</option>
                                <option value="in_progress">In Progress</option>
                                <option value="awaiting_response">Awaiting Response</option>
                                <option value="resolved">Resolved</option>
                                <option value="rejected">Rejected</option>
                                <option value="reopened">Reopened</option>
                            </select>
                        </div>
                        <div class="col-12 col-sm-6 col-md-4 col-lg-2">
                            <label class="form-label small fw-semibold mb-1">Priority</label>
                            <select id="filterPriority" class="form-select form-select-sm">
                                <option value="">All Priorities</option>
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                            </select>
                        </div>
                        <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                            <label class="form-label small fw-semibold mb-1">Category</label>
                            <select id="filterCategory" class="form-select form-select-sm">
                                <option value="">All Categories</option>
                                <?php
                                $lcats = array_values(array_filter(array_unique(array_column($complaints, 'category_name'))));
                                sort($lcats);
                                foreach ($lcats as $cat): ?>
                                    <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-sm-6 col-md-4 col-lg-2">
                            <label class="form-label small fw-semibold mb-1">Date From</label>
                            <input type="date" id="filterDateFrom" class="form-control form-control-sm">
                        </div>
                        <div class="col-12 col-sm-6 col-md-4 col-lg-2">
                            <label class="form-label small fw-semibold mb-1">Date To</label>
                            <input type="date" id="filterDateTo" class="form-control form-control-sm">
                        </div>
                        <div class="col-auto d-flex align-items-end">
                            <button type="button" id="clearFilters" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-times me-1"></i>Clear
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Table Card -->
                <div class="container-card shadow-sm">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <div>
                            <h4 class="mb-1 fw-bold" style="color:var(--udsm-blue);">
                                <i class="fas fa-file-invoice me-2"></i>Department Complaints
                            </h4>
                            <p class="text-muted small mb-0">
                                Complaints from:
                                <?= empty($depts)
                                    ? '<span class="fw-bold" style="color:#b8860b;"><i class="fas fa-crown me-1"></i>All Departments (Senior Leader)</span>'
                                    : htmlspecialchars(implode(', ', array_column($depts, 'department_name'))) ?>
                            </p>
                        </div>
                        <div class="search-input"></div>
                    </div>

                    <div class="table-responsive">
                        <table id="complaintsTable" class="table table-striped">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>TITLE</th>
                                    <th class="text-center">STUDENT</th>
                                    <th class="text-center">CATEGORY</th>
                                    <th class="text-center">PRIORITY</th>
                                    <th class="text-center">STATUS</th>
                                    <th class="text-center">DATE</th>
                                    <th class="text-center">ACTION</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($complaints)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-4">No complaints found in your department(s).</td>
                                    </tr>
                                <?php else: ?>
                                    <?php $n = 1; foreach ($complaints as $c): ?>
                                    <?php
                                    $statusMap = [
                                        STATUS_PENDING           => ['bg-warning text-dark',  'Pending'],
                                        STATUS_IN_PROGRESS       => ['bg-info text-white',    'In Progress'],
                                        STATUS_AWAITING_RESPONSE => ['bg-primary text-white', 'Awaiting Response'],
                                        STATUS_RESOLVED          => ['bg-success text-white', 'Resolved'],
                                        STATUS_REJECTED          => ['bg-danger text-white',  'Rejected'],
                                        STATUS_REOPENED          => ['bg-orange text-white',  'Reopened'],
                                    ];
                                    [$sc, $sl] = $statusMap[$c['complaint_status']] ?? ['bg-secondary text-white', ucfirst($c['complaint_status'])];
                                    $priorityMap = ['low' => 'bg-success', 'medium' => 'bg-warning text-dark', 'high' => 'bg-danger'];
                                    $priClass = $priorityMap[$c['priority']] ?? 'bg-secondary';
                                    $isFinalized = in_array($c['complaint_status'], [STATUS_RESOLVED, STATUS_REJECTED], true);
                                    ?>
                                    <tr data-status="<?= htmlspecialchars($c['complaint_status']) ?>"
                                        data-priority="<?= htmlspecialchars($c['priority']) ?>"
                                        data-category="<?= htmlspecialchars($c['category_name']) ?>"
                                        data-date="<?= date('Y-m-d', strtotime($c['created_at'])) ?>"
                                        data-href="leader_complaint_details.php?id=<?= (int) $c['complaint_id'] ?>">
                                        <td><?= $n++ ?></td>
                                        <td><?= htmlspecialchars($c['complaint_title']) ?></td>
                                        <td class="text-center">
                                            <?= $c['is_anonymous'] ? '<em class="text-muted">Anonymous</em>' : htmlspecialchars($c['student_name']) ?>
                                        </td>
                                        <td class="text-center"><?= htmlspecialchars($c['category_name']) ?></td>
                                        <td class="text-center">
                                            <span class="badge <?= $priClass ?>"><?= ucfirst($c['priority']) ?></span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge <?= $sc ?>"><?= $sl ?></span>
                                            <?php if ($c['endorsement_count'] > 0): ?>
                                                <br><span class="badge mt-1" style="background-color:#6f42c1;">
                                                    <i class="fas fa-thumbs-up me-1"></i><?= (int)$c['endorsement_count'] ?> Rep
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center"><?= date('d M Y', strtotime($c['created_at'])) ?></td>
                                        <td class="text-center">
                                            <div class="d-flex justify-content-center gap-1">
                                                <a href="leader_complaint_details.php?id=<?= $c['complaint_id'] ?>"
                                                   class="btn btn-status btn-outline-secondary" title="View Details">
                                                    <i class="fas fa-eye text-dark"></i>
                                                </a>
                                                <?php if ($c['i_endorsed'] && $isFinalized): ?>
                                                    <button type="button" class="btn btn-status btn-outline-secondary" disabled
                                                            title="Endorsed - complaint is <?= htmlspecialchars($c['complaint_status']) ?>, can no longer be changed"
                                                            style="background-color:#6f42c1;border-color:#6f42c1;opacity:.65;">
                                                        <i class="fas fa-lock text-white"></i>
                                                    </button>
                                                <?php elseif ($c['i_endorsed']): ?>
                                                    <form method="POST" style="display:inline;">
                                                        <input type="hidden" name="action" value="remove_endorsement">
                                                        <input type="hidden" name="complaint_id" value="<?= $c['complaint_id'] ?>">
                                                        <button type="submit"
                                                                class="btn btn-status btn-outline-secondary"
                                                                title="Remove endorsement"
                                                                style="background-color:#6f42c1;border-color:#6f42c1;"
                                                                onclick="return confirm('Remove your endorsement for this complaint?')">
                                                            <i class="fas fa-thumbs-up text-white"></i>
                                                        </button>
                                                    </form>
                                                <?php elseif ($isFinalized): ?>
                                                    <button type="button" class="btn btn-status btn-outline-secondary" disabled
                                                            title="Complaint is already <?= htmlspecialchars($c['complaint_status']) ?>, can no longer be endorsed">
                                                        <i class="fas fa-lock text-muted"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button"
                                                            class="btn btn-status btn-outline-secondary"
                                                            title="Endorse this complaint"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#endorseModal"
                                                            onclick="openEndorseModal(<?= $c['complaint_id'] ?>, '<?= htmlspecialchars($c['complaint_title'], ENT_QUOTES) ?>')">
                                                        <i class="fas fa-thumbs-up text-muted"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div><!-- /p-4 -->
        </div><!-- /content -->
    </div>

    <!-- Endorse Modal -->
    <div class="modal fade" id="endorseModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content shadow-lg rounded-3">
                <div class="modal-header text-white"
                     style="background:linear-gradient(135deg,#6f42c1,#8a5cf7);">
                    <h5 class="modal-title fw-bold text-white">
                        <i class="fas fa-thumbs-up me-2"></i>Endorse Complaint
                    </h5>
                    <button type="button" class="btn-close btn-close-white"
                            data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="endorseForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="endorse">
                        <input type="hidden" name="complaint_id" id="endorse_complaint_id">
                        <p class="mb-3 text-muted small">
                            Endorsing: <strong id="endorse_complaint_title"></strong>
                        </p>
                        <div class="mb-3">
                            <label class="form-label fw-bold">
                                Note <span class="text-muted fw-normal">(optional)</span>
                            </label>
                            <textarea name="note" id="endorse_note" class="form-control" rows="3"
                                      placeholder="Why are you endorsing this complaint?"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn text-white" style="background-color:#6f42c1;">
                            <i class="fas fa-thumbs-up me-1"></i>Confirm Endorsement
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- /Endorse Modal -->

    <!-- <script src="assets/js/jquery-3.6.0.min.js"></script> -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <!-- <script src="assets/js/bootstrap.bundle.min.js"></script> -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
    <!-- <script src="assets/js/jquery.dataTables.min.js"></script> -->
    <script src="https://cdn.datatables.net/1.10.21/js/jquery.dataTables.min.js"></script>
    <!-- <script src="assets/js/dataTables.bootstrap4.min.js"></script> -->
    <script src="https://cdn.datatables.net/1.10.21/js/dataTables.bootstrap4.min.js"></script>
    <script src="assets/js/script.js"></script>
    <script>
        function openEndorseModal(complaintId, complaintTitle) {
            document.getElementById('endorse_complaint_id').value = complaintId;
            document.getElementById('endorse_complaint_title').textContent = complaintTitle;
            document.getElementById('endorse_note').value = '';
        }

        $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
            if (settings.nTable.id !== 'complaintsTable') return true;
            var rowNode  = settings.aoData[dataIndex] ? settings.aoData[dataIndex].nTr : null;
            if (!rowNode) return true;
            var row     = $(rowNode);
            var rStatus = row.data('status')   || '';
            var rPri    = row.data('priority') || '';
            var rCat    = row.data('category') || '';
            var rDate   = row.data('date')     || '';
            var fStatus   = $('#filterStatus').val()   || '';
            var fPriority = $('#filterPriority').val() || '';
            var fCategory = $('#filterCategory').val() || '';
            var fFrom     = $('#filterDateFrom').val() || '';
            var fTo       = $('#filterDateTo').val()   || '';
            if (fStatus   && rStatus !== fStatus)   return false;
            if (fPriority && rPri    !== fPriority)  return false;
            if (fCategory && rCat    !== fCategory)  return false;
            if (fFrom     && rDate   < fFrom)        return false;
            if (fTo       && rDate   > fTo)          return false;
            return true;
        });

        var leaderTable;
        $(function () {
            leaderTable = $('#complaintsTable').DataTable({
                order: [[6, 'desc']],
                pageLength: 25,
                bFilter: true,
                sDom: "fBtlpi",
                pagingType: "numbers",
                columnDefs: [{ orderable: false, targets: [7] }],
                language: {
                    search: " ",
                    sLengthMenu: "_MENU_",
                    searchPlaceholder: "Search complaints...",
                    info: "_START_ - _END_ of _TOTAL_ items"
                },
                initComplete: function() {
                    $(".dataTables_filter").appendTo(".search-input");
                }
            });
            $('#complaintsTable tbody').on('click', 'tr[data-href]', function(e) {
                if ($(e.target).closest('a, button, input, label').length) return;
                window.location.href = $(this).data('href');
            });
            $('#filterStatus, #filterPriority, #filterCategory').on('change', function() { leaderTable.draw(); });
            $('#filterDateFrom, #filterDateTo').on('change', function() { leaderTable.draw(); });
            $('#clearFilters').on('click', function() {
                $('#filterStatus, #filterPriority, #filterCategory').val('');
                $('#filterDateFrom, #filterDateTo').val('');
                leaderTable.search('').draw();
            });
        });
    </script>
</body>
</html>
