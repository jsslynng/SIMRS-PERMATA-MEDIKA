<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/role_access.php';
function simrs_flash(string $msg): void { $_SESSION['flash'] = $msg; }
function simrs_take_flash(): string { $m = $_SESSION['flash'] ?? ''; unset($_SESSION['flash']); return $m; }
function simrs_token(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24)); return $_SESSION['csrf']; }
function simrs_check_post(): void { if ($_SERVER['REQUEST_METHOD'] === 'POST') { if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) { http_response_code(400); exit('Sesi formulir kedaluwarsa. Muat ulang halaman dan coba lagi.'); } } }
function simrs_redirect(string $url): never { header('Location: '.$url); exit; }
function simrs_layout_start(string $title, string $active='Dashboard'): void {
 $name = $_SESSION['name'] ?? $_SESSION['username'] ?? 'User';
 $role = $_SESSION['role'] ?? $_SESSION['role_code'] ?? '';
 $code = $_SESSION['role_code'] ?? '';

 echo '<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.simrs_h($title).' - SIMRS RS Permata Medika</title><link rel="stylesheet" href="style.css"><style>
.formbox{background:#fff;border:1px solid #d5e0e4;border-radius:9px;padding:22px;margin-bottom:22px}
.formrow{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
.formrow .wide{grid-column:1/-1}
.formrow label{display:block;font-weight:700;color:#71818b;margin-bottom:7px}
.formrow input,.formrow select,.formrow textarea{width:100%;padding:11px 12px;border:1px solid #d2dfe3;border-radius:7px;font:inherit;color:#20465a;background:#fff}
.formrow textarea{min-height:80px}
.notice{padding:12px 15px;border-radius:7px;background:#e8f4f5;color:#245b73;margin-bottom:18px}
.errorbox{background:#fdecec;color:#a22;padding:12px 15px;border-radius:7px;margin-bottom:18px}
.tablewrap{overflow:auto}
.form-actions{margin-top:16px;display:flex;gap:8px;flex-wrap:wrap}
.btn.danger{background:#c94a4a}
.muted{color:#71818b}
.main h2{font-size:19px;margin:0 0 14px}
.mini{font-size:12px}
.inlineform{display:flex;gap:8px;align-items:center}
.inlineform select,.inlineform input{padding:7px;border:1px solid #d2dfe3;border-radius:5px}

/* Notifikasi Dropdown Styling */
.notif-bell{position:relative;cursor:pointer;background:#f1f5f9;width:38px;height:38px;border-radius:50%;display:flex;align-items:center;justify-content:center;transition:background .2s}
.notif-bell:hover{background:#e2e8f0}
.notif-badge{position:absolute;top:-2px;right:-2px;background:#e11d48;color:#fff;font-size:10px;font-weight:bold;border-radius:10px;padding:1px 5px;border:2px solid #fff}
.notif-dropdown{display:none;position:absolute;top:50px;right:0;width:310px;background:#fff;border:1px solid #d5e0e4;border-radius:10px;box-shadow:0 10px 25px rgba(0,0,0,.15);z-index:9999;overflow:hidden}
.notif-header{padding:12px 16px;background:#f8fafc;border-bottom:1px solid #e2e8f0;font-weight:bold;font-size:13px;color:#183f52;display:flex;justify-content:space-between}
.notif-item{padding:10px 16px;border-bottom:1px solid #f1f5f9;font-size:12px;display:flex;gap:10px;align-items:flex-start}
.notif-item:hover{background:#f8fafc}
.notif-item small{display:block;color:#94a3b8;margin-top:2px}

@media(max-width:760px){.layout{display:block}.sidebar{width:100%}.main{padding:18px}.formrow,.cards,.patient-grid{grid-template-columns:1fr}.topbar{padding:12px;gap:12px;flex-wrap:wrap;height:auto}}
</style>
<script>
function toggleNotif(){
    var d = document.getElementById("notifDropdown");
    d.style.display = (d.style.display === "block") ? "none" : "block";
}
document.addEventListener("click", function(e){
    var bell = document.getElementById("notifContainer");
    var drop = document.getElementById("notifDropdown");
    if(bell && !bell.contains(e.target) && drop){ drop.style.display = "none"; }
});
</script>
</head><body><header class="topbar"><div class="brand">SIMRS <span>RS PERMATA MEDIKA</span></div><div class="user">

<div id="notifContainer" style="position:relative;">
    <div class="notif-bell" onclick="toggleNotif()" title="Notifikasi Sistem">
        <span>🔔</span>
        <span class="notif-badge">3</span>
    </div>
    <div id="notifDropdown" class="notif-dropdown">
        <div class="notif-header">
            <span>Pemberitahuan Sistem</span>
            <small style="color:#18879b; font-weight:normal; cursor:pointer;" onclick="toggleNotif()">Tutup</small>
        </div>
        <div class="notif-item">
            <span style="font-size:16px;">🚨</span>
            <div><strong>Triase IGD:</strong> Pasien kategori MERAH masuk memerlukan penanganan resusitasi.<small>12 menit yang lalu</small></div>
        </div>
        <div class="notif-item">
            <span style="font-size:16px;">☢️</span>
            <div><strong>Radiologi:</strong> 1 order foto rontgen baru menunggu verifikasi radiografer.<small>25 menit yang lalu</small></div>
        </div>
        <div class="notif-item">
            <span style="font-size:16px;">📅</span>
            <div><strong>Antrean Poli:</strong> 2 pasien telah check-in dan siap dilayani.<small>40 menit yang lalu</small></div>
        </div>
    </div>
</div>

<div>'.simrs_h($name).'<small>'.simrs_h($role).'</small></div>
<a class="logout" href="logout.php">Log-out</a></div></header><div class="layout"><aside class="sidebar"><div class="side-title">SIMRS<br>'.simrs_h($title).'</div>';

 echo '<a class="nav '.($active==='Dashboard'?'active':'').'" href="dashboard.php">🏠 Beranda RS</a>';
 if(in_array($code,['triage_nurse','nurse','ed_staff'],true)){ echo '<a class="nav '.($active==='ED'?'active':'').'" href="ed.php?view=dashboard">🚨 Triase IGD</a>'; } elseif($code==='doctor'){ echo '<a class="nav '.($active==='ED'?'active':'').'" href="ed.php?view=dashboard">🚨 Emergency (IGD)</a>'; }
 if(in_array($code,['doctor','radiographer','radiologist'],true)) echo '<a class="nav '.($active==='Radiology'?'active':'').'" href="radiology.php?view=dashboard">☢️ Radiologi</a>';
 if($code==='registration_staff') echo '<a class="nav '.($active==='Scheduling'?'active':'').'" href="scheduling.php?view=dashboard">📅 Scheduling & Antrean</a>';
 if(in_array($code,['simrs_admin','it_manager','registration_staff','unit_manager','management','auditor'],true)) echo '<a class="nav '.($active==='Reports'?'active':'').'" href="reports.php">📊 Laporan</a>';
 if($code==='simrs_admin') echo '<a class="nav '.($active==='Users'?'active':'').'" href="users.php">👥 User Management</a>';
 echo '</aside><main class="main">';
}
function simrs_layout_end(): void { echo '</main></div></body></html>'; }
function simrs_db_error(Throwable $e): string { error_log('SIMRS: '.$e->getMessage()); return 'Data belum dapat diproses. Periksa tabel/unit yang tersedia di database dan coba lagi.'; }
