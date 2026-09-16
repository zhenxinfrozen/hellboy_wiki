<?php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/ui.php';
admin_require_login();

$slugs = admin_read_json(SLUGS_FILE);
$enTitle = admin_read_json(EN_TITLE_FILE);
$redir = admin_read_json(EN_REDIRECT_FILE);
$alias = admin_read_json(ALIASES_FILE);
$entries = admin_list_entries();

// 正文汉化粗分级 + 断链统计（复刻前台 resolver：slug 反查 / 本地页 / redirect+alias）
$slugRev=[];
foreach ($slugs as $loc=>$sl) $slugRev[mb_strtolower(str_replace('_',' ',(string)$sl))]=$loc;
$map = $redir + $alias;
$levels=['full'=>0,'partial'=>0,'raw'=>0];
$noEn=0; $broken=[]; $brokenPages=0;
$linkRe='~\]\(/entry/([^)#?]+)~';
foreach ($entries as $e) {
    $t=$e['title'];
    $md=(string)@file_get_contents(PAGES.'/'.$t.'.md');
    $cjk=preg_match_all('/[\x{4e00}-\x{9fff}]/u',$md);
    if ($cjk>=200)$levels['full']++; elseif($cjk>=20)$levels['partial']++; else $levels['raw']++;
    if (!$e['has_en'])$noEn++;
    $pageBroken=0;
    preg_match_all($linkRe,$md,$mm);
    foreach($mm[1] as $raw){
        $s=rawurldecode($raw); $s=html_entity_decode($s,ENT_QUOTES|ENT_HTML5,'UTF-8');
        $s=ltrim(trim($s," \t\\\/"),':');
        $norm=str_replace('_',' ',$s);
        $key=mb_strtolower($norm);
        $resolved = isset($slugRev[$key]) || is_file(PAGES.'/'.$norm.'.md') || isset($map[$norm]);
        // 跨维基/特殊页不算断链
        if (preg_match('#^(w:c:|wikipedia:|pt-br:|:?(Category|File|Special):)#i',$s)) $resolved=true;
        if (!$resolved){ $broken[$norm]=($broken[$norm]??0)+1; $pageBroken++; }
    }
    if($pageBroken>0)$brokenPages++;
}
arsort($broken);

admin_head('体检','audit'); ?>
<h1>汉化与结构体检</h1>
<p class="muted">只读统计，直接读 content/ 实时计算，不改任何文件。</p>
<div class="cards">
  <div class="card"><div class="n"><?=count($entries)?></div><div class="l">中文条目</div></div>
  <div class="card"><div class="n"><?=count($entries)-$noEn?></div><div class="l">有英文正文</div></div>
  <div class="card"><div class="n"><?=$noEn?></div><div class="l">缺英文正文</div></div>
  <div class="card"><div class="n"><?=$levels['full']?></div><div class="l">正文较完整(≥200汉字)</div></div>
  <div class="card"><div class="n"><?=$levels['partial']?></div><div class="l">零星汉化(20–199)</div></div>
  <div class="card"><div class="n"><?=$levels['raw']?></div><div class="l">基本英文(&lt;20)</div></div>
</div>

<h2>高频断链目标（站内无法解析）</h2>
<p class="muted">共涉及 <?=$brokenPages?> 个页面、<?=array_sum($broken)?> 处、<?=count($broken)?> 个不同目标。能并入现有正条的，去 <a href="redirects.php">重定向</a> 加映射；确无条目则保持 Fandom 外链或新建。</p>
<table class="grid">
<thead><tr><th>目标（英文名）</th><th style="width:90px">出现次数</th><th style="width:130px">操作</th></tr></thead>
<tbody>
<?php $i=0; foreach($broken as $name=>$cnt): if($i++>=60)break; ?>
<tr><td><?=h($name)?></td><td><?=$cnt?></td>
<td><a href="redirects.php#form" onclick="try{localStorage.setItem('hb_redir',<?=json_encode($name, JSON_UNESCAPED_UNICODE)?>)}catch(e){}">加重定向</a></td></tr>
<?php endforeach; ?>
</tbody></table>
<?php admin_foot();
