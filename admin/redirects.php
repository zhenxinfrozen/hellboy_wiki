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
<div class="content-card p-4 mb-4">
  <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
    <h3 class="h6 mb-0 fw-bold">英文重定向 en_redirects.json <span class="pill ms-1"><?=count($redir)?></span></h3>
    <span class="muted small ms-auto">aliases.json 另有 <?=count($alias)?> 条（仅展示）</span>
  </div>
  <p class="muted small">英文常用名/变体 → 本地中文正条。正文 <code>](/entry/英文名)</code> 无需改动，命中这里即 301 到正条。</p>
  <form method="get" class="d-flex gap-2 mb-3" style="max-width:360px">
    <input type="search" name="q" value="<?=h($q)?>" placeholder="搜索英文名或目标…"><button class="btn btn-primary">搜索</button>
  </form>
  <div style="max-height:50vh;overflow:auto">
    <table class="grid mb-0">
      <thead><tr><th>英文名（入站）</th><th>→ 本地正条</th><th style="width:110px">操作</th></tr></thead>
      <tbody>
      <?php foreach($rows as $en=>$canon): ?>
        <tr><td><?=h($en)?></td><td><?=h($canon)?></td>
        <td><a href="?edit=<?=rawurlencode($en)?>#form">编辑</a></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="content-card p-4" id="form">
  <h3 class="h6 mb-3 fw-bold"><?= $editing!==null?'编辑：'.h($editEn):'新增重定向' ?></h3>
  <form method="post">
    <input type="hidden" name="csrf" value="<?=h(admin_csrf_token())?>">
    <input type="hidden" name="act" value="save_redir">
    <input type="hidden" name="orig_en" value="<?=h($editEn??'')?>">
    <div class="row g-3">
      <div class="col-md-6"><label class="form-label small">英文常用名 / 变体</label>
        <input type="text" name="en" value="<?=h($editEn??'')?>" required list="redirk"></div>
      <div class="col-md-6"><label class="form-label small">目标本地正条（须已存在）</label>
        <input type="text" name="canon" value="<?=h($editing??'')?>" required list="localt">
        <datalist id="localt"><?php foreach($localTitles as $t): ?><option value="<?=h($t)?>"><?php endforeach; ?></datalist></div>
    </div>
    <div class="d-flex gap-2 mt-3"><button class="btn btn-primary">保存重定向</button>
    <?php if($editing!==null): ?><a class="btn sec" href="?">取消</a><?php endif; ?></div>
  </form>
  <?php if($editing!==null): ?>
  <form method="post" class="mt-2" onsubmit="return confirm('确认删除该重定向？')">
    <input type="hidden" name="csrf" value="<?=h(admin_csrf_token())?>">
    <input type="hidden" name="act" value="del_redir"><input type="hidden" name="en" value="<?=h($editEn)?>">
    <button class="btn danger">删除</button>
  </form><?php endif; ?>
</div>
<?php admin_foot();
