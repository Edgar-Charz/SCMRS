<?php
require_once 'config/session.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

require_once "config/Database.php";
require_once "classes/User.php";
require_once "classes/Admin.php";

$db = new Database();
$conn = $db->connect();
$admin = new Admin($conn);

$departments = $admin->getAllDepartments();
$departmentId = (int) ($_GET['department_id'] ?? 0);
$department = $departmentId ? $admin->getDepartmentById($departmentId) : null;

if (!$department && !empty($departments)) {
    $department = $departments[0];
    $departmentId = (int) $department['department_id'];
}

$escalationPath = $departmentId ? $admin->getEscalationPathForDepartment($departmentId) : null;

// Skip common honorifics so avatars aren't all the same letter (e.g. "Mr.", "Ms." both -> "M")
function diagInitial(string $username): string
{
    $honorifics = ['dr', 'mr', 'mrs', 'ms', 'miss', 'prof', 'professor', 'eng', 'rev'];
    foreach (preg_split('/\s+/', trim($username)) as $word) {
        $clean = strtolower(rtrim($word, '.'));
        if ($clean !== '' && !in_array($clean, $honorifics, true)) {
            return strtoupper($word[0]);
        }
    }
    return strtoupper(substr($username, 0, 1) ?: '?');
}

// Renders the staff mini-avatars + names for one box, or an "unassigned" hint.
function diagStaffList(?array $staff): string
{
    if (empty($staff)) {
        return '<div class="esc-empty"><i class="fas fa-user-slash me-1"></i>No staff assigned</div>';
    }
    $html = '<div class="esc-staff-mini-list">';
    foreach ($staff as $s) {
        $html .= '<div class="esc-staff-mini">'
            . '<span class="esc-staff-avatar">' . diagInitial($s['username']) . '</span>'
            . '<span class="esc-staff-mini-name">' . htmlspecialchars($s['username']) . '</span>'
            . '</div>';
    }
    $html .= '</div>';
    return $html;
}

// Renders one Level 1/2/3 box: unconfigured, department-scoped, or university-wide.
// $row['label'] may be a single string or an array of strings (grouped subcategories).
function diagBox(array $row, string $levelLabel, string $levelBadgeClass): string
{
    if (!$row['configured']) {
        return '<div class="esc-box esc-box-empty">'
            . '<span class="esc-level-badge ' . $levelBadgeClass . '">' . $levelLabel . '</span>'
            . '<div class="esc-empty mt-1"><i class="fas fa-ban me-1"></i>Not configured</div>'
            . '</div>';
    }

    $uwClass = $row['is_department_scoped'] ? '' : ' esc-box-university-wide';
    $html = '<div class="esc-box' . $uwClass . '">';
    $html .= '<span class="esc-level-badge ' . $levelBadgeClass . '">' . $levelLabel . '</span>';
    if (!empty($row['label'])) {
        $labels = is_array($row['label']) ? $row['label'] : [$row['label']];
        $html .= '<div class="esc-sub-label">' . implode(' &middot; ', array_map('htmlspecialchars', $labels)) . '</div>';
    }
    $html .= '<div class="esc-role-name">' . htmlspecialchars($row['role_name']) . '</div>';

    if (!$row['is_department_scoped']) {
        $html .= '<div class="esc-uw-tag"><i class="fas fa-university me-1"></i>University-wide</div>';
    } else {
        $html .= diagStaffList($row['staff']);
    }
    $html .= '</div>';
    return $html;
}

// Groups Level 1 rows that share the same role into one box (common when several
// subcategories are handled by the same person), so the diagram doesn't repeat
// an identical box multiple times in a row.
function diagLevel1Boxes(array $level1Rows): string
{
    $grouped = [];
    foreach ($level1Rows as $row) {
        $key = $row['configured'] ? $row['role_id'] : 'unconfigured-' . count($grouped);
        if (!isset($grouped[$key])) {
            $grouped[$key] = $row;
            $grouped[$key]['label'] = $row['label'] ? [$row['label']] : [];
        } elseif ($row['label']) {
            $grouped[$key]['label'][] = $row['label'];
        }
    }

    $html = '';
    foreach ($grouped as $row) {
        $html .= diagBox($row, 'Level 1', 'bg-secondary');
    }
    return $html;
}

// The part of the page that varies by department - rendered both for a full
// page load and for the AJAX partial the department switcher fetches.
function renderEscalationDiagramBody(?array $department, ?array $escalationPath): void
{
    if (!$department) {
        echo '<div class="text-center py-5 text-muted">'
            . '<i class="fas fa-building fa-2x mb-2 d-block"></i>No departments found.</div>';
        return;
    }

    if (empty($escalationPath['categories'])) {
        echo '<div class="text-center py-5 text-muted">'
            . '<i class="fas fa-route fa-2x mb-2 d-block"></i>No active categories configured.</div>';
        return;
    }

    echo '<div class="esc-board">';
    foreach ($escalationPath['categories'] as $cat) {
        echo '<div class="esc-column">';
        echo '<div class="esc-col-header">' . htmlspecialchars($cat['category_name']) . '</div>';
        echo diagLevel1Boxes($cat['level1']);
        echo '<div class="esc-arrow"><i class="fas fa-arrow-down"></i></div>';
        echo diagBox($cat['level2'], 'Level 2', 'bg-warning text-dark');
        echo '<div class="esc-arrow"><i class="fas fa-arrow-down"></i></div>';
        echo diagBox($cat['level3'], 'Level 3', 'bg-danger');
        echo '</div>';
    }
    echo '</div>';

    echo '<hr class="my-4">';
    echo '<div class="esc-legend">'
        . '<span><span class="esc-legend-swatch" style="background:#fff; border:1px solid #e9ecef;"></span>Department-scoped role</span>'
        . '<span><span class="esc-legend-swatch" style="background:linear-gradient(135deg,#fff8e6,#fffdf5); border:1px solid #f0c36d;"></span>University-wide role (same person for every department)</span>'
        . '<span><span class="esc-legend-swatch" style="background:#f8f9fa; border:1px dashed #ced4da;"></span>Not configured yet</span>'
        . '</div>';

    if (!empty($escalationPath['university_wide'])) {
        echo '<div class="mt-4 pt-3" style="border-top:1px solid #e9ecef;">';
        echo '<h6 class="fw-bold mb-2"><i class="fas fa-university me-1"></i>University-wide Roles - Current Holders</h6>';
        echo '<div class="d-flex flex-wrap gap-3">';
        foreach ($escalationPath['university_wide'] as $uw) {
            echo '<div class="esc-box esc-box-university-wide" style="min-width:220px; flex:0 1 auto;">'
                . '<div class="esc-role-name mb-1">' . htmlspecialchars($uw['role_name']) . '</div>'
                . diagStaffList($uw['staff'])
                . '</div>';
        }
        echo '</div></div>';
    }
}

// AJAX partial: the department switcher fetches just this fragment instead of
// reloading the whole page (sidebar, topbar, styles and all).
if (isset($_GET['ajax'])) {
    header('Content-Type: text/html; charset=utf-8');
    renderEscalationDiagramBody($department, $escalationPath);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin | Escalation Diagram</title>
    <link rel="shortcut icon" type="image/x-icon" href="assets/img/favicon.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/3.7.2/animate.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .esc-board {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            justify-content: center;
            align-items: flex-start;
        }

        .esc-column {
            flex: 1 1 240px;
            max-width: 270px;
            display: flex;
            flex-direction: column;
            align-items: stretch;
        }

        .esc-col-header {
            text-align: center;
            font-weight: 700;
            font-size: .95rem;
            margin-bottom: 10px;
            padding-bottom: 8px;
            border-bottom: 2px solid #e9ecef;
        }

        .esc-box {
            background: #fff;
            border: 1px solid #e9ecef;
            border-radius: 12px;
            padding: 12px 14px;
            margin-bottom: 4px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, .06);
        }

        .esc-box-university-wide {
            background: linear-gradient(135deg, #fff8e6, #fffdf5);
            border: 1px solid #f0c36d;
        }

        .esc-box-empty {
            background: #f8f9fa;
            border-style: dashed;
        }

        .esc-level-badge {
            display: inline-block;
            font-size: .68rem;
            font-weight: 700;
            color: #fff;
            padding: 2px 8px;
            border-radius: 20px;
            margin-bottom: 6px;
        }

        .esc-sub-label {
            font-size: .72rem;
            color: #6c757d;
            margin-bottom: 2px;
        }

        .esc-role-name {
            font-weight: 700;
            font-size: .88rem;
            margin-bottom: 6px;
        }

        .esc-uw-tag {
            font-size: .72rem;
            font-weight: 600;
            color: #b8860b;
        }

        .esc-empty {
            font-size: .78rem;
            color: #adb5bd;
            font-style: italic;
        }

        .esc-staff-mini-list {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .esc-staff-mini {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .esc-staff-avatar {
            width: 24px;
            height: 24px;
            min-width: 24px;
            border-radius: 50%;
            background: linear-gradient(135deg, #2d6a9f, #1e3a5f);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: .68rem;
        }

        .esc-staff-mini-name {
            font-size: .78rem;
            color: #343a40;
        }

        .esc-arrow {
            text-align: center;
            color: #adb5bd;
            font-size: 1.1rem;
            margin: 2px 0;
        }

        .esc-legend {
            display: flex;
            gap: 18px;
            flex-wrap: wrap;
            align-items: center;
            font-size: .8rem;
            color: #6c757d;
        }

        .esc-legend-swatch {
            display: inline-block;
            width: 14px;
            height: 14px;
            border-radius: 4px;
            margin-right: 5px;
            vertical-align: middle;
        }

        #escDiagramBody {
            transition: opacity .15s ease;
        }

        #escDiagramBody.esc-loading {
            opacity: .4;
            pointer-events: none;
        }
    </style>
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

                <nav aria-label="breadcrumb" class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item">
                            <a href="admin_dashboard.php"><i class="fas fa-route" style="color: black;"></i></a>
                        </li>
                        <li class="breadcrumb-item">
                            <a href="admin_dashboard.php" style="color:black;">Admin</a>
                        </li>
                        <li class="breadcrumb-item"><a href="manage_departments.php" style="color:black;">Manage
                                Departments</a></li>
                        <li class="breadcrumb-item active">Escalation Diagram</li>
                    </ol>
                    <?php if ($department): ?>
                        <a href="department_hierarchy.php?department_id=<?= $departmentId ?>" id="backToHierarchyLink"
                            class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Hierarchy
                        </a>
                    <?php endif; ?>
                </nav>

                <div class="container-card shadow-sm mt-3">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
                        <div>
                            <h5 class="mb-1 fw-bold">
                                <i class="fas fa-route me-2"></i>Escalation Path Diagram
                            </h5>
                            <span class="flow-hint text-muted small">
                                <i class="fas fa-arrow-down me-1"></i>Each column is one complaint category, flowing
                                from Level 1 (first responder) down to Level 3 (final escalation).
                            </span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <label class="fw-bold small mb-0" for="departmentSelect">Department:</label>
                            <select id="departmentSelect" class="form-select" style="width:auto;">
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?= $dept['department_id'] ?>" <?= (int) $dept['department_id'] === $departmentId ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($dept['department_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div id="escDiagramBody">
                        <?php renderEscalationDiagramBody($department, $escalationPath); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
    <script src="assets/plugins/sweetalert/sweetalert2.all.min.js"></script>
    <script src="assets/plugins/sweetalert/sweetalerts.min.js"></script>
    <script src="assets/js/script.js"></script>
    <script>
        function loadEscalationDiagram(departmentId, pushHistory) {
            const body = document.getElementById('escDiagramBody');
            body.classList.add('esc-loading');

            fetch('escalation_diagram.php?department_id=' + encodeURIComponent(departmentId) + '&ajax=1')
                .then((res) => res.text())
                .then((html) => {
                    body.innerHTML = html;

                    const backLink = document.getElementById('backToHierarchyLink');
                    if (backLink) {
                        backLink.href = 'department_hierarchy.php?department_id=' + departmentId;
                    }

                    if (pushHistory !== false) {
                        const url = new URL(window.location);
                        url.searchParams.set('department_id', departmentId);
                        history.pushState({ departmentId: departmentId }, '', url);
                    }
                })
                .finally(() => {
                    body.classList.remove('esc-loading');
                });
        }

        document.getElementById('departmentSelect').addEventListener('change', function () {
            loadEscalationDiagram(this.value, true);
        });

        window.addEventListener('popstate', function () {
            const departmentId = new URLSearchParams(window.location.search).get('department_id');
            if (departmentId) {
                document.getElementById('departmentSelect').value = departmentId;
                loadEscalationDiagram(departmentId, false);
            }
        });
    </script>
</body>

</html>