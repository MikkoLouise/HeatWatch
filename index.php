<?php
// index.php - HeatWatch Login Page
session_start();

// If already logged in, go to dashboard
if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

require_once 'db.php';

$error = '';

// Check login when form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $result = $conn->query("SELECT * FROM users WHERE username='" . $conn->real_escape_string($username) . "' LIMIT 1");

    if ($result && $row = $result->fetch_assoc()) {
        if (password_verify($password, $row['password'])) {
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['user_name'] = $row['full_name'];
            $_SESSION['username'] = $row['username'];
            header('Location: dashboard.php');
            exit;
        }
    }

    $error = 'Invalid username or password.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HeatWatch - Login</title>
<style>
  body {
    font-family: monospace;
    background: #111;
    color: #fff;
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100vh;
    margin: 0;
  }
  .box {
    background: #222;
    padding: 2rem;
    border-radius: 8px;
    width: 320px;
    border: 1px solid #333;
  }
  h2 {
    margin-bottom: 0.3rem;
    color: #ff4c1c;
    font-size: 1.5rem;
  }
  .subtitle {
    color: #888;
    font-size: 0.8rem;
    margin-bottom: 1.5rem;
  }
  label {
    font-size: 0.75rem;
    color: #aaa;
    display: block;
    margin-bottom: 0.3rem;
    text-transform: uppercase;
    letter-spacing: 1px;
  }
  input {
    width: 100%;
    padding: 0.6rem;
    margin-bottom: 1rem;
    background: #333;
    border: 1px solid #444;
    color: #fff;
    border-radius: 4px;
    box-sizing: border-box;
    font-family: monospace;
  }
  input:focus {
    outline: none;
    border-color: #ff4c1c;
  }
  button {
    width: 100%;
    padding: 0.7rem;
    background: #ff4c1c;
    border: none;
    color: #fff;
    border-radius: 4px;
    cursor: pointer;
    font-family: monospace;
    font-size: 0.9rem;
    letter-spacing: 1px;
    text-transform: uppercase;
  }
  button:hover {
    background: #ff6600;
  }
  .error {
    background: rgba(255,76,28,0.1);
    border-left: 3px solid #ff4c1c;
    padding: 0.5rem 0.8rem;
    margin-bottom: 1rem;
    font-size: 0.8rem;
    color: #ff7755;
  }
  .hint {
    margin-top: 1rem;
    font-size: 0.7rem;
    color: #555;
    border-top: 1px solid #333;
    padding-top: 1rem;
  }
</style>
</head>
<body>
<div class="box">
  <h2>HeatWatch</h2>
  <p class="subtitle">Barangay Health Monitoring System</p>

  <?php if ($error): ?>
  <div class="error">⚠ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST">
    <label>Username</label>
    <input type="text" name="username" placeholder="Enter username" required autocomplete="username">

    <label>Password</label>
    <input type="password" name="password" placeholder="Password" required autocomplete="current-password">

    <button type="submit">Login →</button>
  </form>

  <div class="hint">
    Default: username = <strong>admin</strong> / password = <strong>admin123</strong>
  </div>
</div>
</body>
</html>
