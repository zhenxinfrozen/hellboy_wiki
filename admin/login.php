<?php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/ui.php';

/* 每次打开登录页：作废旧 path=/admin 同名 cookie（双 cookie 会导致登录后被踢），
   未登录时顺带换发全新的 path=/ 会话，保证点一次登录即成功，无需手动清 cookie。 */
admin_login_page_refresh();

if (admin_logged_in()) {
    $goto = admin_safe_goto($_GET['goto'] ?? '') ?: 'index.php';
    header('Location: ' . $goto); exit;
}

// 仅允许站内相对路径回跳（/admin/ 或前台 / 开头），拒绝协议/外域
function admin_safe_goto($g): string {
    $g = trim((string)$g);
    if ($g === '' || !preg_match('#^/[A-Za-z0-9_\-/.?=&%()\x{4e00}-\x{9fa5}]+$#u', $g)) return '';
    if (preg_match('#^//|:/|javascript:#i', $g)) return '';
    return $g;
}

$err = '';
$needUser = ((string)($GLOBALS['ADMIN_CFG']['admin_user'] ?? '')) !== '';

// 登录页先检查是否处于锁定窗口
$lockLeft = admin_login_locked_remaining();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if ($lockLeft > 0) {
        $err = '失败次数过多，请 ' . ceil($lockLeft / 60) . ' 分钟后再试。';
    } elseif (!hash_equals(admin_csrf_token(), (string)($_POST['csrf'] ?? ''))) {
        $err = '会话已过期，请重试。';
    } else {
        $hash = (string)$GLOBALS['ADMIN_CFG']['password_hash'];
        $user = trim((string)($_POST['username'] ?? ''));
        $pw = (string)($_POST['password'] ?? '');
        if ($hash === '') {
            $err = '系统尚未配置登录凭据，请联系管理员在服务器设置 config.local.php。';
        } elseif (admin_verify_credentials($user, $pw)) {
            admin_clear_login_attempts();
            session_regenerate_id(true);
            $_SESSION['admin_ok'] = true;
            $_SESSION['admin_at'] = time();
            $_SESSION['admin_user'] = $user;
            $goto = admin_safe_goto($_POST['goto'] ?? '') ?: 'index.php';
            // 站内前台路径以 / 开头直接跳，后台相对路径原样跳
            header('Location: ' . $goto); exit;
        } else {
            admin_record_failed_login();
            $lockLeft = admin_login_locked_remaining();
            // 统一提示，不区分账户错误还是密码错误（防用户名枚举）
            $err = $lockLeft > 0
                ? '失败次数过多，请 ' . ceil($lockLeft / 60) . ' 分钟后再试。'
                : '账户或密码不正确。';
        }
    }
}
?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>管理员登录 · 地狱男爵维基</title>
<link rel="icon" href="/favicon.ico?v=2" sizes="any">
<style>
:root{
  --red:#c11f1f; --red-hi:#e03a2e; --red-deep:#7d1212;
  --ink:#0d0b0a; --panel:#171312; --panel-2:#1f1917;
  --line:#332925; --txt:#f3e9e2; --mut:#9a8578;
}
*{box-sizing:border-box}
html,body{height:100%}
body{
  margin:0;
  font:15px/1.6 -apple-system,BlinkMacSystemFont,"PingFang SC","Microsoft YaHei","Segoe UI",sans-serif;
  color:var(--txt);
  background:
    radial-gradient(1100px 600px at 50% -8%, rgba(193,31,31,.22), transparent 60%),
    radial-gradient(900px 700px at 50% 118%, rgba(120,18,18,.28), transparent 62%),
    var(--ink);
  min-height:100%;
  display:flex; align-items:center; justify-content:center;
  padding:32px 18px;
  -webkit-font-smoothing:antialiased;
}
/* 细颗粒做旧质感 */
body::before{
  content:""; position:fixed; inset:0; pointer-events:none; opacity:.05;
  background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='120' height='120'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.9' numOctaves='2'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
}
.card{
  position:relative; width:100%; max-width:392px;
  background:linear-gradient(180deg,var(--panel-2),var(--panel) 40%);
  border:1px solid var(--line);
  border-radius:20px;
  padding:38px 34px 32px;
  box-shadow:0 30px 80px -20px rgba(0,0,0,.85), 0 0 0 1px rgba(0,0,0,.4), inset 0 1px 0 rgba(255,255,255,.04);
}
/* 顶部红色描边光 */
.card::before{
  content:""; position:absolute; top:-1px; left:22px; right:22px; height:2px;
  background:linear-gradient(90deg,transparent,var(--red-hi),transparent);
  border-radius:2px;
}
.badge{
  width:92px; height:92px; margin:-84px auto 14px; position:relative;
  border-radius:50%; padding:4px;
  background:conic-gradient(from 210deg,var(--red-deep),var(--red-hi),var(--red),var(--red-deep));
  box-shadow:0 10px 30px -6px rgba(193,31,31,.55), inset 0 0 0 1px rgba(0,0,0,.5);
}
.badge img{
  width:100%; height:100%; border-radius:50%; display:block; object-fit:cover;
  border:3px solid #141010; background:#3a2620;
}
.brand{text-align:center}
.brand h1{
  margin:6px 0 2px; font-size:21px; font-weight:800; letter-spacing:.5px;
  color:#fff;
}
.brand h1 .b{color:var(--red-hi)}
.brand p{margin:0 0 24px; font-size:12.5px; letter-spacing:2px; color:var(--mut); text-transform:uppercase}
.field{margin-bottom:16px}
.field label{
  display:block; font-size:12.5px; font-weight:600; letter-spacing:1px;
  color:var(--mut); margin:0 0 7px 2px;
}
.ctl{position:relative}
.ctl .ico{
  position:absolute; left:13px; top:50%; transform:translateY(-50%);
  width:17px; height:17px; opacity:.55; color:var(--mut); pointer-events:none;
}
.field input{
  width:100%; font:inherit; font-size:15px; color:var(--txt);
  background:#100d0c;
  border:1px solid var(--line);
  border-radius:11px;
  padding:13px 14px 13px 42px;
  outline:none; transition:border-color .15s, box-shadow .15s, background .15s;
}
.field input::placeholder{color:#6b5d54}
.field input:focus{
  border-color:rgba(224,58,46,.75);
  background:#141010;
  box-shadow:0 0 0 3px rgba(193,31,31,.22);
}
.btn{
  margin-top:6px; width:100%; border:0; cursor:pointer;
  font:inherit; font-size:15.5px; font-weight:700; letter-spacing:4px; text-indent:4px;
  color:#fff; padding:14px;
  border-radius:11px;
  background:linear-gradient(180deg,var(--red-hi),var(--red) 45%,var(--red-deep));
  box-shadow:0 10px 24px -8px rgba(193,31,31,.7), inset 0 1px 0 rgba(255,255,255,.22);
  transition:transform .08s, filter .15s, box-shadow .15s;
}
.btn:hover{filter:brightness(1.08)}
.btn:active{transform:translateY(1px); box-shadow:0 5px 14px -6px rgba(193,31,31,.7)}
.err{
  display:flex; gap:9px; align-items:flex-start;
  background:rgba(120,18,18,.22);
  border:1px solid rgba(224,58,46,.45);
  color:#ffc9c2;
  padding:11px 13px; border-radius:11px; font-size:13px; line-height:1.5;
  margin-bottom:18px;
}
.err .dot{flex:0 0 auto; width:18px;height:18px;border-radius:50%;
  background:var(--red);color:#fff;font-size:12px;font-weight:700;
  display:flex;align-items:center;justify-content:center;margin-top:1px}
.err code{background:rgba(0,0,0,.35);padding:1px 5px;border-radius:5px;font-size:12px}
.foot{margin-top:22px;text-align:center;font-size:11.5px;letter-spacing:1px;color:#6b5d54}
.foot a{color:var(--mut);text-decoration:none}
.foot a:hover{color:var(--red-hi)}
@media (max-width:420px){ .card{padding:34px 22px 26px} }
</style>
</head>
<body>
<form class="card" method="post" autocomplete="on">
  <div class="badge"><img src="/assets/img/logo-192.png?v=2" alt="Hellboy" width="92" height="92"></div>
  <div class="brand">
    <h1>地狱男爵维基 <span class="b">·</span> 管理后台</h1>
    <p>B.P.R.D. Restricted Access</p>
  </div>

  <?php if ($GLOBALS['ADMIN_CFG']['password_hash']===''): ?>
    <div class="err"><span class="dot">!</span><span>系统尚未配置登录凭据，请联系管理员在服务器创建 <code>admin/config.local.php</code>。</span></div>
  <?php endif; ?>
  <?php if ($err): ?><div class="err" role="alert"><span class="dot">!</span><span><?= h($err) ?></span></div><?php endif; ?>

  <input type="hidden" name="csrf" value="<?= h(admin_csrf_token()) ?>">
  <input type="hidden" name="goto" value="<?= h(admin_safe_goto($_GET['goto'] ?? '') ?: '') ?>">

  <?php if ($needUser): ?>
  <div class="field">
    <label for="u">账户</label>
    <div class="ctl">
      <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-6.5 8-6.5s8 2.5 8 6.5"/></svg>
      <input id="u" type="text" name="username" autocomplete="username" placeholder="请输入账户" autofocus required>
    </div>
  </div>
  <div class="field">
    <label for="p">密码</label>
    <div class="ctl">
      <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
      <input id="p" type="password" name="password" autocomplete="current-password" placeholder="请输入密码" required>
    </div>
  </div>
  <?php else: ?>
  <div class="field">
    <label for="p">密码</label>
    <div class="ctl">
      <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
      <input id="p" type="password" name="password" autocomplete="current-password" placeholder="请输入密码" autofocus required>
    </div>
  </div>
  <?php endif; ?>

  <button class="btn" type="submit">登 录</button>
  <div class="foot"><a href="/">← 返回地狱男爵维基</a></div>
</form>
</body>
</html>
