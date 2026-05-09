<?php
// ============================================================
// manage.php — HeatWatch CRUD for all sections
// Sections: residents | heat | wellness | illness | zones
// ============================================================

if(isset($_GET['logout'])){
    if(session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION = [];
    if(ini_get('session.use_cookies')){
        $p = session_get_cookie_params();
        setcookie(session_name(),'',time()-42000,$p['path'],$p['domain'],$p['secure'],$p['httponly']);
    }
    session_destroy();
    header('Location: index.php');
    exit;
}

require_once 'db.php';
requireLogin();

$section = $_GET['section'] ?? 'residents';
if($section==='barangays') $section='barangays';
$msg = '';

if($_SERVER['REQUEST_METHOD']==='POST'){
    $action = $_POST['action'] ?? '';
    $esc = fn($v) => $conn->real_escape_string(trim($v ?? ''));

    if($action==='add_resident'){
        $name=$esc($_POST['name']); $age=(int)$_POST['age'];
        $address=$esc($_POST['address']); $zone=(int)$_POST['zone_id'];
        $med=$esc($_POST['medical_condition']);
        $vuln = isVulnerable($age, $med) ? 1 : 0;
        $zone_val = $zone ? $zone : 'NULL';
        $conn->query("INSERT INTO residents (name,age,address,zone_id,medical_condition,is_vulnerable) VALUES ('$name',$age,'$address',$zone_val,'$med',$vuln)");
        $msg='✅ Resident added.';
    }
    elseif($action==='edit_resident'){
        $id=(int)$_POST['id']; $name=$esc($_POST['name']); $age=(int)$_POST['age'];
        $address=$esc($_POST['address']); $zone=(int)$_POST['zone_id']; $med=$esc($_POST['medical_condition']);
        $vuln=isVulnerable($age,$med)?1:0;
        $zone_val=$zone?$zone:'NULL';
        $conn->query("UPDATE residents SET name='$name',age=$age,address='$address',zone_id=$zone_val,medical_condition='$med',is_vulnerable=$vuln WHERE id=$id");
        $msg='✅ Resident updated.';
    }
    elseif($action==='delete_resident'){
        $id=(int)$_POST['id'];
        $conn->query("DELETE FROM residents WHERE id=$id");
        $msg='🗑️ Resident deleted.';
    }
    elseif($action==='add_heat'){
        $date=$esc($_POST['log_date']); $temp=(float)$_POST['temperature']; $hum=(float)$_POST['humidity'];
        $level=getHeatLevel($temp); $by=$_SESSION['user_id'];
        $conn->query("INSERT INTO heat_index_logs (log_date,temperature,humidity,heat_level,recorded_by) VALUES ('$date',$temp,$hum,'$level',$by)");
        $msg='✅ Heat index logged.';
    }
    elseif($action==='edit_heat'){
        $id=(int)$_POST['id']; $date=$esc($_POST['log_date']); $temp=(float)$_POST['temperature']; $hum=(float)$_POST['humidity'];
        $level=getHeatLevel($temp);
        $conn->query("UPDATE heat_index_logs SET log_date='$date',temperature=$temp,humidity=$hum,heat_level='$level' WHERE id=$id");
        $msg='✅ Log updated.';
    }
    elseif($action==='delete_heat'){
        $conn->query("DELETE FROM heat_index_logs WHERE id=".(int)$_POST['id']);
        $msg='🗑️ Log deleted.';
    }
    elseif($action==='add_wellness'){
        $rid=(int)$_POST['resident_id']; $date=$esc($_POST['check_date']);
        $status=$esc($_POST['status']); $notes=$esc($_POST['notes']); $by=$_SESSION['user_id'];
        $conn->query("INSERT INTO wellness_checks (resident_id,check_date,status,notes,checked_by) VALUES ($rid,'$date','$status','$notes',$by)");
        $msg='✅ Wellness check added.';
    }
    elseif($action==='edit_wellness'){
        $id=(int)$_POST['id']; $rid=(int)$_POST['resident_id']; $date=$esc($_POST['check_date']);
        $status=$esc($_POST['status']); $notes=$esc($_POST['notes']);
        $conn->query("UPDATE wellness_checks SET resident_id=$rid,check_date='$date',status='$status',notes='$notes' WHERE id=$id");
        $msg='✅ Check updated.';
    }
    elseif($action==='delete_wellness'){
        $conn->query("DELETE FROM wellness_checks WHERE id=".(int)$_POST['id']);
        $msg='🗑️ Check deleted.';
    }
    elseif($action==='add_illness'){
        $rid=(int)$_POST['resident_id']; $date=$esc($_POST['case_date']);
        $type=$esc($_POST['illness_type']); $outcome=$esc($_POST['outcome']); $notes=$esc($_POST['notes']);
        $conn->query("INSERT INTO illness_cases (resident_id,case_date,illness_type,outcome,notes) VALUES ($rid,'$date','$type','$outcome','$notes')");
        $msg='✅ Illness case recorded.';
    }
    elseif($action==='edit_illness'){
        $id=(int)$_POST['id']; $rid=(int)$_POST['resident_id']; $date=$esc($_POST['case_date']);
        $type=$esc($_POST['illness_type']); $outcome=$esc($_POST['outcome']); $notes=$esc($_POST['notes']);
        $conn->query("UPDATE illness_cases SET resident_id=$rid,case_date='$date',illness_type='$type',outcome='$outcome',notes='$notes' WHERE id=$id");
        $msg='✅ Case updated.';
    }
    elseif($action==='delete_illness'){
        $conn->query("DELETE FROM illness_cases WHERE id=".(int)$_POST['id']);
        $msg='🗑️ Case deleted.';
    }
    elseif($action==='add_barangay'){
        $name=$esc($_POST['zone_name']); $desc=$esc($_POST['description']);
        $conn->query("INSERT INTO barangays (barangay_name,description) VALUES ('$name','$desc')");
        $msg='✅ Barangay added.';
    }
    elseif($action==='edit_zone'||$action==='edit_barangay'){
        $id=(int)$_POST['id']; $name=$esc($_POST['zone_name']); $desc=$esc($_POST['description']);
        $conn->query("UPDATE barangays SET barangay_name='$name',description='$desc' WHERE id=$id");
        $msg='✅ Barangay updated.';
    }
    elseif($action==='delete_zone'||$action==='delete_barangay'){
        $conn->query("DELETE FROM barangays WHERE id=".(int)$_POST['id']);
        $msg='🗑️ Barangay deleted.';
    }

    header("Location: manage.php?section=$section&msg=".urlencode($msg)); exit;
}

if(isset($_GET['msg'])) $msg = urldecode($_GET['msg']);

$zones_list = []; $r=$conn->query("SELECT *, barangay_name AS zone_name FROM barangays ORDER BY barangay_name"); while($z=$r->fetch_assoc()) $zones_list[]=$z;
$residents_list = []; $r=$conn->query("SELECT * FROM residents ORDER BY name"); while($z=$r->fetch_assoc()) $residents_list[]=$z;

$search = $conn->real_escape_string(trim($_GET['search'] ?? ''));
$sort = in_array($_GET['sort']??'', ['name','age','zone_name','check_date','log_date','case_date']) ? $_GET['sort'] : '';

$editData = null;
if(isset($_GET['edit'], $_GET['id'])){
    $eid = (int)$_GET['id'];
    if($section==='residents') $editData = $conn->query("SELECT * FROM residents WHERE id=$eid")->fetch_assoc();
    elseif($section==='heat') $editData = $conn->query("SELECT * FROM heat_index_logs WHERE id=$eid")->fetch_assoc();
    elseif($section==='wellness') $editData = $conn->query("SELECT * FROM wellness_checks WHERE id=$eid")->fetch_assoc();
    elseif($section==='illness') $editData = $conn->query("SELECT * FROM illness_cases WHERE id=$eid")->fetch_assoc();
    elseif($section==='barangays') $editData = $conn->query("SELECT *, barangay_name AS zone_name FROM barangays WHERE id=$eid")->fetch_assoc();
}

$rows = [];
if($section==='residents'){
    $q = "SELECT r.*, b.barangay_name AS zone_name FROM residents r LEFT JOIN barangays b ON r.zone_id=b.id";
    if($search) $q.=" WHERE r.name LIKE '%$search%' OR b.barangay_name LIKE '%$search%'";
    $q.=" ORDER BY ".($sort ?: "r.is_vulnerable DESC, r.name")." ASC";
    $result=$conn->query($q); while($x=$result->fetch_assoc()) $rows[]=$x;
}
elseif($section==='heat'){
    $q = "SELECT h.*, u.full_name AS recorded_name FROM heat_index_logs h LEFT JOIN users u ON h.recorded_by=u.id";
    if($search) $q.=" WHERE h.heat_level LIKE '%$search%' OR h.log_date LIKE '%$search%'";
    $q.=" ORDER BY ".($sort ?: "h.log_date")." DESC";
    $result=$conn->query($q); while($x=$result->fetch_assoc()) $rows[]=$x;
}
elseif($section==='wellness'){
    $q = "SELECT wc.*, r.name AS resident_name, r.age, b.barangay_name AS zone_name
          FROM wellness_checks wc
          INNER JOIN residents r ON wc.resident_id=r.id
          LEFT JOIN barangays b ON r.zone_id=b.id";
    if($search) $q.=" WHERE r.name LIKE '%$search%' OR wc.status LIKE '%$search%'";
    $q.=" ORDER BY ".($sort ?: "wc.check_date")." DESC";
    $result=$conn->query($q); while($x=$result->fetch_assoc()) $rows[]=$x;
}
elseif($section==='illness'){
    $q = "SELECT ic.*, r.name AS resident_name, b.barangay_name AS zone_name
          FROM illness_cases ic
          INNER JOIN residents r ON ic.resident_id=r.id
          LEFT JOIN barangays b ON r.zone_id=b.id";
    if($search) $q.=" WHERE r.name LIKE '%$search%' OR ic.illness_type LIKE '%$search%'";
    $q.=" ORDER BY ".($sort ?: "ic.case_date")." DESC";
    $result=$conn->query($q); while($x=$result->fetch_assoc()) $rows[]=$x;
}
elseif($section==='barangays'){
    $q = "SELECT b.*, b.barangay_name AS zone_name, COUNT(r.id) AS resident_count FROM barangays b LEFT JOIN residents r ON r.zone_id=b.id";
    if($search) $q.=" WHERE b.barangay_name LIKE '%$search%'";
    $q.=" GROUP BY b.id ORDER BY b.barangay_name ASC";
    $result=$conn->query($q); while($x=$result->fetch_assoc()) $rows[]=$x;
}

$sections = [
    'residents'=>['icon'=>'👥','title'=>'Resident Registry','code'=>'RES'],
    'heat'     =>['icon'=>'🌡️','title'=>'Heat Index Log','code'=>'HTX'],
    'wellness' =>['icon'=>'🩺','title'=>'Wellness Checks','code'=>'WLN'],
    'illness'  =>['icon'=>'🚑','title'=>'Illness Cases','code'=>'ILL'],
    'barangays'=>['icon'=>'🗺️','title'=>'Barangay Management','code'=>'BRY'],
];
$cur = $sections[$section];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>HeatWatch — <?= $cur['title'] ?></title>
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:ital,wght@0,400;0,700;1,400&family=Syne:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --fire:#FF4C1C;--fire2:#FF8C00;--amber:#FFAA00;
  --deep:#080604;--surface:#0F0B08;--panel:#14100C;--card:#1A1410;
  --border:#2C2018;--border2:#3A2A18;
  --text:#F2E8DC;--muted:#7A6450;--dim:#4A3828;
  --nav-h:64px;
  --input:#110D0A;
}
body{font-family:'Space Mono',monospace;background:var(--deep);color:var(--text);min-height:100vh;}
a{color:inherit;text-decoration:none;}

/* ══ TOP NAV ══════════════════════════════════════════════ */
.topnav{
  position:fixed;top:0;left:0;right:0;height:var(--nav-h);z-index:100;
  background:var(--surface);border-bottom:1px solid var(--border);
  display:flex;align-items:center;padding:0 1.5rem;gap:0;overflow:hidden;
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
.nav-link-icon{font-size:.85rem;opacity:.65;}
.nav-right{margin-left:auto;display:flex;align-items:center;gap:1rem;flex-shrink:0;}
.nav-user{font-size:.65rem;letter-spacing:1px;color:var(--muted);display:flex;align-items:center;gap:.5rem;}
.nav-user-dot{width:6px;height:6px;border-radius:50%;background:#4CAF50;box-shadow:0 0 6px #4CAF50;}
.nav-logout{
  padding:.4rem .9rem;background:rgba(255,76,28,.1);border:1px solid rgba(255,76,28,.25);
  border-radius:3px;color:var(--fire);font-family:'Space Mono',monospace;
  font-size:.65rem;letter-spacing:1.5px;text-transform:uppercase;cursor:pointer;transition:background .2s;
}
.nav-logout:hover{background:rgba(255,76,28,.2);}

/* ══ PAGE ══════════════════════════════════════════════════ */
.page{margin-top:var(--nav-h);padding:2rem 1.8rem;}

/* ══ PAGE HEADER ══════════════════════════════════════════ */
.page-header{
  display:flex;align-items:flex-end;justify-content:space-between;
  margin-bottom:1.8rem;padding-bottom:1.2rem;
  border-bottom:1px solid var(--border);
  flex-wrap:wrap;gap:1rem;position:relative;overflow:hidden;
}
.page-header::before{
  content:attr(data-code);
  position:absolute;right:0;bottom:1rem;
  font-family:'Syne',sans-serif;font-size:5rem;font-weight:800;
  color:rgba(255,76,28,.04);letter-spacing:-2px;pointer-events:none;line-height:1;
}
.page-label{font-size:.6rem;letter-spacing:4px;text-transform:uppercase;color:var(--muted);margin-bottom:.3rem;}
.page-title{font-family:'Syne',sans-serif;font-weight:800;font-size:1.6rem;letter-spacing:-.5px;}
.toolbar{display:flex;gap:.6rem;align-items:center;flex-wrap:wrap;}

/* ══ SEARCH INPUTS ═════════════════════════════════════════ */
.search-wrap{position:relative;display:flex;align-items:center;}
.search-wrap input[type=text]{
  padding:.5rem .8rem .5rem 2.2rem;
  background:var(--input);border:1px solid var(--border2);border-radius:3px;
  color:var(--text);font-family:'Space Mono',monospace;font-size:.75rem;
  outline:none;transition:border-color .2s;width:200px;letter-spacing:.3px;
}
.search-wrap input[type=text]:focus{border-color:var(--fire);}
.search-wrap::before{
  content:'⌕';position:absolute;left:.65rem;
  color:var(--muted);font-size:.9rem;pointer-events:none;
}

/* ══ BUTTONS ═══════════════════════════════════════════════ */
.btn{
  padding:.5rem 1rem;border:none;border-radius:3px;cursor:pointer;
  font-family:'Space Mono',monospace;font-size:.72rem;letter-spacing:1px;
  text-transform:uppercase;transition:all .18s;display:inline-flex;align-items:center;gap:.4rem;
}
.btn-primary{background:linear-gradient(135deg,var(--fire),var(--fire2));color:#fff;}
.btn-primary:hover{opacity:.88;transform:translateY(-1px);}
.btn-ghost{background:rgba(255,255,255,.04);color:var(--muted);border:1px solid var(--border);}
.btn-ghost:hover{background:rgba(255,255,255,.07);color:var(--text);}
.btn-sm{padding:.3rem .6rem;font-size:.65rem;}
.btn-edit{background:rgba(255,140,0,.1);color:#FFB74D;border:1px solid rgba(255,140,0,.2);}
.btn-edit:hover{background:rgba(255,140,0,.2);}
.btn-del{background:rgba(244,67,54,.08);color:#EF5350;border:1px solid rgba(244,67,54,.2);}
.btn-del:hover{background:rgba(244,67,54,.18);}

/* ══ FLASH MESSAGE ═════════════════════════════════════════ */
.flash{
  padding:.65rem 1rem;margin-bottom:1.4rem;border-radius:3px;
  font-size:.72rem;letter-spacing:.5px;display:flex;align-items:center;gap:.6rem;
}
.flash-ok{background:rgba(76,175,80,.08);border-left:3px solid #4CAF50;color:#66BB6A;}
.flash-del{background:rgba(244,67,54,.08);border-left:3px solid #f44336;color:#EF5350;}

/* ══ FORM CARD ═════════════════════════════════════════════ */
.form-card{
  background:var(--card);border:1px solid var(--border);border-radius:6px;
  margin-bottom:1.6rem;overflow:hidden;
}
.form-card-head{
  padding:.8rem 1.2rem;border-bottom:1px solid var(--border);
  display:flex;align-items:center;gap:.8rem;
  background:var(--panel);
}
.form-card-head-icon{
  width:26px;height:26px;background:rgba(255,76,28,.12);border:1px solid rgba(255,76,28,.2);
  border-radius:3px;display:flex;align-items:center;justify-content:center;font-size:.8rem;
}
.form-card-title{
  font-family:'Syne',sans-serif;font-weight:700;font-size:.82rem;letter-spacing:.5px;
}
.form-card-code{
  margin-left:auto;font-size:.6rem;color:var(--dim);letter-spacing:2px;
  border:1px solid var(--border);padding:.1rem .4rem;border-radius:2px;
}
.form-body{padding:1.2rem;}
.form-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:.9rem;}
.form-grid .full{grid-column:1/-1;}
.flabel{
  display:block;font-size:.6rem;letter-spacing:2.5px;text-transform:uppercase;
  color:var(--muted);margin-bottom:.35rem;
}
.flabel::before{content:'// ';color:var(--fire);opacity:.5;}
.form-grid input,
.form-grid select,
.form-grid textarea{
  width:100%;padding:.6rem .85rem;
  background:var(--input);border:1px solid var(--border2);border-radius:3px;
  color:var(--text);font-family:'Space Mono',monospace;font-size:.8rem;
  outline:none;transition:border-color .2s;letter-spacing:.3px;
}
.form-grid input:focus,
.form-grid select:focus,
.form-grid textarea:focus{border-color:var(--fire);box-shadow:0 0 0 2px rgba(255,76,28,.1);}
.form-grid select option{background:var(--panel);}
textarea{resize:vertical;min-height:64px;}
.form-actions{
  padding:.9rem 1.2rem;border-top:1px solid var(--border);
  background:var(--panel);display:flex;gap:.6rem;align-items:center;
}

/* ══ TABLE SECTION ═════════════════════════════════════════ */
.section-head{display:flex;align-items:center;gap:.8rem;margin-bottom:.6rem;}
.section-title{font-family:'Syne',sans-serif;font-weight:700;font-size:.85rem;letter-spacing:.5px;}
.section-badge{font-size:.6rem;color:var(--muted);border:1px solid var(--border);padding:.12rem .45rem;border-radius:2px;letter-spacing:1px;}
.section-line{flex:1;height:1px;background:var(--border);}
.join-note{
  font-size:.62rem;color:var(--dim);margin-bottom:.8rem;
  padding:.45rem .8rem;background:rgba(255,140,0,.03);
  border-left:2px solid var(--fire2);border-radius:0 2px 2px 0;
  font-style:italic;letter-spacing:.3px;
}

/* ══ TABLE ═════════════════════════════════════════════════ */
.tbl-wrap{background:var(--card);border:1px solid var(--border);border-radius:6px;overflow:hidden;}
table{width:100%;border-collapse:collapse;}
thead{background:var(--panel);}
thead th{
  padding:.6rem 1rem;text-align:left;
  font-size:.6rem;letter-spacing:2px;text-transform:uppercase;
  color:var(--dim);border-bottom:1px solid var(--border);
  white-space:nowrap;font-weight:400;
}
thead th a{color:var(--dim);transition:color .15s;}
thead th a:hover{color:var(--fire);}
tbody tr{border-bottom:1px solid rgba(255,255,255,.03);transition:background .12s;}
tbody tr:hover{background:rgba(255,76,28,.035);}
tbody td{padding:.65rem 1rem;font-size:.82rem;vertical-align:middle;}
.td-name{font-family:'Syne',sans-serif;font-weight:600;font-size:.82rem;}
.td-muted{color:var(--muted);font-size:.75rem;}
.td-dim{color:var(--dim);font-size:.75rem;}
.empty-row td{text-align:center;color:var(--muted);padding:3rem;font-size:.78rem;font-style:italic;}

/* ══ BADGES ════════════════════════════════════════════════ */
.badge{
  display:inline-flex;align-items:center;gap:.2rem;
  padding:.1rem .42rem;border-radius:2px;
  font-size:.6rem;letter-spacing:.5px;font-weight:700;font-family:'Space Mono',monospace;
}
.b-vuln  {background:rgba(255,76,28,.12);color:#FF6B4A;border:1px solid rgba(255,76,28,.2);}
.b-good  {background:rgba(76,175,80,.1);color:#66BB6A;border:1px solid rgba(76,175,80,.2);}
.b-warn  {background:rgba(255,193,7,.1);color:#FFD740;border:1px solid rgba(255,193,7,.2);}
.b-ref   {background:rgba(244,67,54,.1);color:#EF5350;border:1px solid rgba(244,67,54,.2);}
.b-zone  {background:rgba(255,140,0,.08);color:#FFB74D;border:1px solid rgba(255,140,0,.15);}
.b-none  {background:rgba(255,255,255,.04);color:var(--dim);border:1px solid var(--border);}
.b-caution  {background:rgba(255,193,7,.1);color:#FFD740;border:1px solid rgba(255,193,7,.2);}
.b-xcaution {background:rgba(255,87,34,.12);color:#FF7043;border:1px solid rgba(255,87,34,.2);}
.b-danger   {background:rgba(244,67,54,.12);color:#EF5350;border:1px solid rgba(244,67,54,.2);}
.b-xdanger  {background:rgba(156,39,176,.12);color:#CE93D8;border:1px solid rgba(156,39,176,.2);}
.b-normal   {background:rgba(76,175,80,.1);color:#66BB6A;border:1px solid rgba(76,175,80,.2);}

/* ══ DELETE MODAL ══════════════════════════════════════════ */
.modal-overlay{
  display:none;position:fixed;inset:0;
  background:rgba(0,0,0,.75);backdrop-filter:blur(4px);
  z-index:1000;align-items:center;justify-content:center;
}
.modal-overlay.show{display:flex;}
.modal-box{
  background:var(--card);border:1px solid var(--border2);border-radius:6px;
  padding:0;max-width:380px;width:90%;overflow:hidden;
  box-shadow:0 24px 64px rgba(0,0,0,.6);
  animation:modalIn .2s cubic-bezier(.23,1,.32,1);
}
@keyframes modalIn{from{opacity:0;transform:scale(.96) translateY(8px)}to{opacity:1;transform:none}}
.modal-head{
  padding:.8rem 1.2rem;border-bottom:1px solid var(--border);
  background:var(--panel);display:flex;align-items:center;gap:.7rem;
}
.modal-head-icon{
  width:28px;height:28px;background:rgba(244,67,54,.1);border:1px solid rgba(244,67,54,.2);
  border-radius:3px;display:flex;align-items:center;justify-content:center;font-size:.85rem;
}
.modal-head-title{font-family:'Syne',sans-serif;font-weight:700;font-size:.85rem;}
.modal-body{padding:1.4rem 1.2rem;}
.modal-body p{font-size:.78rem;color:var(--muted);line-height:1.6;margin-bottom:1.2rem;}
.modal-actions{display:flex;gap:.6rem;}

@media(max-width:768px){
  .nav-links .nav-link span:last-child{display:none;}
  .page{padding:1.2rem;}
  .form-grid{grid-template-columns:1fr;}
  .page-header{flex-direction:column;align-items:flex-start;}
}
</style>
</head>
<body>

<!-- ══ TOP NAV ══════════════════════════════════════════════ -->
<nav class="topnav">
  <div class="nav-brand">
    <div class="nav-brand-icon">🌡️</div>
    <div class="nav-brand-name">HeatWatch</div>
  </div>
  <div class="nav-links">
    <a href="dashboard.php" class="nav-link">
      <span class="nav-link-icon">⬡</span><span>Dashboard</span>
    </a>
    <a href="manage.php?section=residents" class="nav-link <?= $section==='residents'?'active':'' ?>">
      <span class="nav-link-icon">⬡</span><span>Residents</span>
    </a>
    <a href="manage.php?section=heat" class="nav-link <?= $section==='heat'?'active':'' ?>">
      <span class="nav-link-icon">⬡</span><span>Heat Logs</span>
    </a>
    <a href="manage.php?section=wellness" class="nav-link <?= $section==='wellness'?'active':'' ?>">
      <span class="nav-link-icon">⬡</span><span>Wellness</span>
    </a>
    <a href="manage.php?section=illness" class="nav-link <?= $section==='illness'?'active':'' ?>">
      <span class="nav-link-icon">⬡</span><span>Illness Cases</span>
    </a>
    <a href="manage.php?section=barangays" class="nav-link <?= $section==='barangays'?'active':'' ?>">
      <span class="nav-link-icon">⬡</span><span>Barangays</span>
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

<!-- ══ PAGE ═════════════════════════════════════════════════ -->
<div class="page">

  <!-- PAGE HEADER -->
  <div class="page-header" data-code="<?= $cur['code'] ?>">
    <div>
      <div class="page-label">Manage / <?= $cur['title'] ?></div>
      <div class="page-title"><?= $cur['icon'] ?> <?= $cur['title'] ?></div>
    </div>
    <div class="toolbar">
      <form method="GET" style="display:flex;gap:.5rem;align-items:center;">
        <input type="hidden" name="section" value="<?= $section ?>">
        <div class="search-wrap">
          <input type="text" name="search" placeholder="Search records..." value="<?= htmlspecialchars($search) ?>">
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Search</button>
        <?php if($search): ?>
          <a href="manage.php?section=<?= $section ?>" class="btn btn-ghost btn-sm">Clear</a>
        <?php endif; ?>
      </form>
    </div>
  </div>

  <!-- FLASH MESSAGE -->
  <?php if($msg): ?>
  <div class="flash <?= str_contains($msg,'🗑️')?'flash-del':'flash-ok' ?>">
    <?= htmlspecialchars($msg) ?>
  </div>
  <?php endif; ?>

  <!-- ══ ADD / EDIT FORM ══════════════════════════════════ -->
  <div class="form-card">
    <div class="form-card-head">
      <div class="form-card-head-icon"><?= $editData ? '✏️' : '＋' ?></div>
      <div class="form-card-title"><?= $editData ? 'Edit Record' : 'Add New Entry' ?> — <?= $cur['title'] ?></div>
      <div class="form-card-code"><?= $cur['code'] ?>-<?= $editData ? 'EDIT' : 'NEW' ?></div>
    </div>
    <div class="form-body">
      <form method="POST" action="manage.php?section=<?= $section ?>">
        <?php if($editData): ?><input type="hidden" name="id" value="<?= $editData['id'] ?>"><?php endif; ?>

        <!-- RESIDENTS FORM -->
        <?php if($section==='residents'): ?>
        <input type="hidden" name="action" value="<?= $editData?'edit_resident':'add_resident' ?>">
        <div class="form-grid">
          <div>
            <label class="flabel">Full Name *</label>
            <input type="text" name="name" required value="<?= htmlspecialchars($editData['name']??'') ?>">
          </div>
          <div>
            <label class="flabel">Age *</label>
            <input type="number" name="age" required min="0" max="120" value="<?= $editData['age']??'' ?>">
          </div>
          <div>
            <label class="flabel">Barangay</label>
            <select name="zone_id">
              <option value="">— Select Barangay —</option>
              <?php foreach($zones_list as $z): ?>
              <option value="<?= $z['id'] ?>" <?= ($editData['zone_id']??'')==$z['id']?'selected':'' ?>><?= htmlspecialchars($z['zone_name'] ?? $z['barangay_name'] ?? '') ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="full">
            <label class="flabel">Address</label>
            <input type="text" name="address" value="<?= htmlspecialchars($editData['address']??'') ?>">
          </div>
          <div class="full">
            <label class="flabel">Medical Condition (leave blank if none)</label>
            <textarea name="medical_condition"><?= htmlspecialchars($editData['medical_condition']??'') ?></textarea>
          </div>
        </div>

        <!-- HEAT LOG FORM -->
        <?php elseif($section==='heat'): ?>
        <input type="hidden" name="action" value="<?= $editData?'edit_heat':'add_heat' ?>">
        <div class="form-grid">
          <div>
            <label class="flabel">Date *</label>
            <input type="date" name="log_date" required value="<?= $editData['log_date']??date('Y-m-d') ?>">
          </div>
          <div>
            <label class="flabel">Temperature (°C) *</label>
            <input type="number" name="temperature" required step="0.1" min="0" max="70" value="<?= $editData['temperature']??'' ?>">
          </div>
          <div>
            <label class="flabel">Humidity (%)</label>
            <input type="number" name="humidity" step="0.1" min="0" max="100" value="<?= $editData['humidity']??'' ?>">
          </div>
        </div>

        <!-- WELLNESS FORM -->
        <?php elseif($section==='wellness'): ?>
        <input type="hidden" name="action" value="<?= $editData?'edit_wellness':'add_wellness' ?>">
        <div class="form-grid">
          <div>
            <label class="flabel">Resident *</label>
            <select name="resident_id" required>
              <option value="">— Select Resident —</option>
              <?php foreach($residents_list as $r): ?>
              <option value="<?= $r['id'] ?>" <?= ($editData['resident_id']??'')==$r['id']?'selected':'' ?>><?= htmlspecialchars($r['name']) ?> (age <?= $r['age'] ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="flabel">Check Date *</label>
            <input type="date" name="check_date" required value="<?= $editData['check_date']??date('Y-m-d') ?>">
          </div>
          <div>
            <label class="flabel">Status *</label>
            <select name="status" required>
              <?php foreach(['Good','Needs Monitoring','Referred'] as $s): ?>
              <option value="<?= $s ?>" <?= ($editData['status']??'')===$s?'selected':'' ?>><?= $s ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="full">
            <label class="flabel">Notes</label>
            <textarea name="notes"><?= htmlspecialchars($editData['notes']??'') ?></textarea>
          </div>
        </div>

        <!-- ILLNESS FORM -->
        <?php elseif($section==='illness'): ?>
        <input type="hidden" name="action" value="<?= $editData?'edit_illness':'add_illness' ?>">
        <div class="form-grid">
          <div>
            <label class="flabel">Resident *</label>
            <select name="resident_id" required>
              <option value="">— Select Resident —</option>
              <?php foreach($residents_list as $r): ?>
              <option value="<?= $r['id'] ?>" <?= ($editData['resident_id']??'')==$r['id']?'selected':'' ?>><?= htmlspecialchars($r['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="flabel">Case Date *</label>
            <input type="date" name="case_date" required value="<?= $editData['case_date']??date('Y-m-d') ?>">
          </div>
          <div>
            <label class="flabel">Illness Type *</label>
            <select name="illness_type" required>
              <?php foreach(['Heat Cramps','Heat Exhaustion','Heat Stroke','Heat Rash','Dehydration','Other'] as $t): ?>
              <option value="<?= $t ?>" <?= ($editData['illness_type']??'')===$t?'selected':'' ?>><?= $t ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="flabel">Outcome</label>
            <select name="outcome">
              <?php foreach(['Recovered','Hospitalized','Referred to RHU','Ongoing','Other'] as $o): ?>
              <option value="<?= $o ?>" <?= ($editData['outcome']??'')===$o?'selected':'' ?>><?= $o ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="full">
            <label class="flabel">Notes</label>
            <textarea name="notes"><?= htmlspecialchars($editData['notes']??'') ?></textarea>
          </div>
        </div>

        <!-- BARANGAYS FORM -->
        <?php elseif($section==='barangays'): ?>
        <input type="hidden" name="action" value="<?= $editData?'edit_barangay':'add_barangay' ?>">
        <div class="form-grid">
          <div>
            <label class="flabel">Barangay Name *</label>
            <input type="text" name="zone_name" required value="<?= htmlspecialchars($editData['barangay_name']??'') ?>">
          </div>
          <div class="full">
            <label class="flabel">Description</label>
            <textarea name="description"><?= htmlspecialchars($editData['description']??'') ?></textarea>
          </div>
        </div>
        <?php endif; ?>

        <div class="form-actions">
          <button type="submit" class="btn btn-primary">
            <?= $editData ? '💾 Update Record' : '＋ Add Record' ?>
          </button>
          <?php if($editData): ?>
          <a href="manage.php?section=<?= $section ?>" class="btn btn-ghost">Cancel</a>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>

  <!-- ══ DATA TABLE ═══════════════════════════════════════ -->
  <div class="section-head">
    <div class="section-title">Records</div>
    <div class="section-badge"><?= count($rows) ?> ENTRIES</div>
    <div class="section-line"></div>
  </div>

  <?php
  $joinNotes = [
    'residents' => 'LEFT JOIN barangays b ON r.zone_id = b.id — All residents shown, barangay shown if assigned.',
    'heat'      => 'LEFT JOIN users u ON h.recorded_by = u.id — All logs shown with recorder name if available.',
    'wellness'  => 'INNER JOIN residents r ON wc.resident_id = r.id — Only checks with a valid resident record.',
    'illness'   => 'INNER JOIN residents r ON ic.resident_id = r.id — Only cases linked to valid residents.',
    'barangays' => 'LEFT JOIN residents r ON r.zone_id = b.id GROUP BY b.id — Barangays with 0 residents still shown.',
  ];
  ?>
  <div class="join-note">SQL: <?= $joinNotes[$section] ?></div>

  <div class="tbl-wrap">
    <table>
      <thead>
        <?php if($section==='residents'): ?>
        <tr>
          <th><a href="?section=residents&sort=name">Name ↕</a></th>
          <th><a href="?section=residents&sort=age">Age ↕</a></th>
          <th>Barangay</th><th>Medical Condition</th><th>Vulnerable</th><th>Actions</th>
        </tr>
        <?php elseif($section==='heat'): ?>
        <tr>
          <th><a href="?section=heat&sort=log_date">Date ↕</a></th>
          <th>Temp (°C)</th><th>Humidity</th><th>Heat Level</th><th>Recorded By</th><th>Actions</th>
        </tr>
        <?php elseif($section==='wellness'): ?>
        <tr>
          <th>Resident</th><th>Age</th><th>Barangay</th>
          <th><a href="?section=wellness&sort=check_date">Date ↕</a></th>
          <th>Status</th><th>Notes</th><th>Actions</th>
        </tr>
        <?php elseif($section==='illness'): ?>
        <tr>
          <th>Resident</th><th>Barangay</th>
          <th><a href="?section=illness&sort=case_date">Date ↕</a></th>
          <th>Illness Type</th><th>Outcome</th><th>Actions</th>
        </tr>
        <?php elseif($section==='barangays'): ?>
        <tr><th>Barangay Name</th><th>Description</th><th>Residents</th><th>Actions</th></tr>
        <?php endif; ?>
      </thead>
      <tbody>
        <?php if(empty($rows)): ?>
        <tr class="empty-row">
          <td colspan="10">
            <?= $search ? 'No records match your search.' : 'No entries yet. Add one above.' ?>
          </td>
        </tr>
        <?php endif; ?>

        <?php foreach($rows as $row): ?>
        <tr>
          <?php if($section==='residents'): ?>
            <td>
              <div class="td-name"><?= htmlspecialchars($row['name']) ?></div>
              <?php if($row['is_vulnerable']): ?><span class="badge b-vuln">⚠ Vulnerable</span><?php endif; ?>
            </td>
            <td><?= $row['age'] ?></td>
            <td><?= $row['zone_name'] ? '<span class="badge b-zone">'.htmlspecialchars($row['zone_name']).'</span>' : '<span class="td-dim">—</span>' ?></td>
            <td class="td-muted" style="max-width:180px;overflow:hidden;white-space:nowrap;text-overflow:ellipsis"><?= htmlspecialchars(substr($row['medical_condition']??'—',0,40)) ?></td>
            <td><?= $row['is_vulnerable'] ? '<span class="badge b-vuln">Yes</span>' : '<span class="td-dim">No</span>' ?></td>

          <?php elseif($section==='heat'): ?>
            <td class="td-muted"><?= $row['log_date'] ?></td>
            <td><strong style="font-family:'Syne',sans-serif;font-size:.95rem"><?= $row['temperature'] ?>°C</strong></td>
            <td class="td-muted"><?= $row['humidity'] ?>%</td>
            <td>
              <?php
                $hl = $row['heat_level'];
                $bc = ['Normal'=>'b-normal','Caution'=>'b-caution','Extreme Caution'=>'b-xcaution','Danger'=>'b-danger','Extreme Danger'=>'b-xdanger'][$hl] ?? 'b-none';
              ?>
              <span class="badge <?= $bc ?>"><?= htmlspecialchars($hl) ?></span>
            </td>
            <td class="td-muted"><?= htmlspecialchars($row['recorded_name']??'—') ?></td>

          <?php elseif($section==='wellness'): ?>
            <td><div class="td-name"><?= htmlspecialchars($row['resident_name']) ?></div></td>
            <td class="td-muted"><?= $row['age'] ?></td>
            <td><?= $row['zone_name'] ? '<span class="badge b-zone">'.htmlspecialchars($row['zone_name']).'</span>' : '<span class="td-dim">—</span>' ?></td>
            <td class="td-muted"><?= $row['check_date'] ?></td>
            <td>
              <?php $bc=['Good'=>'b-good','Needs Monitoring'=>'b-warn','Referred'=>'b-ref'][$row['status']]??'b-none'; ?>
              <span class="badge <?= $bc ?>"><?= htmlspecialchars($row['status']) ?></span>
            </td>
            <td class="td-muted" style="max-width:150px;overflow:hidden;white-space:nowrap;text-overflow:ellipsis"><?= htmlspecialchars(substr($row['notes']??'',0,50)) ?></td>

          <?php elseif($section==='illness'): ?>
            <td><div class="td-name"><?= htmlspecialchars($row['resident_name']) ?></div></td>
            <td><?= $row['zone_name'] ? '<span class="badge b-zone">'.htmlspecialchars($row['zone_name']).'</span>' : '<span class="td-dim">—</span>' ?></td>
            <td class="td-muted"><?= $row['case_date'] ?></td>
            <td><?= htmlspecialchars($row['illness_type']) ?></td>
            <td class="td-muted"><?= htmlspecialchars($row['outcome']??'—') ?></td>

          <?php elseif($section==='barangays'): ?>
            <td><div class="td-name"><?= htmlspecialchars($row['zone_name']) ?></div></td>
            <td class="td-muted"><?= htmlspecialchars($row['description']??'') ?></td>
            <td><span class="badge b-zone"><?= $row['resident_count'] ?> residents</span></td>
          <?php endif; ?>

          <!-- ACTIONS -->
          <td>
            <div style="display:flex;gap:.4rem;">
              <a href="manage.php?section=<?= $section ?>&edit=1&id=<?= $row['id'] ?>" class="btn btn-sm btn-edit">✏ Edit</a>
              <button class="btn btn-sm btn-del" onclick="openDelete(<?= $row['id'] ?>,'<?= $section ?>')">🗑</button>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

</div><!-- .page -->

<!-- ══ DELETE MODAL ═════════════════════════════════════════ -->
<div class="modal-overlay" id="deleteModal">
  <div class="modal-box">
    <div class="modal-head">
      <div class="modal-head-icon">🗑</div>
      <div class="modal-head-title">Confirm Deletion</div>
    </div>
    <div class="modal-body">
      <p>This record will be permanently removed and cannot be recovered. Are you sure you want to proceed?</p>
      <form method="POST" id="deleteForm" action="manage.php?section=<?= $section ?>">
        <input type="hidden" name="id" id="deleteId">
        <input type="hidden" name="action" id="deleteAction">
        <div class="modal-actions">
          <button type="submit" class="btn btn-del">Yes, Delete</button>
          <button type="button" class="btn btn-ghost" onclick="closeDelete()">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
const deleteMap = {
  residents:'delete_resident', heat:'delete_heat',
  wellness:'delete_wellness', illness:'delete_illness', barangays:'delete_barangay'
};
function openDelete(id, section){
  document.getElementById('deleteId').value = id;
  document.getElementById('deleteAction').value = deleteMap[section];
  document.getElementById('deleteModal').classList.add('show');
}
function closeDelete(){
  document.getElementById('deleteModal').classList.remove('show');
}
document.getElementById('deleteModal').addEventListener('click', function(e){
  if(e.target === this) closeDelete();
});
</script>
</body>
</html>