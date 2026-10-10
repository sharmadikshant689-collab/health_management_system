package com.curatech;

import java.sql.Date;
import java.sql.Time;
import java.time.LocalDate;
import java.time.LocalTime;
import java.util.List;
import java.util.Locale;
import java.util.Map;

import jakarta.annotation.PostConstruct;
import org.springframework.jdbc.core.JdbcTemplate;
import org.springframework.security.crypto.bcrypt.BCryptPasswordEncoder;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Transactional;

@Service
public class ApiService {
    private static final String ADMIN_EMAIL = "admin@curatech.com";
    private static final String ADMIN_PASSWORD = "admin123";

    private final JdbcTemplate jdbc;
    private final BCryptPasswordEncoder passwordEncoder = new BCryptPasswordEncoder();

    public ApiService(JdbcTemplate jdbc) {
        this.jdbc = jdbc;
    }

    @PostConstruct
    public void ensureDefaultAdmin() {
        List<Map<String, Object>> existing = jdbc.queryForList(
                "SELECT id, password, role, status FROM users WHERE email = ?",
                ADMIN_EMAIL);
        if (existing.isEmpty()) {
            jdbc.update(
                    "INSERT INTO users (full_name, email, password, role, status) VALUES (?, ?, ?, 'admin', 'active')",
                    "CuraTech Admin", ADMIN_EMAIL, passwordEncoder.encode(ADMIN_PASSWORD));
            return;
        }

        Map<String, Object> admin = existing.get(0);
        String storedPassword = String.valueOf(admin.get("password"));
        if (!"admin".equals(admin.get("role"))
                || !"active".equals(admin.get("status"))
                || !passwordMatches(ADMIN_PASSWORD, storedPassword)) {
            jdbc.update(
                    "UPDATE users SET full_name = ?, password = ?, role = 'admin', status = 'active' WHERE id = ?",
                    "CuraTech Admin", passwordEncoder.encode(ADMIN_PASSWORD), admin.get("id"));
        }
    }

    @Transactional
    public ApiResponse handle(String action, Map<String, Object> input) {
        return switch (action) {
            case "register" -> register(input);
            case "login" -> login(input);
            case "get_users" -> success("Users retrieved.",
                    jdbc.queryForList("SELECT id, full_name, email, phone, role, status, created_at FROM users ORDER BY id DESC"));
            case "deactivate_user" -> setUserStatus(input, "inactive");
            case "activate_user" -> setUserStatus(input, "active");
            case "get_doctors" -> success("Doctors retrieved.", getDoctors());
            case "get_patients" -> success("Patients retrieved.", getPatients());
            case "update_patient" -> updatePatient(input);
            case "update_doctor" -> updateDoctor(input);
            case "book_appointment" -> bookAppointment(input);
            case "patient_appointments" -> success("Appointments retrieved.",
                    getPatientAppointments(requiredId(input, "patient_id")));
            case "doctor_appointments" -> success("Doctor appointments retrieved.",
                    getDoctorAppointments(requiredId(input, "doctor_id")));
            case "all_appointments" -> success("All appointments retrieved.",
                    jdbc.queryForList("""
                            SELECT a.id, a.appointment_date, a.appointment_time, a.appointment_type,
                                   a.reason, a.status, pu.full_name AS patient_name,
                                   du.full_name AS doctor_name
                            FROM appointments a
                            INNER JOIN patients p ON a.patient_id = p.id
                            INNER JOIN users pu ON p.user_id = pu.id
                            INNER JOIN doctors d ON a.doctor_id = d.id
                            INNER JOIN users du ON d.user_id = du.id
                            ORDER BY a.appointment_date DESC
                            """));
            case "update_appointment_status" -> updateAppointmentStatus(input);
            case "cancel_appointment" -> cancelAppointment(input);
            case "add_medical_record" -> addMedicalRecord(input);
            case "medical_history" -> success("Medical history retrieved.",
                    medicalHistory(requiredId(input, "patient_id")));
            case "add_schedule" -> addSchedule(input);
            case "doctor_schedule" -> success("Schedule retrieved.",
                    jdbc.queryForList("""
                            SELECT id, day_name, start_time, end_time, status
                            FROM doctor_schedule WHERE doctor_id = ? ORDER BY id
                            """, requiredId(input, "doctor_id")));
            case "delete_schedule" -> deleteSchedule(input);
            case "dashboard_stats" -> success("Dashboard statistics retrieved.", dashboardStats());
            default -> ApiResponse.failure("Invalid or missing API action.");
        };
    }

    private ApiResponse register(Map<String, Object> input) {
        String name = value(input, "name").trim();
        String email = value(input, "email").trim().toLowerCase(Locale.ROOT);
        String phone = value(input, "phone").trim();
        String password = value(input, "password");
        String role = value(input, "role", "patient").trim().toLowerCase(Locale.ROOT);
        if (name.isEmpty() || email.isEmpty() || password.isEmpty()) {
            throw new IllegalArgumentException("Name, email and password are required.");
        }
        if (!email.matches("^[^\\s@]+@[^\\s@]+\\.[^\\s@]+$")) {
            throw new IllegalArgumentException("Invalid email address.");
        }
        if (password.length() < 6) {
            throw new IllegalArgumentException("Password must contain at least 6 characters.");
        }
        if (!List.of("patient", "doctor").contains(role)) {
            throw new IllegalArgumentException("Invalid role.");
        }
        if (jdbc.queryForObject("SELECT COUNT(*) FROM users WHERE email = ?", Integer.class, email) > 0) {
            throw new IllegalArgumentException("Email already registered.");
        }

        jdbc.update(
                "INSERT INTO users (full_name, email, phone, password, role) VALUES (?, ?, ?, ?, ?)",
                name, email, phone, passwordEncoder.encode(password), role);
        long userId = jdbc.queryForObject("SELECT id FROM users WHERE email = ?", Long.class, email);
        if ("patient".equals(role)) {
            jdbc.update("INSERT INTO patients (user_id) VALUES (?)", userId);
        } else {
            jdbc.update("INSERT INTO doctors (user_id) VALUES (?)", userId);
        }
        return success("Registration successful.", Map.of("user_id", userId));
    }

    private ApiResponse login(Map<String, Object> input) {
        String email = value(input, "email").trim().toLowerCase(Locale.ROOT);
        String password = value(input, "password");
        if (email.isEmpty() || password.isEmpty()) {
            throw new IllegalArgumentException("Email and password are required.");
        }

        List<Map<String, Object>> users = jdbc.queryForList("""
                SELECT u.id, u.full_name, u.email, u.phone, u.password, u.role, u.status,
                       p.id AS patient_id, d.id AS doctor_id
                FROM users u
                LEFT JOIN patients p ON p.user_id = u.id
                LEFT JOIN doctors d ON d.user_id = u.id
                WHERE u.email = ?
                """, email);
        if (users.isEmpty()) {
            throw new IllegalArgumentException("Invalid email or password.");
        }

        Map<String, Object> user = users.get(0);
        if (!"active".equals(user.get("status"))) {
            throw new IllegalArgumentException("Your account is inactive.");
        }
        if (!passwordMatches(password, String.valueOf(user.get("password")))) {
            throw new IllegalArgumentException("Invalid email or password.");
        }
        String requestedRole = value(input, "role").trim();
        if (!requestedRole.isEmpty() && !requestedRole.equals(user.get("role"))) {
            throw new IllegalArgumentException("The selected role does not match this account.");
        }

        user.remove("password");
        return success("Login successful.", user);
    }

    private ApiResponse setUserStatus(Map<String, Object> input, String status) {
        long userId = requiredId(input, "user_id");
        jdbc.update("UPDATE users SET status = ? WHERE id = ?", status, userId);
        return success("User " + ("active".equals(status) ? "activated." : "deactivated."), null);
    }

    private List<Map<String, Object>> getDoctors() {
        return jdbc.queryForList("""
                SELECT d.id AS doctor_id, u.id AS user_id, u.full_name, u.email, u.phone,
                       d.specialization, d.qualification, d.experience, d.hospital
                FROM doctors d
                INNER JOIN users u ON d.user_id = u.id
                WHERE u.status = 'active'
                ORDER BY u.full_name
                """);
    }

    private List<Map<String, Object>> getPatients() {
        return jdbc.queryForList("""
                SELECT p.id AS patient_id, u.id AS user_id, u.full_name, u.email, u.phone,
                       p.date_of_birth, p.gender, p.address, p.blood_group
                FROM patients p
                INNER JOIN users u ON p.user_id = u.id
                WHERE u.status = 'active'
                ORDER BY u.full_name
                """);
    }

    private ApiResponse updatePatient(Map<String, Object> input) {
        long userId = requiredId(input, "user_id");
        jdbc.update("UPDATE users SET full_name = ?, phone = ? WHERE id = ?",
                value(input, "name").trim(), value(input, "phone").trim(), userId);
        jdbc.update("""
                UPDATE patients
                SET date_of_birth = ?, gender = ?, address = ?, blood_group = ?
                WHERE user_id = ?
                """,
                nullableDate(input, "date_of_birth"),
                value(input, "gender").trim(),
                value(input, "address").trim(),
                value(input, "blood_group").trim(),
                userId);
        return success("Patient profile updated.", null);
    }

    private ApiResponse updateDoctor(Map<String, Object> input) {
        long userId = requiredId(input, "user_id");
        jdbc.update("UPDATE users SET full_name = ?, phone = ? WHERE id = ?",
                value(input, "name").trim(), value(input, "phone").trim(), userId);
        jdbc.update("""
                UPDATE doctors
                SET specialization = ?, qualification = ?, experience = ?, hospital = ?, about = ?
                WHERE user_id = ?
                """,
                value(input, "specialization").trim(),
                value(input, "qualification").trim(),
                value(input, "experience").trim(),
                value(input, "hospital").trim(),
                value(input, "about").trim(),
                userId);
        return success("Doctor profile updated.", null);
    }

    private ApiResponse bookAppointment(Map<String, Object> input) {
        long patientId = requiredId(input, "patient_id");
        long doctorId = requiredId(input, "doctor_id");
        LocalDate date = parseDate(value(input, "appointment_date"), "appointment date");
        LocalTime time = parseTime(value(input, "appointment_time"), "appointment time");
        Integer count = jdbc.queryForObject("""
                SELECT COUNT(*) FROM appointments
                WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ?
                  AND status <> 'cancelled'
                """, Integer.class, doctorId, Date.valueOf(date), Time.valueOf(time));
        if (count != null && count > 0) {
            throw new IllegalArgumentException("This appointment slot is already booked.");
        }
        jdbc.update("""
                INSERT INTO appointments
                    (patient_id, doctor_id, appointment_date, appointment_time, appointment_type, reason)
                VALUES (?, ?, ?, ?, ?, ?)
                """,
                patientId, doctorId, Date.valueOf(date), Time.valueOf(time),
                value(input, "appointment_type").trim(), value(input, "reason").trim());
        Long appointmentId = jdbc.queryForObject("SELECT LAST_INSERT_ID()", Long.class);
        return success("Appointment booked successfully.", Map.of("appointment_id", appointmentId));
    }

    private List<Map<String, Object>> getPatientAppointments(long patientId) {
        return jdbc.queryForList("""
                SELECT a.id, a.appointment_date, a.appointment_time, a.appointment_type,
                       a.reason, a.status, u.full_name AS doctor_name, d.specialization
                FROM appointments a
                INNER JOIN doctors d ON a.doctor_id = d.id
                INNER JOIN users u ON d.user_id = u.id
                WHERE a.patient_id = ?
                ORDER BY a.appointment_date DESC
                """, patientId);
    }

    private List<Map<String, Object>> getDoctorAppointments(long doctorId) {
        return jdbc.queryForList("""
                SELECT a.id, a.appointment_date, a.appointment_time, a.appointment_type,
                       a.reason, a.status, p.id AS patient_id,
                       u.full_name AS patient_name, u.phone AS patient_phone
                FROM appointments a
                INNER JOIN patients p ON a.patient_id = p.id
                INNER JOIN users u ON p.user_id = u.id
                WHERE a.doctor_id = ?
                ORDER BY a.appointment_date ASC
                """, doctorId);
    }

    private ApiResponse updateAppointmentStatus(Map<String, Object> input) {
        long appointmentId = requiredId(input, "appointment_id");
        String status = value(input, "status").trim().toLowerCase(Locale.ROOT);
        if (!List.of("pending", "confirmed", "cancelled", "completed").contains(status)) {
            throw new IllegalArgumentException("Invalid appointment status.");
        }
        jdbc.update("UPDATE appointments SET status = ? WHERE id = ?", status, appointmentId);
        return success("Appointment status updated.", null);
    }

    private ApiResponse cancelAppointment(Map<String, Object> input) {
        jdbc.update("UPDATE appointments SET status = 'cancelled' WHERE id = ?",
                requiredId(input, "appointment_id"));
        return success("Appointment cancelled.", null);
    }

    private ApiResponse addMedicalRecord(Map<String, Object> input) {
        jdbc.update("""
                INSERT INTO medical_records
                    (patient_id, doctor_id, diagnosis, prescription, notes, record_date)
                VALUES (?, ?, ?, ?, ?, ?)
                """,
                requiredId(input, "patient_id"),
                requiredId(input, "doctor_id"),
                value(input, "diagnosis").trim(),
                value(input, "prescription").trim(),
                value(input, "notes").trim(),
                Date.valueOf(input.containsKey("record_date")
                        ? parseDate(value(input, "record_date"), "record date")
                        : LocalDate.now()));
        return success("Medical record added.", null);
    }

    private List<Map<String, Object>> medicalHistory(long patientId) {
        return jdbc.queryForList("""
                SELECT m.id, m.diagnosis, m.prescription, m.notes, m.record_date,
                       u.full_name AS doctor_name, d.specialization
                FROM medical_records m
                INNER JOIN doctors d ON m.doctor_id = d.id
                INNER JOIN users u ON d.user_id = u.id
                WHERE m.patient_id = ?
                ORDER BY m.record_date DESC
                """, patientId);
    }

    private ApiResponse addSchedule(Map<String, Object> input) {
        jdbc.update("""
                INSERT INTO doctor_schedule (doctor_id, day_name, start_time, end_time, status)
                VALUES (?, ?, ?, ?, ?)
                """,
                requiredId(input, "doctor_id"),
                value(input, "day_name").trim(),
                Time.valueOf(parseTime(value(input, "start_time"), "start time")),
                Time.valueOf(parseTime(value(input, "end_time"), "end time")),
                "unavailable".equals(value(input, "status")) ? "unavailable" : "available");
        return success("Schedule added.", null);
    }

    private ApiResponse deleteSchedule(Map<String, Object> input) {
        jdbc.update("DELETE FROM doctor_schedule WHERE id = ?", requiredId(input, "schedule_id"));
        return success("Schedule deleted.", null);
    }

    private Map<String, Object> dashboardStats() {
        return Map.of(
                "patients", count("SELECT COUNT(*) FROM patients"),
                "doctors", count("SELECT COUNT(*) FROM doctors"),
                "users", count("SELECT COUNT(*) FROM users"),
                "appointments", count("SELECT COUNT(*) FROM appointments"),
                "pending_appointments", count("SELECT COUNT(*) FROM appointments WHERE status = 'pending'"));
    }

    private int count(String sql) {
        Integer result = jdbc.queryForObject(sql, Integer.class);
        return result == null ? 0 : result;
    }

    private boolean passwordMatches(String rawPassword, String encodedPassword) {
        if (encodedPassword.startsWith("$2y$")) {
            encodedPassword = "$2a$" + encodedPassword.substring(4);
        }
        return passwordEncoder.matches(rawPassword, encodedPassword);
    }

    private static ApiResponse success(String message, Object data) {
        return ApiResponse.success(message, data);
    }

    private static String value(Map<String, Object> input, String key) {
        return value(input, key, "");
    }

    private static String value(Map<String, Object> input, String key, String defaultValue) {
        Object result = input.get(key);
        return result == null ? defaultValue : String.valueOf(result);
    }

    private static long requiredId(Map<String, Object> input, String key) {
        try {
            long id = Long.parseLong(value(input, key));
            if (id > 0) {
                return id;
            }
        } catch (NumberFormatException ignored) {
            // Return the same invalid-ID response for missing and malformed identifiers.
        }
        throw new IllegalArgumentException("Invalid " + key.replace('_', ' ') + ".");
    }

    private static LocalDate parseDate(String value, String field) {
        try {
            return LocalDate.parse(value);
        } catch (RuntimeException exception) {
            throw new IllegalArgumentException("Invalid " + field + ".");
        }
    }

    private static LocalTime parseTime(String value, String field) {
        try {
            return LocalTime.parse(value);
        } catch (RuntimeException exception) {
            throw new IllegalArgumentException("Invalid " + field + ".");
        }
    }

    private static Date nullableDate(Map<String, Object> input, String key) {
        String value = value(input, key).trim();
        return value.isEmpty() ? null : Date.valueOf(parseDate(value, key.replace('_', ' ')));
    }
}
