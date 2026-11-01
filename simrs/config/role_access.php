<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function simrs_require_login(): void {
    if (empty($_SESSION['user_id']) || empty($_SESSION['username'])) {
        header('Location: login.php');
        exit;
    }
}

function simrs_require_role(array $allowedRoles): void {
    simrs_require_login();
    $role = $_SESSION['role_code'] ?? '';
    if (!in_array($role, $allowedRoles, true)) {
        http_response_code(403);
        echo '<!doctype html><html lang="id"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Akses ditolak</title><link rel="stylesheet" href="style.css"><body><main class="main"><h1>Akses ditolak</h1><p>Akun Anda tidak memiliki izin untuk membuka halaman ini.</p><p><a class="btn" href="' . htmlspecialchars(simrs_home_for_role($role), ENT_QUOTES, 'UTF-8') . '">Kembali ke dashboard</a></p></main></body></html>';
        exit;
    }
}

function simrs_h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function simrs_home_for_role(string $role): string {
    if ($role === 'registration_staff') return 'scheduling.php?view=dashboard';
    if (in_array($role, ['radiographer', 'radiologist'], true)) return 'radiology.php?view=dashboard';
    if (in_array($role, ['triage_nurse', 'ed_staff', 'doctor', 'nurse'], true)) return 'ed.php';
    return 'dashboard.php';
}
