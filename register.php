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
        // Student inputs
        $username = trim($_POST['student_name']);
        $reg_no = trim($_POST['reg_no']);
        $email = trim($_POST['student_email']);
        // $college = $_POST['college'];
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
        .auth-card {
            width: 100%;
            max-width: 500px;
            padding: 40px;
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            background: #fff;
        }
    </style>
</head>

<body>

    <div id="loader">
        <div class="spinner"></div>
    </div>

    <!-- Form type selection -->
    <div id="authSelection" class="auth-card text-center p-5">
        <h4 class="fw-bold mb-4">Join UDSM Complaints System</h4>
        <p class="text-muted mb-5">Please Select your account type to continue</p>

        <div class="row g-4">
            <div class="col-md-6">
                <div class="selection-box p-4 border rounded-5 cursor-pointer" onclick="showForm('student')">
                    <i class="fas fa-user-graduate fa-3x mb-3 text-primary"></i>
                    <h5 class="fw-bold">Student</h5>
                    <!-- <p class="small text-muted">Register using your Reg. Number</p> -->
                </div>
            </div>

            <div class="col-md-6">
                <div class="selection-box p-4 border rounded-5 cursor-pointer" onclick="showForm('staff')">
                    <i class="fas fa-user-tie fa-3x mb-3 text-primary"></i>
                    <h5 class="fw-bold">Staff</h5>
                    <!-- <p class="small text-muted">Register using your Staff ID</p> -->
                </div>
            </div>

            <p class="text-center small mt-4 mb-0">Already have an account? <a href="login.php"
                    class="fw-bold text-decoration-none" style="color: var(--udsm-blue);">Login here</a></p>

        </div>
    </div>

    <!-- Student form -->
    <div id="studentFormContainer" class="auth-card p-5 d-one">
        <button class="btn btn-link p-0 mb-1 text-decoration-none" onclick="goBack()">
            <i class="fas fa-arrow-left"></i>
            Back
        </button>
        <div class="text-center mb-4">
            <h4 class="fw-bold mb-1">Create Account</h4>
            <p class="text-muted small">Join the UDSM Complaint Management System</p>
            <!-- <p class="text-muted small">Step<span id="stepNumber">1</span> of 3</p> -->
        </div>

        <!-- <div class="step-container">
            <div class="step-line"></div>
            <div class="step-circle active" data-step="1">1</div>
            <div class="step-circle" data-step="2">2</div>
            <div class="step-circle" data-step="3">3</div>
        </div> -->

        <!-- Alert -->
        <?php if (!empty($message) || !empty($error)):
            $type = !empty($message) ? 'success' : 'danger';
            $text = !empty($message) ? $message : $error;
            ?>

            <div class="alert alert-<?php echo $type; ?>" id="alertMessage">
                <span style="display: flex; align-items: center; gap: 0.5rem; font-size: 15px;">
                    <i class="fas <?php echo $type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($text); ?>

                    <button onclick="document.getElementById('alertMessage').style.display='none'"
                        style="background: none; border: none; color: inherit; cursor: pointer; opacity: 0.7; transition: opacity 0.2s;"
                        onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.7'">
                        <i class="fas fa-times"></i>
                    </button>
                </span>

            </div>
        <?php endif; ?>
        <!-- / Alert -->

        <form action="" method="POST" id="multiStepForm">

            <div class="form-step active" id="step1">
                <div class="step-error alert alert-danger d-none mb-3 py-2 small"></div>

                <div class="mb-3">
                    <label class="form-label small fw-bold">Full Name</label>
                    <input type="text" class="form-control" name="student_name" placeholder="Enter FullName"
                        oninput="capitalizeWords(this)" required>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold">Registration Number</label>
                    <input type="text" class="form-control" name="reg_no" placeholder="202X-04-XXXXX"
                        pattern="^202[0-9]-04-[0-9]{5}$" title="Use format 202X-04-XXXXX" maxlength="13" minlength="13"
                        oninput="this.value = this.value.toUpperCase().replace(/[^0-9-]/g,'').slice(0,13)" required>
                </div>

                <div class="mb-4">
                    <label class="form-label small fw-bold">Email Address</label>
                    <input type="email" class="form-control" name="student_email" placeholder="email@udsm.ac.tz"
                        required>
                </div>

                <button type="button" class="btn btn-udsm w-100 next-step">Next Step <i
                        class="fas fa-arrow-right ms-2"></i></button>
            </div>

            <!-- <div class="form-step" id="step2"> -->

            <!-- <div class="mb-3">
                    <label class="form-label small fw-bold">College</label>
                    <select class="form-select form-control" name="college" required>
                        <option value="" selected disabled>--College--</option>
                        <<?php if ($colleges): ?>
                            <?php while ($college_row = $colleges->fetch_assoc()): ?>
                            <option value="<?= ($college_row['college_id']) ?>">
                            <?= ($college_row['college_name']) ?>
                            </option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                    </select>
                </div> -->

            <!-- <div class="mb-3">
                    <label class="form-label small text-muted fw-bold">Program</label>
                    <input type="text" class="form-control" name="program" placeholder="" required>
                </div> -->

            <!-- <div class="d-flex gap-2">
                    <button type="button" class="btn btn-light border w-50 prev-step">Back</button>
                    <button type="button" class="btn btn-udsm w-50 next-step">Next <i class="fas fa-arrow-right ms-2"></i></button>
                </div>
            </div> -->

            <div class="form-step" id="step2">
                <div class="step-error alert alert-danger d-none mb-3 py-2 small"></div>

                <div class="mb-3">
                    <label class="form-label small text-muted fw-bold">Phone Number</label>
                    <input type="text" class="form-control" name="phone_number" placeholder="0XXXXXXXXX"
                        inputmode="numeric" pattern="^0[0-9]{9}$"
                        title="Enter 10 digits starting with 0 (e.g. 0712345678)" maxlength="10" minlength="10"
                        oninput="this.value = this.value.replace(/[^0-9]/g,'').slice(0,10)" required>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold"><i class="fas fa-lock me-2"></i>Password</label>
                    <input type="password" class="form-control" name="password" placeholder="........" required>
                    <div class="form-text text-muted">Min 8 characters, uppercase, lowercase, number, symbol.</div>
                </div>

                <div class="mb-4">
                    <label class="form-label small fw-bold"><i class="fas fa-shield-alt me-2"></i>Confirm
                        Password</label>
                    <input type="password" class="form-control" name="confirm_password" placeholder="........" required>
                </div>

                <div class="d-flex gap-2 mb-3">
                    <button type="button" class="btn btn-light border w-50 prev-step fw-bold">Back</button>
                    <button type="submit" name="registerStudentBTN" class="btn btn-success w-50"
                        style="border-radius: 10px; font-weight: 600;">Complete <i
                            class="fas fa-check-circle ms-2"></i></button>
                </div>
            </div>

            <p class="text-center small mt-4 mb-0">Already have an account? <a href="login.php"
                    class="fw-bold text-decoration-none" style="color: var(--udsm-blue);">Login here</a></p>
        </form>
    </div>

    <!-- Staff form -->
    <div id="staffFormContainer" class="auth-card p-5 d-one">
        <button class="btn btn-link p-0 mb-1 text-decoration-none" onclick="goBack()">
            <i class="fas fa-arrow-left"></i>
            Back
        </button>
        <div class="text-center mb-4">
            <h4 class="fw-bold mb-1">Staff Registration</h4>
            <p class="text-muted small">Join the UDSM Complaint Management System</p>
        </div>

        <?php if (!empty($message) || !empty($error)):
            $type = !empty($message) ? 'success' : 'danger';
            $text = !empty($message) ? $message : $error;
            ?>
            <div class="alert alert-<?php echo $type; ?>" id="staffAlertMessage">
                <span style="display: flex; align-items: center; gap: 0.5rem; font-size: 15px;">
                    <i class="fas <?php echo $type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($text); ?>
                    <button onclick="document.getElementById('staffAlertMessage').style.display='none'"
                        style="background: none; border: none; color: inherit; cursor: pointer; opacity: 0.7; transition: opacity 0.2s;"
                        onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.7'">
                        <i class="fas fa-times"></i>
                    </button>
                </span>
            </div>
        <?php endif; ?>

        <form action="" method="POST" id="staffMultiStepForm" onsubmit="showLoader()">

            <!-- Step 1: Basic Info + Department -->
            <div class="form-step active" id="staff_step1">
                <div class="step-error alert alert-danger d-none mb-3 py-2 small"></div>

                <div class="mb-3">
                    <label class="form-label small fw-bold">Full Name</label>
                    <input type="text" class="form-control" name="staff_name" placeholder="Enter full name"
                        oninput="capitalizeWords(this)" required>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold">Staff ID</label>
                    <input type="text" class="form-control" name="staff_id" placeholder="UDSM-STAFF-XXXXX"
                        pattern="^UDSM-STAFF-[0-9]{5}$" title="Use format UDSM-STAFF-XXXXX" maxlength="16"
                        minlength="16"
                        oninput="this.value = this.value.toUpperCase().replace(/[^A-Z0-9-]/g,'').slice(0,16)" required>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold">Phone Number</label>
                    <input type="text" class="form-control" name="phone_number" placeholder="0XXXXXXXXX"
                        inputmode="numeric" pattern="^0[0-9]{9}$"
                        title="Enter 10 digits starting with 0 (e.g. 0712345678)" maxlength="10" minlength="10"
                        oninput="this.value = this.value.replace(/[^0-9]/g,'').slice(0,10)" required>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold">Email Address</label>
                    <input type="email" class="form-control" name="staff_email" placeholder="staff@udsm.ac.tz" required>
                </div>

                <button type="button" class="btn btn-udsm w-100 next-step">
                    Next Step <i class="fas fa-arrow-right ms-2"></i>
                </button>
            </div>

            <!-- Step 2: Password -->
            <div class="form-step" id="staff_step2">
                <div class="step-error alert alert-danger d-none mb-3 py-2 small"></div>

                <div class="mb-4">
                    <label class="form-label small fw-bold">Department</label>
                    <select class="form-select form-control" name="staff_department">
                        <option value="">-- Select Department --</option>
                        <?php while ($dept = $departments->fetch_assoc()): ?>
                            <option value="<?= $dept['department_id'] ?>">
                                <?= htmlspecialchars($dept['department_name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>


                <div class="mb-3">
                    <label class="form-label small fw-bold"><i class="fas fa-lock me-2"></i>Password</label>
                    <input type="password" class="form-control" name="password" placeholder="........" required>
                    <div class="form-text text-muted">Min 8 characters, uppercase, lowercase, number, symbol.</div>
                </div>

                <div class="mb-4">
                    <label class="form-label small fw-bold"><i class="fas fa-shield-alt me-2"></i>Confirm
                        Password</label>
                    <input type="password" class="form-control" name="confirm_password" placeholder="........" required>
                </div>

                <div class="d-flex gap-2 mb-3">
                    <button type="button" class="btn btn-light border w-50 prev-step fw-bold">Back</button>
                    <button type="submit" name="registerStaffBTN" class="btn btn-success w-50"
                        style="border-radius: 10px; font-weight: 600;">
                        Complete <i class="fas fa-check-circle ms-2"></i>
                    </button>
                </div>
            </div>

            <p class="text-center small mt-4 mb-0">Already have an account? <a href="login.php"
                    class="fw-bold text-decoration-none" style="color: var(--udsm-blue);">Login here</a></p>
        </form>
    </div>

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