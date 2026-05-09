<?php
// dashboard.php - HeatWatch Dashboard with Charts
require_once 'db.php';
requireLogin();

if (isset($_GET['logout'])) { session_destroy(); header('Location: index.php'); exit; }

// Get stats
$todayHeat = $conn->query("SELECT temperature, heat_level FROM heat_index_logs WHERE log_date=CURDATE() ORDER BY id DESC LIMIT 1")->fetch_assoc();
$totalResidents = $conn->query("SELECT COUNT(*) AS c FROM residents")->fetch_assoc()['c'];
$vulnerableCount = $conn->query("SELECT COUNT(*) AS c FROM residents WHERE is_vulnerable=1")->fetch_assoc()['c'];
$illnessCases = $conn->query("SELECT COUNT(*) AS c FROM illness_cases")->fetch_assoc()['c'];
$wellnessChecks = $conn->query("SELECT COUNT(*) AS c FROM wellness_checks")->fetch_assoc()['c'];

// Data for trend chart
$trendResult = $conn->query("SELECT log_date, temperature FROM heat_index_logs ORDER BY log_date ASC LIMIT 8");
$trendDates = []; $trendTemps = [];
while ($row = $trendResult->fetch_assoc()) {
    $trendDates[] = date('M d', strtotime($row['log_date']));
    $trendTemps[] = $row['temperature'];
}

// Data for illness chart
$illnessResult = $conn->query("SELECT illness_type, COUNT(*) AS c FROM illness_cases GROUP BY illness_type");
$illnessLabels = []; $illnessCounts = [];
while ($row = $illnessResult->fetch_assoc()) { $illnessLabels[] = $row['illness_type']; $illnessCounts[] = $row['c']; }
if (empty($illnessLabels)) { $illnessLabels = ['No Data']; $illnessCounts = [1]; }

$todayLevel = $todayHeat['heat_level'] ?? 'Normal';
$heatColor = ['Normal'=>'#4caf50','Caution'=>'#ffc107','Extreme Caution'=>'#ff9800','Danger'=>'#f44336','Extreme Danger'=>'#9c27b0'];
$todayColor = $heatColor[$todayLevel] ?? '#888';

// Get resident list for table
$monitorQuery = $conn->query("
    SELECT r.id, r.name, r.age, r.is_vulnerable, r.medical_condition,
        b.barangay_name AS zone_name, wc.status AS check_status, wc.check_date
    FROM residents r
    LEFT JOIN barangays b ON r.zone_id = b.id
    LEFT JOIN wellness_checks wc ON wc.resident_id = r.id
    ORDER BY r.is_vulnerable DESC, r.name ASC
    LIMIT 20
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HeatWatch - Dashboard</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:monospace;background:#080604;color:#f2e8dc;min-height:100vh}
  .topnav{
    position:fixed;top:0;left:0;right:0;height:60px;z-index:100;
    background:#0f0b08;border-bottom:1px solid #2c2018;
    display:flex;align-items:center;padding:0 1.5rem;gap:1rem;
  }
  .nav-brand{color:#ff4c1c;font-size:1.1rem;font-weight:bold;margin-right:1.5rem}
  .nav-link{color:#7a6450;text-decoration:none;font-size:0.75rem;text-transform:uppercase;letter-spacing:1px;padding:0 0.8rem}
  .nav-link:hover{color:#ff4c1c}
  .nav-link.active{color:#ff4c1c}
  .nav-right{margin-left:auto}
  .nav-logout{padding:0.4rem 0.9rem;background:rgba(255,76,28,0.1);border:1px solid rgba(255,76,28,0.25);border-radius:3px;color:#ff4c1c;font-size:0.7rem;text-decoration:none;text-transform:uppercase;letter-spacing:1px}
  .page{margin-top:60px;padding:2rem}
  h2{font-size:1.4rem;margin-bottom:0.3rem}
  .page-sub{font-size:0.7rem;color:#7a6450;margin-bottom:2rem;letter-spacing:1px}
  .alert-bar{
    display:flex;align-items:center;gap:0.8rem;padding:0.7rem 1.2rem;margin-bottom:1.5rem;
    background:rgba(255,76,28,0.06);border:1px solid #2c2018;border-radius:4px;
    border-left:3px solid <?=$todayColor?>;
  }
  .alert-level{color:<?=$todayColor?>;font-weight:bold;font-size:0.85rem}
  .alert-text{font-size:0.75rem;color:#7a6450}
  .stat-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:1px;background:#2c2018;border:1px solid #2c2018;border-radius:6px;overflow:hidden;margin-bottom:1.5rem}
  .stat{background:#1a1410;padding:1.4rem;position:relative}
  .stat-num{font-size:2.2rem;font-weight:bold;color:#f2e8dc;line-height:1;margin-bottom:0.4rem}
  .stat-num.heat{color:<?=$todayColor?>}
  .stat-label{font-size:0.65rem;text-transform:uppercase;letter-spacing:2px;color:#7a6450}
  .stat-sub{font-size:0.68rem;color:#4a3828;margin-top:0.3rem}
  .chart-row{display:grid;grid-template-columns:2fr 1fr;gap:1rem;margin-bottom:1.5rem}
  .chart-box{background:#1a1410;border:1px solid #2c2018;border-radius:6px;padding:1.2rem}
  .chart-title{font-size:0.75rem;text-transform:uppercase;letter-spacing:2px;color:#7a6450;margin-bottom:1rem}
  .chart-area{height:180px;position:relative}
  h3{font-size:0.9rem;text-transform:uppercase;letter-spacing:2px;color:#7a6450;margin-bottom:1rem;padding-bottom:0.5rem;border-bottom:1px solid #2c2018}
  table{width:100%;border-collapse:collapse;font-size:0.82rem}
  th{text-align:left;padding:0.6rem 0.8rem;background:#1a1410;color:#7a6450;font-size:0.68rem;text-transform:uppercase;letter-spacing:1px;border-bottom:1px solid #2c2018}
  td{padding:0.65rem 0.8rem;border-bottom:1px solid #1a1410}
  .badge-vuln{background:rgba(244,67,54,0.15);color:#ef5350;font-size:0.65rem;padding:0.1rem 0.4rem;border-radius:2px}
  .badge-good{background:rgba(76,175,80,0.15);color:#4caf50;font-size:0.65rem;padding:0.1rem 0.4rem;border-radius:2px}
  .badge-warn{background:rgba(255,193,7,0.15);color:#ffc107;font-size:0.65rem;padding:0.1rem 0.4rem;border-radius:2px}
</style>
</head>
<body>

<div class="topnav">
  <div class="nav-brand">🌡️ HeatWatch</div>
  <a href="dashboard.php" class="nav-link active">Dashboard</a>
  <a href="manage.php" class="nav-link">Manage</a>
  <div class="nav-right">
    <a href="?logout=1" class="nav-logout">Logout</a>
  </div>
</div>

<div class="page">
  <h2>Dashboard</h2>
  <div class="page-sub"><?= date('D, d M Y') ?> — Logged in as <?= htmlspecialchars($_SESSION['user_name']) ?></div>

  <!-- Alert Bar -->
  <div class="alert-bar">
    <span style="font-size:0.7rem;color:#7a6450;text-transform:uppercase;letter-spacing:1px">Today:</span>
    <span class="alert-level"><?= $todayLevel ?></span>
    <span class="alert-text"><?= $todayHeat ? $todayHeat['temperature'].'°C recorded today' : 'No reading today' ?></span>
  </div>

  <!-- Stats -->
  <div class="stat-grid">
    <div class="stat">
      <div class="stat-num heat"><?= $todayHeat ? $todayHeat['temperature'].'°' : 'N/A' ?></div>
      <div class="stat-label">Heat Index</div>
      <div class="stat-sub"><?= htmlspecialchars($todayLevel) ?></div>
    </div>
    <div class="stat">
      <div class="stat-num"><?= $totalResidents ?></div>
      <div class="stat-label">Residents</div>
      <div class="stat-sub"><?= $vulnerableCount ?> vulnerable</div>
    </div>
    <div class="stat">
      <div class="stat-num"><?= $illnessCases ?></div>
      <div class="stat-label">Illness Cases</div>
      <div class="stat-sub">Heat-related</div>
    </div>
    <div class="stat">
      <div class="stat-num"><?= $wellnessChecks ?></div>
      <div class="stat-label">Wellness Checks</div>
      <div class="stat-sub">Total done</div>
    </div>
  </div>

  <!-- Charts -->
  <div class="chart-row">
    <div class="chart-box">
      <div class="chart-title">Heat Index Trend (Last 8 Records)</div>
      <div class="chart-area"><canvas id="trendChart"></canvas></div>
    </div>
    <div class="chart-box">
      <div class="chart-title">Illness Types</div>
      <div class="chart-area"><canvas id="illnessChart"></canvas></div>
    </div>
  </div>

  <!-- Table -->
  <h3>Resident Monitor</h3>
  <table>
    <thead>
      <tr><th>Name</th><th>Age</th><th>Barangay</th><th>Condition</th><th>Last Check</th><th>Status</th></tr>
    </thead>
    <tbody>
      <?php while($r = $monitorQuery->fetch_assoc()): ?>
      <tr>
        <td>
          <?= htmlspecialchars($r['name']) ?>
          <?php if($r['is_vulnerable']): ?> <span class="badge-vuln">⚠ Vulnerable</span><?php endif; ?>
        </td>
        <td><?= $r['age'] ?></td>
        <td><?= htmlspecialchars($r['zone_name'] ?? '—') ?></td>
        <td style="color:#7a6450;font-size:0.78rem"><?= $r['medical_condition'] ? htmlspecialchars(substr($r['medical_condition'],0,25)) : '—' ?></td>
        <td style="color:#4a3828;font-size:0.78rem"><?= $r['check_date'] ?? '—' ?></td>
        <td>
          <?php if(!$r['check_status']): ?>
            <span style="color:#4a3828;font-size:0.75rem">No check</span>
          <?php elseif($r['check_status']==='Good'): ?>
            <span class="badge-good">Good</span>
          <?php else: ?>
            <span class="badge-warn"><?= $r['check_status'] ?></span>
          <?php endif; ?>
        </td>
      </tr>
      <?php endwhile; ?>
      <?php if($totalResidents===0): ?>
      <tr><td colspan="6" style="text-align:center;color:#555;padding:2rem">No residents. <a href="manage.php?section=residents" style="color:#ff4c1c">Add residents →</a></td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<script>
Chart.defaults.color = '#7A6450';
Chart.defaults.font.family = 'monospace';
Chart.defaults.font.size = 10;

new Chart(document.getElementById('trendChart'), {
  type: 'line',
  data: {
    labels: <?= json_encode($trendDates) ?>,
    datasets: [{
      label: '°C', data: <?= json_encode($trendTemps) ?>,
      borderColor: '#FF4C1C', backgroundColor: 'rgba(255,76,28,0.1)',
      tension: 0.4, fill: true, pointBackgroundColor: '#FF8C00',
      pointBorderColor: '#0F0B08', pointBorderWidth: 2, pointRadius: 4, borderWidth: 2
    }]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
      x: { grid: { color: '#2C2018' } },
      y: { grid: { color: '#2C2018' } }
    }
  }
});

new Chart(document.getElementById('illnessChart'), {
  type: 'doughnut',
  data: {
    labels: <?= json_encode($illnessLabels) ?>,
    datasets: [{ data: <?= json_encode($illnessCounts) ?>,
      backgroundColor: ['#FF4C1C','#FF8C00','#FFAA00','#ff5722'],
      borderWidth: 0, borderColor: '#1A1410'
    }]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { position: 'bottom', labels: { font: { size: 9 }, padding: 6 } } },
    cutout: '65%'
  }
});
</script>
</body>
</html>
