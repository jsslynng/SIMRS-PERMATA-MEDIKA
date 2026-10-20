<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['username'] !== 'registration') { header('Location: login.php'); exit; }
?>
<!doctype html><html><head><meta charset="utf-8"><title>SIMRS - Registration Dashboard</title><link rel="stylesheet" href="style.css"></head><body>
<header class="topbar"><div class="brand">SIMRS <span>RS PERMATA MEDIKA</span></div><div class="user"><div><?=htmlspecialchars($_SESSION['name'])?><small><?=htmlspecialchars($_SESSION['role'])?></small></div><a class="logout" href="logout.php">Log-out</a></div></header>
<div class="layout"><aside class="sidebar"><div class="side-title">Registration<br>Department</div>
<a class="nav active" href="registration_dashboard.php">Worklist</a><a class="nav" href="reports.php">Reports</a></aside>
<main class="main"><h1>Dashboard</h1><div class="subtitle">Kelola registrasi pasien, provider, dan appointment.</div>
<div class="cards"><div class="card"><div class="label">Patient Queue</div><div class="num">8</div><div class="hint">Menunggu registrasi</div></div><div class="card"><div class="label">Appointments Today</div><div class="num">18</div><div class="hint">Jadwal hari ini</div></div><div class="card"><div class="label">Confirmed</div><div class="num">14</div><div class="hint">Appointment confirmed</div></div></div>
<div class="panel"><div class="panel-title">Registration Worklist</div><table><thead><tr><th>Nama Pasien</th><th>No. RM</th><th>Provider</th><th>Time</th><th>Status</th><th>Action</th></tr></thead><tbody>
<tr><td>Dimas Pratama</td><td>RM002341</td><td>Dr. Andi Sentosa</td><td>08:30</td><td><span class="badge green">Confirmed</span></td><td><a class="btn" href="scheduling.php?step=patient">Open</a></td></tr>
<tr><td>Maya Salsabila</td><td>RM002342</td><td>Dr. Andi Sentosa</td><td>09:00</td><td><span class="badge yellow">Pending</span></td><td><a class="btn" href="scheduling.php?step=appointment">Open</a></td></tr>
<tr><td>Kevin Wijaya</td><td>RM002343</td><td>Dr. Budi Wijaya</td><td>09:30</td><td><span class="badge green">Confirmed</span></td><td><a class="btn" href="scheduling.php?step=status">Open</a></td></tr>
<tr><td>Nadia Permata</td><td>RM002344</td><td>Dr. Andi Sentosa</td><td>10:00</td><td><span class="badge yellow">Pending</span></td><td><a class="btn" href="scheduling.php?step=schedule">Open</a></td></tr>
</tbody></table></div>
</main></div></body></html>
