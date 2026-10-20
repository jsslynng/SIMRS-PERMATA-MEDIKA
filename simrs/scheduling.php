<?php
$step=$_GET['step']??'schedule';$steps=['schedule'=>'Schedule','patient'=>'Patient','appointment'=>'Appointment','status'=>'Status'];
if(!isset($steps[$step]))$step='schedule';
$data=[
'schedule'=>['Schedule',[['Provider','Dr. Andi Sentosa'],['Unit','Emergency Department'],['Hari','Senin, 05/10/2026'],['Jam Mulai','08:00'],['Jam Selesai','12:00'],['Kuota','10 pasien']]],
'patient'=>['Patient Selection',[['Nama Pasien','Dimas Pratama'],['No. RM','RM002341'],['Jenis Layanan','Consultation'],['Provider','Dr. Andi Sentosa'],['Status','Patient selected']]],
'appointment'=>['Appointment Detail',[['Tanggal','05/10/2026'],['Jam','08:30'],['Provider','Dr. Andi Sentosa'],['Unit','Emergency Department'],['Catatan','Appointment dibuat melalui modul scheduling.']]],
'status'=>['Appointment Status',[['Status','Confirmed'],['Appointment ID','APT000124'],['Patient','Dimas Pratama — RM002341'],['Provider','Dr. Andi Sentosa'],['Keterangan','Jadwal berhasil dikonfirmasi.']]]
];$keys=array_keys($steps);
?>
<!doctype html><html><head><meta charset="utf-8"><title>SIMRS - Scheduling</title><link rel="stylesheet" href="style.css"></head><body>
<header class="topbar"><div class="brand">SIMRS <span>RS PERMATA MEDIKA</span></div><div class="user"><div>Admin Scheduling<small>Scheduling Staff</small></div><a class="logout" href="logout.php">Log-out</a></div></header>
<div class="layout"><aside class="sidebar"><div class="side-title">Scheduling<br>Department</div><a class="nav" href="registration_dashboard.php">Dashboard</a><a class="nav active" href="scheduling.php">Schedule</a><a class="nav" href="reports.php">Reports</a></aside>
<main class="main"><h1>Scheduling</h1><div class="subtitle">Kelola provider, slot, appointment, dan status jadwal.</div>
<div class="patient-head"><div class="section-title">Appointment Workflow</div><div class="patient-grid"><div class="meta"><label>Patient</label><strong>Dimas Pratama</strong></div><div class="meta"><label>No. RM</label><strong>RM002341</strong></div><div class="meta"><label>Provider</label><strong>Dr. Andi Sentosa</strong></div><div class="meta"><label>Status</label><span class="badge green">Confirmed</span></div></div></div>
<div class="tabs"><?php foreach($steps as $k=>$v):?><a class="tab <?=$k==$step?'active':''?>" href="scheduling.php?step=<?=$k?>"><?=$v?></a><?php endforeach;?></div>
<div class="content"><div class="section-title"><?=$data[$step][0]?></div><div class="form-grid"><?php foreach($data[$step][1] as $i=>$f):?><div class="field <?=$i===count($data[$step][1])-1?'full':''?>"><label><?=$f[0]?></label><div class="<?=$i===count($data[$step][1])-1?'textarea':'input'?>"><?=$f[1]?></div></div><?php endforeach;?></div><div class="actions"><a class="btn secondary" href="registration_dashboard.php">← Dashboard</a><?php $idx=array_search($step,$keys);if($idx<count($keys)-1):$n=$keys[$idx+1];?><a class="btn" href="scheduling.php?step=<?=$n?>">Next: <?=$steps[$n]?> →</a><?php endif;?></div></div>
</main></div></body></html>