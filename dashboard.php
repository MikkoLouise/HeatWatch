<?php
// ============================================================
// dashboard.php — HeatWatch Main Dashboard
// ============================================================
require_once 'db.php';
requireLogin();

$todayHeat = $conn->query("SELECT temperature, heat_level FROM heat_index_logs WHERE log_date=CURDATE() ORDER BY id DESC LIMIT 1")->fetch_assoc();
$totalResidents = $conn->query("SELECT COUNT(*) AS c FROM residents")->fetch_assoc()['c'];
$vulnerableCount = $conn->query("SELECT COUNT(*) AS c FROM residents WHERE is_vulnerable=1")->fetch_assoc()['c'];
$illnessCases = $conn->query("SELECT COUNT(*) AS c FROM illness_cases")->fetch_assoc()['c'];
$wellnessChecks = $conn->query("SELECT COUNT(*) AS c FROM wellness_checks")->fetch_assoc()['c'];

$trendResult = $conn->query("SELECT log_date, temperature FROM heat_index_logs ORDER BY log_date ASC LIMIT 8");
$trendDates = []; $trendTemps = [];
while($row = $trendResult->fetch_assoc()){
    $trendDates[] = date('M d', strtotime($row['log_date']));
    $trendTemps[] = $row['temperature'];
}

$illnessResult = $conn->query("SELECT illness_type, COUNT(*) AS c FROM illness_cases GROUP BY illness_type");
$illnessLabels = []; $illnessCounts = [];
while($row = $illnessResult->fetch_assoc()){ $illnessLabels[] = $row['illness_type']; $illnessCounts[] = $row['c']; }
if(empty($illnessLabels)){$illnessLabels=['No Data'];$illnessCounts=[1];}

$statusResult = $conn->query("SELECT status, COUNT(*) AS c FROM wellness_checks GROUP BY status");
$statusLabels=[]; $statusCounts=[];
while($row=$statusResult->fetch_assoc()){$statusLabels[]=$row['status'];$statusCounts[]=$row['c'];}
if(empty($statusLabels)){$statusLabels=['No Data'];$statusCounts=[1];}

$zoneResult = $conn->query("SELECT b.barangay_name AS zone_name, COUNT(r.id) AS c FROM barangays b LEFT JOIN residents r ON r.zone_id=b.id GROUP BY b.id");
$zoneNames=[]; $zoneCounts=[];
while($row=$zoneResult->fetch_assoc()){$zoneNames[]=$row['zone_name'];$zoneCounts[]=$row['c'];}

$monitorQuery = $conn->query("
    SELECT r.id, r.name, r.age, r.is_vulnerable, r.medical_condition,
        b.barangay_name AS zone_name, wc.status AS check_status, wc.check_date, ic.illness_type, ic.outcome
    FROM residents r
    LEFT JOIN barangays b ON r.zone_id = b.id
    LEFT JOIN wellness_checks wc ON wc.resident_id = r.id
    LEFT JOIN illness_cases ic ON ic.resident_id = r.id
    ORDER BY r.is_vulnerable DESC, r.name ASC
    LIMIT 20
");

$heatColor = ['Normal'=>'#4caf50','Caution'=>'#ffc107','Extreme Caution'=>'#ff9800','Danger'=>'#f44336','Extreme Danger'=>'#9c27b0'];
$todayColor = $heatColor[$todayHeat['heat_level'] ?? 'Normal'] ?? '#888';
$todayLevel = $todayHeat['heat_level'] ?? 'Normal';

if(isset($_GET['logout'])){session_destroy(); header('Location:index.php'); exit;}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>HeatWatch — Dashboard</title>
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:ital,wght@0,400;0,700;1,400&family=Syne:wght@400;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --fire:#FF4C1C;--fire2:#FF8C00;--amber:#FFAA00;
  --deep:#080604;--surface:#0F0B08;--panel:#14100C;--card:#1A1410;
  --border:#2C2018;--border2:#3A2A18;
  --text:#F2E8DC;--muted:#7A6450;--dim:#4A3828;
  --nav-h:64px;
}
body{font-family:'Space Mono',monospace;background:var(--deep);color:var(--text);min-height:100vh;}
a{color:inherit;text-decoration:none;}

/* ── TOP NAV ── */
.topnav{
  position:fixed;top:0;left:0;right:0;height:var(--nav-h);z-index:100;
  background:var(--surface);border-bottom:1px solid var(--border);
  display:flex;align-items:center;padding:0 1.5rem;gap:0;
  overflow:hidden;
}
.topnav::after{
  content:'';position:absolute;bottom:0;left:0;right:0;height:1px;
  background:linear-gradient(90deg,transparent,var(--fire),var(--fire2),transparent);
  opacity:.5;
}

.nav-brand{
  display:flex;align-items:center;gap:.7rem;margin-right:2.5rem;flex-shrink:0;
}
.nav-brand-icon{
  width:34px;height:34px;background:linear-gradient(135deg,var(--fire),var(--fire2));
  border-radius:6px;display:flex;align-items:center;justify-content:center;
  font-size:.95rem;box-shadow:0 0 16px rgba(255,76,28,.3);
}
.nav-brand-name{
  font-family:'Syne',sans-serif;font-weight:800;font-size:1.15rem;
  letter-spacing:1px;background:linear-gradient(135deg,var(--fire),var(--fire2));
  -webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;
}

.nav-links{display:flex;align-items:stretch;height:var(--nav-h);flex:1;}
.nav-link{
  display:flex;align-items:center;gap:.45rem;padding:0 1rem;
  font-size:.72rem;letter-spacing:1.5px;text-transform:uppercase;color:var(--muted);
  position:relative;transition:color .2s;white-space:nowrap;
}
.nav-link:hover{color:var(--text);}
.nav-link.active{color:var(--fire);}
.nav-link.active::after{
  content:'';position:absolute;bottom:0;left:0;right:0;height:2px;
  background:linear-gradient(90deg,var(--fire),var(--fire2));
}
.nav-link-icon{font-size:.9rem;opacity:.7;}

.nav-right{
  margin-left:auto;display:flex;align-items:center;gap:1rem;flex-shrink:0;
}
.nav-user{
  font-size:.65rem;letter-spacing:1px;color:var(--muted);
  display:flex;align-items:center;gap:.5rem;
}
.nav-user-dot{width:6px;height:6px;border-radius:50%;background:#4CAF50;box-shadow:0 0 6px #4CAF50;}
.nav-logout{
  padding:.4rem .9rem;background:rgba(255,76,28,.1);border:1px solid rgba(255,76,28,.25);
  border-radius:3px;color:var(--fire);font-family:'Space Mono',monospace;
  font-size:.65rem;letter-spacing:1.5px;text-transform:uppercase;
  cursor:pointer;transition:background .2s;
}
.nav-logout:hover{background:rgba(255,76,28,.2);}

/* ── PAGE WRAPPER ── */
.page{margin-top:var(--nav-h);padding:2rem 1.8rem;}

/* ── PAGE HEADER ── */
.page-header{
  display:flex;align-items:flex-end;gap:1rem;margin-bottom:2rem;
  padding-bottom:1.2rem;border-bottom:1px solid var(--border);
  position:relative;
}
.page-header::before{
  content:'DASHBOARD';
  position:absolute;right:0;bottom:1.5rem;
  font-family:'Syne',sans-serif;font-size:4rem;font-weight:800;
  color:rgba(255,76,28,.04);letter-spacing:-2px;pointer-events:none;
  line-height:1;
}
.page-label{
  font-size:.6rem;letter-spacing:4px;text-transform:uppercase;color:var(--muted);
}
.page-title{
  font-family:'Syne',sans-serif;font-weight:800;font-size:1.6rem;
  letter-spacing:-0.5px;color:var(--text);line-height:1;
}
.page-date{
  margin-left:auto;font-size:.65rem;color:var(--dim);letter-spacing:1px;
  text-align:right;
}

/* ── ALERT BAR ── */
.alert-bar{
  display:flex;align-items:center;gap:.8rem;
  padding:.7rem 1.2rem;margin-bottom:1.8rem;
  background:rgba(255,76,28,.06);border:1px solid var(--border2);border-radius:4px;
  border-left:3px solid <?= $todayColor ?>;
}
.alert-level{
  font-family:'Syne',sans-serif;font-weight:700;font-size:.8rem;letter-spacing:1px;
  color:<?= $todayColor ?>;padding:.2rem .6rem;
  background:<?= $todayColor ?>22;border-radius:2px;
}
.alert-text{font-size:.72rem;color:var(--muted);letter-spacing:.5px;}
.alert-blink{
  width:8px;height:8px;border-radius:50%;background:<?= $todayColor ?>;
  box-shadow:0 0 10px <?= $todayColor ?>;margin-left:auto;
  animation:blink 1.5s ease-in-out infinite;
}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.2}}

/* ── STAT GRID ── */
.stat-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:1px;margin-bottom:1.8rem;background:var(--border);border:1px solid var(--border);border-radius:6px;overflow:hidden;}
.stat{
  background:var(--card);padding:1.4rem 1.6rem;position:relative;
  overflow:hidden;transition:background .2s;
}
.stat:hover{background:#1F1814;}
.stat::before{
  content:'';position:absolute;top:0;left:0;right:0;height:2px;
  background:linear-gradient(90deg,var(--fire),transparent);
  opacity:0;transition:opacity .2s;
}
.stat:hover::before{opacity:1;}
.stat-num{
  font-family:'Syne',sans-serif;font-weight:800;font-size:2.4rem;
  line-height:1;color:var(--text);margin-bottom:.4rem;
}
.stat-num.heat{color:<?= $todayColor ?>;text-shadow:0 0 20px <?= $todayColor ?>44;}
.stat-label{font-size:.62rem;letter-spacing:2px;text-transform:uppercase;color:var(--muted);}
.stat-sub{margin-top:.4rem;font-size:.65rem;color:var(--dim);}
.stat-icon{
  position:absolute;right:1.2rem;top:1.2rem;font-size:1.6rem;opacity:.12;
  font-style:normal;
}

/* ── CHART ROW ── */
.chart-row{display:grid;grid-template-columns:3fr 1.4fr 1.4fr;gap:1rem;margin-bottom:1.8rem;}
.chart-box{
  background:var(--card);border:1px solid var(--border);border-radius:6px;
  padding:1.2rem;position:relative;overflow:hidden;
}
.chart-box::before{
  content:'';position:absolute;top:0;left:0;width:3px;height:100%;
  background:linear-gradient(180deg,var(--fire),transparent);
}
.chart-head{
  display:flex;align-items:center;justify-content:space-between;
  margin-bottom:1rem;
}
.chart-title{font-size:.65rem;letter-spacing:2px;text-transform:uppercase;color:var(--muted);}
.chart-tag{font-size:.6rem;color:var(--dim);border:1px solid var(--border);padding:.15rem .4rem;border-radius:2px;}
.chart-area{position:relative;height:170px;}

/* ── TABLE SECTION ── */
.section-head{
  display:flex;align-items:center;gap:.8rem;margin-bottom:.8rem;
}
.section-title{font-family:'Syne',sans-serif;font-weight:700;font-size:.85rem;letter-spacing:.5px;color:var(--text);}
.section-badge{
  font-size:.6rem;color:var(--muted);border:1px solid var(--border);
  padding:.15rem .5rem;border-radius:2px;letter-spacing:1px;
}
.section-line{flex:1;height:1px;background:var(--border);}
.join-note{
  font-size:.62rem;color:var(--dim);margin-bottom:.8rem;
  padding:.5rem .8rem;background:rgba(255,140,0,.03);border-left:2px solid var(--fire2);
  border-radius:0 2px 2px 0;letter-spacing:.3px;font-style:italic;
}

/* ── TABLE ── */
.tbl-wrap{background:var(--card);border:1px solid var(--border);border-radius:6px;overflow:hidden;}
table{width:100%;border-collapse:collapse;}
thead{background:var(--panel);}
thead th{
  padding:.6rem 1rem;text-align:left;
  font-size:.6rem;letter-spacing:2px;text-transform:uppercase;color:var(--dim);
  border-bottom:1px solid var(--border);white-space:nowrap;font-weight:400;
}
tbody tr{border-bottom:1px solid rgba(255,255,255,.03);transition:background .12s;}
tbody tr:hover{background:rgba(255,76,28,.04);}
tbody td{padding:.65rem 1rem;font-size:.82rem;}
.td-name{font-family:'Syne',sans-serif;font-weight:600;font-size:.82rem;}

/* ── BADGES ── */
.badge{display:inline-flex;align-items:center;gap:.25rem;padding:.12rem .45rem;border-radius:2px;font-size:.62rem;letter-spacing:.5px;font-weight:700;}
.b-vuln{background:rgba(255,76,28,.12);color:#FF6B4A;border:1px solid rgba(255,76,28,.2);}
.b-good{background:rgba(76,175,80,.1);color:#66BB6A;border:1px solid rgba(76,175,80,.2);}
.b-warn{background:rgba(255,193,7,.1);color:#FFD740;border:1px solid rgba(255,193,7,.2);}
.b-ref{background:rgba(244,67,54,.1);color:#EF5350;border:1px solid rgba(244,67,54,.2);}
.b-zone{background:rgba(255,140,0,.08);color:#FFB74D;border:1px solid rgba(255,140,0,.15);}
.b-none{background:rgba(255,255,255,.04);color:var(--dim);border:1px solid var(--border);}

@media(max-width:1100px){
  .stat-grid{grid-template-columns:1fr 1fr;}
  .chart-row{grid-template-columns:1fr;}
}
@media(max-width:700px){
  .stat-grid{grid-template-columns:1fr 1fr;}
  .nav-links .nav-link .nav-link-icon + *{display:none;}
  .page{padding:1.2rem;}
}
</style>
</head>
<body>

<!-- TOP NAV -->
<nav class="topnav">
  <div class="nav-brand">
    <div class="nav-brand-icon">🌡️</div>
    <div class="nav-brand-name">HeatWatch</div>
  </div>

  <div class="nav-links">
    <a href="dashboard.php" class="nav-link active">
      <span class="nav-link-icon">⬡</span>Dashboard
    </a>
    <a href="manage.php?section=residents" class="nav-link">
      <span class="nav-link-icon">⬡</span>Residents
    </a>
    <a href="manage.php?section=heat" class="nav-link">
      <span class="nav-link-icon">⬡</span>Heat Logs
    </a>
    <a href="manage.php?section=wellness" class="nav-link">
      <span class="nav-link-icon">⬡</span>Wellness
    </a>
    <a href="manage.php?section=illness" class="nav-link">
      <span class="nav-link-icon">⬡</span>Illness Cases
    </a>
    <a href="manage.php?section=barangays" class="nav-link">
      <span class="nav-link-icon">⬡</span>Zones
    </a>
  </div>

  <div class="nav-right">
    <div class="nav-user">
      <div class="nav-user-dot"></div>
      <?= htmlspecialchars($_SESSION['user_name']) ?>
    </div>
    <a href="?logout=1" class="nav-logout">Logout</a>
  </div>
</nav>

<div class="page">

  <!-- PAGE HEADER -->
  <div class="page-header">
    <div>
      <div class="page-label">Overview</div>
      <div class="page-title">Mission Dashboard</div>
    </div>
    <div class="page-date">
      <?= date('D, d M Y') ?><br>
      <span style="color:var(--fire);font-size:.6rem">LIVE MONITORING</span>
    </div>
  </div>

  <!-- ALERT BAR -->
  <div class="alert-bar">
    <span style="font-size:.65rem;color:var(--muted);letter-spacing:2px;text-transform:uppercase;">Today's Reading</span>
    <span class="alert-level"><?= $todayLevel ?></span>
    <span class="alert-text">
      <?= $todayHeat ? $todayHeat['temperature'].'°C recorded today' : 'No heat index reading logged today' ?>
    </span>
    <div class="alert-blink"></div>
  </div>

  <!-- STAT CARDS -->
  <div class="stat-grid">
    <div class="stat">
      <div class="stat-num heat"><?= $todayHeat ? $todayHeat['temperature'].'°' : 'N/A' ?></div>
      <div class="stat-label">Heat Index</div>
      <div class="stat-sub"><?= htmlspecialchars($todayLevel) ?></div>
      <i class="stat-icon">🌡️</i>
    </div>
    <div class="stat">
      <div class="stat-num"><?= $totalResidents ?></div>
      <div class="stat-label">Residents</div>
      <div class="stat-sub"><?= $vulnerableCount ?> flagged vulnerable</div>
      <i class="stat-icon">👥</i>
    </div>
    <div class="stat">
      <div class="stat-num"><?= $illnessCases ?></div>
      <div class="stat-label">Illness Cases</div>
      <div class="stat-sub">Heat-related</div>
      <i class="stat-icon">🚑</i>
    </div>
    <div class="stat">
      <div class="stat-num"><?= $wellnessChecks ?></div>
      <div class="stat-label">Wellness Checks</div>
      <div class="stat-sub">Total conducted</div>
      <i class="stat-icon">🩺</i>
    </div>
  </div>

  <!-- CHARTS -->
  <div class="chart-row">
    <div class="chart-box">
      <div class="chart-head">
        <div class="chart-title">Heat Index Trend</div>
        <div class="chart-tag">Last 8 Records</div>
      </div>
      <div class="chart-area"><canvas id="trendChart"></canvas></div>
    </div>
    <div class="chart-box">
      <div class="chart-head">
        <div class="chart-title">Illness Types</div>
        <div class="chart-tag">Breakdown</div>
      </div>
      <div class="chart-area"><canvas id="illnessChart"></canvas></div>
    </div>
    <div class="chart-box">
      <div class="chart-head">
        <div class="chart-title">Check Outcomes</div>
        <div class="chart-tag">Status</div>
      </div>
      <div class="chart-area"><canvas id="statusChart"></canvas></div>
    </div>
  </div>

  <!-- RESIDENT TABLE -->
  <div class="section-head">
    <div class="section-title">Resident Monitor</div>
    <div class="section-badge">SQL JOIN VIEW</div>
    <div class="section-line"></div>
  </div>
  <div class="join-note">
    residents LEFT JOIN barangays, wellness_checks, illness_cases — All residents shown including those without barangay/checks/cases.
  </div>
  <div class="tbl-wrap">
    <table>
      <thead>
        <tr>
          <th>Name</th><th>Age</th><th>Barangay</th><th>Condition</th>
          <th>Last Check</th><th>Status</th><th>Illness</th>
        </tr>
      </thead>
      <tbody>
        <?php while($r = $monitorQuery->fetch_assoc()): ?>
        <tr>
          <td>
            <div class="td-name"><?= htmlspecialchars($r['name']) ?></div>
            <?php if($r['is_vulnerable']): ?><span class="badge b-vuln">⚠ Vulnerable</span><?php endif; ?>
          </td>
          <td><?= $r['age'] ?></td>
          <td><?= $r['zone_name'] ? '<span class="badge b-zone">'.htmlspecialchars($r['zone_name']).'</span>' : '<span style="color:var(--dim)">—</span>' ?></td>
          <td style="color:var(--muted);font-size:.78rem;max-width:160px;overflow:hidden;white-space:nowrap;text-overflow:ellipsis"><?= $r['medical_condition'] ? htmlspecialchars(substr($r['medical_condition'],0,30)) : '—' ?></td>
          <td style="color:var(--dim);font-size:.78rem"><?= $r['check_date'] ?? '—' ?></td>
          <td>
            <?php if(!$r['check_status']): ?>
              <span class="badge b-none">No Check</span>
            <?php elseif($r['check_status']==='Good'): ?>
              <span class="badge b-good">Good</span>
            <?php elseif($r['check_status']==='Needs Monitoring'): ?>
              <span class="badge b-warn">Monitor</span>
            <?php else: ?>
              <span class="badge b-ref">Referred</span>
            <?php endif; ?>
          </td>
          <td style="font-size:.78rem"><?= $r['illness_type'] ? htmlspecialchars($r['illness_type']) : '<span style="color:var(--dim)">—</span>' ?></td>
        </tr>
        <?php endwhile; ?>
        <?php if($totalResidents == 0): ?>
        <tr><td colspan="7" style="text-align:center;color:var(--muted);padding:2.5rem;font-size:.82rem;">
          No residents yet. <a href="manage.php?section=residents" style="color:var(--fire)">Add residents →</a>
        </td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

</div><!-- .page -->

<script>
Chart.defaults.color = '#7A6450';
Chart.defaults.font.family = "'Space Mono', monospace";
Chart.defaults.font.size = 10;

new Chart(document.getElementById('trendChart'), {
  type: 'line',
  data: {
    labels: <?= json_encode($trendDates) ?>,
    datasets: [{
      label: '°C',
      data: <?= json_encode($trendTemps) ?>,
      borderColor: '#FF4C1C',
      backgroundColor: (ctx) => {
        const g = ctx.chart.ctx.createLinearGradient(0,0,0,160);
        g.addColorStop(0,'rgba(255,76,28,.25)');
        g.addColorStop(1,'rgba(255,76,28,0)');
        return g;
      },
      tension:.4, fill:true,
      pointBackgroundColor:'#FF8C00',pointBorderColor:'#0F0B08',
      pointBorderWidth:2, pointRadius:4, borderWidth:2
    }]
  },
  options:{
    responsive:true, maintainAspectRatio:false,
    plugins:{legend:{display:false},tooltip:{backgroundColor:'#1A1410',borderColor:'#2C2018',borderWidth:1}},
    scales:{
      x:{grid:{color:'#2C2018'},ticks:{font:{size:9}}},
      y:{grid:{color:'#2C2018'},ticks:{font:{size:9}}}
    }
  }
});

new Chart(document.getElementById('illnessChart'), {
  type: 'doughnut',
  data:{
    labels: <?= json_encode($illnessLabels) ?>,
    datasets:[{
      data: <?= json_encode($illnessCounts) ?>,
      backgroundColor:['#FF4C1C','#FF8C00','#FFAA00','#ff5722','#e91e63','#9c27b0'],
      borderWidth:0, hoverOffset:6,
      borderColor:'#1A1410',
    }]
  },
  options:{
    responsive:true, maintainAspectRatio:false,
    plugins:{legend:{position:'bottom',labels:{font:{size:9},padding:6,color:'#7A6450',boxWidth:8}}},
    cutout:'65%'
  }
});

new Chart(document.getElementById('statusChart'), {
  type: 'bar',
  data:{
    labels: <?= json_encode($statusLabels) ?>,
    datasets:[{
      data: <?= json_encode($statusCounts) ?>,
      backgroundColor:['rgba(76,175,80,.6)','rgba(255,193,7,.6)','rgba(244,67,54,.6)'],
      borderRadius:3, borderWidth:0
    }]
  },
  options:{
    responsive:true, maintainAspectRatio:false,
    plugins:{legend:{display:false}},
    scales:{
      x:{grid:{display:false},ticks:{font:{size:9}}},
      y:{grid:{color:'#2C2018'},ticks:{font:{size:9}}}
    }
  }
});
</script>
</body>
</html>