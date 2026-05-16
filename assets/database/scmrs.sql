-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 10, 2026 at 03:05 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12
SET
  SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";

START TRANSACTION;

SET
  time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;

/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;

/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;

/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `scmrs`
--
-- --------------------------------------------------------
--
-- Table structure for table `collaboration_notes`
--
CREATE TABLE
  `collaboration_notes` (
    `note_id` int (11) NOT NULL,
    `complaint_id` int (11) NOT NULL,
    `created_by` int (11) NOT NULL,
    `note_text` text NOT NULL,
    `is_internal` tinyint (1) NOT NULL DEFAULT 1 COMMENT '1=internal (staff only), 0=visible to student',
    `created_at` timestamp NOT NULL DEFAULT current_timestamp()
  ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- --------------------------------------------------------
--
-- Table structure for table `colleges`
--
CREATE TABLE
  `colleges` (
    `college_id` int (11) NOT NULL,
    `college_name` varchar(150) NOT NULL,
    `college_shortcode` varchar(20) NOT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp()
  ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_general_ci;

--
-- Dumping data for table `colleges`
--
INSERT INTO
  `colleges` (
    `college_id`,
    `college_name`,
    `college_shortcode`,
    `created_at`
  )
VALUES
  (
    1,
    'College of Engineering and Technology',
    'COET',
    '2026-05-10 12:43:57'
  ),
  (
    2,
    'College of Natural and Applied Sciences',
    'CONAS',
    '2026-05-10 12:43:57'
  ),
  (
    3,
    'College of Social Sciences',
    'CoSS',
    '2026-05-10 12:43:57'
  ),
  (
    4,
    'College of Humanities',
    'CoHU',
    '2026-05-10 12:43:57'
  ),
  (
    5,
    'College of Information and Communication Technologies',
    'CoICT',
    '2026-05-10 12:43:57'
  ),
  (
    6,
    'College of Agricultural Sciences and Fisheries Technology',
    'CoAF',
    '2026-05-10 12:43:57'
  ),
  (
    7,
    'School of Education',
    'SoE',
    '2026-05-10 12:43:57'
  ),
  (8, 'School of Law', 'SoL', '2026-05-10 12:43:57'),
  (
    9,
    'School of Journalism and Mass Communication',
    'SJMC',
    '2026-05-10 12:43:57'
  );

-- --------------------------------------------------------
--
-- Table structure for table `complaints`
--
CREATE TABLE
  `complaints` (
    `complaint_id` int (11) NOT NULL,
    `student_id` int (11) NOT NULL,
    `category_id` int (11) NOT NULL,
    `subcategory_id` int (11) DEFAULT NULL,
    `department_id` int (11) DEFAULT NULL,
    `complaint_title` varchar(200) NOT NULL,
    `complaint_description` text NOT NULL,
    `priority` enum ('low', 'medium', 'high') DEFAULT 'medium',
    `complaint_status` enum (
      'pending',
      'in_progress',
      'rejected',
      'awaiting_student_response',
      'resolved'
    ) DEFAULT 'pending',
    `is_anonymous` tinyint (1) DEFAULT 0,
    `complaint_response` text DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    `routed_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
    `resolved_at` timestamp NULL DEFAULT NULL,
    `closed_at` timestamp NULL DEFAULT NULL
  ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- --------------------------------------------------------
--
-- Table structure for table `complaint_assignments`
--
CREATE TABLE
  `complaint_assignments` (
    `assignment_id` int (11) NOT NULL,
    `complaint_id` int (11) NOT NULL,
    `staff_id` varchar(20) NOT NULL,
    `assigned_by` int (11) NOT NULL COMMENT 'users.user_id of admin or escalating staff',
    `is_lead` tinyint (1) DEFAULT 1 COMMENT '1=primary handler for this complaint',
    `status` enum ('active', 'forwarded', 'completed') DEFAULT 'active',
    `notes` text DEFAULT NULL,
    `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
    `completed_at` timestamp NULL DEFAULT NULL
  ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- --------------------------------------------------------
--
-- Table structure for table `complaint_attachments`
--
CREATE TABLE
  `complaint_attachments` (
    `attachment_id` int (11) NOT NULL,
    `complaint_id` int (11) NOT NULL,
    `uploaded_by` int (11) NOT NULL,
    `file_name` varchar(255) NOT NULL,
    `file_path` varchar(255) NOT NULL,
    `file_type` varchar(100) DEFAULT NULL,
    `file_size` int (11) DEFAULT NULL,
    `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
  ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- --------------------------------------------------------
--
-- Table structure for table `complaint_categories`
--
CREATE TABLE
  `complaint_categories` (
    `category_id` int (11) NOT NULL,
    `category_name` varchar(150) NOT NULL,
    `category_description` text DEFAULT NULL,
    `requires_department_selection` tinyint (1) DEFAULT 0,
    `auto_assign_department_id` int (11) DEFAULT NULL,
    `created_by` int (11) DEFAULT NULL,
    `status` enum ('active', 'inactive') DEFAULT 'active',
    `created_at` timestamp NOT NULL DEFAULT current_timestamp()
  ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_general_ci;

--
-- Dumping data for table `complaint_categories`
--
INSERT INTO
  `complaint_categories` (
    `category_id`,
    `category_name`,
    `category_description`,
    `requires_department_selection`,
    `auto_assign_department_id`,
    `created_by`,
    `status`,
    `created_at`
  )
VALUES
  (
    1,
    'Academic Affairs',
    'Complaints related to academic activities, examinations, and coursework',
    1,
    NULL,
    1,
    'active',
    '2026-05-10 12:43:57'
  ),
  (
    2,
    'Financial Services',
    'Complaints related to fees, bursaries, loans, and financial transactions',
    0,
    30,
    1,
    'active',
    '2026-05-10 12:43:57'
  ),
  (
    3,
    'Accommodation and Housing',
    'Complaints related to hostels, room allocation, and campus housing',
    0,
    34,
    1,
    'active',
    '2026-05-10 12:43:57'
  ),
  (
    4,
    'Student Services',
    'Complaints related to student welfare, health, ID cards, and general services',
    0,
    33,
    1,
    'active',
    '2026-05-10 12:43:57'
  ),
  (
    5,
    'Staff Conduct',
    'Complaints about staff behaviour, misconduct, or professional ethics',
    1,
    NULL,
    1,
    'active',
    '2026-05-10 12:43:57'
  ),
  (
    6,
    'ICT and Internet Services',
    'Complaints about internet, university systems, email, and digital tools',
    0,
    32,
    1,
    'active',
    '2026-05-10 12:43:57'
  ),
  (
    7,
    'Infrastructure and Facilities',
    'Complaints about classrooms, laboratories, toilets, and physical facilities',
    0,
    34,
    1,
    'active',
    '2026-05-10 12:43:57'
  ),
  (
    8,
    'Library Services',
    'Complaints related to library access, books, and study resources',
    0,
    33,
    1,
    'active',
    '2026-05-10 12:43:57'
  );

-- --------------------------------------------------------
--
-- Table structure for table `complaint_escalations`
--
CREATE TABLE
  `complaint_escalations` (
    `escalation_id` int (11) NOT NULL,
    `complaint_id` int (11) NOT NULL,
    `from_staff_id` varchar(20) NOT NULL COMMENT 'staffs.staff_id of the forwarding staff',
    `to_staff_id` varchar(20) NOT NULL COMMENT 'staffs.staff_id of the receiving staff',
    `forwarded_by` int (11) NOT NULL COMMENT 'users.user_id',
    `reason` text NOT NULL,
    `status` enum ('pending', 'accepted', 'declined', 'resolved') DEFAULT 'pending',
    `escalated_at` timestamp NOT NULL DEFAULT current_timestamp(),
    `resolved_at` timestamp NULL DEFAULT NULL
  ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- --------------------------------------------------------
--
-- Table structure for table `complaint_feedback`
--
CREATE TABLE
  `complaint_feedback` (
    `feedback_id` int (11) NOT NULL,
    `complaint_id` int (11) NOT NULL,
    `student_id` int (11) NOT NULL,
    `rating` tinyint (1) NOT NULL COMMENT '1-5 rating',
    `feedback_text` text DEFAULT NULL,
    `submitted_at` timestamp NOT NULL DEFAULT current_timestamp()
  ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- --------------------------------------------------------
--
-- Table structure for table `complaint_status_logs`
--
CREATE TABLE
  `complaint_status_logs` (
    `log_id` int (11) NOT NULL,
    `complaint_id` int (11) NOT NULL,
    `action` varchar(50) NOT NULL,
    `old_status` varchar(50) DEFAULT NULL,
    `new_status` varchar(50) DEFAULT NULL,
    `performed_by` int (11) DEFAULT NULL,
    `remarks` text DEFAULT NULL,
    `changed_at` timestamp NOT NULL DEFAULT current_timestamp()
  ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- --------------------------------------------------------
--
-- Table structure for table `complaint_subcategories`
--
CREATE TABLE
  `complaint_subcategories` (
    `subcategory_id` int (11) NOT NULL,
    `category_id` int (11) NOT NULL,
    `subcategory_name` varchar(150) NOT NULL,
    `subcategory_description` text DEFAULT NULL,
    `status` enum ('active', 'inactive') DEFAULT 'active',
    `created_by` int (11) DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp()
  ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_general_ci;

--
-- Dumping data for table `complaint_subcategories`
--
INSERT INTO
  `complaint_subcategories` (
    `subcategory_id`,
    `category_id`,
    `subcategory_name`,
    `subcategory_description`,
    `status`,
    `created_by`,
    `created_at`
  )
VALUES
  (
    1,
    1,
    'Examination Issues',
    'Problems related to sitting or accessing examinations',
    'active',
    1,
    '2026-05-10 12:43:57'
  ),
  (
    2,
    1,
    'Course Registration Problems',
    'Difficulty registering for courses or units',
    'active',
    1,
    '2026-05-10 12:43:57'
  ),
  (
    3,
    1,
    'Grade and Mark Appeals',
    'Dispute over assigned marks or final grades',
    'active',
    1,
    '2026-05-10 12:43:57'
  ),
  (
    4,
    1,
    'Academic Transcripts',
    'Requests or issues with official transcripts and certificates',
    'active',
    1,
    '2026-05-10 12:43:57'
  ),
  (
    5,
    1,
    'Missing or Incorrect Marks',
    'Marks not recorded or incorrectly entered in the system',
    'active',
    1,
    '2026-05-10 12:43:57'
  ),
  (
    6,
    1,
    'Supplementary Exam Issues',
    'Problems related to supplementary or special examinations',
    'active',
    1,
    '2026-05-10 12:43:57'
  ),
  (
    7,
    2,
    'Bursary and Scholarship Issues',
    'Problems receiving or applying for bursaries and scholarships',
    'active',
    1,
    '2026-05-10 12:43:57'
  ),
  (
    8,
    2,
    'Fee Payment Problems',
    'Difficulties with fee payment, receipts, or bank transfers',
    'active',
    1,
    '2026-05-10 12:43:57'
  ),
  (
    9,
    2,
    'Student Loan Complaints',
    'Issues with HESLB loans and disbursements',
    'active',
    1,
    '2026-05-10 12:43:57'
  ),
  (
    10,
    2,
    'Refund Requests',
    'Requesting refunds for overpaid or unclaimed fees',
    'active',
    1,
    '2026-05-10 12:43:57'
  ),
  (
    11,
    2,
    'Incorrect Fee Charges',
    'Charged fees that do not match the official fee structure',
    'active',
    1,
    '2026-05-10 12:43:57'
  ),
  (
    12,
    3,
    'Hostel Allocation Issues',
    'Problems with hostel room assignment or allocation',
    'active',
    1,
    '2026-05-10 12:43:57'
  ),
  (
    13,
    3,
    'Room Maintenance and Repairs',
    'Maintenance issues in hostel rooms or common areas',
    'active',
    1,
    '2026-05-10 12:43:57'
  ),
  (
    14,
    3,
    'Hostel Security Concerns',
    'Safety and security problems within hostel premises',
    'active',
    1,
    '2026-05-10 12:43:57'
  ),
  (
    15,
    3,
    'Water and Electricity Problems',
    'Utility supply issues in hostels',
    'active',
    1,
    '2026-05-10 12:43:57'
  ),
  (
    16,
    3,
    'Hostel Fee Disputes',
    'Disagreements over hostel charges and billing',
    'active',
    1,
    '2026-05-10 12:43:57'
  ),
  (
    17,
    4,
    'Student ID Card Issues',
    'Lost, damaged, or delayed student identification cards',
    'active',
    1,
    '2026-05-10 12:43:57'
  ),
  (
    18,
    4,
    'Health Centre Complaints',
    'Issues with medical services at the university health centre',
    'active',
    1,
    '2026-05-10 12:43:57'
  ),
  (
    19,
    4,
    'Transportation Issues',
    'Problems with university bus services or transport schedules',
    'active',
    1,
    '2026-05-10 12:43:57'
  ),
  (
    20,
    4,
    'Sports and Recreation',
    'Access or quality of sports and recreational facilities',
    'active',
    1,
    '2026-05-10 12:43:57'
  ),
  (
    21,
    4,
    'Student Organisation Issues',
    'Complaints relating to student government or clubs',
    'active',
    1,
    '2026-05-10 12:43:57'
  ),
  (
    22,
    5,
    'Lecturer Misconduct',
    'Unprofessional behaviour by a teaching staff member',
    'active',
    1,
    '2026-05-10 12:43:57'
  ),
  (
    23,
    5,
    'Harassment or Discrimination',
    'Any form of harassment or discriminatory treatment',
    'active',
    1,
    '2026-05-10 12:43:57'
  ),
  (
    24,
    5,
    'Unfair Assessment',
    'Perceived unfair marking or biased assessment by staff',
    'active',
    1,
    '2026-05-10 12:43:57'
  ),
  (
    25,
    5,
    'Academic Dishonesty Allegations',
    'Accusations of plagiarism, cheating, or exam misconduct',
    'active',
    1,
    '2026-05-10 12:43:57'
  ),
  (
    26,
    6,
    'Internet Connectivity Issues',
    'Slow, unstable, or no internet access on campus',
    'active',
    1,
    '2026-05-10 12:43:57'
  ),
  (
    27,
    6,
    'University Email Problems',
    'Issues accessing or using the official university email',
    'active',
    1,
    '2026-05-10 12:43:57'
  ),
  (
    28,
    6,
    'Student Portal Issues',
    'Problems with the online student portal or e-learning system',
    'active',
    1,
    '2026-05-10 12:43:57'
  ),
  (
    29,
    6,
    'Software and System Access',
    'Inability to access university licensed software or systems',
    'active',
    1,
    '2026-05-10 12:43:57'
  ),
  (
    30,
    7,
    'Classroom Condition',
    'Poor state of lecture rooms, desks, or seating',
    'active',
    1,
    '2026-05-10 12:43:57'
  ),
  (
    31,
    7,
    'Laboratory Equipment Issues',
    'Missing, broken, or insufficient laboratory equipment',
    'active',
    1,
    '2026-05-10 12:43:57'
  ),
  (
    32,
    7,
    'Sanitation and Toilets',
    'Dirty or non-functional toilet facilities on campus',
    'active',
    1,
    '2026-05-10 12:43:57'
  ),
  (
    33,
    7,
    'Lecture Hall Overcrowding',
    'Insufficient space or seats for enrolled students',
    'active',
    1,
    '2026-05-10 12:43:57'
  ),
  (
    34,
    8,
    'Book Availability',
    'Required books or resources not available in the library',
    'active',
    1,
    '2026-05-10 12:43:57'
  ),
  (
    35,
    8,
    'Library Access Issues',
    'Denied or restricted access to library facilities',
    'active',
    1,
    '2026-05-10 12:43:57'
  ),
  (
    36,
    8,
    'E-Library and Online Resources',
    'Problems accessing digital journals, databases, or e-books',
    'active',
    1,
    '2026-05-10 12:43:57'
  ),
  (
    37,
    8,
    'Study Space Concerns',
    'Insufficient or poorly maintained study areas in the library',
    'active',
    1,
    '2026-05-10 12:43:57'
  );

-- --------------------------------------------------------
--
-- Table structure for table `departments`
--
CREATE TABLE
  `departments` (
    `department_id` int (11) NOT NULL,
    `department_college_id` int (11) DEFAULT NULL,
    `department_name` varchar(150) NOT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp()
  ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_general_ci;

--
-- Dumping data for table `departments`
--
INSERT INTO
  `departments` (
    `department_id`,
    `department_college_id`,
    `department_name`,
    `created_at`
  )
VALUES
  (1, 1, 'Civil Engineering', '2026-05-10 12:43:57'),
  (
    2,
    1,
    'Electrical Engineering',
    '2026-05-10 12:43:57'
  ),
  (
    3,
    1,
    'Mechanical and Industrial Engineering',
    '2026-05-10 12:43:57'
  ),
  (
    4,
    1,
    'Chemical and Mining Engineering',
    '2026-05-10 12:43:57'
  ),
  (5, 2, 'Physics', '2026-05-10 12:43:57'),
  (6, 2, 'Chemistry', '2026-05-10 12:43:57'),
  (7, 2, 'Mathematics', '2026-05-10 12:43:57'),
  (
    8,
    2,
    'Zoology and Wildlife Conservation',
    '2026-05-10 12:43:57'
  ),
  (9, 2, 'Botany', '2026-05-10 12:43:57'),
  (
    10,
    3,
    'Sociology and Social Anthropology',
    '2026-05-10 12:43:57'
  ),
  (
    11,
    3,
    'Political Science and Public Administration',
    '2026-05-10 12:43:57'
  ),
  (12, 3, 'Economics', '2026-05-10 12:43:57'),
  (13, 4, 'Kiswahili', '2026-05-10 12:43:57'),
  (14, 4, 'Literature', '2026-05-10 12:43:57'),
  (15, 4, 'History', '2026-05-10 12:43:57'),
  (
    16,
    4,
    'Philosophy and Religious Studies',
    '2026-05-10 12:43:57'
  ),
  (
    17,
    4,
    'Foreign Languages and Linguistics',
    '2026-05-10 12:43:57'
  ),
  (
    18,
    5,
    'Computer Science and Engineering',
    '2026-05-10 12:43:57'
  ),
  (
    19,
    5,
    'Information Systems',
    '2026-05-10 12:43:57'
  ),
  (
    20,
    5,
    'Electronics and Telecommunications',
    '2026-05-10 12:43:57'
  ),
  (
    21,
    6,
    'Crop Science and Horticulture',
    '2026-05-10 12:43:57'
  ),
  (
    22,
    6,
    'Animal Science and Production',
    '2026-05-10 12:43:57'
  ),
  (23, 6, 'Food Technology', '2026-05-10 12:43:57'),
  (
    24,
    7,
    'Educational Psychology and Curriculum Studies',
    '2026-05-10 12:43:57'
  ),
  (
    25,
    7,
    'Educational Foundations and Administration',
    '2026-05-10 12:43:57'
  ),
  (26, 8, 'Public Law', '2026-05-10 12:43:57'),
  (27, 8, 'Private Law', '2026-05-10 12:43:57'),
  (28, 9, 'Journalism', '2026-05-10 12:43:57'),
  (
    29,
    9,
    'Mass Communication',
    '2026-05-10 12:43:57'
  ),
  (
    30,
    NULL,
    'Finance and Accounts Office',
    '2026-05-10 12:43:57'
  ),
  (
    31,
    NULL,
    'Registrar\'s Office',
    '2026-05-10 12:43:57'
  ),
  (
    32,
    NULL,
    'ICT Directorate',
    '2026-05-10 12:43:57'
  ),
  (
    33,
    NULL,
    'Student Services Office',
    '2026-05-10 12:43:57'
  ),
  (
    34,
    NULL,
    'Estates and Works Department',
    '2026-05-10 12:43:57'
  ),
  (
    35,
    NULL,
    'Human Resources Office',
    '2026-05-10 12:43:57'
  );

-- --------------------------------------------------------
--
-- Table structure for table `information_requests`
--
CREATE TABLE
  `information_requests` (
    `request_id` int (11) NOT NULL,
    `complaint_id` int (11) NOT NULL,
    `requested_by` int (11) NOT NULL,
    `request_message` text NOT NULL,
    `status` enum ('pending', 'responded', 'closed') NOT NULL DEFAULT 'pending',
    `student_response` text DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    `responded_at` timestamp NULL DEFAULT NULL
  ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- --------------------------------------------------------
--
-- Table structure for table `notifications`
--
CREATE TABLE
  `notifications` (
    `notification_id` int (11) NOT NULL,
    `user_id` int (11) NOT NULL,
    `complaint_id` int (11) DEFAULT NULL,
    `message` varchar(255) NOT NULL,
    `type` enum (
      'status_change',
      'new_assignment',
      'request_info',
      'new_complaint',
      'new_registration',
      'staff_approved',
      'info_responded',
      'complaint_rejected',
      'complaint_resolved',
      'staff_rejected',
      'complaint_deleted'
    ) NOT NULL DEFAULT 'status_change',
    `link` varchar(255) DEFAULT NULL,
    `is_read` tinyint (1) DEFAULT 0,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp()
  ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- --------------------------------------------------------
--
-- Table structure for table `password_resets`
--
CREATE TABLE
  `password_resets` (
    `id` int (11) NOT NULL AUTO_INCREMENT,
    `email` varchar(150) NOT NULL,
    `token` varchar(64) NOT NULL,
    `expires_at` datetime NOT NULL,
    `used` tinyint (1) DEFAULT 0,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `token` (`token`),
    KEY `email` (`email`)
  ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- --------------------------------------------------------
--
-- Table structure for table `staffs`
--
CREATE TABLE
  `staffs` (
    `staff_id` varchar(20) NOT NULL,
    `staff_user_id` int (11) NOT NULL,
    `staff_department_id` int (11) DEFAULT NULL,
    `staff_role_id` int (11) DEFAULT NULL,
    `staff_approval_status` tinyint (1) NOT NULL DEFAULT 0 COMMENT '0=Unapproved, 1=Approved, 2=Disapproved',
    `staff_approved_by` int (11) DEFAULT NULL,
    `staff_approved_at` datetime DEFAULT NULL
  ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- --------------------------------------------------------
--
-- Table structure for table `staff_departments`
--
CREATE TABLE
  `staff_departments` (
    `id` int (11) NOT NULL,
    `staff_id` varchar(20) NOT NULL,
    `department_id` int (11) NOT NULL
  ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- --------------------------------------------------------
--
-- Table structure for table `staff_roles`
--
CREATE TABLE
  `staff_roles` (
    `role_id` int (11) NOT NULL,
    `role_name` varchar(50) NOT NULL,
    `role_rank` tinyint (4) NOT NULL COMMENT 'Higher = more senior',
    `created_at` timestamp NOT NULL DEFAULT current_timestamp()
  ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_general_ci;

--
-- Dumping data for table `staff_roles`
--
INSERT INTO
  `staff_roles` (`role_id`, `role_name`, `role_rank`, `created_at`)
VALUES
  (1, 'Officer', 1, '2026-05-10 12:43:57'),
  (2, 'Senior Officer', 2, '2026-05-10 12:43:57'),
  (3, 'Principal Officer', 3, '2026-05-10 12:43:57'),
  (4, 'Head of Department', 4, '2026-05-10 12:43:57'),
  (5, 'Director', 5, '2026-05-10 12:43:57');

-- --------------------------------------------------------
--
-- Table structure for table `students`
--
CREATE TABLE
  `students` (
    `student_id` int (11) NOT NULL,
    `student_user_id` int (11) NOT NULL,
    `student_registration_number` varchar(50) NOT NULL,
    `student_program` varchar(150) DEFAULT NULL,
    `student_college_id` int (11) DEFAULT NULL
  ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- --------------------------------------------------------
--
-- Table structure for table `users`
--
CREATE TABLE
  `users` (
    `user_id` int (11) NOT NULL,
    `username` varchar(150) NOT NULL,
    `user_email` varchar(150) NOT NULL,
    `user_phone_number` varchar(20) DEFAULT NULL,
    `user_password` varchar(255) NOT NULL,
    `user_role` enum ('student', 'staff', 'admin') NOT NULL,
    `user_status` enum ('active', 'inactive') DEFAULT 'active',
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    `failed_attempts` int (11) DEFAULT 0,
    `account_locked` tinyint (1) DEFAULT 0,
    `lock_time` datetime DEFAULT NULL
  ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_general_ci;

--
-- Dumping data for table `users`
--
INSERT INTO
  `users` (
    `user_id`,
    `username`,
    `user_email`,
    `user_phone_number`,
    `user_password`,
    `user_role`,
    `user_status`,
    `created_at`,
    `failed_attempts`,
    `account_locked`,
    `lock_time`
  )
VALUES
  (
    1,
    'Admin',
    'admin@udsm.ac.tz',
    '0700000000',
    '$2y$10$GJbJ23NJk0eaZtxLEpa3FOrRo6rCywXUWV84gYmCyGYYKcLdOr2hu',
    'admin',
    'active',
    '2026-05-10 12:43:57',
    0,
    0,
    NULL
  );

--
-- Indexes for dumped tables
--
--
-- Indexes for table `collaboration_notes`
--
ALTER TABLE `collaboration_notes` ADD PRIMARY KEY (`note_id`),
ADD KEY `collaboration_notes_ibfk_1` (`complaint_id`),
ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `colleges`
--
ALTER TABLE `colleges` ADD PRIMARY KEY (`college_id`);

--
-- Indexes for table `complaints`
--
ALTER TABLE `complaints` ADD PRIMARY KEY (`complaint_id`),
ADD KEY `student_id` (`student_id`),
ADD KEY `category_id` (`category_id`),
ADD KEY `subcategory_id` (`subcategory_id`),
ADD KEY `department_id` (`department_id`);

--
-- Indexes for table `complaint_assignments`
--
ALTER TABLE `complaint_assignments` ADD PRIMARY KEY (`assignment_id`),
ADD KEY `assigned_by` (`assigned_by`),
ADD KEY `complaint_id` (`complaint_id`),
ADD KEY `staff_id` (`staff_id`);

--
-- Indexes for table `complaint_attachments`
--
ALTER TABLE `complaint_attachments` ADD PRIMARY KEY (`attachment_id`),
ADD KEY `complaint_id` (`complaint_id`),
ADD KEY `uploaded_by` (`uploaded_by`);

--
-- Indexes for table `complaint_categories`
--
ALTER TABLE `complaint_categories` ADD PRIMARY KEY (`category_id`),
ADD KEY `auto_assign_department_id` (`auto_assign_department_id`),
ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `complaint_escalations`
--
ALTER TABLE `complaint_escalations` ADD PRIMARY KEY (`escalation_id`),
ADD KEY `complaint_id` (`complaint_id`),
ADD KEY `forwarded_by` (`forwarded_by`),
ADD KEY `from_staff_id` (`from_staff_id`),
ADD KEY `to_staff_id` (`to_staff_id`);

--
-- Indexes for table `complaint_feedback`
--
ALTER TABLE `complaint_feedback` ADD PRIMARY KEY (`feedback_id`),
ADD KEY `complaint_id` (`complaint_id`),
ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `complaint_status_logs`
--
ALTER TABLE `complaint_status_logs` ADD PRIMARY KEY (`log_id`),
ADD KEY `complaint_id` (`complaint_id`),
ADD KEY `changed_by` (`performed_by`);

--
-- Indexes for table `complaint_subcategories`
--
ALTER TABLE `complaint_subcategories` ADD PRIMARY KEY (`subcategory_id`),
ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments` ADD PRIMARY KEY (`department_id`),
ADD KEY `college_id` (`department_college_id`);

--
-- Indexes for table `information_requests`
--
ALTER TABLE `information_requests` ADD PRIMARY KEY (`request_id`),
ADD KEY `complaint_id` (`complaint_id`),
ADD KEY `requested_by` (`requested_by`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications` ADD PRIMARY KEY (`notification_id`),
ADD KEY `user_id` (`user_id`),
ADD KEY `complaint_id` (`complaint_id`);

--
-- Indexes for table `staffs`
--
ALTER TABLE `staffs` ADD PRIMARY KEY (`staff_id`),
ADD KEY `user_id` (`staff_user_id`),
ADD KEY `department_id` (`staff_department_id`),
ADD KEY `staff_role_id` (`staff_role_id`),
ADD KEY `staff_approved_by` (`staff_approved_by`);

--
-- Indexes for table `staff_departments`
--
ALTER TABLE `staff_departments` ADD PRIMARY KEY (`id`),
ADD KEY `staff_id` (`staff_id`),
ADD KEY `department_id` (`department_id`);

--
-- Indexes for table `staff_roles`
--
ALTER TABLE `staff_roles` ADD PRIMARY KEY (`role_id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students` ADD PRIMARY KEY (`student_id`),
ADD UNIQUE KEY `registration_number` (`student_registration_number`),
ADD KEY `college_id` (`student_college_id`),
ADD KEY `fk_students_user` (`student_user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users` ADD PRIMARY KEY (`user_id`),
ADD UNIQUE KEY `email` (`user_email`);

--
-- AUTO_INCREMENT for dumped tables
--
--
-- AUTO_INCREMENT for table `collaboration_notes`
--
ALTER TABLE `collaboration_notes` MODIFY `note_id` int (11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `colleges`
--
ALTER TABLE `colleges` MODIFY `college_id` int (11) NOT NULL AUTO_INCREMENT,
AUTO_INCREMENT = 10;

--
-- AUTO_INCREMENT for table `complaints`
--
ALTER TABLE `complaints` MODIFY `complaint_id` int (11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `complaint_assignments`
--
ALTER TABLE `complaint_assignments` MODIFY `assignment_id` int (11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `complaint_attachments`
--
ALTER TABLE `complaint_attachments` MODIFY `attachment_id` int (11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `complaint_categories`
--
ALTER TABLE `complaint_categories` MODIFY `category_id` int (11) NOT NULL AUTO_INCREMENT,
AUTO_INCREMENT = 9;

--
-- AUTO_INCREMENT for table `complaint_escalations`
--
ALTER TABLE `complaint_escalations` MODIFY `escalation_id` int (11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `complaint_feedback`
--
ALTER TABLE `complaint_feedback` MODIFY `feedback_id` int (11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `complaint_status_logs`
--
ALTER TABLE `complaint_status_logs` MODIFY `log_id` int (11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `complaint_subcategories`
--
ALTER TABLE `complaint_subcategories` MODIFY `subcategory_id` int (11) NOT NULL AUTO_INCREMENT,
AUTO_INCREMENT = 38;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments` MODIFY `department_id` int (11) NOT NULL AUTO_INCREMENT,
AUTO_INCREMENT = 36;

--
-- AUTO_INCREMENT for table `information_requests`
--
ALTER TABLE `information_requests` MODIFY `request_id` int (11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications` MODIFY `notification_id` int (11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `staff_departments`
--
ALTER TABLE `staff_departments` MODIFY `id` int (11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `staff_roles`
--
ALTER TABLE `staff_roles` MODIFY `role_id` int (11) NOT NULL AUTO_INCREMENT,
AUTO_INCREMENT = 6;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students` MODIFY `student_id` int (11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users` MODIFY `user_id` int (11) NOT NULL AUTO_INCREMENT,
AUTO_INCREMENT = 2;

--
-- Constraints for dumped tables
--
--
-- Constraints for table `collaboration_notes`
--
ALTER TABLE `collaboration_notes` ADD CONSTRAINT `collaboration_notes_ibfk_1` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`complaint_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD CONSTRAINT `collaboration_notes_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `complaints`
--
ALTER TABLE `complaints` ADD CONSTRAINT `complaints_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `complaint_categories` (`category_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD CONSTRAINT `complaints_ibfk_3` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD CONSTRAINT `complaints_ibfk_4` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD CONSTRAINT `complaints_ibfk_5` FOREIGN KEY (`subcategory_id`) REFERENCES `complaint_subcategories` (`subcategory_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `complaint_assignments`
--
ALTER TABLE `complaint_assignments` ADD CONSTRAINT `complaint_assignments_ibfk_1` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD CONSTRAINT `complaint_assignments_ibfk_2` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`complaint_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD CONSTRAINT `complaint_assignments_ibfk_3` FOREIGN KEY (`staff_id`) REFERENCES `staffs` (`staff_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `complaint_attachments`
--
ALTER TABLE `complaint_attachments` ADD CONSTRAINT `complaint_attachments_ibfk_1` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`complaint_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD CONSTRAINT `complaint_attachments_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `complaint_categories`
--
ALTER TABLE `complaint_categories` ADD CONSTRAINT `complaint_categories_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `complaint_escalations`
--
ALTER TABLE `complaint_escalations` ADD CONSTRAINT `complaint_escalations_ibfk_1` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`complaint_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD CONSTRAINT `complaint_escalations_ibfk_2` FOREIGN KEY (`forwarded_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD CONSTRAINT `complaint_escalations_ibfk_3` FOREIGN KEY (`from_staff_id`) REFERENCES `staffs` (`staff_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD CONSTRAINT `complaint_escalations_ibfk_4` FOREIGN KEY (`to_staff_id`) REFERENCES `staffs` (`staff_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `complaint_feedback`
--
ALTER TABLE `complaint_feedback` ADD CONSTRAINT `complaint_feedback_ibfk_1` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`complaint_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD CONSTRAINT `complaint_feedback_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `complaint_status_logs`
--
ALTER TABLE `complaint_status_logs` ADD CONSTRAINT `complaint_status_logs_ibfk_1` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`complaint_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD CONSTRAINT `complaint_status_logs_ibfk_2` FOREIGN KEY (`performed_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `complaint_subcategories`
--
ALTER TABLE `complaint_subcategories` ADD CONSTRAINT `complaint_subcategories_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `complaint_categories` (`category_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `departments`
--
ALTER TABLE `departments` ADD CONSTRAINT `departments_ibfk_1` FOREIGN KEY (`department_college_id`) REFERENCES `colleges` (`college_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `information_requests`
--
ALTER TABLE `information_requests` ADD CONSTRAINT `information_requests_ibfk_1` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`complaint_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD CONSTRAINT `information_requests_ibfk_2` FOREIGN KEY (`requested_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications` ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`complaint_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `staffs`
--
ALTER TABLE `staffs` ADD CONSTRAINT `staffs_ibfk_1` FOREIGN KEY (`staff_approved_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD CONSTRAINT `staffs_ibfk_2` FOREIGN KEY (`staff_department_id`) REFERENCES `departments` (`department_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD CONSTRAINT `staffs_ibfk_3` FOREIGN KEY (`staff_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `staff_departments`
--
ALTER TABLE `staff_departments` ADD CONSTRAINT `staff_departments_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD CONSTRAINT `staff_departments_ibfk_2` FOREIGN KEY (`staff_id`) REFERENCES `staffs` (`staff_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `students`
--
ALTER TABLE `students` ADD CONSTRAINT `students_ibfk_1` FOREIGN KEY (`student_college_id`) REFERENCES `colleges` (`college_id`) ON DELETE CASCADE ON UPDATE CASCADE,
ADD CONSTRAINT `students_ibfk_2` FOREIGN KEY (`student_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;

/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;

/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;