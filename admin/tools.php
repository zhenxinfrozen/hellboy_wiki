<?php
/**
 * 工具 · 追加代码
 * ------------------------------------------------------------------
 * 维护两个注入文件（修改即保存，自动备份到 admin/backups/）：
 *   content/code_head.html —— 注入前台每个页面的 <head> 尾部（适合
 *                             Google Analytics / 统计 / 验证 meta）
 *   content/code_body.html —— 注入前台每个页面的 </body> 之前（适合
 *                             自定义脚本 / 客服挂件 / 统计 body 片段）
 * 前台 index.php 通过 admin/front_auth.php 里的 hb_append_head_code() /
 * hb_append_body_code() 读取并原样输出；文件不存在或为空则不输出。
 * 注意：这里的内容会原样出现在所有访客页面中，属于“可信代码”区，
 * 只有后台管理员能改，请勿粘贴来路不明的脚本。
 */
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/ui.php';
admin_require_login();

$headFile = CONTENT . '/code_head.html';
$bodyFile = CONTENT . '/code_body.html';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    admin_csrf_check();
    $act = $_POST['act'] ?? '';
    if ($act === 'save') {
        $head = (string)($_POST['head'] ?? '');
        $body = (string)($_POST['body'] ?? '');
        $ok1 = admin_save_with_backup($headFile, $head);
        $ok2 = admin_save_with_backup($bodyFile, $body);
        admin_flash($ok1 && $ok2 ? '已保存并写入前台（刷新前台页面即可生效）。' : '保存失败：请检查 content/ 目录写入权限。', $ok1 && $ok2 ? 'ok' : 'err');
    } elseif ($act === 'clear') {
        $which = (string)($_POST['which'] ?? '');
        if ($which === 'head') { @unlink($headFile); admin_flash('已清空 <head> 注入代码。', 'ok'); }
        elseif ($which === 'body') { @unlink($bodyFile); admin_flash('已清空 </body> 前注入代码。', 'ok'); }
    }
    header('Location: tools.php');
    exit;
}

$head = is_file($headFile) ? (string)@file_get_contents($headFile) : '';
$body = is_file($bodyFile) ? (string)@file_get_contents($bodyFile) : '';

admin_head('工具 · 追加代码', 'tools'); ?>
<h1>工具 · 追加代码</h1>
<p class="muted">在前台所有页面统一追加自定义 HTML / JS（例如 Google Analytics 统计代码）。保存后立即生效，每次修改会自动备份。</p>

<div class="flash info">
  <b>说明：</b>
  <ul style="margin:6px 0 0;padding-left:18px">
    <li><b>&lt;head&gt; 注入</b> —— 输出在每个页面的 <code>&lt;head&gt;</code> 尾部。统计代码（如 GA4）一般放这里。</li>
    <li><b>&lt;/body&gt; 前注入</b> —— 输出在每个页面的 <code>&lt;/body&gt;</code> 之前，适合页面加载完再跑的脚本。</li>
    <li>内容<b>原样输出</b>，不做转义；只允许已登录管理员修改，请勿粘贴不可信脚本。</li>
    <li>对应文件：<code>content/code_head.html</code> / <code>content/code_body.html</code>（nginx 已拒绝直接访问）。</li>
  </ul>
</div>

<form method="post">
  <input type="hidden" name="csrf" value="<?= h(admin_csrf_token()) ?>">
  <input type="hidden" name="act" value="save">

  <h2>&lt;head&gt; 注入 <span class="pill"><?= $head !== '' ? '已启用' : '空' ?></span></h2>
  <label>内容（原样输出到前台每个页面的 &lt;head&gt; 内）</label>
  <textarea name="head" class="ee-textarea" rows="8" placeholder="例如：&#10;&lt;script async src=&quot;https://www.googletagmanager.com/gtag/js?id=G-XXXXXXXXXX&quot;&gt;&lt;/script&gt;&#10;&lt;script&gt;window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','G-XXXXXXXXXX');&lt;/script&gt;"><?= h($head) ?></textarea>

  <h2>&lt;/body&gt; 前注入 <span class="pill"><?= $body !== '' ? '已启用' : '空' ?></span></h2>
  <label>内容（原样输出到前台每个页面的 &lt;/body&gt; 之前）</label>
  <textarea name="body" class="ee-textarea" rows="6" placeholder="例如第三方脚本 / 客服挂件代码…"><?= h($body) ?></textarea>

  <div class="row" style="margin-top:16px">
    <button class="btn">保存全部</button>
    <span class="muted">保存即写文件并自动备份，可放心反复修改。</span>
  </div>
</form>

<?php admin_foot(); ?>
