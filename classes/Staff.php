<?php
require_once __DIR__ . '/Notification.php';

class Staff
{

    private $conn;

    public function __construct($db)
    {
        $this->conn = $db;
    }


    public function getStaffDetailsByUserId($userId)
    {
        $sql = "SELECT users.user_id, users.username, users.user_email, users.user_role, users.user_status,
                       staffs.staff_id, staffs.staff_department_id, staffs.staff_approval_status,
                       staffs.staff_approved_by, staffs.staff_approved_at,
                       departments.department_name, 
                       staff_roles.role_id, staff_roles.role_name, staff_roles.role_rank
                FROM users
                INNER JOIN staffs ON users.user_id = staffs.staff_user_id
                LEFT JOIN departments ON staffs.staff_department_id = departments.department_id
                LEFT JOIN staff_roles ON staffs.staff_role_id = staff_roles.role_id
                WHERE users.user_id = ? AND users.user_role = 'staff'
                LIMIT 1";

        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();

        return $result->fetch_assoc();
    }

    public function isStaffApproved($userId)
    {
        $sql = "SELECT staffs.staff_approval_status
                FROM staffs 
                INNER JOIN users ON staffs.staff_user_id = users.user_id
                WHERE users.user_id = ? AND users.user_role = 'staff'
                LIMIT 1";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        return $row && (int) $row['staff_approval_status'] === 1;
    }

    public function getStaffComplaintCounts($staffId)
    {
        $sql = "SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN complaint_status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN complaint_status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                    SUM(CASE WHEN complaint_status = 'resolved' THEN 1 ELSE 0 END) as resolved,
                    SUM(CASE WHEN complaint_status = 'rejected' THEN 1 ELSE 0 END) as rejected
                FROM complaints
                JOIN complaint_assignments ca ON complaints.complaint_id = ca.complaint_id AND ca.staff_id = ? AND ca.status = 'active'";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            return [
                'total' => 0,
                STATUS_PENDING => 0,
                STATUS_IN_PROGRESS => 0,
                STATUS_RESOLVED => 0,
                STATUS_REJECTED => 0,
            ];
        }

        $stmt->bind_param('s', $staffId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        return [
            'total' => (int) ($row['total'] ?? 0),
            STATUS_PENDING => (int) ($row['pending'] ?? 0),
            STATUS_IN_PROGRESS => (int) ($row['in_progress'] ?? 0),
            STATUS_RESOLVED => (int) ($row['resolved'] ?? 0),
            STATUS_REJECTED => (int) ($row['rejected'] ?? 0),
        ];
    }

    public function getRecentAssignedComplaints($staffId, $limit = 5)
    {
        $sql = "SELECT complaints.complaint_id, complaints.complaint_title, complaints.complaint_status, complaints.created_at,
                       complaint_categories.category_name
                FROM complaints
                JOIN complaint_assignments ca ON complaints.complaint_id = ca.complaint_id AND ca.staff_id = ? AND ca.status = 'active'
                LEFT JOIN complaint_categories ON complaints.category_id = complaint_categories.category_id
                ORDER BY complaints.created_at DESC
                LIMIT ?";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            return [];
        }

        $stmt->bind_param('si', $staffId, $limit);
        $stmt->execute();
        $result = $stmt->get_result();

        $complaints = [];
        while ($row = $result->fetch_assoc()) {
            $complaints[] = $row;
        }

        return $complaints;
    }

    public function getAssignedComplaints($staffId)
    {
        $sql = "SELECT complaints.complaint_id, complaints.complaint_title, complaints.complaint_status, complaints.created_at,
                       complaints.is_anonymous, users.username AS student_name,
                       students.student_registration_number, complaint_categories.category_name
                FROM complaints
                JOIN complaint_assignments ca ON complaints.complaint_id = ca.complaint_id AND ca.staff_id = ? AND ca.status = 'active'
                JOIN students ON complaints.student_id = students.student_id
                JOIN users ON students.student_user_id = users.user_id
                LEFT JOIN complaint_categories ON complaints.category_id = complaint_categories.category_id
                ORDER BY complaints.created_at DESC";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            return [];
        }

        $stmt->bind_param('s', $staffId);
        $stmt->execute();
        $result = $stmt->get_result();

        $complaints = [];
        while ($row = $result->fetch_assoc()) {
            $complaints[] = $row;
        }

        return $complaints;
    }

    // Get staff members eligible to receive an escalation (higher rank than the forwarding staff)
    public function getStaffForEscalation($fromStaffId)
    {
        $sql = "SELECT u.user_id, u.username, s.staff_id, d.department_name,
                       sr.role_name, sr.role_rank
                FROM staffs s
                JOIN users u ON s.staff_user_id = u.user_id
                LEFT JOIN departments d ON s.staff_department_id = d.department_id
                LEFT JOIN staff_roles sr ON s.staff_role_id = sr.role_id
                WHERE s.staff_approval_status = '1'
                  AND s.staff_id != ?
                  AND sr.role_rank > (
                      SELECT COALESCE(sr2.role_rank, 0)
                      FROM staffs s2
                      LEFT JOIN staff_roles sr2 ON s2.staff_role_id = sr2.role_id
                      WHERE s2.staff_id = ?
                  )
                ORDER BY sr.role_rank ASC, u.username ASC";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            return [];
        }

        $stmt->bind_param('ss', $fromStaffId, $fromStaffId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // Forward/escalate a complaint to a higher-ranked staff member
    public function forwardComplaint($complaintId, $fromStaffId, $toStaffId, $forwardedByUserId, $reason)
    {
        try {
            $this->conn->begin_transaction();

            // Block if already resolved or rejected
            $statusStmt = $this->conn->prepare(
                "SELECT complaint_status FROM complaints WHERE complaint_id = ? LIMIT 1"
            );
            $statusStmt->bind_param('i', $complaintId);
            $statusStmt->execute();
            $currentStatus = $statusStmt->get_result()->fetch_assoc()['complaint_status'] ?? '';
            $statusStmt->close();
            if (in_array($currentStatus, [STATUS_RESOLVED, STATUS_REJECTED])) {
                throw new Exception("Cannot escalate a complaint that is already $currentStatus.");
            }

            // Verify destination staff has a higher rank
            $rankSql = "SELECT
                            (SELECT COALESCE(sr.role_rank, 0) FROM staffs s JOIN staff_roles sr ON s.staff_role_id = sr.role_id WHERE s.staff_id = ?) AS from_rank,
                            (SELECT COALESCE(sr.role_rank, 0) FROM staffs s JOIN staff_roles sr ON s.staff_role_id = sr.role_id WHERE s.staff_id = ?) AS to_rank";
            $rankStmt = $this->conn->prepare($rankSql);
            $rankStmt->bind_param('ss', $fromStaffId, $toStaffId);
            $rankStmt->execute();
            $ranks = $rankStmt->get_result()->fetch_assoc();
            $rankStmt->close();

            if ((int) $ranks['to_rank'] <= (int) $ranks['from_rank']) {
                throw new Exception("Can only escalate to a staff member of higher rank.");
            }

            // Log the escalation
            $escStmt = $this->conn->prepare(
                "INSERT INTO complaint_escalations (complaint_id, from_staff_id, to_staff_id, forwarded_by, reason, status)
                 VALUES (?, ?, ?, ?, ?, 'pending')"
            );
            $escStmt->bind_param('issis', $complaintId, $fromStaffId, $toStaffId, $forwardedByUserId, $reason);
            $escStmt->execute();
            $escStmt->close();

            // Mark old assignment forwarded, create new lead assignment
            $fwdStmt = $this->conn->prepare(
                "UPDATE complaint_assignments SET status = 'forwarded', completed_at = NOW()
                 WHERE complaint_id = ? AND staff_id = ? AND status = 'active'"
            );
            $fwdStmt->bind_param('is', $complaintId, $fromStaffId);
            $fwdStmt->execute();
            $fwdStmt->close();

            $newAssignStmt = $this->conn->prepare(
                "INSERT INTO complaint_assignments (complaint_id, staff_id, assigned_by, is_lead, status, notes)
                 VALUES (?, ?, ?, 1, 'active', ?)"
            );
            $newAssignStmt->bind_param('isis', $complaintId, $toStaffId, $forwardedByUserId, $reason);
            $newAssignStmt->execute();
            $newAssignStmt->close();

            // Status log
            $logStmt = $this->conn->prepare(
                "INSERT INTO complaint_status_logs
                 (complaint_id, action, old_status, new_status, performed_by, remarks)
                 VALUES (?, 'escalated', ?, 'in_progress', ?, ?)"
            );
            $logStmt->bind_param('isis', $complaintId, $currentStatus, $forwardedByUserId, $reason);
            $logStmt->execute();
            $logStmt->close();

            $this->conn->commit();

            // Notify the receiving staff (outside transaction — non-critical)
            $toUserStmt = $this->conn->prepare(
                "SELECT staff_user_id FROM staffs WHERE staff_id = ? LIMIT 1"
            );
            $toUserStmt->bind_param('s', $toStaffId);
            $toUserStmt->execute();
            $toUser = $toUserStmt->get_result()->fetch_assoc();
            $toUserStmt->close();
            if ($toUser) {
                (new Notification($this->conn))->create(
                    (int) $toUser['staff_user_id'],
                    "Complaint #$complaintId has been escalated to you. Please review and take action.",
                    'new_assignment',
                    "assigned_complaint_details.php?id=$complaintId",
                    $complaintId
                );
            }

            return true;
        } catch (Exception $e) {
            $this->conn->rollback();
            throw new Exception($e->getMessage());
        }
    }

    // Get escalations pending acceptance for a staff member
    public function getPendingEscalationsForStaff($staffId)
    {
        $sql = "SELECT ce.*, c.complaint_title, c.complaint_status,
                       u.username AS forwarded_by_name,
                       su.username AS from_staff_name
                FROM complaint_escalations ce
                JOIN complaints c ON ce.complaint_id = c.complaint_id
                JOIN users u ON ce.forwarded_by = u.user_id
                JOIN staffs fs ON ce.from_staff_id = fs.staff_id
                JOIN users su ON fs.staff_user_id = su.user_id
                WHERE ce.to_staff_id = ? AND ce.status = 'pending'
                ORDER BY ce.escalated_at DESC";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('s', $staffId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // Get full escalation history for a complaint
    public function getEscalationHistoryForComplaint($complaintId)
    {
        $sql = "SELECT ce.*,
                       fu.username AS from_staff_name,
                       tu.username AS to_staff_name,
                       fsr.role_name AS from_role,
                       tsr.role_name AS to_role,
                       fbu.username AS forwarded_by_name
                FROM complaint_escalations ce
                JOIN staffs fs ON ce.from_staff_id = fs.staff_id
                JOIN users fu ON fs.staff_user_id = fu.user_id
                LEFT JOIN staff_roles fsr ON fs.staff_role_id = fsr.role_id
                JOIN staffs ts ON ce.to_staff_id = ts.staff_id
                JOIN users tu ON ts.staff_user_id = tu.user_id
                LEFT JOIN staff_roles tsr ON ts.staff_role_id = tsr.role_id
                JOIN users fbu ON ce.forwarded_by = fbu.user_id
                WHERE ce.complaint_id = ?
                ORDER BY ce.escalated_at ASC";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('i', $complaintId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function getComplaintById($complaintId, $staffId)
    {
        $sql = "SELECT c.*,
                       u.user_id AS student_user_id,
                       u.username AS student_name,
                       u.user_email AS student_email,
                       u.user_phone_number AS student_phone,
                       s.student_registration_number,
                       cc.category_name,
                       d.department_name,
                       su.username AS assigned_staff_name,
                       sr.role_name AS staff_role_name,
                       ca.target_resolution_date
                FROM complaints c
                JOIN students s ON c.student_id = s.student_id
                JOIN users u ON s.student_user_id = u.user_id
                LEFT JOIN complaint_categories cc ON c.category_id = cc.category_id
                LEFT JOIN departments d ON c.department_id = d.department_id
                JOIN complaint_assignments ca ON c.complaint_id = ca.complaint_id AND ca.staff_id = ? AND ca.status = 'active'
                LEFT JOIN staffs st ON ca.staff_id = st.staff_id
                LEFT JOIN users su ON st.staff_user_id = su.user_id
                LEFT JOIN staff_roles sr ON st.staff_role_id = sr.role_id
                WHERE c.complaint_id = ?
                LIMIT 1";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt)
            return null;
        $stmt->bind_param('si', $staffId, $complaintId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function getComplaintAttachments($complaintId)
    {
        $sql = "SELECT * FROM complaint_attachments WHERE complaint_id = ? ORDER BY uploaded_at ASC";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt)
            return [];
        $stmt->bind_param('i', $complaintId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function getComplaintStatusLogs($complaintId)
    {
        $sql = "SELECT csl.*, u.username AS performed_by_name
                FROM complaint_status_logs csl
                LEFT JOIN users u ON csl.performed_by = u.user_id
                WHERE csl.complaint_id = ?
                ORDER BY csl.changed_at ASC";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt)
            return [];
        $stmt->bind_param('i', $complaintId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function getCollaborationNotes($complaintId)
    {
        $sql = "SELECT cn.*, u.username AS created_by_name
                FROM collaboration_notes cn
                LEFT JOIN users u ON cn.created_by = u.user_id
                WHERE cn.complaint_id = ?
                ORDER BY cn.created_at ASC";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt)
            return [];
        $stmt->bind_param('i', $complaintId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function getInformationRequests($complaintId)
    {
        $sql = "SELECT ir.*, u.username AS requested_by_name
                FROM information_requests ir
                LEFT JOIN users u ON ir.requested_by = u.user_id
                WHERE ir.complaint_id = ?
                ORDER BY ir.created_at ASC";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt)
            return [];
        $stmt->bind_param('i', $complaintId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function addCollaborationNote($complaintId, $createdByUserId, $note)
    {
        $stmt = $this->conn->prepare(
            "INSERT INTO collaboration_notes (complaint_id, created_by, note_text) VALUES (?, ?, ?)"
        );
        if (!$stmt)
            throw new Exception("DB error: " . $this->conn->error);
        $stmt->bind_param('iis', $complaintId, $createdByUserId, $note);
        if (!$stmt->execute())
            throw new Exception("Failed to save note.");
        $stmt->close();
        return true;
    }

    public function requestInformation($complaintId, $requestedByUserId, $question)
    {
        try {
            $this->conn->begin_transaction();

            $oldStmt = $this->conn->prepare(
                "SELECT complaint_status FROM complaints WHERE complaint_id = ? LIMIT 1"
            );
            $oldStmt->bind_param('i', $complaintId);
            $oldStmt->execute();
            $old = $oldStmt->get_result()->fetch_assoc();
            $oldStmt->close();
            $oldStatus = $old['complaint_status'] ?? STATUS_IN_PROGRESS;

            $insStmt = $this->conn->prepare(
                "INSERT INTO information_requests (complaint_id, requested_by, request_message) VALUES (?, ?, ?)"
            );
            $insStmt->bind_param('iis', $complaintId, $requestedByUserId, $question);
            $insStmt->execute();
            $insStmt->close();

            $updStmt = $this->conn->prepare(
                "UPDATE complaints SET complaint_status = 'awaiting_student_response' WHERE complaint_id = ?"
            );
            $updStmt->bind_param('i', $complaintId);
            $updStmt->execute();
            $updStmt->close();

            $logStmt = $this->conn->prepare(
                "INSERT INTO complaint_status_logs (complaint_id, action, old_status, new_status, performed_by, remarks)
                 VALUES (?, 'info_requested', ?, 'awaiting_student_response', ?, ?)"
            );
            $logStmt->bind_param('isis', $complaintId, $oldStatus, $requestedByUserId, $question);
            $logStmt->execute();
            $logStmt->close();

            $this->conn->commit();

            // Notify the student
            $studStmt = $this->conn->prepare(
                "SELECT u.user_id FROM complaints c
                 JOIN students s ON c.student_id = s.student_id
                 JOIN users u ON s.student_user_id = u.user_id
                 WHERE c.complaint_id = ? LIMIT 1"
            );
            $studStmt->bind_param('i', $complaintId);
            $studStmt->execute();
            $studRow = $studStmt->get_result()->fetch_assoc();
            $studStmt->close();
            if ($studRow) {
                (new Notification($this->conn))->create(
                    $studRow['user_id'],
                    "A staff member needs more information about your complaint #$complaintId. Please respond.",
                    'request_info',
                    "student_complaint_details.php?id=$complaintId",
                    $complaintId
                );
            }

            return true;
        } catch (Exception $e) {
            $this->conn->rollback();
            throw new Exception($e->getMessage());
        }
    }

    public function respondToComplaint($complaintId, $staffUserId, $response, $action)
    {
        $statusMap = [
            'resolve' => 'resolved',
            'reject' => 'rejected',
            STATUS_IN_PROGRESS => 'in_progress',
        ];
        if (!array_key_exists($action, $statusMap)) {
            throw new Exception("Invalid action.");
        }
        $newStatus = $statusMap[$action];

        try {
            $this->conn->begin_transaction();

            $oldStmt = $this->conn->prepare(
                "SELECT complaint_status FROM complaints WHERE complaint_id = ? LIMIT 1"
            );
            $oldStmt->bind_param('i', $complaintId);
            $oldStmt->execute();
            $old = $oldStmt->get_result()->fetch_assoc();
            $oldStmt->close();
            $oldStatus = $old['complaint_status'] ?? STATUS_PENDING;

            if ($newStatus === STATUS_RESOLVED) {
                $updStmt = $this->conn->prepare(
                    "UPDATE complaints SET complaint_status = ?, complaint_response = ?,
                     resolved_at = NOW(), updated_at = NOW() WHERE complaint_id = ?"
                );
            } else {
                $updStmt = $this->conn->prepare(
                    "UPDATE complaints SET complaint_status = ?, complaint_response = ?,
                     updated_at = NOW() WHERE complaint_id = ?"
                );
            }
            $updStmt->bind_param('ssi', $newStatus, $response, $complaintId);
            $updStmt->execute();
            $updStmt->close();

            $logStmt = $this->conn->prepare(
                "INSERT INTO complaint_status_logs (complaint_id, action, old_status, new_status, performed_by, remarks)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $logStmt->bind_param('isssis', $complaintId, $action, $oldStatus, $newStatus, $staffUserId, $response);
            $logStmt->execute();
            $logStmt->close();

            $closeStmt = $this->conn->prepare(
                "UPDATE information_requests SET status = 'closed' WHERE complaint_id = ? AND status = 'responded'"
            );
            $closeStmt->bind_param('i', $complaintId);
            $closeStmt->execute();
            $closeStmt->close();

            $this->conn->commit();

            // Notify the student
            $studStmt = $this->conn->prepare(
                "SELECT u.user_id FROM complaints c
                 JOIN students s ON c.student_id = s.student_id
                 JOIN users u ON s.student_user_id = u.user_id
                 WHERE c.complaint_id = ? LIMIT 1"
            );
            $studStmt->bind_param('i', $complaintId);
            $studStmt->execute();
            $studRow = $studStmt->get_result()->fetch_assoc();
            $studStmt->close();
            if ($studRow) {
                if ($newStatus === STATUS_RESOLVED) {
                    $msg = "Your complaint #$complaintId has been resolved.";
                    $type = 'complaint_resolved';
                } elseif ($newStatus === STATUS_REJECTED) {
                    $msg = "Your complaint #$complaintId has been rejected.";
                    $type = 'complaint_rejected';
                } else {
                    $msg = "Your complaint #$complaintId is being actively worked on.";
                    $type = 'status_change';
                }
                (new Notification($this->conn))->create(
                    $studRow['user_id'],
                    $msg,
                    $type,
                    "student_complaint_details.php?id=$complaintId",
                    $complaintId
                );
            }

            // Notify all staff who delegated this complaint downward
            if ($newStatus === STATUS_RESOLVED) {
                $delStmt = $this->conn->prepare(
                    "SELECT DISTINCT s.staff_user_id
                     FROM complaint_escalations ce
                     JOIN staffs s ON ce.from_staff_id = s.staff_id
                     WHERE ce.complaint_id = ? AND ce.type = 'delegation'"
                );
                $delStmt->bind_param('i', $complaintId);
                $delStmt->execute();
                $delegators = $delStmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $delStmt->close();
                $notif = new Notification($this->conn);
                foreach ($delegators as $d) {
                    $notif->create(
                        (int) $d['staff_user_id'],
                        "A complaint you delegated (#{$complaintId}) has been resolved.",
                        'complaint_delegated_resolved',
                        "assigned_complaint_details.php?id={$complaintId}",
                        $complaintId
                    );
                }
            }

            return true;
        } catch (Exception $e) {
            $this->conn->rollback();
            throw new Exception($e->getMessage());
        }
    }

    // Count complaints assigned to this staff where the student has responded to an info request
    public function getStudentRespondedCount($staffId)
    {
        $stmt = $this->conn->prepare(
            "SELECT COUNT(DISTINCT ir.complaint_id) AS cnt
             FROM information_requests ir
             JOIN complaint_assignments ca ON ir.complaint_id = ca.complaint_id
                 AND ca.staff_id = ? AND ca.status = 'active'
             WHERE ir.status = 'responded'"
        );
        $stmt->bind_param("s", $staffId);
        $stmt->execute();
        $cnt = (int) $stmt->get_result()->fetch_assoc()['cnt'];
        $stmt->close();
        return $cnt;
    }

    // Get lower-ranked staff eligible to receive a delegation
    public function getStaffForDelegation($fromStaffId)
    {
        $sql = "SELECT u.user_id, u.username, s.staff_id, d.department_name,
                       sr.role_name, sr.role_rank
                FROM staffs s
                JOIN users u ON s.staff_user_id = u.user_id
                LEFT JOIN departments d ON s.staff_department_id = d.department_id
                LEFT JOIN staff_roles sr ON s.staff_role_id = sr.role_id
                WHERE s.staff_approval_status = '1'
                  AND s.staff_id != ?
                  AND sr.role_rank < (
                      SELECT COALESCE(sr2.role_rank, 0)
                      FROM staffs s2
                      LEFT JOIN staff_roles sr2 ON s2.staff_role_id = sr2.role_id
                      WHERE s2.staff_id = ?
                  )
                ORDER BY sr.role_rank DESC, u.username ASC";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) return [];
        $stmt->bind_param('ss', $fromStaffId, $fromStaffId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // Delegate a complaint downward to a lower-ranked staff member
    public function delegateComplaint($complaintId, $fromStaffId, $toStaffId, $delegatedByUserId, $reason)
    {
        try {
            $this->conn->begin_transaction();

            // Block if already resolved or rejected
            $statusStmt = $this->conn->prepare(
                "SELECT complaint_status FROM complaints WHERE complaint_id = ? LIMIT 1"
            );
            $statusStmt->bind_param('i', $complaintId);
            $statusStmt->execute();
            $currentStatus = $statusStmt->get_result()->fetch_assoc()['complaint_status'] ?? '';
            $statusStmt->close();
            if (in_array($currentStatus, [STATUS_RESOLVED, STATUS_REJECTED])) {
                throw new Exception("Cannot delegate a complaint that is already $currentStatus.");
            }

            // Verify destination staff has a lower rank
            $rankSql = "SELECT
                            (SELECT COALESCE(sr.role_rank, 0) FROM staffs s JOIN staff_roles sr ON s.staff_role_id = sr.role_id WHERE s.staff_id = ?) AS from_rank,
                            (SELECT COALESCE(sr.role_rank, 0) FROM staffs s JOIN staff_roles sr ON s.staff_role_id = sr.role_id WHERE s.staff_id = ?) AS to_rank";
            $rankStmt = $this->conn->prepare($rankSql);
            $rankStmt->bind_param('ss', $fromStaffId, $toStaffId);
            $rankStmt->execute();
            $ranks = $rankStmt->get_result()->fetch_assoc();
            $rankStmt->close();

            if ((int) $ranks['to_rank'] >= (int) $ranks['from_rank']) {
                throw new Exception("Can only delegate to a staff member of lower rank.");
            }

            // Log the delegation
            $escStmt = $this->conn->prepare(
                "INSERT INTO complaint_escalations (complaint_id, from_staff_id, to_staff_id, forwarded_by, reason, type, status)
                 VALUES (?, ?, ?, ?, ?, 'delegation', 'pending')"
            );
            $escStmt->bind_param('issis', $complaintId, $fromStaffId, $toStaffId, $delegatedByUserId, $reason);
            $escStmt->execute();
            $escStmt->close();

            // Mark old assignment forwarded, create new lead assignment
            $fwdStmt = $this->conn->prepare(
                "UPDATE complaint_assignments SET status = 'forwarded', completed_at = NOW()
                 WHERE complaint_id = ? AND staff_id = ? AND status = 'active'"
            );
            $fwdStmt->bind_param('is', $complaintId, $fromStaffId);
            $fwdStmt->execute();
            $fwdStmt->close();

            $newAssignStmt = $this->conn->prepare(
                "INSERT INTO complaint_assignments (complaint_id, staff_id, assigned_by, is_lead, status, notes)
                 VALUES (?, ?, ?, 1, 'active', ?)"
            );
            $newAssignStmt->bind_param('isis', $complaintId, $toStaffId, $delegatedByUserId, $reason);
            $newAssignStmt->execute();
            $newAssignStmt->close();

            // Status log
            $logStmt = $this->conn->prepare(
                "INSERT INTO complaint_status_logs
                 (complaint_id, action, old_status, new_status, performed_by, remarks)
                 VALUES (?, 'delegated', ?, 'in_progress', ?, ?)"
            );
            $logStmt->bind_param('isis', $complaintId, $currentStatus, $delegatedByUserId, $reason);
            $logStmt->execute();
            $logStmt->close();

            $this->conn->commit();

            // Notify the receiving staff (outside transaction — non-critical)
            $toUserStmt = $this->conn->prepare(
                "SELECT staff_user_id FROM staffs WHERE staff_id = ? LIMIT 1"
            );
            $toUserStmt->bind_param('s', $toStaffId);
            $toUserStmt->execute();
            $toUser = $toUserStmt->get_result()->fetch_assoc();
            $toUserStmt->close();
            if ($toUser) {
                (new Notification($this->conn))->create(
                    (int) $toUser['staff_user_id'],
                    "Complaint #$complaintId has been delegated to you. Please handle it.",
                    'complaint_delegated',
                    "assigned_complaint_details.php?id=$complaintId",
                    $complaintId
                );
            }

            return true;
        } catch (Exception $e) {
            $this->conn->rollback();
            throw new Exception($e->getMessage());
        }
    }

    // Get student feedback for a resolved complaint
    public function getComplaintFeedback($complaintId)
    {
        $stmt = $this->conn->prepare(
            "SELECT cf.*, u.username AS student_name
             FROM complaint_feedback cf
             JOIN students s ON cf.student_id = s.student_id
             JOIN users u ON s.student_user_id = u.user_id
             WHERE cf.complaint_id = ?
             LIMIT 1"
        );
        $stmt->bind_param("i", $complaintId);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $data;
    }

    // On Hold / Resume

    public function putComplaintOnHold($complaintId, $staffUserId, $reason)
    {
        try {
            $this->conn->begin_transaction();

            $oldStmt = $this->conn->prepare(
                "SELECT complaint_status FROM complaints WHERE complaint_id = ? LIMIT 1"
            );
            $oldStmt->bind_param('i', $complaintId);
            $oldStmt->execute();
            $old = $oldStmt->get_result()->fetch_assoc();
            $oldStmt->close();
            $oldStatus = $old['complaint_status'] ?? '';

            if (in_array($oldStatus, [STATUS_RESOLVED, STATUS_REJECTED])) {
                throw new Exception("Cannot put a $oldStatus complaint on hold.");
            }

            $updStmt = $this->conn->prepare(
                "UPDATE complaints SET complaint_status = 'on_hold', hold_reason = ?, updated_at = NOW()
                 WHERE complaint_id = ?"
            );
            $updStmt->bind_param('si', $reason, $complaintId);
            $updStmt->execute();
            $updStmt->close();

            $logStmt = $this->conn->prepare(
                "INSERT INTO complaint_status_logs (complaint_id, action, old_status, new_status, performed_by, remarks)
                 VALUES (?, 'on_hold', ?, 'on_hold', ?, ?)"
            );
            $logStmt->bind_param('isis', $complaintId, $oldStatus, $staffUserId, $reason);
            $logStmt->execute();
            $logStmt->close();

            $this->conn->commit();

            // Notify student (outside transaction)
            $studStmt = $this->conn->prepare(
                "SELECT u.user_id FROM complaints c
                 JOIN students s ON c.student_id = s.student_id
                 JOIN users u ON s.student_user_id = u.user_id
                 WHERE c.complaint_id = ? LIMIT 1"
            );
            $studStmt->bind_param('i', $complaintId);
            $studStmt->execute();
            $studRow = $studStmt->get_result()->fetch_assoc();
            $studStmt->close();
            if ($studRow) {
                (new Notification($this->conn))->create(
                    (int) $studRow['user_id'],
                    "Your complaint #$complaintId has been put on hold: $reason",
                    'status_change',
                    "student_complaint_details.php?id=$complaintId",
                    $complaintId
                );
            }

            return true;
        } catch (Exception $e) {
            $this->conn->rollback();
            throw new Exception($e->getMessage());
        }
    }

    public function resumeComplaint($complaintId, $staffUserId)
    {
        try {
            $this->conn->begin_transaction();

            $oldStmt = $this->conn->prepare(
                "SELECT complaint_status FROM complaints WHERE complaint_id = ? LIMIT 1"
            );
            $oldStmt->bind_param('i', $complaintId);
            $oldStmt->execute();
            $old = $oldStmt->get_result()->fetch_assoc();
            $oldStmt->close();
            $oldStatus = $old['complaint_status'] ?? '';

            if ($oldStatus !== 'on_hold') {
                throw new Exception("Complaint is not on hold (current status: $oldStatus).");
            }

            $updStmt = $this->conn->prepare(
                "UPDATE complaints SET complaint_status = 'in_progress', hold_reason = NULL, updated_at = NOW()
                 WHERE complaint_id = ?"
            );
            $updStmt->bind_param('i', $complaintId);
            $updStmt->execute();
            $updStmt->close();

            $logStmt = $this->conn->prepare(
                "INSERT INTO complaint_status_logs (complaint_id, action, old_status, new_status, performed_by, remarks)
                 VALUES (?, 'resumed', 'on_hold', 'in_progress', ?, 'Complaint resumed from hold')"
            );
            $logStmt->bind_param('ii', $complaintId, $staffUserId);
            $logStmt->execute();
            $logStmt->close();

            $this->conn->commit();

            // Notify student (outside transaction)
            $studStmt = $this->conn->prepare(
                "SELECT u.user_id FROM complaints c
                 JOIN students s ON c.student_id = s.student_id
                 JOIN users u ON s.student_user_id = u.user_id
                 WHERE c.complaint_id = ? LIMIT 1"
            );
            $studStmt->bind_param('i', $complaintId);
            $studStmt->execute();
            $studRow = $studStmt->get_result()->fetch_assoc();
            $studStmt->close();
            if ($studRow) {
                (new Notification($this->conn))->create(
                    (int) $studRow['user_id'],
                    "Your complaint #$complaintId has been resumed and is now being actively worked on.",
                    'status_change',
                    "student_complaint_details.php?id=$complaintId",
                    $complaintId
                );
            }

            return true;
        } catch (Exception $e) {
            $this->conn->rollback();
            throw new Exception($e->getMessage());
        }
    }

    // Progress Updates 

    public function sendProgressUpdate($complaintId, $staffUserId, $message)
    {
        $insStmt = $this->conn->prepare(
            "INSERT INTO complaint_progress_updates (complaint_id, sent_by, message) VALUES (?, ?, ?)"
        );
        if (!$insStmt) throw new Exception("DB error: " . $this->conn->error);
        $insStmt->bind_param('iis', $complaintId, $staffUserId, $message);
        if (!$insStmt->execute()) throw new Exception("Failed to save progress update.");
        $insStmt->close();

        // Notify student
        $studStmt = $this->conn->prepare(
            "SELECT u.user_id FROM complaints c
             JOIN students s ON c.student_id = s.student_id
             JOIN users u ON s.student_user_id = u.user_id
             WHERE c.complaint_id = ? LIMIT 1"
        );
        $studStmt->bind_param('i', $complaintId);
        $studStmt->execute();
        $studRow = $studStmt->get_result()->fetch_assoc();
        $studStmt->close();
        if ($studRow) {
            (new Notification($this->conn))->create(
                (int) $studRow['user_id'],
                "Staff has sent a progress update on your complaint #$complaintId",
                'status_change',
                "student_complaint_details.php?id=$complaintId",
                $complaintId
            );
        }

        return true;
    }

    public function getProgressUpdates($complaintId)
    {
        $stmt = $this->conn->prepare(
            "SELECT pu.*, u.username AS sent_by_name
             FROM complaint_progress_updates pu
             LEFT JOIN users u ON pu.sent_by = u.user_id
             WHERE pu.complaint_id = ?
             ORDER BY pu.created_at ASC"
        );
        if (!$stmt) return [];
        $stmt->bind_param('i', $complaintId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // Reject Assignment 

    public function rejectAssignment($complaintId, $staffId, $staffUserId, $reason)
    {
        try {
            $this->conn->begin_transaction();

            $statusStmt = $this->conn->prepare(
                "SELECT complaint_status FROM complaints WHERE complaint_id = ? LIMIT 1"
            );
            $statusStmt->bind_param('i', $complaintId);
            $statusStmt->execute();
            $currentStatus = $statusStmt->get_result()->fetch_assoc()['complaint_status'] ?? '';
            $statusStmt->close();

            if (in_array($currentStatus, [STATUS_RESOLVED, STATUS_REJECTED])) {
                throw new Exception("Cannot reject an assignment for a complaint that is already $currentStatus.");
            }

            $updAssign = $this->conn->prepare(
                "UPDATE complaint_assignments
                 SET status = 'rejected', rejection_reason = ?, completed_at = NOW()
                 WHERE complaint_id = ? AND staff_id = ? AND status = 'active'"
            );
            $updAssign->bind_param('sis', $reason, $complaintId, $staffId);
            $updAssign->execute();
            $updAssign->close();

            $updComp = $this->conn->prepare(
                "UPDATE complaints SET complaint_status = 'pending', updated_at = NOW() WHERE complaint_id = ?"
            );
            $updComp->bind_param('i', $complaintId);
            $updComp->execute();
            $updComp->close();

            $logStmt = $this->conn->prepare(
                "INSERT INTO complaint_status_logs (complaint_id, action, old_status, new_status, performed_by, remarks)
                 VALUES (?, 'assignment_rejected', ?, 'pending', ?, ?)"
            );
            $logStmt->bind_param('isis', $complaintId, $currentStatus, $staffUserId, $reason);
            $logStmt->execute();
            $logStmt->close();

            $this->conn->commit();

            // Notify all admins (outside transaction)
            (new Notification($this->conn))->notifyAllAdmins(
                "Assignment for complaint #$complaintId was rejected by staff. Reason: $reason. Reassignment needed.",
                'new_assignment',
                "manage_complaints.php"
            );

            return true;
        } catch (Exception $e) {
            $this->conn->rollback();
            throw new Exception($e->getMessage());
        }
    }

    // Performance Stats

    public function getPerformanceStats($staffId)
    {
        $stmt = $this->conn->prepare(
            "SELECT
                COUNT(*) as total_assigned,
                SUM(CASE WHEN c.complaint_status = 'resolved' THEN 1 ELSE 0 END) as total_resolved,
                SUM(CASE WHEN c.complaint_status = 'resolved'
                         AND MONTH(c.resolved_at) = MONTH(NOW())
                         AND YEAR(c.resolved_at) = YEAR(NOW()) THEN 1 ELSE 0 END) as resolved_this_month,
                ROUND(AVG(CASE WHEN c.complaint_status = 'resolved'
                               THEN DATEDIFF(c.resolved_at, ca.assigned_at) END), 1) as avg_resolution_days,
                ROUND(SUM(CASE WHEN c.complaint_status = 'resolved' THEN 1 ELSE 0 END)
                      / NULLIF(COUNT(*), 0) * 100, 1) as resolution_rate
             FROM complaints c
             JOIN complaint_assignments ca ON c.complaint_id = ca.complaint_id AND ca.staff_id = ?"
        );
        if (!$stmt) return $this->_defaultPerformanceStats();
        $stmt->bind_param('s', $staffId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $ratingStmt = $this->conn->prepare(
            "SELECT ROUND(AVG(cf.rating), 1) as avg_rating
             FROM complaint_feedback cf
             JOIN complaints c ON cf.complaint_id = c.complaint_id
             JOIN complaint_assignments ca ON c.complaint_id = ca.complaint_id AND ca.staff_id = ?"
        );
        $avgRating = null;
        if ($ratingStmt) {
            $ratingStmt->bind_param('s', $staffId);
            $ratingStmt->execute();
            $ratingRow = $ratingStmt->get_result()->fetch_assoc();
            $ratingStmt->close();
            $avgRating = $ratingRow['avg_rating'] ?? null;
        }

        return [
            'total_assigned'      => (int) ($row['total_assigned'] ?? 0),
            'total_resolved'      => (int) ($row['total_resolved'] ?? 0),
            'resolved_this_month' => (int) ($row['resolved_this_month'] ?? 0),
            'avg_resolution_days' => $row['avg_resolution_days'] ?? null,
            'resolution_rate'     => $row['resolution_rate'] ?? null,
            'avg_rating'          => $avgRating,
        ];
    }

    private function _defaultPerformanceStats()
    {
        return [
            'total_assigned'      => 0,
            'total_resolved'      => 0,
            'resolved_this_month' => 0,
            'avg_resolution_days' => null,
            'resolution_rate'     => null,
            'avg_rating'          => null,
        ];
    }

    // Target Resolution Date 

    public function setTargetDate($complaintId, $staffId, $targetDate)
    {
        if ($targetDate < date('Y-m-d')) {
            throw new Exception("Target date cannot be in the past.");
        }

        $stmt = $this->conn->prepare(
            "UPDATE complaint_assignments
             SET target_resolution_date = ?
             WHERE complaint_id = ? AND staff_id = ? AND status = 'active'"
        );
        if (!$stmt) throw new Exception("DB error: " . $this->conn->error);
        $stmt->bind_param('sis', $targetDate, $complaintId, $staffId);
        if (!$stmt->execute()) throw new Exception("Failed to set target date.");
        $stmt->close();
        return true;
    }

    // Department-Level View 

    public function getDepartmentComplaints($departmentId)
    {
        $sql = "SELECT c.complaint_id, c.complaint_title, c.complaint_status, c.created_at, c.is_anonymous,
                       cc.category_name,
                       u.username AS student_name,
                       su.username AS assigned_staff_name,
                       sr.role_name AS staff_role
                FROM complaints c
                LEFT JOIN complaint_categories cc ON c.category_id = cc.category_id
                JOIN students s ON c.student_id = s.student_id
                JOIN users u ON s.student_user_id = u.user_id
                LEFT JOIN complaint_assignments ca ON c.complaint_id = ca.complaint_id AND ca.status = 'active'
                LEFT JOIN staffs st ON ca.staff_id = st.staff_id
                LEFT JOIN users su ON st.staff_user_id = su.user_id
                LEFT JOIN staff_roles sr ON st.staff_role_id = sr.role_id
                WHERE c.department_id = ?
                ORDER BY c.created_at DESC";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) return [];
        $stmt->bind_param('i', $departmentId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function getDepartmentStats($departmentId)
    {
        $stmt = $this->conn->prepare(
            "SELECT
                COUNT(*) as total,
                SUM(CASE WHEN complaint_status = 'pending'     THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN complaint_status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                SUM(CASE WHEN complaint_status = 'on_hold'     THEN 1 ELSE 0 END) as on_hold,
                SUM(CASE WHEN complaint_status = 'resolved'    THEN 1 ELSE 0 END) as resolved,
                SUM(CASE WHEN complaint_status = 'rejected'    THEN 1 ELSE 0 END) as rejected
             FROM complaints WHERE department_id = ?"
        );
        if (!$stmt) return [];
        $stmt->bind_param('i', $departmentId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

}
