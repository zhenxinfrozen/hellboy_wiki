<?php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/ui.php';
require ROOT . '/lib/Parsedown.php';
admin_require_login();

/* ---------- 信息框（meta）类型模板：type→中文名、该类型常用字段 ---------- */
function ee_type_zh_map(): array {
    return [
        'Story'=>'漫画故事','Character'=>'角色','RealPerson'=>'真实人物','Species'=>'种族',
        'Disambiguation'=>'消歧义','Item'=>'物品','Location'=>'地点','Book'=>'图书',
        'Organization'=>'组织','Other'=>'资料','Movie'=>'电影','Game'=>'游戏',
        'Audio'=>'音频剧','Soundtrack'=>'原声专辑',
    ];
}
/* ---------- 预览：信息框值只放行 /entry/ 内链与 <br>，其余转义（与前台 .ib-card 结构一致） ---------- */
function ee_ib_value(string $v): string {
    $s = htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
    $s = preg_replace('~&lt;a\s+href=&quot;(/entry/[^&"]*)&quot;&gt;([\s\S]*?)&lt;/a&gt;~', '<a href="$1">$2</a>', $s);
    $s = preg_replace('~&lt;br\s*/?&gt;~i', '<br>', $s);
    return (string)$s;
}
function ee_preview_infobox(array $m): string {
    $type = trim((string)($m['type'] ?? ''));
    if ($type === '') return '';
    $head = h(trim((string)($m['type_zh'] ?? '')) ?: $type);
    $image = trim((string)($m['image'] ?? ''));
    if ($image !== '') {
        $src = IMG_URL . '/' . str_replace('%2F', '/', rawurlencode($image));
        $fig = '<div class="ib-figure"><img src="' . h($src) . '" alt=""></div>';
    } else {
        $fig = '<div class="ib-figure is-placeholder"><div class="ib-noimg">暂无特色图</div></div>';
    }
    $rows = '';
    foreach (($m['rows'] ?? []) as $r) {
        if (!is_array($r)) continue;
        $lb = trim((string)($r['label'] ?? ''));
        $vl = trim((string)($r['value'] ?? ''));
        if ($lb === '' || $vl === '') continue;
        $rows .= '<tr><th>' . h($lb) . '</th><td>' . ee_ib_value((string)$r['value']) . '</td></tr>';
    }
    return '<div class="ib-card type-' . h($type) . '"><div class="ib-head">' . $head . '</div>' . $fig
        . '<table class="ib-table"><tbody>' . $rows . '</tbody></table></div>';
}
function ee_field_templates(): array {
    return [
        'Story'       => ['作画','编剧','上色','嵌字','连载日期','出版','编辑','合订本','全集','期数'],
        'Character'   => ['性别','种族','漫画首秀','职业','创作者','亲属','别称/身份','别名','登场电影','饰演','逝世','国籍'],
        'RealPerson'  => ['性别','职业','出生','逝世','国籍','饰演','登场电影','创作者'],
        'Movie'       => ['导演','编剧','制片人','上映日期','片长','配乐','制作预算','票房'],
        'Book'        => ['作者','编剧','作画','出版方','出版日期','连载日期','页数','ISBN','编辑'],
        'Species'     => ['母星','体型','首次登场','创作者'],
        'Game'        => ['上映日期','开发商','出版社','平台'],
        'Audio'       => ['导演','编剧','改编','制片人','执行制片','出版日期'],
        'Soundtrack'  => ['作曲家','发行日期','时长','唱片公司'],
        'Organization'=> ['类型','领导人','总部','首次登场','创作者'],
        'Location'    => ['地理位置','居民','首次登场'],
        'Item'        => ['类型','制造者','持有者','首次登场','创作者'],
        'Other'       => [],
        'Disambiguation' => [],
    ];
}

$isNew = !empty($_GET['new']);
$normLayer = function(string $v): string {
    $v = strtolower(trim($v));
    return in_array($v, ['std','mt','en'], true) ? $v : 'std';
};

/* ---------- 读取当前 meta ---------- */
$loadMeta = function(string $t): array {
    $f = META . '/' . $t . '.json';
    if (is_file($f)) {
        $d = json_decode((string)file_get_contents($f), true);
        if (is_array($d)) return $d;
    }
    return ['type'=>'Other','type_zh'=>'资料','rows'=>[],'image'=>'','cats'=>[],'img_source'=>'placeholder'];
};

/* ---------- 保存处理 ---------- */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && empty($_POST['preview'])) {
    admin_csrf_check();
    $title = trim((string)($_POST['title'] ?? ''));
    $layer = $normLayer((string)($_POST['layer'] ?? 'std'));
    if (($layer === 'std') && (($_POST['lang'] ?? '') === 'en')) $layer = 'en';
    $body  = (string)($_POST['body'] ?? '');
    $slug  = trim((string)($_POST['slug'] ?? ''));

    if (!admin_safe_title($title)) {
        admin_flash('标题非法（不能为空，且不能包含 / 或 \\）。', 'err');
    } else {
        $origTitle = trim((string)($_POST['orig_title'] ?? ''));
        // 改名：三层文件 + meta + 全部索引 + 入站内链全联动
        if ($origTitle !== '' && $origTitle !== $title) {
            $renameLog = admin_rename_entry_everywhere($origTitle, $title);
        }
        // 正文（仅当前层）
        $dir = $layer === 'en' ? PAGES_EN : ($layer === 'mt' ? PAGES_MT : PAGES);
        $target = $dir . '/' . $title . '.md';
        if (!is_file($target)) @mkdir(dirname($target), 0775, true);
        admin_save_with_backup($target, $body);
        // slug（英文 permalink）
        if ($slug !== '' && $slug !== $title) {
            $sl = admin_read_json(SLUGS_FILE);
            if (($sl[$title] ?? null) !== $slug) { $sl[$title] = $slug; admin_write_json(SLUGS_FILE, $sl); }
        }
        // 信息框 meta（结构由前端整理成 meta_json 提交）
        $metaErr = '';
        $mj = json_decode((string)($_POST['meta_json'] ?? '{}'), true);
        if (!is_array($mj)) {
            $metaErr = '属性卡片数据解析失败，正文已保存，但属性未保存。';
        } else {
            $type = trim((string)($mj['type'] ?? 'Other')) ?: 'Other';
            $typeZhMap = ee_type_zh_map();
            $rows = [];
            foreach (($mj['rows'] ?? []) as $r) {
                if (!is_array($r)) continue;
                $lb = trim((string)($r['label'] ?? ''));
                $vl = (string)($r['value'] ?? '');
                if ($lb === '') continue;
                $rows[] = ['label'=>$lb, 'value'=>$vl];
            }
            $rowsEn = [];
            foreach (($mj['rows_en'] ?? []) as $r) {
                if (!is_array($r)) continue;
                $lb = trim((string)($r['label'] ?? ''));
                $vl = (string)($r['value'] ?? '');
                if ($lb === '') continue;
                $rowsEn[] = ['label'=>$lb, 'value'=>$vl];
            }
            $cats = [];
            foreach (($mj['cats'] ?? []) as $c) { $c = trim((string)$c); if ($c !== '' && !in_array($c,$cats,true)) $cats[] = $c; }
            $image = trim((string)($mj['image'] ?? ''));
            // 保留原有未暴露给表单的字段（img_source 等），再叠加本次编辑
            $prev = $loadMeta($title);
            $meta = array_merge($prev, [
                'type' => $type,
                'type_zh' => trim((string)($mj['type_zh'] ?? '')) ?: ($typeZhMap[$type] ?? $type),
                'rows' => $rows,
                'image' => $image,
                'cats' => $cats,
            ]);
            // 有图 → keep；无图 → placeholder（body/fandom 属抓取来源标记，仅在原本就是时保留）
            if ($image !== '') $meta['img_source'] = 'keep';
            elseif (($meta['img_source'] ?? '') === 'keep') $meta['img_source'] = 'placeholder';
            if ($rowsEn) $meta['rows_en'] = $rowsEn; else unset($meta['rows_en']);
            // 排序：type,type_zh,image,img_source,rows,rows_en,cats（稳定可读）
            $ordered = [];
            foreach (['type','type_zh','image','img_source'] as $k) if (array_key_exists($k,$meta)) $ordered[$k]=$meta[$k];
            $ordered['rows']=$rows;
            if ($rowsEn) $ordered['rows_en']=$rowsEn;
            $ordered['cats']=$cats;
            foreach ($meta as $k=>$v) if (!array_key_exists($k,$ordered)) $ordered[$k]=$v;
            $mp = META . '/' . $title . '.json';
            if (!is_dir(META)) @mkdir(META,0775,true);
            admin_save_with_backup($mp, json_encode($ordered, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_UNESCAPED_LINE_TERMINATORS)."\n");
        }
        $layerName = ['std'=>'标准中文','mt'=>'机翻中文','en'=>'英文'][$layer];
        $msg = '已保存：' . $title . '（' . $layerName . '层 + 属性卡片），已自动备份。';
        if (!empty($metaErr)) $msg .= ' 警告：' . $metaErr;
        if (!empty($renameLog)) $msg .= ' 改名联动 ' . count($renameLog) . ' 处。';
        admin_flash($msg, empty($metaErr)?'ok':'err');
        header('Location: entry_edit.php?title=' . rawurlencode($title) . '&layer=' . $layer);
        exit;
    }
}

/* ---------- 载入编辑态 ---------- */
$title = trim((string)($_POST['title'] ?? $_GET['title'] ?? ''));
$layer = $normLayer((string)($_GET['layer'] ?? $_GET['lang'] ?? 'std'));
$doPreview = isset($_POST['preview']);

$body = ''; $slugVal = ''; $meta = ['type'=>'Other','type_zh'=>'资料','rows'=>[],'image'=>'','cats'=>[],'img_source'=>'placeholder'];
if (!$isNew && $title !== '') {
    $dir = $layer === 'en' ? PAGES_EN : ($layer === 'mt' ? PAGES_MT : PAGES);
    $p = $dir . '/' . $title . '.md';
    $body = is_file($p) ? (string)file_get_contents($p) : '';
    $sl = admin_read_json(SLUGS_FILE);
    $slugVal = (string)($sl[$title] ?? $title);
    $meta = $loadMeta($title);
}
if ($doPreview) {
    $body = (string)($_POST['body'] ?? '');
    $title = trim((string)($_POST['title'] ?? ''));
    $slugVal = trim((string)($_POST['slug'] ?? ''));
    $previewMeta = json_decode((string)($_POST['meta_json'] ?? '{}'), true);
    if (!is_array($previewMeta)) $previewMeta = null;
    $pd = new Parsedown(); $pd->setSafeMode(true);
    $previewHtml = $pd->text($body);
}

/* ---------- 内嵌自动补全数据 ---------- */
$slAll = admin_read_json(SLUGS_FILE);
$entryList = [];
foreach ($slAll as $t=>$sg) $entryList[] = ['t'=>(string)$t, 's'=>(string)$sg];
$images = [];
foreach (glob(IMAGES_DIR . '/*') ?: [] as $im) {
    if (is_file($im)) $images[] = basename($im);
}
usort($images, fn($a,$b)=>strcasecmp($a,$b));
$catSet = [];
foreach (glob(META . '/*.json') ?: [] as $mf) {
    $d = json_decode((string)file_get_contents($mf), true);
    if (is_array($d)) foreach (($d['cats'] ?? []) as $c) $catSet[(string)$c] = true;
}
$catsAll = array_keys($catSet); sort($catSet, SORT_NATURAL|SORT_FLAG_CASE);

$bootData = [
    'entries' => $entryList,
    'images'  => $images,
    'cats'    => $catsAll,
    'types'   => ee_type_zh_map(),
    'fields'  => ee_field_templates(),
    'imgBase' => IMG_URL . '/',   // 前台图片路径前缀
    'upUrl'   => 'upload_image.php',
    'csrf'    => admin_csrf_token(),
    'meta'    => $meta,
];

admin_head($isNew ? '新建条目' : '编辑条目', 'entries'); ?>
<h1><?= $isNew ? '新建条目' : '编辑条目' ?></h1>
<form method="post" id="eeForm">
  <input type="hidden" name="csrf" value="<?= h(admin_csrf_token()) ?>">
  <input type="hidden" name="orig_title" value="<?= h($isNew ? '' : $title) ?>">
  <input type="hidden" name="layer" value="<?= h($layer) ?>">
  <input type="hidden" name="lang" value="<?= h($layer==='en'?'en':'zh') ?>">
  <input type="hidden" name="meta_json" id="metaJson" value="">

  <div class="ee">
    <!-- ============ 左：标题 + 正文 ============ -->
    <div class="ee-main">
      <div class="ee-panel">
        <div class="ee-fields">
          <div>
            <label>本地标题（中文正名 / 文件名）</label>
            <input type="text" name="title" id="fTitle" value="<?= h($title) ?>" required>
          </div>
          <div>
            <label>英文 permalink（slug · 留空=与标题相同）</label>
            <input type="text" name="slug" id="fSlug" value="<?= h($slugVal) ?>" placeholder="例：Hellboy (2019 film)">
          </div>
          <div class="full">
            <label>编辑版本层</label>
            <div class="tabs" id="layerTabs">
              <a data-layer="std" class="<?= $layer==='std'?'on':'' ?>">标准中文</a>
              <a data-layer="mt"  class="<?= $layer==='mt'?'on':'' ?>">机翻中文</a>
              <a data-layer="en"  class="<?= $layer==='en'?'on':'' ?>">EN 原文</a>
            </div>
          </div>
        </div>
      </div>

      <div class="ee-panel">
        <h3>正文 <span class="hint">Markdown · 工具栏辅助，落盘仍是标准 .md</span></h3>
        <div class="mdbar" id="mdbar">
          <button type="button" data-act="bold" title="加粗"><b>B</b></button>
          <button type="button" data-act="italic" title="斜体"><i>I</i></button>
          <span class="sep"></span>
          <button type="button" data-act="h2" title="二级标题">H2</button>
          <button type="button" data-act="h3" title="三级标题">H3</button>
          <button type="button" data-act="ul" title="无序列表">• 列表</button>
          <span class="sep"></span>
          <button type="button" data-act="link" title="外部链接">🔗 链接</button>
          <button type="button" data-act="ilink" title="站内条目（自动英文固定链接）">📖 内链</button>
          <button type="button" data-act="img" title="插入图库图片">🖼 图片</button>
          <button type="button" data-act="quote" title="引用">❝</button>
        </div>
        <textarea class="ee-textarea" name="body" id="fBody" spellcheck="false" placeholder="用 Markdown 书写正文；点「内链」可搜索条目并自动插入英文固定链接…"><?= h($body) ?></textarea>
        <div class="ee-status" id="eeStatus">
          <span>字数 <b id="stChars">0</b></span>
          <span>内链 <b id="stLinks">0</b></span>
          <span>图片 <b id="stImgs">0</b></span>
          <span>标题 <b id="stHeads">0</b></span>
          <span id="stWarn"></span>
        </div>
      </div>

      <div class="ee-foot">
        <button class="btn" type="submit" name="save" value="1">💾 保存（自动备份）</button>
        <button class="btn sec" type="submit" name="preview" value="1">预览</button>
        <?php if (!$isNew): ?><a class="btn sec" target="_blank" id="btnFront" href="/entry/<?= rawurlencode(str_replace(' ','_',$slugVal?:$title)) ?>">在前台查看 ↗</a><?php endif; ?>
        <a class="btn ghost" href="entries.php">返回列表</a>
      </div>

      <?php if ($doPreview): ?>
      <div class="pv-head">
        <h2>前台预览</h2>
        <span class="muted">/entry/<?= h(str_replace(' ', '_', $slugVal ?: $title)) ?>（未保存内容，仅供预览）</span>
      </div>
      <div class="preview-wrap">
        <article class="pv-body">
          <h1 class="pv-title"><?= h($title) ?></h1>
          <?= $previewHtml ?>
        </article>
        <aside class="pv-side"><?= $previewMeta ? ee_preview_infobox($previewMeta) : '<span class="muted">无属性卡片</span>' ?></aside>
      </div>
      <?php endif; ?>
    </div>

    <!-- ============ 右：信息框属性卡片（1:1 复刻前台） ============ -->
    <div class="ee-side">
      <div class="ee-panel">
        <h3>特色图 / 属性卡片</h3>

        <label>卡片类型</label>
        <select id="fType"></select>

        <div class="cover" id="coverBox">
          <div class="cover-filled" id="coverPrev" hidden>
            <button type="button" class="cover-card" id="coverCard" title="点击更换特色图">
              <img id="coverImg" alt="特色图">
              <span class="cover-hover"><span class="ch-badge">🖼 更换图片</span></span>
            </button>
            <div class="cover-foot">
              <span class="cover-filename" id="coverName"></span>
              <button type="button" class="cover-change" id="coverChange">更换</button>
              <button type="button" class="cover-remove" id="coverClear">移除</button>
            </div>
          </div>
          <button type="button" class="cover-empty" id="coverDrop">
            <span class="ce-icon"><svg viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2.5"/><circle cx="8.6" cy="8.6" r="1.6"/><path d="M21 15l-5-5L5 21"/></svg></span>
            <span class="ce-main">设置特色图</span>
            <span class="ce-sub">点击上传 / 从媒体库选择，或将图片拖到这里</span>
          </button>
          <input type="file" id="coverFile" accept="image/jpeg,image/png,image/gif,image/webp" hidden>
          <div class="cover-status" id="coverStatus" hidden></div>
        </div>

        <label style="margin-top:16px">属性行</label>
        <div id="metaRows"></div>
        <div class="meta-add">
          <select id="addField"><option value="">＋ 添加常用属性…</option></select>
          <button type="button" class="btn sm sec" id="addCustom">自定义</button>
        </div>

        <label style="margin-top:16px">分类（cats）</label>
        <div class="cat-chips" id="catChips"></div>
        <input type="text" id="catInput" list="catList" placeholder="输入或选择分类后回车">
        <datalist id="catList"></datalist>

        <details style="margin-top:14px">
          <summary class="muted" style="cursor:pointer;font-size:12.5px">英文信息框（rows_en，可选，仅电影等双语条目）</summary>
          <div id="metaRowsEn" style="margin-top:8px"></div>
          <button type="button" class="btn sm sec" id="syncEn" style="margin-top:6px">从中文属性同步结构</button>
        </details>
      </div>
    </div>
  </div>
</form>

<!-- 站内条目内链选择弹层（仅条目，不含图片） -->
<div class="link-pop" id="linkPop">
  <div class="lp-search"><input type="text" id="lpQuery" placeholder="搜索中文或英文名…"></div>
  <div class="lp-list" id="lpList"></div>
</div>

<!-- ============ 图片弹窗：上传 / 媒体库 两套独立逻辑 ============ -->
<div class="modal-overlay" id="imgModal" hidden>
  <div class="modal" role="dialog" aria-modal="true">
    <div class="m-head">
      <b id="imgModalTitle">插入图片</b>
      <button type="button" class="m-close" id="imgModalClose" title="关闭">×</button>
    </div>
    <div class="m-tabs">
      <button type="button" class="m-tab on" data-tab="upload">⬆ 上传新图片</button>
      <button type="button" class="m-tab" data-tab="library">🖼 从媒体库选择</button>
    </div>
    <div class="m-body">
      <div class="m-pane" id="paneUpload">
        <div class="m-drop" id="mDrop">
          <div class="md-icon">⬆</div>
          <div class="md-main">把图片拖到这里，或点击选择文件</div>
          <div class="md-sub">支持 JPG / PNG / GIF / WEBP，单个不超过 8MB</div>
        </div>
        <input type="file" id="mFile" accept="image/jpeg,image/png,image/gif,image/webp" hidden>
        <div class="up-progress" id="mProgress" hidden></div>
        <div class="m-upresult" id="mUpResult" hidden>
          <img id="mUpImg" alt="">
          <div class="mur-info">
            <b id="mUpName"></b>
            <div class="muted" id="mUpMeta"></div>
            <div class="mur-hint">上传成功，点击右下角按钮即可插入。</div>
          </div>
        </div>
      </div>
      <div class="m-pane" id="paneLibrary" hidden>
        <div class="m-libsearch"><input type="text" id="mSearch" placeholder="按文件名过滤…"><span class="muted" id="mCount"></span></div>
        <div class="m-grid" id="mGrid"></div>
        <div class="m-morewrap"><button type="button" class="btn sm sec" id="mMore" hidden>加载更多</button></div>
      </div>
    </div>
    <div class="m-foot">
      <span class="m-selinfo" id="mSelInfo"></span>
      <span class="sp"></span>
      <button type="button" class="btn ghost" id="mCancel">取消</button>
      <button type="button" class="btn" id="mConfirm" disabled>插入到正文</button>
    </div>
  </div>
</div>

<script>
const EE = <?= json_encode($bootData, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="/admin/editor.js?v=12"></script>
<?php admin_foot();
