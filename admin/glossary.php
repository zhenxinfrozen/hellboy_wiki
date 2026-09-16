<?php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/ui.php';
admin_require_login();

$data = is_file(GLOSSARY_FILE) ? admin_read_json(GLOSSARY_FILE) : ['_meta'=>[], 'terms'=>[]];
if (!isset($data['terms']) || !is_array($data['terms'])) $data['terms']=[];
$terms =& $data['terms'];

if (($_SERVER['REQUEST_METHOD'] ?? '')==='POST') {
    admin_csrf_check();
    $act = $_POST['act'] ?? '';
    if ($act==='save_term') {
        $en = trim((string)($_POST['en']??''));
        if ($en==='') { admin_flash('英文名不能为空。','err'); }
        else {
            $zh = trim((string)($_POST['zh']??''));
            $slug = trim((string)($_POST['slug']??''));
            $type = trim((string)($_POST['type']??''));
            $al = array_values(array_filter(array_map('trim', preg_split('/[,，、]/u', (string)($_POST['aliases']??''))), fn($x)=>$x!==''));
            $orig = trim((string)($_POST['orig_en']??''));
            if ($orig!=='' && $orig!==$en) { unset($terms[$orig]); }
            $review = ($_POST['review']??'')==='pending' ? 'pending' : 'confirmed';
            $rec = ['zh'=>$zh ?: null, 'slug'=>$slug ?: null, 'type'=>$type ?: null, 'core'=>!empty($_POST['core']), 'review'=>$review];
            if ($al) $rec['aliases']=$al;
            $terms[$en]=$rec;
            admin_write_json(GLOSSARY_FILE, $data);
            admin_flash('已保存术语：'.$en,'ok');
        }
    } elseif ($act==='del') {
        $en=(string)($_POST['en']??'');
        if (isset($terms[$en])) { unset($terms[$en]); admin_write_json(GLOSSARY_FILE,$data); admin_flash('已删除：'.$en,'ok'); }
    }
    header('Location: glossary.php'.(isset($_GET['edit'])?'?edit='.rawurlencode((string)$_GET['edit']):''));
    exit;
}

$editEn = $_GET['edit'] ?? null;
$q = trim((string)($_GET['q']??''));
$view = $_GET['view'] ?? 'all';
$slugs = admin_read_json(SLUGS_FILE);
$slugRev = [];
foreach ($slugs as $loc=>$sl) $slugRev[(string)$sl]=$loc;

// 校验：slug 是否命中本地页
$badSlug=[];
foreach ($terms as $en=>$r) {
    if (!empty($r['slug']) && !isset($slugRev[$r['slug']])) $badSlug[$en]=$r['slug'];
}

$list=[];
foreach ($terms as $en=>$r) {
    $score=0;
    if ($q!=='') {
        $hay=mb_strtolower($en.' '.($r['zh']??'').' '.implode(' ',(array)($r['aliases']??[])));
        if (mb_stripos($hay,$q)===false) continue;
    }
    if ($view==='core' && empty($r['core'])) continue;
    if ($view==='badslug' && !isset($badSlug[$en])) continue;
    if ($view==='nozh' && !empty($r['zh'])) continue;
    if ($view==='pending' && (($r['review']??'confirmed')!=='pending')) continue;
    $list[$en]=$r;
}
$pendingN=0; foreach ($terms as $r) if (($r['review']??'confirmed')==='pending') $pendingN++;
uksort($list, fn($a,$b)=>strcasecmp($a,$b));

$editing = $editEn!==null && isset($terms[$editEn]) ? $terms[$editEn] : null;
admin_head('术语表','glossary'); ?>
<h1>术语表 glossary.json <span class="pill"><?= count($terms) ?> 条</span></h1>
<p class="muted">唯一译名事实源。<b>zh</b> 为规范中译正名；<b>aliases</b> 为旧译/变体（用逗号分隔，仅兼容不做正名）；<b>slug</b> 指向站内英文 permalink。改动保存即写文件并自动备份。</p>

<div class="row" style="margin-bottom:12px">
  <form method="get" style="flex:1;min-width:220px;display:flex;gap:8px">
    <input type="search" name="q" value="<?=h($q)?>" placeholder="搜索中/英文/别名…">
    <button class="btn">搜索</button>
  </form>
  <a class="pill <?= $view==='all'?'ok':'' ?>" href="?">全部</a>
  <a class="pill <?= $view==='core'?'ok':'' ?>" href="?view=core">核心</a>
  <a class="pill <?= $view==='nozh'?'warn':'' ?>" href="?view=nozh">缺中译</a>
  <a class="pill <?= $view==='pending'?'warn':'' ?>" href="?view=pending">待审核 (<?=$pendingN?>)</a>
  <a class="pill <?= $view==='badslug'?'warn':'' ?>" href="?view=badslug">slug 失效 (<?=count($badSlug)?>)</a>
</div>

<?php if ($badSlug): ?>
<div class="flash info">有 <?=count($badSlug)?> 条术语的 slug 未命中本地页（可能是待建条目）。切到「slug 失效」查看。</div>
<?php endif; ?>

<table class="grid" style="margin-bottom:26px">
<thead><tr><th>英文标准名</th><th>规范中译</th><th>类型</th><th>别名</th><th>slug</th><th>审核</th><th style="width:90px">操作</th></tr></thead>
<tbody>
<?php foreach ($list as $en=>$r): $bad=isset($badSlug[$en]); $pend=(($r['review']??'confirmed')==='pending'); ?>
<tr>
  <td><?=h($en)?><?= !empty($r['core'])?' <span class="pill">核心</span>':'' ?><?= $pend?' <span class="pill warn">待审核</span>':'' ?></td>
  <td><?=h($r['zh']??'')?></td>
  <td class="muted"><?=h($r['type']??'')?></td>
  <td class="muted"><?=h(implode('、',(array)($r['aliases']??[])))?></td>
  <td class=""><?=h($r['slug']??'')?> <?=$bad?'<span class="pill warn">失效</span>':''?></td>
  <td><?= $pend?'<span class="pill warn">待审核</span>':'<span class="pill ok">已确认</span>' ?></td>
  <td><a href="?edit=<?=rawurlencode($en)?>">编辑</a></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

<h2><?= $editing!==null ? '编辑：'.h($editEn) : '新增 / 编辑术语' ?></h2>
<form method="post">
  <input type="hidden" name="csrf" value="<?=h(admin_csrf_token())?>">
  <input type="hidden" name="act" value="save_term">
  <input type="hidden" name="orig_en" value="<?=h($editEn??'')?>">
  <div class="row">
    <div style="flex:2;min-width:220px"><label>英文标准名（键）</label>
      <input type="text" name="en" value="<?=h($editEn??'')?>" required></div>
    <div style="flex:2;min-width:220px"><label>规范中译正名</label>
      <input type="text" name="zh" value="<?=h($editing['zh']??'')?>"></div>
  </div>
  <div class="row">
    <div style="flex:1;min-width:150px"><label>类型</label>
      <input type="text" name="type" list="types" value="<?=h($editing['type']??'')?>" placeholder="character / artist / series / organization">
      <datalist id="types"><option>character</option><option>artist</option><option>series</option><option>organization</option><option>location</option><option>item</option></datalist></div>
    <div style="flex:2;min-width:220px"><label>英文 permalink（slug）</label>
      <input type="text" name="slug" value="<?=h($editing['slug']??'')?>"></div>
    <div style="flex:0 0 auto;padding-top:22px"><label style="margin:0"><input type="checkbox" name="core" value="1" style="width:auto" <?=!empty($editing['core'])?'checked':''?>> 核心词条</label></div>
    <div style="flex:0 0 auto;min-width:120px"><label>审核状态</label>
      <select name="review">
        <?php $rv = $editing['review']??'confirmed'; ?>
        <option value="confirmed" <?=$rv!=='pending'?'selected':''?>>已确认</option>
        <option value="pending" <?=$rv==='pending'?'selected':''?>>待审核</option>
      </select></div>
  </div>
  <label>别名（旧译/变体，逗号分隔；仅用于兼容与回退）</label>
  <input type="text" name="aliases" value="<?=h(implode('，',(array)($editing['aliases']??[])))?>">
  <div class="row" style="margin-top:14px">
    <button class="btn" type="submit">保存术语</button>
    <?php if ($editing!==null): ?><a class="btn sec" href="?">取消编辑</a><?php endif; ?>
  </div>
</form>

<?php if ($editing!==null): ?>
<form method="post" style="margin-top:10px" onsubmit="return confirm('确认删除该术语？')">
  <input type="hidden" name="csrf" value="<?=h(admin_csrf_token())?>">
  <input type="hidden" name="act" value="del">
  <input type="hidden" name="en" value="<?=h($editEn)?>">
  <button class="btn danger" type="submit">删除该术语</button>
</form>
<?php endif; ?>
<?php admin_foot();
