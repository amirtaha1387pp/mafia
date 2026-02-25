<?php
require_once __DIR__ . '/config.php';
$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $phone = trim($_POST['phone']??'');
    $name = trim($_POST['display_name']??'');
    $password = $_POST['password']??'';
    if (!preg_match('/^\d{10,15}$/',$phone)) $error='شماره معتبر نیست.';
    elseif (mb_strlen($name)<3) $error='نام نمایشی حداقل ۳ کاراکتر.';
    elseif (strlen($password)<6) $error='رمز حداقل ۶ کاراکتر.';
    else {
        $stmt = db()->prepare('INSERT INTO users(phone,password_hash,display_name,created_at) VALUES(?,?,?,?)');
        try {
            $stmt->execute([$phone,password_hash($password,PASSWORD_DEFAULT),$name,now()]);
            header('Location: /login.php');exit;
        } catch(Throwable $e){$error='این شماره قبلا ثبت شده است.';}
    }
}
?><!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="stylesheet" href="/styles.css"><title>ثبت نام</title></head><body class="space-bg auth"><div class="card auth-card"><h1>ثبت نام</h1><?php if($error):?><div class="error"><?=htmlspecialchars($error)?></div><?php endif;?><form method="post"><label>نام نمایشی<input name="display_name" required></label><label>شماره موبایل<input name="phone" required></label><label>رمز عبور<input type="password" name="password" required></label><button>ساخت حساب</button></form><a href="/login.php">قبلا عضو شده‌ام</a></div></body></html>
