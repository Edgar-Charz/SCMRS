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
        if ($_POST['action'] === 'endorse') {
            if ($leader->endorse($cid, $note)) {
                $_SESSION['message'] = "Complaint endorsed successfully.";
            } else {
                $_SESSION['message_error'] = "Could not endorse — you may have already endorsed this complaint.";
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
                            <a href="leader_dashboard.php"><i class="fas fa-chart-pie" style="color:black;"></i></a>
                        </li>
                        <li class="breadcrumb-item active">Student Rep / Department Complaints</li>
                    </ol>
                </nav>

                <div class="container-card shadow-sm">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h4 class="mb-1 fw-bold" style="color:var(--udsm-blue);">
                                <i class="fas fa-file-invoice me-2"></i>Department Complaints
                            </h4>
                            <p class="text-muted small mb-0">
                                Complaints from:
                                <?= empty($depts)
                                    ? '<em>No departments assigned</em>'
                                    : htmlspecialchars(implode(', ', array_column($depts, 'department_name'))) ?>
                            </p>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table id="complaintsTable" class="table table-stripped">
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
                                    ?>
                                    <tr>
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
                                                <?php if ($c['i_endorsed']): ?>
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

    <script src="assets/js/jquery-3.6.0.min.js"></script>
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/jquery.dataTables.min.js"></script>
    <script src="assets/js/dataTables.bootstrap4.min.js"></script>
    <script src="assets/js/script.js"></script>
    <script>
        function openEndorseModal(complaintId, complaintTitle) {
            document.getElementById('endorse_complaint_id').value = complaintId;
            document.getElementById('endorse_complaint_title').textContent = complaintTitle;
            document.getElementById('endorse_note').value = '';
        }

        $(function () {
            $('#complaintsTable').DataTable({
                order: [[6, 'desc']],
                pageLength: 25,
                responsive: true,
            });
        });
    </script>
</body>
</html>
