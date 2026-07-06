<?php
require_once 'config/session.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

require_once 'config/Database.php';
require_once 'classes/EmailQueue.php';
require_once 'includes/csrf.php';

$db   = new Database();
$conn = $db->connect();

$message = $error = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'retry_failed') {
        $count   = EmailQueue::retryFailed($conn);
        $message = "{$count} failed email(s) reset to pending and will be retried shortly.";
    } elseif ($action === 'process_now') {
        // Force-process synchronously (useful when debugging)
        require_once 'classes/Mailer.php';
        EmailQueue::processPending(20);
        $message = "Queue processed. Check status below.";
    }
}

$stats = EmailQueue::getStats($conn);
$rows  = EmailQueue::getRecent($conn, 100);

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
    <title>Email Queue | SCMRS Admin</title>
    <link rel="shortcut icon" type="image/x-icon" href="assets/img/favicon.png">
    <!-- <link rel="stylesheet" href="assets/css/bootstrap.min.css"> -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css">
    <!-- <link rel="stylesheet" href="assets/css/animate.css"> -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/3.7.2/animate.min.css">
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
                            <a href="admin_dashboard.php"><i class="fas fa-envelope-open-text" style="color:black;"></i></a>
                        </li>
                        <li class="breadcrumb-item"><a href="admin_dashboard.php" style="color:black;">Admin</a></li>
                        <li class="breadcrumb-item active">Email Queue</li>
                    </ol>
                </nav> 

                <?php if (!empty($message)): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle me-2"></i>
                        <?= htmlspecialchars($message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?= htmlspecialchars($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Stats cards -->
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body d-flex align-items-center gap-3">
                                <div class="rounded-circle d-flex align-items-center justify-content-center"
                                     style="width:48px;height:48px;background:#fff3cd;">
                                    <i class="fas fa-clock text-warning"></i>
                                </div>
                                <div>
                                    <div class="fw-bold fs-4"><?= $stats['pending'] ?></div>
                                    <div class="text-muted small">Pending</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body d-flex align-items-center gap-3">
                                <div class="rounded-circle d-flex align-items-center justify-content-center"
                                     style="width:48px;height:48px;background:#d1fae5;">
                                    <i class="fas fa-check-circle text-success"></i>
                                </div>
                                <div>
                                    <div class="fw-bold fs-4"><?= $stats['sent'] ?></div>
                                    <div class="text-muted small">Sent</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body d-flex align-items-center gap-3">
                                <div class="rounded-circle d-flex align-items-center justify-content-center"
                                     style="width:48px;height:48px;background:#fee2e2;">
                                    <i class="fas fa-times-circle text-danger"></i>
                                </div>
                                <div>
                                    <div class="fw-bold fs-4"><?= $stats['failed'] ?></div>
                                    <div class="text-muted small">Failed</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action buttons -->
                <div class="d-flex gap-2 mb-3">
                    <?php if ($stats['failed'] > 0): ?>
                        <form method="POST">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="retry_failed">
                            <button class="btn btn-warning btn-sm">
                                <i class="fas fa-redo me-1"></i> Retry All Failed
                            </button>
                        </form>
                    <?php endif; ?>
                    <form method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="process_now">
                        <button class="btn btn-primary btn-sm">
                            <i class="fas fa-paper-plane me-1"></i> Process Queue Now
                        </button>
                    </form>
                </div>

                <!-- Queue table -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <span class="fw-bold">Recent Emails (last 100)</span>
                        <div class="search-input"></div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 align-middle" id="emailTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>To</th>
                                        <th>Subject</th>
                                        <th class="text-center">Status</th>
                                        <th class="text-center">Attempts</th>
                                        <th>Last Tried</th>
                                        <th>Queued At</th>
                                        <th>Error</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($rows)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center text-muted py-4">
                                                No emails in the queue yet.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($rows as $row): ?>
                                            <tr>
                                                <td class="text-muted small"><?= $row['id'] ?></td>
                                                <td>
                                                    <div class="fw-semibold small"><?= htmlspecialchars($row['to_name']) ?></div>
                                                    <div class="text-muted small"><?= htmlspecialchars($row['to_email']) ?></div>
                                                </td>
                                                <td class="small"><?= htmlspecialchars($row['subject']) ?></td>
                                                <td class="text-center">
                                                    <?php if ($row['status'] === 'sent'): ?>
                                                        <span class="badge bg-success">Sent</span>
                                                    <?php elseif ($row['status'] === 'failed'): ?>
                                                        <span class="badge bg-danger">Failed</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning text-dark">Pending</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center small"><?= $row['attempts'] ?></td>
                                                <td class="small text-muted">
                                                    <?= $row['last_attempted_at']
                                                        ? date('d M H:i', strtotime($row['last_attempted_at']))
                                                        : '-' ?>
                                                </td>
                                                <td class="small text-muted">
                                                    <?= date('d M H:i', strtotime($row['created_at'])) ?>
                                                </td>
                                                <td class="small text-danger" style="max-width:200px;word-break:break-word;">
                                                    <?= htmlspecialchars($row['error_msg'] ?? '') ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div><!-- /.p-4 -->
        </div><!-- /#content -->
    </div>

    <!-- <script src="assets/js/jquery.min.js"></script> -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <!-- <script src="assets/js/bootstrap.bundle.min.js"></script> -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
    <!-- <script src="assets/js/jquery.dataTables.min.js"></script> -->
    <script src="https://cdn.datatables.net/1.10.21/js/jquery.dataTables.min.js"></script>
    <!-- <script src="assets/js/dataTables.bootstrap4.min.js"></script> -->
    <script src="https://cdn.datatables.net/1.10.21/js/dataTables.bootstrap4.min.js"></script>
    <script src="assets/js/script.js"></script>
    <script>
        $(document).ready(function () {
            $('#emailTable').DataTable({
                order: [[0, 'desc']],
                pageLength: 25,
                bFilter: true,
                sDom: "fBtlpi",
                pagingType: "numbers",
                columnDefs: [{ orderable: false, targets: [7] }],
                language: {
                    search: " ",
                    sLengthMenu: "_MENU_",
                    searchPlaceholder: "Search emails...",
                    info: "_START_ - _END_ of _TOTAL_ items"
                },
                initComplete: function (settings) {
                    $(settings.nTableWrapper).find('.dataTables_filter')
                        .appendTo($(settings.nTable).closest('.card').find('.search-input'));
                }
            });
        });
    </script>
</body>
</html>
