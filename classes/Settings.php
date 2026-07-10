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

    public function updateSecurity(
        $maxLoginAttempts,
        $lockoutDurationMinutes,
        $maxAttachmentSizeMb,
        array $allowedAttachmentTypes,
        $passwordMinLength,
        $passwordRequireUpper,
        $passwordRequireLower,
        $passwordRequireNumber,
        $passwordRequireSpecial,
        $sessionTimeoutMinutes,
        $ipRateLimitAttempts,
        $ipRateLimitWindowMinutes,
        $passwordResetTokenHours,
        $updatedBy
    ) {
        $this->get();
        $allowedTypes = implode(',', $allowedAttachmentTypes);
        $stmt = $this->conn->prepare(
            "UPDATE system_settings SET
                max_login_attempts = ?, lockout_duration_minutes = ?, max_attachment_size_mb = ?,
                allowed_attachment_types = ?, password_min_length = ?, password_require_upper = ?,
                password_require_lower = ?, password_require_number = ?, password_require_special = ?,
                session_timeout_minutes = ?, ip_rate_limit_attempts = ?, ip_rate_limit_window_minutes = ?,
                password_reset_token_hours = ?, updated_by = ?
             WHERE id = 1"
        );
        $stmt->bind_param(
            "iiisiiiiiiiiii",
            $maxLoginAttempts,
            $lockoutDurationMinutes,
            $maxAttachmentSizeMb,
            $allowedTypes,
            $passwordMinLength,
            $passwordRequireUpper,
            $passwordRequireLower,
            $passwordRequireNumber,
            $passwordRequireSpecial,
            $sessionTimeoutMinutes,
            $ipRateLimitAttempts,
            $ipRateLimitWindowMinutes,
            $passwordResetTokenHours,
            $updatedBy
        );
        $stmt->execute();
        $stmt->close();
    }

    // Throws with a user-facing message if $password violates the configured policy
    public function validatePassword(string $password): void
    {
        $s = $this->get();
        $minLen = (int) ($s['password_min_length'] ?? 8);

        if (strlen($password) < $minLen) {
            throw new Exception("Password must be at least {$minLen} characters long.");
        }
        if (!empty($s['password_require_upper']) && !preg_match('/[A-Z]/', $password)) {
            throw new Exception("Password must contain at least one uppercase letter.");
        }
        if (!empty($s['password_require_lower']) && !preg_match('/[a-z]/', $password)) {
            throw new Exception("Password must contain at least one lowercase letter.");
        }
        if (!empty($s['password_require_number']) && !preg_match('/[0-9]/', $password)) {
            throw new Exception("Password must contain at least one number.");
        }
        if (!empty($s['password_require_special']) && !preg_match('/[\W]/', $password)) {
            throw new Exception("Password must contain at least one special character.");
        }
    }

    // Canonical mime type -> [label, extensions] catalog. Single source of truth for
    // the settings.php checkboxes and every attachment upload form's hint text/JS validation.
    private static array $attachmentTypeCatalog = [
        'application/pdf' => ['label' => 'PDF', 'extensions' => ['.pdf']],
        'image/jpeg'       => ['label' => 'JPG/JPEG', 'extensions' => ['.jpg', '.jpeg']],
        'image/png'        => ['label' => 'PNG', 'extensions' => ['.png']],
    ];

    public static function getAttachmentTypeCatalog(): array
    {
        return self::$attachmentTypeCatalog;
    }

    // Returns [mime_type, ...] whitelist for complaint attachments
    public function getAllowedAttachmentTypes(): array
    {
        $s = $this->get();
        return array_filter(array_map('trim', explode(',', $s['allowed_attachment_types'] ?? '')));
    }

    // Returns the max attachment size in bytes
    public function getMaxAttachmentSizeBytes(): int
    {
        $s = $this->get();
        return (int) ($s['max_attachment_size_mb'] ?? 5) * 1024 * 1024;
    }

    // Returns the max attachment size in whole MB, for display
    public function getMaxAttachmentSizeMb(): int
    {
        $s = $this->get();
        return (int) ($s['max_attachment_size_mb'] ?? 5);
    }

    // Returns file extensions (e.g. ['.pdf', '.jpg', '.jpeg', '.png']) matching the configured mime whitelist
    public function getAllowedAttachmentExtensions(): array
    {
        $extensions = [];
        foreach ($this->getAllowedAttachmentTypes() as $mime) {
            $extensions = array_merge($extensions, self::$attachmentTypeCatalog[$mime]['extensions'] ?? []);
        }
        return $extensions;
    }

    // Returns a human-readable label list, e.g. "PDF, JPG/JPEG, PNG"
    public function getAllowedAttachmentLabel(): string
    {
        $labels = [];
        foreach ($this->getAllowedAttachmentTypes() as $mime) {
            $labels[] = self::$attachmentTypeCatalog[$mime]['label'] ?? $mime;
        }
        return implode(', ', $labels);
    }

    // Minutes of inactivity before a logged-in session expires
    public function getSessionTimeoutMinutes(): int
    {
        $s = $this->get();
        return (int) ($s['session_timeout_minutes'] ?? 30);
    }

    // Max failed login attempts allowed from a single IP within the rate-limit window
    public function getIpRateLimitAttempts(): int
    {
        $s = $this->get();
        return (int) ($s['ip_rate_limit_attempts'] ?? 10);
    }

    // Rolling window (minutes) over which IP login attempts are counted
    public function getIpRateLimitWindowMinutes(): int
    {
        $s = $this->get();
        return (int) ($s['ip_rate_limit_window_minutes'] ?? 15);
    }

    // Hours a password-reset link stays valid before expiring
    public function getPasswordResetTokenHours(): int
    {
        $s = $this->get();
        return (int) ($s['password_reset_token_hours'] ?? 1);
    }
}
