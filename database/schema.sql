-- ระบบติดตามการออกประชาสัมพันธ์รับนักเรียนใหม่
-- MySQL 8.4

CREATE DATABASE IF NOT EXISTS prct_pr CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE prct_pr;

-- บุคลากร (เป็นทั้งข้อมูลบุคลากรและบัญชีผู้ใช้สำหรับล็อกอิน)
CREATE TABLE staff (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    username VARCHAR(50) UNIQUE,
    password_hash VARCHAR(255),
    role ENUM('admin','staff') NOT NULL DEFAULT 'staff',
    position VARCHAR(100),
    phone VARCHAR(20),
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- โรงเรียนเป้าหมาย
CREATE TABLE schools (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    district VARCHAR(100),
    province VARCHAR(100),
    address TEXT,
    latitude DECIMAL(10,7),
    longitude DECIMAL(10,7),
    contact_name VARCHAR(150),
    contact_phone VARCHAR(20),
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_school_name (name)
) ENGINE=InnoDB;

-- สายออกแนะแนวประจำวัน
CREATE TABLE outreach_lines (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    line_date DATE NOT NULL,
    line_name VARCHAR(150) NOT NULL,
    notes TEXT,
    status ENUM('planned','in_progress','completed') NOT NULL DEFAULT 'planned',
    created_by INT UNSIGNED,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_line_date (line_date),
    FOREIGN KEY (created_by) REFERENCES staff(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- บุคลากรที่ประจำสาย (เลือกได้หลายคน)
CREATE TABLE line_staff (
    line_id INT UNSIGNED NOT NULL,
    staff_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (line_id, staff_id),
    FOREIGN KEY (line_id) REFERENCES outreach_lines(id) ON DELETE CASCADE,
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- โรงเรียนที่สายไปเยือน (1 สายไปได้หลายโรงเรียน)
CREATE TABLE line_schools (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    line_id INT UNSIGNED NOT NULL,
    school_id INT UNSIGNED NOT NULL,
    visit_order INT UNSIGNED NOT NULL DEFAULT 0,
    UNIQUE KEY uniq_line_school (line_id, school_id),
    FOREIGN KEY (line_id) REFERENCES outreach_lines(id) ON DELETE CASCADE,
    FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- รายงานผลการออกแนะแนว ต่อ 1 โรงเรียนใน 1 สาย (รูปถ่าย 1 รูป + จำนวนนักเรียนแต่ละชั้น)
CREATE TABLE visit_reports (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    line_school_id INT UNSIGNED NOT NULL UNIQUE,
    photo_path VARCHAR(255) NOT NULL,
    m2_count INT UNSIGNED NOT NULL DEFAULT 0,
    m3_count INT UNSIGNED NOT NULL DEFAULT 0,
    m5_count INT UNSIGNED NOT NULL DEFAULT 0,
    m6_count INT UNSIGNED NOT NULL DEFAULT 0,
    reported_by INT UNSIGNED NOT NULL,
    notes TEXT,
    reported_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (line_school_id) REFERENCES line_schools(id) ON DELETE CASCADE,
    FOREIGN KEY (reported_by) REFERENCES staff(id)
) ENGINE=InnoDB;
