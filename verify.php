<?php
require_once __DIR__ . '/config.php';
$error='';
$otpInfo = $_SESSION['debug_otp'] ?? null;
if (empty($_SESSION['pending_uid'])) { header('Location: /login.php'); exit; }
$uid = (int)$_SESSION['pending_uid'];
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $code = trim($_POST['code'] ?? '');
    $stmt = db()->prepare('SELECT otp_code, otp_expires FROM users WHERE id=?');
    $stmt->execute([$uid]);
    $row = $stmt->fetch();
    if ($row && hash_equals((string)$row['otp_code'],$code) && strtotime($row['otp_expires']) >= time()) {
        $up = db()->prepare('UPDATE users SET is_verified=1, otp_code=NULL, otp_expires=NULL WHERE id=?');
        $up->execute([$uid]);
        $_SESSION['uid'] = $uid;
        unset($_SESSION['pending_uid'],$_SESSION['debug_otp']);
        header('Location: /dashboard.php');
        exit;
    }
    $error='کد نامعتبر یا منقضی شده است.';
}
?><!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="stylesheet" href="/styles.css"><title>تایید کد</title></head><body class="space-bg auth"><div class="card auth-card"><h1>تایید کد ورود</h1><p>کد چند رقمی ارسال‌شده را وارد کنید.</p><?php if($otpInfo): ?><div class="hint">کد آزمایشی محیط توسعه: <b><?=$otpInfo?></b></div><?php endif; ?><?php if($error):?><div class="error"><?=htmlspecialchars($error)?></div><?php endif;?><form method="post"><label>کد تایید<input name="code" required></label><button>ورود</button></form></div></body></html>
