<?php
// manage.php - HeatWatch Data Management
// This page lets health workers add, edit, and delete residents

require_once 'db.php';
requireLogin();

$msg = '';
$section = $_GET['section'] ?? 'residents';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $esc = fn($v) => $conn->real_escape_string(trim($v ?? ''));

    if ($action === 'add_resident') {
        $name = $esc($_POST['name']);
        $age = (int)$_POST['age'];
        $address = $esc($_POST['address']);
        $zone = (int)$_POST['zone_id'];
        $med = $esc($_POST['medical_condition']);
        $vuln = isVulnerable($age, $med) ? 1 : 0;
        $zone_val = $zone ? $zone : 'NULL';
        $conn->query("INSERT INTO residents (name, age, address, zone_id, medical_condition, is_vulnerable)
            VALUES ('$name', $age, '$address', $zone_val, '$med', $vuln)");
        $msg = 'Resident added successfully!';
    }

    if ($action === 'delete_resident') {
        $id = (int)$_POST['id'];
        $conn->query("DELETE FROM residents WHERE id=$id");
        $msg = 'Resident deleted.';
    }
}

// Get list of residents
$residents = $conn->query("SELECT r.*, b.barangay_name FROM residents r LEFT JOIN barangays b ON r.zone_id = b.id ORDER BY r.name ASC");

// Get list of barangays for dropdown
$barangays = $conn->query("SELECT * FROM barangays ORDER BY barangay_name ASC");

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}
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
  .navbar {
    background: #1a1410; border-bottom: 1px solid #2c2018;
    padding: 1rem 2rem; display: flex; align-items: center; justify-content: space-between;
  }
  .navbar h1 { color: #ff4c1c; font-size: 1.2rem; }
  .navbar a { color: #aaa; text-decoration: none; font-size: 0.8rem; margin-left: 1rem; }
  .page { padding: 2rem; }
  h2 { margin-bottom: 1.5rem; font-size: 1.2rem; }
  .msg { background: rgba(76,175,80,0.1); border-left: 3px solid #4caf50; padding: 0.6rem 1rem; margin-bottom: 1rem; font-size: 0.82rem; }
  .form-box {
    background: #1a1410; border: 1px solid #2c2018; border-radius: 6px;
    padding: 1.5rem; margin-bottom: 2rem; max-width: 500px;
  }
  .form-box h3 { margin-bottom: 1rem; font-size: 0.95rem; color: #ff4c1c; }
  label { font-size: 0.72rem; color: #7a6450; display: block; margin-bottom: 0.3rem; text-transform: uppercase; letter-spacing: 1px; }
  input, select, textarea {
    width: 100%; padding: 0.5rem; margin-bottom: 0.9rem;
    background: #111; border: 1px solid #333; color: #f2e8dc;
    border-radius: 3px; font-family: monospace; font-size: 0.85rem;
  }
  textarea { height: 70px; resize: vertical; }
  button {
    padding: 0.6rem 1.2rem; background: #ff4c1c; border: none;
    color: #fff; border-radius: 4px; cursor: pointer; font-family: monospace; font-size: 0.82rem;
  }
  button:hover { background: #ff6600; }
  table { width: 100%; border-collapse: collapse; font-size: 0.82rem; }
  th { text-align: left; padding: 0.6rem 0.8rem; background: #1a1410; color: #7a6450; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px solid #2c2018; }
  td { padding: 0.7rem 0.8rem; border-bottom: 1px solid #1a1410; }
  tr:hover td { background: #1a1410; }
  .badge-vuln { background: rgba(244,67,54,0.15); color: #ef5350; font-size: 0.65rem; padding: 0.1rem 0.4rem; border-radius: 2px; }
  .del-btn { background: rgba(244,67,54,0.1); border: 1px solid rgba(244,67,54,0.25); color: #ef5350; padding: 0.3rem 0.6rem; font-size: 0.72rem; }
</style>
</head>
<body>

<div class="navbar">
  <h1>🌡️ HeatWatch — Manage</h1>
  <div>
    <a href="dashboard.php">Dashboard</a>
    <a href="?logout=1">Logout</a>
  </div>
</div>

<div class="page">
  <h2>Manage Residents</h2>

  <?php if ($msg): ?>
  <div class="msg"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <!-- Add Resident Form -->
  <div class="form-box">
    <h3>Add New Resident</h3>
    <form method="POST">
      <input type="hidden" name="action" value="add_resident">
      <label>Full Name</label>
      <input type="text" name="name" required placeholder="e.g. Juan Dela Cruz">
      <label>Age</label>
      <input type="number" name="age" required min="0" max="120">
      <label>Address</label>
      <input type="text" name="address" placeholder="Street / Purok">
      <label>Barangay</label>
      <select name="zone_id">
        <option value="">-- Select Barangay --</option>
        <?php $barangays->data_seek(0); while($b = $barangays->fetch_assoc()): ?>
        <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['barangay_name']) ?></option>
        <?php endwhile; ?>
      </select>
      <label>Medical Condition (if any)</label>
      <textarea name="medical_condition" placeholder="Leave blank if none"></textarea>
      <button type="submit">Add Resident</button>
    </form>
  </div>

  <!-- Residents Table -->
  <table>
    <thead>
      <tr>
        <th>#</th>
        <th>Name</th>
        <th>Age</th>
        <th>Barangay</th>
        <th>Medical Condition</th>
        <th>Vulnerable?</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($residents->num_rows === 0): ?>
      <tr><td colspan="7" style="text-align:center; color:#555; padding:2rem;">No residents yet.</td></tr>
      <?php else: ?>
      <?php while ($r = $residents->fetch_assoc()): ?>
      <tr>
        <td style="color:#555"><?= $r['id'] ?></td>
        <td><?= htmlspecialchars($r['name']) ?></td>
        <td><?= $r['age'] ?></td>
        <td><?= $r['barangay_name'] ?? '—' ?></td>
        <td><?= $r['medical_condition'] ? htmlspecialchars(substr($r['medical_condition'], 0, 30)) : '—' ?></td>
        <td><?= $r['is_vulnerable'] ? '<span class="badge-vuln">⚠ Yes</span>' : '—' ?></td>
        <td>
          <form method="POST" style="display:inline" onsubmit="return confirm('Delete this resident?')">
            <input type="hidden" name="action" value="delete_resident">
            <input type="hidden" name="id" value="<?= $r['id'] ?>">
            <button type="submit" class="del-btn">Delete</button>
          </form>
        </td>
      </tr>
      <?php endwhile; ?>
      <?php endif; ?>
    </tbody>
  </table>

</div>
</body>
</html>
