<?php
class Settings
{
    private $conn;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    // Fetch the single settings row, self-healing (inserting the default row) if missing.
    public function get(): array
    {
        $row = $this->conn->query("SELECT * FROM system_settings WHERE id = 1 LIMIT 1")->fetch_assoc();
        if (!$row) {
            $this->conn->query("INSERT IGNORE INTO system_settings (id) VALUES (1)");
            $row = $this->conn->query("SELECT * FROM system_settings WHERE id = 1 LIMIT 1")->fetch_assoc();
        }
        return $row;
    }

    public function updateEmail(
        $host,
        $port,
        $username,
        $password,
        $encryption,
        $fromEmail,
        $fromName,
        $appUrl,
        $maxAttempts,
        $batchSize,
        $updatedBy
    ) {
        $this->get(); // ensure the row exists before updating
        $stmt = $this->conn->prepare(
            "UPDATE system_settings SET
                smtp_host = ?, smtp_port = ?, smtp_username = ?, smtp_password = ?, smtp_encryption = ?,
                from_email = ?, from_name = ?, app_url = ?, email_max_attempts = ?, email_batch_size = ?,
                updated_by = ?
             WHERE id = 1"
        );
        $stmt->bind_param(
            "sissssssiii",
            $host,
            $port,
            $username,
            $password,
            $encryption,
            $fromEmail,
            $fromName,
            $appUrl,
            $maxAttempts,
            $batchSize,
            $updatedBy
        );
        $stmt->execute();
        $stmt->close();
    }

    public function updateRouting($slaHighDays, $slaMediumDays, $slaLowDays, $updatedBy)
    {
        $this->get();
        $stmt = $this->conn->prepare(
            "UPDATE system_settings SET
                sla_high_days = ?, sla_medium_days = ?, sla_low_days = ?, updated_by = ?
             WHERE id = 1"
        );
        $stmt->bind_param("iiii", $slaHighDays, $slaMediumDays, $slaLowDays, $updatedBy);
        $stmt->execute();
        $stmt->close();
    }

    public function updateBranding($institutionName, $logoPath, $contactEmail, $updatedBy)
    {
        $this->get();
        $stmt = $this->conn->prepare(
            "UPDATE system_settings SET
                institution_name = ?, institution_logo_path = ?, institution_contact_email = ?, updated_by = ?
             WHERE id = 1"
        );
        $stmt->bind_param("sssi", $institutionName, $logoPath, $contactEmail, $updatedBy);
        $stmt->execute();
        $stmt->close();
    }
}
