# CuraTech Healthcare Management System

The existing HTML/CSS/JavaScript interface now uses a Java Spring Boot API and
the XAMPP MySQL/MariaDB database.

## Requirements

- XAMPP MySQL/MariaDB
- Java 17 or later
- Internet access on the first Maven Wrapper run to download Maven/dependencies

The backend reads its database settings from environment variables. Defaults
match this XAMPP installation:

- JDBC URL: `jdbc:mysql://localhost:3307/healthcare_management`
- Username: `root`
- Password: empty

Import `Database/healthcare_management.sql` with phpMyAdmin if the database and
tables have not already been created.

## Start the Java backend and frontend

1. Start **MySQL** in the XAMPP Control Panel.
2. Open PowerShell in `C:\xampp\htdocs\Curatech`.
3. Start the Spring Boot app:

   ```powershell
   .\mvnw.cmd spring-boot:run
   ```

4. Open `http://localhost:8080/frontened/curatech.html`.
5. Log in with the account email and password. The account role is detected
   automatically:
   - Email: `admin@curatech.com`
   - Password: `admin123`
6. Other people can choose **Register**, enter their own personal email
   (including Gmail), choose a password and patient/doctor account type, and
   the app will create the account in MySQL and sign them in.

Spring Boot serves the existing frontend from `frontened/` and exposes the API
at `http://localhost:8080/backend/backend.php`. Apache and the PHP built-in
server are not needed. Keep the Spring Boot terminal open while using the site;
press `Ctrl+C` to stop it.

To override database configuration in PowerShell:

```powershell
$env:DB_URL = 'jdbc:mysql://localhost:3307/healthcare_management'
$env:DB_USERNAME = 'root'
$env:DB_PASSWORD = ''
.\mvnw.cmd spring-boot:run
```

Email addresses are account usernames; users create a CuraTech password. The
app does not use Google OAuth and does not check whether the email inbox is
owned or verified. The old `backend/backend.php` remains in the project as a
legacy file, but the frontend now calls the Spring Boot backend.
