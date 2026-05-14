<?php
class User
{
    private $conn;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    // Student Registration
    public function studentRegister($username, $reg_no, $email, $phone_number, $password, $confirmPassword)
    {
        try {
            // Check empty fields
            if (empty($username) || empty($reg_no) || empty($email) || empty($phone_number) || empty($password) || empty($confirmPassword)) {
                throw new Exception("All fields are required.");
            }

            // Validate registration number
            $reg_no = trim($reg_no);
            if (!preg_match('/^202\d-04-\d{5}$/', $reg_no)) {
                throw new Exception("Invalid registration number format. Must be in the format 202X-04-XXXXX.");
            }

            // Validate phone number
            $phone_number = trim($phone_number);
            if (!preg_match('/^0\d{9}$/', $phone_number)) {
                throw new Exception("Invalid phone number format. Must be 10 digits starting with 0 (e.g. 0712345678).");
            }

            // Validate email
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Invalid Email format.");
            }

            // Validate password
            if ($password != $confirmPassword) {
                throw new Exception("Passwords do not match.");
            }

            if (strlen($password) < 8) {
                throw new Exception("Password must be at least 8 characters long.");
            }

            if (!preg_match('/[A-Z]/', $password)) {
                throw new Exception("Password must contain at least one uppercase letter.");
            }

            if (!preg_match('/[a-z]/', $password)) {
                throw new Exception("Password must contain at least one lowercase letter.");
            }

            if (!preg_match('/[0-9]/', $password)) {
                throw new Exception("Password must contain at least one number.");
            }

            if (!preg_match('/[\W]/', $password)) {
                throw new Exception("Password must contain at least one special character.");
            }

            // Check if email exists
            $email_check_stmt = $this->conn->prepare("SELECT user_id FROM users WHERE user_email = ?");
            $email_check_stmt->bind_param("s", $email);
            $email_check_stmt->execute();
            $email_check_result = $email_check_stmt->get_result();

            if ($email_check_result->num_rows > 0) {
                throw new Exception("Email already registered.");
            }
            $email_check_result->close();

            // Check Registration Number
            $regno_check_stmt = $this->conn->prepare("SELECT student_id FROM students WHERE student_registration_number = ?");
            $regno_check_stmt->bind_param("s", $reg_no);
            $regno_check_stmt->execute();
            $regno_check_result = $regno_check_stmt->get_result();

            if ($regno_check_result->num_rows > 0) {
                throw new Exception("Registration number already registered.");
            }
            $regno_check_result->close();

            // Start Transaction
            $this->conn->begin_transaction();

            // Hash password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            // Insert into users table
            $insert_user_stmt = $this->conn->prepare("INSERT INTO users (username, user_email, user_phone_number, user_password, user_role) VALUES (?, ?, ?, ?, 'student')");
            $insert_user_stmt->bind_param("ssss", $username, $email, $phone_number, $hashedPassword);
            $insert_user_stmt->execute();

            $userId = $this->conn->insert_id;
            $insert_user_stmt->close();

            // Insert into students table
            $insert_student_stmt = $this->conn->prepare("INSERT INTO students (student_user_id, student_registration_number) VALUES(?, ?)");
            $insert_student_stmt->bind_param("ss", $userId, $reg_no);
            $insert_student_stmt->execute();
            $insert_student_stmt->close();

            // Commit Transaction
            $this->conn->commit();

            return true;
        } catch (Exception $e) {
            // Rollback
            $this->conn->rollback();

            throw new Exception($e->getMessage());
        }
    }

    // Staff Registration
    public function staffRegister($username, $email, $password, $confirmPassword, $departmentId = null, $staffId = null, $phoneNumber = null)
    {
        try {
            // Check empty fields
            if (empty($username) || empty($email) || empty($password) || empty($confirmPassword)) {
                throw new Exception("All fields are required.");
            }

            // Validate staff ID format
            if (!empty($staffId)) {
                $staffId = strtoupper(trim($staffId));
                if (!preg_match('/^UDSM-STAFF-\d{5}$/', $staffId)) {
                    throw new Exception("Invalid Staff ID format. Must be UDSM-STAFF-XXXXX.");
                }
            } else {
                $staffId = null;
            }

            // Validate phone number
            if (!empty($phoneNumber)) {
                $phoneNumber = trim($phoneNumber);
                if (!preg_match('/^0\d{9}$/', $phoneNumber)) {
                    throw new Exception("Invalid phone number format. Must be 10 digits starting with 0.");
                }
            } else {
                $phoneNumber = null;
            }

            // Validate email
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Invalid Email format.");
            }

            // Validate password
            if ($password != $confirmPassword) {
                throw new Exception("Passwords do not match.");
            }

            if (strlen($password) < 8) {
                throw new Exception("Password must be at least 8 characters long.");
            }

            if (!preg_match('/[A-Z]/', $password)) {
                throw new Exception("Password must contain at least one uppercase letter.");
            }

            if (!preg_match('/[a-z]/', $password)) {
                throw new Exception("Password must contain at least one lowercase letter.");
            }

            if (!preg_match('/[0-9]/', $password)) {
                throw new Exception("Password must contain at least one number.");
            }

            if (!preg_match('/[\W]/', $password)) {
                throw new Exception("Password must contain at least one special character.");
            }

            // Check if email exists
            $email_check_stmt = $this->conn->prepare("SELECT user_id FROM users WHERE user_email = ?");
            $email_check_stmt->bind_param("s", $email);
            $email_check_stmt->execute();
            $email_check_result = $email_check_stmt->get_result();

            if ($email_check_result->num_rows > 0) {
                throw new Exception("Email already registered.");
            }
            $email_check_result->close();

            // Check if staff ID is already taken
            if ($staffId !== null) {
                $sid_check = $this->conn->prepare("SELECT staff_id FROM staffs WHERE staff_id = ?");
                $sid_check->bind_param("s", $staffId);
                $sid_check->execute();
                if ($sid_check->get_result()->num_rows > 0) {
                    $sid_check->close();
                    throw new Exception("Staff ID already registered.");
                }
                $sid_check->close();
            }

            // Start Transaction
            $this->conn->begin_transaction();

            // Hash password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            // Insert into users table
            $insert_user_stmt = $this->conn->prepare("INSERT INTO users (username, user_email, user_phone_number, user_password, user_role) VALUES (?, ?, ?, ?, 'staff')");
            $insert_user_stmt->bind_param("ssss", $username, $email, $phoneNumber, $hashedPassword);
            $insert_user_stmt->execute();

            $userId = $this->conn->insert_id;
            $insert_user_stmt->close();

            // Insert into staffs table
            $deptId = $departmentId ?: null;
            $insert_staff_stmt = $this->conn->prepare("INSERT INTO staffs (staff_user_id, staff_id, staff_department_id) VALUES(?, ?, ?)");
            $insert_staff_stmt->bind_param("isi", $userId, $staffId, $deptId);
            $insert_staff_stmt->execute();
            $insert_staff_stmt->close();

            // Commit Transaction
            $this->conn->commit();

            return true;
        } catch (Exception $e) {
            // Rollback
            $this->conn->rollback();

            throw new Exception($e->getMessage());
        }
    }

    // Login
    public function userLogin($email, $password)
    {

        try {
            // Get user by email
            $stmt = $this->conn->prepare("SELECT * FROM users WHERE user_email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();

            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();

            // User not found
            if (!$user) {
                return [
                    "status" => false,
                    "message" => "Invalid email or password"
                ];
            }

            // Handle account lock and auto unlock
            if ($user['account_locked'] == 1) {

                $lockTime = !empty($user['lock_time']) ? strtotime($user['lock_time']) : 0;
                $currentTime = time();

                if (($currentTime - $lockTime) < 900) { // 15 mins
                    $remaining = 900 - ($currentTime - $lockTime);
                    $minutes = ceil($remaining / 60);

                    return [
                        "status" => false,
                        "message" => "Account is locked. Try again in {$minutes} minute(s)"
                    ];
                } else {
                    // Unlock automatically
                    $unlock = $this->conn->prepare("UPDATE users 
                                                    SET account_locked = 0, failed_attempts = 0 
                                                    WHERE user_id = ?");
                    $unlock->bind_param("i", $user['user_id']);
                    $unlock->execute();
                    $unlock->close();

                    // Update local state
                    $user['account_locked'] = 0;
                    $user['failed_attempts'] = 0;
                }
            }

            // Check if account is active
            if ($user['user_status'] !== "active") {
                return [
                    "status" => false,
                    "message" => "Account is not active"
                ];
            }

            // Verify password
            if (password_verify($password, $user['user_password'])) {

                // Reset failed attempts
                $reset = $this->conn->prepare("UPDATE users SET failed_attempts = 0 WHERE user_id = ?");
                $reset->bind_param("i", $user['user_id']);
                $reset->execute();
                $reset->close();

                return [
                    "status" => true,
                    "message" => "Login successful",
                    "user_id" => $user['user_id'],
                    "username" => $user['username'],
                    "user_email" => $user['user_email'],
                    "user_role" => $user['user_role']
                ];
            } else {

                // Wrong password
                $attempts = $user['failed_attempts'] + 1;

                if ($attempts >= 3) {

                    // Lock account
                    $lock = $this->conn->prepare("UPDATE users 
                                                    SET failed_attempts = ?, account_locked = 1, lock_time = NOW()
                                                    WHERE user_id = ?");
                    $lock->bind_param("ii", $attempts, $user['user_id']);
                    $lock->execute();
                    $lock->close();

                    return [
                        "status" => false,
                        "message" => "Account locked after 3 failed attempts"
                    ];
                } else {

                    // Update attempts
                    $update = $this->conn->prepare("UPDATE users SET failed_attempts = ? WHERE user_id = ?");
                    $update->bind_param("ii", $attempts, $user['user_id']);
                    $update->execute();
                    $update->close();

                    return [
                        "status" => false,
                        "message" => "Invalid email or password. Attempt $attempts of 3"
                    ];
                }
            }
        } catch (Exception $e) {
            return [
                "status" => false,
                "message" => "Login error: " . $e->getMessage()
            ];
        }
    }

    // Fetch full profile with role-specific fields
    public function getFullProfile($userId, $role)
    {
        if ($role === 'student') {
            $sql = "SELECT u.user_id, u.username, u.user_email, u.user_role, u.user_status, u.created_at,
                           s.student_registration_number, s.student_program,
                           c.college_name
                    FROM users u
                    JOIN students s ON u.user_id = s.student_user_id
                    LEFT JOIN colleges c ON s.student_college_id = c.college_id
                    WHERE u.user_id = ?";
        } elseif ($role === 'staff') {
            $sql = "SELECT u.user_id, u.username, u.user_email, u.user_role, u.user_status, u.created_at,
                           d.department_name, sr.role_name
                    FROM users u
                    JOIN staffs st ON u.user_id = st.staff_user_id
                    LEFT JOIN departments d ON st.staff_department_id = d.department_id
                    LEFT JOIN staff_roles sr ON st.staff_role_id = sr.role_id
                    WHERE u.user_id = ?";
        } else {
            $sql = "SELECT user_id, username, user_email, user_role, user_status, created_at
                    FROM users WHERE user_id = ?";
        }
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $data;
    }

    // Update username
    public function updateUsername($userId, $newUsername)
    {
        if (empty($newUsername)) {
            throw new Exception("Username cannot be empty.");
        }
        $chk = $this->conn->prepare("SELECT user_id FROM users WHERE username = ? AND user_id != ?");
        $chk->bind_param("si", $newUsername, $userId);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            $chk->close();
            throw new Exception("Username is already taken.");
        }
        $chk->close();

        $stmt = $this->conn->prepare("UPDATE users SET username = ? WHERE user_id = ?");
        $stmt->bind_param("si", $newUsername, $userId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    // Update password (verifies current password first)
    public function updatePassword($userId, $currentPassword, $newPassword, $confirmPassword)
    {
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            throw new Exception("All password fields are required.");
        }
        if ($newPassword !== $confirmPassword) {
            throw new Exception("New passwords do not match.");
        }
        if (strlen($newPassword) < 8) {
            throw new Exception("New password must be at least 8 characters.");
        }

        $stmt = $this->conn->prepare("SELECT user_password FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row || !password_verify($currentPassword, $row['user_password'])) {
            throw new Exception("Current password is incorrect.");
        }

        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $upd = $this->conn->prepare("UPDATE users SET user_password = ? WHERE user_id = ?");
        $upd->bind_param("si", $hash, $userId);
        $ok = $upd->execute();
        $upd->close();
        return $ok;
    }

    // Fetch all students with account and college details
    public function getAllRegisteredStudents()
    {
        $sql = "SELECT u.user_id, u.username, u.user_email, u.user_status, 
                       s.student_registration_number, s.student_program, c.college_name
                FROM users u
                JOIN students s ON u.user_id = s.student_user_id
                LEFT JOIN colleges c ON s.student_college_id = c.college_id
                WHERE u.user_role = 'student'
                ORDER BY s.student_registration_number ASC";
        return $this->conn->query($sql);
    }

    // Fetch all staff with account and department details
    public function getAllRegisteredStaff()
    {
        $sql = "SELECT u.user_id, u.username, u.user_email, u.user_status, 
                       d.department_name
                FROM users u
                JOIN staffs st ON u.user_id = st.staff_user_id
                LEFT JOIN departments d ON st.staff_department_id = d.department_id
                WHERE u.user_role = 'staff'
                ORDER BY u.username ASC";
        return $this->conn->query($sql);
    }

    // Utility to toggle user status (Active/Inactive)
    public function toggleStatus($userId, $currentStatus)
    {
        $newStatus = ($currentStatus == 'active') ? 'inactive' : 'active';
        $stmt = $this->conn->prepare("UPDATE users SET user_status = ? WHERE user_id = ?");
        $stmt->bind_param('si', $newStatus, $userId);
        return $stmt->execute();
    }

    public function getAttachmentById($attachmentId, $userId, $userRole)
    {
        $sql = "SELECT ca.attachment_id, ca.complaint_id, ca.file_path, ca.file_name, ca.file_type,
                       c.student_id, c.assigned_staff_id,
                       s.student_user_id, st.staff_user_id
                FROM complaint_attachments ca
                JOIN complaints c ON ca.complaint_id = c.complaint_id
                LEFT JOIN students s ON c.student_id = s.student_id
                LEFT JOIN staffs st ON c.assigned_staff_id = st.staff_id
                WHERE ca.attachment_id = ?
                LIMIT 1";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('i', $attachmentId);
        $stmt->execute();
        $result = $stmt->get_result();
        $attachment = $result->fetch_assoc();
        $stmt->close();

        if (!$attachment) {
            return false;
        }

        switch ($userRole) {
            case 'admin':
                return $attachment;
            case 'staff':
                if (!empty($attachment['staff_user_id']) && (int) $attachment['staff_user_id'] === $userId) {
                    return $attachment;
                }
                break;
            case 'student':
                if (!empty($attachment['student_user_id']) && (int) $attachment['student_user_id'] === $userId) {
                    return $attachment;
                }
                break;
        }

        return false;
    }

    public function createPasswordResetToken($email)
    {
        $stmt = $this->conn->prepare("SELECT user_id FROM users WHERE user_email = ?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) {
            return false;
        }
        $stmt->close();

        $del = $this->conn->prepare("DELETE FROM password_resets WHERE email = ?");
        $del->bind_param('s', $email);
        $del->execute();
        $del->close();

        $token     = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $ins = $this->conn->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
        $ins->bind_param('sss', $email, $token, $expiresAt);
        $ins->execute();
        $ins->close();

        return $token;
    }

    public function validateResetToken($token)
    {
        $stmt = $this->conn->prepare(
            "SELECT email FROM password_resets
             WHERE token = ? AND used = 0 AND expires_at > NOW()
             LIMIT 1"
        );
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ? $row['email'] : false;
    }

    public function resetPassword($token, $newPassword)
    {
        $email = $this->validateResetToken($token);
        if (!$email) {
            throw new Exception("Invalid or expired reset link.");
        }

        $hashed = password_hash($newPassword, PASSWORD_DEFAULT);

        $upd = $this->conn->prepare("UPDATE users SET user_password = ? WHERE user_email = ?");
        $upd->bind_param('ss', $hashed, $email);
        $upd->execute();
        $upd->close();

        $mark = $this->conn->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
        $mark->bind_param('s', $token);
        $mark->execute();
        $mark->close();

        return true;
    }
}
