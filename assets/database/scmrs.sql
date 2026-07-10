-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 02, 2026 at 08:50 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `scmrs`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `log_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `target_type` varchar(50) NOT NULL,
  `target_id` int(11) DEFAULT NULL,
  `target_name` varchar(255) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `collaboration_notes`
--

CREATE TABLE `collaboration_notes` (
  `note_id` int(11) NOT NULL,
  `complaint_id` int(11) NOT NULL,
  `created_by` int(11) NOT NULL,
  `note_text` text NOT NULL,
  `is_internal` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=internal (staff only), 0=visible to student',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `colleges`
--

CREATE TABLE `colleges` (
  `college_id` int(11) NOT NULL,
  `college_name` varchar(150) NOT NULL,
  `college_shortcode` varchar(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `complaints`
--

CREATE TABLE `complaints` (
  `complaint_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `subcategory_id` int(11) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `preferred_staff_id` varchar(20) DEFAULT NULL,
  `complaint_title` varchar(200) NOT NULL,
  `complaint_description` text NOT NULL,
  `priority` enum('low','medium','high') DEFAULT 'medium',
  `complaint_status` enum('pending','in_progress','awaiting_student_response','resolved','rejected','reopened','on_hold','deleted') NOT NULL DEFAULT 'pending',
  `escalation_level` tinyint(1) NOT NULL DEFAULT 1,
  `hold_reason` text DEFAULT NULL,
  `is_anonymous` tinyint(1) DEFAULT 0,
  `complaint_response` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `routed_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `resolved_at` timestamp NULL DEFAULT NULL,
  `closed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `complaint_assignments`
--

CREATE TABLE `complaint_assignments` (
  `assignment_id` int(11) NOT NULL,
  `complaint_id` int(11) NOT NULL,
  `staff_id` varchar(20) NOT NULL,
  `assigned_by` int(11) DEFAULT NULL COMMENT 'users.user_id of admin or escalating staff; NULL = auto-assigned by the system',
  `is_lead` tinyint(1) DEFAULT 1 COMMENT '1=primary handler for this complaint',
  `status` enum('active','forwarded','completed','rejected') NOT NULL DEFAULT 'active',
  `rejection_reason` text DEFAULT NULL,
  `target_resolution_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL,
  `overdue_notified` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `complaint_attachments`
--

CREATE TABLE `complaint_attachments` (
  `attachment_id` int(11) NOT NULL,
  `complaint_id` int(11) NOT NULL,
  `uploaded_by` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_type` varchar(100) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `complaint_categories`
--

CREATE TABLE `complaint_categories` (
  `category_id` int(11) NOT NULL,
  `category_name` varchar(150) NOT NULL,
  `category_description` text DEFAULT NULL,
  `requires_department_selection` tinyint(1) DEFAULT 0,
  `leader_endorsable` tinyint(1) NOT NULL DEFAULT 0,
  `auto_assign_department_id` int(11) DEFAULT NULL,
  `default_role_id` int(11) DEFAULT NULL,
  `level2_role_id` int(11) DEFAULT NULL,
  `level3_role_id` int(11) DEFAULT NULL,
  `default_priority` enum('low','medium','high') NOT NULL DEFAULT 'medium',
  `created_by` int(11) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `complaint_endorsements`
--

CREATE TABLE `complaint_endorsements` (
  `id` int(11) NOT NULL,
  `complaint_id` int(11) NOT NULL,
  `leader_id` int(11) NOT NULL,
  `note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `complaint_escalations`
--

CREATE TABLE `complaint_escalations` (
  `escalation_id` int(11) NOT NULL,
  `complaint_id` int(11) NOT NULL,
  `from_staff_id` varchar(20) NOT NULL COMMENT 'staffs.staff_id of the forwarding staff',
  `to_staff_id` varchar(20) NOT NULL COMMENT 'staffs.staff_id of the receiving staff',
  `forwarded_by` int(11) NOT NULL COMMENT 'users.user_id',
  `reason` text NOT NULL,
  `type` enum('escalation','delegation') NOT NULL DEFAULT 'escalation',
  `status` enum('pending','accepted','declined','resolved') DEFAULT 'pending',
  `escalated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `resolved_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `complaint_feedback`
--

CREATE TABLE `complaint_feedback` (
  `feedback_id` int(11) NOT NULL,
  `complaint_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `rating` tinyint(1) NOT NULL COMMENT '1-5 rating',
  `feedback_text` text DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `complaint_progress_updates`
--

CREATE TABLE `complaint_progress_updates` (
  `update_id` int(11) NOT NULL,
  `complaint_id` int(11) NOT NULL,
  `sent_by` int(11) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `complaint_status_logs`
--

CREATE TABLE `complaint_status_logs` (
  `log_id` int(11) NOT NULL,
  `complaint_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `old_status` varchar(50) DEFAULT NULL,
  `new_status` varchar(50) DEFAULT NULL,
  `performed_by` int(11) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `complaint_subcategories`
--

CREATE TABLE `complaint_subcategories` (
  `subcategory_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `subcategory_name` varchar(150) NOT NULL,
  `subcategory_description` text DEFAULT NULL,
  `leader_endorsable` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('active','inactive') DEFAULT 'active',
  `default_role_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `department_id` int(11) NOT NULL,
  `department_college_id` int(11) DEFAULT NULL,
  `department_name` varchar(150) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `email_queue`
--

CREATE TABLE `email_queue` (
  `id` int(11) NOT NULL,
  `to_email` varchar(255) NOT NULL,
  `to_name` varchar(255) NOT NULL DEFAULT '',
  `subject` varchar(500) NOT NULL,
  `body` mediumtext NOT NULL,
  `status` enum('pending','sent','failed') NOT NULL DEFAULT 'pending',
  `attempts` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `last_attempted_at` timestamp NULL DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `error_msg` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `information_requests`
--

CREATE TABLE `information_requests` (
  `request_id` int(11) NOT NULL,
  `complaint_id` int(11) NOT NULL,
  `requested_by` int(11) NOT NULL,
  `request_message` text NOT NULL,
  `status` enum('pending','responded','closed') NOT NULL DEFAULT 'pending',
  `student_response` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `responded_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `login_rate_limits`
--

CREATE TABLE `login_rate_limits` (
  `id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `attempted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `complaint_id` int(11) DEFAULT NULL,
  `message` varchar(255) NOT NULL,
  `type` enum('status_change','new_assignment','request_info','new_complaint','new_registration','staff_approved','info_responded','complaint_rejected','complaint_resolved','staff_rejected','complaint_deleted','complaint_reopened','complaint_delegated','complaint_delegated_resolved','complaint_overdue','new_complaint_in_rep_scope','endorsed_complaint_updated','system','password_reset_admin','filed_on_behalf') NOT NULL DEFAULT 'status_change',
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `email` varchar(150) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staffs`
--

CREATE TABLE `staffs` (
  `staff_id` varchar(20) NOT NULL,
  `staff_user_id` int(11) NOT NULL,
  `staff_department_id` int(11) DEFAULT NULL,
  `staff_role_id` int(11) DEFAULT NULL,
  `staff_approval_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0=Unapproved, 1=Approved, 2=Disapproved',
  `staff_approved_by` int(11) DEFAULT NULL,
  `staff_approved_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_roles`
--

CREATE TABLE `staff_roles` (
  `role_id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL,
  `role_rank` tinyint(4) NOT NULL COMMENT 'Higher = more senior',
  `is_department_scoped` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 = one holder per department; 0 = single university-wide holder',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `student_id` int(11) NOT NULL,
  `student_user_id` int(11) NOT NULL,
  `student_registration_number` varchar(50) NOT NULL,
  `student_program` varchar(150) DEFAULT NULL,
  `student_college_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_rep_departments`
--

CREATE TABLE `student_rep_departments` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `department_id` int(11) NOT NULL,
  `assigned_by` int(11) NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL DEFAULT 1,
  `smtp_host` varchar(150) NOT NULL DEFAULT 'localhost',
  `smtp_port` int(11) NOT NULL DEFAULT 1025,
  `smtp_username` varchar(150) DEFAULT NULL,
  `smtp_password` varchar(255) DEFAULT NULL,
  `smtp_encryption` varchar(10) DEFAULT NULL,
  `from_email` varchar(150) NOT NULL DEFAULT 'noreply@scmrs.udsm.ac.tz',
  `from_name` varchar(150) NOT NULL DEFAULT 'UDSM SCMRS',
  `app_url` varchar(255) NOT NULL DEFAULT 'http://localhost/scmrs',
  `email_max_attempts` int(11) NOT NULL DEFAULT 3,
  `email_batch_size` int(11) NOT NULL DEFAULT 10,
  `sla_high_days` int(11) NOT NULL DEFAULT 2,
  `sla_medium_days` int(11) NOT NULL DEFAULT 5,
  `sla_low_days` int(11) NOT NULL DEFAULT 10,
  `institution_name` varchar(150) NOT NULL DEFAULT 'UDSM',
  `institution_logo_path` varchar(255) DEFAULT 'assets/img/logo.png',
  `institution_contact_email` varchar(150) DEFAULT NULL,
  `max_login_attempts` int(11) NOT NULL DEFAULT 3,
  `lockout_duration_minutes` int(11) NOT NULL DEFAULT 15,
  `max_attachment_size_mb` int(11) NOT NULL DEFAULT 5,
  `allowed_attachment_types` varchar(255) NOT NULL DEFAULT 'image/jpeg,image/png,application/pdf',
  `password_min_length` int(11) NOT NULL DEFAULT 8,
  `password_require_upper` tinyint(1) NOT NULL DEFAULT 1,
  `password_require_lower` tinyint(1) NOT NULL DEFAULT 1,
  `password_require_number` tinyint(1) NOT NULL DEFAULT 1,
  `password_require_special` tinyint(1) NOT NULL DEFAULT 1,
  `session_timeout_minutes` int(11) NOT NULL DEFAULT 30,
  `ip_rate_limit_attempts` int(11) NOT NULL DEFAULT 10,
  `ip_rate_limit_window_minutes` int(11) NOT NULL DEFAULT 15,
  `password_reset_token_hours` int(11) NOT NULL DEFAULT 1,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `updated_by` int(11) DEFAULT NULL COMMENT 'users.user_id of admin who last saved settings; no FK on purpose'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(150) NOT NULL,
  `user_email` varchar(150) NOT NULL,
  `user_phone_number` varchar(20) DEFAULT NULL,
  `user_password` varchar(255) NOT NULL,
  `user_role` enum('student','student_leader','staff','admin') NOT NULL,
  `user_status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `failed_attempts` int(11) DEFAULT 0,
  `account_locked` tinyint(1) DEFAULT 0,
  `lock_time` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_admin` (`admin_id`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `collaboration_notes`
--
ALTER TABLE `collaboration_notes`
  ADD PRIMARY KEY (`note_id`),
  ADD KEY `collaboration_notes_ibfk_1` (`complaint_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `colleges`
--
ALTER TABLE `colleges`
  ADD PRIMARY KEY (`college_id`);

--
-- Indexes for table `complaints`
--
ALTER TABLE `complaints`
  ADD PRIMARY KEY (`complaint_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `subcategory_id` (`subcategory_id`),
  ADD KEY `department_id` (`department_id`),
  ADD KEY `preferred_staff_id` (`preferred_staff_id`),
  ADD KEY `idx_status_created` (`complaint_status`, `created_at`);

--
-- Indexes for table `complaint_assignments`
--
ALTER TABLE `complaint_assignments`
  ADD PRIMARY KEY (`assignment_id`),
  ADD KEY `assigned_by` (`assigned_by`),
  ADD KEY `complaint_id` (`complaint_id`),
  ADD KEY `staff_id` (`staff_id`);

--
-- Indexes for table `complaint_attachments`
--
ALTER TABLE `complaint_attachments`
  ADD PRIMARY KEY (`attachment_id`),
  ADD KEY `complaint_id` (`complaint_id`),
  ADD KEY `uploaded_by` (`uploaded_by`);

--
-- Indexes for table `complaint_categories`
--
ALTER TABLE `complaint_categories`
  ADD PRIMARY KEY (`category_id`),
  ADD KEY `auto_assign_department_id` (`auto_assign_department_id`),
  ADD KEY `default_role_id` (`default_role_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `complaint_endorsements`
--
ALTER TABLE `complaint_endorsements`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_endorsement` (`complaint_id`,`leader_id`);

--
-- Indexes for table `complaint_escalations`
--
ALTER TABLE `complaint_escalations`
  ADD PRIMARY KEY (`escalation_id`),
  ADD KEY `complaint_id` (`complaint_id`),
  ADD KEY `forwarded_by` (`forwarded_by`),
  ADD KEY `from_staff_id` (`from_staff_id`),
  ADD KEY `to_staff_id` (`to_staff_id`);

--
-- Indexes for table `complaint_feedback`
--
ALTER TABLE `complaint_feedback`
  ADD PRIMARY KEY (`feedback_id`),
  ADD KEY `complaint_id` (`complaint_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `complaint_progress_updates`
--
ALTER TABLE `complaint_progress_updates`
  ADD PRIMARY KEY (`update_id`),
  ADD KEY `complaint_id` (`complaint_id`),
  ADD KEY `sent_by` (`sent_by`);

--
-- Indexes for table `complaint_status_logs`
--
ALTER TABLE `complaint_status_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `complaint_id` (`complaint_id`),
  ADD KEY `changed_by` (`performed_by`);

--
-- Indexes for table `complaint_subcategories`
--
ALTER TABLE `complaint_subcategories`
  ADD PRIMARY KEY (`subcategory_id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `default_role_id` (`default_role_id`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`department_id`),
  ADD KEY `college_id` (`department_college_id`);

--
-- Indexes for table `email_queue`
--
ALTER TABLE `email_queue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status_created` (`status`,`created_at`);

--
-- Indexes for table `information_requests`
--
ALTER TABLE `information_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `complaint_id` (`complaint_id`),
  ADD KEY `requested_by` (`requested_by`);

--
-- Indexes for table `login_rate_limits`
--
ALTER TABLE `login_rate_limits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ip_time` (`ip_address`,`attempted_at`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `complaint_id` (`complaint_id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `token` (`token`),
  ADD KEY `email` (`email`);

--
-- Indexes for table `staffs`
--
ALTER TABLE `staffs`
  ADD PRIMARY KEY (`staff_id`),
  ADD KEY `user_id` (`staff_user_id`),
  ADD KEY `department_id` (`staff_department_id`),
  ADD KEY `staff_role_id` (`staff_role_id`),
  ADD KEY `staff_approved_by` (`staff_approved_by`);

--
-- Indexes for table `staff_roles`
--
ALTER TABLE `staff_roles`
  ADD PRIMARY KEY (`role_id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`student_id`),
  ADD UNIQUE KEY `registration_number` (`student_registration_number`),
  ADD KEY `college_id` (`student_college_id`),
  ADD KEY `fk_students_user` (`student_user_id`);

--
-- Indexes for table `student_rep_departments`
--
ALTER TABLE `student_rep_departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_rep_dept` (`user_id`,`department_id`),
  ADD KEY `department_id` (`department_id`),
  ADD KEY `assigned_by` (`assigned_by`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`user_email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `collaboration_notes`
--
ALTER TABLE `collaboration_notes`
  MODIFY `note_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `colleges`
--
ALTER TABLE `colleges`
  MODIFY `college_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `complaints`
--
ALTER TABLE `complaints`
  MODIFY `complaint_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `complaint_assignments`
--
ALTER TABLE `complaint_assignments`
  MODIFY `assignment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `complaint_attachments`
--
ALTER TABLE `complaint_attachments`
  MODIFY `attachment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `complaint_categories`
--
ALTER TABLE `complaint_categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `complaint_endorsements`
--
ALTER TABLE `complaint_endorsements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `complaint_escalations`
--
ALTER TABLE `complaint_escalations`
  MODIFY `escalation_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `complaint_feedback`
--
ALTER TABLE `complaint_feedback`
  MODIFY `feedback_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `complaint_progress_updates`
--
ALTER TABLE `complaint_progress_updates`
  MODIFY `update_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `complaint_status_logs`
--
ALTER TABLE `complaint_status_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `complaint_subcategories`
--
ALTER TABLE `complaint_subcategories`
  MODIFY `subcategory_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `department_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `email_queue`
--
ALTER TABLE `email_queue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `information_requests`
--
ALTER TABLE `information_requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `login_rate_limits`
--
ALTER TABLE `login_rate_limits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `staff_roles`
--
ALTER TABLE `staff_roles`
  MODIFY `role_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `student_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `student_rep_departments`
--
ALTER TABLE `student_rep_departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `collaboration_notes`
--
ALTER TABLE `collaboration_notes`
  ADD CONSTRAINT `collaboration_notes_ibfk_1` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`complaint_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `collaboration_notes_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `complaints`
--
ALTER TABLE `complaints`
  ADD CONSTRAINT `complaints_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `complaint_categories` (`category_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `complaints_ibfk_3` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `complaints_ibfk_4` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `complaints_ibfk_5` FOREIGN KEY (`subcategory_id`) REFERENCES `complaint_subcategories` (`subcategory_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `complaints_ibfk_6` FOREIGN KEY (`preferred_staff_id`) REFERENCES `staffs` (`staff_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `complaint_assignments`
--
ALTER TABLE `complaint_assignments`
  ADD CONSTRAINT `complaint_assignments_ibfk_1` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `complaint_assignments_ibfk_2` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`complaint_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `complaint_assignments_ibfk_3` FOREIGN KEY (`staff_id`) REFERENCES `staffs` (`staff_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `complaint_attachments`
--
ALTER TABLE `complaint_attachments`
  ADD CONSTRAINT `complaint_attachments_ibfk_1` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`complaint_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `complaint_attachments_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `complaint_categories`
--
ALTER TABLE `complaint_categories`
  ADD CONSTRAINT `complaint_categories_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_category_default_role` FOREIGN KEY (`default_role_id`) REFERENCES `staff_roles` (`role_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_category_level2_role` FOREIGN KEY (`level2_role_id`) REFERENCES `staff_roles` (`role_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_category_level3_role` FOREIGN KEY (`level3_role_id`) REFERENCES `staff_roles` (`role_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `complaint_endorsements`
--
ALTER TABLE `complaint_endorsements`
  ADD CONSTRAINT `fk_endorsement_complaint` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`complaint_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_endorsement_leader` FOREIGN KEY (`leader_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `complaint_escalations`
--
ALTER TABLE `complaint_escalations`
  ADD CONSTRAINT `complaint_escalations_ibfk_1` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`complaint_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `complaint_escalations_ibfk_2` FOREIGN KEY (`forwarded_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `complaint_escalations_ibfk_3` FOREIGN KEY (`from_staff_id`) REFERENCES `staffs` (`staff_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `complaint_escalations_ibfk_4` FOREIGN KEY (`to_staff_id`) REFERENCES `staffs` (`staff_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `complaint_feedback`
--
ALTER TABLE `complaint_feedback`
  ADD CONSTRAINT `complaint_feedback_ibfk_1` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`complaint_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `complaint_feedback_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `complaint_progress_updates`
--
ALTER TABLE `complaint_progress_updates`
  ADD CONSTRAINT `complaint_progress_updates_ibfk_1` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`complaint_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `complaint_progress_updates_ibfk_2` FOREIGN KEY (`sent_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `complaint_status_logs`
--
ALTER TABLE `complaint_status_logs`
  ADD CONSTRAINT `complaint_status_logs_ibfk_1` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`complaint_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `complaint_status_logs_ibfk_2` FOREIGN KEY (`performed_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `complaint_subcategories`
--
ALTER TABLE `complaint_subcategories`
  ADD CONSTRAINT `complaint_subcategories_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `complaint_categories` (`category_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_subcategory_default_role` FOREIGN KEY (`default_role_id`) REFERENCES `staff_roles` (`role_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `departments`
--
ALTER TABLE `departments`
  ADD CONSTRAINT `departments_ibfk_1` FOREIGN KEY (`department_college_id`) REFERENCES `colleges` (`college_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `information_requests`
--
ALTER TABLE `information_requests`
  ADD CONSTRAINT `information_requests_ibfk_1` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`complaint_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `information_requests_ibfk_2` FOREIGN KEY (`requested_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`complaint_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `staffs`
--
ALTER TABLE `staffs`
  ADD CONSTRAINT `staffs_ibfk_1` FOREIGN KEY (`staff_approved_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `staffs_ibfk_2` FOREIGN KEY (`staff_department_id`) REFERENCES `departments` (`department_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `staffs_ibfk_3` FOREIGN KEY (`staff_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `staffs_ibfk_4` FOREIGN KEY (`staff_role_id`) REFERENCES `staff_roles` (`role_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `students_ibfk_1` FOREIGN KEY (`student_college_id`) REFERENCES `colleges` (`college_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `students_ibfk_2` FOREIGN KEY (`student_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `student_rep_departments`
--
ALTER TABLE `student_rep_departments`
  ADD CONSTRAINT `student_rep_departments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `student_rep_departments_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `student_rep_departments_ibfk_3` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`user_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
