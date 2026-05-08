<?php
// dashboard.php - HeatWatch Main Dashboard
require_once 'db.php';
requireLogin();

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

// Get total number of residents
$totalResidents = $conn->query("SELECT COUNT(*) AS c FROM residents")->fetch_assoc()['c'];

// Get number of vulnerable residents
$vulnerableCount = $conn->query("SELECT COUNT(*) AS c FROM residents WHERE is_vulnerable=1")->fetch_assoc()['c'];

// Get today's heat reading
$todayHeat = $conn->query("SELECT temperature, heat_level FROM heat_index_logs WHERE log_date=CURDATE() ORDER BY id DESC LIMIT 1")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HeatWatch - Dashboard</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    font-family: monospace;
    background: #0f0b08;
    color: #f2e8dc;
    min-height: 100vh;
  }
  .navbar {
    background: #1a1410;
    border-bottom: 1px solid #2c2018;
    padding: 1rem 2rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
  }
  .navbar h1 {
    color: #ff4c1c;
    font-size: 1.2rem;
  }
  .navbar a {
    color: #aaa;
    text-decoration: none;
    font-size: 0.8rem;
    margin-left: 1rem;
  }
  .navbar a:hover { color: #ff4c1c; }
  .page {
    padding: 2rem;
  }
  h2 {
    margin-bottom: 1.5rem;
    color: #f2e8dc;
    font-size: 1.3rem;
  }
  .stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
    margin-bottom: 2rem;
  }
  .stat-card {
    background: #1a1410;
    border: 1px solid #2c2018;
    border-radius: 6px;
    padding: 1.5rem;
  }
  .stat-card .number {
    font-size: 2.5rem;
    font-weight: bold;
    color: #ff4c1c;
  }
  .stat-card .label {
    font-size: 0.75rem;
    color: #7a6450;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-top: 0.3rem;
  }
  .links {
    margin-top: 1.5rem;
  }
  .links a {
    display: inline-block;
    padding: 0.6rem 1.2rem;
    background: #ff4c1c;
    color: #fff;
    text-decoration: none;
    border-radius: 4px;
    margin-right: 0.5rem;
    font-size: 0.8rem;
  }
</style>
</head>
<body>

<div class="navbar">
  <h1>🌡️ HeatWatch</h1>
  <div>
    <a href="manage.php">Manage Data</a>
    <a href="?logout=1">Logout</a>
  </div>
</div>

<div class="page">
  <h2>Dashboard — Welcome, <?= htmlspecialchars($_SESSION['user_name']) ?></h2>

  <div class="stats">
    <div class="stat-card">
      <div class="number"><?= $todayHeat ? $todayHeat['temperature'] . '°C' : 'N/A' ?></div>
      <div class="label">Today's Heat Index</div>
    </div>
    <div class="stat-card">
      <div class="number"><?= $totalResidents ?></div>
      <div class="label">Total Residents</div>
    </div>
    <div class="stat-card">
      <div class="number"><?= $vulnerableCount ?></div>
      <div class="label">Vulnerable Residents</div>
    </div>
  </div>

  <div class="links">
    <a href="manage.php?section=residents">Manage Residents</a>
    <a href="manage.php?section=heat">Log Heat Index</a>
    <a href="manage.php?section=wellness">Wellness Checks</a>
  </div>
</div>

</body>
</html>
