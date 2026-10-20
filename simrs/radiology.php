<?php
$step=$_GET['step']??'order';$steps=['order'=>'Order','exam'=>'Examination','result'=>'Result','interpretation'=>'Interpretation'];
if(!isset($steps[$step]))$step='order';
$data=[
'order'=>['Radiology Order',[['Nama Pasien','Dimas Pratama'],['No. RM','RM002341'],['Jenis Pemeriksaan','Chest X-Ray'],['Modalitas','X-Ray'],['Body Part','Thorax'],['Prioritas','Urgent'],['Status','Ordered']]],
'exam'=>['Radiology Examination',[['Patient','Dimas Pratama — RM002341'],['Examination','Chest X-Ray AP'],['Tanggal Pemeriksaan','03/10/2026'],['Radiographer','Petugas Radiologi'],['Status','Examination completed']]],
'result'=>['Radiology Result',[['Tanggal Pemeriksaan','03/10/2026'],['Hasil','Hasil pemeriksaan tersedia untuk direview dokter.'],['Status','Result entered'],['Provider','Radiology Department']]],
'interpretation'=>['Interpretation',[['Interpretasi','Gambaran radiologi telah diinterpretasikan dan didokumentasikan.'],['Kesimpulan','Final report pending verification'],['Status','Pending verification'],['Clinical Note','Hasil terhubung dengan radiology order pasien.']]]
];$keys=array_keys($steps);
?>
<!doctype html><html><head><meta charset="utf-8"><title>SIMRS - Radiology</title><link rel="stylesheet" href="style.css"></head><body>
<header class="topbar"><div class="brand">SIMRS <span>RS PERMATA MEDIKA</span></div><div class="user"><div>Dr. Andi Sentosa<small>Radiology Access</small></div><a class="logout" href="logout.php">Log-out</a></div></header>
<div class="layout"><aside class="sidebar"><div class="side-title">Radiology<br>Department</div><a class="nav" href="radiology_dashboard.php">Dashboard</a><a class="nav active" href="radiology.php">Worklist</a><a class="nav" href="reports.php">Reports</a></aside>
<main class="main"><h1>Radiology Order</h1><div class="subtitle">Radiology workflow — RM002341 Dimas Pratama</div>
<div class="patient-head"><div class="section-title">Patient Information</div><div class="patient-grid"><div class="meta"><label>Nama Pasien</label><strong>Dimas Pratama</strong></div><div class="meta"><label>No. RM</label><strong>RM002341</strong></div><div class="meta"><label>Order</label><strong>Chest X-Ray</strong></div><div class="meta"><label>Priority</label><span class="badge red">Urgent</span></div></div></div>
<div class="tabs"><?php foreach($steps as $k=>$v):?><a class="tab <?=$k==$step?'active':''?>" href="radiology.php?step=<?=$k?>"><?=$v?></a><?php endforeach;?></div>
<div class="content"><div class="section-title"><?=$data[$step][0]?></div><div class="form-grid"><?php foreach($data[$step][1] as $i=>$f):?><div class="field <?=$i===count($data[$step][1])-1?'full':''?>"><label><?=$f[0]?></label><div class="<?=$i===count($data[$step][1])-1?'textarea':'input'?>"><?=$f[1]?></div></div><?php endforeach;?></div><div class="actions"><a class="btn secondary" href="radiology_dashboard.php">← Dashboard</a><?php $idx=array_search($step,$keys);if($idx<count($keys)-1):$n=$keys[$idx+1];?><a class="btn" href="radiology.php?step=<?=$n?>">Next: <?=$steps[$n]?> →</a><?php endif;?></div></div>
</main></div></body></html>