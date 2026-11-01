<?php
session_start();
require_once __DIR__ . "/config/database.php";

$error = "";
$success = "";

// Fitur Auto-Sync Password Demo ke Database MySQL
if (isset($_GET['sync_passwords'])) {
    $demoAccounts = [
        'admin'        => ['name' => 'Administrator SIMRS', 'role' => 'simrs_admin', 'pass' => 'PermataAdmin@2026'],
        'doctor'       => ['name' => 'dr. Ahmad Spesialis, Sp.Rad', 'role' => 'doctor', 'pass' => 'PermataDoctor@2026'],
        'triage_nurse' => ['name' => 'Ns. Siti Rahma, S.Kep', 'role' => 'triage_nurse', 'pass' => 'PermataTriage_nurse@2026'],
        'registration' => ['name' => 'Budi Santoso (Petugas Registrasi)', 'role' => 'registration_staff', 'pass' => 'PermataRegistration@2026'],
        'radiographer' => ['name' => 'Rian Hidayat, A.Md.Rad', 'role' => 'radiographer', 'pass' => 'PermataRadiographer@2026'],
        'radiology'    => ['name' => 'dr. Maya Radiologi, Sp.Rad', 'role' => 'radiologist', 'pass' => 'PermataRadiology@2026'],
        'ed_staff'     => ['name' => 'Petugas Administrasi IGD', 'role' => 'ed_staff', 'pass' => 'PermataEd_staff@2026'],
    ];

    try {
        $pdo->beginTransaction();
        foreach ($demoAccounts as $u => $d) {
            $hash = password_hash($d['pass'], PASSWORD_DEFAULT);
            
            // Cek apakah user sudah ada
            $st = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $st->execute([$u]);
            $uid = $st->fetchColumn();

            if ($uid) {
                $up = $pdo->prepare("UPDATE users SET password_hash = ?, status = 'active' WHERE id = ?");
                $up->execute([$hash, $uid]);
            } else {
                $ins = $pdo->prepare("INSERT INTO users (full_name, username, password_hash, status) VALUES (?, ?, ?, 'active')");
                $ins->execute([$d['name'], $u, $hash]);
                $uid = (int)$pdo->lastInsertId();
            }

            // Sambungkan ke role
            $rSt = $pdo->prepare("SELECT id FROM roles WHERE role_code = ?");
            $rSt->execute([$d['role']]);
            $rid = $rSt->fetchColumn();

            if ($rid && $uid) {
                $pdo->prepare("INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?, ?)")->execute([$uid, $rid]);
            }
        }
        $pdo->commit();
        $success = "Semua akun demo berhasil disinkronkan dengan password Permata(Role)@2026!";
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = "Gagal sinkronisasi database: " . $e->getMessage();
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($username !== "" && $password !== "") {
        try {
            $stmt = $pdo->prepare(
                "SELECT u.id, u.username, u.full_name, u.password_hash, u.status,
                        r.role_code, r.role_name
                 FROM users u
                 LEFT JOIN user_roles ur ON ur.user_id = u.id
                 LEFT JOIN roles r ON r.id = ur.role_id
                 WHERE u.username = :username
                 LIMIT 1"
            );
            $stmt->execute(["username" => $username]);
            $user = $stmt->fetch();

            if (
                $user &&
                $user["status"] === "active" &&
                password_verify($password, $user["password_hash"])
            ) {
                session_regenerate_id(true);

                $_SESSION["user_id"] = (int) $user["id"];
                $_SESSION["username"] = $user["username"];
                $_SESSION["name"] = $user["full_name"];
                $_SESSION["role_code"] = $user["role_code"] ?? "";
                $_SESSION["role"] = $user["role_name"] ?? "";

                $roleCode = $_SESSION["role_code"];

                if ($roleCode === "registration_staff") {
                    header("Location: scheduling.php?view=dashboard");
                } elseif (in_array($roleCode, ["radiographer", "radiologist"], true)) {
                    header("Location: radiology.php?view=dashboard");
                } elseif (in_array($roleCode, ["triage_nurse", "ed_staff", "doctor", "nurse"], true)) {
                    header("Location: ed.php");
                } else {
                    header("Location: dashboard.php");
                }
                exit;
            }

            $error = "Username atau password salah. Jika baru pertama kali, klik tombol kuning '⚡ Sinkronkan Database' di bawah.";
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $error = "Koneksi database gagal. Periksa apakah MySQL XAMPP sudah menyala.";
        }
    } else {
        $error = "Masukkan username dan password.";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — SIMRS RS Permata Medika</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; }
        body { background: linear-gradient(135deg, #0f2b38 0%, #184b61 50%, #1e6985 100%); min-height: 100vh; display: flex; justify-content: center; align-items: center; padding: 20px; }
        .login-card { width: 450px; max-width: 100%; background: #ffffff; padding: 34px 30px; border-radius: 14px; box-shadow: 0 15px 35px rgba(0,0,0,0.25); }
        .logo-box { text-align: center; margin-bottom: 22px; }
        .logo-box .icon { display: inline-flex; justify-content: center; align-items: center; width: 54px; height: 54px; background: #e8f4f5; border-radius: 50%; font-size: 26px; margin-bottom: 8px; }
        .logo-box h1 { color: #183b4e; font-size: 23px; font-weight: 800; }
        .logo-box p { color: #64748b; font-size: 13px; margin-top: 3px; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; margin-bottom: 6px; color: #1e3a4b; font-weight: 600; font-size: 13px; }
        .form-group input { width: 100%; padding: 11px 13px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; outline: none; transition: border-color .2s; }
        .form-group input:focus { border-color: #17818f; box-shadow: 0 0 0 3px rgba(23, 129, 143, 0.15); }
        .login-btn { width: 100%; padding: 12px; border: none; border-radius: 8px; background: #17818f; color: white; font-size: 15px; font-weight: 700; cursor: pointer; transition: background .2s; margin-top: 4px; }
        .login-btn:hover { background: #136a77; }
        .error { background: #fdecec; color: #b91c1c; padding: 10px 14px; border-radius: 8px; margin-bottom: 16px; font-size: 13px; text-align: center; border: 1px solid #fecaca; }
        .success { background: #ecfdf5; color: #047857; padding: 10px 14px; border-radius: 8px; margin-bottom: 16px; font-size: 13px; text-align: center; border: 1px solid #a7f3d0; font-weight: 600; }
        
        /* Demo Box */
        .demo-box { margin-top: 20px; padding: 14px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; font-size: 12px; }
        .demo-title { font-weight: bold; color: #334155; margin-bottom: 8px; display: flex; align-items: center; justify-content: space-between; }
        .demo-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; }
        .demo-btn { background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; padding: 6px 10px; text-align: left; cursor: pointer; transition: all .15s; font-size: 11px; }
        .demo-btn:hover { background: #e0f2fe; border-color: #38bdf8; }
        .demo-btn strong { display: block; color: #0f172a; font-size: 11px; }
        .demo-btn span { color: #64748b; font-size: 10px; }
        .sync-btn { display: block; width: 100%; text-align: center; background: #fef08a; color: #854d0e; border: 1px solid #fde047; padding: 8px; border-radius: 6px; font-weight: bold; font-size: 12px; text-decoration: none; margin-top: 12px; transition: background .2s; }
        .sync-btn:hover { background: #fde047; }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="logo-box">
            <div class="icon">🏥</div>
            <h1>SIMRS</h1>
            <p>RS PERMATA MEDIKA INDONESIA</p>
        </div>

        <?php if ($error !== ""): ?>
            <div class="error"><?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?></div>
        <?php endif; ?>

        <?php if ($success !== ""): ?>
            <div class="success"><?= htmlspecialchars($success, ENT_QUOTES, "UTF-8") ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" placeholder="Masukkan username" autocomplete="username" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Masukkan password" autocomplete="current-password" required>
            </div>
            <button type="submit" class="login-btn">Masuk ke Sistem &rarr;</button>
        </form>

        <!-- Daftar Akun Demo & 1-Click Login -->
        <div class="demo-box">
            <div class="demo-title">
                <span>🔑 Akun Demo (Klik untuk Auto-Fill)</span>
                <span style="font-size:10px; color:#17818f; font-weight:normal;">1-Click</span>
            </div>
            <div class="demo-grid">
                <button type="button" class="demo-btn" onclick="fillForm('doctor', 'PermataDoctor@2026')">
                    <strong>👨‍⚕️ dr. Dokter</strong>
                    <span>PermataDoctor@2026</span>
                </button>
                <button type="button" class="demo-btn" onclick="fillForm('triage_nurse', 'PermataTriage_nurse@2026')">
                    <strong>👩‍⚕️ Perawat Triase</strong>
                    <span>PermataTriage_nurse@2026</span>
                </button>
                <button type="button" class="demo-btn" onclick="fillForm('registration', 'PermataRegistration@2026')">
                    <strong>📋 Admisi/Poli</strong>
                    <span>PermataRegistration@2026</span>
                </button>
                <button type="button" class="demo-btn" onclick="fillForm('radiographer', 'PermataRadiographer@2026')">
                    <strong>📷 Radiografer</strong>
                    <span>PermataRadiographer@2026</span>
                </button>
                <button type="button" class="demo-btn" onclick="fillForm('radiology', 'PermataRadiology@2026')">
                    <strong>🩻 dr. Radiolog</strong>
                    <span>PermataRadiology@2026</span>
                </button>
                <button type="button" class="demo-btn" onclick="fillForm('admin', 'PermataAdmin@2026')">
                    <strong>⚙️ Admin SIMRS</strong>
                    <span>PermataAdmin@2026</span>
                </button>
            </div>

            <!-- Tombol 1-Click Sync Database -->
            <a href="login_baru.php?sync_passwords=1" class="sync-btn">
                ⚡ Klik di Sini untuk Sinkronkan Password ke Database
            </a>
        </div>
    </div>

    <script>
    function fillForm(user, pass) {
        document.getElementById('username').value = user;
        document.getElementById('password').value = pass;
    }
    </script>
</body>
</html>