<?php
// db.php - Database connection and table setup for HeatWatch

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'heatwatch');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->query("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$conn->select_db(DB_NAME);

// users table
$conn->query("CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// barangays table
$conn->query("CREATE TABLE IF NOT EXISTS barangays (
    id INT AUTO_INCREMENT PRIMARY KEY,
    barangay_name VARCHAR(100) NOT NULL,
    description TEXT
)");

// residents table
$conn->query("CREATE TABLE IF NOT EXISTS residents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    age INT NOT NULL,
    address TEXT,
    zone_id INT,
    medical_condition TEXT,
    is_vulnerable TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (zone_id) REFERENCES barangays(id) ON DELETE SET NULL
)");

// heat index logs table
$conn->query("CREATE TABLE IF NOT EXISTS heat_index_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    log_date DATE NOT NULL,
    temperature DECIMAL(5,2) NOT NULL,
    humidity DECIMAL(5,2),
    heat_level VARCHAR(20),
    recorded_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
)");

// wellness checks table
$conn->query("CREATE TABLE IF NOT EXISTS wellness_checks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    resident_id INT NOT NULL,
    check_date DATE NOT NULL,
    status ENUM('Good','Needs Monitoring','Referred') NOT NULL DEFAULT 'Good',
    notes TEXT,
    checked_by INT,
    FOREIGN KEY (resident_id) REFERENCES residents(id) ON DELETE CASCADE,
    FOREIGN KEY (checked_by) REFERENCES users(id) ON DELETE SET NULL
)");

// illness cases table
$conn->query("CREATE TABLE IF NOT EXISTS illness_cases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    resident_id INT NOT NULL,
    case_date DATE NOT NULL,
    illness_type VARCHAR(100) NOT NULL,
    outcome VARCHAR(100),
    notes TEXT,
    FOREIGN KEY (resident_id) REFERENCES residents(id) ON DELETE CASCADE
)");

// Seed: admin user
$check = $conn->query("SELECT id FROM users WHERE username='admin'");
if ($check->num_rows === 0) {
    $hash = password_hash('admin123', PASSWORD_DEFAULT);
    $conn->query("INSERT INTO users (username, password, full_name) VALUES ('admin', '$hash', 'Health Worker Admin')");
}

// Seed: barangays
$bcheck = $conn->query("SELECT id FROM barangays LIMIT 1");
if ($bcheck->num_rows === 0) {
    $conn->query("INSERT INTO barangays (barangay_name, description) VALUES
        ('Agusan Canyon', 'Mountainous barangay in the upper watershed area'),
        ('Damilag', 'Agricultural barangay known for cool climate'),
        ('Alae', 'Forested barangay along the river valley'),
        ('Tankulan', 'Upland barangay with farming communities'),
        ('Diclum', 'Remote highland barangay near the forest boundary')
    ");
}

// Seed: sample heat index logs (added this commit)
$hcheck = $conn->query("SELECT id FROM heat_index_logs LIMIT 1");
if ($hcheck->num_rows === 0) {
    $adminId = $conn->query("SELECT id FROM users WHERE username='admin'")->fetch_assoc()['id'];
    $samples = [
        ['2025-04-20', 30, 65, 'Caution'],
        ['2025-04-21', 34, 70, 'Extreme Caution'],
        ['2025-04-22', 38, 72, 'Extreme Caution'],
        ['2025-04-23', 43, 75, 'Danger'],
        ['2025-04-24', 36, 68, 'Extreme Caution'],
        ['2025-04-25', 31, 60, 'Caution'],
        ['2025-04-26', 45, 80, 'Danger'],
        [date('Y-m-d'), 39, 74, 'Extreme Caution'],
    ];
    foreach ($samples as $s) {
        $conn->query("INSERT INTO heat_index_logs (log_date,temperature,humidity,heat_level,recorded_by) VALUES ('$s[0]',$s[1],$s[2],'$s[3]',$adminId)");
    }
}

// Helper: heat level based on temperature
function getHeatLevel($temp) {
    if ($temp >= 52) return 'Extreme Danger';
    if ($temp >= 42) return 'Danger';
    if ($temp >= 33) return 'Extreme Caution';
    if ($temp >= 27) return 'Caution';
    return 'Normal';
}

// Helper: check if resident is vulnerable
function isVulnerable($age, $medical_condition) {
    return ($age >= 60 || $age < 5 || !empty(trim($medical_condition)));
}

// Helper: require login session
function requireLogin() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['user_id'])) {
        header('Location: index.php');
        exit;
    }
}
?>
