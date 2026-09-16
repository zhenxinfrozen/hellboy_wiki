<?php
/**
 * 后台图片上传（AJAX / fetch）。
 * 登录 + CSRF 双校验；只接受真实图片；扩展名白名单；大小上限；文件名清洗 + 防覆盖/穿越。
 * 成功返回 JSON：{ok:true, name:"落盘文件名", url:"/content/images/.."}
 */
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

function up_fail(int $code, string $msg): never {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!admin_logged_in()) up_fail(401, '未登录或会话已过期，请刷新页面重新登录。');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') up_fail(405, '仅支持 POST。');
if (!hash_equals(admin_csrf_token(), (string)($_POST['csrf'] ?? ''))) up_fail(419, 'CSRF token 无效，请刷新页面重试。');
if (!isset($_FILES['file']) || !is_array($_FILES['file'])) up_fail(400, '没有收到文件（字段名需为 file）。');

$f = $_FILES['file'];
if ($f['error'] !== UPLOAD_ERR_OK) {
    $map = [
        UPLOAD_ERR_INI_SIZE   => '文件超过服务器 PHP 上限（upload_max_filesize）。',
        UPLOAD_ERR_FORM_SIZE  => '文件超过表单允许大小。',
        UPLOAD_ERR_PARTIAL    => '文件只上传了一部分。',
        UPLOAD_ERR_NO_FILE    => '没有选择文件。',
        UPLOAD_ERR_NO_TMP_DIR => '服务器缺少临时目录。',
        UPLOAD_ERR_CANT_WRITE => '服务器写入失败。',
    ];
    up_fail(400, $map[$f['error']] ?? ('上传失败，错误码 ' . $f['error']));
}
if (!is_uploaded_file($f['tmp_name'])) up_fail(400, '非法上传来源。');

$maxBytes = 8 * 1024 * 1024;   // 8MB
if ($f['size'] <= 0 || $f['size'] > $maxBytes) up_fail(413, '图片需大于 0 且不超过 8MB。');

// 真实图片校验（不信任客户端 MIME / 扩展名）
$info = @getimagesize($f['tmp_name']);
if ($info === false) up_fail(400, '文件不是有效的图片。');
$allowedExt = [
    IMAGETYPE_JPEG => 'jpg',
    IMAGETYPE_PNG  => 'png',
    IMAGETYPE_GIF  => 'gif',
    IMAGETYPE_WEBP => 'webp',
];
if (!isset($allowedExt[$info[2]])) up_fail(400, '仅支持 JPG / PNG / GIF / WEBP。');
$ext = $allowedExt[$info[2]];

// 文件名清洗：去目录、去危险字符，只保留中英文数字及常见安全标点空格
$orig = (string)$f['name'];
$base = pathinfo($orig, PATHINFO_FILENAME);
$base = preg_replace('~[\\\\/]~', '', $base) ?? '';          // 绝不能带路径
$base = str_replace(["\0", '?', '*', '"', '<', '>', '|', ':', '`'], '', $base);
$base = trim($base);
if ($base === '') $base = 'image-' . date('Ymd-His');
// 限制长度，避免超长文件名
if (mb_strlen($base) > 80) $base = mb_substr($base, 0, 80);
$safeBase = $base;

if (!is_dir(IMAGES_DIR)) @mkdir(IMAGES_DIR, 0775, true);
if (!is_dir(IMAGES_DIR) || !is_writable(IMAGES_DIR)) up_fail(500, '图库目录不可写：content/images。');

// 防覆盖：同名已存在则追加 -2/-3…
$name = $safeBase . '.' . $ext;
$dest = IMAGES_DIR . '/' . $name;
$i = 2;
while (file_exists($dest)) {
    $name = $safeBase . '-' . $i . '.' . $ext;
    $dest = IMAGES_DIR . '/' . $name;
    $i++;
    if ($i > 999) up_fail(500, '同名文件过多，无法落盘。');
}

if (!move_uploaded_file($f['tmp_name'], $dest)) up_fail(500, '保存到图库失败。');
@chmod($dest, 0664);

$url = IMG_URL . '/' . str_replace('%2F', '/', rawurlencode($name));
echo json_encode(['ok' => true, 'name' => $name, 'url' => $url, 'size' => (int)filesize($dest),
                  'w' => (int)$info[0], 'h' => (int)$info[1]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
