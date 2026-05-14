<?php
class Student extends User
{

    private $conn;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    // Get student id
    public function getStudentId($userId)
    {
        $stmt = $this->conn->prepare("SELECT student_id FROM students WHERE student_user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row    = $result->fetch_assoc();
        $stmt->close();
        return $row ? $row['student_id'] : null;
    }

    // Get total complaints by student
    public function getTotalComplaints($studentId)
    {
        $stmt = $this->conn->prepare("SELECT COUNT(*) as total FROM complaints WHERE student_id = ?");
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc()['total'];
    }

    public function getTotalPending($studentId)
    {
        $stmt = $this->conn->prepare("SELECT COUNT(*) as total FROM complaints WHERE student_id = ? AND complaint_status = 'pending'");
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc()['total'];
    }

    public function getTotalInprogress($studentId)
    {
        $stmt = $this->conn->prepare("SELECT COUNT(*) as total FROM complaints WHERE student_id = ? AND complaint_status = 'in_progress'");
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc()['total'];
    }

    public function getTotalResolved($studentId)
    {
        $stmt = $this->conn->prepare("SELECT COUNT(*) as total FROM complaints WHERE student_id = ? AND complaint_status = 'resolved'");
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc()['total'];
    }

    // Get complaints list for dashboard overview
    public function getStudentComplaints($studentId)
    {
        $stmt = $this->conn->prepare(
            "SELECT c.*, cc.category_name
             FROM complaints c
             JOIN complaint_categories cc ON c.category_id = cc.category_id
             WHERE c.student_id = ?
             ORDER BY c.created_at DESC"
        );
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $data;
    }

    // Get details of a specific complaint (ownership enforced by caller)
    public function readStudentComplaint($complaintId)
    {
        $stmt = $this->conn->prepare(
            "SELECT c.*, u.username, d.department_name, cc.category_name
             FROM complaints c
             LEFT JOIN students s ON c.student_id = s.student_id
             JOIN users u ON u.user_id = s.student_user_id
             LEFT JOIN departments d ON c.department_id = d.department_id
             LEFT JOIN complaint_categories cc ON c.category_id = cc.category_id
             WHERE c.complaint_id = ?"
        );
        $stmt->bind_param("i", $complaintId);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $data;
    }

    // Complaint status history — all actors (admin, staff, student)
    public function readStudentComplaintHistory($complaintId)
    {
        $stmt = $this->conn->prepare(
            "SELECT csl.*, u.username
             FROM complaint_status_logs csl
             LEFT JOIN users u ON csl.performed_by = u.user_id
             WHERE csl.complaint_id = ?
             ORDER BY csl.changed_at ASC"
        );
        $stmt->bind_param("i", $complaintId);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $data;
    }

    // Complaint attachments
    public function readStudentComplaintAttachments($complaintId)
    {
        $stmt = $this->conn->prepare(
            "SELECT * FROM complaint_attachments WHERE complaint_id = ? ORDER BY uploaded_at ASC"
        );
        $stmt->bind_param("i", $complaintId);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $data;
    }

    // Information requests for a complaint
    public function readStudentComplaintInfoRequests($complaintId)
    {
        $stmt = $this->conn->prepare(
            "SELECT ir.*, u.username
             FROM information_requests ir
             JOIN users u ON ir.requested_by = u.user_id
             WHERE ir.complaint_id = ?
             ORDER BY ir.created_at ASC"
        );
        $stmt->bind_param("i", $complaintId);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $data;
    }

    // Student submits a response to an information request
    public function respondToInfoRequest($requestId, $complaintId, $studentId, $response)
    {
        try {
            $this->conn->begin_transaction();

            // Verify this request belongs to a complaint owned by this student
            $checkStmt = $this->conn->prepare(
                "SELECT ir.request_id FROM information_requests ir
                 JOIN complaints c ON ir.complaint_id = c.complaint_id
                 WHERE ir.request_id = ? AND ir.complaint_id = ? AND c.student_id = ? AND ir.status = 'pending'"
            );
            $checkStmt->bind_param("iii", $requestId, $complaintId, $studentId);
            $checkStmt->execute();
            if ($checkStmt->get_result()->num_rows === 0) {
                throw new Exception("Invalid request or already responded.");
            }
            $checkStmt->close();

            // Save the student response
            $updStmt = $this->conn->prepare(
                "UPDATE information_requests SET student_response = ?, status = 'responded' WHERE request_id = ?"
            );
            $updStmt->bind_param("si", $response, $requestId);
            $updStmt->execute();
            $updStmt->close();

            // If no more pending requests for this complaint, revert status to in_progress
            $pendingStmt = $this->conn->prepare(
                "SELECT COUNT(*) AS cnt FROM information_requests WHERE complaint_id = ? AND status = 'pending'"
            );
            $pendingStmt->bind_param("i", $complaintId);
            $pendingStmt->execute();
            $remaining = (int)$pendingStmt->get_result()->fetch_assoc()['cnt'];
            $pendingStmt->close();

            if ($remaining === 0) {
                $revertStmt = $this->conn->prepare(
                    "UPDATE complaints SET complaint_status = 'in_progress'
                     WHERE complaint_id = ? AND complaint_status = 'awaiting_student_response'"
                );
                $revertStmt->bind_param("i", $complaintId);
                $revertStmt->execute();
                $revertStmt->close();
            }

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollback();
            throw new Exception($e->getMessage());
        }
    }

    // Submit feedback for a resolved complaint
    public function submitFeedback($complaintId, $studentId, $rating, $feedbackText)
    {
        // Only allow feedback for resolved complaints owned by this student
        $checkStmt = $this->conn->prepare(
            "SELECT complaint_id FROM complaints
             WHERE complaint_id = ? AND student_id = ? AND complaint_status = 'resolved'"
        );
        $checkStmt->bind_param("ii", $complaintId, $studentId);
        $checkStmt->execute();
        if ($checkStmt->get_result()->num_rows === 0) {
            throw new Exception("Feedback can only be submitted for your resolved complaints.");
        }
        $checkStmt->close();

        // Check not already submitted
        $dupStmt = $this->conn->prepare(
            "SELECT feedback_id FROM complaint_feedback WHERE complaint_id = ? AND student_id = ?"
        );
        $dupStmt->bind_param("ii", $complaintId, $studentId);
        $dupStmt->execute();
        if ($dupStmt->get_result()->num_rows > 0) {
            throw new Exception("You have already submitted feedback for this complaint.");
        }
        $dupStmt->close();

        $stmt = $this->conn->prepare(
            "INSERT INTO complaint_feedback (complaint_id, student_id, rating, feedback_text) VALUES (?, ?, ?, ?)"
        );
        $stmt->bind_param("iiis", $complaintId, $studentId, $rating, $feedbackText);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    // Check if student already submitted feedback for a complaint
    public function getComplaintFeedback($complaintId, $studentId)
    {
        $stmt = $this->conn->prepare(
            "SELECT * FROM complaint_feedback WHERE complaint_id = ? AND student_id = ? LIMIT 1"
        );
        $stmt->bind_param("ii", $complaintId, $studentId);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $data;
    }

    // Get filtered complaints with status tabs support
    public function getFilteredComplaints($studentId, $filter = 'all')
    {
        $where  = "WHERE c.student_id = ?";
        $types  = "i";
        $params = [$studentId];

        $validStatuses = ['pending', 'in_progress', 'resolved', 'rejected', 'awaiting_student_response'];
        if ($filter !== 'all' && in_array($filter, $validStatuses)) {
            $where   .= " AND c.complaint_status = ?";
            $types   .= "s";
            $params[] = $filter;
        }

        $sql = "SELECT c.*, d.department_name, cc.category_name,
                    (SELECT COUNT(*) FROM information_requests
                     WHERE complaint_id = c.complaint_id AND status = 'pending') AS pending_requests
                FROM complaints c
                LEFT JOIN departments d ON c.department_id = d.department_id
                LEFT JOIN complaint_categories cc ON c.category_id = cc.category_id
                $where
                ORDER BY FIELD(c.complaint_status, 'awaiting_student_response', 'pending', 'in_progress', 'resolved', 'rejected'),
                         c.created_at DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $data;
    }

    // Count complaints per status for tab badges
    public function getComplaintCounts($studentId)
    {
        $counts   = [];
        $statuses = ['all', 'awaiting_student_response', 'pending', 'in_progress', 'resolved', 'rejected'];

        foreach ($statuses as $status) {
            if ($status === 'all') {
                $stmt = $this->conn->prepare("SELECT COUNT(*) AS cnt FROM complaints WHERE student_id = ?");
                $stmt->bind_param("i", $studentId);
            } else {
                $stmt = $this->conn->prepare("SELECT COUNT(*) AS cnt FROM complaints WHERE student_id = ? AND complaint_status = ?");
                $stmt->bind_param("is", $studentId, $status);
            }
            $stmt->execute();
            $counts[$status] = (int)$stmt->get_result()->fetch_assoc()['cnt'];
            $stmt->close();
        }

        return $counts;
    }

    // Count pending info requests that need a student response
    public function getPendingInfoRequestsCount($studentId)
    {
        $stmt = $this->conn->prepare(
            "SELECT COUNT(DISTINCT c.complaint_id) AS cnt
             FROM complaints c
             JOIN information_requests ir ON ir.complaint_id = c.complaint_id
             WHERE c.student_id = ? AND ir.status = 'pending'"
        );
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        $cnt = (int)$stmt->get_result()->fetch_assoc()['cnt'];
        $stmt->close();
        return $cnt;
    }

    // Delete a pending complaint (only allowed while status is pending)
    public function deleteComplaint($complaintId, $studentId)
    {
        $stmt = $this->conn->prepare(
            "DELETE FROM complaints WHERE complaint_id = ? AND student_id = ? AND complaint_status = 'pending'"
        );
        $stmt->bind_param("ii", $complaintId, $studentId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok && $this->conn->affected_rows > 0;
    }
}
