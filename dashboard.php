<?php
require_once __DIR__ . '/auth.php';
$user = require_login();
$scenarios = db()->query('SELECT * FROM scenarios ORDER BY id')->fetchAll();
$avatars = db()->query('SELECT * FROM avatars ORDER BY id DESC')->fetchAll();
$shop = db()->query('SELECT * FROM shop_items ORDER BY id DESC')->fetchAll();
$rolesCount = db()->query('SELECT COUNT(*) c FROM roles')->fetch()['c'];
?><!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>داشبورد مافیا کینگ</title>
<link rel="manifest" href="/manifest.json"><link rel="stylesheet" href="/styles.css">
</head>
<body class="space-bg app" data-user-id="<?=$user['id']?>" data-role="<?=htmlspecialchars($user['role'])?>">
<header class="topbar">
  <h1>🚀 مافیا کینگ</h1>
  <div>کاربر: <b><?=htmlspecialchars($user['display_name'])?></b> | نقش: <?=role_label($user['role'])?> | 💰 <?=$user['coins']?> | 💎 <?=$user['gems']?></div>
  <nav><button data-tab="game">بازی زنده</button><button data-tab="shop">فروشگاه</button><button data-tab="profile">پروفایل</button><?php if(can_access_admin($user)):?><button data-tab="admin">پنل ادمین</button><?php endif;?><a class="btn" href="/logout.php">خروج</a></nav>
</header>

<main>
<section id="tab-game" class="tab active card">
  <h2>لابی و بازی صوتی ۳۰ نفره</h2>
  <div class="grid2">
    <form id="createLobbyForm">
      <h3>ساخت لابی</h3>
      <label>نام لابی<input name="name" required></label>
      <label>نوع<select name="type"><option value="friendly">دوستانه</option><option value="ranked">امتیازی</option></select></label>
      <label>سناریو<select name="scenario_id"><?php foreach($scenarios as $s):?><option value="<?=$s['id']?>"><?=htmlspecialchars($s['name'])?></option><?php endforeach;?></select></label>
      <label>رمز خصوصی<input name="password" placeholder="اختیاری"></label>
      <label>رده سنی<select name="age_group"><option>13+</option><option selected>18+</option><option>21+</option></select></label>
      <button>ایجاد لابی</button>
    </form>
    <div>
      <h3>تنظیمات زمان‌بندی پیش‌فرض</h3>
      <ul>
        <li>معارفه هر نفر ۳۰ ثانیه، از روز دوم ۴۰ ثانیه</li><li>بین هر نفر ۵ ثانیه تنفس</li><li>کیس: ۲۰۰ ثانیه</li><li>هر ابیلیتی شب ۳۰ ثانیه + ۵ ثانیه تنفس</li><li>ماسون/تایلر ۲۵ ثانیه + مکالمه ۳۰ ثانیه</li><li>چالش ۳۰ ثانیه، دفاعیه ۳۵ ثانیه</li>
      </ul>
      <p>سناریوها: <?=count($scenarios)?> | نقش‌ها: <?=$rolesCount?> (قابل افزودن نقش جدید).</p>
    </div>
  </div>
  <div class="lobby-list-wrap"><h3>لیست لابی‌ها</h3><div id="lobbyList"></div></div>
  <div id="roomPanel" class="hidden">
    <h3>اتاق فعال <span id="roomName"></span></h3>
    <div class="voice-controls"><button id="joinVoice">ورود صوتی</button><button id="muteMe">بی‌صدا/وصل</button><button id="likeBtn">👍</button><button id="dislikeBtn">👎</button><button id="challengeBtn">🎯 چالش +1</button></div>
    <div class="speaker-stage" id="speakerStage">🎤 بازیکن سخنگو اینجا نمایش داده می‌شود</div>
    <div class="chat-wrap"><div id="chatBox"></div><form id="chatForm"><input name="message" placeholder="پیام..." required><button>ارسال</button></form></div>
  </div>
</section>

<section id="tab-shop" class="tab card">
  <h2>فروشگاه (آواتار، ایموجی، VIP)</h2>
  <div class="avatar-grid"><?php foreach($avatars as $a):?><article><img src="<?=htmlspecialchars($a['image_url'])?>" alt="avatar"><h4><?=htmlspecialchars($a['name'])?></h4><p>💎<?=$a['price_gem']?> | 💰<?=$a['price_coin']?></p><button data-buy-avatar="<?=$a['id']?>">خرید آواتار</button></article><?php endforeach;?></div>
  <h3>آیتم‌ها</h3><div class="item-grid"><?php foreach($shop as $it):?><article><h4><?=htmlspecialchars($it['title'])?></h4><p>💎<?=$it['price_gem']?> | 💰<?=$it['price_coin']?></p><button data-buy-item="<?=$it['id']?>">خرید</button></article><?php endforeach;?></div>
  <h3>درخواست خرید جم با رسید</h3>
  <form id="receiptForm"><label>مبلغ (ریال)<input type="number" name="amount_rial" required></label><label>تعداد جم درخواستی<input type="number" name="requested_gems" required></label><label>لینک رسید<input name="receipt_path" required></label><button>ثبت درخواست</button></form>
</section>

<section id="tab-profile" class="tab card">
  <h2>پروفایل و تنظیمات ۴ زبانه</h2>
  <form id="profileForm">
    <label>نام نمایشی<input name="display_name" value="<?=htmlspecialchars($user['display_name'])?>"></label>
    <label>اینستاگرام<input name="instagram" value="<?=htmlspecialchars($user['instagram'] ?? '')?>"></label>
    <label>روبیکا<input name="rubika" value="<?=htmlspecialchars($user['rubika'] ?? '')?>"></label>
    <label>زبان<select name="lang"><option value="fa">فارسی</option><option value="ar">العربية</option><option value="en">English</option><option value="zh">中文</option></select></label>
    <button>ذخیره</button>
  </form>
  <p>تم بازی فضایی، آواتار روی سفینه، ستاره چشمک‌زن، حالت شب تاریک فعال است.</p>
</section>

<?php if(can_access_admin($user)):?>
<section id="tab-admin" class="tab card">
  <h2>پنل ادمین پیشرفته</h2>
  <div class="grid2">
    <form id="coinsForm"><h3>افزایش سکه/جم</h3><label>شناسه کاربر<input name="user_id" required></label><label>سکه<input name="coins" value="0"></label><label>جم<input name="gems" value="0"></label><button>اعمال</button></form>
    <form id="roleForm"><h3>تغییر نقش کاربر</h3><label>شناسه کاربر<input name="user_id" required></label><label>نقش<select name="role"><option value="player">بازیکن</option><option value="admin">ادمین</option><option value="senior_admin">ادمین ارشد</option><?php if($user['role']==='owner'):?><option value="owner">مالک</option><?php endif;?></select></label><button>ثبت</button></form>
  </div>
  <form id="avatarAdminForm"><h3>افزودن آواتار جدید</h3><label>نام<input name="name" required></label><label>قیمت جم<input name="price_gem" required></label><label>قیمت سکه<input name="price_coin" required></label><label>تصویر<input name="image_url" required></label><button>افزودن</button></form>
  <form id="scenarioForm"><h3>ساخت سناریو/نقش جدید</h3><label>نام سناریو<input name="name" required></label><label>شرح<input name="description" required></label><label>نقش جدید<input name="role_name" required></label><label>سمت<select name="side"><option>mafia</option><option>citizen</option><option>independent</option></select></label><button>ثبت</button></form>
  <h3>گزارش تخلف‌ها</h3><div id="reportList"></div>
</section>
<?php endif;?>
</main>

<div id="toast"></div>
<script>window.APP_BOOT = { userId: <?=$user['id']?>, role: "<?=htmlspecialchars($user['role'])?>" };</script>
<script src="/app.js"></script>
</body></html>
