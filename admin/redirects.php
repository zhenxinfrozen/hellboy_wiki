<?php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/ui.php';
admin_require_login();

$redir = admin_read_json(EN_REDIRECT_FILE);
$alias = admin_read_json(ALIASES_FILE);
$slugs = admin_read_json(SLUGS_FILE);
$localTitles = array_keys($slugs);

if (($_SERVER['REQUEST_METHOD'] ?? '')==='POST') {
    admin_csrf_check();
    $act = $_POST['act'] ?? '';
    if ($act==='save_redir') {
        $en = trim((string)($_POST['en']??''));
        $canon = trim((string)($_POST['canon']??''));
        $orig = trim((string)($_POST['orig_en']??''));
        if ($en==='' || $canon==='') admin_flash('英文名和目标正条都不能为空。','err');
        elseif (!in_array($canon,$localTitles,true)) admin_flash('目标正条不在本地条目中：'.$canon,'err');
        else {
            if ($orig!=='' && $orig!==$en) unset($redir[$orig]);
            $redir[$en]=$canon;
            admin_write_json(EN_REDIRECT_FILE,$redir);
            admin_flash('已保存重定向：'.$en.' → '.$canon,'ok');
        }
    } elseif ($act==='del_redir') {
        $en=(string)($_POST['en']??'');
        if (isset($redir[$en])) { unset($redir[$en]); admin_write_json(EN_REDIRECT_FILE,$redir); admin_flash('已删除：'.$en,'ok'); }
    }
    header('Location: redirects.php'); exit;
}

$q=trim((string)($_GET['q']??''));
$editEn=$_GET['edit']??null;
$rows=$redir;
if ($q!=='') {
    $ql=mb_strtolower($q);
    $rows=array_filter($rows, fn($v,$k)=>mb_stripos(mb_strtolower($k.' '.$v),$ql)!==false, ARRAY_FILTER_USE_BOTH);
}
uksort($rows, fn($a,$b)=>strcasecmp($a,$b));
$editing = $editEn!==null && isset($redir[$editEn]) ? $redir[$editEn] : null;

admin_head('重定向 / 别名','redirects'); ?>
<h1>英文重定向 en_redirects.json <span class="pill"><?=count($redir)?></span></h1>
<p class="muted">英文常用名/变体 → 本地中文正条。正文 <code>](/entry/英文名)</code> 无需改动，命中这里即 301 到正条。人工合并表 aliases.json 另有 <?=count($alias)?> 条（当前版本仅在此展示）。</p>
<div class="row" style="margin-bottom:12px">
  <form method="get" style="flex:1;min-width:220px;display:flex;gap:8px">
    <input type="search" name="q" value="<?=h($q)?>" placeholder="搜索英文名或目标…"><button class="btn">搜索</button>
  </form>
</div>
<div style="max-height:50vh;overflow:auto;border:1px solid var(--line);border-radius:8px;margin-bottom:24px">
<table class="grid">
<thead><tr><th>英文名（入站）</th><th>→ 本地正条</th><th style="width:110px">操作</th></tr></thead>
<tbody>
<?php foreach($rows as $en=>$canon): ?>
<tr><td><?=h($en)?></td><td><?=h($canon)?></td>
<td><a href="?edit=<?=rawurlencode($en)?>#form">编辑</a></td></tr>
<?php endforeach; ?>
</tbody></table></div>

<h2 id="form"><?= $editing!==null?'编辑：'.h($editEn):'新增重定向' ?></h2>
<form method="post">
  <input type="hidden" name="csrf" value="<?=h(admin_csrf_token())?>">
  <input type="hidden" name="act" value="save_redir">
  <input type="hidden" name="orig_en" value="<?=h($editEn??'')?>">
  <div class="row">
    <div style="flex:1;min-width:220px"><label>英文常用名 / 变体</label>
      <input type="text" name="en" value="<?=h($editEn??'')?>" required list="redirk"></div>
    <div style="flex:1;min-width:220px"><label>目标本地正条（须已存在）</label>
      <input type="text" name="canon" value="<?=h($editing??'')?>" required list="localt">
      <datalist id="localt"><?php foreach($localTitles as $t): ?><option value="<?=h($t)?>"><?php endforeach; ?></datalist></div>
  </div>
  <div class="row" style="margin-top:12px"><button class="btn">保存重定向</button>
  <?php if($editing!==null): ?><a class="btn sec" href="?">取消</a><?php endif; ?></div>
</form>
<?php if($editing!==null): ?>
<form method="post" style="margin-top:10px" onsubmit="return confirm('确认删除该重定向？')">
  <input type="hidden" name="csrf" value="<?=h(admin_csrf_token())?>">
  <input type="hidden" name="act" value="del_redir"><input type="hidden" name="en" value="<?=h($editEn)?>">
  <button class="btn danger">删除</button>
</form><?php endif; ?>
<?php admin_foot();
