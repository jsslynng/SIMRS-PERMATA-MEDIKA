<?php
session_start();
require_once __DIR__.'/config/database.php';
require_once __DIR__.'/includes/auth.php';
if (is_logged_in()) { header('Location: dashboard.php'); exit; }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['cancel'])) { $_POST = []; }
    else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $stmt = $pdo->prepare('SELECT u.id,u.username,u.full_name,u.password,r.name role_name,r.slug role_slug FROM users u JOIN roles r ON r.id=u.role_id WHERE u.username=? AND u.is_active=1 LIMIT 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password'])) {
            unset($user['password']);
            $_SESSION['user'] = $user;
            header('Location: dashboard.php'); exit;
        }
        $error = 'Username atau password tidak sesuai.';
    }
}
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Login - RS Permata Medika</title><link rel="stylesheet" href="assets/style.css"></head>
<body class="login-page">
<div class="login-wrap">
  <section class="login-brand"><div class="hospital-icon">+</div><h1>RS PERMATA MEDIKA</h1><p>Melayani dengan hati, sehat bersama kami</p><div class="login-note">SIMRS Basic Prototype<br><small>Week 7 - Authentication & RBAC</small></div></section>
  <section class="login-card"><h2>Welcome Back</h2><p>Silakan masuk menggunakan akun sesuai role.</p>
    <?php if($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="post">
      <label>Username</label><input name="username" required placeholder="Masukkan username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
      <label>Password</label><input type="password" name="password" required placeholder="Masukkan password">
      <div class="form-actions"><button class="btn" type="submit">Submit</button><button class="btn secondary" name="cancel" value="1" type="submit" formnovalidate>Cancel</button></div>
    </form>
    <div class="demo-accounts"><strong>Akun Demo</strong><span>management / password123</span><span>outpatient / password123</span><span>nurse / password123</span></div>
  </section>
</div></body></html>
