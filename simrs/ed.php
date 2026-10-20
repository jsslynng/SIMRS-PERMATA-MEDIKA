<?php
$patients=[
'dimas'=>['Dimas Pratama','RM002341','08:15','Red'],
'maya'=>['Maya Salsabila','RM002342','08:32','Yellow'],
'kevin'=>['Kevin Wijaya','RM002343','09:05','Green'],
'nadia'=>['Nadia Permata','RM002344','09:18','Yellow']
];
$patient=$patients[$_GET['patient']??'dimas']??$patients['dimas'];
$steps=['triage'=>'Triage','ttv'=>'TTV','anamnesis'=>'Anamnesis','pf'=>'Physical Exam','diagnosis'=>'Diagnosis','disposition'=>'Disposition'];
$step=$_GET['step']??'triage'; if(!isset($steps[$step]))$step='triage';
$data=[
'triage'=>['Triage Assessment',[['Keluhan Utama','Sesak napas sejak 2 jam terakhir'],['Kategori Triage',$patient[3].' — Emergency'],['Arrival Time',$patient[2]],['Status','Triage completed'],['Catatan Triage','Pasien membutuhkan assessment dan penanganan segera.']]],
'ttv'=>['TTV',[['Tekanan Darah','150 / 95 mmHg'],['Heart Rate','108 bpm'],['Respiratory Rate','24 x/min'],['Suhu','37.8 °C'],['SpO₂','94%'],['Kesadaran','Compos mentis'],['Clinical Note','TTV dicatat sebagai bagian dari initial assessment pasien di IGD.']]],
'anamnesis'=>['Anamnesis',[['Keluhan Utama','Sesak napas sejak 2 jam terakhir'],['Riwayat Penyakit Sekarang','Sesak muncul mendadak dan memberat saat aktivitas.'],['Riwayat Penyakit Dahulu','Tidak ada riwayat penyakit kronis yang dilaporkan.'],['Alergi','Tidak diketahui'],['Clinical Note','Anamnesis dicatat untuk mendukung assessment klinis.']]],
'pf'=>['Physical Exam',[['General Appearance','Tampak sesak, compos mentis'],['Thorax','Tachypnea, suara napas dinilai'],['Cardiovascular','Takikardi'],['Extremity','Akral hangat, tidak ada edema'],['Clinical Note','Hasil pemeriksaan fisik dicatat pada encounter pasien.']]],
'diagnosis'=>['Diagnosis',[['Diagnosis Utama','Acute dyspnea — assessment lebih lanjut'],['Diagnosis Sekunder','Tachycardia'],['Tipe','Assessment awal IGD'],['Status','Draft / pending confirmation'],['Clinical Note','Diagnosis didokumentasikan berdasarkan assessment yang telah dilakukan.']]],
'disposition'=>['Disposition',[['Keputusan','Rawat Inap'],['Status','Pending confirmation'],['Keterangan','Pasien diarahkan untuk tindak lanjut sesuai kondisi klinis dan hasil assessment.']]]
];
?>
<!doctype html><html><head><meta charset="utf-8"><title>SIMRS - Emergency Patient Assessment</title><link rel="stylesheet" href="style.css"></head><body>
<header class="topbar"><div class="brand">SIMRS <span>RS PERMATA MEDIKA</span></div><div class="user"><div>Dr. Andi Sentosa<small>Emergency Doctor</small></div><a class="logout" href="logout.php">Log-out</a></div></header>
<div class="layout"><aside class="sidebar"><div class="side-title">Emergency<br>Department</div><a class="nav active" href="dashboard.php">Worklist</a><a class="nav" href="reports.php">Reports</a></aside>
<main class="main"><h1>Emergency Patient Assessment</h1><div class="subtitle">Encounter <?=$patient[1]?> — <?=$patient[0]?></div>
<div class="patient-head"><div class="section-title">Patient Information</div><div class="patient-grid"><div class="meta"><label>Nama Pasien</label><strong><?=$patient[0]?></strong></div><div class="meta"><label>No. RM</label><strong><?=$patient[1]?></strong></div><div class="meta"><label>Arrival Time</label><strong><?=$patient[2]?></strong></div><div class="meta"><label>Triage</label><span class="badge <?=strtolower($patient[3])?>"><?=$patient[3]?></span></div></div></div>
<div class="tabs"><?php foreach($steps as $key=>$label): ?><a class="tab <?=$key==$step?'active':''?>" href="ed.php?patient=<?=array_search($patient,$patients)?>&step=<?=$key?>"><?=$label?></a><?php endforeach;?></div>
<div class="content"><div class="section-title"><?=$data[$step][0]?></div><div class="form-grid">
<?php foreach($data[$step][1] as $i=>$f): ?><div class="field <?=$i===count($data[$step][1])-1?'full':''?>"><label><?=$f[0]?></label><div class="<?=$i===count($data[$step][1])-1?'textarea':'input'?>"><?=$f[1]?></div></div><?php endforeach;?>
</div><div class="actions"><a class="btn secondary" href="dashboard.php">← Back to Worklist</a>
<?php $keys=array_keys($steps);$idx=array_search($step,$keys); if($idx<count($keys)-1): $next=$keys[$idx+1];?><a class="btn" href="ed.php?patient=<?=array_search($patient,$patients)?>&step=<?=$next?>">Next: <?=$steps[$next]?> →</a><?php endif;?></div></div>
</main></div></body></html>