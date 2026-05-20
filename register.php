<?php
require_once "config/Database.php";
require_once "classes/User.php";
require_once "classes/College.php";
require_once "classes/Department.php";
require_once "classes/Notification.php";

$db = new Database();
$conn = $db->connect();

$user = new User($conn);
$college = new College($conn);
$department = new Department($conn);

$message = $error = "";

// Student Registration
if (isset($_POST["registerStudentBTN"])) {
    try {
        $username = trim($_POST['student_name']);
        $reg_no = trim($_POST['reg_no']);
        $email = trim($_POST['student_email']);
        $phone_number = $_POST['phone_number'];
        $password = $_POST['password'];
        $confirmPassword = $_POST['confirm_password'];

        if ($user->studentRegister($username, $reg_no, $email, $phone_number, $password, $confirmPassword)) {
            (new Notification($conn))->notifyAllAdmins(
                "New student registered: $username",
                'new_registration',
                'user_management.php#students'
            );
            header("Location: login.php");
            exit;
        }
    } catch (Exception $e) {
        $error = "Registration failed. " . $e->getMessage();
    }
}

// Staff Registration
if (isset($_POST["registerStaffBTN"])) {
    try {
        $username = trim($_POST['staff_name']);
        $email = trim($_POST['staff_email']);
        $password = $_POST['password'];
        $confirmPassword = $_POST['confirm_password'];
        $departmentId = (int) ($_POST['staff_department'] ?? 0) ?: null;
        $staffId = trim($_POST['staff_id'] ?? '');
        $phoneNumber = trim($_POST['phone_number'] ?? '');

        if ($user->staffRegister($username, $email, $password, $confirmPassword, $departmentId, $staffId ?: null, $phoneNumber ?: null)) {
            (new Notification($conn))->notifyAllAdmins(
                "New staff member registered: $username (pending your approval)",
                'new_registration',
                'user_management.php#approval'
            );
            header("Location: login.php");
            exit;
        }
    } catch (Exception $e) {
        $error = "Registration failed. " . $e->getMessage();
    }
}

$colleges = $college->getColleges();
$departments = $department->getDepartments();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | SCMRS</title>
    <link rel="shortcut icon" type="image/x-icon" href="assets/img/favicon.png">
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/animate.css">
    <link rel="stylesheet" href="assets/plugins/select2/css/select2.min.css">
    <link rel="stylesheet" href="assets/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="assets/plugins/fontawesome/css/fontawesome.min.css">
    <link rel="stylesheet" href="assets/plugins/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="assets/plugins/toastr/toatr.css">
    <link rel="stylesheet" href="assets/css/auth-style.css">
    <style>
        body {
            display: block;
            min-height: 100vh;
            height: auto;
            padding: 0;
            background: none;
        }

        /* ── Split layout ── */
        .split-layout {
            display: flex;
            min-height: 100vh;
        }

        .split-left {
            width: 45%; 
            min-width: 280px;
            /* background-image: url('assets/img/campus1.jpg');
                background-size: cover;
                background-position: center; */
            background-color: #001a52;
            color: #fff;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 48px 40px;
            position: sticky;
            top: 0;
            height: 100vh;
            overflow: hidden;
            flex-shrink: 0;
        }

        /* ── Slideshow photos (z-index 0) ── */
        .slide-bg {
            position: absolute;
            inset: 0;
            background-size: cover;
            background-position: center;
            opacity: 0;
            z-index: 0;
            animation: campusSlide 25s infinite;
        }

        .slide-bg:nth-child(1) {
            background-image: url('assets/img/campus1.jpg');
            animation-delay: 0s;
        }

        .slide-bg:nth-child(2) {
            background-image: url('assets/img/campus2.jpg');
            animation-delay: 5s;
        }

        .slide-bg:nth-child(3) {
            background-image: url('assets/img/campus3.jpg');
            animation-delay: 10s;
        }

        .slide-bg:nth-child(4) {
            background-image: url('assets/img/campus4.jpg');
            animation-delay: 15s;
        }

        .slide-bg:nth-child(5) {
            background-image: url('assets/img/campus5.jpg');
            animation-delay: 20s;
        }

        @keyframes campusSlide {
            0% {
                opacity: 0;
            }

            7% {
                opacity: 1;
            }

            /* fade in  ~1.75s */
            27% {
                opacity: 1;
            }

            /* stay visible     */
            33% {
                opacity: 0;
            }

            /* fade out ~1.5s   */
            100% {
                opacity: 0;
            }

            /* hidden till next */
        }

        /* Dark blue overlay — sits above photos, below text (z-index 1) */
        .split-left::before {
            content: '';
            position: absolute;
            inset: 0;
            z-index: 1;
            background: linear-gradient(160deg,
                    rgba(0, 10, 40, 0.82) 0%,
                    rgba(0, 35, 100, 0.72) 50%,
                    rgba(0, 60, 100, 0.68) 100%);
            pointer-events: none;
        }

        /* Bottom fade for text readability (z-index 1) */
        .split-left::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 40%;
            z-index: 1;
            background: linear-gradient(to top, rgba(0, 10, 40, 0.55), transparent);
            pointer-events: none;
        }

        /* Text content sits above overlays (z-index 2) — exclude slide-bg divs */
        .split-left>*:not(.slide-bg) {
            position: relative;
            z-index: 2;
        }

        .split-logo {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            border: 3px solid rgba(253, 216, 53, 0.85);
            object-fit: cover;
            margin-bottom: 20px;
        }

        .split-label {
            font-size: 0.65rem;
            font-weight: 700;
            letter-spacing: .16em;
            text-transform: uppercase;
            color: rgba(253, 216, 53, 0.9);
            margin-bottom: 10px;
        }

        .split-title {
            font-size: 1.4rem;
            font-weight: 700;
            line-height: 1.35;
            margin-bottom: 10px;
        }

        .split-subtitle {
            font-size: 0.82rem;
            color: rgba(255, 255, 255, 0.65);
            line-height: 1.65;
            margin-bottom: 28px;
        }

        .split-features {
            list-style: none;
            padding: 0;
            margin: 0 0 32px;
            display: flex;
            flex-direction: column;
            gap: 11px;
        }

        .split-features li {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.82rem;
            color: rgba(255, 255, 255, 0.82);
        }

        .split-features li i {
            color: rgba(253, 216, 53, 0.9);
            font-size: 0.78rem;
            flex-shrink: 0;
        }

        .split-footer-line {
            height: 1px;
            background: rgba(255, 255, 255, 0.12);
            margin-bottom: 14px;
        }

        .split-uni {
            font-size: 0.68rem;
            font-weight: 600;
            letter-spacing: .06em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.4);
            margin: 0;
        }

        .split-right {
            flex: 1;
            background: linear-gradient(135deg, #f0f4ff 0%, #f0fdf4 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 24px;
            min-height: 100vh;
        }

        @media (max-width: 768px) {
            .split-left {
                display: none;
            }

            .split-right {
                padding: 5vh 16px 40px;
                align-items: flex-start;
            }
        }

        /* Card base override */
        .auth-card {
            width: 100%;
            max-width: 460px;
            padding: 32px 28px;
            border-radius: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.04), 0 16px 40px rgba(0, 0, 0, 0.09);
            background: #fff;
            position: relative;
            overflow: hidden;
        }

        .auth-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #0062cc, #60a5fa);
            border-radius: 20px 20px 0 0;
        }

        #studentFormContainer,
        #staffFormContainer {
            max-width: 600px;
        }

        /* ── Account-type selection cards ── */
        .sel-card {
            padding: 22px 12px 18px;
            border-radius: 16px;
            border: 2px solid #e9ecef;
            cursor: pointer;
            text-align: center;
            transition: border-color .25s, background .25s, transform .25s, box-shadow .25s;
        }

        .sel-card:hover {
            border-color: var(--udsm-blue);
            background: #f5f8ff;
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0, 98, 204, .14);
        }

        .sel-icon {
            width: 54px;
            height: 54px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            font-size: 1.3rem;
        }

        .sel-icon.student {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .sel-icon.staff {
            background: #dcfce7;
            color: #15803d;
        }

        /* ── Form header block ── */
        .reg-header {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 13px 16px;
            background: linear-gradient(135deg, #f8faff, #f0f5ff);
            border: 1px solid #e2eaff;
            border-radius: 14px;
            margin-bottom: 18px;
        }

        .reg-logo {
            width: 46px;
            height: 46px;
            border-radius: 50%;
            border: 2px solid var(--udsm-yellow);
            object-fit: cover;
            flex-shrink: 0;
        }

        .reg-badge {
            display: inline-block;
            padding: 2px 9px;
            border-radius: 20px;
            font-size: 0.68rem;
            font-weight: 700;
            letter-spacing: .06em;
            text-transform: uppercase;
            margin-bottom: 2px;
        }

        .badge-student {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .badge-staff {
            background: #dcfce7;
            color: #15803d;
        }

        /* ── Section dividers ── */
        .form-divider {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 4px 0 10px;
            font-size: 0.68rem;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: .07em;
        }

        .form-divider::before,
        .form-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #f1f5f9;
        }

        /* ── Input with left icon ── */
        .field-wrap {
            position: relative;
        }

        .field-icon {
            position: absolute;
            left: 11px;
            top: calc((100% - 1rem) / 2);
            transform: translateY(-50%);
            color: #adb5bd;
            font-size: 0.78rem;
            pointer-events: none;
            z-index: 5;
        }

        /* Fix eye toggle to stay centered on the input, not the whole wrapper */
        .field-wrap .pwd-eye {
            top: calc((100% - 1rem) / 2);
        }

        .form-control.icon-input,
        .form-select.icon-input {
            padding-left: 30px;
        }

        /* Password field: left icon + right eye toggle */
        .pwd-wrap.field-wrap .form-control {
            padding-left: 30px;
            padding-right: 2.6rem;
        }

        /* ── Form controls ── */
        .form-control,
        .form-select {
            border-radius: 10px;
            font-size: 0.875rem;
            padding: 9px 12px;
            border: 1.5px solid #e2e8f0;
            background: #fcfcfd;
            transition: border-color .2s, box-shadow .2s, background .2s;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--udsm-blue);
            box-shadow: 0 0 0 3px rgba(0, 98, 204, .1);
            background: #fff;
        }

        .form-label {
            font-size: 0.78rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 4px;
        }

        /* ── Submit button ── */
        .btn-register {
            background: linear-gradient(90deg, #0062cc 0%, #3b9eff 100%);
            border: none;
            color: #fff;
            border-radius: 12px;
            padding: 11px;
            font-weight: 600;
            font-size: 0.92rem;
            letter-spacing: .02em;
            box-shadow: 0 4px 14px rgba(0, 98, 204, .28);
            transition: all .2s;
        }

        .btn-register:hover {
            color: #fff;
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(0, 98, 204, .38);
        }

        .btn-register:active {
            transform: translateY(0);
            box-shadow: 0 2px 8px rgba(0, 98, 204, .2);
        }

        /* ── Back button ── */
        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 0.78rem;
            font-weight: 600;
            color: #64748b;
            background: none;
            border: none;
            padding: 0;
            cursor: pointer;
            text-decoration: none;
            margin-bottom: 12px;
            transition: color .2s;
        }

        .btn-back:hover {
            color: var(--udsm-blue);
        }

        /* ── Password rules ── */
        .pwd-rules {
            margin: 0;
            padding: 4px 0 0;
            list-style: none;
            display: flex;
            flex-wrap: wrap;
            gap: 2px 10px;
        }

        .pwd-rules li {
            color: #94a3b8;
            font-size: 0.69rem;
            white-space: nowrap;
            transition: color .2s;
        }

        .pwd-rules li.rule-ok {
            color: #16a34a;
        }

        /* Always reserve space so the field icon never shifts when error appears */
        .field-wrap .invalid-feedback {
            display: block;
            min-height: 1rem;
            font-size: 0.72rem;
            visibility: hidden;
        }

        .field-wrap .is-invalid~.invalid-feedback {
            visibility: visible;
        }
    </style>
</head>

<body>

    <div id="loader">
        <div class="loader-content">
            <img src="assets/img/logo.png" alt="UDSM" class="loader-logo">
            <div class="spinner"></div>
            <p class="loader-text">Please wait...</p>
        </div>
    </div>

    <div class="split-layout">
        <!-- Left branding panel -->
        <div class="split-left">
            <div class="slide-bg"></div>
            <div class="slide-bg"></div>
            <div class="slide-bg"></div>
            <div class="slide-bg"></div>
            <div class="slide-bg"></div>

            <img src="assets/img/logo.png" alt="UDSM" class="split-logo">
            <div class="split-label">SCMRS &mdash; University Platform</div>
            <h2 class="split-title">Student Complaint Management &amp; Reporting System</h2>
            <p class="split-subtitle">A centralized portal where students and staff can submit, track, and resolve
                academic complaints with full transparency.</p>
            <ul class="split-features">
                <li><i class="fas fa-check-circle"></i> Submit complaints quickly and easily</li>
                <li><i class="fas fa-check-circle"></i> Track resolution progress in real time</li>
                <li><i class="fas fa-check-circle"></i> Communicate directly with responsible staff</li>
                <li><i class="fas fa-check-circle"></i> Receive timely, documented responses</li>
            </ul>
            <div class="split-footer-line"></div>
            <p class="split-uni">University of Dar es Salaam</p>
        </div>

        <!-- Right form panel -->
        <div class="split-right">

            <!-- Account type selection -->
            <div id="authSelection" class="auth-card text-center">
                <!-- <img src="assets/img/logo.png" alt="UDSM Logo" class="brand-logo"> -->
                <h4 class="fw-bold mb-1">Create an Account</h4>
                <p class="text-muted small mb-4">Select your account type to get started</p>

                <div class="row g-3 mb-4">
                    <div class="col-6">
                        <div class="sel-card" onclick="showForm('student')">
                            <div class="sel-icon student">
                                <i class="fas fa-user-graduate"></i>
                            </div>
                            <div class="fw-semibold" style="font-size:.9rem;">Student</div>
                            <div class="text-muted" style="font-size:.72rem;">Use your Reg. No.</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="sel-card" onclick="showForm('staff')">
                            <div class="sel-icon staff">
                                <i class="fas fa-chalkboard-teacher"></i>
                            </div>
                            <div class="fw-semibold" style="font-size:.9rem;">Staff</div>
                            <div class="text-muted" style="font-size:.72rem;">Use your Staff ID</div>
                        </div>
                    </div>
                </div>

                <p class="small mb-0">Already have an account?
                    <a href="login.php" class="fw-bold text-decoration-none" style="color:var(--udsm-blue);">Login
                        here</a>
                </p>
            </div>

            <!-- Student Registration Form -->
            <div id="studentFormContainer" class="auth-card d-one">
                <button class="btn-back" onclick="goBack()">
                    <i class="fas fa-arrow-left"></i> Back
                </button>

                <div class="reg-header">
                    <img src="assets/img/logo.png" alt="UDSM" class="reg-logo">
                    <div>
                        <span class="reg-badge badge-student">Student</span>
                        <h5 class="fw-bold mb-0" style="font-size:1rem;line-height:1.3;">Create Your Account</h5>
                        <p class="text-muted mb-0" style="font-size:.75rem;">UDSM Complaint Management System</p>
                    </div>
                </div>

                <?php if (!empty($error) && isset($_POST['registerStudentBTN'])): ?>
                    <div class="alert alert-danger d-flex align-items-center gap-2 mb-3 py-2">
                        <i class="fas fa-exclamation-circle flex-shrink-0"></i>
                        <span class="flex-grow-1 small"><?= htmlspecialchars($error) ?></span>
                        <button type="button" onclick="this.closest('.alert').remove()"
                            class="btn-close btn-sm ms-auto"></button>
                    </div>
                <?php endif; ?>

                <form action="" method="POST" onsubmit="showLoader()">
                    <!-- <div class="form-divider">Personal Information</div> -->
                    <div class="row g-2 mb-2">
                        <div class="col-12 col-sm-6">
                            <label class="form-label">Full Name</label>
                            <div class="field-wrap">
                                <i class="fas fa-user field-icon"></i>
                                <input type="text" id="s_name" class="form-control icon-input" name="student_name"
                                    placeholder="Enter your full name" oninput="capitalizeWords(this)" required>
                                <div class="invalid-feedback"></div>
                            </div>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label">Registration Number</label>
                            <div class="field-wrap">
                                <i class="fas fa-id-card field-icon"></i>
                                <input type="text" id="s_reg_no" class="form-control icon-input" name="reg_no"
                                    placeholder="202X-04-XXXXX" pattern="^202[0-9]-04-[0-9]{5}$"
                                    title="Use format 202X-04-XXXXX" maxlength="13" minlength="13"
                                    oninput="this.value=this.value.toUpperCase().replace(/[^0-9-]/g,'').slice(0,13)"
                                    required>
                                <div class="invalid-feedback"></div>
                            </div>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label">Email Address</label>
                            <div class="field-wrap">
                                <i class="fas fa-envelope field-icon"></i>
                                <input type="email" id="s_email" class="form-control icon-input" name="student_email"
                                    placeholder="email@udsm.ac.tz" required>
                                <div class="invalid-feedback"></div>
                            </div>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label">Phone Number</label>
                            <div class="field-wrap">
                                <i class="fas fa-phone field-icon"></i>
                                <input type="text" id="s_phone" class="form-control icon-input" name="phone_number"
                                    placeholder="0XXXXXXXXX" inputmode="numeric" pattern="^0[0-9]{9}$"
                                    title="Enter 10 digits starting with 0" maxlength="10" minlength="10"
                                    oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,10)" required>
                                <div class="invalid-feedback"></div>
                            </div>
                        </div>
                    </div>

                    <!-- <div class="form-divider mt-2">Security</div> -->
                    <div class="row g-2 mb-2">
                        <div class="col-12 col-sm-6">
                            <label class="form-label">Password</label>
                            <div class="pwd-wrap field-wrap">
                                <i class="fas fa-lock field-icon"></i>
                                <input type="password" id="s_pwd" class="form-control" name="password"
                                    placeholder="Min. 8 characters" required>
                                <button type="button" class="pwd-eye" onclick="togglePwd('s_pwd',this)" tabindex="-1">
                                    <i class="fas fa-eye-slash"></i>
                                </button>
                                <div class="invalid-feedback"></div>
                            </div>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label">Confirm Password</label>
                            <div class="pwd-wrap field-wrap">
                                <i class="fas fa-lock field-icon"></i>
                                <input type="password" id="s_cpwd" class="form-control" name="confirm_password"
                                    placeholder="Repeat your password" required>
                                <button type="button" class="pwd-eye" onclick="togglePwd('s_cpwd',this)" tabindex="-1">
                                    <i class="fas fa-eye-slash"></i>
                                </button>
                                <div class="invalid-feedback"></div>
                            </div>
                        </div>
                        <div class="col-12">
                            <ul class="pwd-rules">
                                <li data-rule="length"><i class="fas fa-circle me-1"
                                        style="font-size:6px;vertical-align:middle;"></i>8+ chars</li>
                                <li data-rule="upper"><i class="fas fa-circle me-1"
                                        style="font-size:6px;vertical-align:middle;"></i>Uppercase</li>
                                <li data-rule="lower"><i class="fas fa-circle me-1"
                                        style="font-size:6px;vertical-align:middle;"></i>Lowercase</li>
                                <li data-rule="number"><i class="fas fa-circle me-1"
                                        style="font-size:6px;vertical-align:middle;"></i>Number</li>
                                <li data-rule="special"><i class="fas fa-circle me-1"
                                        style="font-size:6px;vertical-align:middle;"></i>Symbol</li>
                            </ul>
                        </div>
                    </div>

                    <button type="submit" name="registerStudentBTN" class="btn btn-register w-100 mt-3">
                        <i class="fas fa-user-plus me-2"></i>Create Account
                    </button>
                    <p class="text-center small mt-3 mb-0">Already have an account?
                        <a href="login.php" class="fw-bold text-decoration-none" style="color:var(--udsm-blue);">Login
                            here</a>
                    </p>
                </form>
            </div>

            <!-- Staff Registration Form -->
            <div id="staffFormContainer" class="auth-card d-one">
                <button class="btn-back" onclick="goBack()">
                    <i class="fas fa-arrow-left"></i> Back
                </button>

                <div class="reg-header">
                    <img src="assets/img/logo.png" alt="UDSM" class="reg-logo">
                    <div>
                        <span class="reg-badge badge-staff">Staff</span>
                        <h5 class="fw-bold mb-0" style="font-size:1rem;line-height:1.3;">Create Your Account</h5>
                        <p class="text-muted mb-0" style="font-size:.75rem;">UDSM Complaint Management System</p>
                    </div>
                </div>

                <?php if (!empty($error) && isset($_POST['registerStaffBTN'])): ?>
                    <div class="alert alert-danger d-flex align-items-center gap-2 mb-3 py-2">
                        <i class="fas fa-exclamation-circle flex-shrink-0"></i>
                        <span class="flex-grow-1 small"><?= htmlspecialchars($error) ?></span>
                        <button type="button" onclick="this.closest('.alert').remove()"
                            class="btn-close btn-sm ms-auto"></button>
                    </div>
                <?php endif; ?>

                <form action="" method="POST" onsubmit="showLoader()">
                    <!-- <div class="form-divider">Personal Information</div> -->
                    <div class="row g-2">
                        <div class="col-12 col-sm-6">
                            <label class="form-label">Full Name</label>
                            <div class="field-wrap">
                                <i class="fas fa-user field-icon"></i>
                                <input type="text" id="st_name" class="form-control icon-input" name="staff_name"
                                    placeholder="Enter your full name" oninput="capitalizeWords(this)" required>
                                <div class="invalid-feedback"></div>
                            </div>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label">Staff ID</label>
                            <div class="field-wrap">
                                <i class="fas fa-id-badge field-icon"></i>
                                <input type="text" id="st_staff_id" class="form-control icon-input" name="staff_id"
                                    placeholder="UDSM-STAFF-XXXXX" pattern="^UDSM-STAFF-[0-9]{5}$"
                                    title="Use format UDSM-STAFF-XXXXX" maxlength="16" minlength="16"
                                    oninput="this.value=this.value.toUpperCase().replace(/[^A-Z0-9-]/g,'').slice(0,16)"
                                    required>
                                <div class="invalid-feedback"></div>
                            </div>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label">Email Address</label>
                            <div class="field-wrap">
                                <i class="fas fa-envelope field-icon"></i>
                                <input type="email" id="st_email" class="form-control icon-input" name="staff_email"
                                    placeholder="staff@udsm.ac.tz" required>
                                <div class="invalid-feedback"></div>
                            </div>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label">Phone Number</label>
                            <div class="field-wrap">
                                <i class="fas fa-phone field-icon"></i>
                                <input type="text" id="st_phone" class="form-control icon-input" name="phone_number"
                                    placeholder="0XXXXXXXXX" inputmode="numeric" pattern="^0[0-9]{9}$"
                                    title="Enter 10 digits starting with 0" maxlength="10" minlength="10"
                                    oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,10)" required>
                                <div class="invalid-feedback"></div>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Department
                                <span class="fw-normal text-muted">(optional)</span>
                            </label>
                            <div class="field-wrap">
                                <i class="fas fa-building field-icon"></i>
                                <select class="form-select icon-input" name="staff_department">
                                    <option value="">-- Select Department --</option>
                                    <?php while ($dept = $departments->fetch_assoc()): ?>
                                        <option value="<?= $dept['department_id'] ?>">
                                            <?= htmlspecialchars($dept['department_name']) ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                                <div class="invalid-feedback"></div>
                            </div>
                        </div>
                    </div>

                    <!-- <div class="form-divider mt-2">Security</div> -->
                    <div class="row g-2">
                        <div class="col-12 col-sm-6">
                            <label class="form-label">Password</label>
                            <div class="pwd-wrap field-wrap">
                                <i class="fas fa-lock field-icon"></i>
                                <input type="password" id="st_pwd" class="form-control" name="password"
                                    placeholder="Min. 8 characters" required>
                                <button type="button" class="pwd-eye" onclick="togglePwd('st_pwd',this)" tabindex="-1">
                                    <i class="fas fa-eye-slash"></i>
                                </button>
                                <div class="invalid-feedback"></div>
                            </div>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label">Confirm Password</label>
                            <div class="pwd-wrap field-wrap">
                                <i class="fas fa-lock field-icon"></i>
                                <input type="password" id="st_cpwd" class="form-control" name="confirm_password"
                                    placeholder="Repeat your password" required>
                                <button type="button" class="pwd-eye" onclick="togglePwd('st_cpwd',this)" tabindex="-1">
                                    <i class="fas fa-eye-slash"></i>
                                </button>
                                <div class="invalid-feedback"></div>
                            </div>
                        </div>
                        <div class="col-12">
                            <ul class="pwd-rules">
                                <li data-rule="length"><i class="fas fa-circle me-1"
                                        style="font-size:6px;vertical-align:middle;"></i>8+ chars</li>
                                <li data-rule="upper"><i class="fas fa-circle me-1"
                                        style="font-size:6px;vertical-align:middle;"></i>Uppercase</li>
                                <li data-rule="lower"><i class="fas fa-circle me-1"
                                        style="font-size:6px;vertical-align:middle;"></i>Lowercase</li>
                                <li data-rule="number"><i class="fas fa-circle me-1"
                                        style="font-size:6px;vertical-align:middle;"></i>Number</li>
                                <li data-rule="special"><i class="fas fa-circle me-1"
                                        style="font-size:6px;vertical-align:middle;"></i>Symbol</li>
                            </ul>
                        </div>
                    </div>

                    <button type="submit" name="registerStaffBTN" class="btn btn-register w-100 mt-3">
                        <i class="fas fa-user-check me-2"></i>Register as Staff
                    </button>
                    <p class="text-center small mt-3 mb-0">Already have an account?
                        <a href="login.php" class="fw-bold text-decoration-none" style="color:var(--udsm-blue);">Login
                            here</a>
                    </p>
                </form>
            </div>

        </div><!-- /.split-right -->
    </div><!-- /.split-layout -->

    <script src="assets/js/auth-script.js"></script>
    <script>
        function capitalizeWords(input) {
            if (typeof input.value !== 'string' || input.value.length === 0) return;
            input.value = input.value.replace(/\b\w/g, function (char) {
                return char.toUpperCase();
            });
        }
    </script>
    <script src="assets/plugins/toastr/toastr.min.js"></script>
    <script src="assets/plugins/toastr/toastr.js"></script>
</body>

</html>