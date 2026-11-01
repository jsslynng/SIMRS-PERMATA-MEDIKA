<?php
require_once __DIR__ . '/config/module_helpers.php';

$role = $_SESSION['role_code'] ?? '';
$triageRole = in_array($role, ['triage_nurse', 'nurse', 'ed_staff'], true);
$doctorRole = $role === 'doctor';

if (!$triageRole && !$doctorRole) {
    simrs_require_role(['triage_nurse', 'nurse', 'ed_staff', 'doctor']);
}

$view = $_GET['view'] ?? 'dashboard';
$allowedViews = $triageRole ? ['dashboard', 'triage'] : ['dashboard', 'soap'];

if (!in_array($view, $allowedViews, true)) {
    http_response_code(403);
    exit('Akses ditolak: halaman tidak tersedia untuk role ini.');
}

simrs_check_post();
$msg = simrs_take_flash();
$err = '';
$patients = [];
$encounters = [];
$selected = (int)($_GET['encounter'] ?? 0);
$latestTriage = null;
$note = null;
$diagnoses = [];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        // 1. Buat Encounter IGD Baru (Bisa oleh Dokter MAUPUN Perawat Triase)
        if ($action === 'encounter') {
            $pid = (int)($_POST['patient_id'] ?? 0);
            $complaint = trim($_POST['chief_complaint'] ?? '');
            $priority = $_POST['priority'] ?? 'urgent';

            if (!$pid || $complaint === '') {
                throw new RuntimeException('Pasien dan keluhan utama wajib diisi.');
            }
            if (!in_array($priority, ['routine', 'urgent', 'emergency'], true)) {
                throw new RuntimeException('Prioritas tidak valid.');
            }

            $unit = $pdo->query("SELECT id FROM units WHERE unit_code = 'ED' LIMIT 1")->fetchColumn();
            if (!$unit) {
                throw new RuntimeException('Unit ED belum tersedia di tabel units.');
            }

            $no = 'ED' . date('ymdHis') . random_int(10, 99);
            $q = $pdo->prepare("INSERT INTO encounters (encounter_no, patient_id, unit_id, encounter_type, priority, status, registered_by, chief_complaint) VALUES (?, ?, ?, 'ED', ?, 'waiting', ?, ?)");
            $q->execute([$no, $pid, $unit, $priority, $_SESSION['user_id'], $complaint]);
            $newEncounterId = (int)$pdo->lastInsertId();

            if ($triageRole) {
                simrs_flash("Encounter IGD <strong>$no</strong> berhasil didaftarkan! <a href='ed.php?view=triage&encounter=$newEncounterId' style='font-weight:bold; text-decoration:underline; margin-left:8px;'>&rarr; Langsung Input Triase & TTV Pasien Ini</a>");
            } else {
                simrs_flash("Encounter IGD <strong>$no</strong> berhasil didaftarkan.");
            }
            simrs_redirect('ed.php?view=dashboard');
        }

        $eid = (int)($_POST['encounter_id'] ?? 0);
        if (!$eid) {
            throw new RuntimeException('Encounter pasien tidak valid.');
        }

        $q = $pdo->prepare("SELECT id FROM encounters WHERE id = ? AND encounter_type = 'ED'");
        $q->execute([$eid]);
        if (!$q->fetchColumn()) {
            throw new RuntimeException('Encounter IGD tidak ditemukan.');
        }

        // 2. Input Triage & TTV (Perawat Triase)
        if ($action === 'triage' && $triageRole) {
            $cat = $_POST['triage_category'] ?? 'yellow';
            if (!in_array($cat, ['red', 'yellow', 'green', 'black'], true)) {
                throw new RuntimeException('Kategori triase tidak valid.');
            }

            $sbp = $_POST['sbp'] !== '' ? filter_var($_POST['sbp'], FILTER_VALIDATE_INT) : null;
            $dbp = $_POST['dbp'] !== '' ? filter_var($_POST['dbp'], FILTER_VALIDATE_INT) : null;
            $pulse = $_POST['pulse'] !== '' ? filter_var($_POST['pulse'], FILTER_VALIDATE_INT) : null;
            $rr = $_POST['rr'] !== '' ? filter_var($_POST['rr'], FILTER_VALIDATE_INT) : null;
            $temp = $_POST['temp'] !== '' ? filter_var($_POST['temp'], FILTER_VALIDATE_FLOAT) : null;
            $spo2 = $_POST['spo2'] !== '' ? filter_var($_POST['spo2'], FILTER_VALIDATE_INT) : null;
            $pain = $_POST['pain'] !== '' ? filter_var($_POST['pain'], FILTER_VALIDATE_INT) : null;

            if ($spo2 !== null && ($spo2 < 0 || $spo2 > 100)) throw new RuntimeException('SpO₂ harus antara 0–100%.');
            if ($pain !== null && ($pain < 0 || $pain > 10)) throw new RuntimeException('Skor nyeri harus antara 0–10.');

            $q = $pdo->prepare('INSERT INTO triage_assessments (encounter_id, assessed_by, triage_category, systolic_bp, diastolic_bp, pulse, respiratory_rate, temperature_c, spo2, pain_score, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $q->execute([$eid, $_SESSION['user_id'], $cat, $sbp, $dbp, $pulse, $rr, $temp, $spo2, $pain, trim($_POST['notes'] ?? '')]);

            $q = $pdo->prepare("UPDATE encounters SET status = 'in_progress' WHERE id = ? AND status IN ('waiting', 'registered')");
            $q->execute([$eid]);

            simrs_flash('Pemeriksaan Triase dan TTV berhasil disimpan ke rekam medis.');
            simrs_redirect('ed.php?view=dashboard');
        }

        // 3. Simpan SOAP & Diagnosis (Dokter IGD)
        if ($action === 'soap' && $doctorRole) {
            $s = trim($_POST['subjective'] ?? '');
            $o = trim($_POST['objective'] ?? '');
            $a = trim($_POST['assessment'] ?? '');
            $p = trim($_POST['plan'] ?? '');

            $q = $pdo->prepare("SELECT id FROM clinical_notes WHERE encounter_id = ? AND note_type = 'progress' ORDER BY created_at DESC LIMIT 1");
            $q->execute([$eid]);
            $nid = $q->fetchColumn();

            if ($nid) {
                $q = $pdo->prepare('UPDATE clinical_notes SET subjective = ?, objective = ?, assessment = ?, plan = ?, author_id = ?, updated_at = NOW() WHERE id = ?');
                $q->execute([$s, $o, $a, $p, $_SESSION['user_id'], $nid]);
            } else {
                $q = $pdo->prepare("INSERT INTO clinical_notes (encounter_id, author_id, note_type, subjective, objective, assessment, plan) VALUES (?, ?, 'progress', ?, ?, ?, ?)");
                $q->execute([$eid, $_SESSION['user_id'], $s, $o, $a, $p]);
            }

            $dname = trim($_POST['diagnosis_name'] ?? '');
            $dcode = trim($_POST['diagnosis_code'] ?? '');
            $dtype = $_POST['diagnosis_type'] ?? 'working';
            if (!in_array($dtype, ['primary', 'secondary', 'working', 'differential'], true)) $dtype = 'working';

            if ($dname !== '') {
                $q = $pdo->prepare('SELECT id FROM diagnoses WHERE encounter_id = ? AND diagnosis_type = ? ORDER BY created_at DESC LIMIT 1');
                $q->execute([$eid, $dtype]);
                $did = $q->fetchColumn();

                if ($did) {
                    $q = $pdo->prepare('UPDATE diagnoses SET diagnosis_name = ?, diagnosis_code = ?, recorded_by = ? WHERE id = ?');
                    $q->execute([$dname, $dcode ?: null, $_SESSION['user_id'], $did]);
                } else {
                    $q = $pdo->prepare('INSERT INTO diagnoses (encounter_id, diagnosis_code, diagnosis_name, diagnosis_type, recorded_by) VALUES (?, ?, ?, ?, ?)');
                    $q->execute([$eid, $dcode ?: null, $dname, $dtype, $_SESSION['user_id']]);
                }
            }

            $disp = $_POST['disposition'] ?? '';
            if ($disp !== '') {
                if (!in_array($disp, ['discharged', 'admitted', 'transferred'], true)) throw new RuntimeException('Disposition tidak valid.');
                $q = $pdo->prepare("UPDATE encounters SET status = ?, discharge_at = CASE WHEN ? = 'discharged' THEN NOW() ELSE NULL END WHERE id = ?");
                $q->execute([$disp, $disp, $eid]);
            }

            simrs_flash('Catatan SOAP klinis, diagnosis, dan disposisi pasien berhasil disimpan.');
            simrs_redirect('ed.php?view=dashboard');
        }

        throw new RuntimeException('Aksi tidak diizinkan untuk role ini.');
    }

    $patients = $pdo->query('SELECT id, medical_record_no, full_name FROM patients ORDER BY full_name')->fetchAll();
    $encounters = $pdo->query("
        SELECT e.id, e.encounter_no, e.priority, e.status, e.arrival_at, e.chief_complaint,
               p.full_name, p.medical_record_no,
               (SELECT ta.triage_category FROM triage_assessments ta WHERE ta.encounter_id = e.id ORDER BY ta.assessed_at DESC LIMIT 1) triage_category,
               (SELECT ta.spo2 FROM triage_assessments ta WHERE ta.encounter_id = e.id ORDER BY ta.assessed_at DESC LIMIT 1) latest_spo2,
               (SELECT ta.systolic_bp FROM triage_assessments ta WHERE ta.encounter_id = e.id ORDER BY ta.assessed_at DESC LIMIT 1) latest_sbp,
               (SELECT ta.temperature_c FROM triage_assessments ta WHERE ta.encounter_id = e.id ORDER BY ta.assessed_at DESC LIMIT 1) latest_temp
        FROM encounters e
        JOIN patients p ON p.id = e.patient_id
        WHERE e.encounter_type = 'ED'
        ORDER BY e.arrival_at DESC
        LIMIT 200
    ")->fetchAll();

    if ($selected) {
        $q = $pdo->prepare('SELECT * FROM triage_assessments WHERE encounter_id = ? ORDER BY assessed_at DESC LIMIT 1');
        $q->execute([$selected]);
        $latestTriage = $q->fetch() ?: null;

        $q = $pdo->prepare("SELECT * FROM clinical_notes WHERE encounter_id = ? AND note_type = 'progress' ORDER BY created_at DESC LIMIT 1");
        $q->execute([$selected]);
        $note = $q->fetch() ?: null;

        $q = $pdo->prepare('SELECT * FROM diagnoses WHERE encounter_id = ? ORDER BY created_at DESC');
        $q->execute([$selected]);
        $diagnoses = $q->fetchAll();
    }
} catch (Throwable $e) {
    $err = $e instanceof RuntimeException ? $e->getMessage() : simrs_db_error($e);
}

simrs_layout_start($triageRole ? 'Triage Nurse' : 'Emergency Department', 'ED');
if ($msg) echo '<div class="notice">' . $msg . '</div>';
if ($err) echo '<div class="errorbox">' . simrs_h($err) . '</div>';
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; flex-wrap:wrap; gap:12px;">
    <div>
        <h1 style="margin:0 0 4px;"><?= $triageRole ? ($view === 'triage' ? 'Input Triase & Tanda Vital (TTV)' : 'Worklist Triase Perawat') : ($view === 'soap' ? 'Pengkajian Medis & SOAP Dokter' : 'Emergency Department (IGD)') ?></h1>
        <div class="subtitle" style="margin-bottom:0;"><?= $triageRole ? 'Pendaftaran kedatangan IGD, penapisan prioritas, dan input tanda vital.' : 'Pencatatan klinis SOAP, diagnosis terstruktur, dan disposisi rawat.' ?></div>
    </div>
    <div class="form-actions" style="margin:0;">
        <a class="btn <?= $view === 'dashboard' ? '' : 'secondary' ?>" href="ed.php?view=dashboard">&larr; <?= $triageRole ? 'Triage Worklist' : 'Encounter List' ?></a>
        <?php if ($triageRole): ?>
            <a class="btn <?= $view === 'triage' ? '' : 'secondary' ?>" href="ed.php?view=triage">Input Triage & TTV</a>
        <?php endif; ?>
    </div>
</div>

<?php if ($view === 'dashboard'): ?>
    <!-- Form Registrasi Pasien Baru ke IGD (BISA DILAKUKAN OLEH PERAWAT MAUPUN DOKTER) -->
    <div class="formbox" style="margin-bottom:18px;">
        <h2>Daftarkan Kedatangan Pasien ke IGD</h2>
        <p class="muted">Gunakan form ini saat pasien darurat baru tiba di IGD untuk membuat nomor encounter.</p>
        <?php if (!$patients): ?>
            <p class="muted">Belum ada pasien. Silakan registrasikan pasien melalui menu Scheduling terlebih dahulu.</p>
        <?php else: ?>
            <form method="post">
                <input type="hidden" name="csrf" value="<?= simrs_h(simrs_token()) ?>">
                <input type="hidden" name="action" value="encounter">
                <div class="formrow">
                    <div>
                        <label>Pilih Pasien Terdaftar *</label>
                        <select name="patient_id" required>
                            <option value="">-- Pilih pasien --</option>
                            <?php foreach ($patients as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= simrs_h($p['medical_record_no'] . ' — ' . $p['full_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label>Tingkat Prioritas Awal</label>
                        <select name="priority">
                            <option value="urgent" selected>Urgent (Mendesak)</option>
                            <option value="emergency">Emergency (Gawat Darurat)</option>
                            <option value="routine">Routine (Tidak gawat)</option>
                        </select>
                    </div>
                    <div class="wide">
                        <label>Keluhan Utama Pasien Masuk IGD *</label>
                        <textarea name="chief_complaint" required placeholder="Misal: Nyeri dada menjalar ke lengan kiri, sesak napas akut, penurunan kesadaran, luka bakar..."></textarea>
                    </div>
                </div>
                <div class="form-actions">
                    <button class="btn" type="submit">🚨 Daftarkan Encounter IGD</button>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <!-- Panel Worklist Pasien IGD -->
    <div class="panel">
        <div class="panel-title" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
            <span><?= $triageRole ? 'Triage Worklist (Daftar Antrean Triase Pasien)' : 'ED Encounter Worklist' ?></span>
            <input type="text" id="edSearch" placeholder="Cari nama pasien, no. RM, atau encounter..." style="padding:6px 12px; border:1px solid #ccd7dc; border-radius:6px; font-size:13px; width:260px;">
        </div>
        <div class="tablewrap">
            <table id="edTable">
                <thead>
                    <tr>
                        <th>No. Encounter</th>
                        <th>Nama Pasien</th>
                        <th>No. RM</th>
                        <th>Keluhan Utama</th>
                        <th>Prioritas</th>
                        <th>Kategori Triase</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($encounters as $e): 
                    $tCat = strtolower($e['triage_category'] ?? '');
                    $badgeTriage = 'secondary';
                    if ($tCat === 'red') $badgeTriage = 'red';
                    elseif ($tCat === 'yellow') $badgeTriage = 'yellow';
                    elseif ($tCat === 'green') $badgeTriage = 'green';
                    elseif ($tCat === 'black') $badgeTriage = 'danger';

                    $alert = '';
                    if ($e['latest_spo2'] !== null && $e['latest_spo2'] < 92) $alert .= '🚨 SpO₂ ' . $e['latest_spo2'] . '% ';
                    if ($e['latest_sbp'] !== null && $e['latest_sbp'] >= 180) $alert .= '⚠️ TD ' . $e['latest_sbp'] . ' ';
                    if ($e['latest_temp'] !== null && $e['latest_temp'] >= 39) $alert .= '🔥 ' . $e['latest_temp'] . '°C ';
                ?>
                    <tr class="ed-row">
                        <td><strong><?= simrs_h($e['encounter_no']) ?></strong></td>
                        <td>
                            <strong><?= simrs_h($e['full_name']) ?></strong>
                            <?php if ($alert): ?>
                                <span style="display:block; font-size:11px; color:#d9534f; font-weight:bold;"><?= $alert ?></span>
                            <?php endif; ?>
                        </td>
                        <td><code><?= simrs_h($e['medical_record_no']) ?></code></td>
                        <td><small><?= simrs_h($e['chief_complaint'] ?? '-') ?></small></td>
                        <td><?= simrs_h(ucfirst($e['priority'])) ?></td>
                        <td>
                            <span class="badge <?= $badgeTriage ?>" style="font-size:11px;">
                                <?= $tCat ? strtoupper($tCat) : 'BELUM DINILAI' ?>
                            </span>
                        </td>
                        <td><span class="badge secondary"><?= simrs_h($e['status']) ?></span></td>
                        <td>
                            <a class="btn" style="padding:5px 12px; font-size:12px;" href="ed.php?view=<?= $triageRole ? 'triage' : 'soap' ?>&encounter=<?= $e['id'] ?>">
                                <?= $triageRole ? '📝 Input TTV' : '🩺 Open SOAP' ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$encounters): ?>
                    <tr><td colspan="8" class="muted" style="text-align:center; padding:30px;">Belum ada antrean encounter IGD. Pasien yang didaftarkan akan muncul di sini.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
    document.getElementById('edSearch')?.addEventListener('input', function() {
        const query = this.value.toLowerCase();
        document.querySelectorAll('.ed-row').forEach(row => {
            row.style.display = row.innerText.toLowerCase().includes(query) ? '' : 'none';
        });
    });
    </script>
<?php endif; ?>

<?php if ($view === 'triage' && $triageRole): ?>
    <?php if (!$selected): ?>
        <!-- TAMPILAN JIKA PERAWAT KLIK INPUT TRIAGE & TTV TANPA PILIH PASIEN DULU -->
        <div class="formbox">
            <h2>Pilih Pasien untuk Penilaian Triase & TTV</h2>
            <p class="muted">Silakan pilih pasien dari daftar kunjungan IGD yang siap dinilai tanda vitalnya:</p>
            
            <div style="margin-bottom:18px;">
                <label style="font-weight:bold; margin-bottom:6px; display:block;">Pilih Pasien dari Dropdown:</label>
                <select onchange="if(this.value) window.location.href='ed.php?view=triage&encounter=' + this.value;" style="padding:10px; width:100%; max-width:480px; border:1px solid #ccd7dc; border-radius:7px;">
                    <option value="">-- Pilih pasien IGD yang akan dinilai --</option>
                    <?php foreach ($encounters as $enc): ?>
                        <option value="<?= $enc['id'] ?>">
                            <?= simrs_h($enc['encounter_no'] . ' — ' . $enc['full_name'] . ' (' . ($enc['triage_category'] ? 'Triase: ' . strtoupper($enc['triage_category']) : 'Belum Dinilai') . ')') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="panel">
                <div class="panel-title">Antrean Pasien IGD Menunggu Triase</div>
                <div class="tablewrap">
                    <table>
                        <thead>
                            <tr>
                                <th>No. Encounter</th>
                                <th>Pasien</th>
                                <th>No. RM</th>
                                <th>Keluhan Masuk</th>
                                <th>Status Triase</th>
                                <th>Aksi Cepat</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($encounters as $enc): ?>
                            <tr>
                                <td><strong><?= simrs_h($enc['encounter_no']) ?></strong></td>
                                <td><strong><?= simrs_h($enc['full_name']) ?></strong></td>
                                <td><code><?= simrs_h($enc['medical_record_no']) ?></code></td>
                                <td><small><?= simrs_h($enc['chief_complaint']) ?></small></td>
                                <td>
                                    <span class="badge <?= $enc['triage_category'] ? ($enc['triage_category'] === 'red' ? 'red' : ($enc['triage_category'] === 'yellow' ? 'yellow' : 'green')) : 'secondary' ?>">
                                        <?= $enc['triage_category'] ? strtoupper($enc['triage_category']) : 'Belum Dinilai' ?>
                                    </span>
                                </td>
                                <td>
                                    <a class="btn" style="padding:5px 12px; font-size:12px;" href="ed.php?view=triage&encounter=<?= $enc['id'] ?>">
                                        📝 Mulai Nilai Triase & TTV &rarr;
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$encounters): ?>
                            <tr><td colspan="6" class="muted" style="text-align:center; padding:20px;">Belum ada pasien IGD. Silakan daftarkan pasien baru pada form di Beranda IGD.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    <?php else: ?>
        <!-- FORM PENGISIAN TTV & TRIASE JIKA PASIEN SUDAH DIPILIH -->
        <?php 
        $patientRow = null;
        foreach ($encounters as $en) {
            if ((int)$en['id'] === $selected) { $patientRow = $en; break; }
        }
        if (!$patientRow): ?>
            <div class="errorbox">Encounter pasien tidak ditemukan. <a href="ed.php?view=triage">Pilih pasien lain &rarr;</a></div>
        <?php else: ?>
            <div class="formbox">
                <h2>Pemeriksaan Triase & TTV Pasien</h2>
                <div style="background:#eef6f9; border-left:4px solid #18879b; padding:12px 16px; border-radius:4px; margin-bottom:18px;">
                    <div style="font-size:16px; font-weight:bold; color:#183f52;"><?= simrs_h($patientRow['full_name']) ?> (RM: <?= simrs_h($patientRow['medical_record_no']) ?>)</div>
                    <div style="font-size:13px; color:#476575; margin-top:3px;">No. Encounter: <strong><?= simrs_h($patientRow['encounter_no']) ?></strong> &bull; Keluhan Masuk: <em><?= simrs_h($patientRow['chief_complaint']) ?></em></div>
                </div>

                <?php if ($latestTriage): ?>
                    <div class="notice">
                        <strong>Hasil Penilaian Triase Sebelumnya (<?= simrs_h($latestTriage['assessed_at']) ?>):</strong><br>
                        Kategori: <strong><?= strtoupper($latestTriage['triage_category']) ?></strong> &bull;
                        TD: <?= simrs_h($latestTriage['systolic_bp'] ?? '-') ?>/<?= simrs_h($latestTriage['diastolic_bp'] ?? '-') ?> mmHg &bull;
                        Nadi: <?= simrs_h($latestTriage['pulse'] ?? '-') ?> x/m &bull;
                        RR: <?= simrs_h($latestTriage['respiratory_rate'] ?? '-') ?> x/m &bull;
                        Suhu: <?= simrs_h($latestTriage['temperature_c'] ?? '-') ?> °C &bull;
                        SpO₂: <?= simrs_h($latestTriage['spo2'] ?? '-') ?>% &bull;
                        Skor Nyeri: <?= simrs_h($latestTriage['pain_score'] ?? '-') ?>/10
                    </div>
                <?php endif; ?>

                <form method="post">
                    <input type="hidden" name="csrf" value="<?= simrs_h(simrs_token()) ?>">
                    <input type="hidden" name="action" value="triage">
                    <input type="hidden" name="encounter_id" value="<?= $selected ?>">

                    <div class="formrow">
                        <div class="wide">
                            <label>Kategori Triase (Emergency Severity Index / ATS) *</label>
                            <select name="triage_category" style="font-weight:bold; font-size:15px;" required>
                                <option value="red" <?= ($latestTriage['triage_category'] ?? '') === 'red' ? 'selected' : '' ?> style="color:#b6383d;">🔴 MERAH — Resusitasi / Mengancam Nyawa Segera (Prioritas 1)</option>
                                <option value="yellow" <?= empty($latestTriage) || ($latestTriage['triage_category'] ?? '') === 'yellow' ? 'selected' : '' ?> style="color:#896500;">🟡 KUNING — Emergency / Gawat Tidak Darurat (Prioritas 2)</option>
                                <option value="green" <?= ($latestTriage['triage_category'] ?? '') === 'green' ? 'selected' : '' ?> style="color:#2d6a4f;">🟢 HIJAU — Semi-Urgent / Stabil (Prioritas 3)</option>
                                <option value="black" <?= ($latestTriage['triage_category'] ?? '') === 'black' ? 'selected' : '' ?> style="color:#333;">⚫ HITAM — Death on Arrival (DOA)</option>
                            </select>
                        </div>

                        <div>
                            <label>Tekanan Darah Sistolik (mmHg)</label>
                            <input type="number" name="sbp" placeholder="Contoh: 120" value="<?= simrs_h($latestTriage['systolic_bp'] ?? '') ?>">
                        </div>
                        <div>
                            <label>Tekanan Darah Diastolik (mmHg)</label>
                            <input type="number" name="dbp" placeholder="Contoh: 80" value="<?= simrs_h($latestTriage['diastolic_bp'] ?? '') ?>">
                        </div>
                        <div>
                            <label>Frekuensi Nadi (/menit)</label>
                            <input type="number" name="pulse" placeholder="Contoh: 84" value="<?= simrs_h($latestTriage['pulse'] ?? '') ?>">
                        </div>
                        <div>
                            <label>Laju Pernapasan / RR (/menit)</label>
                            <input type="number" name="rr" placeholder="Contoh: 20" value="<?= simrs_h($latestTriage['respiratory_rate'] ?? '') ?>">
                        </div>
                        <div>
                            <label>Suhu Tubuh (°C)</label>
                            <input type="number" step="0.1" name="temp" placeholder="Contoh: 36.8" value="<?= simrs_h($latestTriage['temperature_c'] ?? '') ?>">
                        </div>
                        <div>
                            <label>Saturasi Oksigen / SpO₂ (%)</label>
                            <input type="number" name="spo2" min="0" max="100" placeholder="Contoh: 98" value="<?= simrs_h($latestTriage['spo2'] ?? '') ?>">
                        </div>
                        <div class="wide">
                            <label>Skor Nyeri Pasien (0–10)</label>
                            <input type="number" name="pain" min="0" max="10" placeholder="0 = Tidak nyeri, 10 = Nyeri luar biasa" value="<?= simrs_h($latestTriage['pain_score'] ?? '') ?>">
                        </div>
                        <div class="wide">
                            <label>Catatan Triase / Kondisi Klinis Awal</label>
                            <textarea name="notes" placeholder="Catatan kesadaran (GCS), kondisi jalan napas, atau riwayat alergi..."><?= simrs_h($latestTriage['notes'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button class="btn" type="submit">💾 Simpan Triase & TTV</button>
                        <a class="btn secondary" href="ed.php?view=dashboard">Batal & Kembali</a>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    <?php endif; ?>
<?php endif; ?>

<?php if ($view === 'soap' && $doctorRole && $selected): ?>
    <?php 
    $patientRow = null;
    foreach ($encounters as $en) {
        if ((int)$en['id'] === $selected) { $patientRow = $en; break; }
    }
    if (!$patientRow): ?>
        <div class="errorbox">Encounter pasien tidak ditemukan. <a href="ed.php?view=dashboard">Kembali ke Worklist &rarr;</a></div>
    <?php else: ?>
        <div class="formbox">
            <h2>Pengkajian Klinis & Rekam Medis SOAP Dokter IGD</h2>
            <div style="background:#eef6f9; border-left:4px solid #18879b; padding:12px 16px; border-radius:4px; margin-bottom:18px;">
                <div style="font-size:16px; font-weight:bold; color:#183f52;"><?= simrs_h($patientRow['full_name']) ?> (RM: <?= simrs_h($patientRow['medical_record_no']) ?>)</div>
                <div style="font-size:13px; color:#476575; margin-top:3px;">No. Encounter: <strong><?= simrs_h($patientRow['encounter_no']) ?></strong> &bull; Keluhan Masuk: <em><?= simrs_h($patientRow['chief_complaint']) ?></em></div>
            </div>

            <?php if ($latestTriage): ?>
                <div class="notice">
                    <strong>TTV & Triase Terakhir dari Perawat:</strong> Kategori <span class="badge <?= $latestTriage['triage_category'] === 'red' ? 'red' : ($latestTriage['triage_category'] === 'yellow' ? 'yellow' : 'green') ?>"><?= strtoupper($latestTriage['triage_category']) ?></span> &bull;
                    TD: <?= simrs_h($latestTriage['systolic_bp'] ?? '-') ?>/<?= simrs_h($latestTriage['diastolic_bp'] ?? '-') ?> mmHg &bull;
                    Nadi: <?= simrs_h($latestTriage['pulse'] ?? '-') ?> x/m &bull;
                    RR: <?= simrs_h($latestTriage['respiratory_rate'] ?? '-') ?> x/m &bull;
                    Suhu: <?= simrs_h($latestTriage['temperature_c'] ?? '-') ?> °C &bull;
                    SpO₂: <?= simrs_h($latestTriage['spo2'] ?? '-') ?>% &bull;
                    Skor Nyeri: <?= simrs_h($latestTriage['pain_score'] ?? '-') ?>/10
                </div>
            <?php endif; ?>

            <form method="post">
                <input type="hidden" name="csrf" value="<?= simrs_h(simrs_token()) ?>">
                <input type="hidden" name="action" value="soap">
                <input type="hidden" name="encounter_id" value="<?= $selected ?>">

                <div class="formrow">
                    <div class="wide">
                        <label>S — Subjective (Anamnesis / Keluhan & Riwayat Penyakit Sekarang)</label>
                        <textarea name="subjective" placeholder="Pasien mengeluhkan... sejak 2 hari SMRS, riwayat alergi..."><?= simrs_h($note['subjective'] ?? '') ?></textarea>
                    </div>
                    <div class="wide">
                        <label>O — Objective (Pemeriksaan Fisik & Temuan Objektif)</label>
                        <textarea name="objective" placeholder="Keadaan umum: Tampak sakit sedang, kesadaran compos mentis, thoraks: vesikuler..."><?= simrs_h($note['objective'] ?? '') ?></textarea>
                    </div>
                    <div class="wide">
                        <label>A — Assessment (Penilaian Klinis / Resume Kasus)</label>
                        <textarea name="assessment" placeholder="Kesan klinis dan pertimbangan diferensial..."><?= simrs_h($note['assessment'] ?? '') ?></textarea>
                    </div>
                    <div class="wide">
                        <label>P — Plan (Rencana Penunjang, Terapi Obat, & Tindakan)</label>
                        <textarea name="plan" placeholder="Order Lab darah lengkap, Rontgen Thorax, Infus RL 20 tpm, Inj. Ondansetron 4mg..."><?= simrs_h($note['plan'] ?? '') ?></textarea>
                    </div>

                    <!-- Preset ICD-10 Cepat -->
                    <div class="wide" style="background:#f9fafb; padding:12px; border-radius:8px; border:1px solid #e2e8f0;">
                        <label style="margin-bottom:6px;">⚡ Quick-Pick ICD-10 IGD (Klik untuk mengisi cepat):</label>
                        <div style="display:flex; flex-wrap:wrap; gap:6px;">
                            <button type="button" class="btn secondary" style="font-size:11px; padding:4px 8px;" onclick="pickDiagnosis('A09', 'Gastroenteritis Akut (GEA / Diare Akut)')">A09 Gastroenteritis</button>
                            <button type="button" class="btn secondary" style="font-size:11px; padding:4px 8px;" onclick="pickDiagnosis('I10', 'Hipertensi Esensial / Urgensi')">I10 Hipertensi</button>
                            <button type="button" class="btn secondary" style="font-size:11px; padding:4px 8px;" onclick="pickDiagnosis('J18.9', 'Pneumonia Akut Komunitas')">J18.9 Pneumonia</button>
                            <button type="button" class="btn secondary" style="font-size:11px; padding:4px 8px;" onclick="pickDiagnosis('J45.9', 'Asma Bronkial Eksaserbasi Akut')">J45.9 Asma Akut</button>
                            <button type="button" class="btn secondary" style="font-size:11px; padding:4px 8px;" onclick="pickDiagnosis('R10.4', 'Nyeri Abdomen Akut (Kolik Abdomen)')">R10.4 Nyeri Abdomen</button>
                            <button type="button" class="btn secondary" style="font-size:11px; padding:4px 8px;" onclick="pickDiagnosis('S06.0', 'Cedera Kepala Ringan (CKR)')">S06.0 Cedera Kepala</button>
                            <button type="button" class="btn secondary" style="font-size:11px; padding:4px 8px;" onclick="pickDiagnosis('K29.7', 'Gastritis Akut')">K29.7 Gastritis</button>
                            <button type="button" class="btn secondary" style="font-size:11px; padding:4px 8px;" onclick="pickDiagnosis('R50.9', 'Febris / Demam Observasi Akut')">R50.9 Febris Akut</button>
                        </div>
                    </div>

                    <div>
                        <label>Kode ICD-10</label>
                        <input id="diagCode" name="diagnosis_code" value="<?= simrs_h($diagnoses[0]['diagnosis_code'] ?? '') ?>" placeholder="Misal: A09 atau I10">
                    </div>
                    <div>
                        <label>Nama Diagnosis Medis *</label>
                        <input id="diagName" name="diagnosis_name" value="<?= simrs_h($diagnoses[0]['diagnosis_name'] ?? '') ?>" placeholder="Nama penyakit / diagnosis">
                    </div>
                    <div>
                        <label>Tipe Diagnosis</label>
                        <select name="diagnosis_type">
                            <option value="working" selected>Diagnosis Kerja (Working Diagnosis)</option>
                            <option value="primary">Diagnosis Utama (Primary)</option>
                            <option value="secondary">Diagnosis Sekunder (Secondary)</option>
                            <option value="differential">Diagnosis Banding (Differential)</option>
                        </select>
                    </div>
                    <div>
                        <label>Disposisi Pasien (Status Akhir IGD)</label>
                        <select name="disposition">
                            <option value="">-- Tetap di IGD / Dalam Observasi --</option>
                            <option value="discharged">Discharged (Boleh Pulang / Rawat Jalan)</option>
                            <option value="admitted">Admitted (Rujuk Rawat Inap / Bangsal)</option>
                            <option value="transferred">Transferred (Rujuk ke Faskes Lain / ICU)</option>
                        </select>
                    </div>
                </div>

                <div class="form-actions">
                    <button class="btn" type="submit">💾 Simpan SOAP & Diagnosis</button>
                    <a class="btn secondary" href="ed.php?view=dashboard">Batal & Kembali</a>
                </div>
            </form>

            <script>
            function pickDiagnosis(code, name) {
                document.getElementById('diagCode').value = code;
                document.getElementById('diagName').value = name;
            }
            </script>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php simrs_layout_end(); ?>