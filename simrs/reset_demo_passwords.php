<?php
// Jalankan sekali secara lokal, lalu HAPUS file ini.
require_once __DIR__ . '/config/database.php';
header('Content-Type: text/plain; charset=utf-8');
$map = [
    'registration' => 'PermataRegistration@2026',
    'ed_staff' => 'PermataEd_staff@2026',
    'triage_nurse' => 'PermataTriage_nurse@2026',
    'doctor' => 'PermataDoctor@2026',
    'radiographer' => 'PermataRadiographer@2026',
    'radiology' => 'PermataRadiology@2026',
];
try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE username = ? AND status = 'active'");
    foreach ($map as $username => $password) {
        $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $username]);
        echo $username . ': ' . ($stmt->rowCount() ? 'password diperbarui' : 'tidak berubah (akun tidak ditemukan atau hash sama)') . PHP_EOL;
    }
    $pdo->commit();
    echo PHP_EOL . 'Akun admin tidak disentuh. Hapus reset_demo_passwords.php sekarang.' . PHP_EOL;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo 'Reset gagal. Tidak ada perubahan yang dikomit.' . PHP_EOL;
    error_log($e->getMessage());
}
