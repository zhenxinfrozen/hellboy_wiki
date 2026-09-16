<?php
require_once __DIR__ . '/bootstrap.php';
admin_expire_legacy_cookies();   // 同时作废旧 path=/admin 同名 cookie
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();

// 安全回跳：仅允许站内相对路径；默认回登录页
$goto = trim((string)($_GET['goto'] ?? ''));
if ($goto !== '' && preg_match('#^/[A-Za-z0-9_\-/.?=&%()\x{4e00}-\x{9fa5}]+$#u', $goto)
    && !preg_match('#^//|:/|javascript:#i', $goto)) {
    header('Location: ' . $goto);
} else {
    header('Location: login.php');
}
