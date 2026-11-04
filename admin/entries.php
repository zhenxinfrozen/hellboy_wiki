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
<div class="content-card p-4">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <form method="get" class="d-flex gap-2" style="max-width:320px;flex:1">
      <input type="search" name="q" value="<?= h($q) ?>" placeholder="搜索条目标题…">
      <button class="btn btn-primary" type="submit">搜索</button>
    </form>
    <a class="btn sec" href="entry_edit.php?new=1">+ 新建条目</a>
  </div>
  <div class="d-flex flex-wrap gap-2 mb-3">
    <a class="pill <?= $view==='all'?'ok':'' ?>" href="entries.php">全部</a>
    <a class="pill <?= $view==='noen'?'warn':'' ?>" href="entries.php?view=noen">仅缺英文正文</a>
  </div>
  <div class="table-responsive">
    <table class="grid mb-0">
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
  </div>
</div>
<?php admin_foot();
