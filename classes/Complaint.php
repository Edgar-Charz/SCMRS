<?php
class Complaint
{
    private $conn;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    // Create a new complaint
    public function createComplaint($title, $description, $category_id, $department_id, $is_anonymous, $student_id, $user_id, $subcategory_id = null)
    {
        try {
            // Start transaction
            $this->conn->begin_transaction();

            // Check empty fields
            if (empty($title) || empty($description) || empty($category_id) || empty($student_id)) {
                throw new Exception("Fill all the required fields");
            }

            // Insert a new complaint
            $insertComplaintStmt = $this->conn->prepare("INSERT INTO complaints (student_id, category_id, subcategory_id, department_id, complaint_title, complaint_description, is_anonymous)
                                                        VALUES(?, ?, ?, ?, ?, ?, ?)");

            if (!$insertComplaintStmt) {
                throw new Exception("Database error");
            }

            $subcategoryVal = !empty($subcategory_id) ? (int)$subcategory_id : null;
            $insertComplaintStmt->bind_param("iiiisss", $student_id, $category_id, $subcategoryVal, $department_id, $title, $description, $is_anonymous);
            if (!$insertComplaintStmt->execute()) {
                throw new Exception("Failed to create new complaint." . $insertComplaintStmt->error);
            }

            $complaintId = $insertComplaintStmt->insert_id;
            $insertComplaintStmt->close();

            // Handle file attachments
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
            $max_size = 5 * 1024 * 1024;

            if (!empty($_FILES['attachments']['name'][0])) {
                $upload_dir = 'uploads/complaints/' . $complaintId . '/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                foreach ($_FILES['attachments']['tmp_name'] as $key => $tmp_name) {
                    if ($_FILES['attachments']['error'][$key] !== UPLOAD_ERR_OK) {
                        continue;
                    }
                    $file_type = $_FILES['attachments']['type'][$key];
                    $file_size = $_FILES['attachments']['size'][$key];
                    if (!in_array($file_type, $allowed_types) || $file_size > $max_size) {
                        continue;
                    }
                    $file_name   = $_FILES['attachments']['name'][$key];
                    $unique_name = uniqid() . '_' . time() . '_' . $file_name;
                    $target_path = $upload_dir . $unique_name;

                    if (move_uploaded_file($tmp_name, $target_path)) {
                        $attach_stmt = $this->conn->prepare(
                            "INSERT INTO complaint_attachments (complaint_id, uploaded_by, file_name, file_path, file_type, file_size) VALUES (?, ?, ?, ?, ?, ?)"
                        );
                        $attach_stmt->bind_param("issssi", $complaintId, $user_id, $file_name, $target_path, $file_type, $file_size);
                        $attach_stmt->execute();
                        $attach_stmt->close();
                    }
                }
            }

            // Log the complaint creation in history
            $history_stmt = $this->conn->prepare("INSERT INTO complaint_status_logs (complaint_id, action, new_status, performed_by, remarks) 
                                                    VALUES (?, 'submitted', 'pending', ?, ?)");

            if (!$history_stmt) {
                throw new Exception("Database error" . $this->conn->error);
            }
            $history_note = $is_anonymous ? 'Complaint submitted anonymously' : 'Complaint submitted';
            $history_stmt->bind_param("iss", $complaintId, $user_id, $history_note);
            if (!$history_stmt->execute()) {
                throw new Exception("Failed to insert complaint logs" . $history_stmt->error);
            }
            $history_stmt->close();

            // Commit transaction
            $this->conn->commit();

            return $complaintId;
        } catch (Exception $e) {
            // Rollback transaction
            $this->conn->rollBack();

            throw new Exception($e->getMessage());
        }
    }


    // Get complaint categories
    public function getComplaintCategories()
    {
        return $this->conn->query("SELECT * FROM complaint_categories ORDER BY category_name");
    }

    // Get subcategories for a given category
    public function getSubcategoriesByCategoryId($categoryId)
    {
        $stmt = $this->conn->prepare(
            "SELECT subcategory_id, subcategory_name
             FROM complaint_subcategories
             WHERE category_id = ? AND status = 'active'
             ORDER BY subcategory_name ASC"
        );
        $stmt->bind_param("i", $categoryId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}
