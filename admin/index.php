<?php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/ui.php';
admin_require_login();

$slugs = admin_read_json(SLUGS_FILE);
$enTitle = admin_read_json(EN_TITLE_FILE);
$redir = admin_read_json(EN_REDIRECT_FILE);
$alias = admin_read_json(ALIASES_FILE);
$gloss = is_file(GLOSSARY_FILE) ? admin_read_json(GLOSSARY_FILE) : ['terms'=>[]];
$entries = admin_list_entries();
$zhCount = count($entries);
$enCount = count(array_filter($entries, fn($e)=>$e['has_en']));
$termCount = is_array($gloss['terms'] ?? null) ? count($gloss['terms']) : 0;
$imgs = glob(IMAGES_DIR . '/*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE) ?: [];

admin_head('仪表盘', 'dashboard'); ?>
<h1>仪表盘</h1>
<div class="cards">
  <div class="card"><div class="n"><?= $zhCount ?></div><div class="l">中文条目</div></div>
  <div class="card"><div class="n"><?= $enCount ?></div><div class="l">英文正文</div></div>
  <div class="card"><div class="n"><?= $zhCount-$enCount ?></div><div class="l">缺英文正文</div></div>
  <div class="card"><div class="n"><?= $termCount ?></div><div class="l">术语表词条</div></div>
  <div class="card"><div class="n"><?= count($redir) ?></div><div class="l">英文重定向</div></div>
  <div class="card"><div class="n"><?= count($imgs) ?></div><div class="l">图片文件</div></div>
</div>

<h2>快速开始</h2>
<div class="row">
  <a class="btn" href="entries.php">管理 / 新建条目</a>
  <a class="btn sec" href="glossary.php">编辑术语表</a>
  <a class="btn sec" href="redirects.php">重定向 / 别名</a>
  <a class="btn sec" href="audit.php">汉化体检</a>
</div>

<h2>安全说明</h2>
<p class="muted">所有写操作都会先在 <code>admin/backups/</code> 留一份时间戳备份。后台不使用数据库，内容仍是 <code>content/</code> 下的纯 Markdown / JSON。</p>
<?php if (($GLOBALS['ADMIN_CFG']['password_hash'] ?? '')===''): ?>
<div class="flash err">警告：当前使用空配置（config.example.php）。请创建 admin/config.local.php 并设置 admin_user 与 password_hash，否则无法登录。</div>
<?php endif; ?>
<?php admin_foot();
