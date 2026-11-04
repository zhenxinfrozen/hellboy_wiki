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

admin_head('控制台仪表盘', 'dashboard'); ?>

<!-- 统计指标网格 -->
<div class="row g-3 mb-4">
    <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="content-card p-3 d-flex align-items-center gap-3 h-100">
            <div class="p-2 rounded bg-primary bg-opacity-10 text-primary">
                <i data-feather="book-open"></i>
            </div>
            <div>
                <div class="h3 mb-0 fw-bold"><?= $zhCount ?></div>
                <div class="text-secondary small">中文条目</div>
            </div>
        </div>
    </div>

    <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="content-card p-3 d-flex align-items-center gap-3 h-100">
            <div class="p-2 rounded bg-success bg-opacity-10 text-success">
                <i data-feather="check-circle"></i>
            </div>
            <div>
                <div class="h3 mb-0 fw-bold"><?= $enCount ?></div>
                <div class="text-secondary small">英文正文</div>
            </div>
        </div>
    </div>

    <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="content-card p-3 d-flex align-items-center gap-3 h-100">
            <div class="p-2 rounded bg-warning bg-opacity-10 text-warning">
                <i data-feather="alert-circle"></i>
            </div>
            <div>
                <div class="h3 mb-0 fw-bold"><?= $zhCount - $enCount ?></div>
                <div class="text-secondary small">缺英文正文</div>
            </div>
        </div>
    </div>

    <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="content-card p-3 d-flex align-items-center gap-3 h-100">
            <div class="p-2 rounded bg-info bg-opacity-10 text-info">
                <i data-feather="bookmark"></i>
            </div>
            <div>
                <div class="h3 mb-0 fw-bold"><?= $termCount ?></div>
                <div class="text-secondary small">术语表词条</div>
            </div>
        </div>
    </div>

    <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="content-card p-3 d-flex align-items-center gap-3 h-100">
            <div class="p-2 rounded bg-secondary bg-opacity-10 text-secondary">
                <i data-feather="shuffle"></i>
            </div>
            <div>
                <div class="h3 mb-0 fw-bold"><?= count($redir) ?></div>
                <div class="text-secondary small">英文重定向</div>
            </div>
        </div>
    </div>

    <div class="col-xl-2 col-md-4 col-sm-6">
        <div class="content-card p-3 d-flex align-items-center gap-3 h-100">
            <div class="p-2 rounded bg-danger bg-opacity-10 text-danger">
                <i data-feather="image"></i>
            </div>
            <div>
                <div class="h3 mb-0 fw-bold"><?= count($imgs) ?></div>
                <div class="text-secondary small">图片文件</div>
            </div>
        </div>
    </div>
</div>

<!-- 快速开始操作卡片 -->
<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="content-card p-4">
            <h3 class="h5 mb-3 fw-bold d-flex align-items-center gap-2">
                <i data-feather="zap" class="text-warning"></i> 常用快捷操作
            </h3>
            <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-primary btn-sm d-inline-flex align-items-center gap-2" href="entries.php">
                    <i data-feather="list"></i> 管理 / 新建条目
                </a>
                <a class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-2" href="glossary.php">
                    <i data-feather="book-open"></i> 编辑术语表
                </a>
                <a class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-2" href="redirects.php">
                    <i data-feather="shuffle"></i> 重定向 / 别名
                </a>
                <a class="btn btn-outline-info btn-sm d-inline-flex align-items-center gap-2" href="audit.php">
                    <i data-feather="activity"></i> 汉化体检
                </a>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="content-card p-4">
            <h3 class="h5 mb-3 fw-bold d-flex align-items-center gap-2">
                <i data-feather="shield" class="text-success"></i> 安全与存储架构
            </h3>
            <p class="text-secondary small mb-0 lh-base">
                本维基后台纯文件驱动，不使用 MySQL。所有写操作都会自动在 <code>admin/backups/</code> 保留时间戳备份，内容保存在 <code>content/</code> 下的 Markdown / JSON。
            </p>
        </div>
    </div>
</div>

<?php if (($GLOBALS['ADMIN_CFG']['password_hash'] ?? '') === ''): ?>
    <div class="alert alert-danger d-flex align-items-center gap-2" role="alert">
        <i data-feather="alert-triangle"></i>
        <div>警告：当前使用示例配置（config.example.php）。请创建 admin/config.local.php 并设置 admin_user 与 password_hash，否则无法登录。</div>
    </div>
<?php endif; ?>

<?php admin_foot();
