<?php
// db.php - Database connection for HeatWatch
// This file connects to the MySQL database

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
$conn->query("CREATE DATABASE IF NOT EXISTS " . DB_NAME);
$conn->select_db(DB_NAME);

// Create users table
$conn->query("CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Default admin account
$check = $conn->query("SELECT id FROM users WHERE username='admin'");
if ($check->num_rows === 0) {
    $hash = password_hash('admin123', PASSWORD_DEFAULT);
    $conn->query("INSERT INTO users (username, password, full_name) VALUES ('admin', '$hash', 'Admin')");
}

echo "Database connected!";
?>
