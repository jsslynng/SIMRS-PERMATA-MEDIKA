<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['username'] !== 'radiology') { header('Location: login.php'); exit; }
?>
<!doctype html><html><head><meta charset="utf-8"><title>SIMRS - Radiology Dashboard</title><link rel="stylesheet" href="style.css"></head><body>
<header class="topbar"><div class="brand">SIMRS <span>RS PERMATA MEDIKA</span></div><div class="user"><div><?=htmlspecialchars($_SESSION['name'])?><small><?=htmlspecialchars($_SESSION['role'])?></small></div><a class="logout" href="logout.php">Log-out</a></div></header>
<div class="layout"><aside class="sidebar"><div class="side-title">Radiology<br>Department</div>
<a class="nav active" href="radiology_dashboard.php">Worklist</a><a class="nav" href="reports.php">Reports</a></aside>
<main class="main"><h1>Dashboard</h1><div class="subtitle">Kelola order dan hasil pemeriksaan radiologi pasien.</div>
<div class="cards"><div class="card"><div class="label">Pending Orders</div><div class="num">6</div><div class="hint">Perlu diproses</div></div><div class="card"><div class="label">Urgent Orders</div><div class="num">2</div><div class="hint">Prioritas segera</div></div><div class="card"><div class="label">Completed Today</div><div class="num">12</div><div class="hint">Pemeriksaan selesai</div></div></div>
<div class="panel"><div class="panel-title">Radiology Worklist</div><table><thead><tr><th>Nama Pasien</th><th>No. RM</th><th>Examination</th><th>Priority</th><th>Status</th><th>Action</th></tr></thead><tbody>
<tr><td>Dimas Pratama</td><td>RM002341</td><td>Chest X-Ray</td><td><span class="badge red">Urgent</span></td><td>Ordered</td><td><a class="btn" href="radiology.php?step=order">Open</a></td></tr>
<tr><td>Maya Salsabila</td><td>RM002342</td><td>Chest X-Ray</td><td><span class="badge yellow">Routine</span></td><td>Examination</td><td><a class="btn" href="radiology.php?step=exam">Open</a></td></tr>
<tr><td>Kevin Wijaya</td><td>RM002343</td><td>Extremity X-Ray</td><td><span class="badge green">Routine</span></td><td>Result entered</td><td><a class="btn" href="radiology.php?step=result">Open</a></td></tr>
<tr><td>Nadia Permata</td><td>RM002344</td><td>Ultrasound</td><td><span class="badge yellow">Routine</span></td><td>Pending verification</td><td><a class="btn" href="radiology.php?step=interpretation">Open</a></td></tr>
</tbody></table></div>
</main></div></body></html>
