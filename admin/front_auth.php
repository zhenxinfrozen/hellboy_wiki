<?php
/**
 * 前台（index.php）轻量鉴权读取。
 * 与 admin/bootstrap.php 共用同一会话名与配置，但不做重定向/输出，
 * 仅回答「当前访问者是否为已登录管理员」，用于顶栏入口与条目内联编辑按钮。
 */
declare(strict_types=1);

if (!function_exists('hb_admin_cfg')) {
    function hb_admin_cfg(): array {
        static $cfg = null;
        if ($cfg === null) {
            $local = __DIR__ . '/config.local.php';
            $c = is_file($local) ? (require $local) : [];
            if (!is_array($c)) $c = [];
            $cfg = $c + [
                'admin_user'    => '',
                'password_hash' => '',
                'session_name'  => 'HBADMIN_SID',
                'login_ttl'     => 604800,
            ];
        }
        return $cfg;
    }
}

if (!function_exists('hb_admin')) {
    /**
     * 仅当请求携带管理员会话 cookie 时才开启会话（避免给匿名访客创建会话文件）。
     * 返回 true 表示已登录且未过期。
     */
    function hb_admin(): bool {
        static $resolved = null;
        if ($resolved !== null) return $resolved;
        $cfg = hb_admin_cfg();
        $name = (string)$cfg['session_name'];
        if (empty($_COOKIE[$name])) { return $resolved = false; }
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_name($name);
            $https = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
                || strcasecmp((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''), 'https') === 0
                || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;
            session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'path' => '/', 'secure' => $https]);
            @session_start();
        }
        $ok = !empty($_SESSION['admin_ok'])
            && (($_SESSION['admin_at'] ?? 0) + (int)$cfg['login_ttl']) > time();
        return $resolved = (bool)$ok;
    }
}
