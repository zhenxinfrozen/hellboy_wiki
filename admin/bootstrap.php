<?php
/**
 * 后台引导：路径、配置、登录态、通用工具。
 * 所有 admin/*.php 顶部 require __DIR__.'/bootstrap.php';
 */
declare(strict_types=1);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);

define('ADMIN_DIR', __DIR__);
define('ROOT', dirname(ADMIN_DIR));
define('CONTENT', ROOT . '/content');
define('PAGES', CONTENT . '/pages');
define('PAGES_MT', CONTENT . '/pages_mt');
define('PAGES_EN', CONTENT . '/pages_en');
define('META', CONTENT . '/meta');
define('IMAGES_DIR', CONTENT . '/images');
define('IMG_URL', '/content/images');   // 前台图片 URL 前缀
define('SLUGS_FILE', CONTENT . '/slugs.json');
define('EN_TITLE_FILE', CONTENT . '/en_title.json');
define('EN_REDIRECT_FILE', CONTENT . '/en_redirects.json');
define('ALIASES_FILE', CONTENT . '/aliases.json');
define('GLOSSARY_FILE', CONTENT . '/glossary.json');

// ---- 配置（本地私有，不随内容回灌）----
$cfgFile = ADMIN_DIR . '/config.local.php';
$ADMIN_CFG = is_file($cfgFile) ? (require $cfgFile) : (require ADMIN_DIR . '/config.example.php');
if (!is_array($ADMIN_CFG)) $ADMIN_CFG = [];
$ADMIN_CFG += ['admin_user'=>'', 'password_hash'=>'', 'session_name'=>'HBADMIN_SID', 'login_ttl'=>604800,
               'login_max_attempts'=>8, 'login_lock_seconds'=>900,
               'backup_dir'=>ADMIN_DIR.'/backups', 'backup_keep'=>40];

define('BACKUP_DIR', $ADMIN_CFG['backup_dir']);

// ---- 会话 / 登录 ----
function admin_is_https(): bool {
    if (($_SERVER['HTTPS'] ?? '') && strtolower((string)$_SERVER['HTTPS']) !== 'off') return true;
    if (strcasecmp((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''), 'https') === 0) return true;
    if ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443) return true;
    return false;
}
function admin_cookie_params(): array {
    return ['httponly'=>true, 'samesite'=>'Lax', 'path'=>'/', 'secure'=>admin_is_https()];
}
function admin_session_setup(array $cfg): void {
    session_name($cfg['session_name']);
    // cookie 作用域设为站点根 /，使前台 index.php 能识别后台登录态（顶栏入口/条目内联编辑）
    // HTTPS 下自动加 Secure，仅经加密通道回传。
    session_set_cookie_params(admin_cookie_params());
    session_start();
}
admin_session_setup($ADMIN_CFG);

/* 清理 2026-09-15 之前版本遗留在 path=/admin 的同名 cookie：
   它与现今 path=/ 的同名 cookie 共存时，浏览器会按路径长度优先发送旧的 /admin 那条，
   PHP 只取首个值，导致刚登录成功又被当成未登录踢回登录页。
   在登录/登出页显式让旧路径 cookie 过期，即可消除双 cookie。 */
function admin_expire_legacy_cookies(): void {
    if (headers_sent()) return;
    $name = (string)($GLOBALS['ADMIN_CFG']['session_name'] ?? 'HBADMIN_SID');
    setcookie($name, '', ['expires' => 1, 'path' => '/admin', 'httponly' => true, 'samesite' => 'Lax']);
}

function admin_logged_in(): bool {
    return !empty($_SESSION['admin_ok']) && (($_SESSION['admin_at'] ?? 0) + ($GLOBALS['ADMIN_CFG']['login_ttl'] ?? 604800)) > time();
}
/* 登录页（GET）：作废旧 /admin cookie 后，把当前 CSRF 会话以 path=/ 重新下发，
   保证用户点一次登录即可成功，不需要手动清 cookie。 */
function admin_login_page_refresh(): void {
    if (!admin_logged_in()) {
        session_regenerate_id(true);   // 注意：session 模块下发的 Set-Cookie 会顶掉同名头，必须先 regenerate
    }
    admin_expire_legacy_cookies();     // 再下发 path=/admin 旧 cookie 的过期指令，两者才能共存
}
function admin_require_login(): void {
    if (!admin_logged_in()) {
        header('Location: login.php');
        exit;
    }
}
function admin_csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function admin_csrf_check(): void {
    if (!hash_equals(admin_csrf_token(), (string)($_POST['csrf'] ?? ''))) {
        http_response_code(419);
        exit('CSRF token 无效，请返回重试。');
    }
}

// ---- 登录失败限流（按来源 IP，落盘在系统临时目录，原子读写）----
function admin_client_ip(): string {
    // 不信任 XFF（可伪造）；站点无反代时 REMOTE_ADDR 即真实对端。
    return (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
}
function admin_attempt_file(): string {
    return rtrim(sys_get_temp_dir(), '/') . '/hb_admin_attempts_' . hash('sha256', admin_client_ip()) . '.json';
}
/** 剩余锁定秒数；0 表示当前可尝试 */
function admin_login_locked_remaining(): int {
    $max = (int)($GLOBALS['ADMIN_CFG']['login_max_attempts'] ?? 8);
    $lock = (int)($GLOBALS['ADMIN_CFG']['login_lock_seconds'] ?? 900);
    $f = admin_attempt_file();
    if (!is_file($f)) return 0;
    $d = json_decode((string)@file_get_contents($f), true);
    $count = (int)($d['count'] ?? 0);
    $first = (int)($d['first'] ?? 0);
    if ($count < $max) return 0;
    $elapsed = time() - $first;
    if ($elapsed >= $lock) { @unlink($f); return 0; }   // 锁定窗口已过，清零
    return $lock - $elapsed;
}
function admin_record_failed_login(): void {
    $lock = (int)($GLOBALS['ADMIN_CFG']['login_lock_seconds'] ?? 900);
    $f = admin_attempt_file();
    $now = time();
    $d = is_file($f) ? (json_decode((string)@file_get_contents($f), true) ?: []) : [];
    $count = (int)($d['count'] ?? 0);
    $first = (int)($d['first'] ?? 0);
    if ($first === 0 || ($now - $first) >= $lock) { $count = 0; $first = $now; } // 新窗口
    $count++;
    $tmp = $f . '.tmp-' . bin2hex(random_bytes(4));
    @file_put_contents($tmp, json_encode(['count'=>$count, 'first'=>$first, 'last'=>$now]));
    @rename($tmp, $f);
}
function admin_clear_login_attempts(): void { @unlink(admin_attempt_file()); }

/**
 * 账户名 + 密码校验。
 * - 未配置 admin_user 时退化为仅校验密码（向后兼容旧配置）。
 * - 统一返回布尔，错误提示不分账户/密码，避免用户名枚举。
 */
function admin_verify_credentials(string $user, string $pw): bool {
    $cfg = $GLOBALS['ADMIN_CFG'];
    $hash = (string)($cfg['password_hash'] ?? '');
    if ($hash === '') return false;
    $wantUser = (string)($cfg['admin_user'] ?? '');
    if ($wantUser !== '' && !hash_equals($wantUser, $user)) return false;
    return password_verify($pw, $hash);
}

// ---- 输出 / 编码 ----
function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function admin_flash(?string $msg = null, string $kind = 'ok'): ?array {
    if ($msg !== null) { $_SESSION['flash'] = ['m'=>$msg, 'k'=>$kind]; return null; }
    $f = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $f;
}

// ---- JSON 读写（带 .bak 风格备份，UTF-8 不转义）----
function admin_read_json(string $path): array {
    if (!is_file($path)) return [];
    $j = json_decode((string)file_get_contents($path), true);
    return is_array($j) ? $j : [];
}
function admin_write_json(string $path, array $data): bool {
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_LINE_TERMINATORS);
    return $json !== false && admin_save_with_backup($path, $json . "\n");
}
function admin_save_with_backup(string $path, string $content): bool {
    if (!is_dir(dirname($path))) @mkdir(dirname($path), 0775, true);
    if (is_file($path)) admin_backup($path);
    $tmp = $path . '.tmp-' . bin2hex(random_bytes(4));
    if (file_put_contents($tmp, $content) === false) return false;
    return rename($tmp, $path);
}
function admin_backup(string $path): void {
    if (!is_file($path)) return;
    @mkdir(BACKUP_DIR, 0775, true);
    $rel = str_replace(DIRECTORY_SEPARATOR, '__', ltrim(str_replace(ROOT, '', $path), DIRECTORY_SEPARATOR));
    $stamp = date('Ymd-His');
    $dest = BACKUP_DIR . '/' . $rel . '.' . $stamp . '.bak';
    @copy($path, $dest);
    admin_backup_gc($rel);
}
function admin_backup_gc(string $relPrefix): void {
    $keep = (int)($GLOBALS['ADMIN_CFG']['backup_keep'] ?? 40);
    $list = glob(BACKUP_DIR . '/' . $relPrefix . '.*.bak') ?: [];
    usort($list, fn($a,$b)=>filemtime($b)<=>filemtime($a));
    foreach (array_slice($list, $keep) as $old) @unlink($old);
}

// ---- 内容工具 ----
function admin_list_entries(): array {
    $files = glob(PAGES . '/*.md') ?: [];
    $out = [];
    foreach ($files as $f) {
        $title = basename($f, '.md');
        $out[] = [
            'title' => $title,
            'zh_mtime' => filemtime($f),
            'zh_size' => filesize($f),
            'has_en' => is_file(PAGES_EN . '/' . $title . '.md'),
            'en_mtime' => is_file(PAGES_EN . '/' . $title . '.md') ? filemtime(PAGES_EN . '/' . $title . '.md') : null,
            'en_size' => is_file(PAGES_EN . '/' . $title . '.md') ? filesize(PAGES_EN . '/' . $title . '.md') : null,
        ];
    }
    usort($out, fn($a,$b)=>mb_strtolower($a['title'])<=>mb_strtolower($b['title']));
    return $out;
}
function admin_safe_title(string $t): bool {
    $t = trim($t);
    if ($t === '' || in_array($t, ['.','..'], true)) return false;
    if (preg_match('#[\\\\/]#', $t)) return false;           // 禁止路径分隔
    if (str_contains($t, "\0")) return false;
    return true;
}

/**
 * 条目改名全联动：三层 md 文件 + meta 信息框 + 全部按标题索引的 JSON（键迁移 / 值改名）
 * + nav/portal 菜单 + 其它正文里指向旧名的入站内链，统一改成新标题对应的英文 permalink。
 * 同时在 en_redirects 保留 旧中文标题 → 新标题 的跳转，旧链接/书签不会 404。
 * 返回本次实际改动的明细数组，供调用方写入 flash 日志。
 */
function admin_rename_entry_everywhere(string $old, string $new): array {
    $log = [];
    if ($old === '' || $new === '' || $old === $new) return $log;

    // 1) 三层正文文件改名（存在才动，各自备份）
    foreach ([PAGES, PAGES_MT, PAGES_EN] as $dir) {
        $a = $dir . '/' . $old . '.md'; $b = $dir . '/' . $new . '.md';
        if (is_file($a) && !is_file($b)) { admin_backup($a); rename($a, $b); $log[] = 'rename md: ' . basename($dir); }
    }

    // 2) meta 信息框文件改名
    $ma = META . '/' . $old . '.json'; $mb = META . '/' . $new . '.json';
    if (is_file($ma) && !is_file($mb)) { admin_backup($ma); rename($ma, $mb); $log[] = 'rename meta'; }

    // 3) “键=本地标题”的三张表：整键迁移，保留其值
    foreach ([SLUGS_FILE, EN_TITLE_FILE] as $jf) {
        $d = admin_read_json($jf);
        if (array_key_exists($old, $d) && !array_key_exists($new, $d)) {
            $d[$new] = $d[$old]; unset($d[$old]);
            admin_write_json($jf, $d); $log[] = 'key migrate: ' . basename($jf);
        }
    }
    $trPath = CONTENT . '/translation.json';
    $tr = admin_read_json($trPath);
    if (isset($tr['entries']) && is_array($tr['entries']) && array_key_exists($old, $tr['entries']) && !array_key_exists($new, $tr['entries'])) {
        $tr['entries'][$new] = $tr['entries'][$old]; unset($tr['entries'][$old]);
        admin_write_json($trPath, $tr); $log[] = 'key migrate: translation.json';
    }

    // 4) 其余索引 JSON：把“字符串值 / 数组项”里精确等于旧名的地方改成新名（不动键、不误伤英文别名键）
    $valueFiles = [ALIASES_FILE, EN_REDIRECT_FILE, GLOSSARY_FILE,
                   CONTENT . '/nav.json', CONTENT . '/portal.json', CONTENT . '/portals.json',
                   $trPath, EN_TITLE_FILE, SLUGS_FILE];
    foreach (array_unique($valueFiles) as $jf) {
        if (!is_file($jf)) continue;
        $raw = (string)file_get_contents($jf);
        $d = json_decode($raw, true);
        if (!is_array($d)) continue;
        $changed = false;
        $walk = function (array &$node) use (&$walk, $old, $new, &$changed) {
            foreach ($node as $k => &$v) {
                if (is_string($v) && $v === $old) { $v = $new; $changed = true; }
                elseif (is_array($v)) $walk($v);
            }
            unset($v);
        };
        $walk($d);
        if ($changed) { admin_write_json($jf, $d); $log[] = 'value rename: ' . basename($jf); }
    }

    // 5) 保留旧中文标题 → 新标题 的跳转（放在值改名之后，避免被覆盖）
    $redir = admin_read_json(EN_REDIRECT_FILE);
    if (!isset($redir[$old]) || $redir[$old] !== $new) {
        $redir[$old] = $new; admin_write_json(EN_REDIRECT_FILE, $redir); $log[] = 'add legacy redirect';
    }

    // 6) 重写所有正文里指向旧名的 /entry/ 内链 → 新标题英文 permalink（只换 href，不动可见文字）
    //    仅扫描中文两层；pages_en 是冻结只读层，其链接由前台运行时 rewrite_entry_links 统一解析，绝不物理改写。
    $sl = admin_read_json(SLUGS_FILE);
    $enSlug = (string)($sl[$new] ?? $new);
    $enUrl = admin_slug_url($enSlug);
    $variants = array_unique(['/entry/' . rawurlencode($old),
                              '/entry/' . rawurlencode(str_replace(' ', '_', $old))]);
    $linkEdits = 0;
    foreach ([PAGES, PAGES_MT] as $scanDir) {
      if (!is_dir($scanDir)) continue;
      foreach (glob($scanDir . '/*.md') ?: [] as $p) {
        $src = (string)file_get_contents($p);
        $out = $src;
        foreach ($variants as $tok) {
            // 仅当后面紧跟 ) # ? 或空白时替换，避免误伤“旧名是更长标题前缀”的情况
            $pat = '~(' . preg_quote($tok, '~') . ')(?=[)#?\s])~u';
            $rep = @preg_replace($pat, '/entry/' . $enUrl, $out);
            if ($rep !== null) $out = $rep;
        }
        if ($out !== $src) { admin_backup($p); admin_save_with_backup($p, $out); $linkEdits++; }
      }
    }
    if ($linkEdits) $log[] = 'inbound links rewritten in ' . $linkEdits . ' md file(s) (zh layers only)';

    return $log;
}

/** 与前台 index.php 的 slug_url() 保持一致：空格→下划线，保留可读标点，其余百分号编码 */
function admin_slug_url(string $slug): string {
    $s = str_replace(' ', '_', trim($slug));
    $s = rawurlencode($s);
    return str_replace(['%28','%29','%3A','%27','%3B','%2F'], ['(',')',":","'",';','/'], $s);
}
