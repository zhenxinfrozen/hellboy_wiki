<?php
/**
 * 图片 / 缩略图管理
 * - 统计原图、thumb、gallery 三档覆盖率与体积
 * - 一键补全缺失派生图（后台异步调 tools/build_thumbs.py）
 * - 列出超大原图（>1MB）供人工评估
 */
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/ui.php';
admin_require_login();

$IMG_FILES = glob(IMAGES_DIR . '/*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE) ?: [];
// 排除派生目录（thumb/gallery 子目录下的文件不会被顶层 glob 抓到）
$THUMB_DIR = IMAGES_DIR . '/thumb';
$GALLERY_DIR = IMAGES_DIR . '/gallery';
$THUMB_FILES = glob($THUMB_DIR . '/*.webp') ?: [];
$GALLERY_FILES = glob($GALLERY_DIR . '/*.webp') ?: [];

$thumbMap = [];
foreach ($THUMB_FILES as $f) $thumbMap[basename($f, '.webp')] = $f;
$galleryMap = [];
foreach ($GALLERY_FILES as $f) $galleryMap[basename($f, '.webp')] = $f;

$srcTotalBytes = 0; $thumbBytes = 0; $galleryBytes = 0;
$missingThumb = []; $missingGallery = [];
$bigOnes = []; // >1MB
foreach ($IMG_FILES as $f) {
    $size = filesize($f);
    $srcTotalBytes += $size;
    $stem = pathinfo($f, PATHINFO_FILENAME);
    if (isset($thumbMap[$stem])) { $thumbBytes += filesize($thumbMap[$stem]); }
    else $missingThumb[] = $f;
    if (isset($galleryMap[$stem])) { $galleryBytes += filesize($galleryMap[$stem]); }
    else $missingGallery[] = $f;
    if ($size > 1024 * 1024) $bigOnes[] = [$f, $size];
}
usort($bigOnes, fn($a,$b)=>$b[1]<=>$a[1]);

// ---- 异步触发生成 ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'generate') {
    admin_csrf_check();
    $script = ROOT . '/tools/build_thumbs.py';
    if (!is_file($script)) {
        admin_flash('找不到 tools/build_thumbs.py', 'err');
    } else {
        $cmd = (stripos(PHP_OS, 'WIN') === 0 ? 'python ' : 'python3 ') . escapeshellarg($script) . ' --apply';
        if (stripos(PHP_OS, 'WIN') === 0) {
            // Windows 后台异步
            pclose(popen('start /B "" cmd /c "' . $cmd . '" >NUL 2>&1', 'r'));
        } else {
            exec('nohup ' . $cmd . ' >/dev/null 2>&1 &');
        }
        admin_flash('已在后台启动 build_thumbs.py，约 1–5 分钟后刷新查看进度。');
    }
    header('Location: images.php'); exit;
}

function hsize(int $b): string {
    if ($b >= 1048576) return number_format($b/1048576, 1) . ' MB';
    if ($b >= 1024) return number_format($b/1024, 1) . ' KB';
    return $b . ' B';
}
$covThumb = count($IMG_FILES) ? round(count($THUMB_FILES) * 100 / count($IMG_FILES)) : 0;
$covGallery = count($IMG_FILES) ? round(count($GALLERY_FILES) * 100 / count($IMG_FILES)) : 0;
$savings = ($srcTotalBytes > 0) ? round(($thumbBytes + $galleryBytes) * 100 / $srcTotalBytes) : 0;

admin_head('图片与缩略图', 'images'); ?>

<div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="content-card p-3">
            <div class="text-secondary small">原图文件</div>
            <div class="h3 mb-0 fw-bold"><?= count($IMG_FILES) ?></div>
            <div class="text-secondary small">共 <?= hsize($srcTotalBytes) ?></div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="content-card p-3">
            <div class="text-secondary small">thumb (宽400, q80)</div>
            <div class="h3 mb-0 fw-bold"><?= count($THUMB_FILES) ?> <span class="small text-secondary">/ <?= count($IMG_FILES) ?></span></div>
            <div class="text-secondary small">覆盖率 <?= $covThumb ?>% · 共 <?= hsize($thumbBytes) ?></div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="content-card p-3">
            <div class="text-secondary small">gallery (宽800, q82)</div>
            <div class="h3 mb-0 fw-bold"><?= count($GALLERY_FILES) ?> <span class="small text-secondary">/ <?= count($IMG_FILES) ?></span></div>
            <div class="text-secondary small">覆盖率 <?= $covGallery ?>% · 共 <?= hsize($galleryBytes) ?></div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="content-card p-3">
            <div class="text-secondary small">派生 / 原身体积比</div>
            <div class="h3 mb-0 fw-bold"><?= $savings ?>%</div>
            <div class="text-secondary small">thumb+gallery 合计 <?= hsize($thumbBytes + $galleryBytes) ?></div>
        </div>
    </div>
</div>

<div class="content-card p-3 mb-4 d-flex align-items-center gap-2 flex-wrap">
    <form method="post" style="margin:0">
        <input type="hidden" name="csrf" value="<?= h(admin_csrf_token()) ?>">
        <input type="hidden" name="action" value="generate">
        <button class="btn btn-primary btn-sm" type="submit">
            <i data-feather="image"></i> 一键补全缺失缩略图（后台异步）
        </button>
    </form>
    <span class="text-secondary small">缺失 thumb: <?= count($missingThumb) ?> · 缺失 gallery: <?= count($missingGallery) ?> · 全量重跑也是幂等的，已存在的会跳过</span>
</div>

<?php if ($bigOnes): ?>
<div class="content-card p-3 mb-4">
    <h3 class="h6 mb-3 fw-bold"><i data-feather="alert-triangle" class="text-warning"></i> 超大原图 Top <?= count($bigOnes) ?>（&gt;1MB，建议优先转 webp 原图）</h3>
    <table class="grid">
        <thead><tr><th>文件</th><th style="width:120px">原图大小</th><th style="width:80px">thumb</th><th style="width:80px">gallery</th></tr></thead>
        <tbody>
        <?php foreach (array_slice($bigOnes, 0, 20) as [$f, $size]):
            $stem = pathinfo($f, PATHINFO_FILENAME); ?>
            <tr>
                <td><code><?= h(basename($f)) ?></code></td>
                <td><?= hsize($size) ?></td>
                <td><?= isset($thumbMap[$stem]) ? '<span class="pill ok">'.hsize(filesize($thumbMap[$stem])).'</span>' : '<span class="pill err">缺</span>' ?></td>
                <td><?= isset($galleryMap[$stem]) ? '<span class="pill ok">'.hsize(filesize($galleryMap[$stem])).'</span>' : '<span class="pill err">缺</span>' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php if ($missingThumb): ?>
<div class="content-card p-3 mb-4">
    <h3 class="h6 mb-3 fw-bold"><i data-feather="clock"></i> 仍缺 thumb 的原图（前 50）</h3>
    <table class="grid">
        <thead><tr><th>文件</th><th style="width:120px">原图大小</th></tr></thead>
        <tbody>
        <?php foreach (array_slice($missingThumb, 0, 50) as $f): ?>
            <tr><td><code><?= h(basename($f)) ?></code></td><td><?= hsize(filesize($f)) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php if (count($missingThumb) > 50): ?><p class="text-secondary small mt-2">… 共 <?= count($missingThumb) ?> 张未列出</p><?php endif; ?>
</div>
<?php endif; ?>

<div class="content-card p-3 text-secondary small">
    <b>说明：</b>
    <ul class="mb-0">
        <li>卡片墙（/gallery、/browse、首页 series/char/movie）走 <code>thumb</code> 档；正文画廊与信息框封面走 <code>gallery</code> 档；灯箱放大仍用原图。</li>
        <li>派生图目录：<code>content/images/thumb/</code> 与 <code>content/images/gallery/</code>，文件名 = 原图 stem + <code>.webp</code>。</li>
        <li>新增原图后，点上方按钮或执行 <code>python tools/build_thumbs.py --apply</code> 即可补全。</li>
    </ul>
</div>

<?php admin_foot();
