-- ============================================
-- Monastery Healthcare & Donation Management
-- Database Schema - Version 1.0
-- ============================================

DROP DATABASE IF EXISTS monastery_healthcare;
CREATE DATABASE monastery_healthcare CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE monastery_healthcare;

-- ============================================
-- 1. ROLES TABLE
-- ============================================
CREATE TABLE roles (
    role_id INT PRIMARY KEY AUTO_INCREMENT,
    role_name VARCHAR(50) NOT NULL UNIQUE COMMENT 'Admin|Doctor|Donor|Monk',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO roles (role_name) VALUES 
('Admin'), ('Doctor'), ('Donor'), ('Monk');

-- ============================================
-- 2. USERS TABLE
-- ============================================
CREATE TABLE users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    phone VARCHAR(20),
    password_hash VARCHAR(255) NOT NULL,
    role_id INT NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(role_id) ON DELETE RESTRICT,
    INDEX idx_email (email),
    INDEX idx_role (role_id)
) ENGINE=InnoDB;

-- Default admin user (password: admin123)
INSERT INTO users (name, email, password_hash, role_id, status) VALUES
('System Administrator', 'admin@monastery.lk', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 'active');

-- ============================================
-- 3. TITLES TABLE (for monks)
-- ============================================
CREATE TABLE titles (
    title_id INT PRIMARY KEY AUTO_INCREMENT,
    title_name VARCHAR(50) NOT NULL UNIQUE COMMENT 'Ven., Rev., etc.',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO titles (title_name) VALUES 
('Ven.'), ('Rev.'), ('Most Ven.'), ('Thero');

-- ============================================
-- 4. CATEGORIES TABLE (for donations & bills)
-- ============================================
CREATE TABLE categories (
    category_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    type ENUM('donation', 'bill') NOT NULL COMMENT 'donation or bill category',
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_type (type)
) ENGINE=InnoDB;

INSERT INTO categories (name, type, description) VALUES
('Medicine', 'donation', 'Medical supplies and pharmaceuticals'),
('Food & Beverages', 'donation', 'Food and drink supplies'),
('Electricity', 'donation', 'Electricity bill payments'),
('Water', 'donation', 'Water bill payments'),
('General Donation', 'donation', 'General purpose donations'),
('Electricity Bill', 'bill', 'Monthly electricity expenses'),
('Water Bill', 'bill', 'Monthly water expenses'),
('Food Expenses', 'bill', 'Food and beverage expenses'),
('Medicine Expenses', 'bill', 'Medical supplies expenses'),
('Maintenance', 'bill', 'Facility maintenance costs');

-- ============================================
-- 5. MONKS TABLE
-- ============================================
CREATE TABLE monks (
    monk_id INT PRIMARY KEY AUTO_INCREMENT,
    full_name VARCHAR(150) NOT NULL,
    dob DATE,
    title_id INT,
    phone VARCHAR(20),
    emergency_contact VARCHAR(20),
    blood_group VARCHAR(5),
    allergies TEXT,
    chronic_conditions TEXT,
    notes TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (title_id) REFERENCES titles(title_id) ON DELETE SET NULL,
    INDEX idx_name (full_name),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- ============================================
-- 6. DOCTORS TABLE
-- ============================================
CREATE TABLE doctors (
    doctor_id INT PRIMARY KEY AUTO_INCREMENT,
    full_name VARCHAR(150) NOT NULL,
    specialization ENUM('Ayurvedic', 'Western', 'General') NOT NULL,
    contact VARCHAR(20),
    email VARCHAR(150),
    license_number VARCHAR(50),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_specialization (specialization)
) ENGINE=InnoDB;

-- ============================================
-- 7. ROOMS TABLE
-- ============================================
CREATE TABLE rooms (
    room_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    type VARCHAR(50) COMMENT 'Consultation, Treatment, etc.',
    capacity INT DEFAULT 1,
    status ENUM('available', 'occupied', 'maintenance') DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status)
) ENGINE=InnoDB;

INSERT INTO rooms (name, type, capacity, status) VALUES
('Consultation Room 1', 'Consultation', 1, 'available'),
('Consultation Room 2', 'Consultation', 1, 'available'),
('Treatment Room', 'Treatment', 2, 'available');

-- ============================================
-- 8. ROOM SLOTS TABLE
-- ============================================
CREATE TABLE room_slots (
    room_slot_id INT PRIMARY KEY AUTO_INCREMENT,
    room_id INT NOT NULL,
    day_of_week TINYINT NOT NULL COMMENT '0=Sunday, 6=Saturday',
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES rooms(room_id) ON DELETE CASCADE,
    UNIQUE KEY unique_room_slot (room_id, day_of_week, start_time),
    INDEX idx_day (day_of_week)
) ENGINE=InnoDB;

-- ============================================
-- 9. DOCTOR AVAILABILITY TABLE
-- ============================================
CREATE TABLE doctor_availability (
    avail_id INT PRIMARY KEY AUTO_INCREMENT,
    doctor_id INT NOT NULL,
    day_of_week TINYINT NOT NULL COMMENT '0=Sunday, 6=Saturday',
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (doctor_id) REFERENCES doctors(doctor_id) ON DELETE CASCADE,
    UNIQUE KEY unique_doctor_slot (doctor_id, day_of_week, start_time),
    INDEX idx_day (day_of_week)
) ENGINE=InnoDB;

-- ============================================
-- 10. APPOINTMENTS TABLE
-- ============================================
CREATE TABLE appointments (
    app_id INT PRIMARY KEY AUTO_INCREMENT,
    monk_id INT NOT NULL,
    doctor_id INT NOT NULL,
    room_slot_id INT,
    app_date DATE NOT NULL,
    app_time TIME NOT NULL,
    status ENUM('scheduled', 'completed', 'cancelled', 'no-show') DEFAULT 'scheduled',
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (monk_id) REFERENCES monks(monk_id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES doctors(doctor_id) ON DELETE RESTRICT,
    FOREIGN KEY (room_slot_id) REFERENCES room_slots(room_slot_id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_date (app_date),
    INDEX idx_status (status),
    INDEX idx_monk (monk_id),
    INDEX idx_doctor (doctor_id)
) ENGINE=InnoDB;

-- ============================================
-- 11. MEDICAL RECORDS TABLE
-- ============================================
CREATE TABLE medical_records (
    record_id INT PRIMARY KEY AUTO_INCREMENT,
    monk_id INT NOT NULL,
    doctor_id INT NOT NULL,
    appointment_id INT,
    visit_date DATE NOT NULL,
    diagnosis TEXT,
    symptoms TEXT,
    medication TEXT,
    lab_tests TEXT,
    notes TEXT,
    follow_up_date DATE,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (monk_id) REFERENCES monks(monk_id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES doctors(doctor_id) ON DELETE RESTRICT,
    FOREIGN KEY (appointment_id) REFERENCES appointments(app_id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_visit_date (visit_date),
    INDEX idx_monk (monk_id)
) ENGINE=InnoDB;

-- ============================================
-- 12. DONATIONS TABLE
-- ============================================
CREATE TABLE donations (
    donation_id INT PRIMARY KEY AUTO_INCREMENT,
    donor_user_id INT NULL COMMENT 'Null if anonymous or non-registered',
    donor_name VARCHAR(150),
    donor_email VARCHAR(150),
    donor_phone VARCHAR(20),
    amount DECIMAL(12,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'LKR',
    category_id INT NOT NULL,
    method ENUM('bank', 'cash', 'card_sandbox') NOT NULL,
    
    -- Bank transfer fields
    bank_reference VARCHAR(100) COMMENT 'Bank deposit/transfer reference',
    slip_path VARCHAR(255) COMMENT 'Path to uploaded bank slip image',
    
    -- Card payment fields (sandbox only)
    gateway_name VARCHAR(50) COMMENT 'e.g., PayHere, WebXPay',
    order_id VARCHAR(100) COMMENT 'Merchant order ID',
    txn_ref VARCHAR(100) COMMENT 'Gateway transaction reference',
    raw_payload_json TEXT COMMENT 'Webhook payload for debugging',
    
    -- Cash fields
    receipt_number VARCHAR(50) COMMENT 'Cash receipt number',
    
    -- Status tracking
    status ENUM('pending', 'paid', 'verified', 'failed', 'cancelled') DEFAULT 'pending',
    verified_by INT COMMENT 'Admin user who verified',
    verified_at TIMESTAMP NULL,
    
    -- Notes and tracking
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (donor_user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (category_id) REFERENCES categories(category_id) ON DELETE RESTRICT,
    FOREIGN KEY (verified_by) REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL,
    
    INDEX idx_status (status),
    INDEX idx_date (created_at),
    INDEX idx_category (category_id),
    INDEX idx_method (method),
    INDEX idx_order_id (order_id)
) ENGINE=InnoDB;

-- ============================================
-- 13. BILLS TABLE
-- ============================================
CREATE TABLE bills (
    bill_id INT PRIMARY KEY AUTO_INCREMENT,
    category_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'LKR',
    bill_date DATE NOT NULL,
    due_date DATE,
    description TEXT,
    vendor_name VARCHAR(150),
    invoice_number VARCHAR(50),
    attachment_path VARCHAR(255) COMMENT 'Path to bill/invoice PDF/image',
    status ENUM('pending', 'paid', 'overdue', 'cancelled') DEFAULT 'pending',
    paid_date DATE,
    paid_by INT,
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (category_id) REFERENCES categories(category_id) ON DELETE RESTRICT,
    FOREIGN KEY (paid_by) REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL,
    
    INDEX idx_bill_date (bill_date),
    INDEX idx_status (status),
    INDEX idx_category (category_id)
) ENGINE=InnoDB;

-- ============================================
-- 14. AUDIT LOGS TABLE
-- ============================================
CREATE TABLE audit_logs (
    log_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    table_name VARCHAR(50) NOT NULL,
    record_id INT NOT NULL,
    action ENUM('create', 'update', 'delete') NOT NULL,
    changed_fields TEXT COMMENT 'JSON of changed fields',
    old_values TEXT COMMENT 'JSON of old values',
    new_values TEXT COMMENT 'JSON of new values',
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    
    INDEX idx_table (table_name),
    INDEX idx_timestamp (timestamp),
    INDEX idx_user (user_id),
    INDEX idx_action (action)
) ENGINE=InnoDB;

-- ============================================
-- 15. EMAIL NOTIFICATIONS LOG TABLE
-- ============================================
CREATE TABLE email_notifications (
    notification_id INT PRIMARY KEY AUTO_INCREMENT,
    recipient_email VARCHAR(150) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    type ENUM('donation_receipt', 'appointment_reminder', 'general') NOT NULL,
    related_id INT COMMENT 'ID of related record (donation_id, app_id, etc)',
    status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    sent_at TIMESTAMP NULL,
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_status (status),
    INDEX idx_type (type),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

-- ============================================
-- 16. SYSTEM SETTINGS TABLE
-- ============================================
CREATE TABLE system_settings (
    setting_id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO system_settings (setting_key, setting_value, description) VALUES
('monastery_name', 'Giribawa Seela Suva Herath Bhikkhu Hospital', 'Monastery full name'),
('monastery_email', 'admin@monastery.lk', 'Primary contact email'),
('monastery_phone', '+94 XX XXX XXXX', 'Contact phone number'),
('smtp_host', '', 'SMTP server host'),
('smtp_port', '587', 'SMTP server port'),
('smtp_username', '', 'SMTP username'),
('smtp_password', '', 'SMTP password (encrypted)'),
('payment_gateway', 'sandbox', 'Payment gateway mode: sandbox or live'),
('default_language', 'en', 'Default system language (en or si)'),
('currency', 'LKR', 'Default currency');

-- ============================================
-- VIEWS FOR REPORTING
-- ============================================

-- View: Monthly donation summary by category
CREATE VIEW v_monthly_donations AS
SELECT 
    YEAR(d.created_at) AS year,
    MONTH(d.created_at) AS month,
    d.category_id,
    c.name AS category_name,
    COUNT(*) AS donation_count,
    SUM(d.amount) AS total_amount,
    d.method
FROM donations d
JOIN categories c ON d.category_id = c.category_id
WHERE d.status IN ('paid', 'verified')
GROUP BY YEAR(d.created_at), MONTH(d.created_at), d.category_id, d.method;

-- View: Monthly bill summary by category
CREATE VIEW v_monthly_bills AS
SELECT 
    YEAR(b.bill_date) AS year,
    MONTH(b.bill_date) AS month,
    b.category_id,
    c.name AS category_name,
    COUNT(*) AS bill_count,
    SUM(b.amount) AS total_amount
FROM bills b
JOIN categories c ON b.category_id = c.category_id
WHERE b.status = 'paid'
GROUP BY YEAR(b.bill_date), MONTH(b.bill_date), b.category_id;

-- View: Appointment statistics
CREATE VIEW v_appointment_stats AS
SELECT 
    DATE(a.app_date) AS date,
    COUNT(*) AS total_appointments,
    SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) AS completed,
    SUM(CASE WHEN a.status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled,
    SUM(CASE WHEN a.status = 'no-show' THEN 1 ELSE 0 END) AS no_show
FROM appointments a
GROUP BY DATE(a.app_date);

-- ============================================
-- STORED PROCEDURES
-- ============================================

DELIMITER //

-- Procedure: Get monk medical history
CREATE PROCEDURE sp_get_monk_medical_history(IN p_monk_id INT)
BEGIN
    SELECT 
        mr.record_id,
        mr.visit_date,
        d.full_name AS doctor_name,
        d.specialization,
        mr.diagnosis,
        mr.symptoms,
        mr.medication,
        mr.lab_tests,
        mr.notes,
        mr.follow_up_date
    FROM medical_records mr
    JOIN doctors d ON mr.doctor_id = d.doctor_id
    WHERE mr.monk_id = p_monk_id
    ORDER BY mr.visit_date DESC;
END //

-- Procedure: Get available doctors for a specific day
CREATE PROCEDURE sp_get_available_doctors(IN p_day_of_week TINYINT)
BEGIN
    SELECT 
        d.doctor_id,
        d.full_name,
        d.specialization,
        d.contact,
        da.start_time,
        da.end_time
    FROM doctors d
    JOIN doctor_availability da ON d.doctor_id = da.doctor_id
    WHERE d.status = 'active'
      AND da.day_of_week = p_day_of_week
      AND da.is_active = TRUE
    ORDER BY da.start_time;
END //

-- Procedure: Monthly financial summary
CREATE PROCEDURE sp_monthly_financial_summary(
    IN p_year INT,
    IN p_month INT
)
BEGIN
    SELECT 
        'Donations' AS type,
        c.name AS category,
        SUM(d.amount) AS total
    FROM donations d
    JOIN categories c ON d.category_id = c.category_id
    WHERE YEAR(d.created_at) = p_year
      AND MONTH(d.created_at) = p_month
      AND d.status IN ('paid', 'verified')
    GROUP BY c.category_id
    
    UNION ALL
    
    SELECT 
        'Bills' AS type,
        c.name AS category,
        SUM(b.amount) AS total
    FROM bills b
    JOIN categories c ON b.category_id = c.category_id
    WHERE YEAR(b.bill_date) = p_year
      AND MONTH(b.bill_date) = p_month
      AND b.status = 'paid'
    GROUP BY c.category_id;
END //

DELIMITER ;

-- ============================================
-- TRIGGERS
-- ============================================

DELIMITER //

-- Trigger: Log user updates
CREATE TRIGGER tr_users_update_log
AFTER UPDATE ON users
FOR EACH ROW
BEGIN
    INSERT INTO audit_logs (user_id, table_name, record_id, action, changed_fields, old_values, new_values)
    VALUES (
        NEW.user_id,
        'users',
        NEW.user_id,
        'update',
        JSON_OBJECT('email', NEW.email, 'status', NEW.status),
        JSON_OBJECT('email', OLD.email, 'status', OLD.status),
        JSON_OBJECT('email', NEW.email, 'status', NEW.status)
    );
END //

-- Trigger: Log donation verification
CREATE TRIGGER tr_donations_verify_log
AFTER UPDATE ON donations
FOR EACH ROW
BEGIN
    IF NEW.status != OLD.status AND NEW.status IN ('verified', 'paid') THEN
        INSERT INTO audit_logs (user_id, table_name, record_id, action, changed_fields)
        VALUES (
            NEW.verified_by,
            'donations',
            NEW.donation_id,
            'update',
            JSON_OBJECT('status', NEW.status, 'verified_at', NEW.verified_at)
        );
    END IF;
END //

DELIMITER ;

-- ============================================
-- SAMPLE DATA FOR TESTING
-- ============================================

-- Sample doctors
INSERT INTO doctors (full_name, specialization, contact, email, status) VALUES
('Dr. Kumara Silva', 'Western', '0771234567', 'kumara@hospital.lk', 'active'),
('Dr. Nimal Perera', 'Ayurvedic', '0772345678', 'nimal@hospital.lk', 'active'),
('Dr. Chamari Fernando', 'Western', '0773456789', 'chamari@hospital.lk', 'active');

-- Sample monks
INSERT INTO monks (full_name, dob, title_id, blood_group, notes, status) VALUES
('Chandrasiri Thero', '1965-03-15', 4, 'O+', 'Senior monk with diabetes', 'active'),
('Pannaloka Thero', '1970-07-22', 2, 'A+', 'Requires regular blood pressure monitoring', 'active'),
('Dhammaloka Thero', '1980-11-05', 2, 'B+', 'No known conditions', 'active');

-- Sample doctor availability (Monday = 1)
INSERT INTO doctor_availability (doctor_id, day_of_week, start_time, end_time) VALUES
(1, 1, '09:00:00', '12:00:00'), -- Dr. Kumara on Monday
(1, 3, '09:00:00', '12:00:00'), -- Dr. Kumara on Wednesday
(2, 2, '14:00:00', '17:00:00'), -- Dr. Nimal on Tuesday
(3, 4, '09:00:00', '12:00:00'); -- Dr. Chamari on Thursday

-- Sample room slots
INSERT INTO room_slots (room_id, day_of_week, start_time, end_time) VALUES
(1, 1, '09:00:00', '09:30:00'),
(1, 1, '09:30:00', '10:00:00'),
(1, 1, '10:00:00', '10:30:00'),
(2, 2, '14:00:00', '14:30:00'),
(2, 2, '14:30:00', '15:00:00');

-- ============================================
-- DATABASE SCHEMA COMPLETE
-- ============================================

SELECT 'Database schema created successfully!' AS message;
