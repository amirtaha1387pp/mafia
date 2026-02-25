<?php
session_start();

define('DB_PATH', __DIR__ . '/mafia_king.sqlite');

define('APP_NAME', 'مافیا کینگ');

define('VOICE_SIGNAL_TTL_SECONDS', 120);

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }
    return $pdo;
}

function now(): string {
    return (new DateTime('now', new DateTimeZone('Asia/Tehran')))->format('Y-m-d H:i:s');
}

function init_db(): void {
    $pdo = db();
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        phone TEXT UNIQUE NOT NULL,
        password_hash TEXT NOT NULL,
        display_name TEXT NOT NULL,
        role TEXT NOT NULL DEFAULT 'player',
        coins INTEGER NOT NULL DEFAULT 1000,
        gems INTEGER NOT NULL DEFAULT 100,
        avatar_id INTEGER,
        vip_until TEXT,
        is_verified INTEGER NOT NULL DEFAULT 0,
        otp_code TEXT,
        otp_expires TEXT,
        age_group TEXT DEFAULT '18+',
        instagram TEXT,
        rubika TEXT,
        created_at TEXT NOT NULL
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS avatars (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        price_gem INTEGER NOT NULL,
        price_coin INTEGER NOT NULL,
        image_url TEXT NOT NULL,
        rarity TEXT NOT NULL DEFAULT 'normal',
        animated INTEGER NOT NULL DEFAULT 0
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS user_avatars (
        user_id INTEGER NOT NULL,
        avatar_id INTEGER NOT NULL,
        PRIMARY KEY (user_id, avatar_id)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS scenarios (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT UNIQUE NOT NULL,
        description TEXT NOT NULL,
        custom_roles_json TEXT NOT NULL DEFAULT '[]'
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS roles (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        scenario_id INTEGER,
        name TEXT NOT NULL,
        side TEXT NOT NULL,
        power_description TEXT NOT NULL
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS lobbies (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        type TEXT NOT NULL,
        scenario_id INTEGER NOT NULL,
        password TEXT,
        max_players INTEGER NOT NULL DEFAULT 30,
        owner_id INTEGER,
        age_group TEXT DEFAULT '18+',
        is_ranked INTEGER NOT NULL DEFAULT 0,
        status TEXT NOT NULL DEFAULT 'waiting',
        entry_coin INTEGER NOT NULL DEFAULT 0,
        entry_gem INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS lobby_members (
        lobby_id INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        is_observer INTEGER NOT NULL DEFAULT 0,
        is_muted INTEGER NOT NULL DEFAULT 0,
        is_kicked INTEGER NOT NULL DEFAULT 0,
        likes INTEGER NOT NULL DEFAULT 0,
        dislikes INTEGER NOT NULL DEFAULT 0,
        extra_challenges INTEGER NOT NULL DEFAULT 0,
        PRIMARY KEY (lobby_id, user_id)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS lobby_chat (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        lobby_id INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        message TEXT NOT NULL,
        created_at TEXT NOT NULL
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS reports (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        reporter_id INTEGER NOT NULL,
        against_user_id INTEGER NOT NULL,
        reason TEXT NOT NULL,
        details TEXT,
        status TEXT NOT NULL DEFAULT 'open',
        created_at TEXT NOT NULL
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS gem_receipts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        amount_rial INTEGER NOT NULL,
        requested_gems INTEGER NOT NULL,
        receipt_path TEXT,
        status TEXT NOT NULL DEFAULT 'pending',
        created_at TEXT NOT NULL
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS voice_signals (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        lobby_id INTEGER NOT NULL,
        from_user INTEGER NOT NULL,
        to_user INTEGER NOT NULL,
        signal_type TEXT NOT NULL,
        payload TEXT NOT NULL,
        created_at TEXT NOT NULL
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS shop_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        type TEXT NOT NULL,
        title TEXT NOT NULL,
        price_gem INTEGER NOT NULL DEFAULT 0,
        price_coin INTEGER NOT NULL DEFAULT 0,
        metadata_json TEXT NOT NULL DEFAULT '{}'
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        key TEXT PRIMARY KEY,
        value TEXT NOT NULL
    )");

    seed_defaults($pdo);
}

function seed_defaults(PDO $pdo): void {
    $scenarioNames = [
        'کلاسیک پیشرفته','تفنگدار','شهروندان متحد','ناتو','مذاکره','زودیاک','جک گنجشکه','کهکشانی','تورنمنت حرفه‌ای','فصل شکارچیان','سفینه مرگ'
    ];
    foreach ($scenarioNames as $name) {
        $stmt = $pdo->prepare('INSERT OR IGNORE INTO scenarios(name, description) VALUES(?, ?)');
        $stmt->execute([$name, "سناریوی {$name} با پشتیبانی از نقش سفارشی"]);
    }

    $countRoles = (int)$pdo->query('SELECT COUNT(*) c FROM roles')->fetch()['c'];
    if ($countRoles < 70) {
        $pdo->exec('DELETE FROM roles');
        $scIds = $pdo->query('SELECT id FROM scenarios')->fetchAll(PDO::FETCH_COLUMN);
        $sides = ['mafia','citizen','independent'];
        for ($i=1; $i<=75; $i++) {
            $stmt = $pdo->prepare('INSERT INTO roles(scenario_id, name, side, power_description) VALUES(?, ?, ?, ?)');
            $sc = $scIds[array_rand($scIds)];
            $side = $sides[array_rand($sides)];
            $stmt->execute([$sc, "نقش {$i}", $side, "قدرت ویژه شماره {$i} با زمان‌بندی شب/روز"]);
        }
    }

    $avatarCount = (int)$pdo->query('SELECT COUNT(*) c FROM avatars')->fetch()['c'];
    if ($avatarCount === 0) {
        $seedAvatars = [
            ['کاپیتان نوا', 50, 500, 'https://picsum.photos/seed/a1/200/200', 'rare', 1],
            ['مافیا استار', 120, 1200, 'https://picsum.photos/seed/a2/200/200', 'epic', 1],
            ['شهروند مدار', 20, 200, 'https://picsum.photos/seed/a3/200/200', 'normal', 0],
        ];
        $stmt = $pdo->prepare('INSERT INTO avatars(name, price_gem, price_coin, image_url, rarity, animated) VALUES(?,?,?,?,?,?)');
        foreach ($seedAvatars as $av) { $stmt->execute($av); }
    }

    $itemCount = (int)$pdo->query('SELECT COUNT(*) c FROM shop_items')->fetch()['c'];
    if ($itemCount === 0) {
        $stmt = $pdo->prepare('INSERT INTO shop_items(type, title, price_gem, price_coin, metadata_json) VALUES(?,?,?,?,?)');
        $stmt->execute(['emoji','استیکر صوتی کهکشانی',25,250,'{"sound":"beep"}']);
        $stmt->execute(['booster','چالش اضافه +1',15,150,'{"extraChallenge":1}']);
        $stmt->execute(['vip','VIP یک‌ماهه',300,3000,'{"days":30}']);
    }

    $defaults = [
        'coin_to_gem_rate' => '100:10',
        'ranked_entry_coin' => '0',
        'friendly_entry_coin' => '0',
        'dark_mode_night' => '1'
    ];
    $stmt = $pdo->prepare('INSERT OR IGNORE INTO settings(key, value) VALUES(?,?)');
    foreach ($defaults as $k=>$v) { $stmt->execute([$k,$v]); }
}

init_db();
