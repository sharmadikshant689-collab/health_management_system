-- =====================================================
-- CURATECH HEALTHCARE MANAGEMENT SYSTEM
-- DATABASE SQL FILE
-- =====================================================

-- Create Database
CREATE DATABASE IF NOT EXISTS curatech;

-- Select Database
USE curatech;


-- =====================================================
-- 1. USERS TABLE
-- =====================================================

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,

    full_name VARCHAR(100) NOT NULL,

    email VARCHAR(150) NOT NULL UNIQUE,

    phone VARCHAR(20),

    password VARCHAR(255) NOT NULL,

    role ENUM('admin', 'doctor', 'patient')
        NOT NULL DEFAULT 'patient',

    status ENUM('active', 'inactive')
        NOT NULL DEFAULT 'active',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


-- =====================================================
-- 2. PATIENTS TABLE
-- =====================================================

CREATE TABLE IF NOT EXISTS patients (
    id INT AUTO_INCREMENT PRIMARY KEY,

    user_id INT NOT NULL UNIQUE,

    date_of_birth DATE,

    gender VARCHAR(20),

    blood_group VARCHAR(10),

    address VARCHAR(255),

    emergency_contact VARCHAR(100),

    medical_history TEXT,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_patient_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE
);


-- =====================================================
-- 3. DOCTORS TABLE
-- =====================================================

CREATE TABLE IF NOT EXISTS doctors (
    id INT AUTO_INCREMENT PRIMARY KEY,

    user_id INT NOT NULL UNIQUE,

    specialization VARCHAR(100),

    qualification VARCHAR(150),

    experience_years INT DEFAULT 0,

    consultation_fee DECIMAL(10,2) DEFAULT 0.00,

    availability_status ENUM('available', 'unavailable')
        DEFAULT 'available',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_doctor_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE
);


-- =====================================================
-- 4. DOCTOR SCHEDULE TABLE
-- =====================================================

CREATE TABLE IF NOT EXISTS doctor_schedule (
    id INT AUTO_INCREMENT PRIMARY KEY,

    doctor_id INT NOT NULL,

    day_of_week VARCHAR(20) NOT NULL,

    start_time TIME NOT NULL,

    end_time TIME NOT NULL,

    CONSTRAINT fk_schedule_doctor
        FOREIGN KEY (doctor_id)
        REFERENCES doctors(id)
        ON DELETE CASCADE
);


-- =====================================================
-- 5. APPOINTMENTS TABLE
-- =====================================================

CREATE TABLE IF NOT EXISTS appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,

    patient_id INT NOT NULL,

    doctor_id INT NOT NULL,

    appointment_date DATE NOT NULL,

    appointment_time TIME NOT NULL,

    appointment_type VARCHAR(100),

    reason TEXT,

    status ENUM(
        'pending',
        'confirmed',
        'completed',
        'cancelled'
    ) DEFAULT 'pending',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_appointment_patient
        FOREIGN KEY (patient_id)
        REFERENCES patients(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_appointment_doctor
        FOREIGN KEY (doctor_id)
        REFERENCES doctors(id)
        ON DELETE CASCADE
);


-- =====================================================
-- 6. MEDICAL RECORDS TABLE
-- =====================================================

CREATE TABLE IF NOT EXISTS medical_records (
    id INT AUTO_INCREMENT PRIMARY KEY,

    patient_id INT NOT NULL,

    doctor_id INT NOT NULL,

    diagnosis VARCHAR(255),

    prescription TEXT,

    notes TEXT,

    record_date DATE NOT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_record_patient
        FOREIGN KEY (patient_id)
        REFERENCES patients(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_record_doctor
        FOREIGN KEY (doctor_id)
        REFERENCES doctors(id)
        ON DELETE CASCADE
);


-- =====================================================
-- CHECK ALL TABLES
-- =====================================================

SHOW TABLES;