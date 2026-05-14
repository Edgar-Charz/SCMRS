# SCMRS — Students Complaints Management and Reporting System

A web-based platform designed to streamline how student complaints are submitted, tracked, and resolved within an institution. It provides a structured workflow for handling issues, ensuring transparency, accountability, and timely resolution.

---

## Features

### Student
- Submit complaints with file attachments and optional anonymity
- Track complaint status in real time (pending, in progress, resolved, rejected)
- View full complaint history and admin/staff responses
- Respond to information requests from staff

### Staff
- View complaints assigned by the admin
- Submit responses and update complaint progress
- Access full complaint details and attachment downloads

### Admin
- Full complaint management: assign to staff, set priority, resolve, or reject
- Manage departments, complaint categories and subcategories, and staff roles
- Approve or reject staff registration requests
- Manage student and staff accounts
- Generate and export reports filtered by department, category, date range, or priority
- View resolution statistics broken down by staff, category, department, and monthly trend

---

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8+ |
| Database | MySQL / MariaDB (via MySQLi) |
| Server | Apache (XAMPP) |
| PDF Export | FPDF / TCPDF |
| Frontend | HTML, CSS, JavaScript, Ajax |

---

## Requirements

- [XAMPP](https://www.apachefriends.org/) (Apache + MySQL) or any PHP 8+ + MySQL environment
- PHP 8.0 or higher
- MySQL 5.7 / MariaDB 10.4 or higher
- A modern web browser

---

## Installation & Setup

1. **Clone the repository** into your XAMPP `htdocs` folder:
   ```bash
   git clone https://github.com/Edgar-Charz/SCMRS.git C:/xampp/htdocs/scmrs
   ```

2. **Start Apache and MySQL** from the XAMPP Control Panel.

3. **Create the database:**
   - Open [phpMyAdmin](http://localhost/phpmyadmin)
   - Create a new database named `scmrs`
   - Select the `scmrs` database, click **Import**, and upload `assets/database/scmrs.sql`

4. **Configure the database connection** (only if your MySQL credentials differ from the defaults):

   Open `config/Database.php` and update the values:
   ```php
   private $servername = "localhost";
   private $username   = "root";
   private $password   = "";
   private $dbname     = "scmrs";
   ```

5. **Open the app** in your browser:
   ```
   http://localhost/scmrs
   ```

6. **Register an account** — use the registration page to create your first student or staff account. Staff accounts require admin approval before login.

---

## Project Structure

```
scmrs/
├── ajax/                   # Ajax handlers (e.g. dynamic subcategory loading)
├── assets/
│   ├── css/                # Stylesheets
│   ├── js/                 # JavaScript files
│   ├── database/           # scmrs.sql — database schema and seed data
│   ├── fpdf/               # FPDF library for PDF generation
│   └── plugins/            # Third-party plugins (TCPDF, etc.)
├── classes/                # PHP classes (User, Complaint, Admin, Staff, etc.)
├── config/                 # Database connection config
├── includes/               # Shared UI components (topbar, toast notifications)
├── uploads/complaints/     # Uploaded complaint attachments (auto-created)
├── admin_dashboard.php
├── staff_dashboard.php
├── student_dashboard.php
├── login.php
├── register.php
└── ...                     # Role-specific pages
```

---

## Seeded Reference Data

The SQL file comes pre-loaded with:
- **Colleges** — a list of institution colleges for student registration
- **Departments** — departments complaints can be routed to
- **Complaint categories and subcategories** — predefined issue types
- **Staff roles** — role hierarchy used for complaint escalation

No default user accounts are seeded. All accounts are created through registration.
