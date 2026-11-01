<?php
require_once __DIR__.'/config/module_helpers.php';simrs_require_login();$role=$_SESSION['role_code']??'';
if($role!=='simrs_admin'){simrs_redirect(simrs_home_for_role($role));}
$msg='';try{$patients=(int)$pdo->query('SELECT COUNT(*) FROM patients')->fetchColumn();$appointments=(int)$pdo->query('SELECT COUNT(*) FROM appointments WHERE appointment_date=CURDATE()')->fetchColumn();$encounters=(int)$pdo->query("SELECT COUNT(*) FROM encounters WHERE DATE(arrival_at)=CURDATE() AND encounter_type='ED'")->fetchColumn();$radiology=(int)$pdo->query("SELECT COUNT(*) FROM radiology_orders WHERE status<>'cancelled'")->fetchColumn();}catch(Throwable $e){$msg=simrs_db_error($e);$patients=$appointments=$encounters=$radiology=0;}
simrs_layout_start('SIMRS Administration','Dashboard');if($msg)echo '<div class="errorbox">'.simrs_h($msg).'</div>';
?>
<h1>SIMRS Dashboard</h1><div class="subtitle">Ringkasan operasional dari database dan pintasan ke tiga modul utama.</div>
<div class="cards"><div class="card"><div class="label">Registered Patients</div><div class="num"><?=$patients?></div><div class="hint">Data dari tabel patients</div></div><div class="card"><div class="label">ED Encounters Today</div><div class="num"><?=$encounters?></div><div class="hint">Data dari tabel encounters</div></div><div class="card"><div class="label">Radiology Orders</div><div class="num"><?=$radiology?></div><div class="hint">Data dari tabel radiology_orders</div></div></div>
<div class="cards"><div class="card"><div class="label">Appointments Today</div><div class="num"><?=$appointments?></div><div class="hint">Data dari tabel appointments</div></div></div>
<div class="actions"><a class="btn" href="users.php">Open User Management</a><a class="btn secondary" href="reports.php">Open Reports</a></div>
<?php simrs_layout_end(); ?>
