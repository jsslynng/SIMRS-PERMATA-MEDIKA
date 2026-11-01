<?php
require_once __DIR__ . '/config/module_helpers.php';
simrs_require_role(['registration_staff']);
simrs_check_post();

$msg = simrs_take_flash();
$err = '';
$view = $_GET['view'] ?? 'dashboard';

if (!in_array($view, ['dashboard', 'patient_new', 'appointment_new'], true)) {
    http_response_code(400);
    exit('Halaman scheduling tidak valid.');
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        // 1. Registrasi Pasien Baru
        if ($action === 'patient') {
            $mr = trim($_POST['medical_record_no'] ?? '');
            $name = trim($_POST['full_name'] ?? '');
            $dob = $_POST['birth_date'] ?: null;
            $sex = $_POST['sex'] ?? 'U';
            $phone = trim($_POST['phone'] ?? '');
            $address = trim($_POST['address'] ?? '');

            if ($mr === '' || $name === '') {
                throw new RuntimeException('No. rekam medis dan nama pasien wajib diisi.');
            }

            // Cek duplikasi nomor rekam medis
            $check = $pdo->prepare('SELECT id FROM patients WHERE medical_record_no = ? LIMIT 1');
            $check->execute([$mr]);
            if ($check->fetchColumn()) {
                throw new RuntimeException("No. Rekam Medis '$mr' sudah terdaftar. Gunakan nomor lain.");
            }

            $q = $pdo->prepare('INSERT INTO patients (medical_record_no, full_name, birth_date, sex, phone, address, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $q->execute([$mr, $name, $dob, $sex, $phone, $address, $_SESSION['user_id']]);
            $newPatientId = (int)$pdo->lastInsertId();

            simrs_flash("Pasien $name ($mr) berhasil didaftarkan! <a href='scheduling.php?view=appointment_new&patient_id=$newPatientId' style='text-decoration:underline;font-weight:bold;margin-left:8px;'>Langsung Buat Appointment Pasien Ini &rarr;</a>");
            simrs_redirect('scheduling.php?view=dashboard');
        }

        // 2. Buat Appointment Baru
        if ($action === 'appointment') {
            $pid = (int)($_POST['patient_id'] ?? 0);
            $unit = (int)($_POST['unit_id'] ?? 0);
            $date = $_POST['appointment_date'] ?? '';
            $time = $_POST['start_time'] ?? '';
            $complaint = trim($_POST['complaint'] ?? '');

            if (!$pid || !$unit || !$date || !$time) {
                throw new RuntimeException('Pasien, unit, tanggal, dan jam wajib diisi.');
            }

            $no = 'APT' . date('ymdHis') . random_int(10, 99);
            $q = $pdo->prepare("INSERT INTO appointments (appointment_no, patient_id, unit_id, appointment_date, start_time, complaint, status, created_by) VALUES (?, ?, ?, ?, ?, ?, 'Booked', ?)");
            $q->execute([$no, $pid, $unit, $date, $time, $complaint, $_SESSION['user_id']]);

            simrs_flash("Appointment berhasil dibuat dengan No: $no.");
            simrs_redirect('scheduling.php?view=dashboard');
        }

        // 3. Perbarui Status Appointment
        if ($action === 'status') {
            $id = (int)($_POST['appointment_id'] ?? 0);
            $status = $_POST['status'] ?? 'Booked';
            $valid = ['Booked', 'Confirmed', 'Checked-in', 'Completed', 'Cancelled', 'Rescheduled', 'No-show'];

            if (!in_array($status, $valid, true)) {
                throw new RuntimeException('Status tidak valid.');
            }

            $q = $pdo->prepare('UPDATE appointments SET status = ? WHERE id = ?');
            $q->execute([$status, $id]);

            simrs_flash("Status appointment berhasil diperbarui menjadi '$status'.");
            simrs_redirect('scheduling.php?view=dashboard');
        }
    }

    // Query Data
    $patients = $pdo->query('SELECT id, medical_record_no, full_name, phone FROM patients ORDER BY full_name')->fetchAll();
    $units = $pdo->query('SELECT id, unit_name FROM units WHERE is_active = 1 ORDER BY unit_name')->fetchAll();
    
    $appointments = $pdo->query("
        SELECT a.id, a.appointment_no, a.appointment_date, a.start_time, a.status, a.complaint,
               p.id as patient_id, p.full_name, p.medical_record_no, p.phone,
               u.unit_name
        FROM appointments a
        JOIN patients p ON p.id = a.patient_id
        JOIN units u ON u.id = a.unit_id
        ORDER BY a.appointment_date DESC, a.start_time DESC
        LIMIT 150
    ")->fetchAll();

    $patientCount = (int)$pdo->query('SELECT COUNT(*) FROM patients')->fetchColumn();
    $appointmentCount = (int)$pdo->query('SELECT COUNT(*) FROM appointments WHERE appointment_date = CURDATE()')->fetchColumn();
    $confirmedCount = (int)$pdo->query("SELECT COUNT(*) FROM appointments WHERE status IN ('Confirmed', 'Checked-in')")->fetchColumn();

} catch (Throwable $e) {
    $err = $e instanceof RuntimeException ? $e->getMessage() : simrs_db_error($e);
    $patients = $patients ?? [];
    $units = $units ?? [];
    $appointments = $appointments ?? [];
    $patientCount = $patientCount ?? 0;
    $appointmentCount = $appointmentCount ?? 0;
    $confirmedCount = $confirmedCount ?? 0;
}

simrs_layout_start('Scheduling & Registrasi', 'Scheduling');
if ($msg) echo '<div class="notice">' . $msg . '</div>';
if ($err) echo '<div class="errorbox">' . simrs_h($err) . '</div>';
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; flex-wrap:wrap; gap:12px;">
    <div>
        <h1 style="margin:0 0 4px;"><?= $view === 'patient_new' ? 'Registrasi Pasien Baru' : ($view === 'appointment_new' ? 'Buat Appointment Pasien' : 'Appointment & Antrean Pasien') ?></h1>
        <div class="subtitle" style="margin-bottom:0;">Pendaftaran pasien dan pengelolaan jadwal terintegrasi ke database SIMRS.</div>
    </div>
    <div class="form-actions" style="margin:0;">
        <a class="btn <?= $view === 'dashboard' ? '' : 'secondary' ?>" href="scheduling.php?view=dashboard">&larr; Appointment List</a>
        <a class="btn <?= $view === 'patient_new' ? '' : 'secondary' ?>" href="scheduling.php?view=patient_new">+ Pasien Baru</a>
        <a class="btn <?= $view === 'appointment_new' ? '' : 'secondary' ?>" href="scheduling.php?view=appointment_new">+ Buat Appointment</a>
    </div>
</div>

<?php if ($view === 'dashboard'): ?>
    <!-- Kartu Statistik -->
    <div class="cards">
        <div class="card">
            <div class="label">Total Pasien Terdaftar</div>
            <div class="num"><?= number_format($patientCount) ?></div>
            <div class="hint">Database master pasien</div>
        </div>
        <div class="card">
            <div class="label">Appointment Hari Ini</div>
            <div class="num"><?= number_format($appointmentCount) ?></div>
            <div class="hint">Tanggal: <?= date('d M Y') ?></div>
        </div>
        <div class="card">
            <div class="label">Confirmed / Checked-in</div>
            <div class="num"><?= number_format($confirmedCount) ?></div>
            <div class="hint">Siap dilayani di Poliklinik</div>
        </div>
    </div>

    <!-- Filter & Tabel Worklist -->
    <div class="panel">
        <div class="panel-title" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
            <span>Daftar Appointment & Antrean</span>
            <div style="display:flex; gap:8px;">
                <input type="text" id="tableSearch" placeholder="Cari nama, No. RM, atau No. Appt..." style="padding:6px 12px; border:1px solid #ccd7dc; border-radius:6px; font-size:13px; width:260px;">
                <select id="statusFilter" style="padding:6px 10px; border:1px solid #ccd7dc; border-radius:6px; font-size:13px;">
                    <option value="">Semua Status</option>
                    <option value="Booked">Booked</option>
                    <option value="Confirmed">Confirmed</option>
                    <option value="Checked-in">Checked-in</option>
                    <option value="Completed">Completed</option>
                    <option value="Cancelled">Cancelled</option>
                </select>
            </div>
        </div>

        <div class="tablewrap">
            <table id="appointmentTable">
                <thead>
                    <tr>
                        <th>No. Appointment</th>
                        <th>Nama Pasien</th>
                        <th>No. RM</th>
                        <th>Jadwal Pelayanan</th>
                        <th>Unit Tujuan</th>
                        <th>Keluhan</th>
                        <th>Status</th>
                        <th>Ubah Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($appointments as $idx => $a): 
                    $badgeClass = 'secondary';
                    if ($a['status'] === 'Checked-in') $badgeClass = 'green';
                    elseif ($a['status'] === 'Confirmed') $badgeClass = 'yellow';
                    elseif ($a['status'] === 'Cancelled') $badgeClass = 'red';
                    elseif ($a['status'] === 'Completed') $badgeClass = 'btn';
                    $queueNum = 'A-' . str_pad((string)($idx + 1), 3, '0', STR_PAD_LEFT);
                ?>
                    <tr class="appt-row" data-status="<?= simrs_h($a['status']) ?>">
                        <td><strong><?= simrs_h($a['appointment_no']) ?></strong></td>
                        <td><?= simrs_h($a['full_name']) ?></td>
                        <td><code><?= simrs_h($a['medical_record_no']) ?></code></td>
                        <td><?= simrs_h(date('d/m/Y', strtotime($a['appointment_date']))) ?> &bull; <?= simrs_h(substr($a['start_time'], 0, 5)) ?></td>
                        <td><?= simrs_h($a['unit_name']) ?></td>
                        <td><small class="muted"><?= simrs_h($a['complaint'] ?: '-') ?></small></td>
                        <td><span class="badge <?= $badgeClass ?>"><?= simrs_h($a['status']) ?></span></td>
                        <td>
                            <form class="inlineform" method="post" style="margin:0;">
                                <input type="hidden" name="csrf" value="<?= simrs_h(simrs_token()) ?>">
                                <input type="hidden" name="action" value="status">
                                <input type="hidden" name="appointment_id" value="<?= $a['id'] ?>">
                                <select name="status" style="font-size:12px; padding:4px 6px;">
                                    <?php foreach (['Booked', 'Confirmed', 'Checked-in', 'Completed', 'Cancelled', 'Rescheduled', 'No-show'] as $s): ?>
                                        <option value="<?= $s ?>" <?= $a['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="btn" type="submit" style="padding:4px 8px; font-size:12px;">Save</button>
                            </form>
                        </td>
                        <td>
                            <button type="button" class="btn secondary" style="padding:4px 10px; font-size:12px;"
                                onclick="openQueueModal('<?= simrs_h($queueNum) ?>', '<?= simrs_h($a['full_name']) ?>', '<?= simrs_h($a['medical_record_no']) ?>', '<?= simrs_h($a['unit_name']) ?>', '<?= simrs_h($a['appointment_no']) ?>', '<?= simrs_h(date('d M Y', strtotime($a['appointment_date']))) ?> <?= simrs_h(substr($a['start_time'], 0, 5)) ?>')">
                                🖨️ Cetak Tiket
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$appointments): ?>
                    <tr><td colspan="9" class="muted" style="text-align:center; padding:30px;">Belum ada appointment. Daftarkan pasien dan buat appointment baru melalui menu di atas.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Cetak Tiket Antrean Pasien -->
    <div id="queueModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:9999; justify-content:center; align-items:center;">
        <div style="background:#fff; border-radius:12px; width:340px; padding:24px; box-shadow:0 10px 25px rgba(0,0,0,0.2); text-align:center;" id="printableTicket">
            <div style="font-size:16px; font-weight:800; color:#183f52;">RS PERMATA MEDIKA</div>
            <div style="font-size:11px; color:#71818b; margin-bottom:12px; border-bottom:1px dashed #ccd7dc; padding-bottom:8px;">Sistem Informasi Manajemen Rumah Sakit (SIMRS)</div>
            
            <div style="font-size:12px; color:#71818b; text-transform:uppercase; font-weight:700;">Nomor Antrean Pelayanan</div>
            <div id="ticketNumber" style="font-size:42px; font-weight:900; color:#18879b; margin:6px 0;">A-001</div>
            
            <div style="text-align:left; background:#f4f7f8; border-radius:8px; padding:12px; font-size:12px; margin-bottom:14px;">
                <div style="margin-bottom:4px;"><strong>Pasien:</strong> <span id="ticketPatient"></span></div>
                <div style="margin-bottom:4px;"><strong>No. RM:</strong> <span id="ticketMR"></span></div>
                <div style="margin-bottom:4px;"><strong>Poli/Unit:</strong> <span id="ticketUnit"></span></div>
                <div style="margin-bottom:4px;"><strong>Jadwal:</strong> <span id="ticketSchedule"></span></div>
                <div><strong>No. Appt:</strong> <span id="ticketAppt"></span></div>
            </div>

            <p style="font-size:11px; color:#71818b; margin:0 0 16px;">Harap menunggu hingga nomor antrean Anda dipanggil oleh petugas loket/poliklinik.</p>
            
            <div style="display:flex; gap:8px; justify-content:center;">
                <button type="button" class="btn" onclick="window.print()">Print Slip</button>
                <button type="button" class="btn secondary" onclick="closeQueueModal()">Tutup</button>
            </div>
        </div>
    </div>

    <script>
    function openQueueModal(num, patient, mr, unit, appt, schedule) {
        document.getElementById('ticketNumber').textContent = num;
        document.getElementById('ticketPatient').textContent = patient;
        document.getElementById('ticketMR').textContent = mr;
        document.getElementById('ticketUnit').textContent = unit;
        document.getElementById('ticketSchedule').textContent = schedule;
        document.getElementById('ticketAppt').textContent = appt;
        const modal = document.getElementById('queueModal');
        modal.style.display = 'flex';
    }
    function closeQueueModal() {
        document.getElementById('queueModal').style.display = 'none';
    }
    document.getElementById('tableSearch')?.addEventListener('input', filterAppointments);
    document.getElementById('statusFilter')?.addEventListener('change', filterAppointments);
    function filterAppointments() {
        const query = (document.getElementById('tableSearch')?.value || '').toLowerCase();
        const status = document.getElementById('statusFilter')?.value || '';
        const rows = document.querySelectorAll('.appt-row');
        rows.forEach(row => {
            const text = row.innerText.toLowerCase();
            const rowStatus = row.getAttribute('data-status') || '';
            const matchQuery = !query || text.includes(query);
            const matchStatus = !status || rowStatus === status;
            row.style.display = (matchQuery && matchStatus) ? '' : 'none';
        });
    }
    </script>
<?php endif; ?>

<?php if ($view === 'patient_new'): ?>
    <div class="formbox">
        <h2>Pendaftaran Pasien Baru</h2>
        <p class="muted">Masukkan identitas pasien untuk disimpan ke database master pasien SIMRS.</p>
        <form method="post">
            <input type="hidden" name="csrf" value="<?= simrs_h(simrs_token()) ?>">
            <input type="hidden" name="action" value="patient">
            <div class="formrow">
                <div>
                    <label>No. Rekam Medis (RM) *</label>
                    <input name="medical_record_no" required placeholder="Contoh: RM-2026-001" value="RM-<?= date('y') ?>-<?= str_pad((string)($patientCount + 1), 4, '0', STR_PAD_LEFT) ?>">
                </div>
                <div>
                    <label>Nama Lengkap Pasien *</label>
                    <input name="full_name" required placeholder="Nama lengkap sesuai KTP/identitas">
                </div>
                <div>
                    <label>Tanggal Lahir</label>
                    <input type="date" name="birth_date">
                </div>
                <div>
                    <label>Jenis Kelamin</label>
                    <select name="sex">
                        <option value="U">Pilih jenis kelamin</option>
                        <option value="M">Laki-laki (M)</option>
                        <option value="F">Perempuan (F)</option>
                    </select>
                </div>
                <div>
                    <label>No. Telepon / WhatsApp</label>
                    <input name="phone" placeholder="Contoh: 081234567890">
                </div>
                <div>
                    <label>Alamat Tinggal</label>
                    <input name="address" placeholder="Alamat domisili pasien">
                </div>
            </div>
            <div class="form-actions">
                <button class="btn" type="submit">Simpan Data Pasien</button>
                <a class="btn secondary" href="scheduling.php?view=dashboard">Batal</a>
            </div>
        </form>
    </div>
<?php endif; ?>

<?php if ($view === 'appointment_new'): 
    $preselectedPatient = (int)($_GET['patient_id'] ?? 0);
?>
    <div class="formbox">
        <h2>Buat Appointment Pasien</h2>
        <p class="muted">Hubungkan pasien terdaftar ke unit/poliklinik dengan jadwal waktu pelayanan.</p>
        <?php if (!$patients || !$units): ?>
            <p class="errorbox">Master data pasien atau unit belum tersedia. Silakan daftarkan pasien terlebih dahulu.</p>
        <?php else: ?>
            <form method="post">
                <input type="hidden" name="csrf" value="<?= simrs_h(simrs_token()) ?>">
                <input type="hidden" name="action" value="appointment">
                <div class="formrow">
                    <div>
                        <label>Pilih Pasien Terdaftar *</label>
                        <select name="patient_id" required>
                            <option value="">-- Cari atau pilih nama pasien --</option>
                            <?php foreach ($patients as $p): ?>
                                <option value="<?= $p['id'] ?>" <?= $preselectedPatient === (int)$p['id'] ? 'selected' : '' ?>>
                                    <?= simrs_h($p['medical_record_no'] . ' — ' . $p['full_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label>Unit / Poliklinik Tujuan *</label>
                        <select name="unit_id" required>
                            <option value="">-- Pilih Unit --</option>
                            <?php foreach ($units as $u): ?>
                                <option value="<?= $u['id'] ?>"><?= simrs_h($u['unit_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label>Tanggal Pelayanan *</label>
                        <input type="date" name="appointment_date" required value="<?= date('Y-m-d') ?>">
                    </div>
                    <div>
                        <label>Jam Mulai Pelayanan *</label>
                        <input type="time" name="start_time" required value="<?= date('H:i') ?>">
                    </div>
                    <div class="wide">
                        <label>Keluhan Pasien / Catatan Medis Awal</label>
                        <textarea name="complaint" placeholder="Misal: Kontrol rutin, batuk berdahak 3 hari, konsultasi spesialis..."></textarea>
                    </div>
                </div>
                <div class="form-actions">
                    <button class="btn" type="submit">Simpan & Terbitkan Jadwal Appointment</button>
                    <a class="btn secondary" href="scheduling.php?view=dashboard">Kembali ke Daftar</a>
                </div>
            </form>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php simrs_layout_end(); ?>