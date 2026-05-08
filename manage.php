<?php
// manage.php - HeatWatch Data Management
// Sections: residents, heat index logs

require_once 'db.php';
requireLogin();

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

$msg = '';
$section = $_GET['section'] ?? 'residents';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $esc = fn($v) => $conn->real_escape_string(trim($v ?? ''));

    // --- Residents ---
    if ($action === 'add_resident') {
        $name = $esc($_POST['name']); $age = (int)$_POST['age'];
        $address = $esc($_POST['address']); $zone = (int)$_POST['zone_id'];
        $med = $esc($_POST['medical_condition']);
        $vuln = isVulnerable($age, $med) ? 1 : 0;
        $zone_val = $zone ? $zone : 'NULL';
        $conn->query("INSERT INTO residents (name,age,address,zone_id,medical_condition,is_vulnerable) VALUES ('$name',$age,'$address',$zone_val,'$med',$vuln)");
        $msg = 'Resident added!';
    }
    if ($action === 'delete_resident') {
        $conn->query("DELETE FROM residents WHERE id=" . (int)$_POST['id']);
        $msg = 'Resident deleted.';
    }

    // --- Heat Index ---
    if ($action === 'add_heat') {
        $date = $esc($_POST['log_date']);
        $temp = (float)$_POST['temperature'];
        $hum = (float)$_POST['humidity'];
        $level = getHeatLevel($temp);
        $by = $_SESSION['user_id'];
        $conn->query("INSERT INTO heat_index_logs (log_date,temperature,humidity,heat_level,recorded_by) VALUES ('$date',$temp,$hum,'$level',$by)");
        $msg = 'Heat index logged!';
    }
    if ($action === 'delete_heat') {
        $conn->query("DELETE FROM heat_index_logs WHERE id=" . (int)$_POST['id']);
        $msg = 'Log deleted.';
    }
}

// Query data
$residents = $conn->query("SELECT r.*, b.barangay_name FROM residents r LEFT JOIN barangays b ON r.zone_id = b.id ORDER BY r.name ASC");
$barangays = $conn->query("SELECT * FROM barangays ORDER BY barangay_name ASC");
$heatLogs = $conn->query("SELECT h.*, u.full_name FROM heat_index_logs h LEFT JOIN users u ON h.recorded_by=u.id ORDER BY h.log_date DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HeatWatch - Manage</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: monospace; background: #0f0b08; color: #f2e8dc; min-height: 100vh; }
  .navbar { background: #1a1410; border-bottom: 1px solid #2c2018; padding: 1rem 2rem; display: flex; align-items: center; justify-content: space-between; }
  .navbar h1 { color: #ff4c1c; font-size: 1.2rem; }
  .navbar a { color: #aaa; text-decoration: none; font-size: 0.8rem; margin-left: 1rem; }
  .navbar a:hover { color: #ff4c1c; }
  .tabs { display: flex; gap: 0; background: #1a1410; border-bottom: 1px solid #2c2018; padding: 0 2rem; }
  .tab {
    padding: 0.8rem 1.2rem; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px;
    color: #7a6450; text-decoration: none; border-bottom: 2px solid transparent;
  }
  .tab:hover { color: #f2e8dc; }
  .tab.active { color: #ff4c1c; border-bottom-color: #ff4c1c; }
  .page { padding: 2rem; }
  h2 { margin-bottom: 1.5rem; font-size: 1.1rem; }
  .msg { background: rgba(76,175,80,0.1); border-left: 3px solid #4caf50; padding: 0.6rem 1rem; margin-bottom: 1rem; font-size: 0.82rem; color: #81c784; }
  .form-box { background: #1a1410; border: 1px solid #2c2018; border-radius: 6px; padding: 1.5rem; margin-bottom: 2rem; max-width: 480px; }
  .form-box h3 { margin-bottom: 1rem; font-size: 0.9rem; color: #ff4c1c; }
  label { font-size: 0.7rem; color: #7a6450; display: block; margin-bottom: 0.25rem; text-transform: uppercase; letter-spacing: 1px; }
  input, select, textarea { width: 100%; padding: 0.5rem; margin-bottom: 0.85rem; background: #111; border: 1px solid #333; color: #f2e8dc; border-radius: 3px; font-family: monospace; font-size: 0.84rem; }
  textarea { height: 60px; resize: vertical; }
  input:focus, select:focus { outline: none; border-color: #ff4c1c; }
  button { padding: 0.6rem 1.2rem; background: #ff4c1c; border: none; color: #fff; border-radius: 3px; cursor: pointer; font-family: monospace; font-size: 0.8rem; }
  button:hover { background: #ff6600; }
  table { width: 100%; border-collapse: collapse; font-size: 0.82rem; }
  th { text-align: left; padding: 0.6rem 0.8rem; background: #1a1410; color: #7a6450; font-size: 0.68rem; text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px solid #2c2018; }
  td { padding: 0.65rem 0.8rem; border-bottom: 1px solid #1a1410; }
  tr:hover td { background: #1a1410; }
  .badge-vuln { background: rgba(244,67,54,0.15); color: #ef5350; font-size: 0.65rem; padding: 0.1rem 0.4rem; border-radius: 2px; }
  .del-btn { background: rgba(244,67,54,0.1); border: 1px solid rgba(244,67,54,0.25); color: #ef5350; padding: 0.3rem 0.6rem; font-size: 0.7rem; }
  .heat-badge { font-size: 0.7rem; padding: 0.15rem 0.5rem; border-radius: 2px; }
  .heat-Caution { background: rgba(255,193,7,0.15); color: #ffc107; }
  .heat-ExCaution { background: rgba(255,152,0,0.15); color: #ff9800; }
  .heat-Danger { background: rgba(244,67,54,0.15); color: #f44336; }
  .heat-Normal { background: rgba(76,175,80,0.15); color: #4caf50; }
</style>
</head>
<body>

<div class="navbar">
  <h1>🌡️ HeatWatch — Manage</h1>
  <div>
    <a href="dashboard.php">← Dashboard</a>
    <a href="?logout=1">Logout</a>
  </div>
</div>

<div class="tabs">
  <a href="?section=residents" class="tab <?= $section==='residents'?'active':'' ?>">Residents</a>
  <a href="?section=heat" class="tab <?= $section==='heat'?'active':'' ?>">Heat Index</a>
</div>

<div class="page">

<?php if ($msg): ?>
<div class="msg">✅ <?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<?php if ($section === 'residents'): ?>
  <h2>Manage Residents</h2>
  <div class="form-box">
    <h3>Add New Resident</h3>
    <form method="POST">
      <input type="hidden" name="action" value="add_resident">
      <label>Full Name</label>
      <input type="text" name="name" required placeholder="Juan Dela Cruz">
      <label>Age</label>
      <input type="number" name="age" required min="0" max="120">
      <label>Address</label>
      <input type="text" name="address" placeholder="Purok / Street">
      <label>Barangay</label>
      <select name="zone_id">
        <option value="">-- Select --</option>
        <?php while($b = $barangays->fetch_assoc()): ?>
        <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['barangay_name']) ?></option>
        <?php endwhile; ?>
      </select>
      <label>Medical Condition</label>
      <textarea name="medical_condition" placeholder="Leave blank if none"></textarea>
      <button type="submit">Add Resident</button>
    </form>
  </div>
  <table>
    <thead><tr><th>#</th><th>Name</th><th>Age</th><th>Barangay</th><th>Condition</th><th>Vulnerable</th><th>Action</th></tr></thead>
    <tbody>
      <?php if ($residents->num_rows === 0): ?>
      <tr><td colspan="7" style="text-align:center;color:#555;padding:2rem">No residents yet.</td></tr>
      <?php else: while($r = $residents->fetch_assoc()): ?>
      <tr>
        <td style="color:#555"><?= $r['id'] ?></td>
        <td><?= htmlspecialchars($r['name']) ?></td>
        <td><?= $r['age'] ?></td>
        <td><?= $r['barangay_name'] ?? '—' ?></td>
        <td style="color:#7a6450"><?= $r['medical_condition'] ? htmlspecialchars(substr($r['medical_condition'],0,25)) : '—' ?></td>
        <td><?= $r['is_vulnerable'] ? '<span class="badge-vuln">⚠ Yes</span>' : '—' ?></td>
        <td>
          <form method="POST" style="display:inline" onsubmit="return confirm('Delete?')">
            <input type="hidden" name="action" value="delete_resident">
            <input type="hidden" name="id" value="<?= $r['id'] ?>">
            <button type="submit" class="del-btn">Delete</button>
          </form>
        </td>
      </tr>
      <?php endwhile; endif; ?>
    </tbody>
  </table>

<?php elseif ($section === 'heat'): ?>
  <h2>Log Heat Index</h2>
  <div class="form-box">
    <h3>Add Heat Reading</h3>
    <form method="POST">
      <input type="hidden" name="action" value="add_heat">
      <label>Date</label>
      <input type="date" name="log_date" required value="<?= date('Y-m-d') ?>">
      <label>Temperature (°C)</label>
      <input type="number" name="temperature" required step="0.1" min="0" max="60" placeholder="e.g. 38.5">
      <label>Humidity (%)</label>
      <input type="number" name="humidity" step="0.1" min="0" max="100" placeholder="e.g. 72">
      <button type="submit">Log Reading</button>
    </form>
  </div>
  <table>
    <thead><tr><th>Date</th><th>Temp (°C)</th><th>Humidity (%)</th><th>Heat Level</th><th>Logged By</th><th>Action</th></tr></thead>
    <tbody>
      <?php if ($heatLogs->num_rows === 0): ?>
      <tr><td colspan="6" style="text-align:center;color:#555;padding:2rem">No records yet.</td></tr>
      <?php else: while($h = $heatLogs->fetch_assoc()): ?>
      <tr>
        <td><?= $h['log_date'] ?></td>
        <td><?= $h['temperature'] ?>°C</td>
        <td><?= $h['humidity'] ?>%</td>
        <td>
          <?php
            $cls = str_replace(' ','', $h['heat_level']);
            if($h['heat_level']==='Extreme Caution') $cls='ExCaution';
          ?>
          <span class="heat-badge heat-<?= $cls ?>"><?= $h['heat_level'] ?></span>
        </td>
        <td style="color:#7a6450"><?= htmlspecialchars($h['full_name'] ?? 'Unknown') ?></td>
        <td>
          <form method="POST" style="display:inline" onsubmit="return confirm('Delete?')">
            <input type="hidden" name="action" value="delete_heat">
            <input type="hidden" name="id" value="<?= $h['id'] ?>">
            <button type="submit" class="del-btn">Delete</button>
          </form>
        </td>
      </tr>
      <?php endwhile; endif; ?>
    </tbody>
  </table>
<?php endif; ?>

</div>
</body>
</html>
