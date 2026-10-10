<?php

/*
===========================================================
 CURATECH HEALTHCARE MANAGEMENT SYSTEM
 SINGLE FILE BACKEND
 PHP + MYSQL API
===========================================================

 File:
 backend/backend.php

 This file handles:
 - Database connection
 - Database/table creation
 - Registration
 - Login
 - Patients
 - Doctors
 - Appointments
 - Doctor schedules
 - Medical records
 - Admin operations

 IMPORTANT:
 Change DB_USERNAME / DB_PASSWORD if your MySQL
 configuration is different.
===========================================================
*/


/* =========================================================
   CONFIGURATION
========================================================= */

header("Content-Type: application/json");

header("Access-Control-Allow-Origin: *");

header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");

header("Access-Control-Allow-Headers: Content-Type");


if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    exit;
}


/* =========================================================
   MYSQL CONFIGURATION
========================================================= */

$DB_HOST = "localhost";

$DB_PORT = 3307;

$DB_USERNAME = "root";

$DB_PASSWORD = "";

$DB_NAME = "healthcare_management";


/* =========================================================
   DATABASE CONNECTION
========================================================= */

mysqli_report(MYSQLI_REPORT_OFF);

@$conn = new mysqli(
    $DB_HOST,
    $DB_USERNAME,
    $DB_PASSWORD,
    "",
    $DB_PORT
);


/* Check connection */

if ($conn->connect_error) {

    sendResponse(
        false,
        "MySQL connection failed: " . $conn->connect_error
    );

}


/* Create database */

$sql = "CREATE DATABASE IF NOT EXISTS `$DB_NAME`
        CHARACTER SET utf8mb4
        COLLATE utf8mb4_unicode_ci";


if (!$conn->query($sql)) {

    sendResponse(
        false,
        "Database creation failed."
    );

}


/* Select database */

$conn->select_db($DB_NAME);
$conn->set_charset("utf8mb4");


/* =========================================================
   CREATE TABLES
========================================================= */


/* USERS TABLE */

$conn->query("

CREATE TABLE IF NOT EXISTS users (

    id INT AUTO_INCREMENT PRIMARY KEY,

    full_name VARCHAR(100) NOT NULL,

    email VARCHAR(150) NOT NULL UNIQUE,

    phone VARCHAR(20),

    password VARCHAR(255) NOT NULL,

    role ENUM(
        'patient',
        'doctor',
        'admin'
    ) NOT NULL DEFAULT 'patient',

    status ENUM(
        'active',
        'inactive'
    ) NOT NULL DEFAULT 'active',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP

) ENGINE=InnoDB

");


/* PATIENTS TABLE */

$conn->query("

CREATE TABLE IF NOT EXISTS patients (

    id INT AUTO_INCREMENT PRIMARY KEY,

    user_id INT NOT NULL,

    date_of_birth DATE,

    gender VARCHAR(20),

    address TEXT,

    blood_group VARCHAR(10),

    emergency_contact VARCHAR(100),

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE

) ENGINE=InnoDB

");


/* DOCTORS TABLE */

$conn->query("

CREATE TABLE IF NOT EXISTS doctors (

    id INT AUTO_INCREMENT PRIMARY KEY,

    user_id INT NOT NULL,

    specialization VARCHAR(100),

    qualification VARCHAR(150),

    experience VARCHAR(50),

    hospital VARCHAR(150),

    about TEXT,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE

) ENGINE=InnoDB

");


/* DOCTOR SCHEDULE */

$conn->query("

CREATE TABLE IF NOT EXISTS doctor_schedule (

    id INT AUTO_INCREMENT PRIMARY KEY,

    doctor_id INT NOT NULL,

    day_name VARCHAR(20) NOT NULL,

    start_time TIME NOT NULL,

    end_time TIME NOT NULL,

    status ENUM(
        'available',
        'unavailable'
    ) DEFAULT 'available',

    FOREIGN KEY (doctor_id)
        REFERENCES doctors(id)
        ON DELETE CASCADE

) ENGINE=InnoDB

");


/* APPOINTMENTS */

$conn->query("

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
        'cancelled',
        'completed'
    ) DEFAULT 'pending',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (patient_id)
        REFERENCES patients(id)
        ON DELETE CASCADE,

    FOREIGN KEY (doctor_id)
        REFERENCES doctors(id)
        ON DELETE CASCADE

) ENGINE=InnoDB

");


/* MEDICAL RECORDS */

$conn->query("

CREATE TABLE IF NOT EXISTS medical_records (

    id INT AUTO_INCREMENT PRIMARY KEY,

    patient_id INT NOT NULL,

    doctor_id INT NOT NULL,

    diagnosis TEXT,

    prescription TEXT,

    notes TEXT,

    record_date DATE NOT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (patient_id)
        REFERENCES patients(id)
        ON DELETE CASCADE,

    FOREIGN KEY (doctor_id)
        REFERENCES doctors(id)
        ON DELETE CASCADE

) ENGINE=InnoDB

");


/* =========================================================
   DEFAULT ADMIN
========================================================= */

$adminEmail = "admin@curatech.com";


$checkAdmin = $conn->prepare(
    "SELECT id, password, role, status FROM users WHERE email = ?"
);

$checkAdmin->bind_param(
    "s",
    $adminEmail
);

$checkAdmin->execute();

$adminResult = $checkAdmin->get_result();

$adminPassword = "admin123";

if ($adminResult->num_rows === 0) {

    $adminPasswordHash = password_hash(
        $adminPassword,
        PASSWORD_DEFAULT
    );


    $adminInsert = $conn->prepare("

        INSERT INTO users
        (full_name, email, password, role)

        VALUES
        ('CuraTech Admin', ?, ?, 'admin')

    ");


    $adminInsert->bind_param(
        "ss",
        $adminEmail,
        $adminPasswordHash
    );


    $adminInsert->execute();

} else {

    $admin = $adminResult->fetch_assoc();

    if (
        $admin["role"] !== "admin" ||
        $admin["status"] !== "active" ||
        !password_verify($adminPassword, $admin["password"])
    ) {
        $adminPasswordHash = password_hash(
            $adminPassword,
            PASSWORD_DEFAULT
        );

        $adminUpdate = $conn->prepare("
            UPDATE users
            SET
                full_name = 'CuraTech Admin',
                password = ?,
                role = 'admin',
                status = 'active'
            WHERE id = ?
        ");

        $adminUpdate->bind_param(
            "si",
            $adminPasswordHash,
            $admin["id"]
        );

        $adminUpdate->execute();
    }

}


/* =========================================================
   GET REQUEST DATA
========================================================= */

$input = json_decode(
    file_get_contents("php://input"),
    true
);


/* =========================================================
   REQUEST PARAMETERS
========================================================= */

$action = $_GET["action"] ?? ($input["action"] ?? "");


/* =========================================================
   REGISTER
========================================================= */

if ($action === "register") {

    $name =
        trim($input["name"] ?? "");

    $email =
        trim($input["email"] ?? "");

    $phone =
        trim($input["phone"] ?? "");

    $password =
        $input["password"] ?? "";

    $role =
        $input["role"] ?? "patient";


    if (
        $name === "" ||
        $email === "" ||
        $password === ""
    ) {

        sendResponse(
            false,
            "Name, email and password are required."
        );

    }


    if (
        !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
    ) {

        sendResponse(
            false,
            "Invalid email address."
        );

    }


    if (
        strlen($password) < 6
    ) {

        sendResponse(
            false,
            "Password must contain at least 6 characters."
        );

    }


    if (
        !in_array(
            $role,
            ["patient", "doctor"]
        )
    ) {

        sendResponse(
            false,
            "Invalid role."
        );

    }


    /* Check existing user */

    $check = $conn->prepare(
        "SELECT id FROM users WHERE email = ?"
    );

    $check->bind_param(
        "s",
        $email
    );

    $check->execute();

    $result = $check->get_result();


    if ($result->num_rows > 0) {

        sendResponse(
            false,
            "Email already registered."
        );

    }


    /* Hash password */

    $hashedPassword =
        password_hash(
            $password,
            PASSWORD_DEFAULT
        );


    /* Insert user */

    $stmt = $conn->prepare("

        INSERT INTO users
        (
            full_name,
            email,
            phone,
            password,
            role
        )

        VALUES
        (?, ?, ?, ?, ?)

    ");


    $stmt->bind_param(
        "sssss",
        $name,
        $email,
        $phone,
        $hashedPassword,
        $role
    );


    if (!$stmt->execute()) {

        sendResponse(
            false,
            "Registration failed."
        );

    }


    $userId =
        $conn->insert_id;


    /* Create patient/doctor profile */

    if ($role === "patient") {

        $profile = $conn->prepare("

            INSERT INTO patients
            (user_id)

            VALUES (?)

        ");

        $profile->bind_param(
            "i",
            $userId
        );

        $profile->execute();

    }


    if ($role === "doctor") {

        $profile = $conn->prepare("

            INSERT INTO doctors
            (user_id)

            VALUES (?)

        ");

        $profile->bind_param(
            "i",
            $userId
        );

        $profile->execute();

    }


    sendResponse(
        true,
        "Registration successful.",
        [
            "user_id" => $userId
        ]
    );

}


/* =========================================================
   LOGIN
========================================================= */

if ($action === "login") {

    $email =
        trim($input["email"] ?? "");

    $password =
        $input["password"] ?? "";


    if (
        $email === "" ||
        $password === ""
    ) {

        sendResponse(
            false,
            "Email and password are required."
        );

    }


    $stmt = $conn->prepare("

        SELECT
            users.id,
            users.full_name,
            users.email,
            users.password,
            users.role,
            users.status,
            patients.id AS patient_id,
            doctors.id AS doctor_id

        FROM users

        LEFT JOIN patients
            ON patients.user_id = users.id

        LEFT JOIN doctors
            ON doctors.user_id = users.id

        WHERE email = ?

    ");


    $stmt->bind_param(
        "s",
        $email
    );

    $stmt->execute();

    $result =
        $stmt->get_result();


    if (
        $result->num_rows === 0
    ) {

        sendResponse(
            false,
            "Invalid email or password."
        );

    }


    $user =
        $result->fetch_assoc();

    if (
        $user["status"] !== "active"
    ) {

        sendResponse(
            false,
            "Your account is inactive."
        );

    }


    if (
        !password_verify(
            $password,
            $user["password"]
        )
    ) {

        sendResponse(
            false,
            "Invalid email or password."
        );

    }

    $requestedRole =
        trim($input["role"] ?? "");

    if (
        $requestedRole !== "" &&
        $requestedRole !== $user["role"]
    ) {
        sendResponse(
            false,
            "The selected role does not match this account."
        );
    }


    unset(
        $user["password"]
    );


    sendResponse(
        true,
        "Login successful.",
        $user
    );

}


/* =========================================================
   GET ALL USERS - ADMIN
========================================================= */

if ($action === "get_users") {

    $result = $conn->query("

        SELECT
            id,
            full_name,
            email,
            phone,
            role,
            status,
            created_at

        FROM users

        ORDER BY id DESC

    ");


    $users = [];


    while (
        $row = $result->fetch_assoc()
    ) {

        $users[] = $row;

    }


    sendResponse(
        true,
        "Users retrieved.",
        $users
    );

}


/* =========================================================
   DEACTIVATE USER
========================================================= */

if ($action === "deactivate_user") {

    $userId =
        intval(
            $input["user_id"] ?? 0
        );


    if ($userId <= 0) {

        sendResponse(
            false,
            "Invalid user ID."
        );

    }


    $stmt = $conn->prepare("

        UPDATE users

        SET status = 'inactive'

        WHERE id = ?

    ");


    $stmt->bind_param(
        "i",
        $userId
    );


    $stmt->execute();


    sendResponse(
        true,
        "User deactivated."
    );

}


/* =========================================================
   ACTIVATE USER
========================================================= */

if ($action === "activate_user") {

    $userId =
        intval(
            $input["user_id"] ?? 0
        );


    $stmt = $conn->prepare("

        UPDATE users

        SET status = 'active'

        WHERE id = ?

    ");


    $stmt->bind_param(
        "i",
        $userId
    );


    $stmt->execute();


    sendResponse(
        true,
        "User activated."
    );

}


/* =========================================================
   GET DOCTORS
========================================================= */

if ($action === "get_doctors") {

    $result = $conn->query("

        SELECT

            doctors.id AS doctor_id,

            users.id AS user_id,

            users.full_name,

            users.email,

            users.phone,

            doctors.specialization,

            doctors.qualification,

            doctors.experience,

            doctors.hospital

        FROM doctors

        INNER JOIN users
            ON doctors.user_id = users.id

        WHERE users.status = 'active'

        ORDER BY users.full_name

    ");


    $doctors = [];


    while (
        $row = $result->fetch_assoc()
    ) {

        $doctors[] = $row;

    }


    sendResponse(
        true,
        "Doctors retrieved.",
        $doctors
    );

}


/* =========================================================
   GET PATIENTS
========================================================= */

if ($action === "get_patients") {

    $result = $conn->query("

        SELECT

            patients.id AS patient_id,

            users.id AS user_id,

            users.full_name,

            users.email,

            users.phone,

            patients.date_of_birth,

            patients.gender,

            patients.address,

            patients.blood_group

        FROM patients

        INNER JOIN users
            ON patients.user_id = users.id

        WHERE users.status = 'active'

        ORDER BY users.full_name

    ");


    $patients = [];


    while (
        $row = $result->fetch_assoc()
    ) {

        $patients[] = $row;

    }


    sendResponse(
        true,
        "Patients retrieved.",
        $patients
    );

}


/* =========================================================
   UPDATE PATIENT PROFILE
========================================================= */

if ($action === "update_patient") {

    $userId =
        intval(
            $input["user_id"] ?? 0
        );

    $name =
        trim(
            $input["name"] ?? ""
        );

    $phone =
        trim(
            $input["phone"] ?? ""
        );

    $dob =
        $input["date_of_birth"] ?? null;

    $gender =
        trim(
            $input["gender"] ?? ""
        );

    $address =
        trim(
            $input["address"] ?? ""
        );

    $bloodGroup =
        trim(
            $input["blood_group"] ?? ""
        );


    if ($userId <= 0) {

        sendResponse(
            false,
            "Invalid user ID."
        );

    }


    $stmt = $conn->prepare("

        UPDATE users

        SET
            full_name = ?,
            phone = ?

        WHERE id = ?

    ");


    $stmt->bind_param(
        "ssi",
        $name,
        $phone,
        $userId
    );


    $stmt->execute();


    $stmt2 = $conn->prepare("

        UPDATE patients

        SET
            date_of_birth = ?,
            gender = ?,
            address = ?,
            blood_group = ?

        WHERE user_id = ?

    ");


    $stmt2->bind_param(
        "ssssi",
        $dob,
        $gender,
        $address,
        $bloodGroup,
        $userId
    );


    $stmt2->execute();


    sendResponse(
        true,
        "Patient profile updated."
    );

}


/* =========================================================
   UPDATE DOCTOR PROFILE
========================================================= */

if ($action === "update_doctor") {

    $userId =
        intval(
            $input["user_id"] ?? 0
        );

    $name =
        trim(
            $input["name"] ?? ""
        );

    $phone =
        trim(
            $input["phone"] ?? ""
        );

    $specialization =
        trim(
            $input["specialization"] ?? ""
        );

    $qualification =
        trim(
            $input["qualification"] ?? ""
        );

    $experience =
        trim(
            $input["experience"] ?? ""
        );

    $hospital =
        trim(
            $input["hospital"] ?? ""
        );

    $about =
        trim(
            $input["about"] ?? ""
        );


    $stmt = $conn->prepare("

        UPDATE users

        SET
            full_name = ?,
            phone = ?

        WHERE id = ?

    ");


    $stmt->bind_param(
        "ssi",
        $name,
        $phone,
        $userId
    );


    $stmt->execute();


    $stmt2 = $conn->prepare("

        UPDATE doctors

        SET
            specialization = ?,
            qualification = ?,
            experience = ?,
            hospital = ?,
            about = ?

        WHERE user_id = ?

    ");


    $stmt2->bind_param(
        "sssssi",
        $specialization,
        $qualification,
        $experience,
        $hospital,
        $about,
        $userId
    );


    $stmt2->execute();


    sendResponse(
        true,
        "Doctor profile updated."
    );

}


/* =========================================================
   BOOK APPOINTMENT
========================================================= */

if ($action === "book_appointment") {

    $patientId =
        intval(
            $input["patient_id"] ?? 0
        );

    $doctorId =
        intval(
            $input["doctor_id"] ?? 0
        );

    $date =
        $input["appointment_date"] ?? "";

    $time =
        $input["appointment_time"] ?? "";

    $type =
        trim(
            $input["appointment_type"] ?? ""
        );

    $reason =
        trim(
            $input["reason"] ?? ""
        );


    if (
        $patientId <= 0 ||
        $doctorId <= 0 ||
        $date === "" ||
        $time === ""
    ) {

        sendResponse(
            false,
            "Required appointment information is missing."
        );

    }


    /* Check duplicate slot */

    $check = $conn->prepare("

        SELECT id

        FROM appointments

        WHERE doctor_id = ?

        AND appointment_date = ?

        AND appointment_time = ?

        AND status != 'cancelled'

    ");


    $check->bind_param(
        "iss",
        $doctorId,
        $date,
        $time
    );


    $check->execute();


    $result =
        $check->get_result();


    if (
        $result->num_rows > 0
    ) {

        sendResponse(
            false,
            "This appointment slot is already booked."
        );

    }


    $stmt = $conn->prepare("

        INSERT INTO appointments
        (
            patient_id,
            doctor_id,
            appointment_date,
            appointment_time,
            appointment_type,
            reason
        )

        VALUES
        (?, ?, ?, ?, ?, ?)

    ");


    $stmt->bind_param(
        "iissss",
        $patientId,
        $doctorId,
        $date,
        $time,
        $type,
        $reason
    );


    if (!$stmt->execute()) {

        sendResponse(
            false,
            "Appointment booking failed."
        );

    }


    sendResponse(
        true,
        "Appointment booked successfully.",
        [
            "appointment_id" =>
                $conn->insert_id
        ]
    );

}


/* =========================================================
   GET PATIENT APPOINTMENTS
========================================================= */

if ($action === "patient_appointments") {

    $patientId =
        intval(
            $_GET["patient_id"] ?? 0
        );


    $stmt = $conn->prepare("

        SELECT

            appointments.id,

            appointments.appointment_date,

            appointments.appointment_time,

            appointments.appointment_type,

            appointments.reason,

            appointments.status,

            users.full_name AS doctor_name,

            doctors.specialization

        FROM appointments

        INNER JOIN doctors
            ON appointments.doctor_id =
               doctors.id

        INNER JOIN users
            ON doctors.user_id =
               users.id

        WHERE appointments.patient_id = ?

        ORDER BY
            appointments.appointment_date DESC

    ");


    $stmt->bind_param(
        "i",
        $patientId
    );


    $stmt->execute();


    $result =
        $stmt->get_result();


    $appointments = [];


    while (
        $row = $result->fetch_assoc()
    ) {

        $appointments[] = $row;

    }


    sendResponse(
        true,
        "Appointments retrieved.",
        $appointments
    );

}


/* =========================================================
   GET DOCTOR APPOINTMENTS
========================================================= */

if ($action === "doctor_appointments") {

    $doctorId =
        intval(
            $_GET["doctor_id"] ?? 0
        );


    $stmt = $conn->prepare("

        SELECT

            appointments.id,

            appointments.appointment_date,

            appointments.appointment_time,

            appointments.appointment_type,

            appointments.reason,

            appointments.status,

            users.full_name AS patient_name,

            users.phone AS patient_phone

        FROM appointments

        INNER JOIN patients
            ON appointments.patient_id =
               patients.id

        INNER JOIN users
            ON patients.user_id =
               users.id

        WHERE appointments.doctor_id = ?

        ORDER BY
            appointments.appointment_date ASC

    ");


    $stmt->bind_param(
        "i",
        $doctorId
    );


    $stmt->execute();


    $result =
        $stmt->get_result();


    $appointments = [];


    while (
        $row = $result->fetch_assoc()
    ) {

        $appointments[] = $row;

    }


    sendResponse(
        true,
        "Doctor appointments retrieved.",
        $appointments
    );

}


/* =========================================================
   ALL APPOINTMENTS - ADMIN
========================================================= */

if ($action === "all_appointments") {

    $result = $conn->query("

        SELECT

            appointments.id,

            appointments.appointment_date,

            appointments.appointment_time,

            appointments.appointment_type,

            appointments.reason,

            appointments.status,

            patient_users.full_name
                AS patient_name,

            doctor_users.full_name
                AS doctor_name

        FROM appointments

        INNER JOIN patients

            ON appointments.patient_id =
               patients.id

        INNER JOIN users patient_users

            ON patients.user_id =
               patient_users.id

        INNER JOIN doctors

            ON appointments.doctor_id =
               doctors.id

        INNER JOIN users doctor_users

            ON doctors.user_id =
               doctor_users.id

        ORDER BY
            appointments.appointment_date DESC

    ");


    $appointments = [];


    while (
        $row = $result->fetch_assoc()
    ) {

        $appointments[] = $row;

    }


    sendResponse(
        true,
        "All appointments retrieved.",
        $appointments
    );

}


/* =========================================================
   UPDATE APPOINTMENT STATUS
========================================================= */

if ($action === "update_appointment_status") {

    $appointmentId =
        intval(
            $input["appointment_id"] ?? 0
        );

    $status =
        $input["status"] ?? "";


    $allowedStatuses = [

        "pending",
        "confirmed",
        "cancelled",
        "completed"

    ];


    if (
        !in_array(
            $status,
            $allowedStatuses
        )
    ) {

        sendResponse(
            false,
            "Invalid appointment status."
        );

    }


    $stmt = $conn->prepare("

        UPDATE appointments

        SET status = ?

        WHERE id = ?

    ");


    $stmt->bind_param(
        "si",
        $status,
        $appointmentId
    );


    $stmt->execute();


    sendResponse(
        true,
        "Appointment status updated."
    );

}


/* =========================================================
   CANCEL APPOINTMENT
========================================================= */

if ($action === "cancel_appointment") {

    $appointmentId =
        intval(
            $input["appointment_id"] ?? 0
        );


    $stmt = $conn->prepare("

        UPDATE appointments

        SET status = 'cancelled'

        WHERE id = ?

    ");


    $stmt->bind_param(
        "i",
        $appointmentId
    );


    $stmt->execute();


    sendResponse(
        true,
        "Appointment cancelled."
    );

}


/* =========================================================
   ADD MEDICAL RECORD
========================================================= */

if ($action === "add_medical_record") {

    $patientId =
        intval(
            $input["patient_id"] ?? 0
        );

    $doctorId =
        intval(
            $input["doctor_id"] ?? 0
        );

    $diagnosis =
        trim(
            $input["diagnosis"] ?? ""
        );

    $prescription =
        trim(
            $input["prescription"] ?? ""
        );

    $notes =
        trim(
            $input["notes"] ?? ""
        );

    $date =
        $input["record_date"]
        ?? date("Y-m-d");


    $stmt = $conn->prepare("

        INSERT INTO medical_records
        (
            patient_id,
            doctor_id,
            diagnosis,
            prescription,
            notes,
            record_date
        )

        VALUES
        (?, ?, ?, ?, ?, ?)

    ");


    $stmt->bind_param(
        "iissss",
        $patientId,
        $doctorId,
        $diagnosis,
        $prescription,
        $notes,
        $date
    );


    if (!$stmt->execute()) {

        sendResponse(
            false,
            "Medical record creation failed."
        );

    }


    sendResponse(
        true,
        "Medical record added."
    );

}


/* =========================================================
   GET MEDICAL HISTORY
========================================================= */

if ($action === "medical_history") {

    $patientId =
        intval(
            $_GET["patient_id"] ?? 0
        );


    $stmt = $conn->prepare("

        SELECT

            medical_records.id,

            medical_records.diagnosis,

            medical_records.prescription,

            medical_records.notes,

            medical_records.record_date,

            users.full_name AS doctor_name,

            doctors.specialization

        FROM medical_records

        INNER JOIN doctors

            ON medical_records.doctor_id =
               doctors.id

        INNER JOIN users

            ON doctors.user_id =
               users.id

        WHERE medical_records.patient_id = ?

        ORDER BY
            medical_records.record_date DESC

    ");


    $stmt->bind_param(
        "i",
        $patientId
    );


    $stmt->execute();


    $result =
        $stmt->get_result();


    $records = [];


    while (
        $row = $result->fetch_assoc()
    ) {

        $records[] = $row;

    }


    sendResponse(
        true,
        "Medical history retrieved.",
        $records
    );

}


/* =========================================================
   ADD DOCTOR SCHEDULE
========================================================= */

if ($action === "add_schedule") {

    $doctorId =
        intval(
            $input["doctor_id"] ?? 0
        );

    $day =
        trim(
            $input["day_name"] ?? ""
        );

    $start =
        $input["start_time"] ?? "";

    $end =
        $input["end_time"] ?? "";


    $stmt = $conn->prepare("

        INSERT INTO doctor_schedule
        (
            doctor_id,
            day_name,
            start_time,
            end_time
        )

        VALUES
        (?, ?, ?, ?)

    ");


    $stmt->bind_param(
        "isss",
        $doctorId,
        $day,
        $start,
        $end
    );


    $stmt->execute();


    sendResponse(
        true,
        "Schedule added."
    );

}


/* =========================================================
   GET DOCTOR SCHEDULE
========================================================= */

if ($action === "doctor_schedule") {

    $doctorId =
        intval(
            $_GET["doctor_id"] ?? 0
        );


    $stmt = $conn->prepare("

        SELECT

            id,
            day_name,
            start_time,
            end_time,
            status

        FROM doctor_schedule

        WHERE doctor_id = ?

        ORDER BY id

    ");


    $stmt->bind_param(
        "i",
        $doctorId
    );


    $stmt->execute();


    $result =
        $stmt->get_result();


    $schedule = [];


    while (
        $row = $result->fetch_assoc()
    ) {

        $schedule[] = $row;

    }


    sendResponse(
        true,
        "Schedule retrieved.",
        $schedule
    );

}


/* =========================================================
   DELETE SCHEDULE
========================================================= */

if ($action === "delete_schedule") {

    $scheduleId =
        intval(
            $input["schedule_id"] ?? 0
        );


    $stmt = $conn->prepare("

        DELETE FROM doctor_schedule

        WHERE id = ?

    ");


    $stmt->bind_param(
        "i",
        $scheduleId
    );


    $stmt->execute();


    sendResponse(
        true,
        "Schedule deleted."
    );

}


/* =========================================================
   ADMIN DASHBOARD STATISTICS
========================================================= */

if ($action === "dashboard_stats") {

    $patientsResult =
        $conn->query("

            SELECT COUNT(*) AS total

            FROM patients

        ");


    $patients =
        $patientsResult
            ->fetch_assoc()["total"];


    $doctorsResult =
        $conn->query("

            SELECT COUNT(*) AS total

            FROM doctors

        ");


    $doctors =
        $doctorsResult
            ->fetch_assoc()["total"];


    $usersResult =
        $conn->query("

            SELECT COUNT(*) AS total

            FROM users

        ");


    $users =
        $usersResult
            ->fetch_assoc()["total"];


    $appointmentsResult =
        $conn->query("

            SELECT COUNT(*) AS total

            FROM appointments

        ");


    $appointments =
        $appointmentsResult
            ->fetch_assoc()["total"];


    $pendingResult =
        $conn->query("

            SELECT COUNT(*) AS total

            FROM appointments

            WHERE status = 'pending'

        ");


    $pending =
        $pendingResult
            ->fetch_assoc()["total"];


    sendResponse(

        true,

        "Dashboard statistics retrieved.",

        [

            "patients" =>
                $patients,

            "doctors" =>
                $doctors,

            "users" =>
                $users,

            "appointments" =>
                $appointments,

            "pending_appointments" =>
                $pending

        ]

    );

}


/* =========================================================
   UNKNOWN ACTION
========================================================= */

sendResponse(

    false,

    "Invalid or missing API action."

);


/* =========================================================
   RESPONSE FUNCTION
========================================================= */

function sendResponse(

    $success,

    $message,

    $data = null

) {

    echo json_encode(

        [

            "success" =>
                $success,

            "message" =>
                $message,

            "data" =>
                $data

        ],

        JSON_PRETTY_PRINT

    );


    exit;

}

?>