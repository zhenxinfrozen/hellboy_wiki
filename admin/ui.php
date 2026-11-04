<?php
/**
 * 后台 UI 门面 - 基于 SharedAdmin 通用架构套件
 */
require_once __DIR__ . '/bootstrap.php';
require_once ROOT . '/shared-admin/src/AdminCore.php';

use SharedAdmin\AdminCore;

// 初始化通用后台配置
AdminCore::init([
    'site_title'     => 'HELLBOY WIKI',
    'site_subtitle'  => '词条管理控制台',
    'assets_url'     => '/shared-admin/assets',
    'home_url'       => '/',
    'logout_url'     => 'logout.php',
    'current_user'   => [
        'name'  => $_SESSION['admin_user'] ?? 'Wiki Admin',
        'role'  => '维基管理员',
        'email' => 'admin@hellboy.wiki'
    ],
    'menu' => [
        ['type' => 'item', 'key' => 'dashboard', 'title' => '控制台', 'url' => 'index.php', 'icon' => 'home'],
        ['type' => 'section', 'title' => '内容与词条'],
        ['type' => 'item', 'key' => 'entries', 'title' => '条目管理', 'url' => 'entries.php', 'icon' => 'file-text'],
        ['type' => 'item', 'key' => 'glossary', 'title' => '术语表', 'url' => 'glossary.php', 'icon' => 'book-open'],
        ['type' => 'item', 'key' => 'redirects', 'title' => '重定向 / 别名', 'url' => 'redirects.php', 'icon' => 'shuffle'],
        ['type' => 'section', 'title' => '系统与质量'],
        ['type' => 'item', 'key' => 'audit', 'title' => '汉化体检', 'url' => 'audit.php', 'icon' => 'activity'],
    ]
]);

function admin_nav(string $cur): string {
    // 兼容可能直接调用 admin_nav 的旧代码
    return '';
}

function admin_head(string $title, string $cur = ''): void {
    $f = admin_flash();
    $flashData = $f ? ['msg' => $f['m'], 'type' => $f['k']] : null;

    $actions = [];
    if ($cur === 'dashboard') {
        $actions[] = ['title' => '管理条目', 'url' => 'entries.php', 'icon' => 'list', 'class' => 'btn-outline-primary btn-sm'];
        $actions[] = ['title' => '新建条目', 'url' => 'entry_edit.php', 'icon' => 'plus', 'class' => 'btn-primary btn-sm'];
    } elseif ($cur === 'entries') {
        $actions[] = ['title' => '新建条目', 'url' => 'entry_edit.php', 'icon' => 'plus', 'class' => 'btn-primary btn-sm'];
    }

    AdminCore::renderHeader([
        'title'        => $title,
        'subtitle'     => 'Hellboy Wiki 知识库维护管理',
        'current_page' => $cur,
        'actions'      => $actions,
        'flash'        => $flashData,
    ]);
?>
<style>
/* ============ Hellboy Wiki 通用表格与控件补齐 ============ */
table.grid {
    border-collapse: separate;
    border-spacing: 0;
    width: 100%;
    background: #fff;
    border: 1px solid var(--border-color, #cdd6e0);
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.06);
    margin-bottom: 24px;
}
table.grid th, table.grid td {
    border-bottom: 1px solid var(--border-light, #e2e8f0);
    padding: 10px 14px;
    text-align: left;
    font-size: 13.5px;
    vertical-align: middle;
}
table.grid th {
    background: #f8fafc;
    font-weight: 600;
    color: #1f2b3a;
}
table.grid tr:last-child td { border-bottom: 0; }
table.grid tbody tr:hover { background: #f8fbff; }

.pill {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 20px;
    background: #eef2f7;
    font-size: 12px;
    color: #6b7480;
    font-weight: 500;
    text-decoration: none;
    line-height: 1.5;
    transition: all 0.15s ease;
}
.pill:hover { background: #e2e8f0; color: #1f2b3a; }
.pill.ok { background: #e7f6ee; color: #1a7f4b; }
.pill.warn { background: #fdf1e3; color: #b25e09; }
.pill.err { background: #fdecea; color: #c0392b; }
.muted { color: #6b7480 !important; }

.btn.sec { background: #eef2f7; color: #1f2b3a; box-shadow: none; }
.btn.sec:hover { background: #e2e8f0; }
.btn.danger { background: #c0392b; color: #fff; }
.btn.ghost { background: transparent; color: #6b7480; box-shadow: none; }
.btn.ghost:hover { background: #eef2f7; color: #1f2b3a; }

input[type=text], input[type=password], input[type=search], input[type=url], input[type=date], select, textarea {
    font: inherit;
    padding: 8px 12px;
    border: 1px solid #cdd6e0;
    border-radius: 8px;
    width: 100%;
    background: #fff;
    color: #1f2b3a;
    transition: border-color .15s, box-shadow .15s;
}
input:focus, select:focus, textarea:focus {
    outline: none;
    border-color: #0073aa;
    box-shadow: 0 0 0 3px rgba(0, 115, 170, 0.12);
}

/* ============ Hellboy Wiki 专属：条目编辑器与弹窗样式 ============ */
.tabs{display:flex;gap:4px;margin:0 0 0}
.tabs a{padding:8px 16px;border:1px solid var(--border-color);border-bottom:0;border-radius:9px 9px 0 0;background:#f3f6fa;font-size:13.5px}
.tabs a.on{background:var(--bg-secondary);font-weight:600;color:var(--primary-color);box-shadow:0 -2px 8px rgba(20,40,70,.04)}
.preview{border:1px solid var(--border-color);border-radius:0 0 10px 10px;background:var(--bg-secondary);padding:18px 22px;min-height:40vh;box-shadow:var(--shadow-light)}
.preview img{max-width:160px;border:1px solid var(--border-color);border-radius:5px;margin:4px}
.preview table{border-collapse:collapse;margin:6px 0}
.preview td,.preview th{border:1px solid var(--border-color);padding:4px 8px}
.login-box{max-width:360px;margin:9vh auto;background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:16px;padding:30px;box-shadow:var(--shadow-heavy)}
code{background:#eef1f5;padding:1px 5px;border-radius:4px}

.ee{display:grid;grid-template-columns:minmax(0,1fr) 340px;gap:22px;align-items:start}
.ee-main{min-width:0}
.ee-side{position:sticky;top:20px}
@media(max-width:1080px){.ee{grid-template-columns:1fr}.ee-side{position:static}}
.ee-panel{background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:var(--radius-medium);box-shadow:var(--shadow-light);padding:16px 18px;margin-bottom:18px}
.ee-panel > h3{margin:0 0 12px;font-size:13px;text-transform:uppercase;letter-spacing:.6px;color:var(--text-secondary);font-weight:600;display:flex;align-items:center;gap:8px}
.ee-panel > h3 .hint{text-transform:none;letter-spacing:0;font-weight:400;margin-left:auto}
.ee-fields{display:grid;grid-template-columns:1fr 1fr;gap:0 14px}
.ee-fields .full{grid-column:1/-1}
@media(max-width:560px){.ee-fields{grid-template-columns:1fr}}
.mdbar{display:flex;flex-wrap:wrap;gap:3px;padding:7px;border:1px solid var(--border-color);border-bottom:0;border-radius:9px 9px 0 0;background:#f7f9fc;position:sticky;top:0;z-index:5}
.mdbar button{border:0;background:transparent;border-radius:6px;min-width:32px;height:30px;padding:0 8px;cursor:pointer;font-size:13.5px;color:var(--text-primary);display:inline-flex;align-items:center;justify-content:center}
.mdbar button:hover{background:rgba(0,115,170,0.1);color:var(--primary-color)}
.mdbar .sep{width:1px;background:var(--border-color);margin:3px 4px}
.ee-textarea{border-radius:0 0 9px 9px !important;min-height:52vh;resize:vertical}
.ee-status{display:flex;gap:14px;flex-wrap:wrap;margin-top:8px;font-size:12px;color:var(--text-secondary)}
.ee-status b{color:var(--text-primary);font-weight:600}
.ee-status .bad{color:var(--error-color);font-weight:600}

.meta-row{display:grid;grid-template-columns:92px 1fr;grid-template-areas:"lab val";gap:0;align-items:stretch;border:1px solid var(--border-color);border-radius:9px;margin-bottom:6px;background:#fff;transition:border-color .13s,box-shadow .13s;position:relative}
.meta-row:hover{border-color:#c7d4e6}
.meta-row:focus-within{border-color:var(--primary-color);box-shadow:0 0 0 2px rgba(0,115,170,0.15)}
.meta-row>input[data-k=label]{grid-area:lab;width:100%;min-width:0;border:0;border-right:1px solid var(--border-color);background:#f5f7fa;color:var(--text-primary);font-weight:600;font-size:12.5px;padding:9px 9px;text-align:left;border-radius:9px 0 0 9px}
.meta-row>input[data-k=label]:focus{outline:none;background:#eef3fa}
.mr-val{grid-area:val;display:flex;align-items:stretch;min-width:0}
.mv-edit{flex:1;min-width:0;min-height:38px;padding:8px 30px 8px 10px;font-size:13px;line-height:1.55;border:0;border-radius:0 9px 9px 0;background:transparent;cursor:text;word-break:break-word;position:relative}
.mv-edit:focus{outline:none;background:#fff}
.mv-line{min-height:20px}
.mv-chip{display:inline-flex;align-items:center;gap:3px;max-width:100%;margin:1px 3px 1px 0;padding:0 4px 0 9px;background:rgba(0,115,170,0.1);border:1px solid rgba(0,115,170,0.25);border-radius:999px;color:var(--primary-color);font-size:12.5px;line-height:1.7;vertical-align:baseline;user-select:none;cursor:default;white-space:nowrap}
.mv-chip-tx{white-space:nowrap}
.mv-chip-x{display:inline-flex;align-items:center;justify-content:center;width:16px;height:16px;border-radius:50%;color:var(--primary-color);font-size:13px;cursor:pointer;flex:none}
.mv-chip-x:hover{background:#d45a5a;color:#fff}
.mv-edit.is-empty::before{content:attr(data-ph);color:#a7b2c0;pointer-events:none;position:absolute;left:10px;top:8px}
.mr-link{flex:none;align-self:center;width:26px;height:26px;margin-right:4px;border:0;background:transparent;border-radius:6px;cursor:pointer;display:flex;align-items:center;justify-content:center;color:#8a98ab;opacity:.35;transition:.13s}
.meta-row:hover .mr-link,.meta-row:focus-within .mr-link{opacity:1}
.mr-link:hover{background:rgba(0,115,170,0.1);color:var(--primary-color)}
.meta-row .x{position:absolute;top:3px;right:3px;width:20px;height:20px;border:0;background:transparent;color:#b9c1cd;cursor:pointer;border-radius:50%;font-size:14px;line-height:1;opacity:0;transition:.13s;z-index:2}
.meta-row:hover .x{opacity:1}
.meta-row .x:hover{background:#fdecea;color:#b33}
.meta-add{display:flex;gap:8px;margin-top:6px}
.meta-add select{flex:1;font-size:12.5px;padding:7px 9px}

.cover{margin-top:6px}
.cover-filled[hidden],.cover-empty[hidden]{display:none}
.cover-filled{border:1px solid var(--border-color);border-radius:12px;overflow:hidden;background:#0b0f16}
.cover-card{display:block;width:100%;padding:0;border:0;background:#0b0f16;cursor:pointer;position:relative}
.cover-card img{display:block;width:100%;height:auto;max-height:380px;object-fit:contain}
.cover-hover{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(13,20,30,.42);opacity:0;transition:opacity .16s}
.cover-card:hover .cover-hover{opacity:1}
.cover-card:focus-visible{outline:2px solid var(--primary-color);outline-offset:-2px}
.ch-badge{background:rgba(255,255,255,.94);color:#20304a;font-size:12.5px;font-weight:600;padding:7px 14px;border-radius:999px;box-shadow:0 2px 10px rgba(0,0,0,.25)}
.cover-foot{display:flex;align-items:center;gap:6px;background:#fff;padding:7px 10px;border-top:1px solid var(--border-color)}
.cover-filename{flex:1;min-width:0;font-size:11.5px;color:var(--text-secondary);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.cover-change,.cover-remove{border:0;background:transparent;font-size:11.5px;cursor:pointer;padding:3px 7px;border-radius:6px}
.cover-change{color:var(--primary-color)}
.cover-change:hover{background:rgba(0,115,170,0.1)}
.cover-remove{color:#b3403a}
.cover-remove:hover{background:#fdecea}
.cover-empty{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4px;width:100%;border:1.5px dashed var(--border-color);border-radius:12px;padding:26px 16px;background:#fafcfe;color:var(--text-secondary);cursor:pointer;transition:.15s}
.cover-empty:hover,.cover-empty.drag{border-color:var(--primary-color);background:rgba(0,115,170,0.05);color:var(--primary-color)}
.cover-empty .ce-icon{width:30px;height:30px;margin-bottom:4px;color:#93a1b5}
.cover-empty .ce-main{font-size:13px;font-weight:600;color:var(--text-primary)}
.cover-empty:hover .ce-main,.cover-empty.drag .ce-main{color:var(--primary-color)}
.cover-empty .ce-sub{font-size:11px;opacity:.85}
.cover-empty.drag{box-shadow:0 0 0 3px rgba(0,115,170,.15)}

.cat-chips{display:flex;flex-wrap:wrap;gap:6px;margin-top:6px}
.cat-chip{display:inline-flex;align-items:center;gap:5px;background:rgba(0,115,170,0.1);color:var(--primary-color);border-radius:20px;padding:3px 6px 3px 11px;font-size:12px}
.cat-chip button{border:0;background:transparent;color:var(--primary-color);cursor:pointer;font-size:13px;line-height:1;padding:0 2px}

.live-ib{margin-top:14px}
.ib-card{background:#fff;border:1px solid var(--border-color);border-radius:10px;overflow:hidden;box-shadow:var(--shadow-light)}
.ib-head{background:#23282d;color:#fff;text-align:center;font-weight:700;font-size:13.5px;padding:7px}
.ib-figure img{display:block;width:100%;height:auto}
.ib-figure.is-placeholder{background:#0b0f16}
.ib-table{width:100%;border-collapse:collapse;font-size:12.5px}
.ib-table th{width:38%;background:#f3f6fa;text-align:left;vertical-align:top;padding:6px 8px;border-top:1px solid var(--border-color);color:var(--text-primary);font-weight:600}
.ib-table td{padding:6px 8px;border-top:1px solid var(--border-color);vertical-align:top}
.ib-table a{color:var(--primary-color)}
.live-ib .live-empty{color:var(--text-secondary);font-size:12px;text-align:center;padding:10px}

.link-pop{position:absolute;z-index:50;background:#fff;border:1px solid var(--border-color);border-radius:10px;box-shadow:var(--shadow-heavy);width:340px;max-height:320px;display:none;overflow:hidden;flex-direction:column}
.link-pop.open{display:flex}
.link-pop .lp-search{padding:10px;border-bottom:1px solid var(--border-color)}
.link-pop .lp-list{overflow:auto}
.link-pop .lp-item{padding:8px 12px;cursor:pointer;border-bottom:1px solid #f0f3f7;font-size:13px}
.link-pop .lp-item:hover,.link-pop .lp-item.active{background:rgba(0,115,170,0.1)}
.link-pop .lp-item .en{color:var(--text-secondary);font-size:11.5px;display:block}
.link-pop .lp-empty{padding:16px;text-align:center;color:var(--text-secondary);font-size:12.5px}

.ee-foot{display:flex;gap:10px;align-items:center;flex-wrap:wrap;position:sticky;bottom:14px;z-index:20;background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:12px;box-shadow:var(--shadow-medium);padding:12px 16px;margin:4px 0 8px}
.ee-foot .sp{flex:1}

.pv-head{display:flex;align-items:baseline;gap:12px;margin:6px 0 12px;flex-wrap:wrap}
.pv-head h2{margin:0;font-size:17px}
.preview-wrap{display:grid;grid-template-columns:minmax(0,1fr) 300px;gap:24px;align-items:start}
@media(max-width:900px){.preview-wrap{grid-template-columns:1fr}}
.pv-body{background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:var(--radius-medium);box-shadow:var(--shadow-light);padding:22px 26px;line-height:1.75;font-size:14.5px}
.pv-title{font-size:24px;margin:0 0 14px}
.pv-body h2,.pv-body h3{margin:18px 0 8px}
.pv-body img{max-width:100%;border-radius:6px}
.pv-body a{color:var(--primary-color)}
.pv-side{position:sticky;top:20px}
.pv-side .ib-noimg{height:150px;display:flex;align-items:center;justify-content:center;color:#8a97a8;font-size:12.5px;background:#0b0f16}
@media(max-width:900px){.pv-side{position:static;max-width:340px}}

body.modal-open{overflow:hidden}
.modal-overlay{position:fixed;inset:0;z-index:1000;background:rgba(15,23,36,.5);display:flex;align-items:center;justify-content:center;padding:24px}
.modal-overlay[hidden]{display:none}
.modal{background:var(--bg-secondary);border-radius:14px;box-shadow:var(--shadow-heavy);width:min(860px,100%);max-height:88vh;display:flex;flex-direction:column;overflow:hidden}
.m-head{display:flex;align-items:center;padding:15px 18px;border-bottom:1px solid var(--border-color)}
.m-head b{font-size:15px}
.m-close{margin-left:auto;border:0;background:transparent;font-size:22px;line-height:1;color:var(--text-secondary);cursor:pointer;padding:0 4px}
.m-close:hover{color:var(--text-primary)}
.m-tabs{display:flex;gap:4px;padding:10px 14px 0;border-bottom:1px solid var(--border-color)}
.m-tab{border:0;background:transparent;padding:9px 16px;font-size:13.5px;color:var(--text-secondary);cursor:pointer;border-bottom:2px solid transparent;border-radius:8px 8px 0 0}
.m-tab:hover{background:rgba(0,115,170,0.1);color:var(--primary-color)}
.m-tab.on{color:var(--primary-color);border-bottom-color:var(--primary-color);font-weight:600}
.m-body{flex:1;overflow:auto;padding:18px}
.m-drop{border:2px dashed var(--border-color);border-radius:14px;padding:40px 20px;text-align:center;cursor:pointer;background:#fafcfe;transition:.15s}
.m-drop:hover,.m-drop.drag{border-color:var(--primary-color);background:rgba(0,115,170,0.05)}
.m-drop .md-icon{font-size:34px;color:var(--primary-color);margin-bottom:8px}
.m-drop .md-main{font-size:14.5px;font-weight:600;color:var(--text-primary)}
.m-drop .md-sub{font-size:12px;color:var(--text-secondary);margin-top:5px}
.m-upresult[hidden]{display:none}
.m-upresult{display:flex;gap:14px;align-items:center;margin-top:14px;border:1px solid var(--border-color);border-radius:10px;padding:12px;background:#fff}
.m-upresult img{width:120px;height:90px;object-fit:cover;border-radius:8px;border:1px solid var(--border-color);flex:none;background:#0b0f16}
.mur-info{min-width:0}
.mur-info b{font-size:13.5px;word-break:break-all}
.mur-hint{margin-top:6px;font-size:12px;color:#2e7d32}
.m-libsearch{display:flex;gap:10px;align-items:center;margin-bottom:12px}
.m-libsearch input{flex:1;min-width:0}
.m-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(128px,1fr));gap:10px}
.m-cell{border:2px solid var(--border-color);border-radius:10px;overflow:hidden;cursor:pointer;background:#fff;transition:.12s;display:flex;flex-direction:column}
.m-cell:hover{border-color:var(--primary-color);transform:translateY(-1px)}
.m-cell.selected{border-color:var(--primary-color);box-shadow:0 0 0 2px rgba(0,115,170,.25)}
.m-cell img{width:100%;height:96px;object-fit:cover;display:block;background:#0b0f16}
.m-cap{font-size:11px;color:var(--text-secondary);padding:5px 7px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.m-cell.selected .m-cap{color:var(--primary-color);font-weight:600}
.m-empty{grid-column:1/-1;text-align:center;color:var(--text-secondary);font-size:13px;padding:36px 0}
.m-morewrap{text-align:center;margin-top:14px}
.m-foot{display:flex;align-items:center;gap:10px;padding:13px 18px;border-top:1px solid var(--border-color);background:#f8fafc}
.m-foot .sp{flex:1}
.m-selinfo{font-size:12.5px;color:var(--text-secondary);max-width:50%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.m-foot .btn:disabled{opacity:.5;cursor:not-allowed}
</style>
<?php
}

function admin_foot(): void {
    AdminCore::renderFooter();
}
