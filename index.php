<?php
// ============================================================
// index.php — HeatWatch Login Page
// ============================================================
session_start();
if (!empty($_SESSION['user_id'])) { header('Location: dashboard.php'); exit; }
require_once 'db.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $result = $conn->query("SELECT * FROM users WHERE username='".  $conn->real_escape_string($username)."' LIMIT 1");
    if ($result && $row = $result->fetch_assoc()) {
        if (password_verify($password, $row['password'])) {
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['user_name'] = $row['full_name'];
            $_SESSION['username'] = $row['username'];
            header('Location: dashboard.php'); exit;
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
<title>HeatWatch — Login</title>
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:ital,wght@0,400;0,700;1,400&family=Syne:wght@400;700;800&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --fire:#FF4C1C;--fire2:#FF8C00;--amber:#FFAA00;
  --deep:#080604;--surface:#0F0B08;--panel:#16110D;
  --border:#2C2018;--border2:#3A2A18;
  --text:#F2E8DC;--muted:#7A6450;--dim:#4A3828;
}
html,body{height:100%;}
body{
  font-family:'Space Mono',monospace;
  background:var(--deep);color:var(--text);
  overflow:hidden;display:flex;
}

/* ── SPLIT LAYOUT ── */
.left-panel{
  width:55%;height:100vh;position:relative;overflow:hidden;
  background:var(--surface);
  border-right:1px solid var(--border);
}
.right-panel{
  width:45%;height:100vh;display:flex;
  flex-direction:column;justify-content:center;
  padding:4rem 3.5rem;position:relative;overflow:hidden;
}

/* ── GRID BACKGROUND ── */
.left-panel::before{
  content:'';position:absolute;inset:0;
  background-image:
    linear-gradient(rgba(255,76,28,.06) 1px, transparent 1px),
    linear-gradient(90deg, rgba(255,76,28,.06) 1px, transparent 1px);
  background-size:40px 40px;
  animation:gridShift 20s linear infinite;
}
@keyframes gridShift{from{background-position:0 0}to{background-position:40px 40px}}

/* ── THERMAL BLOB ── */
.thermal-blob{
  position:absolute;
  border-radius:50%;filter:blur(80px);
  animation:blobPulse 4s ease-in-out infinite alternate;
}
.blob1{width:400px;height:400px;background:rgba(255,76,28,.18);top:-100px;left:-100px;}
.blob2{width:300px;height:300px;background:rgba(255,140,0,.12);bottom:-50px;right:-50px;animation-delay:-2s;}
.blob3{width:200px;height:200px;background:rgba(255,170,0,.1);top:40%;left:30%;}
@keyframes blobPulse{from{transform:scale(1) rotate(0deg)}to{transform:scale(1.15) rotate(8deg)}}

/* ── SCANLINE ── */
.scanline{
  position:absolute;left:0;right:0;height:2px;
  background:linear-gradient(90deg,transparent,rgba(255,76,28,.6),transparent);
  animation:scan 5s linear infinite;top:-2px;
}
@keyframes scan{from{top:-2px}to{top:100%}}

/* ── LEFT PANEL CONTENT ── */
.left-content{
  position:relative;z-index:2;height:100%;
  display:flex;flex-direction:column;justify-content:space-between;
  padding:3rem;
}
.brand{
  display:flex;align-items:flex-start;gap:1rem;
}
.brand-icon{
  width:52px;height:52px;background:linear-gradient(135deg,var(--fire),var(--fire2));
  border-radius:10px;display:flex;align-items:center;justify-content:center;
  font-size:1.5rem;flex-shrink:0;
  box-shadow:0 0 30px rgba(255,76,28,.4);
}
.brand-text{}
.brand-name{
  font-family:'Syne',sans-serif;font-weight:800;font-size:2rem;
  line-height:1;letter-spacing:-1px;
  background:linear-gradient(135deg,var(--fire) 0%,var(--fire2) 60%,var(--amber) 100%);
  -webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;
}
.brand-sub{font-size:.65rem;color:var(--muted);letter-spacing:3px;text-transform:uppercase;margin-top:.25rem;}

/* ── STATUS READOUTS ── */
.readouts{display:flex;flex-direction:column;gap:.8rem;}
.readout{
  display:flex;align-items:center;gap:.8rem;
  padding:.6rem .8rem;border:1px solid var(--border);border-radius:4px;
  background:rgba(255,76,28,.03);
}
.readout-dot{width:6px;height:6px;border-radius:50%;background:var(--fire);box-shadow:0 0 8px var(--fire);animation:dotBlink 2s ease-in-out infinite;}
@keyframes dotBlink{0%,100%{opacity:1}50%{opacity:.3}}
.readout-dot.green{background:#4CAF50;box-shadow:0 0 8px #4CAF50;animation-delay:.5s;}
.readout-dot.amber{background:var(--amber);box-shadow:0 0 8px var(--amber);animation-delay:1s;}
.readout-label{font-size:.65rem;letter-spacing:2px;text-transform:uppercase;color:var(--muted);}
.readout-value{margin-left:auto;font-size:.7rem;color:var(--fire);font-weight:700;}

/* ── DIAGONAL SLASH DECO ── */
.slash-deco{
  position:absolute;bottom:0;right:0;width:60%;height:60%;
  overflow:hidden;pointer-events:none;
}
.slash-deco::before,.slash-deco::after{
  content:'';position:absolute;
  width:200%;height:1px;
  background:linear-gradient(90deg,transparent,rgba(255,76,28,.2),transparent);
  transform-origin:left center;
}
.slash-deco::before{transform:rotate(-35deg);top:30%;}
.slash-deco::after{transform:rotate(-35deg);top:50%;opacity:.5;}

/* ── RIGHT PANEL ── */
.right-panel::before{
  content:'';position:absolute;top:0;left:0;right:0;height:2px;
  background:linear-gradient(90deg,transparent,var(--fire),var(--fire2),transparent);
  opacity:.6;
}

.login-tag{
  font-size:.6rem;letter-spacing:4px;text-transform:uppercase;color:var(--muted);
  margin-bottom:2.5rem;display:flex;align-items:center;gap:.6rem;
}
.login-tag::before{content:'';flex:0 0 24px;height:1px;background:var(--fire);opacity:.6;}

.login-title{
  font-family:'Syne',sans-serif;font-weight:800;font-size:2.2rem;
  line-height:1.1;letter-spacing:-1px;margin-bottom:.5rem;
  color:var(--text);
}
.login-title span{
  background:linear-gradient(135deg,var(--fire),var(--fire2));
  -webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;
}
.login-desc{font-size:.78rem;color:var(--muted);margin-bottom:2.5rem;line-height:1.6;}

/* ── FORM FIELDS ── */
.field{margin-bottom:1.4rem;position:relative;}
.field-label{
  font-size:.6rem;letter-spacing:3px;text-transform:uppercase;
  color:var(--muted);margin-bottom:.5rem;display:flex;align-items:center;gap:.5rem;
}
.field-label::before{content:'//';color:var(--fire);opacity:.6;}
.field-wrap{position:relative;}
.field-wrap input{
  width:100%;padding:.85rem 1rem .85rem 3rem;
  background:var(--panel);border:1px solid var(--border2);border-radius:4px;
  color:var(--text);font-family:'Space Mono',monospace;font-size:.85rem;
  outline:none;transition:border-color .2s,box-shadow .2s;
  letter-spacing:.5px;
}
.field-wrap input:focus{
  border-color:var(--fire);
  box-shadow:0 0 0 2px rgba(255,76,28,.12),inset 0 0 20px rgba(255,76,28,.03);
}
.field-icon{
  position:absolute;left:.9rem;top:50%;transform:translateY(-50%);
  font-size:.9rem;opacity:.5;pointer-events:none;
}
.field-wrap input:focus ~ .field-corner{opacity:1;}
.field-corner{
  position:absolute;bottom:-1px;right:-1px;width:8px;height:8px;
  border-bottom:2px solid var(--fire);border-right:2px solid var(--fire);
  opacity:0;transition:opacity .2s;pointer-events:none;
}
.field-corner-tl{
  position:absolute;top:-1px;left:-1px;width:8px;height:8px;
  border-top:2px solid var(--fire);border-left:2px solid var(--fire);
  opacity:0;transition:opacity .2s;pointer-events:none;
}
.field-wrap input:focus ~ .field-corner,
.field-wrap input:focus ~ .field-corner-tl{opacity:1;}

/* ── LOGIN BUTTON ── */
.login-btn{
  width:100%;padding:1rem;margin-top:.5rem;
  background:linear-gradient(135deg,var(--fire) 0%,var(--fire2) 100%);
  border:none;border-radius:4px;color:#fff;
  font-family:'Syne',sans-serif;font-weight:700;font-size:.95rem;
  letter-spacing:2px;text-transform:uppercase;
  cursor:pointer;position:relative;overflow:hidden;
  transition:transform .15s,box-shadow .2s;
}
.login-btn::before{
  content:'';position:absolute;top:0;left:-100%;width:100%;height:100%;
  background:linear-gradient(90deg,transparent,rgba(255,255,255,.15),transparent);
  transition:left .4s;
}
.login-btn:hover::before{left:100%;}
.login-btn:hover{transform:translateY(-1px);box-shadow:0 8px 24px rgba(255,76,28,.35);}
.login-btn:active{transform:translateY(0);}

/* ── ERROR ── */
.error{
  background:rgba(255,76,28,.08);
  border-left:3px solid var(--fire);
  padding:.7rem 1rem;color:#ff7755;font-size:.75rem;
  margin-bottom:1.4rem;letter-spacing:.5px;
}

/* ── HINT ── */
.hint{
  margin-top:1.8rem;padding-top:1.2rem;
  border-top:1px solid var(--border);
  font-size:.65rem;color:var(--dim);letter-spacing:1px;
  display:flex;gap:.8rem;align-items:center;
}
.hint-key{
  background:rgba(255,76,28,.08);border:1px solid var(--border2);
  padding:.2rem .5rem;border-radius:2px;color:var(--muted);font-weight:700;
}

/* ── CORNER MARKS ── */
.corner-mark{
  position:absolute;width:16px;height:16px;
}
.corner-mark.tl{top:1.5rem;left:1.5rem;border-top:1px solid var(--fire);border-left:1px solid var(--fire);}
.corner-mark.br{bottom:1.5rem;right:1.5rem;border-bottom:1px solid var(--fire);border-right:1px solid var(--fire);}

/* ── VERSION TAG ── */
.version-tag{
  position:absolute;bottom:2rem;left:3rem;
  font-size:.6rem;color:var(--dim);letter-spacing:2px;
}

@media(max-width:768px){
  body{overflow:auto;flex-direction:column;}
  .left-panel{width:100%;height:280px;}
  .right-panel{width:100%;padding:2.5rem 1.8rem;}
}
</style>
</head>
<body>

<!-- LEFT PANEL -->
<div class="left-panel">
  <div class="scanline"></div>
  <div class="thermal-blob blob1"></div>
  <div class="thermal-blob blob2"></div>
  <div class="thermal-blob blob3"></div>
  <div class="slash-deco"></div>

  <div class="left-content">
    <div class="brand">
      <div class="brand-icon">🌡️</div>
      <div class="brand-text">
        <div class="brand-name">HeatWatch</div>
        <div class="brand-sub">Barangay Health Monitoring</div>
      </div>
    </div>

    <div class="readouts">
      <div class="readout">
        <div class="readout-dot"></div>
        <div class="readout-label">System Status</div>
        <div class="readout-value">ONLINE</div>
      </div>
      <div class="readout">
        <div class="readout-dot green"></div>
        <div class="readout-label">Database</div>
        <div class="readout-value">CONNECTED</div>
      </div>
      <div class="readout">
        <div class="readout-dot amber"></div>
        <div class="readout-label">Monitoring</div>
        <div class="readout-value">ACTIVE</div>
      </div>
    </div>

    <div class="version-tag">HW-SYS // v2.0 // SECURE</div>
  </div>

  <div class="corner-mark tl"></div>
  <div class="corner-mark br"></div>
</div>

<!-- RIGHT PANEL -->
<div class="right-panel">
  <div class="login-tag">Access Portal</div>
  <h1 class="login-title">Sign in to<br><span>Command Center</span></h1>
  <p class="login-desc">Authorized personnel only. Monitor heat index levels and resident wellness data across all 5 barangays.</p>

  <?php if($error): ?>
  <div class="error">⚠ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST">
    <div class="field">
      <div class="field-label">Username</div>
      <div class="field-wrap">
        <span class="field-icon">👤</span>
        <input type="text" name="username" placeholder="Enter username" required autocomplete="username">
        <div class="field-corner"></div>
        <div class="field-corner-tl"></div>
      </div>
    </div>
    <div class="field">
      <div class="field-label">Password</div>
      <div class="field-wrap">
        <span class="field-icon">🔒</span>
        <input type="password" name="password" placeholder="••••••••••" required autocomplete="current-password">
        <div class="field-corner"></div>
        <div class="field-corner-tl"></div>
      </div>
    </div>
    <button type="submit" class="login-btn">Authenticate →</button>
  </form>

  <div class="hint">
    <span>Default credentials:</span>
    <span class="hint-key">admin</span>
    <span style="opacity:.4">/</span>
    <span class="hint-key">admin123</span>
  </div>
</div>

</body>
</html>