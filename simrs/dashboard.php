<?php
session_start();
if (isset($_SESSION['username'])) {
    if ($_SESSION['username'] === 'radiology') { header('Location: radiology_dashboard.php'); exit; }
    if ($_SESSION['username'] === 'registration') { header('Location: registration_dashboard.php'); exit; }
}
?>
<?php
$patients = [
 ['Dimas Pratama','RM002341','08:15','Red','ed.php?patient=dimas&step=triage'],
 ['Maya Salsabila','RM002342','08:32','Yellow','ed.php?patient=maya&step=triage'],
 ['Kevin Wijaya','RM002343','09:05','Green','ed.php?patient=kevin&step=triage'],
 ['Nadia Permata','RM002344','09:18','Yellow','ed.php?patient=nadia&step=triage']
];
?>
<!doctype html><html><head><meta charset="utf-8"><title>SIMRS - Dashboard</title><link rel="stylesheet" href="style.css"></head>
<body>
<header class="topbar"><div class="brand">SIMRS <span>RS PERMATA MEDIKA</span></div><div class="user"><div>Dr. Andi Sentosa<small>Emergency Doctor</small></div><a class="logout" href="logout.php">Log-out</a></div></header>
<div class="layout"><aside class="sidebar"><div class="side-title">Emergency<br>Department</div>
<a class="nav active" href="dashboard.php">Worklist</a><a class="nav" href="reports.php">Reports</a></aside>
<main class="main"><h1>Dashboard</h1><div class="subtitle">Kelola pasien dan encounter pada layanan gawat darurat.</div>
<div class="cards"><div class="card"><div class="label">Pasien Menunggu</div><div class="num">8</div><div class="hint">Pada worklist</div></div><div class="card"><div class="label">Triage Merah</div><div class="num">2</div><div class="hint">Perlu perhatian segera</div></div><div class="card"><div class="label">Encounter Hari Ini</div><div class="num">24</div><div class="hint">Total encounter</div></div></div>
<div class="panel"><div class="panel-title">Emergency Worklist</div><table><thead><tr><th>Nama Pasien</th><th>No. RM</th><th>Arrival Time</th><th>Triage</th><th>Action</th></tr></thead><tbody>
<?php foreach($patients as $p): ?><tr><td><?=htmlspecialchars($p[0])?></td><td><?=$p[1]?></td><td><?=$p[2]?></td><td><span class="badge <?=strtolower($p[3])?>"><?=$p[3]?></span></td><td><a class="btn" href="<?=$p[4]?>">Open</a></td></tr><?php endforeach; ?>
</tbody></table></div>
</main></div></body></html>