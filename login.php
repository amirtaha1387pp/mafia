<?php
require_once __DIR__ . '/config.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = db()->prepare('SELECT * FROM users WHERE phone=?');
    $stmt->execute([$phone]);
    $u = $stmt->fetch();

    if ($u && password_verify($password, $u['password_hash'])) {
        $otp = (string)random_int(100000, 999999);
        $expires = (new DateTime('+5 minutes'))->format('Y-m-d H:i:s');
        $up = db()->prepare('UPDATE users SET otp_code=?, otp_expires=? WHERE id=?');
        $up->execute([$otp, $expires, $u['id']]);
        $_SESSION['pending_uid'] = $u['id'];
        $_SESSION['debug_otp'] = $otp;
        header('Location: /verify.php');
        exit;
    }
    $error = 'شماره یا رمز اشتباه است.';
}
?><!doctype html>
<html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="/styles.css"><title>ورود - مافیا کینگ</title></head>
<body class="space-bg auth">
<div class="card auth-card"><h1>ورود به مافیا کینگ</h1>
<p>برای ورود، ساخت حساب و تایید کد اجباری است.</p>
<?php if($error): ?><div class="error"><?=htmlspecialchars($error)?></div><?php endif; ?>
<form method="post">
<label>شماره موبایل <input name="phone" required></label>
<label>رمز عبور <input type="password" name="password" required></label>
<button>دریافت کد ورود</button>
</form>
<a href="/register.php">حساب ندارم</a>
</div></body></html>
