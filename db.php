<?php
// ============================================================
// db.php — HeatWatch Database Connection and Schema Setup
// This file connects to MySQL database using XAMPP
// It also creates all tables automatically if they dont exist
// ============================================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');           // XAMPP default: empty password
define('DB_NAME', 'heatwatch');

// Create connection (without DB first, to create DB if needed)
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS);
if ($conn->connect_error) {
    die("
<!DOCTYPE html>
<html lang='en'>
<head>
<meta charset='UTF-8'>
<meta name='viewport' content='width=device-width,initial-scale=1.0'>
<title>HeatWatch — Connection Error</title>
<link href='https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@700;800&display=swap' rel='stylesheet'>
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{
  --fire:#FF4C1C;--fire2:#FF8C00;
  --deep:#080604;--surface:#0F0B08;--card:#1A1410;
  --border:#2C2018;--text:#F2E8DC;--muted:#7A6450;--dim:#4A3828;
}
body{
  font-family:'Space Mono',monospace;background:var(--deep);color:var(--text);
  min-height:100vh;display:flex;align-items:center;justify-content:center;
  padding:2rem;
}
.err-box{
  background:var(--card);border:1px solid var(--border);border-radius:6px;
  max-width:520px;width:100%;overflow:hidden;
  box-shadow:0 24px 64px rgba(0,0,0,.5);
}
.err-head{
  padding:1rem 1.4rem;border-bottom:1px solid var(--border);
  background:var(--surface);display:flex;align-items:center;gap:.8rem;
}
.err-icon{
  width:32px;height:32px;background:rgba(244,67,54,.12);
  border:1px solid rgba(244,67,54,.25);border-radius:4px;
  display:flex;align-items:center;justify-content:center;font-size:.9rem;
}
.err-title{font-family:'Syne',sans-serif;font-weight:800;font-size:.9rem;letter-spacing:.5px;}
.err-code{margin-left:auto;font-size:.6rem;color:var(--dim);border:1px solid var(--border);padding:.1rem .4rem;border-radius:2px;letter-spacing:2px;}
.err-body{padding:1.6rem 1.4rem;}
.err-label{font-size:.6rem;letter-spacing:3px;text-transform:uppercase;color:var(--muted);margin-bottom:.5rem;}
.err-label::before{content:'// ';color:var(--fire);opacity:.5;}
.err-msg{
  font-size:.8rem;color:#EF5350;line-height:1.6;
  background:rgba(244,67,54,.06);border-left:3px solid #f44336;
  padding:.8rem 1rem;border-radius:0 3px 3px 0;margin-bottom:1.4rem;
  word-break:break-all;
}
.err-steps{margin-bottom:0;}
.err-steps li{
  font-size:.75rem;color:var(--muted);padding:.35rem 0;
  border-bottom:1px solid rgba(255,255,255,.04);
  padding-left:.8rem;position:relative;
}
.err-steps li::before{content:'→';position:absolute;left:0;color:var(--fire);opacity:.6;}
.err-steps li:last-child{border-bottom:none;}
</style>
</head>
<body>
<div class='err-box'>
  <div class='err-head'>
    <div class='err-icon'>⚠</div>
    <div class='err-title'>Database Connection Failed</div>
    <div class='err-code'>DB-ERR</div>
  </div>
  <div class='err-body'>
    <div class='err-label'>Error Details</div>
    <div class='err-msg'>" . htmlspecialchars($conn->connect_error) . "</div>
    <div class='err-label'>Troubleshooting Steps</div>
    <ul class='err-steps'>
      <li>Open XAMPP Control Panel and make sure MySQL is running</li>
      <li>Verify DB_HOST is set to <strong>localhost</strong></li>
      <li>Check that DB_USER is <strong>root</strong> and DB_PASS is empty (XAMPP default)</li>
      <li>Confirm port 3306 is not blocked or in use by another process</li>
    </ul>
  </div>
</div>
</body>
</html>
    ");
}

// Create database if it doesn't exist
$conn->query("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$conn->select_db(DB_NAME);

// ── TABLE: users ──────────────────────────────────────────────
$conn->query("CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// ── TABLE: barangays ──────────────────────────────────────────────
$conn->query("CREATE TABLE IF NOT EXISTS barangays (
    id INT AUTO_INCREMENT PRIMARY KEY,
    barangay_name VARCHAR(100) NOT NULL,
    description TEXT
)");

// ── TABLE: residents ──────────────────────────────────────────
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

// ── TABLE: heat_index_logs ────────────────────────────────────
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

// ── TABLE: wellness_checks ────────────────────────────────────
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

// ── TABLE: illness_cases ──────────────────────────────────────
$conn->query("CREATE TABLE IF NOT EXISTS illness_cases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    resident_id INT NOT NULL,
    case_date DATE NOT NULL,
    illness_type VARCHAR(100) NOT NULL,
    outcome VARCHAR(100),
    notes TEXT,
    FOREIGN KEY (resident_id) REFERENCES residents(id) ON DELETE CASCADE
)");

// ── SEED: Default admin account (password: admin123) ─────────
$check = $conn->query("SELECT id FROM users WHERE username='admin'");
if ($check->num_rows === 0) {
    $hash = password_hash('admin123', PASSWORD_DEFAULT);
    $conn->query("INSERT INTO users (username, password, full_name) VALUES ('admin', '$hash', 'Health Worker Admin')");
}

// ── SEED: Default barangays ───────────────────────────────────────
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

// ── SEED: Sample heat index logs ─────────────────────────────
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
        $conn->query("INSERT INTO heat_index_logs (log_date, temperature, humidity, heat_level, recorded_by)
            VALUES ('$s[0]', $s[1], $s[2], '$s[3]', $adminId)");
    }
}

// ── HELPER: Compute heat level from temperature ───────────────
function getHeatLevel($temp) {
    if ($temp >= 52) return 'Extreme Danger';
    if ($temp >= 42) return 'Danger';
    if ($temp >= 33) return 'Extreme Caution';
    if ($temp >= 27) return 'Caution';
    return 'Normal';
}

// ── HELPER: Flag vulnerable residents ─────────────────────────
function isVulnerable($age, $medical_condition) {
    return ($age >= 60 || $age < 5 || !empty(trim($medical_condition)));
}

// ── Session helper ─────────────────────────────────────────────
function requireLogin() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['user_id'])) {
        header('Location: index.php');
        exit;
    }
}
?>