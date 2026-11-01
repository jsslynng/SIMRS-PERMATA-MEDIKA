<?php
require_once __DIR__ . '/config/module_helpers.php';
simrs_require_role(['doctor', 'radiographer', 'radiologist']);

$role = $_SESSION['role_code'] ?? '';
$canOrder = $role === 'doctor';
$canExam = $role === 'radiographer';
$canReport = $role === 'radiologist';

$view = $_GET['view'] ?? 'dashboard';
if (!in_array($view, ['dashboard', 'order', 'exam', 'result', 'print'], true)) {
    http_response_code(400);
    exit('Halaman radiologi tidak valid.');
}

simrs_check_post();
$msg = simrs_take_flash();
$err = '';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        // 1. Dokter Membuat Order Radiologi
        if ($action === 'order' && $canOrder) {
            $eid = (int)($_POST['encounter_id'] ?? 0);
            $exam = trim($_POST['exam_name'] ?? '');
            $mod = trim($_POST['modality'] ?? 'X-Ray');
            $part = trim($_POST['body_part'] ?? '');
            $priority = $_POST['priority'] ?? 'routine';
            $ind = trim($_POST['indication'] ?? '');

            if (!$eid || $exam === '' || $part === '') {
                throw new RuntimeException('Encounter, jenis pemeriksaan, dan bagian tubuh wajib diisi.');
            }
            if (!in_array($priority, ['routine', 'urgent', 'stat'], true)) {
                throw new RuntimeException('Prioritas tidak valid.');
            }

            // Cari atau buat katalog radiologi
            $q = $pdo->prepare('SELECT id FROM radiology_catalog WHERE exam_name = ? LIMIT 1');
            $q->execute([$exam]);
            $catalog = (int)$q->fetchColumn();

            if (!$catalog) {
                $code = 'RAD' . strtoupper(substr(hash('sha256', $exam . $mod . $part), 0, 10));
                $q = $pdo->prepare('INSERT INTO radiology_catalog (exam_code, exam_name, modality, body_part) VALUES (?, ?, ?, ?)');
                $q->execute([$code, $exam, $mod, $part]);
                $catalog = (int)$pdo->lastInsertId();
            }

            $pdo->beginTransaction();
            $no = 'ORDRAD' . date('ymdHis') . random_int(10, 99);
            $q = $pdo->prepare("INSERT INTO clinical_orders (order_no, encounter_id, ordered_by, order_type, priority, clinical_indication, status) VALUES (?, ?, ?, 'radiology', ?, ?, 'submitted')");
            $q->execute([$no, $eid, $_SESSION['user_id'], $priority, $ind]);
            $orderId = (int)$pdo->lastInsertId();

            $q = $pdo->prepare("INSERT INTO radiology_orders (clinical_order_id, radiology_exam_id, status) VALUES (?, ?, 'received')");
            $q->execute([$orderId, $catalog]);
            $pdo->commit();

            simrs_flash('Order radiologi berhasil dibuat: ' . $no);
            simrs_redirect('radiology.php?view=dashboard');
        }

        $rid = (int)($_POST['radiology_order_id'] ?? 0);
        if (!$rid) throw new RuntimeException('Pilih order radiologi yang valid.');

        // 2. Radiografer Melakukan Pemeriksaan
        if ($action === 'exam' && $canExam) {
            $next = $_POST['exam_status'] ?? 'verified';
            $validExam = ['verified', 'scheduled', 'in_progress', 'performed'];
            if (!in_array($next, $validExam, true)) throw new RuntimeException('Status pemeriksaan tidak valid.');

            $identity = !empty($_POST['identity_verified']);
            if (in_array($next, ['in_progress', 'performed'], true) && !$identity) {
                throw new RuntimeException('Verifikasi identitas pasien wajib dicentang sebelum pelaksanaan foto.');
            }

            $scheduled = $_POST['scheduled_at'] ?? '';
            $scheduledSql = $scheduled !== '' ? str_replace('T', ' ', $scheduled) . ':00' : null;

            $q = $pdo->prepare("
                UPDATE radiology_orders 
                SET status = ?,
                    scheduled_at = COALESCE(?, scheduled_at),
                    performed_at = CASE WHEN ? = 'performed' THEN NOW() ELSE performed_at END,
                    performed_by = CASE WHEN ? = 'performed' THEN ? ELSE performed_by END
                WHERE id = ? AND status NOT IN ('final', 'cancelled')
            ");
            $q->execute([$next, $scheduledSql, $next, $next, $_SESSION['user_id'], $rid]);

            if ($next === 'performed') {
                $pdo->prepare("UPDATE clinical_orders co JOIN radiology_orders ro ON ro.clinical_order_id = co.id SET co.status = 'completed' WHERE ro.id = ?")->execute([$rid]);
            }

            simrs_flash('Status pelaksanaan foto radiologi berhasil diperbarui menjadi: ' . strtoupper($next));
            simrs_redirect('radiology.php?view=dashboard');
        }

        // 3. Radiolog Membaca & Menandatangani Hasil
        if ($action === 'report' && $canReport) {
            $report = trim($_POST['report_text'] ?? '');
            $impression = trim($_POST['impression'] ?? '');
            if ($report === '' || $impression === '') throw new RuntimeException('Hasil pemeriksaan dan kesimpulan wajib diisi.');

            $q = $pdo->prepare('SELECT status, report_text, impression FROM radiology_orders WHERE id = ?');
            $q->execute([$rid]);
            $old = $q->fetch();
            if (!$old || $old['status'] === 'cancelled') throw new RuntimeException('Order tidak dapat difinalisasi.');

            $pdo->beginTransaction();
            if ($old['status'] === 'final') {
                $audit = $pdo->prepare("INSERT INTO audit_logs (user_id, module_code, action, entity_table, entity_id, details_json, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $audit->execute([
                    $_SESSION['user_id'], 'RADIOLOGY', 'REPORT_AMENDED', 'radiology_orders', $rid,
                    json_encode(['previous_report' => $old['report_text'], 'previous_impression' => $old['impression']], JSON_UNESCAPED_UNICODE),
                    $_SERVER['REMOTE_ADDR'] ?? null
                ]);
            }

            $q = $pdo->prepare("UPDATE radiology_orders SET report_text = ?, impression = ?, reported_by = ?, finalized_at = NOW(), status = 'final' WHERE id = ? AND status NOT IN ('cancelled')");
            $q->execute([$report, $impression, $_SESSION['user_id'], $rid]);
            $pdo->commit();

            simrs_flash($old['status'] === 'final' ? 'Hasil radiologi berhasil diperbarui; revisi tercatat di audit log.' : 'Hasil bacaan radiologi berhasil difinalisasi & ditandatangani.');
            simrs_redirect('radiology.php?view=dashboard');
        }

        throw new RuntimeException('Aksi tidak diizinkan untuk kewenangan Anda.');
    }

    $encounters = $pdo->query("SELECT e.id, e.encounter_no, p.full_name, p.medical_record_no FROM encounters e JOIN patients p ON p.id = e.patient_id ORDER BY e.arrival_at DESC LIMIT 200")->fetchAll();
    
    $orders = $pdo->query("
        SELECT ro.id, ro.status, ro.performed_at, ro.scheduled_at, ro.finalized_at, ro.report_text, ro.impression,
               co.order_no, co.priority, co.clinical_indication, co.ordered_at,
               p.full_name, p.medical_record_no, p.birth_date, p.sex,
               rc.exam_name, rc.modality, rc.body_part,
               e.encounter_no,
               u_rep.full_name as radiologist_name
        FROM radiology_orders ro
        JOIN clinical_orders co ON co.id = ro.clinical_order_id
        JOIN radiology_catalog rc ON rc.id = ro.radiology_exam_id
        JOIN encounters e ON e.id = co.encounter_id
        JOIN patients p ON p.id = e.patient_id
        LEFT JOIN users u_rep ON u_rep.id = ro.reported_by
        ORDER BY co.ordered_at DESC
        LIMIT 150
    ")->fetchAll();

    $countAll = count($orders);
    $countPending = count(array_filter($orders, fn($o) => !in_array($o['status'], ['final', 'cancelled'], true)));
    $countFinal = count(array_filter($orders, fn($o) => $o['status'] === 'final'));

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $err = $e instanceof RuntimeException ? $e->getMessage() : simrs_db_error($e);
    $encounters = $encounters ?? [];
    $orders = $orders ?? [];
    $countAll = $countAll ?? 0;
    $countPending = $countPending ?? 0;
    $countFinal = $countFinal ?? 0;
}

// Tampilan Cetak Hasil Resmi
if ($view === 'print') {
    $printId = (int)($_GET['id'] ?? 0);
    $item = null;
    foreach ($orders as $ord) {
        if ((int)$ord['id'] === $printId) { $item = $ord; break; }
    }
    if (!$item) exit('Hasil pemeriksaan radiologi tidak ditemukan.');
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Hasil Radiologi - <?= simrs_h($item['order_no']) ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; color: #222; }
        .kop { text-align: center; border-bottom: 3px double #333; padding-bottom: 12px; margin-bottom: 24px; }
        .kop h2 { margin: 0; font-size: 20px; color: #183f52; }
        .kop p { margin: 4px 0 0; font-size: 13px; color: #666; }
        .title { text-align: center; font-size: 17px; font-weight: bold; margin-bottom: 20px; text-decoration: underline; }
        .meta-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 13px; }
        .meta-table td { padding: 4px 8px; vertical-align: top; }
        .section-box { border: 1px solid #ddd; border-radius: 6px; padding: 14px; margin-bottom: 16px; font-size: 13px; line-height: 1.6; }
        .sign { margin-top: 40px; float: right; width: 250px; text-align: center; font-size: 13px; }
        .no-print { margin-bottom: 20px; }
        @media print { .no-print { display: none; } body { margin: 0; } }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()" style="padding: 8px 16px; background: #18879b; color: #fff; border: 0; border-radius: 5px; cursor: pointer; font-weight: bold;">🖨️ Cetak Lembar Ini</button>
        <button onclick="window.close()" style="padding: 8px 16px; background: #e2e8f0; border: 0; border-radius: 5px; cursor: pointer;">Tutup</button>
    </div>
    <div class="kop">
        <h2>RUMAH SAKIT PERMATA MEDIKA</h2>
        <p>Instalasi Radiologi Diagnostik & Imejing Kedokteran</p>
        <p>Jl. Kesehatan No. 45, Telp: (021) 555-0123 &bull; Terakreditasi Paripurna KARS</p>
    </div>
    <div class="title">HASIL PEMERIKSAAN RADIOLOGI</div>
    <table class="meta-table">
        <tr>
            <td width="18%"><strong>No. Order</strong></td><td width="32%">: <?= simrs_h($item['order_no']) ?></td>
            <td width="18%"><strong>No. Rekam Medis</strong></td><td width="32%">: <?= simrs_h($item['medical_record_no']) ?></td>
        </tr>
        <tr>
            <td><strong>Nama Pasien</strong></td><td>: <?= simrs_h($item['full_name']) ?></td>
            <td><strong>Tanggal Lahir / JK</strong></td><td>: <?= simrs_h($item['birth_date'] ?: '-') ?> (<?= simrs_h($item['sex']) ?>)</td>
        </tr>
        <tr>
            <td><strong>Pemeriksaan</strong></td><td>: <strong><?= simrs_h($item['exam_name']) ?> (<?= simrs_h($item['modality']) ?>)</strong></td>
            <td><strong>Tanggal Foto</strong></td><td>: <?= simrs_h($item['performed_at'] ?: $item['ordered_at']) ?></td>
        </tr>
        <tr>
            <td><strong>Bagian Tubuh</strong></td><td>: <?= simrs_h($item['body_part']) ?></td>
            <td><strong>Indikasi Klinis</strong></td><td>: <?= simrs_h($item['clinical_indication'] ?: '-') ?></td>
        </tr>
    </table>
    
    <div style="font-weight: bold; font-size: 14px; margin-bottom: 6px;">DESKRIPSI / FINDINGS:</div>
    <div class="section-box"><?= nl2br(simrs_h($item['report_text'])) ?></div>

    <div style="font-weight: bold; font-size: 14px; margin-bottom: 6px;">KESIMPULAN / IMPRESSION:</div>
    <div class="section-box" style="background: #fcfcfc;"><strong><?= nl2br(simrs_h($item['impression'])) ?></strong></div>

    <div class="sign">
        <div>Tanggal Baca: <?= simrs_h(date('d F Y', strtotime($item['finalized_at'] ?: 'now'))) ?></div>
        <div style="margin: 20px 0; color: #18879b; font-weight: bold;">[ TERVERIFIKASI DIGITAL ]</div>
        <div><strong><?= simrs_h($item['radiologist_name'] ?: 'dr. Spesialis Radiologi, Sp.Rad') ?></strong></div>
        <small style="color: #666;">SIP: 446/SIP.RAD/2026</small>
    </div>
</body>
</html>
<?php
    exit;
}

simrs_layout_start('Radiology', 'Radiology');
if ($msg) echo '<div class="notice">' . simrs_h($msg) . '</div>';
if ($err) echo '<div class="errorbox">' . simrs_h($err) . '</div>';
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; flex-wrap:wrap; gap:12px;">
    <div>
        <h1 style="margin:0 0 4px;"><?= $view === 'order' ? 'Form Order Radiologi Baru' : ($view === 'result' ? 'Interpretasi Hasil Radiologi' : ($view === 'exam' ? 'Pelaksanaan Pemeriksaan Foto' : 'Radiology Worklist')) ?></h1>
        <div class="subtitle" style="margin-bottom:0;">Pengelolaan order radiologi, verifikasi tindakan, hingga pelaporan hasil diagnostik.</div>
    </div>
    <div class="form-actions" style="margin:0;">
        <a class="btn <?= $view === 'dashboard' ? '' : 'secondary' ?>" href="radiology.php?view=dashboard">&larr; Radiology Worklist</a>
        <?php if ($canOrder): ?>
            <a class="btn <?= $view === 'order' ? '' : 'secondary' ?>" href="radiology.php?view=order">+ Buat Order Baru</a>
        <?php endif; ?>
    </div>
</div>

<?php if ($view === 'dashboard'): ?>
    <div class="cards">
        <div class="card">
            <div class="label">Total Order Radiologi</div>
            <div class="num"><?= $countAll ?></div>
            <div class="hint">Semua order di database</div>
        </div>
        <div class="card">
            <div class="label">Menunggu / Sedang Proses</div>
            <div class="num"><?= $countPending ?></div>
            <div class="hint">Perlu tindakan foto/bacaan</div>
        </div>
        <div class="card">
            <div class="label">Hasil Final Selesai</div>
            <div class="num"><?= $countFinal ?></div>
            <div class="hint">Siap dicetak dokter</div>
        </div>
    </div>

    <div class="panel">
        <div class="panel-title" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
            <span>Daftar Pemeriksaan Radiologi Pasien</span>
            <input type="text" id="radSearch" placeholder="Cari nama pasien, no. order, atau tes..." style="padding:6px 12px; border:1px solid #ccd7dc; border-radius:6px; font-size:13px; width:260px;">
        </div>
        <div class="tablewrap">
            <table id="radTable">
                <thead>
                    <tr>
                        <th>No. Order</th>
                        <th>Nama Pasien</th>
                        <th>No. RM</th>
                        <th>Pemeriksaan</th>
                        <th>Prioritas</th>
                        <th>Status</th>
                        <th>Hasil / Kesimpulan</th>
                        <th>Aksi Tindakan</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($orders as $o): 
                    $statBadge = 'secondary';
                    if ($o['status'] === 'final') $statBadge = 'green';
                    elseif ($o['status'] === 'performed') $statBadge = 'yellow';
                    elseif ($o['status'] === 'in_progress') $statBadge = 'yellow';
                    elseif ($o['status'] === 'cancelled') $statBadge = 'red';

                    $prioBadge = 'secondary';
                    if ($o['priority'] === 'stat') $prioBadge = 'red';
                    elseif ($o['priority'] === 'urgent') $prioBadge = 'yellow';
                ?>
                    <tr class="rad-row">
                        <td><strong><?= simrs_h($o['order_no']) ?></strong></td>
                        <td><?= simrs_h($o['full_name']) ?></td>
                        <td><code><?= simrs_h($o['medical_record_no']) ?></code></td>
                        <td><strong><?= simrs_h($o['exam_name']) ?></strong><br><small class="muted"><?= simrs_h($o['modality'] . ' &bull; ' . $o['body_part']) ?></small></td>
                        <td><span class="badge <?= $prioBadge ?>"><?= strtoupper(simrs_h($o['priority'])) ?></span></td>
                        <td><span class="badge <?= $statBadge ?>"><?= strtoupper(simrs_h($o['status'])) ?></span></td>
                        <td><small><?= simrs_h($o['impression'] ?: ($o['report_text'] ?: 'Belum ada hasil bacaan')) ?></small></td>
                        <td>
                            <?php if ($canExam && !in_array($o['status'], ['final', 'cancelled'], true)): ?>
                                <a class="btn" style="padding:4px 8px; font-size:12px; margin-bottom:4px;" href="radiology.php?view=exam&id=<?= $o['id'] ?>">📷 Foto</a>
                            <?php endif; ?>
                            <?php if ($canReport && $o['status'] !== 'cancelled'): ?>
                                <a class="btn" style="padding:4px 8px; font-size:12px; margin-bottom:4px;" href="radiology.php?view=result&id=<?= $o['id'] ?>">✍️ Baca</a>
                            <?php endif; ?>
                            <?php if ($o['status'] === 'final'): ?>
                                <a class="btn secondary" style="padding:4px 8px; font-size:12px;" href="radiology.php?view=print&id=<?= $o['id'] ?>" target="_blank">🖨️ Cetak</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$orders): ?>
                    <tr><td colspan="8" class="muted" style="text-align:center; padding:30px;">Belum ada order radiologi. Buat order pertama melalui formulir di atas.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
    document.getElementById('radSearch')?.addEventListener('input', function() {
        const query = this.value.toLowerCase();
        document.querySelectorAll('.rad-row').forEach(row => {
            row.style.display = row.innerText.toLowerCase().includes(query) ? '' : 'none';
        });
    });
    </script>
<?php endif; ?>

<?php if ($canOrder && $view === 'order'): ?>
    <div class="formbox">
        <h2>Order Pemeriksaan Radiologi</h2>
        <p class="muted">Dokter mengajukan permintaan foto rontgen/CT/USG ke instalasi radiologi.</p>
        <?php if (!$encounters): ?>
            <p class="errorbox">Belum ada kunjungan/encounter pasien. Silakan buat encounter di Scheduling atau IGD terlebih dahulu.</p>
        <?php else: ?>
            <form method="post">
                <input type="hidden" name="csrf" value="<?= simrs_h(simrs_token()) ?>">
                <input type="hidden" name="action" value="order">
                <div class="formrow">
                    <div class="wide">
                        <label>Pilih Pasien / Kunjungan Encounter *</label>
                        <select name="encounter_id" required>
                            <option value="">-- Pilih encounter pasien --</option>
                            <?php foreach ($encounters as $e): ?>
                                <option value="<?= $e['id'] ?>"><?= simrs_h($e['encounter_no'] . ' — ' . $e['medical_record_no'] . ' ' . $e['full_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label>Nama Pemeriksaan *</label>
                        <input name="exam_name" required placeholder="Contoh: Chest X-Ray PA, Foto Pelvis, USG Abdomen">
                    </div>
                    <div>
                        <label>Modalitas Alat</label>
                        <select name="modality">
                            <option value="X-Ray">X-Ray (Rontgen Konvensional)</option>
                            <option value="CT">CT-Scan</option>
                            <option value="Ultrasound">USG (Ultrasonografi)</option>
                            <option value="MRI">MRI</option>
                            <option value="Mammography">Mammografi</option>
                        </select>
                    </div>
                    <div>
                        <label>Bagian Tubuh (Body Part) *</label>
                        <input name="body_part" required placeholder="Contoh: Thorax, Abdomen, Femur Dextra">
                    </div>
                    <div>
                        <label>Prioritas Pemeriksaan</label>
                        <select name="priority">
                            <option value="routine">Routine (Biasa)</option>
                            <option value="urgent">Urgent (Mendesak)</option>
                            <option value="stat">STAT / CITO (Segera & Darurat)</option>
                        </select>
                    </div>
                    <div class="wide">
                        <label>Indikasi Klinis / Catatan Dokter</label>
                        <textarea name="indication" placeholder="Kecurigaan klinis, riwayat trauma, dugaan efusi/pneumonia..."></textarea>
                    </div>
                </div>
                <div class="form-actions">
                    <button class="btn" type="submit">Kirim Order ke Radiologi</button>
                    <a class="btn secondary" href="radiology.php?view=dashboard">Batal</a>
                </div>
            </form>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if ($view === 'exam' && $canExam): 
    $ridExam = (int)($_GET['id'] ?? 0);
    $examOrder = null;
    foreach ($orders as $ord) {
        if ((int)$ord['id'] === $ridExam) { $examOrder = $ord; break; }
    }
    if ($examOrder): ?>
        <div class="formbox">
            <h2>Pelaksanaan Pemeriksaan Radiologi (Radiografer)</h2>
            <div style="background:#eef6f9; border-left:4px solid #18879b; padding:12px 16px; border-radius:4px; margin-bottom:18px;">
                <div style="font-size:16px; font-weight:bold; color:#183f52;"><?= simrs_h($examOrder['full_name']) ?> (RM: <?= simrs_h($examOrder['medical_record_no']) ?>)</div>
                <div style="font-size:13px; color:#476575; margin-top:3px;">
                    Order: <strong><?= simrs_h($examOrder['order_no']) ?></strong> &bull; Pemeriksaan: <strong><?= simrs_h($examOrder['exam_name']) ?> (<?= simrs_h($examOrder['modality']) ?>)</strong> &bull; Indikasi: <em><?= simrs_h($examOrder['clinical_indication'] ?: '-') ?></em>
                </div>
            </div>

            <form method="post">
                <input type="hidden" name="csrf" value="<?= simrs_h(simrs_token()) ?>">
                <input type="hidden" name="action" value="exam">
                <input type="hidden" name="radiology_order_id" value="<?= $examOrder['id'] ?>">

                <div class="formrow">
                    <div>
                        <label>Status Pelaksanaan Tindakan *</label>
                        <select name="exam_status">
                            <option value="verified" <?= $examOrder['status'] === 'verified' ? 'selected' : '' ?>>Verified (Order Diterima)</option>
                            <option value="scheduled" <?= $examOrder['status'] === 'scheduled' ? 'selected' : '' ?>>Scheduled (Dijadwalkan)</option>
                            <option value="in_progress" <?= $examOrder['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress (Sedang Diambil Foto)</option>
                            <option value="performed" <?= $examOrder['status'] === 'performed' ? 'selected' : '' ?>>Performed (Foto Selesai Diambil)</option>
                        </select>
                    </div>
                    <div>
                        <label>Waktu Pelaksanaan Foto</label>
                        <input type="datetime-local" name="scheduled_at" value="<?= simrs_h(!empty($examOrder['scheduled_at']) ? date('Y-m-d\TH:i', strtotime($examOrder['scheduled_at'])) : date('Y-m-d\TH:i')) ?>">
                    </div>
                    <div class="wide" style="background:#fff8e6; padding:12px; border-radius:6px; border:1px solid #fed7aa;">
                        <label style="cursor:pointer; color:#9a3412;">
                            <input type="checkbox" name="identity_verified" value="1" required>
                            <strong>Saya telah melakukan verifikasi identitas pasien (Nama, No. RM, dan Bagian Tubuh) sebelum mengambil foto rontgen.</strong>
                        </label>
                    </div>
                </div>
                <div class="form-actions">
                    <button class="btn" type="submit">Simpan Status Pelaksanaan</button>
                    <a class="btn secondary" href="radiology.php?view=dashboard">Kembali</a>
                </div>
            </form>
        </div>
    <?php else: ?>
        <div class="errorbox">Order radiologi tidak ditemukan.</div>
    <?php endif; ?>
<?php endif; ?>

<?php if ($view === 'result' && $canReport): 
    $ridView = (int)($_GET['id'] ?? 0);
    $selectedOrder = null;
    foreach ($orders as $ord) {
        if ((int)$ord['id'] === $ridView) { $selectedOrder = $ord; break; }
    }
    if ($selectedOrder): ?>
        <div class="formbox">
            <h2>Ekspertise & Bacaan Hasil Radiologi (Dokter Radiolog)</h2>
            <div style="background:#eef6f9; border-left:4px solid #18879b; padding:12px 16px; border-radius:4px; margin-bottom:18px;">
                <div style="font-size:16px; font-weight:bold; color:#183f52;"><?= simrs_h($selectedOrder['full_name']) ?> (RM: <?= simrs_h($selectedOrder['medical_record_no']) ?>)</div>
                <div style="font-size:13px; color:#476575; margin-top:3px;">
                    Order: <strong><?= simrs_h($selectedOrder['order_no']) ?></strong> &bull; Pemeriksaan: <strong><?= simrs_h($selectedOrder['exam_name']) ?> (<?= simrs_h($selectedOrder['modality']) ?> &bull; <?= simrs_h($selectedOrder['body_part']) ?>)</strong>
                </div>
            </div>

            <!-- Quick Template Chips -->
            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px; margin-bottom:16px;">
                <label style="font-weight:bold; margin-bottom:6px; display:block;">⚡ Quick Template Bacaan Radiologi (Klik untuk otomatis mengisi):</label>
                <div style="display:flex; flex-wrap:wrap; gap:8px;">
                    <button type="button" class="btn secondary" style="font-size:12px; padding:5px 10px;" onclick="applyTemplate('normal_thorax')">🫁 Thorax Normal</button>
                    <button type="button" class="btn secondary" style="font-size:12px; padding:5px 10px;" onclick="applyTemplate('bronchopneumonia')">🫁 Bronkopneumonia</button>
                    <button type="button" class="btn secondary" style="font-size:12px; padding:5px 10px;" onclick="applyTemplate('fracture')">🦴 Fraktur Tulang</button>
                    <button type="button" class="btn secondary" style="font-size:12px; padding:5px 10px;" onclick="applyTemplate('cardiomegaly')">🫀 Kardiomegali</button>
                </div>
            </div>

            <form method="post">
                <input type="hidden" name="csrf" value="<?= simrs_h(simrs_token()) ?>">
                <input type="hidden" name="action" value="report">
                <input type="hidden" name="radiology_order_id" value="<?= $selectedOrder['id'] ?>">

                <div class="formrow">
                    <div class="wide">
                        <label>Findings / Deskripsi Temuan Gambar *</label>
                        <textarea id="reportFindings" name="report_text" required rows="5"><?= simrs_h($selectedOrder['report_text'] ?? '') ?></textarea>
                    </div>
                    <div class="wide">
                        <label>Impression / Kesimpulan Medis Diagnostik *</label>
                        <textarea id="reportImpression" name="impression" required rows="3"><?= simrs_h($selectedOrder['impression'] ?? '') ?></textarea>
                    </div>
                </div>

                <div class="form-actions">
                    <button class="btn" type="submit">💾 Finalisasi & Tanda Tangan Laporan</button>
                    <a class="btn secondary" href="radiology.php?view=dashboard">Batal</a>
                </div>
            </form>

            <script>
            const radTemplates = {
                normal_thorax: {
                    findings: "Cor: Bentuk dan ukuran dalam batas normal, CTR < 50%.\nPulmo: Corakan bronkovaskuler normal, tidak tampak infiltrat maupun konsolidasi pada kedua lapang paru.\nSinus kostofrenikus kanan dan kiri lancip, diafragma licin.\nTulang-tulang dinding toraks intak, soft tissue tenang.",
                    impression: "Cor dan pulmo dalam batas normal.\nTidak tampak gambaran pneumonia, kardiomegali, atau TB aktif."
                },
                bronchopneumonia: {
                    findings: "Cor: Bentuk dan ukuran dalam batas normal.\nPulmo: Corakan bronkovaskuler kasar disertai gambaran bercak infiltrat patchy pada kedua lapang paru terutama lobus medius dan inferior.\nSinus kostofrenikus kanan dan kiri lancip, diafragma baik.\nTulang-tulang rangka intak.",
                    impression: "Kesan gambaran Bronkopneumonia aktif."
                },
                fracture: {
                    findings: "Tampak diskontinuitas korteks os disertai pergeseran fragmen (displaced fracture).\nCelah sendi di sekitarnya dalam batas baik.\nTampak pembengkakan jaringan lunak (soft tissue swelling) di sekitar area lesi.",
                    impression: "Fraktur komplet dengan pergeseran fragmen tulang. Disarankan fiksasi / konsultasi spesialis orthopedi."
                },
                cardiomegaly: {
                    findings: "Cor: Tampak membesar ke lateral kiri dengan CTR > 55% (apeks tertanam/terangkat).\nPulmo: Corakan vaskular paru meningkat ringan, tidak tampak bendungan cairan masif.\nSinus dan diafragma dalam batas normal.",
                    impression: "Kardiomegali disertai peningkatan corakan vaskular paru."
                }
            };
            function applyTemplate(type) {
                if (radTemplates[type]) {
                    document.getElementById('reportFindings').value = radTemplates[type].findings;
                    document.getElementById('reportImpression').value = radTemplates[type].impression;
                }
            }
            </script>
        </div>
    <?php else: ?>
        <div class="errorbox">Order radiologi tidak ditemukan.</div>
    <?php endif; ?>
<?php endif; ?>

<?php simrs_layout_end(); ?>