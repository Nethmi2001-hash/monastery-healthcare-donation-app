# ☸ Seela Suwa Herath — Monastery Healthcare & Donation Management System

A comprehensive web application for managing healthcare services, donations, and welfare operations for a Buddhist monastery (Bikshu Gilan Arana) in Sri Lanka.

---

## 📋 Table of Contents

- [Overview](#overview)
- [Features](#features)
- [Tech Stack](#tech-stack)
- [Prerequisites](#prerequisites)
- [Installation & Setup](#installation--setup)
- [Database Setup](#database-setup)
- [Configuration](#configuration)
- [Running the Application](#running-the-application)
- [User Roles & Access](#user-roles--access)
- [Project Structure](#project-structure)
- [API Endpoints](#api-endpoints)
- [Screenshots](#screenshots)
- [Troubleshooting](#troubleshooting)
- [License](#license)

---

## Overview

**Seela Suwa Herath** is a full-stack PHP web application designed to manage the day-to-day operations of a monastery healthcare and donation system. It connects donors, doctors, monks, and administrators through role-based dashboards, enabling transparent donation tracking, appointment scheduling, bill management, and more.

The platform supports:
- **Public-facing pages** for donors to contribute and view transparency reports
- **Role-based dashboards** for Admins, Donors, Doctors, and Monks
- **Real-time notifications** and alerts across all dashboards
- **Multi-language support** (English and Sinhala)

---

## Features

### 🏠 Public Pages
- **Landing Page** — Beautiful hero section with monastery information, mission, and donation CTA
- **Public Donation Page** — Donate via bank transfer or PayHere gateway (no login required)
- **Transparency Report** — Public financial overview showing how donations are utilized
- **Alms Date Request** — Public users can request specific dates to offer alms (food)

### 👨‍💼 Admin Dashboard
- Overview statistics (monks, doctors, appointments, donations)
- Weekly appointment trend charts
- Financial overview (donations vs expenses, last 6 months)
- Alerts & notifications (pending appointments, alms requests, inactive doctors)
- Today's schedule preview
- Quick action links to all management modules

### 🏥 Management Modules (Admin)
- **Monk Management** — Add, edit, import (CSV), and manage monks with medical history
- **Doctor Management** — Register doctors, manage specializations and status
- **Doctor Availability** — Configure doctor schedules and available time slots
- **Appointment Management** — Schedule, assign, and track patient appointments
- **Donation Management** — View, verify, and manage all donations with receipt generation
- **Alms Date Requests** — Approve or reject donor alms date requests
- **Bill/Expense Management** — Track monastery expenses and bills
- **Room Management** — Manage monastery rooms and bed allocation
- **Room Slot Management** — Manage room slots and occupancy
- **Category Management** — Create donation categories (Healthcare, Electricity, etc.)
- **Title Management** — Manage monk honorific titles
- **Reports** — Generate and export financial reports (PDF)
- **AI Chatbot** — AI-powered assistant for admin queries (Gemini/OpenAI)

### 💰 Donor Dashboard
- Personal donation statistics (total donated, this month, verified count)
- Recent donation history with status tracking
- Monthly donation trend chart
- Donation breakdown by category (pie chart)
- Monastery transparency overview (total donations vs expenses)
- **Make Donation** — Submit bank transfer donations with slip upload
- **Request Alms Date** — Request a date to offer alms with conflict detection
- My alms date request history with status tracking

### 🩺 Doctor Dashboard
- Upcoming appointments schedule
- Patient (monk) information
- Appointment management

### 🧘 Monk Dashboard
- Personal health records
- Appointment requests to admin
- Appointment history

---

## Tech Stack

| Layer | Technology |
|-------|-----------|
| **Backend** | PHP 8.2+ |
| **Database** | MySQL 5.7+ / MariaDB 10.4+ |
| **Frontend** | HTML5, CSS3, JavaScript (ES6+) |
| **CSS Framework** | Bootstrap 5.3.3 |
| **Icons** | Bootstrap Icons 1.11.1 |
| **Charts** | Chart.js |
| **Fonts** | Google Fonts (Inter, EB Garamond, Raleway) |
| **PDF Generation** | FPDF |
| **Email** | PHPMailer |
| **Payment Gateway** | PayHere (Sri Lanka) |
| **AI Integration** | Google Gemini API / OpenAI API |
| **Server** | Apache (XAMPP) |

---

## Prerequisites

Before you begin, make sure you have the following installed:

1. **XAMPP** (or any LAMP/WAMP stack)
   - PHP 8.2 or higher
   - MySQL 5.7+ or MariaDB 10.4+
   - Apache with `mod_rewrite` enabled
2. **Web Browser** — Chrome, Firefox, Edge, or Safari (latest version)
3. **Git** (optional, for cloning the repository)

---

## Installation & Setup

### Step 1: Clone or Download the Project

```bash
# Clone the repository
git clone https://github.com/Nethmi2001/monastery-healthcare-donation-app.git

# OR download and extract the ZIP into your XAMPP htdocs folder
```

### Step 2: Place in XAMPP htdocs

Move/clone the project folder into your XAMPP `htdocs` directory:

```
C:\xampp\htdocs\monastery-healthcare-donation-app\
```

> **Important:** The folder name must be exactly `monastery-healthcare-donation-app` as it is referenced throughout the application.

### Step 3: Start XAMPP Services

1. Open **XAMPP Control Panel**
2. Start **Apache** service
3. Start **MySQL** service

---

## Database Setup

### Option 1: Import the SQL Schema (Recommended)

1. Open **phpMyAdmin** at `http://localhost/phpmyadmin`
2. Click **Import** tab
3. Select the file: `sql/database_schema.sql`
4. Click **Go** to execute

This will:
- Create the `monastery_healthcare` database
- Create all required tables (users, roles, monks, doctors, appointments, donations, bills, categories, rooms, etc.)
- Insert default roles (Admin, Donor, Doctor, Monk)
- Insert default admin user and sample data

### Option 2: Manual Database Creation

```sql
CREATE DATABASE monastery_healthcare CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE monastery_healthcare;
SOURCE /path/to/sql/database_schema.sql;
```

### Default Admin Credentials

| Field | Value |
|-------|-------|
| Email | `admin@monastery.lk` |
| Password | `admin123` |

> **⚠️ Important:** Change the default admin password immediately after first login.

---

## Configuration

### Database Configuration

Edit `includes/db_config.php` if your MySQL credentials differ:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');       // Your MySQL username
define('DB_PASS', '');           // Your MySQL password
define('DB_NAME', 'monastery_healthcare');
```

### Application Base URL

If your folder name differs from `monastery-healthcare-donation-app`, update `includes/init.php`:

```php
define('BASE_URL', '/monastery-healthcare-donation-app/');
```

### PayHere Payment Gateway (Optional)

To enable online payments, configure `includes/payhere_config.php` with your PayHere merchant credentials:

```php
define('PAYHERE_MERCHANT_ID', 'your_merchant_id');
define('PAYHERE_MERCHANT_SECRET', 'your_merchant_secret');
```

### AI Chatbot (Optional)

To enable the AI chatbot, configure one of:

- **Google Gemini**: Edit `includes/gemini_config.php` with your Gemini API key
- **OpenAI**: Edit `includes/openai_config.php` with your OpenAI API key

### Email Notifications (Optional)

Configure SMTP settings in `includes/email_config.php` for email notifications:

```php
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'your_email@gmail.com');
define('SMTP_PASS', 'your_app_password');
```

---

## Running the Application

1. Ensure **Apache** and **MySQL** are running in XAMPP
2. Open your browser and navigate to:

```
http://localhost/monastery-healthcare-donation-app/
```

3. You will see the **landing page** with navigation to:
   - **Donate Now** → Public donation page
   - **Transparency** → Public financial reports
   - **Sign In** → Login page for registered users

4. Login with default admin credentials to access the admin dashboard

### Quick Access URLs

| Page | URL |
|------|-----|
| Landing Page | `http://localhost/monastery-healthcare-donation-app/` |
| Login | `http://localhost/monastery-healthcare-donation-app/pages/auth/login.php` |
| Register | `http://localhost/monastery-healthcare-donation-app/pages/auth/register.php` |
| Dashboard | `http://localhost/monastery-healthcare-donation-app/pages/dashboard.php` |
| Public Donate | `http://localhost/monastery-healthcare-donation-app/pages/public/public_donate.php` |
| Transparency | `http://localhost/monastery-healthcare-donation-app/pages/public/public_transparency.php` |

---

## User Roles & Access

The application supports **4 user roles**, each with a dedicated dashboard:

| Role | Dashboard | Key Capabilities |
|------|-----------|-----------------|
| **Admin** | Full management dashboard | All modules: monks, doctors, appointments, donations, bills, rooms, reports, chatbot |
| **Donor** | Donor dashboard | Make donations, request alms dates, view personal donation history & transparency |
| **Doctor** | Doctor dashboard | View assigned appointments, manage patient records |
| **Monk** | Monk dashboard | Request appointments, view health records and appointment history |

### Role-Based Routing

The `pages/dashboard.php` acts as a router — it detects the logged-in user's role and automatically loads the appropriate dashboard:

```
Admin  → pages/dashboard.php (admin dashboard inline)
Donor  → dashboards/dashboard_donor.php
Doctor → dashboards/dashboard_doctor.php
Monk   → dashboards/dashboard_monk.php
```

---

## Project Structure

```
monastery-healthcare-donation-app/
│
├── index.php                      # Landing page (public)
├── README.md                      # This file
│
├── api/                           # Backend API endpoints
│   ├── advanced_search.php        # Search functionality
│   ├── chatbot_api.php            # AI chatbot endpoint
│   ├── check_notifications.php    # Real-time notification checks
│   ├── export_report.php          # PDF report generation
│   ├── generate_receipt.php       # Donation receipt PDF
│   ├── payhere_checkout.php       # PayHere payment initiation
│   ├── payhere_notify.php         # PayHere payment webhook
│   ├── payhere_return.php         # PayHere return handler
│   ├── payhere_cancel.php         # PayHere cancellation handler
│   ├── process_donation_date_request.php  # Public alms date request
│   ├── process_public_donation.php        # Public donation processing
│   └── verify_donation.php        # Admin donation verification
│
├── assets/
│   ├── css/                       # Stylesheets
│   ├── js/                        # JavaScript files
│   └── monk_import_template.csv   # CSV template for monk import
│
├── dashboards/                    # Role-specific dashboard files
│   ├── dashboard_donor.php        # Donor dashboard
│   ├── dashboard_doctor.php       # Doctor dashboard
│   └── dashboard_monk.php         # Monk dashboard
│
├── docs/                          # Documentation files
│
├── email_templates/               # HTML email templates
│
├── fpdf/                          # FPDF library for PDF generation
│
├── images/                        # Static images (hero, gallery)
│
├── includes/                      # Core PHP includes
│   ├── init.php                   # Bootstrap (ROOT_PATH, BASE_URL)
│   ├── db_config.php              # Database connection & helpers
│   ├── auth_check.php             # Authentication middleware
│   ├── csrf.php                   # CSRF token protection
│   ├── navbar.php                 # Sidebar navigation (role-based)
│   ├── footer.php                 # Footer include
│   ├── language.php               # Multi-language support (EN/SI)
│   ├── email_config.php           # SMTP email configuration
│   ├── email_helper.php           # Email sending utilities
│   ├── gemini_config.php          # Google Gemini AI config
│   ├── openai_config.php          # OpenAI API config
│   ├── payhere_config.php         # PayHere payment gateway config
│   └── qrcode_helper.php         # QR code generation utility
│
├── pages/                         # Application pages
│   ├── auth/                      # Authentication pages
│   │   ├── login.php              # User login
│   │   ├── register.php           # User registration
│   │   ├── logout.php             # Session logout
│   │   ├── forgot_password.php    # Password reset request
│   │   └── reset_password.php     # Password reset form
│   │
│   ├── public/                    # Public-facing pages
│   │   ├── public_donate.php      # Public donation form
│   │   └── public_transparency.php # Financial transparency report
│   │
│   ├── dashboard.php              # Dashboard router (role-based)
│   ├── monk_management.php        # Monk CRUD management
│   ├── doctor_management.php      # Doctor CRUD management
│   ├── doctor_availability.php    # Doctor schedule management
│   ├── patient_appointments.php   # Appointment scheduling
│   ├── donation_management.php    # Donation management & verification
│   ├── donation_date_requests.php # Alms date request management
│   ├── bill_management.php        # Expense/bill tracking
│   ├── room_management.php        # Room management
│   ├── room_slot_management.php   # Room slot allocation
│   ├── category_management.php    # Donation categories
│   ├── title_management.php       # Monk title management
│   ├── reports.php                # Financial reports
│   ├── chatbot.php                # AI chatbot interface
│   ├── import_monks.php           # CSV monk import
│   ├── table.php                  # Data table views
│   └── edit.php                   # Edit forms
│
├── phpmailer/                     # PHPMailer library
│
├── setup/                         # Setup scripts
│
├── sql/                           # Database files
│   ├── database_schema.sql        # Full database schema
│   └── chat_logs_table.sql        # Chatbot logs table
│
└── uploads/                       # User uploads
    └── slips/                     # Bank slip uploads
```

---

## API Endpoints

| Endpoint | Method | Description | Auth |
|----------|--------|-------------|------|
| `api/process_public_donation.php` | POST | Process public donations | No |
| `api/process_donation_date_request.php` | POST | Submit alms date request (public) | No |
| `api/payhere_checkout.php` | POST | Initiate PayHere payment | No |
| `api/payhere_notify.php` | POST | PayHere IPN webhook | No |
| `api/payhere_return.php` | GET | PayHere success return | No |
| `api/payhere_cancel.php` | GET | PayHere cancel return | No |
| `api/verify_donation.php` | POST | Verify/reject donation | Admin |
| `api/generate_receipt.php` | GET | Generate donation receipt PDF | Admin |
| `api/export_report.php` | GET | Export financial report PDF | Admin |
| `api/chatbot_api.php` | POST | AI chatbot query | Logged in |
| `api/check_notifications.php` | GET | Check for new notifications | Logged in |
| `api/advanced_search.php` | GET | Search monks/doctors/donations | Admin |

---

## Key Features in Detail

### 🔐 Security
- **Prepared Statements** — All database queries use parameterized queries to prevent SQL injection
- **CSRF Protection** — Cross-site request forgery tokens on all forms
- **XSS Prevention** — Output sanitization with `htmlspecialchars()`
- **Session Management** — Secure PHP sessions with role-based access control
- **Password Hashing** — bcrypt hashing for all user passwords

### 🌐 Multi-Language Support
- English (default)
- Sinhala (සිංහල)
- Language switcher available in the navigation bar

### 📊 Charts & Analytics
- Weekly appointment trends (line chart)
- Monthly donation trends (bar chart)
- Donation by category breakdown (doughnut chart)
- Financial overview: donations vs expenses (bar chart)

### 📧 Email Notifications
- Donation receipt emails
- Appointment confirmation emails
- Password reset emails
- HTML email templates with monastery branding

### 💳 Payment Integration
- **PayHere Gateway** — Online card/bank payments for Sri Lankan users
- **Bank Transfer** — Manual bank slip upload with admin verification
- **Receipt Generation** — PDF receipts with QR codes

---

## Troubleshooting

### Common Issues

| Issue | Solution |
|-------|----------|
| **404 Not Found** | Ensure the project folder is named `monastery-healthcare-donation-app` and placed in `htdocs` |
| **Database connection error** | Verify MySQL is running and credentials in `includes/db_config.php` are correct |
| **Blank page / 500 error** | Check Apache error logs: `C:\xampp\apache\logs\error.log` |
| **Login redirects in a loop** | Clear browser cookies/cache or check if `session_start()` is called correctly |
| **File upload fails** | Ensure `uploads/slips/` directory exists and has write permissions |
| **PayHere not working** | Configure valid merchant credentials in `includes/payhere_config.php` |
| **Donation form shows "Server error: 404"** | Hard refresh with `Ctrl+Shift+R` to clear cached JavaScript |
| **Chatbot not responding** | Configure a valid API key in `gemini_config.php` or `openai_config.php` |

### PHP Configuration

Ensure these PHP settings are enabled in `php.ini`:

```ini
extension=mysqli
extension=mbstring
extension=openssl
file_uploads = On
upload_max_filesize = 10M
post_max_size = 12M
```

---

## License

This project is developed for **Seela Suwa Herath Bikshu Gilan Arana** monastery welfare management. All rights reserved.

---

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/new-feature`)
3. Commit your changes (`git commit -m 'Add new feature'`)
4. Push to the branch (`git push origin feature/new-feature`)
5. Open a Pull Request

---

<p align="center">
  Made with ❤️ in Sri Lanka | © 2026 Seela Suwa Herath
</p>
