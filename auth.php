<?php
require_once __DIR__ . '/config.php';

function current_user(): ?array {
    if (empty($_SESSION['uid'])) return null;
    $stmt = db()->prepare('SELECT * FROM users WHERE id=?');
    $stmt->execute([$_SESSION['uid']]);
    $u = $stmt->fetch();
    return $u ?: null;
}

function require_login(): array {
    $u = current_user();
    if (!$u || (int)$u['is_verified'] !== 1) {
        header('Location: /login.php');
        exit;
    }
    return $u;
}

function require_role(array $roles): array {
    $u = require_login();
    if (!in_array($u['role'], $roles, true)) {
        http_response_code(403);
        echo 'عدم دسترسی';
        exit;
    }
    return $u;
}

function can_access_admin(array $u): bool {
    return in_array($u['role'], ['admin','senior_admin','owner'], true);
}

function role_label(string $r): string {
    return [
        'player'=>'بازیکن',
        'admin'=>'ادمین',
        'senior_admin'=>'ادمین ارشد',
        'owner'=>'مالک'
    ][$r] ?? $r;
}
