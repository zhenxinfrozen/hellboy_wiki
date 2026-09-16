<?php
require_once __DIR__ . '/bootstrap.php';
function admin_nav(string $cur): string {
    $items = [
        ['index.php', 'dashboard', '仪表盘'],
        ['entries.php', 'entries', '条目'],
        ['glossary.php', 'glossary', '术语表'],
        ['redirects.php', 'redirects', '重定向 / 别名'],
        ['audit.php', 'audit', '体检'],
    ];
    $h = '<nav class="adm-side">';
    foreach ($items as [$u,$k,$label]) {
        $on = $cur===$k ? ' on' : '';
        $h .= '<a class="adm-navi'.$on.'" href="'.h($u).'">'.$label.'</a>';
    }
    $h .= '<a class="adm-navi logout" href="logout.php">退出登录</a>';
    $h .= '</nav>';
    return $h;
}
function admin_head(string $title, string $cur = ''): void {
    $f = admin_flash();
    ?><!doctype html>
<html lang="zh-CN"><head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($title) ?> · 后台</title>
<style>
:root{
  --blue:#2f6db3;--blue-d:#24568f;--blue-l:#eaf2fb;
  --ink:#1f2b3a;--muted:#6b7480;--line:#e2e8f0;--line-2:#cdd6e0;
  --bg:#eef2f7;--panel:#ffffff;--ok:#1a7f4b;--warn:#b25e09;--err:#c0392b;
  --shadow:0 1px 2px rgba(20,40,70,.05),0 4px 16px rgba(20,40,70,.06);
  --shadow-lg:0 8px 30px rgba(20,40,70,.14);
  --radius:12px;
}
*{box-sizing:border-box}
body{margin:0;font:14px/1.55 -apple-system,"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif;color:var(--ink);background:var(--bg);-webkit-font-smoothing:antialiased}
a{color:var(--blue);text-decoration:none}
.adm-top{height:54px;background:linear-gradient(90deg,#1c2940,#243754);color:#fff;display:flex;align-items:center;gap:14px;padding:0 20px;box-shadow:var(--shadow)}
.adm-top b{font-size:15.5px;letter-spacing:.2px}
.adm-top .sp{flex:1}
.adm-top a{color:#c4d2e6;font-size:13px;padding:5px 11px;border:1px solid rgba(255,255,255,.14);border-radius:20px;transition:.15s}
.adm-top a:hover{background:rgba(255,255,255,.12);color:#fff}
.adm-wrap{display:flex;min-height:calc(100vh - 54px)}
.adm-side{width:176px;background:var(--panel);border-right:1px solid var(--line);padding:16px 0;display:flex;flex-direction:column;position:sticky;top:54px;height:calc(100vh - 54px)}
.adm-side .adm-brand{padding:0 18px 12px;font-size:11px;text-transform:uppercase;letter-spacing:.8px;color:var(--muted)}
.adm-navi{padding:10px 18px;color:var(--ink);border-left:3px solid transparent;font-size:13.5px;transition:.12s}
.adm-navi:hover{background:#f3f7fc;color:var(--blue)}
.adm-navi.on{background:var(--blue-l);border-left-color:var(--blue);color:var(--blue);font-weight:600}
.adm-navi.logout{margin-top:auto;color:var(--muted)}
.adm-main{flex:1;padding:26px 30px 60px;max-width:1240px;min-width:0}
h1{font-size:21px;margin:0 0 18px;font-weight:700;letter-spacing:.2px}
h2{font-size:15px;margin:24px 0 10px}
.cards{display:flex;gap:14px;flex-wrap:wrap}
.card{background:var(--panel);border:1px solid var(--line);border-radius:var(--radius);padding:16px 18px;min-width:150px;box-shadow:var(--shadow)}
.card .n{font-size:26px;font-weight:700}
.card .l{color:var(--muted);font-size:12.5px}
table.grid{border-collapse:collapse;width:100%;background:var(--panel);border:1px solid var(--line);border-radius:10px;overflow:hidden;box-shadow:var(--shadow)}
table.grid th,table.grid td{border-bottom:1px solid var(--line);padding:9px 12px;text-align:left;font-size:13px}
table.grid th{background:#f5f8fc;font-weight:600}
table.grid tr:last-child td{border-bottom:0}
table.grid tbody tr:hover{background:#f8fbff}
input[type=text],input[type=password],input[type=search],input[type=url],input[type=date],select,textarea{font:inherit;padding:9px 11px;border:1px solid var(--line-2);border-radius:8px;width:100%;background:#fff;color:var(--ink);transition:border-color .12s,box-shadow .12s}
input:focus,select:focus,textarea:focus{outline:none;border-color:var(--blue);box-shadow:0 0 0 3px rgba(47,109,179,.13)}
textarea{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:12.8px;line-height:1.65}
label{font-size:12.5px;color:var(--muted);display:block;margin:12px 0 5px;font-weight:500}
.btn{display:inline-flex;align-items:center;gap:6px;border:0;background:var(--blue);color:#fff;padding:9px 18px;border-radius:8px;cursor:pointer;font-size:13.5px;font-weight:500;transition:.14s;box-shadow:var(--shadow)}
.btn:hover{background:var(--blue-d)}
.btn.sec{background:#eef2f7;color:var(--ink);box-shadow:none}
.btn.sec:hover{background:#e2e8f0}
.btn.danger{background:var(--err)}
.btn.ghost{background:transparent;color:var(--muted);box-shadow:none}
.btn.ghost:hover{background:#eef2f7;color:var(--ink)}
.btn.sm{padding:5px 11px;font-size:12.5px;border-radius:7px}
.row{display:flex;gap:12px;align-items:center;flex-wrap:wrap}
.flash{padding:11px 15px;border-radius:10px;margin:0 0 16px;font-size:13.5px;box-shadow:var(--shadow)}
.flash.ok{background:#e7f6ee;color:var(--ok);border:1px solid #bfe6d0}
.flash.err{background:#fdecea;color:var(--err);border:1px solid #f5c6c0}
.flash.info{background:#eef4fb;color:var(--blue);border:1px solid #cfe0f3}
.muted{color:var(--muted)}
.pill{display:inline-block;padding:2px 9px;border-radius:20px;background:#eef2f7;font-size:11.5px;color:var(--muted)}
.pill.ok{background:#e7f6ee;color:var(--ok)}
.pill.warn{background:#fdf1e3;color:var(--warn)}
.tabs{display:flex;gap:4px;margin:0 0 0}
.tabs a{padding:8px 16px;border:1px solid var(--line);border-bottom:0;border-radius:9px 9px 0 0;background:#f3f6fa;font-size:13.5px}
.tabs a.on{background:var(--panel);font-weight:600;color:var(--blue);box-shadow:0 -2px 8px rgba(20,40,70,.04)}
.preview{border:1px solid var(--line);border-radius:0 0 10px 10px;background:var(--panel);padding:18px 22px;min-height:40vh;box-shadow:var(--shadow)}
.preview img{max-width:160px;border:1px solid var(--line);border-radius:5px;margin:4px}
.preview table{border-collapse:collapse;margin:6px 0}
.preview td,.preview th{border:1px solid var(--line);padding:4px 8px}
.login-box{max-width:360px;margin:9vh auto;background:var(--panel);border:1px solid var(--line);border-radius:16px;padding:30px;box-shadow:var(--shadow-lg)}
code{background:#eef1f5;padding:1px 5px;border-radius:4px}

/* ============ 结构化条目编辑器（复刻前台：左正文 / 右信息框） ============ */
.ee{display:grid;grid-template-columns:minmax(0,1fr) 340px;gap:22px;align-items:start}
.ee-main{min-width:0}
.ee-side{position:sticky;top:20px}
@media(max-width:1080px){.ee{grid-template-columns:1fr}.ee-side{position:static}}
.ee-panel{background:var(--panel);border:1px solid var(--line);border-radius:var(--radius);box-shadow:var(--shadow);padding:16px 18px;margin-bottom:18px}
.ee-panel > h3{margin:0 0 12px;font-size:13px;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);font-weight:600;display:flex;align-items:center;gap:8px}
.ee-panel > h3 .hint{text-transform:none;letter-spacing:0;font-weight:400;margin-left:auto}
.ee-fields{display:grid;grid-template-columns:1fr 1fr;gap:0 14px}
.ee-fields .full{grid-column:1/-1}
@media(max-width:560px){.ee-fields{grid-template-columns:1fr}}
/* 工具栏 */
.mdbar{display:flex;flex-wrap:wrap;gap:3px;padding:7px;border:1px solid var(--line-2);border-bottom:0;border-radius:9px 9px 0 0;background:#f7f9fc;position:sticky;top:0;z-index:5}
.mdbar button{border:0;background:transparent;border-radius:6px;min-width:32px;height:30px;padding:0 8px;cursor:pointer;font-size:13.5px;color:var(--ink);display:inline-flex;align-items:center;justify-content:center}
.mdbar button:hover{background:var(--blue-l);color:var(--blue)}
.mdbar .sep{width:1px;background:var(--line);margin:3px 4px}
.ee-textarea{border-radius:0 0 9px 9px !important;min-height:52vh;resize:vertical}
.ee-status{display:flex;gap:14px;flex-wrap:wrap;margin-top:8px;font-size:12px;color:var(--muted)}
.ee-status b{color:var(--ink);font-weight:600}
.ee-status .bad{color:var(--err);font-weight:600}
/* 属性行 */
/* ========== 属性行（信息框式：左灰标签 / 右内容，悬停浮现操作） ========== */
.meta-row{display:grid;grid-template-columns:92px 1fr;grid-template-areas:"lab val";gap:0;align-items:stretch;border:1px solid var(--line);border-radius:9px;margin-bottom:6px;background:#fff;transition:border-color .13s,box-shadow .13s;position:relative}
.meta-row:hover{border-color:#c7d4e6}
.meta-row:focus-within{border-color:var(--blue);box-shadow:0 0 0 2px var(--blue-l)}
.meta-row>input[data-k=label]{grid-area:lab;width:100%;min-width:0;border:0;border-right:1px solid var(--line);background:#f5f7fa;color:var(--ink);font-weight:600;font-size:12.5px;padding:9px 9px;text-align:left;border-radius:9px 0 0 9px}
.meta-row>input[data-k=label]:focus{outline:none;background:#eef3fa}
.mr-val{grid-area:val;display:flex;align-items:stretch;min-width:0}
.mv-edit{flex:1;min-width:0;min-height:38px;padding:8px 30px 8px 10px;font-size:13px;line-height:1.55;border:0;border-radius:0 9px 9px 0;background:transparent;cursor:text;word-break:break-word;position:relative}
.mv-edit:focus{outline:none;background:#fff}
.mv-line{min-height:20px}
.mv-chip{display:inline-flex;align-items:center;gap:3px;max-width:100%;margin:1px 3px 1px 0;padding:0 4px 0 9px;background:var(--blue-l,#e8f0fe);border:1px solid #b9d2f7;border-radius:999px;color:#1a4f9b;font-size:12.5px;line-height:1.7;vertical-align:baseline;user-select:none;cursor:default;white-space:nowrap}
.mv-chip-tx{white-space:nowrap}
.mv-chip-x{display:inline-flex;align-items:center;justify-content:center;width:16px;height:16px;border-radius:50%;color:#5b86c4;font-size:13px;cursor:pointer;flex:none}
.mv-chip-x:hover{background:#d45a5a;color:#fff}
.mv-edit.is-empty::before{content:attr(data-ph);color:#a7b2c0;pointer-events:none;position:absolute;left:10px;top:8px}
/* 操作钮：默认半透明，悬停整行才清晰 */
.mr-link{flex:none;align-self:center;width:26px;height:26px;margin-right:4px;border:0;background:transparent;border-radius:6px;cursor:pointer;display:flex;align-items:center;justify-content:center;color:#8a98ab;opacity:.35;transition:.13s}
.meta-row:hover .mr-link,.meta-row:focus-within .mr-link{opacity:1}
.mr-link:hover{background:var(--blue-l);color:var(--blue-d)}
.meta-row .x{position:absolute;top:3px;right:3px;width:20px;height:20px;border:0;background:transparent;color:#b9c1cd;cursor:pointer;border-radius:50%;font-size:14px;line-height:1;opacity:0;transition:.13s;z-index:2}
.meta-row:hover .x{opacity:1}
.meta-row .x:hover{background:#fdecea;color:#b33}
.meta-add{display:flex;gap:8px;margin-top:6px}
.meta-add select{flex:1;font-size:12.5px;padding:7px 9px}
/* ========== 特色图（单一入口：点卡片开「上传/媒体库」弹窗） ========== */
.cover{margin-top:6px}
.cover-filled[hidden],.cover-empty[hidden]{display:none}
.cover-filled{border:1px solid var(--line);border-radius:12px;overflow:hidden;background:#0b0f16}
.cover-card{display:block;width:100%;padding:0;border:0;background:#0b0f16;cursor:pointer;position:relative}
.cover-card img{display:block;width:100%;height:auto;max-height:380px;object-fit:contain}
.cover-hover{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(13,20,30,.42);opacity:0;transition:opacity .16s}
.cover-card:hover .cover-hover{opacity:1}
.cover-card:focus-visible{outline:2px solid var(--blue);outline-offset:-2px}
.ch-badge{background:rgba(255,255,255,.94);color:#20304a;font-size:12.5px;font-weight:600;padding:7px 14px;border-radius:999px;box-shadow:0 2px 10px rgba(0,0,0,.25)}
.cover-foot{display:flex;align-items:center;gap:6px;background:#fff;padding:7px 10px;border-top:1px solid var(--line)}
.cover-filename{flex:1;min-width:0;font-size:11.5px;color:var(--muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.cover-change,.cover-remove{border:0;background:transparent;font-size:11.5px;cursor:pointer;padding:3px 7px;border-radius:6px}
.cover-change{color:var(--blue-d)}
.cover-change:hover{background:var(--blue-l)}
.cover-remove{color:#b3403a}
.cover-remove:hover{background:#fdecea}
.cover-empty{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4px;width:100%;border:1.5px dashed var(--line-2);border-radius:12px;padding:26px 16px;background:#fafcfe;color:var(--muted);cursor:pointer;transition:.15s}
.cover-empty:hover,.cover-empty.drag{border-color:var(--blue);background:var(--blue-l);color:var(--blue-d)}
.cover-empty .ce-icon{width:30px;height:30px;margin-bottom:4px;color:#93a1b5}
.cover-empty .ce-main{font-size:13px;font-weight:600;color:var(--ink)}
.cover-empty:hover .ce-main,.cover-empty.drag .ce-main{color:var(--blue-d)}
.cover-empty .ce-sub{font-size:11px;opacity:.85}
.cover-empty.drag{box-shadow:0 0 0 3px rgba(64,120,200,.15)}
.cover-status{margin-top:8px;font-size:12px;color:var(--blue);background:var(--blue-l);border:1px solid var(--blue);border-radius:7px;padding:7px 10px}
.up-progress{font-size:12.5px;color:var(--blue);background:var(--blue-l);border:1px solid var(--blue);border-radius:7px;padding:8px 10px;margin-bottom:10px}
/* 分类 chips */
.cat-chips{display:flex;flex-wrap:wrap;gap:6px;margin-top:6px}
.cat-chip{display:inline-flex;align-items:center;gap:5px;background:var(--blue-l);color:var(--blue-d);border-radius:20px;padding:3px 6px 3px 11px;font-size:12px}
.cat-chip button{border:0;background:transparent;color:var(--blue-d);cursor:pointer;font-size:13px;line-height:1;padding:0 2px}
/* 实时信息框预览（1:1 复刻前台 .ib-card） */
.live-ib{margin-top:14px}
.ib-card{background:#fff;border:1px solid var(--line);border-radius:10px;overflow:hidden;box-shadow:var(--shadow)}
.ib-head{background:#2b3b52;color:#fff;text-align:center;font-weight:700;font-size:13.5px;padding:7px}
.ib-figure img{display:block;width:100%;height:auto}
.ib-figure.is-placeholder{background:#0b0f16}
.ib-table{width:100%;border-collapse:collapse;font-size:12.5px}
.ib-table th{width:38%;background:#f3f6fa;text-align:left;vertical-align:top;padding:6px 8px;border-top:1px solid var(--line);color:var(--ink);font-weight:600}
.ib-table td{padding:6px 8px;border-top:1px solid var(--line);vertical-align:top}
.ib-table a{color:var(--blue)}
.live-ib .live-empty{color:var(--muted);font-size:12px;text-align:center;padding:10px}
/* 内链选择弹层 */
.link-pop{position:absolute;z-index:50;background:#fff;border:1px solid var(--line);border-radius:10px;box-shadow:var(--shadow-lg);width:340px;max-height:320px;display:none;overflow:hidden;flex-direction:column}
.link-pop.open{display:flex}
.link-pop .lp-search{padding:10px;border-bottom:1px solid var(--line)}
.link-pop .lp-list{overflow:auto}
.link-pop .lp-item{padding:8px 12px;cursor:pointer;border-bottom:1px solid #f0f3f7;font-size:13px}
.link-pop .lp-item:hover,.link-pop .lp-item.active{background:var(--blue-l)}
.link-pop .lp-item .en{color:var(--muted);font-size:11.5px;display:block}
.link-pop .lp-empty{padding:16px;text-align:center;color:var(--muted);font-size:12.5px}
.ee-foot{display:flex;gap:10px;align-items:center;flex-wrap:wrap;position:sticky;bottom:14px;z-index:20;background:var(--panel);border:1px solid var(--line);border-radius:12px;box-shadow:var(--shadow);padding:12px 16px;margin:4px 0 8px}
.ee-foot .sp{flex:1}
/* ===== 统一预览（正文+信息框，模拟前台两栏） ===== */
.pv-head{display:flex;align-items:baseline;gap:12px;margin:6px 0 12px;flex-wrap:wrap}
.pv-head h2{margin:0;font-size:17px}
.preview-wrap{display:grid;grid-template-columns:minmax(0,1fr) 300px;gap:24px;align-items:start}
@media(max-width:900px){.preview-wrap{grid-template-columns:1fr}}
.pv-body{background:var(--panel);border:1px solid var(--line);border-radius:var(--radius);box-shadow:var(--shadow);padding:22px 26px;line-height:1.75;font-size:14.5px}
.pv-title{font-size:24px;margin:0 0 14px}
.pv-body h2,.pv-body h3{margin:18px 0 8px}
.pv-body img{max-width:100%;border-radius:6px}
.pv-body a{color:var(--blue)}
.pv-side{position:sticky;top:20px}
.pv-side .ib-noimg{height:150px;display:flex;align-items:center;justify-content:center;color:#8a97a8;font-size:12.5px;background:#0b0f16}
@media(max-width:900px){.pv-side{position:static;max-width:340px}}
/* ===== 图片弹窗（上传 / 媒体库 双 Tab） ===== */
body.modal-open{overflow:hidden}
.modal-overlay{position:fixed;inset:0;z-index:100;background:rgba(15,23,36,.5);display:flex;align-items:center;justify-content:center;padding:24px}
.modal-overlay[hidden]{display:none}
.modal{background:var(--panel);border-radius:14px;box-shadow:0 20px 60px rgba(0,0,0,.35);width:min(860px,100%);max-height:88vh;display:flex;flex-direction:column;overflow:hidden}
.m-head{display:flex;align-items:center;padding:15px 18px;border-bottom:1px solid var(--line)}
.m-head b{font-size:15px}
.m-close{margin-left:auto;border:0;background:transparent;font-size:22px;line-height:1;color:var(--muted);cursor:pointer;padding:0 4px}
.m-close:hover{color:var(--ink)}
.m-tabs{display:flex;gap:4px;padding:10px 14px 0;border-bottom:1px solid var(--line)}
.m-tab{border:0;background:transparent;padding:9px 16px;font-size:13.5px;color:var(--muted);cursor:pointer;border-bottom:2px solid transparent;border-radius:8px 8px 0 0}
.m-tab:hover{background:var(--blue-l);color:var(--blue-d)}
.m-tab.on{color:var(--blue-d);border-bottom-color:var(--blue);font-weight:600}
.m-body{flex:1;overflow:auto;padding:18px}
.m-drop{border:2px dashed var(--line-2);border-radius:14px;padding:40px 20px;text-align:center;cursor:pointer;background:#fafcfe;transition:.15s}
.m-drop:hover,.m-drop.drag{border-color:var(--blue);background:var(--blue-l)}
.m-drop .md-icon{font-size:34px;color:var(--blue);margin-bottom:8px}
.m-drop .md-main{font-size:14.5px;font-weight:600;color:var(--ink)}
.m-drop .md-sub{font-size:12px;color:var(--muted);margin-top:5px}
.m-upresult[hidden]{display:none}
.m-upresult{display:flex;gap:14px;align-items:center;margin-top:14px;border:1px solid var(--line);border-radius:10px;padding:12px;background:#fff}
.m-upresult img{width:120px;height:90px;object-fit:cover;border-radius:8px;border:1px solid var(--line);flex:none;background:#0b0f16}
.mur-info{min-width:0}
.mur-info b{font-size:13.5px;word-break:break-all}
.mur-hint{margin-top:6px;font-size:12px;color:#2e7d32}
.m-libsearch{display:flex;gap:10px;align-items:center;margin-bottom:12px}
.m-libsearch input{flex:1;min-width:0}
.m-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(128px,1fr));gap:10px}
.m-cell{border:2px solid var(--line);border-radius:10px;overflow:hidden;cursor:pointer;background:#fff;transition:.12s;display:flex;flex-direction:column}
.m-cell:hover{border-color:var(--blue);transform:translateY(-1px)}
.m-cell.selected{border-color:var(--blue);box-shadow:0 0 0 2px rgba(64,120,200,.25)}
.m-cell img{width:100%;height:96px;object-fit:cover;display:block;background:#0b0f16}
.m-cap{font-size:11px;color:var(--muted);padding:5px 7px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.m-cell.selected .m-cap{color:var(--blue-d);font-weight:600}
.m-empty{grid-column:1/-1;text-align:center;color:var(--muted);font-size:13px;padding:36px 0}
.m-morewrap{text-align:center;margin-top:14px}
.m-foot{display:flex;align-items:center;gap:10px;padding:13px 18px;border-top:1px solid var(--line);background:#f8fafc}
.m-foot .sp{flex:1}
.m-selinfo{font-size:12.5px;color:var(--muted);max-width:50%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.m-foot .btn:disabled{opacity:.5;cursor:not-allowed}
</style></head>
<body>
<header class="adm-top"><b>地狱男爵维基 · 后台</b><span class="sp"></span>
<a target="_blank" href="/">查看前台 ↗</a></header>
<div class="adm-wrap">
<?php if (admin_logged_in()): ?><?= admin_nav($cur) ?><?php endif; ?>
<main class="adm-main">
<?php if ($f): ?><div class="flash <?= h($f['k']) ?>"><?= h($f['m']) ?></div><?php endif; ?>
<?php
}
function admin_foot(): void { ?>
</main></div></body></html><?php
}
