<?php
// db.php - Database connection and table setup for HeatWatch

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'heatwatch');

// Connect to MySQL
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database if it does not exist
$conn->query("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$conn->select_db(DB_NAME);

// Create users table
$conn->query("CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Create barangays table
$conn->query("CREATE TABLE IF NOT EXISTS barangays (
    id INT AUTO_INCREMENT PRIMARY KEY,
    barangay_name VARCHAR(100) NOT NULL,
    description TEXT
)");

// Create residents table
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

// Create heat index logs table
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

// Create wellness checks table
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

// Create illness cases table
$conn->query("CREATE TABLE IF NOT EXISTS illness_cases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    resident_id INT NOT NULL,
    case_date DATE NOT NULL,
    illness_type VARCHAR(100) NOT NULL,
    outcome VARCHAR(100),
    notes TEXT,
    FOREIGN KEY (resident_id) REFERENCES residents(id) ON DELETE CASCADE
)");

// Add default admin account
$check = $conn->query("SELECT id FROM users WHERE username='admin'");
if ($check->num_rows === 0) {
    $hash = password_hash('admin123', PASSWORD_DEFAULT);
    $conn->query("INSERT INTO users (username, password, full_name) VALUES ('admin', '$hash', 'Health Worker Admin')");
}

// Add default barangays
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

// Helper: get heat level based on temperature
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

// Helper: check if user is logged in
function requireLogin() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['user_id'])) {
        header('Location: index.php');
        exit;
    }
}
?>
