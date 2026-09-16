<?php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/ui.php';
admin_require_login();

$entries = admin_list_entries();
$q = trim((string)($_GET['q'] ?? ''));
if ($q !== '') {
    $ql = mb_strtolower($q);
    $entries = array_values(array_filter($entries, function($e) use ($ql){
        return mb_stripos(mb_strtolower($e['title']), $ql) !== false;
    }));
}
$view = $_GET['view'] ?? 'all';
if ($view === 'noen') $entries = array_values(array_filter($entries, fn($e)=>!$e['has_en']));

admin_head('条目', 'entries'); ?>
<h1>条目 <span class="pill"><?= count($entries) ?></span></h1>
<div class="row" style="margin-bottom:14px">
  <form method="get" style="flex:1;min-width:220px;display:flex;gap:8px">
    <input type="search" name="q" value="<?= h($q) ?>" placeholder="搜索条目标题…">
    <button class="btn" type="submit">搜索</button>
  </form>
  <a class="btn sec" href="entry_edit.php?new=1">+ 新建条目</a>
</div>
<div class="row" style="margin-bottom:12px">
  <a class="pill <?= $view==='all'?'ok':'' ?>" href="entries.php">全部</a>
  <a class="pill <?= $view==='noen'?'warn':'' ?>" href="entries.php?view=noen">仅缺英文正文</a>
</div>
<table class="grid">
  <thead><tr><th>标题（本地）</th><th>中文</th><th>英文</th><th style="width:120px">操作</th></tr></thead>
  <tbody>
  <?php foreach ($entries as $e): $t=$e['title']; ?>
    <tr>
      <td><a target="_blank" href="/entry/<?= rawurlencode(str_replace(' ','_',(admin_read_json(SLUGS_FILE)[$t] ?? $t))) ?>"><?= h($t) ?></a></td>
      <td><?= $e['zh_size']>200 ? '<span class="pill ok">有正文</span>' : '<span class="pill warn">'.number_format($e['zh_size']).'B</span>' ?></td>
      <td><?= $e['has_en'] ? '<span class="pill ok">EN</span>' : '<span class="pill warn">缺</span>' ?></td>
      <td><a href="entry_edit.php?title=<?= rawurlencode($t) ?>">编辑</a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php admin_foot();
