<?php
/**
 * Hellboy Viewer 18104 — Wiki.js 1:1 布局 + Wikipedia 门户内容
 * 纯 PHP + Markdown 文件，无数据库。前端控制器。
 */
declare(strict_types=1);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);

const ROOT      = __DIR__;
const CONTENT   = ROOT . '/content';
const PAGES     = CONTENT . '/pages';
const META      = CONTENT . '/meta';
const IMG       = '/content/images';
const SITE_NAME = '地狱男爵中文维基';
const PAGES_EN  = CONTENT . '/pages_en';   // 英文正文（Fandom 同步）
const CUSTOM    = CONTENT . '/custom';     // 自定义页面（Markdown）
const EN_TITLE_FILE = CONTENT . '/en_title.json'; // 本地标题 => Fandom 英文标题
const ALIAS_FILE    = CONTENT . '/aliases.json';  // 英文别名 => 中文正条（合并重复词条）
const EN_REDIRECT_FILE = CONTENT . '/en_redirects.json'; // Fandom英文名/常用名 => 本地中文正条（修英文内链红链）
const SLUGS_FILE = CONTENT . '/slugs.json'; // 本地标题 => 英文固定链接名（permalink，与界面语言无关）
const PORTAL_FILE = CONTENT . '/portal.json'; // 门户页：手写核心块 + 自动子分类规则（对齐 18100 分类页）

// 前台轻量管理员鉴权（读取 /admin 同一登录态，用于顶栏入口与条目内联编辑）
require_once ROOT . '/admin/front_auth.php';

// 静态资源版本号：取文件 mtime，改动后 URL 自动变化，强制浏览器重新拉取（破 7 天缓存）
@define('ASSET_VER_CSS', (string)(@filemtime(ROOT . '/assets/css/app.css') ?: 1));
@define('ASSET_VER_JS',  (string)(@filemtime(ROOT . '/assets/js/app.js')   ?: 1));

// 信息框字段标签：中文 => 英文
const IB_LABEL_EN = [
  // 简体（meta 大量使用，优先列全）
  '编剧'=>'Writer','作画'=>'Art','上色'=>'Colorist','嵌字'=>'Lettering','编辑'=>'Editor',
  '出版'=>'Published by','出版方'=>'Publisher','出版社'=>'Publisher','连载日期'=>'Publication date',
  '发行日期'=>'Publication date','出版日期'=>'Publication date','上映日期'=>'Release date',
  '合订本'=>'Collected edition','全集'=>'Collected in','期数'=>'Issues','篇数'=>'Issues',
  '性别'=>'Gender','种族'=>'Species','职业'=>'Occupation','本名'=>'Real name',
  '别称/身份'=>'Alias','别名'=>'Alias','称号'=>'Title','标题'=>'Title',
  '亲属'=>'Relatives','关系'=>'Relations','阵营'=>'Affiliation','创作者'=>'Created by',
  '漫画首秀'=>'First comic appearance','首次登场'=>'First appearance','首秀'=>'First appearance',
  '登场电影'=>'Films','饰演'=>'Portrayed by','导演'=>'Director','片长'=>'Running time',
  '身高'=>'Height','体重'=>'Weight','发色'=>'Hair','瞳色'=>'Eyes','肤色'=>'Skin',
  '出生'=>'Born','逝世'=>'Died','国籍'=>'Nationality','状态'=>'Status','能力'=>'Abilities',
  '基地'=>'Base of operations','地点'=>'Location','地理位置'=>'Geographic location','居民'=>'Residents',
  '主角'=>'Main character','类型'=>'Type','体型'=>'Body type','母星'=>'Homeworld',
  '系列'=>'Series','装帧'=>'Format','页数'=>'Pages','发行商'=>'Publisher','发行'=>'Published',
  '作者'=>'Author','改编'=>'Adapted by','执行制片'=>'Executive producers','剪辑'=>'Film editor',
  '对白剪辑'=>'Dialogue editing','音效设计'=>'Sound design','票房'=>'Box office','配乐'=>'Music',
  '制作预算'=>'Production budget','制片人'=>'Producer','开发商'=>'Developer','平台'=>'Platforms',
  '首发于'=>'Originally published in','馆藏版'=>'Omnibus edition','内部编号'=>'Internal numbering','登场'=>'Appeared in',
  // 繁体
  '创作者'=>'Created by','編劇'=>'Writer','畫師'=>'Artist','画师'=>'Artist',
  '著色'=>'Colorist','文字'=>'Lettering','營陣'=>'Affiliation','所屬'=>'Affiliation',
  '種族'=>'Race','性別'=>'Gender','體重'=>'Weight','髮色'=>'Hair','眼睛'=>'Eyes','膚色'=>'Skin',
  '國籍'=>'Nationality','職業'=>'Occupation','狀態'=>'Status','首次登場'=>'First appearance',
  '登場'=>'Appearances','親屬'=>'Relatives','關係'=>'Relations','盟友'=>'Allies','敵人'=>'Enemies',
  '發行'=>'Published','發行日期'=>'Publication date','發行商'=>'Publisher','裝幀'=>'Format',
  '頁數'=>'Pages','地點'=>'Location',
];
// 信息框类型头：中文 => 英文
const IB_TYPE_EN = [
  '角色'=>'Character','人物'=>'Character','漫畫故事'=>'Comic story','漫画故事'=>'Comic story',
  '故事'=>'Story','電影'=>'Film','电影'=>'Film','系列'=>'Series','漫畫系列'=>'Comic series','漫画系列'=>'Comic series',
  '合訂本'=>'Collected edition','單行本'=>'Collected edition','小說'=>'Novel','小说'=>'Novel',
  '遊戲'=>'Game','游戏'=>'Game','組織'=>'Organization','组织'=>'Organization','地點'=>'Location','地点'=>'Location',
  '種族'=>'Species','种族'=>'Species','物種'=>'Species','物种'=>'Species','物品'=>'Item','武器'=>'Weapon',
  '事件'=>'Event','創作者'=>'Creator','创作者'=>'Creator','畫師'=>'Artist','画师'=>'Artist','編劇'=>'Writer',
  '真实人物'=>'Person','圖書'=>'Book','图书'=>'Book','原聲專輯'=>'Soundtrack','原声专辑'=>'Soundtrack',
  '音頻劇'=>'Audio drama','音频剧'=>'Audio drama','消歧義'=>'Disambiguation','消歧义'=>'Disambiguation',
  '資料'=>'Reference','资料'=>'Reference',
];
// ===== LANG TOGGLE PATCH: 中英文切换 =====
// 显式 ?lang= 参数始终最高优先（en 或 zh 都认）；无参数时才看 cookie
$TV='zh-mt';
if (isset($_GET['lang']) && $_GET['lang']==='en') { $LANG='en'; $TV='en'; }
elseif (isset($_GET['lang'])) {                                    // 显式中文
    $LANG='zh';
    $TV = (($_GET['v'] ?? '')==='std') ? 'zh-std' : 'zh-mt';
} elseif (($_COOKIE['hb_lang'] ?? '')==='en') { $LANG='en'; $TV='en'; }
else { $LANG='zh'; $TV = (($_GET['v'] ?? '')==='std') ? 'zh-std' : 'zh-mt'; }
// v=std 也可独立表达标准中文版（不带 lang 时）
if ($LANG==='zh' && (($_GET['v'] ?? '')==='std')) $TV='zh-std';
if ($LANG==='en') { @setcookie('hb_lang','en',['path'=>'/','expires'=>time()+31536000,'samesite'=>'Lax']); }
else { @setcookie('hb_lang','zh',['path'=>'/','expires'=>time()+31536000,'samesite'=>'Lax']); }
function L(): string { global $LANG; return $LANG; }

// ===== 三层版本：EN（原始，不改）/ std 标准中文（人工·已固化）/ mt 机翻中文（默认）=====
const PAGES_MT   = CONTENT . '/pages_mt';              // 机翻中文草稿层（默认呈现）
const TRANS_FILE = CONTENT . '/translation.json';      // 逐条翻译状态
function V(): string { global $TV; return $TV; }       // zh-mt | zh-std | en
// 在保留当前路径/查询的基础上构造 URL，并覆盖给定参数
function url_with_params(array $over): string {
    $u = parse_url($_SERVER['REQUEST_URI'] ?? '/');
    parse_str($u['query'] ?? '', $q);
    foreach ($over as $k=>$v) {
        if ($v===null) unset($q[$k]); else $q[$k]=$v;
    }
    $qs=http_build_query($q);
    return ($u['path']??'/').($qs!==''?'?'.$qs:'');
}
// 版本切换：en / std(标准中文) / mt(机翻中文，默认)
function ver_switch_url(string $want): string {
    if ($want==='en')  return url_with_params(['lang'=>'en','v'=>null]);
    if ($want==='std') return url_with_params(['lang'=>'zh','v'=>'std']);
    return url_with_params(['lang'=>'zh','v'=>null]);    // mt：不带 v
}
// UI 文案中英字典（导航/按钮等固定界面）
const UI_EN = [
  '首页'=>'Home','条目画廊'=>'Gallery','关于本站'=>'About','主要栏目'=>'Featured',
  '地狱男爵宇宙'=>'Hellboy Universe','漫画时间线'=>'Comic Timeline','漫画'=>'Comics',
  '人物'=>'Characters','画师'=>'Creators','搜索…'=>'Search…','全部'=>'All',
  // 6 大组
  '漫画故事'=>'Comic Stories','角色'=>'Characters','电影'=>'Films','创作者'=>'Creators',
  '世界观'=>'World','其它'=>'Misc','其它+readme'=>'Misc + README',
  // 漫画故事子组
  'B.P.R.D.：蛙灾瘟疫'=>'B.P.R.D.: Plague of Frogs','B.P.R.D.：人间炼狱'=>'B.P.R.D.: Hell on Earth',
  'B.P.R.D.：现世终章'=>'B.P.R.D.: The Devil You Know','B.P.R.D.：1946–1948'=>'B.P.R.D.: 1946–1948',
  'B.P.R.D.：吸血鬼'=>'B.P.R.D.: Vampire','B.P.R.D. 主线'=>'B.P.R.D. Main',
  '地狱男爵在地狱'=>'Hellboy in Hell','地狱男爵'=>'Hellboy','亚伯·塞皮恩'=>'Abe Sapien','龙虾强生'=>'Lobster Johnson',
  '猎巫人'=>'Witchfinder','跨界·番外·非正史'=>'Crossovers & Non-canon','其它故事'=>'Other Stories',
  // 角色子组
  '影视/动画角色'=>'Film / Animation','龙虾强生 / 猎巫人系列'=>'Lobster / Witchfinder',
  '反派·纳粹'=>'Villains · Nazis','恶魔·神祇·精怪'=>'Demons · Gods · Spirits',
  'B.P.R.D. 成员'=>'B.P.R.D. Members','其它角色'=>'Other Characters',
  // 创作者子组
  '编剧'=>'Writers','内页/线稿画师'=>'Interior / Pencil Artists','封面画师'=>'Cover Artists',
  '上色师'=>'Colorists','嵌字师'=>'Lettering','其它创作者'=>'Other Creators',
  // 世界观子组
  '物品'=>'Items','组织/势力'=>'Organizations / Factions',
  // 其它子组
  '消歧义'=>'Disambiguation','资料'=>'Reference',
];
function tr(string $s): string {
  if (L()==='en') return UI_EN[$s] ?? $s;
  return $s;
}
// 给当前 URL 切换语言（保留路径与其它查询参数）
function lang_switch_url(string $want): string {
    // 头部中/EN 两键：到 EN 走英文；到中文保持当前中文版本（机译/标准）
    return $want==='en' ? ver_switch_url('en') : ver_switch_url(V()==='zh-std' ? 'std' : 'mt');
}

require ROOT . '/lib/Parsedown.php';
require_once ROOT . '/lib/hb_tbl_v2.php';

final class Ctx {
    public array $nav = [];
    public array $titles = [];
    public function __construct() {
        $j = @file_get_contents(CONTENT . '/nav.json');
        $this->nav = $j ? (json_decode($j, true) ?: []) : [];
        foreach ($this->nav as $sec) {
            foreach ($sec['items'] ?? [] as $t) $this->titles[] = $t;
            foreach ($sec['groups'] ?? [] as $g) foreach ($g['items'] ?? [] as $t) $this->titles[] = $t;
        }
        $this->titles = array_values(array_unique($this->titles));
    }
    public function nav_items(array $sec): array {
        if (!empty($sec['items'])) return $sec['items'];
        $out=[]; foreach ($sec['groups'] ?? [] as $g) foreach ($g['items'] ?? [] as $t) $out[]=$t;
        return $out;
    }
    public function find_section(string $name): ?array {
        foreach ($this->nav as $sec) if (($sec['section'] ?? '')===$name) return $sec;
        return null;
    }
    /** 条目 -> 所属大类/系列反查（用于侧栏进入具体条目时自动展开并高亮所属系列）。
     *  返回 ['section'=>大类,'sub'=>系列名或null]；查不到返回 null。
     *  若将来出现一个条目归属多个系列，返回首个（当前数据 0 冲突）。 */
    public function locate_entry(string $title): ?array {
        if ($title==='') return null;
        foreach ($this->nav as $sec) {
            $sn = $sec['section'] ?? '';
            foreach ($sec['items'] ?? [] as $t) {
                if ($t === $title) return ['section'=>$sn,'sub'=>null];
            }
            foreach ($sec['groups'] ?? [] as $g) {
                foreach ($g['items'] ?? [] as $t) {
                    if ($t === $title) return ['section'=>$sn,'sub'=>$g['sub'] ?? null];
                }
            }
        }
        return null;
    }
    public function meta(string $title): array {
        $f = META . '/' . $title . '.json';
        $d = is_file($f) ? (json_decode((string)file_get_contents($f), true) ?: []) : [];
        return $d + ['type'=>null,'type_zh'=>'','rows'=>[],'image'=>null,'cats'=>[]];
    }
    private ?array $portalData = null;
    /** 门户页配置（portal.json）：intro / core / subcat_groups */
    public function portal(string $section): ?array {
        if ($this->portalData === null) {
            $this->portalData = is_file(PORTAL_FILE)
                ? (json_decode((string)file_get_contents(PORTAL_FILE), true) ?: []) : [];
        }
        return $this->portalData[$section] ?? null;
    }
    /** cats 归一化（小写、去重），用于自动子分类匹配；输出空格分隔的 slug 供卡片 data-cat */
    public function cat_keys(string $title): array {
        $out = [];
        foreach ($this->meta($title)['cats'] ?? [] as $c) {
            $k = mb_strtolower(trim((string)$c), 'UTF-8');
            if ($k !== '') $out[$k] = true;
        }
        return array_keys($out);
    }
    public function exists(string $title): bool {
        return is_file(PAGES.'/'.$title.'.md')
            || is_file(PAGES_MT.'/'.$title.'.md')
            || is_file(PAGES_EN.'/'.$title.'.md');
    }
    private ?array $aliasMap = null;
    /** 加载合并别名表：六组重复词条别名 + 全量 Fandom 英文名→本地中文正条 */
    private function aliases(): array {
        if ($this->aliasMap === null) {
            $map = is_file(ALIAS_FILE)
                ? (json_decode((string)file_get_contents(ALIAS_FILE), true) ?: []) : [];
            $redir = is_file(EN_REDIRECT_FILE)
                ? (json_decode((string)file_get_contents(EN_REDIRECT_FILE), true) ?: []) : [];
            // en_redirects 为高置信全量表，aliases 为人工合并表；后者优先
            $this->aliasMap = $redir + $map;
        }
        return $this->aliasMap;
    }
    private ?array $aliasLower = null;
    /** 反查：若给定英文名是某中文正条的别名，返回正条标题，否则返回 null。
     *  先精确匹配（保留原大小写行为），未命中再做一次大小写不敏感兜底——
     *  迁移源里同一别名大小写写法不统一（如 Tooth_fairy / Tooth Fairy），
     *  堆重复大小写键易漏，统一在此归一最稳。多义（同一小写键指向不同正条）时不猜。 */
    public function alias_canon(string $name): ?string {
        $norm = str_replace('_', ' ', $name);
        $map = $this->aliases();
        if (isset($map[$norm])) return $map[$norm];
        if ($this->aliasLower === null) {
            $this->aliasLower = [];
            foreach ($map as $k => $v) {
                $lk = mb_strtolower($k, 'UTF-8');
                // 同一小写键出现冲突指向时记为 null，表示歧义、不自动解析
                if (isset($this->aliasLower[$lk]) && $this->aliasLower[$lk] !== $v) {
                    $this->aliasLower[$lk] = null;
                } else {
                    $this->aliasLower[$lk] = $v;
                }
            }
        }
        $hit = $this->aliasLower[mb_strtolower($norm, 'UTF-8')] ?? null;
        return ($hit !== null && $this->exists($hit)) ? $hit : null;
    }
    private ?array $redirectFlip = null;
    /** 返回某正条已知的全部英文名（en_title 登记名 + en_redirects 常用名/变体），供搜索命中 */
    public function alt_en_names(string $title): array {
        $names = [];
        $en = $this->en_title($title);
        if ($en !== '' && $en !== $title) $names[] = $en;
        if ($this->redirectFlip === null) {
            $this->redirectFlip = [];
            foreach ($this->aliases() as $enName => $canon) {
                $this->redirectFlip[$canon][] = $enName;
            }
        }
        foreach ($this->redirectFlip[$title] ?? [] as $enName) {
            if ($enName !== $title && !in_array($enName, $names, true)) $names[] = $enName;
        }
        return $names;
    }
    private ?array $slugReverse = null;
    /** 英文固定链接名（空格/下划线、大小写不敏感）→ 本地标题 */
    public function slug_title(string $name): ?string {
        global $HB_SLUGS;
        if ($this->slugReverse === null) {
            $this->slugReverse = [];
            foreach ($HB_SLUGS as $title => $slug) {
                $k = mb_strtolower(str_replace('_', ' ', trim((string)$slug)));
                $this->slugReverse[$k] = (string)$title;
            }
        }
        $k = mb_strtolower(str_replace('_', ' ', trim($name)));
        return $this->slugReverse[$k] ?? null;
    }
    /** 解析 /entry/ 路径：返回 [本地标题, 是否需要 301 到规范英文 URL] */
    public function resolve_entry(string $in): array {
        $norm = str_replace('_', ' ', trim($in));
        // 1) 英文固定链接直达（大小写/下划线不规范时 301 归一）
        $title = $this->slug_title($norm);
        if ($title !== null) {
            $canonSpaces = str_replace('_', ' ', hb_slug_of($title));
            return [$title, $canonSpaces !== $norm];
        }
        // 2) 旧中文路径（如 /entry/罗杰）→ 301 到英文固定链接
        if ($this->exists($norm)) return [$norm, true];
        // 3) 旧英文别名/常用名 → 301 到正条的规范 URL
        $canon = $this->alias_canon($norm);
        if ($canon !== null && $this->exists($canon)) return [$canon, true];
        return [$norm, false];
    }
    /** 标题归一：下划线转空格（站内文件名一律用空格）；英文名解析为本地中文正条。返回 [规范标题, 是否发生跳转] */
    public function resolve_title(string $title): array {
        $norm = str_replace('_', ' ', $title);
        // 本地已有该标题的页面则原样保留，不跳转（英文标题本地页优先）
        if ($this->exists($norm)) return [$norm, ($norm !== $title)];
        $map = $this->aliases();
        if (isset($map[$norm]) && $this->exists($map[$norm])) return [$map[$norm], true];
        return [$norm, ($norm !== $title)];
    }
    public function body_file(string $title): string {
        if (L()==='en' && is_file(PAGES_EN . '/' . $title . '.md')) return PAGES_EN . '/' . $title . '.md';
        return $this->zh_body_file($title)['path'];
    }
    private ?array $transMap = null;
    /** 翻译状态表 translation.json（key=本地中文标题） */
    private function trans_map(): array {
        if ($this->transMap === null) {
            $d = is_file(TRANS_FILE) ? (json_decode((string)file_get_contents(TRANS_FILE), true) ?: []) : [];
            $this->transMap = is_array($d['entries'] ?? null) ? $d['entries'] : [];
        }
        return $this->transMap;
    }
    public function trans_status(string $title): array {
        $m = $this->trans_map();
        $r = $m[$title] ?? [];
        return is_array($r) ? ($r + ['status'=>'none']) : ['status'=>'none'];
    }
    public function has_mt(string $title): bool { return is_file(PAGES_MT . '/' . $title . '.md'); }
    /**
     * 中文三层取页。返回 [path, variant]：
     *  variant=mt   机翻中文（pages_mt，默认优先）
     *  variant=std  标准中文版（pages/，人工或已固化；无机翻时回退）
     */
    public function zh_body_file(string $title): array {
        $std = PAGES . '/' . $title . '.md';
        $mt  = PAGES_MT . '/' . $title . '.md';
        $en  = PAGES_EN . '/' . $title . '.md';
        if (V()==='zh-std') return is_file($std) ? ['path'=>$std, 'variant'=>'std']
            : (is_file($en) ? ['path'=>$en,'variant'=>'en'] : ['path'=>$std,'variant'=>'std']);
        // 默认 zh-mt：机译稿 → 人工标准页 → 英文原文（尚未翻译的新增条目）
        if (is_file($mt)) return ['path'=>$mt, 'variant'=>'mt'];
        if (is_file($std)) return ['path'=>$std, 'variant'=>'std'];
        return ['path'=>$en, 'variant'=>'en'];
    }
    public function has_en(string $title): bool { return is_file(PAGES_EN . '/' . $title . '.md'); }
    private ?array $enTitleMap = null;
    // EN 模式下的展示标题（Fandom 英文实体名）；缺则回退本地标题
    public function en_title(string $title): string {
        if ($this->enTitleMap === null) {
            $this->enTitleMap = is_file(EN_TITLE_FILE)
                ? (json_decode((string)file_get_contents(EN_TITLE_FILE), true) ?: []) : [];
        }
        $en = $this->enTitleMap[$title] ?? '';
        return $en !== '' ? $en : $title;
    }
    private ?array $zhTitleMap = null;
    // 中文模式的显示标题：优先机译/人工中译名 zh_title（待验证也照常显示），缺省回退本地 key
    public function zh_title(string $title): string {
        if ($this->zhTitleMap === null) {
            $m = $this->trans_map();
            $z = [];
            foreach ($m as $k => $v) {
                if (is_array($v) && trim((string)($v['zh_title'] ?? '')) !== '') $z[$k] = trim((string)$v['zh_title']);
            }
            $this->zhTitleMap = $z;
        }
        return $this->zhTitleMap[$title] ?? $title;
    }
    public function disp_title(string $title): string {
        if (L()==='en') {
            return is_file(PAGES_EN . '/' . $title . '.md') ? $this->en_title($title) : $title;
        }
        return $this->zh_title($title);
    }
    // 信息框：英文模式优先 rows_en + type_en，缺则把中文标签/类型头机读英文化
    public function ib_for(string $title): array {
        $ib = $this->meta($title);
        if (L()==='en') {
            if (!empty($ib['rows_en'])) {
                $ib['rows'] = $ib['rows_en'];
            } else {
                foreach ($ib['rows'] as &$r) {
                    $lab = trim((string)($r['label'] ?? ''));
                    if (isset(IB_LABEL_EN[$lab])) $r['label'] = IB_LABEL_EN[$lab];
                }
                unset($r);
            }
            if (!empty($ib['type_en'])) {
                $ib['type_zh'] = $ib['type_en'];
            } else {
                $tz = trim((string)($ib['type_zh'] ?? ''));
                if (isset(IB_TYPE_EN[$tz])) $ib['type_zh'] = IB_TYPE_EN[$tz];
                elseif (!empty($ib['type'])) $ib['type_zh'] = $ib['type'];
            }
        }
        return $ib;
    }
}
/* 自定义页面：slug => ['title'=>..,'file'=>..]（平铺页；_groups 里的分组页也合并进路由表） */
function custom_pages(): array {
    $fallback = ['about'=>['title'=>'关于本站','file'=>'about.md']];
    $idx = CUSTOM . '/pages.json';
    if (!is_file($idx)) return $fallback;
    $d = json_decode((string)file_get_contents($idx), true);
    if (!is_array($d)) return $fallback;
    foreach (($d['_groups'] ?? []) as $grp) {
        foreach (($grp['pages'] ?? []) as $gp) {
            if (!empty($gp['slug'])) $d[$gp['slug']] = $gp;
        }
    }
    unset($d['_groups']);
    return $d;
}
/* 自定义页面的侧栏分组（如「翻译报告」） */
function custom_groups(): array {
    $idx = CUSTOM . '/pages.json';
    if (!is_file($idx)) return [];
    $d = json_decode((string)file_get_contents($idx), true);
    $grps = is_array($d) ? ($d['_groups'] ?? []) : [];
    return is_array($grps) ? $grps : [];
}
/* 属于分组页的 slug 集合（侧栏平铺时排除） */
function custom_group_slugs(): array {
    $out = [];
    foreach (custom_groups() as $grp)
        foreach (($grp['pages'] ?? []) as $gp)
            if (!empty($gp['slug'])) $out[$gp['slug']] = true;
    return $out;
}
$ctx = new Ctx();
$uri = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
function e(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
/* 英文固定链接：全站唯一的 permalink 名（slugs.json），与中/英界面语言解耦 */
$HB_SLUGS_RAW = is_file(SLUGS_FILE) ? (json_decode((string)file_get_contents(SLUGS_FILE), true) ?: []) : [];
// PHP 会把纯数字 JSON 键（如 "1946"）转成 int；统一规范化回字符串键值
$HB_SLUGS = [];
foreach ($HB_SLUGS_RAW as $k => $v) $HB_SLUGS[(string)$k] = (string)$v;
unset($HB_SLUGS_RAW);
function hb_slug_of(string $title): string { global $HB_SLUGS; return $HB_SLUGS[$title] ?? $title; }
/* slug => URL 片段：空格转下划线，括号/冒号/撇号保持可读，其余百分号编码 */
function slug_url(string $slug): string {
    $s = str_replace(' ', '_', trim($slug));
    $s = rawurlencode($s);
    return str_replace(['%28','%29','%3A','%27','%3B','%2F'], ['(',')',":","'",';','/'], $s);
}
function ent(string $t): string { return '/entry/' . slug_url(hb_slug_of($t)); }
function img(string $f): string { return IMG . '/' . rawurlencode($f); }

// HONEST-LANG: 依据正文实际字符判定 zh/en/mixed，不看标题
function detect_lang(string $md): string {
    $t = preg_replace('/<[^>]+>/u', ' ', $md);
    $t = preg_replace('#https?://\S+#u', ' ', $t);
    preg_match_all('/[\x{4e00}-\x{9fff}]/u', $t, $zh);
    preg_match_all('/[A-Za-z]{2,}/', $t, $en);
    $nz = count($zh[0] ?? []); $ne = count($en[0] ?? []); $tot = $nz + $ne;
    if ($tot === 0) return 'en';
    $r = $nz / $tot;
    if ($r > 0.55) return 'zh';
    if ($r < 0.08) return 'en';
    return 'mixed';
}

/* Wiki.js 风格线性图标（白色 stroke，随侧栏底色反白） */
function icon_svg(string $k): string {
    $icons = [
      'home'    => '<path d="M3 9.5 12 3l9 6.5"/><path d="M5 10v10h14V10"/><path d="M9.5 20v-6h5v6"/>',
      'gallery' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/>',
      'user'    => '<circle cx="12" cy="8" r="4"/><path d="M4.5 21c.8-3.8 4-5.5 7.5-5.5s6.7 1.7 7.5 5.5"/>',
      'book'    => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>',
      'film'    => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="M7 4v16M17 4v16M2 9h5M17 9h5M2 15h5M17 15h5"/>',
      'layers'  => '<path d="m12 2 9 5-9 5-9-5 9-5z"/><path d="m3 12 9 5 9-5"/><path d="m3 17 9 5 9-5"/>',
      'brush'   => '<path d="M9.06 11.9 18.5 2.5a2.12 2.12 0 0 1 3 3L12.1 14.94"/><path d="M7 14c-2 0-3.5 1.5-3.5 3.5 0 1.5-1 3-2.5 3.5C2 22 4 23 6 23c2.5 0 4.5-2 4.5-4.5C10.5 15.5 9 14 7 14z"/>',
    ];
    $body = $icons[$k] ?? $icons['layers'];
    return '<svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" '
        . 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$body.'</svg>';
}

$q = trim($_GET['q'] ?? '');
if ($q !== '') { render_search($ctx, $q); exit; }
if ($uri === '/' || $uri === '/index.php') { render_home($ctx); exit; }
if ($uri === '/gallery') { render_gallery($ctx); exit; }
if ($uri === '/browse') { render_browse($ctx, $_GET['g'] ?? '', $_GET['s'] ?? '', $_GET['cat'] ?? ''); exit; }
if (preg_match('#^/p/([A-Za-z0-9_\-]+)$#', $uri, $cm)) {
    $pages = custom_pages();
    if (!isset($pages[$cm[1]])) { render_404($ctx, $cm[1]); exit; }
    render_custom($ctx, $cm[1], $pages[$cm[1]]); exit;
}
if (preg_match('#^/portal/([A-Za-z0-9_\-]+)$#', $uri, $cm)) {
    $portals = portal_pages();
    if (!isset($portals[$cm[1]])) { render_404($ctx, $cm[1]); exit; }
    render_portal_page($ctx, $cm[1], $portals[$cm[1]]); exit;
}
if (preg_match('#^/entry/(.+)$#', $uri, $m)) {
    [$canonical, $moved] = $ctx->resolve_entry($m[1]);
    if ($moved) {
        $qs = '';
        if (isset($_GET['lang']) && $_GET['lang'] !== '') $qs = '?lang=' . rawurlencode((string)$_GET['lang']);
        header('Location: ' . ent($canonical) . $qs, true, 301); exit;   // 旧中文路径/别名/大小写 → 英文固定链接
    }
    if (!$ctx->exists($canonical)) { render_404($ctx, $canonical); exit; }
    render_entry($ctx, $canonical); exit;
}
http_response_code(404); render_404($ctx, $uri);

/* ---------- markdown ---------- */
function md_html(string $md, int $tocOffset = 0): array {
    // 剥掉源码残留的 HTML 注释（如构建器写的 missing-image 标记），避免直接显示给读者
    $md = preg_replace('/<!--.*?-->/s', '', $md);
    // HONEST-LANG: 清洗 Google Docs 粘贴残留的内联样式标签（只去标签，保留内层文字）
    $md = preg_replace('/<span\b[^>]*>/i', '', $md);
    $md = str_ireplace('</span>', '', $md);
    $md = preg_replace('/<br\s*\/?>/i', "\n", $md);
    $md = preg_replace('/<\/?b>/i', '**', $md);
    $md = preg_replace('/<\/?(?:strong|em|i)>/i', '', $md);
    // 剥除迁移残留的文末分类标签 **Category:X**（417 页，真实分类已由 meta/信息框渲染）
    $md = preg_replace('/\*\*\s*Category:[^*]*\*\*/u', '', $md);
    // 迁移残留：图片标签下一行裸写的文件名（被当成正文显示）。仅当该行等于图片 basename 时删除，保留真正的图注。
    $md = preg_replace_callback('/(!\[[^\]]*\]\(([^)\s]+)(?:\s+"[^"]*")?\))[ \t]*\r?\n[ \t]*([^\n\r]+?)[ \t]*(?=\r?\n)/i', function($m) {
        $base = basename(rawurldecode(explode('?', $m[2])[0]));
        return trim($m[3]) === $base ? $m[1] . "\n" : $m[0];
    }, $md);
    // 剥掉正文里的第一个 H1（页面已用 <h1 class=page-title> 渲染标题，避免重复标题）
    $md = preg_replace('/^\s*#\s+.+?(\r?\n|$)/', '', $md, 1);
    $pd = new Parsedown();
    $pd->setSafeMode(true);
    $html = $pd->text($md);
    // 剥掉正文第一个 H1（页面标题已由布局统一输出，避免重复）
    $html = preg_replace('#\s*<h1>.*?</h1>\s*#s', '', $html, 1);
    $toc = [];
    $html = preg_replace_callback('#<h([23])>(.*?)</h\1>#s', function($m) use (&$toc, $tocOffset) {
        $text = trim(html_entity_decode(strip_tags($m[2]), ENT_QUOTES, 'UTF-8'));
        $id = 'sec-' . ($tocOffset + count($toc) + 1);
        $toc[] = ['level'=>(int)$m[1], 'text'=>$text, 'id'=>$id];
        return '<h'.$m[1].' id="'.e($id).'">'.$m[2].'</h'.$m[1].'>';
    }, $html);
    $html = preg_replace('#<a href="(https?://[^"]+)"#', '<a href="$1" target="_blank" rel="noopener"', $html);
    $html = hb_gallery_group($html);
    return [$html, $toc];
}

/* 纯图片自动收拢为响应式网格画廊。
   规则：位于「画廊/Gallery」标题下的图片组，只要 1 张就网格（有图即网格）；
   正文其他位置仍需连续 >=3 张才收拢，避免单张正文插图被卡片化。
   只含 <img>、中间仅空白的 <p> 才参与，夹杂文字/链接的图片保持原样，md 源零改动。 */
function hb_gallery_run(string $seg, int $min): string {
    // 单元 = 一张独立成段图片 + 其后零到多条图注段；图注可是管道符(|图注)或期号(\#1)。
    // 允许图间夹多条图注：每张图只取紧随的第一条作 figcaption，其余视为缺图导致的孤儿期号，自动忽略。
    $grp  = '#(?:<p>\s*<img\b[^>]*?/?>\s*</p>\s*(?:<p>\s*(?:\|\s*.*?|\#\s*\d{1,3})\s*</p>\s*)*){'.($min >= 1 ? $min : 1).',}#i';
    $imgTok = '#<p>\s*(<img\b[^>]*?/?>)\s*</p>#i';
    $capTok = '#<p>\s*\|\s*(?P<capT>.*?)\s*</p>|<p>\s*\#\s*(?P<capN>\d{1,3})\s*</p>#is';
    return preg_replace_callback($grp, function($m) use ($imgTok, $capTok, $min) {
        $parts = preg_split($imgTok, $m[0], -1, PREG_SPLIT_DELIM_CAPTURE);
        $items = ''; $n = 0;
        for ($i = 1; $i < count($parts); $i += 2) {           // 奇数位 = 捕获的 <img>
            $img = $parts[$i]; $after = $parts[$i+1] ?? '';
            $cap = '';
            if (preg_match($capTok, $after, $c)) {
                if (isset($c['capT']) && trim($c['capT']) !== '')     $cap = '<figcaption>'.trim($c['capT']).'</figcaption>';
                elseif (isset($c['capN']) && trim($c['capN']) !== '') $cap = '<figcaption>#'.trim($c['capN']).'</figcaption>';
            }
            $items .= '<figure class="hbg-item">'.$img.$cap.'</figure>';
            $n++;
        }
        if ($n < $min) return $m[0];
        return '<div class="hb-gallery">'.$items.'</div>';
    }, $seg);
}
function hb_gallery_group(string $html): string {
    // 先对全文按 >=3 收拢（画师封面墙等正文连续图组，行为同旧版）
    $html = hb_gallery_run($html, 3);
    // 再定位每个「画廊/Gallery」标题，标题之后到下一个同级/更高级标题之间，门槛降到 1
    $html = preg_replace_callback(
        '#(<h[2-4][^>]*>\s*(?:gallery|画廊)[^<]*</h[2-4]>)(.*?)(?=<h[2-4][^>]*>|$)#is',
        function($m){ return $m[1].hb_gallery_run($m[2], 1); },
        $html
    );
    // 未入组（不足门槛或被小标题分隔）的独立 "|图注" 段：剥管道符，渲染为斜体图注
    $html = preg_replace('#<p>\s*\|\s*(.+?)</p>#s', '<p class="hbg-solo-cap">$1</p>', $html);
    return $html;
}

/* ---------- layout: black topbar / blue fixed sidebar / center scroll / right rail ---------- */
function layout(Ctx $ctx, string $title, string $main, array $opts = []): void {
    $right = $opts['right'] ?? '';
    $wide  = $opts['wide'] ?? false;
    ?><!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($title) ?> · <?= SITE_NAME ?></title>
<link rel="stylesheet" href="/assets/css/app.css?v=<?= ASSET_VER_CSS ?>">
<link rel="icon" href="/favicon.ico?v=2" sizes="any">
<link rel="icon" type="image/png" sizes="32x32" href="/assets/img/favicon-32.png?v=2">
<link rel="apple-touch-icon" href="/assets/img/apple-touch-icon.png?v=2">
</head>
<body>
<div class="app">
  <header class="topbar">
    <a class="tb-brand" href="/">
      <span class="tb-mark"><img src="/assets/img/logo-96.png?v=2" alt="" width="28" height="28"></span>
      <span class="tb-name"><?= SITE_NAME ?></span>
    </a>
    <form class="tb-search" action="/" method="get" role="search">
      <span class="tb-search-ico">⌕</span>
      <input type="search" name="q" placeholder="<?= L()==='en'?'Search…':'搜索…' ?>" value="<?= e($_GET['q'] ?? '') ?>" autocomplete="off">
    </form>
    <div class="tb-right">
      <div class="lang-switch" id="langSwitch" data-lang="<?= e(L()) ?>">
        <a class="ls-btn<?= L()==='zh'?' on':'' ?>" href="<?= e(lang_switch_url('zh')) ?>">中</a>
        <a class="ls-btn<?= L()==='en'?' on':'' ?>" href="<?= e(lang_switch_url('en')) ?>">EN</a>
      </div>
      <a href="/gallery" title="<?= L()==='en'?'Gallery':'条目画廊' ?>">▦</a>
      <?php
        $hbHere = ($_SERVER['REQUEST_URI'] ?? '/');
        $hbLoginGoto = '/admin/login.php?goto=' . rawurlencode($hbHere);
      ?>
      <?php if (hb_admin()): ?>
      <a class="tb-admin" href="/admin/" title="<?= L()==='en'?'Admin dashboard':'管理后台' ?>"><?= L()==='en'?'Admin':'管理' ?></a>
      <a class="tb-admin tb-logout" href="/admin/logout.php?goto=<?= rawurlencode($hbHere) ?>" title="<?= L()==='en'?'Log out':'退出登录' ?>">⎋</a>
      <?php else: ?>
      <a class="tb-admin" href="<?= e($hbLoginGoto) ?>" title="<?= L()==='en'?'Admin login':'管理员登录' ?>"><?= L()==='en'?'Admin login':'管理员登录' ?></a>
      <?php endif; ?>
    </div>
  </header>

  <div class="body-row">
    <aside class="sidebar" id="sidebar">
      <nav class="side-nav">
        <a class="nav-item pin <?= ($opts['current']??'')==='home'?'on':'' ?>" href="/"><?= icon_svg('home') ?> <?= tr('首页') ?></a>
        <a class="nav-item pin <?= ($opts['current']??'')==='gallery'?'on':'' ?>" href="/gallery"><?= icon_svg('gallery') ?> <?= tr('条目画廊') ?></a>
        <?php $customGroupSlugs = custom_group_slugs(); ?>
        <?php foreach (custom_pages() as $cslug=>$cp): ?>
        <?php if (isset($customGroupSlugs[$cslug])) continue; /* 分组页在折叠组里渲染 */ ?>
        <?php if (($cp['nav'] ?? true) === false) continue; /* 如「关于本站」已并入首页底部，不在侧栏单列 */ ?>
        <a class="nav-item pin <?= ($opts['current']??'')==='p:'.$cslug?'on':'' ?>" href="/p/<?= e($cslug) ?>"><?= icon_svg('home') ?> <?= e($cp['title'] ?? $cslug) ?></a>
        <?php endforeach; ?>
        <?php foreach (custom_groups() as $cgrp):
          if (($cgrp['nav'] ?? '') === 'other') continue;   /* 并入底部「其它+readme」折叠组，不单独成组 */
          $cgrpId = 'grp-c-' . substr(md5($cgrp['slug'] ?? 'x'),0,8);
          $cgrpActive = false;
          foreach (($cgrp['pages'] ?? []) as $cgp) { if (($opts['current']??'')==='p:'.($cgp['slug'] ?? '')) { $cgrpActive = true; break; } }
        ?>
        <div class="nav-fold<?= $cgrpActive?' open':'' ?>">
          <button type="button" class="nav-fold-head" aria-expanded="<?= $cgrpActive?'true':'false' ?>" aria-controls="<?= e($cgrpId) ?>">
            <?= icon_svg($cgrp['icon'] ?? 'layers') ?>
            <span class="nf-label"><?= e($cgrp['title'] ?? ($cgrp['slug'] ?? '')) ?></span>
            <span class="nf-count"><?= count($cgrp['pages'] ?? []) ?></span>
            <span class="nf-caret">▸</span>
          </button>
          <div class="nav-fold-body" id="<?= e($cgrpId) ?>">
            <?php foreach (($cgrp['pages'] ?? []) as $cgp): $cgpSlug = $cgp['slug'] ?? ''; ?>
            <a class="nav-item sub <?= ($opts['current']??'')==='p:'.$cgpSlug?'on':'' ?>" href="/p/<?= e($cgpSlug) ?>"><?= e($cgp['title'] ?? $cgpSlug) ?></a>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endforeach; ?>
        <?php
        $featuredCur = ($opts['current']??'');
        $featuredOpen = in_array($featuredCur, ['《地狱男爵》宇宙全系列漫画','《地狱男爵》漫画出版时间线','portal:characters','portal:stories','portal:artists'], true);
        ?>
        <div class="nav-fold<?= $featuredOpen?' open':'' ?>">
          <button type="button" class="nav-fold-head" aria-expanded="<?= $featuredOpen?'true':'false' ?>" aria-controls="grp-featured">
            <?= icon_svg('layers') ?>
            <span class="nf-label"><?= tr('主要栏目') ?></span>
            <span class="nf-count">5</span>
            <span class="nf-caret">▸</span>
          </button>
          <div class="nav-fold-body" id="grp-featured">
            <a class="nav-item sub <?= $featuredCur==='《地狱男爵》宇宙全系列漫画'?'on':'' ?>" href="<?= ent('《地狱男爵》宇宙全系列漫画') ?>"><?= tr('地狱男爵宇宙') ?></a>
            <a class="nav-item sub <?= $featuredCur==='《地狱男爵》漫画出版时间线'?'on':'' ?>" href="<?= ent('《地狱男爵》漫画出版时间线') ?>"><?= tr('漫画时间线') ?></a>
            <a class="nav-item sub<?= ($opts['current']??'')==='portal:stories'?' on':'' ?>" href="/portal/stories"><?= tr('漫画') ?></a>
            <a class="nav-item sub<?= ($opts['current']??'')==='portal:characters'?' on':'' ?>" href="/portal/characters"><?= tr('人物') ?></a>
            <a class="nav-item sub<?= ($opts['current']??'')==='portal:artists'?' on':'' ?>" href="/portal/artists"><?= tr('画师') ?></a>
          </div>
        </div>
        <?php
        $groupIcons = ['角色'=>'user','漫画故事'=>'book','故事'=>'book','电影'=>'film','Films'=>'film','Comics'=>'layers','创作者'=>'brush','艺术家'=>'brush','世界观'=>'layers','其它'=>'layers','Series'=>'layers'];
        foreach ($ctx->nav as $sec):
          $secName = $sec['section'];
          $iconKey = $sec['icon'] ?? ($groupIcons[$secName] ?? 'layers');
          $allItems = $ctx->nav_items($sec);
          $active = in_array(($opts['current']??''), $allItems, true);
          $open = $active ? ' open' : '';
          $gid = 'grp-' . substr(md5($secName),0,8);
        ?>
          <?php if (!empty($sec['items'])): /* 小类（如电影）直接列条目 */ ?>
          <div class="nav-fold<?= $open ?>">
            <button type="button" class="nav-fold-head" aria-expanded="<?= $active?'true':'false' ?>" aria-controls="<?= $gid ?>">
              <?= icon_svg($iconKey) ?>
              <span class="nf-label"><?= tr($secName) ?></span>
              <span class="nf-count"><?= count($allItems) ?></span>
              <span class="nf-caret">▸</span>
            </button>
            <div class="nav-fold-body" id="<?= $gid ?>">
              <?php foreach ($sec['items'] as $t): ?>
                <a class="nav-item sub <?= ($opts['current']??'')===$t?'on':'' ?>" href="<?= ent($t) ?>"><?= e($ctx->disp_title($t)) ?></a>
              <?php endforeach; ?>
            </div>
          </div>
          <?php else: /* 大类：只列子组链接，点进 /browse 浏览页，绝不在侧栏铺开全部条目 */ ?>
          <?php
            $bOpen = ($opts['browseGroup']??'')===$secName;
            // 「其它」折叠组额外收纳 custom 页里标记 nav=other 的 readme（翻译报告三篇）
            $readmePages = [];
            if ($secName==='其它') {
              foreach (custom_groups() as $cgrp2) {
                if (($cgrp2['nav'] ?? '') !== 'other') continue;
                foreach (($cgrp2['pages'] ?? []) as $rp) if (!empty($rp['slug'])) $readmePages[] = $rp;
              }
              foreach ($readmePages as $rp) if (($opts['current']??'')==='p:'.($rp['slug']??'')) { $bOpen = true; break; }
            }
            $secLabel = $secName==='其它' ? '其它+readme' : $secName;
          ?>
          <div class="nav-fold<?= $bOpen?' open':'' ?>">
            <button type="button" class="nav-fold-head" aria-expanded="<?= $bOpen?'true':'false' ?>" aria-controls="<?= $gid ?>">
              <?= icon_svg($iconKey) ?>
              <span class="nf-label"><?= tr($secLabel) ?></span>
              <span class="nf-count"><?= count($allItems) ?></span>
              <span class="nf-caret">▸</span>
            </button>
            <div class="nav-fold-body" id="<?= $gid ?>">
              <a class="nav-item sub nav-cat<?= $bOpen && ($opts['browseSub']??null)===null?' on':'' ?>" href="/browse?g=<?= rawurlencode($secName) ?>"><span class="nc-label"><?= tr('全部') ?></span><span class="nc-count"><?= count($allItems) ?></span></a>
              <?php foreach ($sec['groups'] as $grp): ?>
                <a class="nav-item sub nav-cat<?= $bOpen && ($opts['browseSub']??'')===$grp['sub']?' on':'' ?>" href="/browse?g=<?= rawurlencode($secName) ?>&amp;s=<?= rawurlencode($grp['sub']) ?>">
                  <span class="nc-label"><?= tr($grp['sub']) ?></span><span class="nc-count"><?= count($grp['items']) ?></span>
                </a>
              <?php endforeach; ?>
              <?php if ($readmePages): ?>
                <div class="nav-readme-sep">README</div>
                <?php foreach ($readmePages as $rp): $rpSlug=$rp['slug']??''; ?>
                <a class="nav-item sub<?= ($opts['current']??'')==='p:'.$rpSlug?' on':'' ?>" href="/p/<?= e($rpSlug) ?>"><?= e($rp['title'] ?? $rpSlug) ?></a>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>
          <?php endif; ?>
        <?php endforeach; ?>
      </nav>
    </aside>

    <main class="content<?= $wide?' wide':'' ?>">
      <?php if (!empty($opts['mobileTop'])): ?><div class="mobile-rail mobile-rail-top"><?= $opts['mobileTop'] ?></div><?php endif; ?>
      <?= $main ?>
      <?php if (!empty($opts['mobileBottom'])): ?><div class="mobile-rail mobile-rail-bottom"><?= $opts['mobileBottom'] ?></div><?php endif; ?>
    </main>

    <aside class="rail" id="rail">
      <?= $right ?>
    </aside>
  </div>
</div>
<button class="menu-fab" id="menuFab" aria-label="导航">☰</button>
<script src="/assets/js/app.js?v=<?= ASSET_VER_JS ?>"></script>
</body>
</html><?php
}

function rail_toc(array $toc): string {
    if (!$toc) return '';
    $out = '<div class="rail-card"><div class="rail-title">PAGE CONTENTS</div><ul class="rail-toc">';
    foreach ($toc as $t)
        $out .= '<li class="lv'.$t['level'].'"><a href="#'.e($t['id']).'">'.e($t['text']).'</a></li>';
    return $out.'</ul></div>';
}

function infobox_card(array $ib): string {
    if (!$ib['type']) return '';
    if (!empty($ib['image'])) {
        $img = '<div class="ib-figure"><img src="'.img($ib['image']).'" alt="" loading="lazy"></div>';
    } else {
        $img = '<div class="ib-figure is-placeholder"><img src="'.img('placeholder-bprd.png').'" alt="暂无图片" loading="lazy"></div>';
    }
    $rows = '';
    foreach ($ib['rows'] as $r) {
        $v = trim((string)$r['value']);
        if ($v==='') continue;
        $rows .= '<tr><th>'.e($r['label']).'</th><td>'.$v.'</td></tr>';
    }
    return '<div class="rail-card ib-card type-'.e($ib['type']).'">'
        . '<div class="ib-head">'.e($ib['type_zh'] ?: $ib['type']).'</div>'
        . $img.'<table class="ib-table"><tbody>'.$rows.'</tbody></table></div>';
}

function cats_html(array $cats): string {
    if (!$cats) return '';
    $label = L()==='en' ? 'Categories' : '分类';
    $out = '<div class="categories"><span class="cat-label">'.$label.'</span>';
    foreach ($cats as $c) $out .= '<a class="cat-chip" href="/?q='.rawurlencode($c).'">'.e($c).'</a>';
    return $out.'</div>';
}

/* ---------- home: Wikipedia 门户还原 ---------- */
function portal_series(): array {
    return [
        ['Front Page - Hellboy.png','Hellboy (series)'],
        ['FrontPage-HellboyBPRD.png','Hellboy and the B.P.R.D.'],
        ['Front Page - Hellboy in Hell.png','Hellboy in Hell (series)'],
        ['Front Page - BPRD.png','B.P.R.D. (series)'],
        ['FrontPage-BPRD-TheDevilYouKnow.png','The Devil You Know (story cycle)'],
        ['Front Page - BPRD Hell on Earth.png','Hell on Earth (story cycle)'],
        ['Front Page - Abe Sapien.png','Abe Sapien (series)'],
        ['Front Page - Witchfinder.png','Witchfinder'],
        ['Front Page - Lobster Johnson.png','Lobster Johnson (series)'],
        ['Front Page - Sledgehammer 44.png','Sledgehammer 44 (series)'],
        ['FrontPage-RiseoftheBlackFlame.png','Rise of the Black Flame'],
        ['FrontPage-FrankensteinUnderground.png','Frankenstein Underground'],
        ['FrontPage-TheVisitor.png','The Visitor: How & Why He Stayed'],
        ['FrontPage-Koshchei.png','Koshchei the Deathless (story)'],
        ['FrontPage-Rasputin.png','Rasputin: The Voice of the Dragon'],
        ['Front Page - Crimson Lotus.png','Crimson Lotus (story)'],
    ];
}
function portal_chars(): array {
    // slug 一律用本地真实页面标题，避免英文规范名 404（路由层另有 en_redirects 兜底）
    return [
      [['地狱男爵','地狱男爵（Hellboy）'],['丽兹·舍曼','丽兹·舍曼'],['亚伯·塞皮恩','亚伯·塞皮恩'],['凯特·考瑞根','凯特·考瑞根'],['罗杰','罗杰'],['本·达米奥','本·达米奥'],['约翰·克劳斯','约翰·克劳斯'],['潘雅','潘雅']],
      [['特雷佛·布鲁姆','特雷佛·布鲁姆教授'],['爱德华·格雷','爱德华·格雷爵士'],['Lobster Johnson','大螯虾强生'],['菲尼克斯·埃斯佩霍','菲尼克斯·埃斯佩霍'],['阿什利·斯托德','阿什利·斯托德'],['辛西雅·泰南','辛西雅·泰南'],["Frankenstein's Monster",'弗兰肯斯坦的怪物']],
      [['奥古·桀哈','奥古·桀哈'],['赫卡特','赫卡特'],['黑焰','黑焰'],['瓦尔瓦拉','瓦尔瓦拉'],['血皇后','血皇后'],['格里高利·拉斯普廷','格里高利·拉斯普廷'],['克焰人','克焰人'],['雅加婆婆（人物）','雅加婆婆'],['棕精灵','棕精灵']],
    ];
}

/**
 * 首页「电影」板块——与「电影」分类（nav.json）联动，单一数据源，不再手敲海报清单。
 * 只收录 type=Movie 的真实影片条目（自动排除原声专辑 / 宣传漫画 / 物品等非影片项）；
 * 海报取 meta.image，链接走固定链接，按上映年份升序。
 * 返回 ['live'=>[真人...], 'animated'=>[动画/短片/试播...]]，每项 [title,img,year]。
 */
function portal_movies(Ctx $ctx): array {
    $sec = $ctx->find_section('电影');
    $items = $sec ? ($sec['items'] ?? []) : [];
    $rows = [];
    foreach ($items as $t) {
        if (!$ctx->exists($t)) continue;
        $m = $ctx->meta($t);
        if (($m['type'] ?? null) !== 'Movie') continue;          // 只要影片条目
        $img = trim((string)($m['image'] ?? ''));
        if ($img === '') continue;                                // 无海报不上墙
        // 年份：优先英文信息框发行日期行；否则从规范名/中译名里取四位年份（如 2019 reboot 的 TBA）
        $year = 0;
        foreach (($m['rows_en'] ?? []) as $r) {
            $lab = strtolower(trim((string)($r['label'] ?? '')));
            if (in_array($lab, ['release date','year','released','air date','premiere','original release'], true)
                && preg_match('/(?:19|20)\d{2}/', strip_tags((string)($r['value'] ?? '')), $mm)) {
                $year = (int)$mm[0]; break;
            }
        }
        if (!$year && preg_match('/(?:19|20)\d{2}/', $t.' '.$ctx->zh_title($t), $mm)) $year = (int)$mm[0];
        // 真人 / 动画：以 cats 的 Hellboy Animated 为准，并用标题关键词（动画/animated/短片/short/试播/pilot）兜底
        $animated = false;
        foreach (($m['cats'] ?? []) as $c) {
            if (strtolower(str_replace(' ', '', (string)$c)) === 'hellboyanimated') { $animated = true; break; }
        }
        if (!$animated) {
            $probe = mb_strtolower($t.' '.$ctx->zh_title($t).' '.$ctx->en_title($t), 'UTF-8');
            foreach (['动画','短片','试播','animated','short','pilot'] as $kw) {
                if (mb_stripos($probe, $kw) !== false) { $animated = true; break; }
            }
        }
        $rows[] = ['title'=>$t,'img'=>$img,'year'=>$year,'animated'=>$animated];
    }
    usort($rows, function($a,$b) {
        if ($a['year'] !== $b['year']) {
            if (!$a['year']) return 1; if (!$b['year']) return -1;   // 无年份垫底
            return $a['year'] <=> $b['year'];
        }
        return strcmp($a['title'], $b['title']);
    });
    $live = []; $anim = [];
    foreach ($rows as $r) {
        if ($r['animated']) { $anim[] = $r; } else { $live[] = $r; }
    }
    return ['live'=>$live,'animated'=>$anim];
}

function render_home(Ctx $ctx): void {
    $series = portal_series();
    $charCols = portal_chars();
    $enHome = L()==='en';
    // 出版与收藏参考页（含大型原生表格：出版月表、平装/精装/馆藏总目）
    $editions = [
        ['Hellboy_Comic_Publication_Timeline', '漫画出版时间线', 'Comic Publication Timeline', '按年份/月份排列的全部故事首发表', 'Every story’s first publication, by year/month'],
        ['The_Hellboy_Universe_Comics', '宇宙全系列漫画', 'The Hellboy Universe Comics', '核心与衍生系列总览 + Stories by Month 月表', 'Core & spin-off series overview + Stories by Month'],
        ['Trade_Collections', '合订本：平装', 'Trade Paperbacks', '70+ 本平装合订本总目（收录内容/附加素材/封面）', '70+ trade paperbacks: contents, extras & covers'],
        ['Omnibus_Editions', '合订本：精装', 'Omnibus Editions', '精装 Omnibus 合订总目', 'Hardcover omnibus master index'],
        ['Hellboy_Library_Editions', '合订本：馆藏版', 'Library Editions', '大开本精装馆藏版总目', 'Oversized hardcover library editions'],
        ['Upcoming', '即将推出', 'Upcoming', '已公布但尚未出版的新书与故事', 'Announced but not yet published'],
    ];
    // 电影板块与「电影」分类（nav.json）联动：自动收录全部 type=Movie 影片，单一数据源
    $movieGroups = portal_movies($ctx);
    ob_start(); ?>
    <div class="crumbs">首页</div>
    <article class="portal">
      <div class="portal-logo"><img src="<?= img('Hellboy_Wiki.png') ?>" alt="The Hellboy Wiki"></div>
      <h1 class="portal-title">首页</h1>

      <p><b>地狱男爵中文维基</b> 收录和整理与<b>地狱男爵</b>（地狱小子、Hellboy）相关的所有中文信息，当前作为个人资料库的现代化阅读版重建。</p>
      <?php
        // 首页索引链接对齐 18100 MediaWiki 分类页：指向对应 browse 索引（分类），而非单个条目
        $bRole     = '/portal/characters';  // 独立门户页（手写板块+自动子分类+A–Z）
        $bStory    = '/portal/stories';
        $bComics   = '/portal/stories';
        $bFilms    = '/browse?g=' . rawurlencode('电影');    // 分类:Films
        $bInterior = '/browse?g=' . rawurlencode('创作者') . '&s=' . rawurlencode('内页/线稿画师'); // Interior Artist
        $bCover    = '/browse?g=' . rawurlencode('创作者') . '&s=' . rawurlencode('封面画师');       // Cover Artist
        $bColor    = '/browse?g=' . rawurlencode('创作者') . '&s=' . rawurlencode('上色师');         // Colorist
      ?>
      <p>内容包括：<a href="<?= $bRole ?>">人物词条</a>、<a href="<?= $bStory ?>">漫画故事索引</a>、<a href="<?= $bComics ?>">图书系列索引</a>。艺术家词条：<a href="<?= $bInterior ?>">主笔画师</a>、<a href="<?= $bCover ?>">封绘艺术家</a>、<a href="<?= $bColor ?>">上色</a>。以及<a href="<?= $bFilms ?>">地狱男爵电影</a>相关条目。</p>
      <div class="portal-note">现代化阅读版当前收录 <b><?= number_format(count($ctx->titles)) ?></b> 个有效词条，按系列与人物分类整理。所有中文译名基于「不高兴汉化」版本，谨致谢意。</div>

      <h2 id="sec-series" class="portal-bar">核心漫画系列</h2>
      <div class="series-grid">
        <?php foreach ($series as [$file,$target]): ?>
          <a class="series-cell" href="<?= ent($target) ?>"><img src="<?= img($file) ?>" alt="<?= e($target) ?>" loading="lazy"></a>
        <?php endforeach; ?>
      </div>

      <h2 id="sec-editions" class="portal-bar"><?= $enHome ? 'Editions & Collections' : '出版与收藏' ?></h2>
      <div class="ref-grid">
        <?php foreach ($editions as [$slug,$zh,$en,$descZh,$descEn]): ?>
          <a class="ref-cell" href="/entry/<?= rawurlencode($slug) ?>">
            <span class="ref-name"><?= $enHome ? $en : $zh ?></span>
            <?php if (!$enHome): ?><span class="ref-en"><?= $en ?></span><?php endif; ?>
            <span class="ref-desc"><?= $enHome ? $descEn : $descZh ?></span>
          </a>
        <?php endforeach; ?>
      </div>

      <h2 id="sec-chars" class="portal-bar"><?= $enHome ? 'Core Characters' : '核心角色' ?></h2>
      <?php $charIcons = []; foreach ($charCols as $col) foreach ($col as $pair) $charIcons[] = $pair[0]; ?>
      <div class="char-icon-grid">
        <?php foreach ($charIcons as $ct): $cmeta = $ctx->meta($ct); $cname = $enHome ? $ctx->en_title($ct) : $ctx->zh_title($ct); ?>
          <a class="char-icon-cell" href="<?= ent($ct) ?>">
            <?php if (!empty($cmeta['image'])): ?><img class="char-icon-thumb" src="<?= img($cmeta['image']) ?>" alt="" loading="lazy"><?php else: ?><span class="char-icon-thumb noimg"><?= e(mb_substr($cname,0,1,'UTF-8')) ?></span><?php endif; ?>
            <span class="char-icon-name"><?= e($cname) ?></span>
          </a>
        <?php endforeach; ?>
      </div>

      <h2 id="sec-movies" class="portal-bar"><?= $enHome ? 'Films' : '电影' ?></h2>
      <?php
        $movieSections = [
          [$enHome ? 'Live-action films' : '真人电影', $movieGroups['live']],
          [$enHome ? 'Animated films, shorts &amp; pilot' : '动画电影 · 短片 · 试播集', $movieGroups['animated']],
        ];
      ?>
      <?php foreach ($movieSections as [$subTitle, $mv]): if (!$mv) continue; ?>
        <h3 class="movie-sub"><?= $subTitle ?> <span class="movie-sub-n"><?= count($mv) ?></span></h3>
        <div class="movie-row">
          <?php foreach ($mv as $mvItem): $mvLabel = $enHome ? $ctx->en_title($mvItem['title']) : $ctx->zh_title($mvItem['title']); ?>
            <a class="movie-cell" href="<?= ent($mvItem['title']) ?>">
              <img src="<?= img($mvItem['img']) ?>" alt="<?= e($mvLabel) ?>" loading="lazy">
              <span class="movie-name"><?= e($mvLabel) ?></span>
              <?php if ($enHome && $mvItem['year']): ?><span class="movie-year"><?= $mvItem['year'] ?></span><?php endif; ?>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
      <p class="portal-more-line"><a href="<?= $bFilms ?>"><?= $enHome ? 'Browse all film-related entries (soundtracks &amp; more) →' : '查看「电影」分类下全部条目（含原声专辑、宣传漫画等）→' ?></a></p>

      <h2 id="sec-src" class="portal-bar">关于本站 / 资料来源</h2>
      <div class="home-about">
        <p><b>地狱男爵中文维基</b> 基于 MediaWiki 起步，如今用纯 PHP + Markdown 文件重建，不依赖数据库、Docker 或 Node.js，以阅读体验为核心。这里早期完全开放编辑，如今主要作为站长本人的个人资料库；仍欢迎爱好者协助补充译名、纠错、补全条目，较复杂的更改请先联系站长对齐译名与体例。</p>
        <ul class="src-list">
          <li>基础英文内容来自 FANDOM <a href="https://hellboy.fandom.com" target="_blank" rel="noopener">Hellboy Wiki</a>；中文条目为手工翻译 + 机翻初稿，另参考<a href="https://zh.wikipedia.org" target="_blank" rel="noopener">维基百科</a>、<a href="http://tieba.baidu.com/f?kw=%E5%9C%B0%E7%8B%B1%E7%94%B7%E7%88%B5&amp;ie=utf-8" target="_blank" rel="noopener">地狱男爵吧</a>及网络文章。所有中文译名基于「不高兴汉化」版本，谨致谢意。</li>
          <li>本站不提供相关漫画资源下载；图文版权归原权利人所有，如涉侵权请联系站长处理。</li>
          <li>联系方式：<a href="https://rzx.me" target="_blank" rel="noopener">rzx.me</a> ｜ 微信：zhenxinfrozen。想按封面浏览全部条目？前往 <a href="/gallery">条目画廊</a>。</li>
        </ul>
        <details class="home-about-more">
          <summary>版本规划与技术说明</summary>
          <ul class="src-list">
            <li><b>18100 / 18102</b>：早期 MediaWiki 主站与归档站。</li>
            <li><b>18104</b>：定稿稳定基线（Wiki.js 风格三栏布局）。</li>
            <li><b>18105</b>：实验版——自定义页面、中英文切换、英文原文同步；<b>18106</b>：当前阅读版，全站机翻初稿收口。</li>
            <li>数据源：清理后的 MediaWiki 词条导出为 Markdown，信息框以 JSON 保存。顶栏 <b>中 / EN</b> 切换语言并记住选择；中文可在「机译 / 标准」间切换，英文正文缺失时自动回退中文。</li>
          </ul>
        </details>
      </div>
    </article>
    <?php
    $main = ob_get_clean();
    $right = '<div class="rail-card"><div class="rail-title">本页导航</div><ul class="rail-toc">'
        . '<li class="lv2"><a href="#sec-series">核心漫画系列</a></li>'
        . '<li class="lv2"><a href="#sec-editions">'.($enHome ? 'Editions & Collections' : '出版与收藏').'</a></li>'
        . '<li class="lv2"><a href="#sec-chars">'.($enHome ? 'Core Characters' : '核心角色').'</a></li>'
        . '<li class="lv2"><a href="#sec-movies">电影</a></li>'
        . '<li class="lv2"><a href="#sec-src">'.($enHome ? 'About / Sources' : '关于本站 / 资料来源').'</a></li></ul></div>';
    layout($ctx, '首页', $main, ['current'=>'home','right'=>$right]);
}

/* ---------- browse: 分类浏览页（筛选 + 封面卡片） ---------- */
function render_browse(Ctx $ctx, string $g, string $s, string $cat = ''): void {
    $sec = $ctx->find_section($g);
    if (!$sec) { render_404($ctx, 'browse'); return; }
    $secName = $sec['section'];
    $enMode = L()==='en';
    $crumbSub = $s !== '';

    // 门户配置（对齐 18100 MediaWiki 分类页：手写核心块 + 自动子分类）
    $portal = (!$crumbSub) ? $ctx->portal($secName) : null;
    // 把门户自动子分类规则展平成有序列表，供 cat=<idx> 服务端筛选
    $catRules = [];
    if ($portal) {
        foreach (($portal['subcat_groups'] ?? []) as $grp)
            foreach (($grp['cats'] ?? []) as $c) $catRules[] = $c;
    }
    $activeCat = -1;
    $activeCatLabel = '';

    // resolve item list
    $items = null;
    if ($s !== '') {
        foreach ($sec['groups'] ?? [] as $grp) if ($grp['sub']===$s) { $items = $grp['items']; break; }
        if ($items === null) { render_404($ctx, $g.'/'.$s); return; }
    } else {
        $items = $ctx->nav_items($sec);
        // 门户自动子分类筛选（cat=展平规则下标）
        if ($cat !== '' && $portal && ctype_digit($cat) && isset($catRules[(int)$cat])) {
            $activeCat = (int)$cat;
            $rule = $catRules[$activeCat];
            $activeCatLabel = $rule['label'];
            $want = array_map(fn($x)=>mb_strtolower($x,'UTF-8'), $rule['match'] ?? []);
            $items = array_values(array_filter($items, fn($t)=> (bool)array_intersect($ctx->cat_keys($t), $want)));
        }
    }
    ob_start(); ?>
    <div class="crumbs"><a href="/"><?= $enMode?'Home':'首页' ?></a> / <a href="/browse?g=<?= rawurlencode($secName) ?><?= $enMode?'&lang=en':'' ?>"><?= tr($secName) ?></a><?php if($crumbSub): ?> / <?= tr($s) ?><?php endif; ?><?php if($activeCat>=0): ?> / <?= e($activeCatLabel) ?><?php endif; ?></div>
    <div class="browse-head">
      <h1 class="page-title"><?= tr($secName) ?><?php if($crumbSub): ?><span class="bh-sub">· <?= tr($s) ?></span><?php elseif($activeCat>=0): ?><span class="bh-sub">· <?= e($activeCatLabel) ?></span><?php endif; ?></h1>
      <div class="browse-tools">
        <input type="search" id="browseFilter" class="browse-filter" placeholder="<?= $enMode ? ('Filter in this category… ('.count($items).')') : ('在本分类内筛选…（'.count($items).' 条）') ?>" autocomplete="off">
        <span class="browse-count" id="browseCount"><?= count($items) ?></span>
      </div>
    </div>

    <?php if ($portal && $activeCat < 0): ?>
      <?php if (!empty($portal['intro'])): ?><p class="portal-intro"><?= e($portal['intro']) ?></p><?php endif; ?>
      <?php
        // 手写核心块（核心人物 / 核心画师）
        $core = array_values(array_filter($portal['core'] ?? [], fn($t)=> $ctx->exists($t)));
      ?>
      <?php if ($core): ?>
        <section class="portal-core">
          <h2 class="portal-bar"><?= e($portal['core_title'] ?? ($enMode?'Core':'核心')) ?></h2>
          <ul class="portal-core-grid">
            <?php foreach ($core as $t): $m=$ctx->meta($t); $nm=$ctx->disp_title($t); ?>
              <li>
                <a class="pcore-item" href="<?= ent($t) ?>">
                  <?php if (!empty($m['image'])): ?><img class="pcore-thumb" src="<?= img($m['image']) ?>" alt="" loading="lazy"><?php else: ?><span class="pcore-thumb noimg"><?= e(mb_substr($nm,0,1,'UTF-8')) ?></span><?php endif; ?>
                  <span class="pcore-name"><?= e($nm) ?></span>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
        </section>
      <?php endif; ?>

      <?php if (!empty($portal['subcat_groups'])): ?>
        <?php
          // 预先把每组展平成 [全局下标, 规则]，下标与 catRules 一致供 cat=<idx> 筛选
          $flatGroups = [];
          $fi = -1;
          foreach ($portal['subcat_groups'] as $grp) {
              $row = ['group'=>$grp['group'], 'cats'=>[]];
              foreach ($grp['cats'] as $cc) { $fi++; $row['cats'][] = [$fi, $cc]; }
              $flatGroups[] = $row;
          }
        ?>
        <section class="portal-subcats">
          <?php foreach ($flatGroups as $grp): ?>
            <h2 class="portal-subcat-g"><?= e($grp['group']) ?></h2>
            <div class="portal-chips">
              <?php foreach ($grp['cats'] as [$ci,$c]): ?>
                <?php if ((int)($c['count']??0) <= 0) continue; ?>
                <a class="portal-chip" href="/browse?g=<?= rawurlencode($secName) ?>&amp;cat=<?= $ci ?><?= $enMode?'&amp;lang=en':'' ?>"><?= e($c['label']) ?><span class="pc-num"><?= (int)$c['count'] ?></span></a>
              <?php endforeach; ?>
            </div>
          <?php endforeach; ?>
        </section>
      <?php endif; ?>
    <?php elseif ($activeCat >= 0): ?>
      <p class="portal-back"><a href="/browse?g=<?= rawurlencode($secName) ?><?= $enMode?'&amp;lang=en':'' ?>">← <?= $enMode?'Back to category portal':'返回分类门户' ?></a></p>
    <?php endif; ?>

    <?php if (!$crumbSub && $activeCat < 0 && !empty($sec['groups']) && empty($portal['subcat_groups'])): ?>
      <?php if ($portal): ?><h2 class="portal-subcat-g"><?= $enMode?'By role / type':'按职能 / 类型' ?></h2><?php endif; ?>
      <div class="subchip-row" id="subChips">
        <?php foreach ($sec['groups'] as $grp): ?>
          <a class="subchip" href="/browse?g=<?= rawurlencode($secName) ?>&amp;s=<?= rawurlencode($grp['sub']) ?><?= $enMode?'&amp;lang=en':'' ?>"><?= tr($grp['sub']) ?><span><?= count($grp['items']) ?></span></a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php
      // A–Z 字母索引（依据英文固定链接名首字母；卡片 data-letter 供客户端筛选）
      $letters = [];
      foreach ($items as $t) { $l = strtoupper(substr(preg_replace('/^[^A-Za-z]+/','', hb_slug_of($t)),0,1)); if ($l==='') $l='#'; $letters[$l]=true; }
      $letterOrder = array_merge(range('A','Z'),['#']);
      $availLetters = array_values(array_intersect($letterOrder, array_keys($letters)));
    ?>
    <?php if ($portal && count($items) > 20): ?>
      <div class="portal-az" id="portalAz">
        <span class="az-label"><?= $enMode?'A–Z index':'字母索引' ?></span>
        <?php foreach ($availLetters as $L): ?><button type="button" class="az-btn" data-letter="<?= e($L) ?>"><?= e($L) ?></button><?php endforeach; ?>
        <button type="button" class="az-btn az-all" data-letter=""><?= $enMode?'All':'全部' ?></button>
      </div>
    <?php endif; ?>

    <div class="card-grid browse-grid" id="browseGrid">
      <?php foreach ($items as $t): $ib=$enMode?$ctx->ib_for($t):$ctx->meta($t); $cardName=$ctx->disp_title($t); $dl0=strtoupper(substr(preg_replace('/^[^A-Za-z]+/','',hb_slug_of($t)),0,1)); $dl=($dl0===''?'#':$dl0); ?>
        <a class="entry-card" data-name="<?= e(mb_strtolower($cardName.' '.$t)) ?>" data-letter="<?= e($dl) ?>" href="<?= ent($t) ?>">
          <?php if (!empty($ib['image'])): ?>
            <div class="card-thumb"><img src="<?= img($ib['image']) ?>" alt="" loading="lazy"></div>
          <?php else: ?><div class="card-thumb is-placeholder"><img src="<?= img('placeholder-bprd.png') ?>" alt=""></div><?php endif; ?>
          <div class="card-meta">
            <span class="card-type"><?= e($ib['type_zh'] ?: tr($secName)) ?></span>
            <h3><?= e($cardName) ?></h3>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
    <div class="empty" id="browseEmpty" hidden><?= $enMode?'No matching entries.':'没有匹配的条目。' ?></div>
    <script>
    (function(){
      var f=document.getElementById('browseFilter'),
          cards=Array.prototype.slice.call(document.querySelectorAll('#browseGrid .entry-card')),
          cnt=document.getElementById('browseCount'), emp=document.getElementById('browseEmpty'),
          activeLetter='';
      function apply(){
        var q=f?f.value.trim().toLowerCase():'';var n=0;
        cards.forEach(function(c){
          var okName=(q==='')||(c.getAttribute('data-name')||'').indexOf(q)!==-1;
          var okLetter=(activeLetter==='')||(c.getAttribute('data-letter')===activeLetter);
          var ok=okName&&okLetter; c.style.display=ok?'':'none'; if(ok)n++;
        });
        if(cnt)cnt.textContent=n; if(emp)emp.hidden=(n!==0);
      }
      if(f)f.addEventListener('input',apply);
      document.querySelectorAll('#portalAz .az-btn').forEach(function(b){
        b.addEventListener('click',function(){
          activeLetter=b.getAttribute('data-letter')||'';
          document.querySelectorAll('#portalAz .az-btn').forEach(function(x){x.classList.remove('on')});
          if(activeLetter!=='')b.classList.add('on');
          apply();
        });
      });
      if(f)f.focus();
    })();
    </script>
    <?php
    layout($ctx, ($crumbSub?tr($s).' · ':(($activeCat>=0)?$activeCatLabel.' · ':'')).tr($secName), ob_get_clean(),
      ['current'=>'','browseGroup'=>$secName,'browseSub'=>$s]);
}

/* ---------- gallery: 原卡片墙，独立页 ---------- */
function render_gallery(Ctx $ctx): void {
    $enMode = L()==='en';
    ob_start(); ?>
    <div class="crumbs"><a href="/"><?= $enMode?'Home':'首页' ?></a> / <?= $enMode?'Gallery':'条目画廊' ?></div>
    <h1 class="page-title"><?= $enMode?'Gallery':'条目画廊' ?></h1>
    <p class="page-sub"><?= $enMode?'Browse every entry by cover thumbnail.':'按封面缩略图浏览全部条目。' ?></p>
    <?php foreach ($ctx->nav as $sec): $allItems=$ctx->nav_items($sec); ?>
      <section class="gal-section">
        <h2 class="gal-title"><?= tr($sec['section']) ?><span class="count"><?= count($allItems) ?></span></h2>
        <?php foreach ($sec['groups'] ?? [['sub'=>null,'items'=>$allItems]] as $grp): if(!empty($grp['sub'])): ?>
          <h3 class="gal-subtitle"><?= tr($grp['sub']) ?><span class="count"><?= count($grp['items']) ?></span></h3>
        <?php endif; ?>
        <div class="card-grid">
          <?php foreach ($grp['items'] as $t): $ib=$enMode?$ctx->ib_for($t):$ctx->meta($t); $cardName=$ctx->disp_title($t); ?>
            <a class="entry-card" href="<?= ent($t) ?>">
              <?php if (!empty($ib['image'])): ?>
                <div class="card-thumb"><img src="<?= img($ib['image']) ?>" alt="" loading="lazy"></div>
              <?php else: ?><div class="card-thumb is-placeholder"><img src="<?= img('placeholder-bprd.png') ?>" alt=""></div><?php endif; ?>
              <div class="card-meta">
                <span class="card-type"><?= e($ib['type_zh'] ?: ($enMode?'Entry':'条目')) ?></span>
                <h3><?= e($cardName) ?></h3>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
      </section>
    <?php endforeach;
    layout($ctx, $enMode?'Gallery':'条目画廊', ob_get_clean(), ['current'=>'gallery']);
}

/* ---------- 正文固定链接改写：所有站内条目统一输出英文 permalink ----------
 * 正文源文件里可能同时存在中文路径、Fandom 英文名、旧别名；渲染时统一解析。
 * 1) 站内有条目 → /entry/<英文固定链接名>，不带 lang，语言由 cookie/顶栏决定
 * 2) 站内没有 → 外链 Fandom / Wikipedia 等英文源页，不再 404
 * 3) 跨维基残留（w:c: / wikipedia: / pt-br: / Special）→ 对应外链
 */
function en_url_encode_title(string $t): string {
    $t = str_replace(' ', '_', $t);
    $t = rawurlencode($t);
    return str_replace(['%28','%29','%3A','%27','%3B'], ['(',')',":","'",';'], $t);
}
function en_link_href(Ctx $ctx, string $rawSlug): ?string {
    global $HB_SLUGS;
    $slug = rawurldecode($rawSlug);
    // href 属性里可能残留 HTML 实体（&#39; / &quot; / &amp;），先解码再做后续百分号编码，避免 & 被二次编码成 %26
    $slug = html_entity_decode($slug, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    // 分离 #锚点
    $frag = '';
    if (($p = strpos($slug, '#')) !== false) { $frag = substr($slug, $p); $slug = substr($slug, 0, $p); }
    // pandoc/迁移残留：\xAA(ª)、\xAB(«) 实际是冒号替身（如 %AAwikipedia%AC）
    $slug = str_replace(["\xAA", "\xAB", "\xAC"], ':', $slug);
    $slug = trim($slug, " \t\\\/");
    $slug = ltrim($slug, ':');   // %3Awikipedia%3A 这类带前导冒号
    // 本站路由前缀 /entry/：表格内链接由 sanitize_html_table 直接调用本函数时仍带着它，
    // 不剥会把 entry/Title 当成标题查本地（必失配）并回退成 wiki/entry%2FTitle 死链
    if (preg_match('#^entry/#i', $slug)) $slug = substr($slug, 6);
    if ($slug === '') return null;
    // 跨 Fandom 维基：w:c:marvel:Title → https://marvel.fandom.com/wiki/Title
    if (preg_match('#^w:c:([^:]+):(.+)$#i', $slug, $mm)) {
        return 'https://' . strtolower($mm[1]) . '.fandom.com/wiki/' . en_url_encode_title(ltrim($mm[2], ':')) . $frag;
    }
    // 英文维基百科：wikipedia:Title
    if (preg_match('#^wikipedia:(.+)$#i', $slug, $mm)) {
        return 'https://en.wikipedia.org/wiki/' . en_url_encode_title($mm[1]) . $frag;
    }
    // Fandom 葡语分站：pt-br:Title
    if (preg_match('#^pt-br:(.+)$#i', $slug, $mm)) {
        return 'https://hellboy.fandom.com/pt-br/wiki/' . en_url_encode_title($mm[1]) . $frag;
    }
    // 分类/文件页残留 → Fandom 源站
    if (preg_match('#^:?(?:Category|File|FIle):(.+)$#i', $slug, $mm)) {
        $ns = preg_match('#^:?File#i', $slug) ? 'File' : 'Category';
        return 'https://hellboy.fandom.com/wiki/' . $ns . ':' . en_url_encode_title($mm[1]) . $frag;
    }
    // Special:BookSources 等特殊页
    if (preg_match('#^:?Special:(.+)$#i', $slug, $mm)) {
        return 'https://hellboy.fandom.com/wiki/Special:' . en_url_encode_title($mm[1]) . $frag;
    }
    // 站内实体：优先英文固定链接反查，其次旧英文别名，最后本地同名中文页
    $norm = str_replace('_', ' ', ltrim($slug, ':'));
    $canon = $ctx->slug_title($norm);
    if ($canon === null) $canon = $ctx->alias_canon($norm);
    if ($canon === null && $ctx->exists($norm)) $canon = $norm;
    // 迁移脏数据：所有格撇号被错写/截断成结尾 &（如 Dr._Carp%26 应为 Dr._Carp%27s_Experiment）。
    // 仅当去 & 前缀能【唯一】匹配到某个本地所有格标题时才还原；多义或无匹配一律不猜，继续走 Fandom 回退。
    if ($canon === null && preg_match('#^(.+)&$#u', $norm, $pm)) {
        $prefix = mb_strtolower(rtrim($pm[1]));
        $hits = [];
        foreach (($HB_SLUGS ?? []) as $title => $sl) {
            $cand = mb_strtolower(str_replace('_', ' ', trim((string)$sl)));
            $nextCh = mb_strlen($cand) > mb_strlen($prefix) ? mb_substr($cand, mb_strlen($prefix), 1) : '';
            if (str_starts_with($cand, $prefix) && in_array($nextCh, [mb_chr(0x2019), "'"], true)) {
                $hits[$cand] = (string)$title;
            }
        }
        if (count($hits) === 1) $canon = reset($hits);
    }
    if ($canon !== null) {
        return ent($canon) . $frag;   // 统一英文 permalink；语言跟 cookie，不污染固定链接
    }
    // 本地确实没有 → Fandom 英文源页（保持 Fandom 原始标题/下划线写法）
    // 收尾清洗：无法唯一还原的“所有格被截断成结尾 &”脏 slug（如 World&），去掉孤立 &，
    // 避免把 World& 这种非法标题拼进 Fandom URL（丢一个 & 比丢整词风险小，且本地已确认无此页）
    if (preg_match('#^(.+)&$#u', $slug)) $slug = rtrim($slug, '&');
    return 'https://hellboy.fandom.com/wiki/' . en_url_encode_title($slug) . $frag;
}
function rewrite_entry_links(Ctx $ctx, string $html): string {
    // 旧迁移残留的文末分类标签：**Category:X** 在 markdown 阶段已剥；这里兜底删 HTML 链接
    $html = preg_replace('#<a\s+[^>]*href="[^"]*"[^>]*>\s*Category\s*:[^<]*</a>\s*#iu', '', $html);

    // 旧域绝对地址归一：https://hellboy.rzx.me/<标题> → 本地英文 permalink（动态化，不写死域名）
    // 旧站是根路径形式（/中文%E6%A0%87%E9%A2%98），也兼容 /wiki/ 前缀。本地无此条目才保留为外链。
    $resolveOld = function(string $encPath) use ($ctx): ?string {
        $path = rawurldecode($encPath);
        // 去 query / fragment
        foreach (['?','#'] as $ch) { if (($p = strpos($path, $ch)) !== false) $path = substr($path, 0, $p); }
        $path = ltrim($path, '/');
        if (preg_match('#^wiki/#i', $path)) $path = substr($path, 5);
        $path = trim(str_replace('\xAA\xAB\xAC', ':', $path));
        if ($path === '') return null;
        // 1) 本地中文页直连（全角括号标题等）
        if ($ctx->exists($path)) return ent($path);
        // 2) 英文路径：下划线→空格后反查 slug / 合并别名
        $norm = str_replace('_', ' ', ltrim($path, ':'));
        $canon = $ctx->slug_title($norm);
        if ($canon === null) $canon = $ctx->alias_canon($norm);
        if ($canon !== null) return ent($canon);
        return null;   // 本地无此条目：交回调用方保留原外链
    };
    $html = preg_replace_callback('~<a\s+([^>]*?)href="https?://(?:www\.)?hellboy\.rzx\.me/([^"#?]*)((?:\?|#)[^"]*)?"([^>]*)>(.*?)</a>~si', function($m) use ($resolveOld) {
        $local = $resolveOld($m[2]);
        if ($local === null) return $m[0];   // 本地没有：保留旧域外链（不人为破坏）
        $pre = $m[1]; $post = $m[4];
        // 去掉旧外链专属的 target/rel（站内跳转不应新开窗）
        $attrs = $pre . ' ' . $post;
        $attrs = preg_replace('#\s*target="[^"]*"#i', '', $attrs);
        $attrs = preg_replace('#\s*rel="[^"]*"#i', '', $attrs);
        return '<a href="' . e($local) . '"' . $attrs . '>' . $m[5] . '</a>';
    }, $html);

    return preg_replace_callback('#<a\s+href="/entry/([^"]*)"([^>]*)>(.*?)</a>#s', function($m) use ($ctx) {
        $href = en_link_href($ctx, $m[1]);
        if ($href === null) return '<span>' . $m[3] . '</span>';   // 空链接：降级为纯文本
        if (str_starts_with($href, 'http')) {
            return '<a href="' . e($href) . '" target="_blank" rel="noopener"' . $m[2] . '>' . $m[3] . '</a>';
        }
        return '<a href="' . e($href) . '"' . $m[2] . '>' . $m[3] . '</a>';
    }, $html);
}

/* ---------- entry ---------- */

// 《地狱男爵》漫画出版时间线：源 md 保留 Google-Docs 风格的彩色 <span>，普通 md_html 会剥光样式只剩文本。
// 这里专用解析，重建颜色（对应各系列平装本书脊色）+ 年份/日期层级。
function hb_tl_color(?string $c): ?string {
    if (!$c) return null;
    $c = strtolower(trim($c));
    $map = [
        'red'=>'red', 'rgb(255, 0, 0)'=>'red',
        'olive'=>'olive', 'rgb(128, 128, 0)'=>'olive', 'rgb(147, 150, 0)'=>'olive',
        'gray'=>'gray', 'rgb(128, 128, 128)'=>'gray', 'rgb(121, 121, 121)'=>'gray',
        'blue'=>'blue', 'rgb(54, 101, 238)'=>'blue', 'rgb(45, 79, 201)'=>'blue',
        'green'=>'green', 'rgb(50, 135, 18)'=>'green',
        'brown'=>'brown', 'rgb(165, 42, 42)'=>'brown',
        'rgb(217, 104, 38)'=>'orange',
        'mediumpurple'=>'purple', 'darkturquoise'=>'teal',
        'rgb(51, 51, 51)'=>'ink',
    ];
    return $map[$c] ?? null;
}

// 把一行 HTML span 解析成 [text,color,bold,italic,small]
function hb_tl_tokens(string $line): array {
    $out = [];
    if (!preg_match_all('/<span\b([^>]*)>(.*?)<\/span>/s', $line, $ms, PREG_SET_ORDER)) return $out;
    foreach ($ms as $m) {
        $style = $m[1];
        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($m[2]), ENT_QUOTES|ENT_HTML5, 'UTF-8')));
        if ($text === '') continue;
        $col = null;
        if (preg_match('/(?:^|;)\s*color:\s*([^;"]+);/i', $style, $cm)) $col = hb_tl_color($cm[1]);
        $out[] = [
            't' => $text,
            'c' => $col,
            'b' => stripos($style, 'font-weight: bold') !== false,
            'i' => stripos($style, 'font-style: italic') !== false,
            's' => stripos($style, 'xx-small') !== false,
        ];
    }
    return $out;
}

function render_timeline_html(string $raw): array {
    $blocks = preg_split('/\n\s*\n/', $raw) ?: [];
    $legendHtml = '';
    $years = [];   // ['y'=>..,'rows'=>[ [date, innerHtml] ]]
    $bi = -1;
    foreach ($blocks as $b) {
        $bi++;
        $b = trim(str_replace("\r", '', $b));
        if ($b === '') continue;
        // 注意：不能在这里删 </span>，否则 token 正则无法配对；裸标签由 strip_tags 兑底
        $lines = preg_split('/  \n|\n/', $b) ?: [];
        foreach ($lines as $ln) {
            $ln = trim($ln);
            if ($ln === '') continue;
            $toks = hb_tl_tokens($ln);
            if (!$toks) {
                // 无 span 的纯文本（引言等）
                $txt = trim(html_entity_decode(strip_tags($ln), ENT_QUOTES|ENT_HTML5, 'UTF-8'));
                if ($txt !== '' && $bi === 0) $legendHtml .= '<p class="hb-tl-intro">'.e($txt).'</p>';
                continue;
            }
            // 年份行：首个 token 为 4 位年
            if (preg_match('/^(?:19|20)\d{2}$/', $toks[0]['t'])) {
                $years[] = ['y'=>$toks[0]['t'], 'rows'=>[]];
                continue;
            }
            // 事件行
            $date = null;
            if ($toks[0]['s'] && $toks[0]['b']) $date = $toks[0]['t'];
            if (!$date && preg_match('/^([A-Z]{3,9}(?:\s+\d{1,2})?)\b/', $toks[0]['t'], $dm)) $date = $dm[1];
            // 行主色（首个非 ink 的彩色 token）
            $rowCol = null;
            foreach ($toks as $tk) if ($tk['c'] && !in_array($tk['c'], ['ink'], true)) { $rowCol = $tk['c']; break; }
            // 判断是否在年表区（有日期才算事件；图例行无日期）
            if ($date && $years) {
                // 渲染日期之后的正文 token（跳过日期本身）
                $inner = '';
                $started = false;
                foreach ($toks as $idx=>$tk) {
                    if ($idx===0) continue; // 日期列单独渲染
                    $inner .= hb_tl_span($tk);
                }
                $inner = preg_replace('/^\s*[\-–—―]+\s*/u', '', trim($inner));
                $years[count($years)-1]['rows'][] = ['d'=>$date, 'c'=>$rowCol, 'html'=>$inner];
            } else {
                // 图例行：彩色重绘；去掉源数据里被截断的孤立书名号《
                while ($toks && trim(end($toks)['t']) === '《') array_pop($toks);
                while ($toks && trim($toks[0]['t']) === '《') array_shift($toks);
                $line = '';
                foreach ($toks as $tk) $line .= hb_tl_span($tk);
                if (trim(strip_tags($line)) !== '') $legendHtml .= '<div class="hb-tl-legend-row">'.$line.'</div>';
            }
        }
    }
    // 组装
    $h = '';
    if ($legendHtml !== '') $h .= '<div class="hb-tl-legend">'.$legendHtml.'</div>';
    foreach ($years as $yr) {
        $h .= '<section class="hb-tl-year"><h2 class="hb-tl-yhead" id="sec-y-'.e($yr['y']).'">'.e($yr['y']).'</h2>';
        foreach ($yr['rows'] as $r) {
            $h .= '<div class="hb-tl-row'.($r['c']?' hb-tl-'.$r['c']:'').'">'
                . '<span class="hb-tl-date">'.e($r['d']).'</span>'
                . '<span class="hb-tl-body">'.$r['html'].'</span></div>';
        }
        $h .= '</section>';
    }
    $toc = array_map(fn($yr)=>['level'=>2,'text'=>$yr['y'],'id'=>'sec-y-'.$yr['y']], $years);
    return [$h, $toc];
}

function hb_tl_span(array $tk): string {
    $cls = [];
    if ($tk['c'] && $tk['c']!=='ink') $cls[] = 'hb-tc-'.$tk['c'];
    if ($tk['b']) $cls[] = 'hb-tb';
    if ($tk['i']) $cls[] = 'hb-ti';
    if ($tk['s']) $cls[] = 'hb-ts';
    // 系列书脊色用于粗体的卷号 token；普通英文故事名斜体不上彩色
    $attr = $cls ? ' class="'.implode(' ', $cls).'"' : '';
    return '<span'.$attr.'>'.e($tk['t']).'</span> ';
}

/** 表格内图片分类：竖版书封→hb-cover（统一 2:3 缩略图）；横向系列 logo→hb-banner（限高）。尺寸结果静态缓存。 */
function hb_tbl_img_class(string $src): string {
    static $cache = [];
    $key = $src;
    if (array_key_exists($key, $cache)) return $cache[$key];
    $cls = 'hb-cover';   // 默认按书封处理（含无法读取的外链图）
    if (str_starts_with($src, '/content/')) {
        $file = __DIR__ . rawurldecode(parse_url($src, PHP_URL_PATH) ?? '');
        if (is_file($file)) {
            $sz = @getimagesize($file);
            if ($sz && $sz[0] > 0 && $sz[1] > 0) {
                $ratio = $sz[0] / $sz[1];
                if ($ratio > 1.25)      $cls = 'hb-banner';  // 横向系列 logo（如 Front_Page_* 2.88:1）
                elseif ($ratio < 0.9)   $cls = 'hb-cover';
            }
        }
    }
    return $cache[$key] = $cls;
}

/** 消毒单个迁移遗留的原生 HTML 表格：仅保留白名单标签/属性，旧式 wiki 链接统一解析为英文 permalink。 */
function sanitize_html_table(Ctx $ctx, string $t): string {
    // 1) 链接重写：href="Title_(x)" 走 en_link_href（本地→permalink，缺失→Fandom）；页内锚点/脚注只留文字；外链新开窗
    $t = preg_replace_callback('#<a\s+([^>]*)>(.*?)</a>#is', function($m) use ($ctx) {
        $inner = $m[2];
        if (!preg_match('#href\s*=\s*"([^"]*)"#i', $m[1], $h)) return $inner;
        $rawHref = $h[1];
        $dec = html_entity_decode($rawHref, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($dec === '' || $dec[0] === '#' || preg_match('#^mailto:#i', $dec)) {
            return $dec[0]==='#' ? $inner : '<a href="'.e($dec).'">'.$inner.'</a>';
        }
        if (preg_match('#^https?://#i', $dec)) {
            return '<a href="'.e($dec).'" target="_blank" rel="noopener">'.$inner.'</a>';
        }
        $resolved = en_link_href($ctx, $rawHref);
        $ext = is_string($resolved) && preg_match('#^https?://#', $resolved);
        return '<a href="'.e((string)$resolved).'"'.($ext?' target="_blank" rel="noopener"':'').'>'.$inner.'</a>';
    }, $t);
    // 2) span 只是迁移样式容器，拆包保留文字；清空空段落
    $t = preg_replace('#</?span\b[^>]*>#i', '', $t);
    $t = preg_replace('#<p>\s*</p>#i', '', $t);
    // 3) 标签/属性白名单重建（防内联事件、样式、脚本）
    $allowed = ['table','thead','tbody','tfoot','tr','th','td','caption','colgroup','col',
                'p','br','a','em','strong','b','i','u','sup','sub','ul','ol','li','img'];
    $keepAttr = ['a'=>['href'], 'img'=>['src','alt'], 'td'=>['colspan','rowspan'],
                 'th'=>['colspan','rowspan'], 'col'=>['span']];
    $t = preg_replace_callback('#<(/?)([a-zA-Z][a-zA-Z0-9]*)((?:[^>]*?))(/?)>#s', function($m) use ($allowed,$keepAttr) {
        $close=$m[1]; $tag=strtolower($m[2]); $rest=$m[3]; $self=$m[4];
        if (!in_array($tag,$allowed,true)) return '';          // 未知标签：丢弃标签、保留内层文字
        if ($close) return '</'.$tag.'>';
        if ($tag==='table') return '<table class="hb-utable">';
        $attrs=[];
        if (preg_match_all('#([a-zA-Z\-]+)\s*=\s*"([^"]*)"#', $rest, $am, PREG_SET_ORDER)) {
            foreach ($am as $a) {
                $k=strtolower($a[1]);
                if (in_array($k, $keepAttr[$tag] ?? [], true)) $attrs[$k]=$a[2];
            }
        }
        if ($tag==='img' && isset($attrs['src'])) {
            $src=$attrs['src'];
            if (!preg_match('#^https?://#i',$src) && !str_starts_with($src,'/content/')) {
                $src='/content/images/'.basename(rawurldecode(explode('?',$src)[0]));
            }
            $attrs['src']=$src; $attrs['loading']='lazy';
            $attrs['class']=hb_tbl_img_class($src);
        }
        $out='<'.$tag;
        foreach ($attrs as $k=>$v) $out.=' '.$k.'="'.e($v).'"';
        if ($self!=='' || in_array($tag,['br','col','img'],true)) $out.=' /';
        return $out.'>';
    }, $t);
    // 4) 单元格内残留的 Markdown 图片/粗斜体转 HTML（表格在 md 渲染前被抽出，不会被 Parsedown 处理）
    $t = preg_replace_callback('#!\[([^\]]*)\]\(([^)\s]+)(?:\s+"[^"]*")?\)#', function($im) {
        $src=$im[2];
        if (!preg_match('#^https?://#i',$src) && !str_starts_with($src,'/')) {
            $src='/content/images/'.basename(rawurldecode(explode('?',$src)[0]));
        }
        $cls = hb_tbl_img_class($src);
        return '<img src="'.e($src).'" alt="'.e($im[1]).'" loading="lazy" class="'.$cls.'" />';
    }, $t);
    $t = preg_replace('#\*\*([^*]+)\*\*#u','<strong>$1</strong>',$t);
    $t = hb_normalize_table_columns($t);
    return $t;
}

/**
 * 统一合订本类表格的前几列布局（纯渲染层，md 源不动）：
 *  1) 若首列表头是序号「#」（部分表有、部分表无），整列删除；
 *  2) 把 Cover（封面）列一律置顶为第 1 列。
 * 用支持 rowspan/colspan 的二维网格实现；跨被删列的单元格 colspan-1。若发现被删列含 rowspan 错点则放弃删除以保安全。
 */
function hb_normalize_table_columns(string $t): string {
    if (!preg_match_all('#<tr\b[^>]*>.*?</tr>#is', $t, $rm, PREG_SET_ORDER)) return $t;
    // 1) 解析每行单元格；$store[id] = 单元格
    $store=[]; $rowMeta=[];
    foreach ($rm as $r) {
        $full=$r[0];
        if (!preg_match('#^(<tr\b[^>]*>)(.*)</tr>$#is',$full,$tm)) { $rowMeta[]=null; continue; }
        $ids=[];
        if (preg_match_all('#<(t[hd])\b([^>]*)>(.*?)</t[hd]>#is',$tm[2],$cm,PREG_SET_ORDER)) {
            foreach ($cm as $c) {
                $rs=1;$cs=1;
                if (preg_match('#\browspan\s*=\s*"(\d+)"#i',$c[2],$m)) $rs=max(1,(int)$m[1]);
                if (preg_match('#\bcolspan\s*=\s*"(\d+)"#i',$c[2],$m)) $cs=max(1,(int)$m[1]);
                $id=count($store);
                $store[$id]=['tag'=>strtolower($c[1]),'attr'=>$c[2],'inner'=>$c[3],'rs'=>$rs,'cs'=>$cs,'ar'=>-1,'ac'=>-1];
                $ids[]=$id;
            }
        }
        $rowMeta[]=['open'=>$tm[1],'ids'=>$ids];
    }
    // 2) 二维网格：格子内存放单元格 id；跨行/列的多个格子指向同一 id
    $grid=[]; $ncol=0;
    foreach ($rowMeta as $ri=>$meta) {
        if ($meta===null) continue;
        $c=0;
        foreach ($meta['ids'] as $id) {
            while (isset($grid[$ri][$c])) $c++;
            $cell=&$store[$id];
            for ($dr=0;$dr<$cell['rs'];$dr++)
                for ($dc=0;$dc<$cell['cs'];$dc++) $grid[$ri+$dr][$c+$dc]=$id;
            $cell['ar']=$ri; $cell['ac']=$c;
            unset($cell);
            $c+=$store[$id]['cs'];
        }
        $ncol=max($ncol,$c);
    }
    // 3) 从表头行读取列名，定位 # 列与 Cover 列
    $heads=[]; $hashCol=-1; $coverCol=-1;
    foreach ($rowMeta as $meta) {
        if ($meta===null) continue;
        $isHead=false; foreach($meta['ids'] as $id) if($store[$id]['tag']==='th'){$isHead=true;break;}
        if(!$isHead) continue;
        foreach ($meta['ids'] as $id) {
            $cell=$store[$id];
            $name=trim(preg_replace('#<[^>]+>#','',$cell['inner']));
            $heads[$cell['ac']]=$name;
            if ($name==='#' && $hashCol===-1) $hashCol=$cell['ac'];
            if ((strcasecmp($name,'Cover')===0 || $name==='封面') && $coverCol===-1) $coverCol=$cell['ac'];
        }
        break;
    }
    // 表头缺封面名时：找含 hb-cover 封面图的列（取出现次数最多且唯一最多者）
    if ($coverCol<0) {
        $imgCols=[];
        foreach ($store as $cell) {
            if ($cell['tag']!=='td' || $cell['ar']<0) continue;
            if (preg_match('#<img\b[^>]*class="[^"]*hb-cover#i',$cell['inner'])) $imgCols[$cell['ac']]=( $imgCols[$cell['ac']]??0)+1;
        }
        if ($imgCols) {
            arsort($imgCols); $top=array_keys($imgCols);
            if (count($top)===1 || $imgCols[$top[0]]>$imgCols[$top[1]]) $coverCol=$top[0];
        }
    }
    // 确定要删除的列（仅当 # 位于第 1 列）
    $removeCol = ($hashCol===0) ? 0 : -1;
    if ($removeCol===-1 && $coverCol<=0) return $t;   // 无 # 可删，且 Cover 已在首列/不存在
    // 4) 计算旧列 → 新列映射
    $oldCols=range(0,$ncol-1);
    if ($removeCol>=0) $oldCols=array_values(array_diff($oldCols,[$removeCol]));
    // 在剩余列中把 Cover 置顶
    foreach ($oldCols as $k=>$oc) { if ($oc===$coverCol) { array_splice($oldCols,$k,1); array_unshift($oldCols,$oc); break; } }
    // 5) 逐行重建；输出的单元格按新列序，仅在错点输出一次，并修正 colspan
    $newRows=[]; $ri=0;
    foreach ($rowMeta as $meta) {
        if ($meta===null) { $newRows[]=$rm[$ri][0]; $ri++; continue; }
        $out=$meta['open'];
        foreach ($oldCols as $oldC) {
            if (!isset($grid[$ri][$oldC])) continue;
            $id=$grid[$ri][$oldC]; $cell=$store[$id];
            if ($cell['ar']!==$ri || $cell['ac']!==$oldC) continue;
            $attr=$cell['attr']; $cs=$cell['cs'];
            if ($removeCol>=0 && $cs>1) {
                // 该单元格跨过被删列则跨度减 1（错点不在被删列）
                if ($cell['ac']<$removeCol && $cell['ac']+$cs-1>=$removeCol) $cs--;
                elseif ($cell['ac']===$removeCol) continue; // 理论上不会走到（错点在被删列）
                $attr=preg_replace('#\bcolspan\s*=\s*"\d+"#i','colspan="'.$cs.'"',$attr);
                if (!preg_match('#\bcolspan\s*=#i',$attr) && $cs>1) $attr.=' colspan="'.$cs.'"';
            }
            $out.='<'.$cell['tag'].$attr.'>'.$cell['inner'].'</'.$cell['tag'].'>';
        }
        $out.='</tr>';
        $newRows[]=$out; $ri++;
    }
    $i=0;
    return preg_replace_callback('#<tr\b[^>]*>.*?</tr>#is', function($m) use (&$i,$newRows){
        return $newRows[$i++] ?? $m[0];
    }, $t);
}

/**
 * 迁移遗留修复：很多中文页把原本位于 EN 源文末 ## Gallery 的封面图提到了正文开头，
 * 既与右侧信息框封面重复，又把标题/导语挤成“配图”。此函数在渲染层把这张同图移回文末。
 * 规则（保守）：
 *  - 仅处理中文正文（EN 原文保持 Fandom 原顺序不动）；
 *  - 仅当正文第一张独立成段图片与信息框 meta.image 是同一张时才移动（无封面的页不动）；
 *  - md 源零改动，只调整渲染输入；文末若无 Gallery 标题则补一个。
 */
function hb_relocate_lead_cover(string $md, ?string $cover): string {
    if ($cover===null || $cover==='') return $md;
    $norm = function(string $u): string {
        $u = rawurldecode($u);
        $u = preg_split('/[?#]/', $u)[0];
        $u = basename(str_replace('\\','/',$u));
        return mb_strtolower(preg_replace('/[\s_\-]+/u','',$u));
    };
    $coverN = $norm($cover);
    if ($coverN==='') return $md;
    // 仅匹配正文最前面的独立成段图片（允许前置空行）
    if (!preg_match('/\A\s*!(?:\[[^\]]*\])?\(([^)]+)\)\s*(?:\n|$)/u', $md, $m, PREG_OFFSET_CAPTURE)) return $md;
    $imgUrl = $m[1][0];
    if ($norm($imgUrl) !== $coverN) return $md;
    $full = $m[0][0];
    // 从开头移除该图片
    $rest = substr($md, strlen($full));
    $rest = preg_replace('/^\s*/u','',$rest,1);
    // 移到文末：已存在 Gallery 标题则追加到末尾；否则补一个
    $imgMd = '![]('.$imgUrl.')';
    $tail = rtrim($rest);
    // 去重：若文末正文/Gallery 已含同一张图（常见于开头封面=Gallery 第一张），只移除开头图，不再重复追加
    $already = false;
    if (preg_match_all('/!\[[^\]]*\]\(([^)]+)\)/u', $tail, $imAll)) {
        foreach ($imAll[1] as $u) { if ($norm($u) === $coverN) { $already = true; break; } }
    }
    if ($already) return $tail;
    if (preg_match('/^(#{1,4})\s*(?:gallery|画廊)\s*$/uim', $tail, $gm, PREG_OFFSET_CAPTURE)) {
        // 已有 Gallery：封面插到标题正下方，避免被挤到文末导航链接之后
        $at = $gm[0][1] + strlen($gm[0][0]);
        return substr($tail, 0, $at) . "\n\n" . $imgMd . "\n" . substr($tail, $at);
    }
    return $tail . "\n\n## 画廊\n\n" . $imgMd . "\n";
}

/**
 * 迁移遗留修复（仅中文渲染层，md 源零改动，EN 不动）：
 * 还原 Gallery/Cover Art 等处的 MediaWiki 残留：<File:名|图注>、<文件:..>、裸 "文件:名.jpg|图注"。
 * 兼容畸形：%7C 编码管道、尖括号只包住首词（<File:Abe> Sapien 12.jpg）、File:名|600px|图注。
 * 磁盘真实存在的图 → 独立成段 md 图片（交给 hb_gallery_group 收拢网格）；缺图整条丢弃，不造死链。
 * 顺带清理：! colspan=N | 导航残骸（保留内层链接）、整行跨语言 interwiki（de:/sv: 等）、相邻重复 **加粗**。
 */
/**
 * 展开被压平成一行空格分隔文件名的 MediaWiki <gallery>（迁移遗留，全站仅个别页）。
 * 形如：A.jpg B 2.jpg C 3.PNG  —— 文件名本身可含空格，无法直接按空格切。
 * 用磁盘真实图片清单做贪心匹配：从当前 token 起，到下一个带图片扩展名的 token 为止，
 * 下划线归一后命中本地文件即认作一张图，逐张还原为独立成段的 md 图片
 * （随后由 hb_gallery_group 收拢为网格并接入灯箱）。缺图/杂词不造死链。
 * 仅在「画廊/Gallery」标题之下生效，两种语言都跑，md 源零改动。
 */
function hb_expand_flat_gallery(string $md): string {
    static $norm = null;
    if ($norm === null) {
        $norm = [];
        foreach (scandir(CONTENT.'/images') ?: [] as $f) {
            if ($f[0] === '.') continue;
            $k = mb_strtolower(preg_replace('/\s+/u', '_', $f));
            $norm[$k] = $f;
            // 兼容 md 里多个转义下划线（___）被规范化成单下划线落盘的情况
            $norm[preg_replace('/_+/', '_', $k)] = $f;
        }
    }
    $extRx = '/\.(?:jpe?g|png|gif)$/i';
    $lines  = preg_split('/\R/u', $md);
    $out    = [];
    $inGal  = false; $galLevel = 0;
    foreach ($lines as $line) {
        if (preg_match('/^(#{1,4})\s*(?:gallery|画廊)(?:\s|$)/im', trim($line), $hm)) {
            $inGal = true; $galLevel = strlen($hm[1]);
            $out[] = $line; continue;
        }
        if ($inGal && preg_match('/^(#{1,4})\s*\S/', $line, $hm2)) {
            if (strlen($hm2[1]) <= $galLevel) { $inGal = false; $galLevel = 0; }
        }
        if ($inGal && strpos($line, '![') === false && stripos($line, '<img') === false
            && preg_match_all($extRx, $line) >= 1) {
            // 还原 markdown 转义下划线后按空白切 token
            $toks = preg_split('/\s+/u', trim(str_replace('\\_', '_', $line)));
            $toks = array_values(array_filter($toks, fn($t) => $t !== ''));
            $n = count($toks); $imgs = []; $i = 0;
            while ($i < $n) {
                $j = $i;
                while ($j < $n && !preg_match($extRx, $toks[$j])) $j++;
                if ($j >= $n) break;                       // 后续无扩展名 token，结束
                $cand = implode('_', array_slice($toks, $i, $j - $i + 1));
                $key  = mb_strtolower(preg_replace('/\s+/u', '_', $cand));
                $hit  = $norm[$key] ?? ($norm[preg_replace('/_+/', '_', $key)] ?? null);
                if ($hit !== null) { $imgs[] = $hit; $i = $j + 1; }
                else { $i++; }                            // 起点不是文件名，滑动重试
            }
            // 仅当从首个 token 就能连续解析出图片时才转换，避免误伤夹杂文件名的正文
            if ($imgs) {
                $block = [];
                foreach ($imgs as $disk) $block[] = '![]('.IMG.'/'.rawurlencode($disk).')';
                $out[] = implode("\n\n", $block);
                continue;
            }
        }
        $out[] = $line;
    }
    return implode("\n", $out);
}

function hb_clean_gallery_legacy(string $md): string {
    static $imgExact = null, $imgNorm = null;
    if ($imgExact === null) {
        $imgExact = []; $imgNorm = [];
        foreach (scandir(CONTENT.'/images') ?: [] as $f) {
            if ($f[0] === '.') continue;
            $imgExact[$f] = $f;
            $imgNorm[mb_strtolower(preg_replace('/\s+/u','_',$f))] = $f;
        }
    }
    // 导航表格残骸：剥 ! colspan=N | 前缀，保留单元格链接/文字
    $md = preg_replace('/![ \t]*colspan[ \t]*=[ \t]*\d+[ \t]*\|[ \t]*/i', '', $md);
    // 整行跨语言链接 de:xxx / sv:xxx / pt-br:xxx（语言码白名单，标题可含空格；不含 http，避免误伤）
    $md = preg_replace('/^[ \t]*(?:de|sv|fr|es|it|ja|pl|ru|pt-br|en|zh|nl|fi|da|no|cs|hu|tr|ko|ar|he)[ \t]*:[ \t]*\S[^\n]*$/mi', '', $md);
    // 相邻完全相同的重复加粗 **X** **X** … → 一个
    $md = preg_replace('/(\*\*([^*\n]+)\*\*)(?:[ \t]*\1)+/u', '$1', $md);

    // 形态：① <File:名|注>/<文件：..> 尖括号；② 裸 File:名.jpg|注 / 文件：名.jpg|注（允许文件名含空格）
    $tokRx = '/<\s*(?:File|文件)\s*[:：]\s*([^<>]*?)\s*>|(?:(?<![A-Za-z])File|文件)\s*[:：]\s*([^<>|]+\.(?:jpe?g|png|gif))/iu';
    // 正文已有的图（含刚被 hb_relocate_lead_cover 移到文末的封面）→ token 同图去重
    $existing = [];
    if (preg_match_all('/!\[[^\]]*\]\(([^)]+)\)/u', $md, $em)) {
        foreach ($em[1] as $u) {
            $b = preg_replace('/\s+/u', '_', basename(rawurldecode(explode('?', $u)[0])));
            $existing[mb_strtolower($b)] = true;
        }
    }
    $resolve = function(string $name) use ($imgExact, $imgNorm): ?string {
        $name = trim((string)preg_replace('/\s+/u', '_', $name));
        if ($name === '') return null;
        if (isset($imgExact[$name])) return $imgExact[$name];
        $k = mb_strtolower($name);
        return $imgNorm[$k] ?? null;
    };
    $capFromParts = function(array $parts): string {
        $cap = '';
        $keep = [];
        foreach ($parts as $p) {
            $p = trim($p);
            // 跳过空段与 MediaWiki 参数（600px / thumb / right / link= 等）
            if ($p === '' || preg_match('/^\d+px$/i', $p)
                || in_array(mb_strtolower($p), ['thumb','thumbnail','frame','framed','frameless','right','left','center','none'], true)
                || preg_match('/^(link|alt)\s*=/i', $p)) continue;
            $keep[] = $p;   // 其余拼回（如 “Issue” + “#2” → “Issue #2”）
        }
        $cap = implode(' ', $keep);
        $cap = (string)preg_replace('/\s+/u', ' ', $cap);
        $plain = trim(preg_replace('~[*_`\[\]()/\\\\]~u', '', $cap), ' #');
        return $plain === '' ? '' : $cap;
    };
    $emitted = [];
    $out = [];   // 段落级累积：图片/图注必须各自独立成段，hb_gallery_group 才能收拢
    foreach (preg_split('/\R/u', $md) as $line) {
        if (!preg_match_all($tokRx, $line, $mm, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            $out[] = $line; continue;
        }
        // token 之前的正文（如 “Covers by [Mike Mignola]”）保留为独立段
        $pre = substr($line, 0, $mm[0][0][1]);
        if (trim($pre) !== '') $out[] = $pre;
        $lineLen = strlen($line);
        foreach ($mm as $i => $mt) {
            $end   = $mt[0][1] + strlen($mt[0][0]);
            $nxt   = isset($mm[$i+1]) ? $mm[$i+1][0][1] : $lineLen;
            $tail  = substr($line, $end, $nxt - $end);   // 到下一 token 为止：畸形文件名延续 或 |图注
            $g1    = $mt[1][1] >= 0;                     // true=<File:..> 尖括号形态
            $inner = rawurldecode($g1 ? $mt[1][0] : $mt[2][0]);
            // 畸形 <File:Abe> Sapien 12.jpg ：括号内无管道、非完整文件名 → 文件名跨 '>' 延续
            $mangled = $g1 && strpos($inner, '|') === false
                       && !preg_match('/\.(jpe?g|png|gif)$/i', trim($inner));
            if ($mangled) $inner .= $tail;
            $name = $inner; $capParts = [];
            if (($p = strpos($inner, '|')) !== false) {
                $name = substr($inner, 0, $p);
                $capParts[] = substr($inner, $p + 1);
            }
            if (!$mangled) {
                $tt = trim($tail);
                if ($tt !== '') {
                    if ($tt[0] === '|') $capParts[] = ltrim($tt, '|');          // <File:a.jpg>|图注
                    elseif ($capParts)  $capParts[] = $tt;                       // 图注续文（如 \#1）
                }
            }
            $cap  = $capFromParts($capParts);
            $real = $resolve($name);
            if (!$real) continue;                       // 磁盘缺图：整条丢弃，不造死链
            $key = mb_strtolower($real);
            if (isset($existing[$key]) || isset($emitted[$key])) continue;
            $emitted[$key] = true;
            $out[] = '';
            $out[] = '![](' . img($real) . ')';          // 独立成段图片
            if ($cap !== '') { $out[] = ''; $out[] = '|' . $cap; }  // 独立成段图注
            $out[] = '';
        }
    }
    $md = implode("\n", $out);
    // Gallery 段：若首张图（通常是归位封面）被介绍文字与后面的图片组隔开，把首张图下移到介绍文字之后、图片组之前
    $md = preg_replace_callback(
        '/(^#{2,4}[ \t]*(?:gallery|画廊)[^\n]*$)\R+(!\[[^\]]*\]\([^)]+\))\R+(.+?)\R+(?=!\[[^\]]*\]\()/ims',
        function($m) {
            // 期号图注（如 \\#1 / #1）不是介绍文字，不能把封面图挪到它后面，否则图注错位
            if (preg_match('/^\\\\?#\s*\d+\s*$/u', trim($m[3]))) return $m[0];
            return $m[1] . "\n\n" . trim($m[3]) . "\n\n" . $m[2] . "\n\n";
        },
        $md
    );
    return $md;
}

/**
 * 塌表重建（纯渲染层，md 源不动）：
 * 早期英文/机译导入管线在把 MediaWiki 表格转成站内 markdown 时，剥掉了 <table>/<tr>/<td>/<th>/<thead>，
 * 只留下一个裸 <tbody>：表头列名以纯文本逐行列在 <tbody> 上方，数据区用「空行」分记录、每个单元格是一个块
 * （<ul> 整块、封面图整行、标量逐行；rowspan 表现为续行记录里整块缺失）。这些裸标签随后被 Parsedown
 * 安全模式转义成可见的 &lt;li&gt; 乱码，表格样式也丢失。
 *
 * 这里按【表头列名 + 块类型锚点（封面图/列表/日期/序号）】把每个块无损归位到对应列，重建为合法 <table>，
 * 随后复用既有的 sanitize_html_table / 列规范化 / 链接重写管线。任何无法 100% 对齐的表都原样保留（宁可不修也不串行/丢内容）。
 */
function hb_rebuild_collapsed_tables(string $raw): string {
    if (stripos($raw,'<tbody')===false) return $raw;
    $hdrMap = [
        '#'=>'num', 'title'=>'title', '书名'=>'title',
        'date published'=>'date', '出版日期'=>'date',
        'material collected'=>'material', 'material collected:'=>'material', '收录内容'=>'material',
        'additional material'=>'additional', '附加内容'=>'additional',
        'cover'=>'cover', '封面'=>'cover',
        'library edition'=>'lib', '图书馆版（library edition）'=>'lib', '图书馆版'=>'lib',
        'omnibus'=>'omni', '大全集版（omnibus）'=>'omni', '大全集版'=>'omni',
        'author'=>'author', '编剧'=>'author',
        'artist'=>'artist', '绘者'=>'artist',
        'collected in'=>'collected', '收录于'=>'collected',
    ];
    $dateRe = '/^\s*(?:January|February|March|April|May|June|July|August|September|October|November|December|\d{4}|[—–-]\s*\d{4}|\?+|TBD|TBA|N\s*\/?\s*A|Fall|Spring|Summer|Winter|Late|Early)/iu';

    $lines = preg_split('/\r?\n/', $raw);
    $tb = [];
    foreach ($lines as $i=>$l) if (preg_match('/^\s*<tbody>\s*$/i',$l)) $tb[]=$i;
    if (!$tb) return $raw;

    // 解析单个 tbody 的数据区为「记录 × 块」
    $splitBlocks = function(array $body): array {
        $recs=[]; $cur=[];
        $flush=function() use (&$cur,&$recs){ if($cur){$recs[]=$cur;$cur=[];} };
        $n=count($body); $i=0;
        while ($i<$n) {
            $s=trim($body[$i]);
            if ($s==='') { $flush(); $i++; continue; }
            // 孤立的迁移杂质：align="left|
            if (preg_match('/^\s*align\s*=\s*"?left\|?\s*$/i',$s)) { $i++; continue; }
            if (preg_match('#^<(ul|ol)\b#i',$s,$mm) && stripos($s,'</'.$mm[1])===false) {
                $b=[$s]; $i++;
                while ($i<$n) { $b[]=trim($body[$i]); if (stripos($body[$i],'</'.$mm[1])!==false){$i++;break;} $i++; }
                $cur[]=implode("\n",$b); continue;
            }
            $openMore=function(string $x):bool {
                return (substr_count($x,'<em')>substr_count($x,'</em>'))
                    || (substr_count($x,'<span')>substr_count($x,'</span>'))
                    || (substr_count($x,'<a ')>substr_count($x,'</a>'))
                    || (bool)preg_match('#<br\s*/?>$#i',$x);
            };
            if ($openMore($s)) {
                $b=[$s]; $i++;
                while ($i<$n) {
                    $nx=trim($body[$i]); $b[]=$nx; $i++;
                    if (preg_match('#<br\s*/?>$#i',$nx)) continue;
                    $j=implode("\n",$b);
                    if (substr_count($j,'<em')>substr_count($j,'</em>')) continue;
                    if (substr_count($j,'<span')>substr_count($j,'</span>')) continue;
                    if (substr_count($j,'<a ')>substr_count($j,'</a>')) continue;
                    break;
                }
                $cur[]=implode("\n",$b); continue;
            }
            $cur[]=$s; $i++;
        }
        $flush();
        return $recs;
    };

    // 块类型
    $blockKind = function(string $b) use ($dateRe): string {
        if (preg_match('#^<(ul|ol)\b#i',$b)) return 'list';
        if (strpos($b,'![](')!==false || preg_match('#<img\b#i',$b)) return 'img';
        $t=trim(preg_replace('#<[^>]+>#','',$b));
        if (preg_match('/^\s*(?:\d+|N\s*\/?\s*A|#?\d+[a-z]?)\s*$/ius',$t)) return 'num';
        if (preg_match($dateRe,$t)) return 'date';
        return 'scalar';
    };

    // 单元格内 markdown（图片/粗斜体）转 HTML，使其脱离 Parsedown 也能渲染
    $cellInline = function(string $s): string {
        $s = preg_replace_callback('#!\[([^\]]*)\]\(([^)\s]+)(?:\s+"[^"]*")?\)#', function($im){
            $src=$im[2];
            if (!preg_match('#^https?://#i',$src) && !str_starts_with($src,'/')) {
                $src='/content/images/'.basename(rawurldecode(explode('?',$src)[0]));
            }
            return '<img src="'.e($src).'" alt="'.e($im[1]).'" />';
        }, $s);
        // 裸 /entry/ markdown 链接 [text](/entry/Slug)（外层 <a 已是 HTML 的不动）
        $s = preg_replace_callback('#(?<!["\'>])\[([^\]]+)\]\((/entry/[^)]+)\)#u', function($m){
            return '<a href="'.e($m[2]).'">'.$m[1].'</a>';
        }, $s);
        $s = preg_replace('#\*\*([^*]+)\*\*#u','<strong>$1</strong>',$s);
        return $s;
    };

    // 从后往前替换，避免行号位移
    for ($ti=count($tb)-1; $ti>=0; $ti--) {
        $idx=$tb[$ti];
        // 向上读表头列名
        $j=$idx-1;
        while ($j>=0 && trim($lines[$j])==='') $j--;
        $hdr=[];
        while ($j>=0 && trim($lines[$j])!=='') {
            $s=trim($lines[$j]);
            if (preg_match('#<(tbody|/tbody|ul|ol|li)\b#i',$s) || preg_match('/^#{2,4}\s/',$s)) break;
            $hdr[]=$s; $j--;
        }
        $hdr=array_reverse($hdr);
        $cols=[]; $bad=false;
        foreach ($hdr as $h) {
            $key=$hdrMap[mb_strtolower(trim($h),'UTF-8')] ?? null;
            if ($key===null) { $bad=true; break; }
            $cols[]=$key;
        }
        if ($bad || !$cols) continue;   // 识别不出列：不动
        $colSet=array_flip($cols);
        // 数据区范围
        $end=count($lines);
        for ($x=$idx+1;$x<count($lines);$x++){
            $s=trim($lines[$x]);
            if ($s==='</tbody>' || preg_match('/^\s*<tbody>\s*$/i',$lines[$x]) || preg_match('/^#{2,4}\s/',$s)){ $end=$x; break; }
        }
        $body=[];
        for ($x=$idx+1;$x<$end;$x++) if (trim($lines[$x])!=='</tbody>') $body[]=$lines[$x];
        $recs=$splitBlocks($body);
        if (!$recs) continue;

        // 逐记录归位
        $outRows=[]; $ok=true;
        $coverCi=array_search('cover',$cols,true);
        $listCols=array_values(array_filter($cols,fn($c)=>in_array($c,['material','additional'],true)));
        foreach ($recs as $rec) {
            $kinds=array_map($blockKind,$rec);
            $assign=[];   // colKey => blockIndex
            $imgAt=null; foreach($kinds as $k=>$kd) if($kd==='img'){ $imgAt=$k; break; }
            $listAt=[]; foreach($kinds as $k=>$kd) if($kd==='list') $listAt[]=$k;
            if ($imgAt!==null) {
                if (!isset($colSet['cover'])) { $ok=false; break; }
                $assign['cover']=$imgAt;
            }
            // 列表列：以封面为界，封面之前→按序的 material/additional；封面之后→其后的列表列
            if (count($listAt)>count($listCols)) { $ok=false; break; }
            $before=array_values(array_filter($listAt,fn($k)=>$imgAt===null||$k<$imgAt));
            $after =array_values(array_filter($listAt,fn($k)=>$imgAt!==null&&$k>$imgAt));
            $bc=array_values(array_filter($listCols,fn($c)=>$imgAt===null||$coverCi===false||array_search($c,$cols,true)<$coverCi));
            $ac=array_values(array_filter($listCols,fn($c)=>$imgAt!==null&&$coverCi!==false&&array_search($c,$cols,true)>$coverCi));
            if (count($before)>count($bc) || count($after)>count($ac)) { $ok=false; break; }
            foreach($before as $n=>$k) $assign[$bc[$n]]=$k;
            foreach($after as $n=>$k)  $assign[$ac[$n]]=$k;
            // 日期
            if (isset($colSet['date'])) foreach($kinds as $k=>$kd)
                if($kd==='date'&&!in_array($k,$listAt,true)&&$k!==$imgAt&&!isset($assign['date'])){ $assign['date']=$k; break; }
            // 序号列「#」永远是首列，故只可能是每条记录的第 0 个块（值可能是数字或 N/A）；
            // 不能见到 num 类就抢，否则附加内容列里的占位文本 "N/A" 会被误当成序号
            if (isset($colSet['num']) && count($rec)>0 && $kinds[0]==='num') $assign['num']=0;
            $used=array_values($assign);
            // 其余块（书名/作者/封面文字链接，以及无 <ul> 时 material/additional 列的 N/A 占位）
            // 严格按源中顺序 → 按列顺序补位，含 material/additional 与封面之后的尾巴列
            $remain=[]; for($k=0;$k<count($rec);$k++) if(!in_array($k,$used,true)) $remain[]=$k;
            $ri=0;
            foreach($cols as $c){
                if (isset($assign[$c]) || $c==='cover') continue;
                if ($ri>=count($remain)) continue;
                // 列表列只接受非 list 块（真正的 <ul> 已在上面按锚点分配）
                if (in_array($c,['material','additional'],true) && $kinds[$remain[$ri]]==='list') continue;
                $assign[$c]=$remain[$ri]; $ri++;
            }
            if ($ri<count($remain)) { $ok=false; break; }   // 还有块无处安放：放弃整表
            $outRows[]=['assign'=>$assign,'rec'=>$rec];
        }
        if (!$ok) continue;

        // 生成 HTML（表头 + 行；缺失的合并格留空 <td>，保证不错位/不丢内容）
        $h='<table>';
        // 表头沿用源 md 原文（中文层自然显示中文），与 $cols 角色逐列对齐；仅在缺失时回退英文标准名
        $thMap=['num'=>'#','title'=>'Title','date'=>'Date Published','material'=>'Material Collected',
                'additional'=>'Additional Material','cover'=>'Cover','lib'=>'Library Edition',
                'omni'=>'Omnibus','author'=>'Author','artist'=>'Artist','collected'=>'Collected in'];
        $thRaw=[];
        foreach($hdr as $hl){
            $x=trim(preg_replace('#<[^>]+>#u','',(string)$hl));
            $x=str_replace("''",'',$x);
            $thRaw[]=$x;
        }
        $h.='<thead><tr>';
        foreach($cols as $ci=>$c){
            $lab=($thRaw[$ci]??'')!=='' ? $thRaw[$ci] : ($thMap[$c]??$c);
            $h.='<th><p>'.e($lab).'</p></th>';
        }
        $h.='</tr></thead><tbody>';
        foreach($outRows as $row){
            $assign=$row['assign']; $rec=$row['rec'];
            $h.='<tr>';
            foreach($cols as $c){
                if (isset($assign[$c])) $h.='<td>'.$cellInline($rec[$assign[$c]]).'</td>';
                else $h.='<td></td>';
            }
            $h.='</tr>';
        }
        $h.='</tbody></table>';

        // 用重建表替换 [表头块 .. <tbody>..</tbody>]
        $start=$j+1;                 // 表头块上边界（含其前空行处理在拼接处）
        // 找到 </tbody> 结束行
        // $end 指向独立的 </tbody> 关闭行；区间必须含它，否则会留下可见的孤立 </tbody>
        $closeLine=$end+1;
        for($x=$idx;$x<=$end;$x++) if(trim($lines[$x])==='</tbody>'){ $closeLine=$x+1; break; }
        array_splice($lines,$start,$closeLine-$start,['','@@HBREBUILT@@']);
        // 暂存重建表（用占位，最后统一回填，避免在数组里放大段 HTML）
        $GLOBALS['__hbRebuilt'][]=$h;
    }
    if (empty($GLOBALS['__hbRebuilt'])) return $raw;
    $out=implode("\n",$lines);
    $gi=0;
    $out=preg_replace_callback('/@@HBREBUILT@@/', function() use (&$gi){
        return "\n\n".$GLOBALS['__hbRebuilt'][$gi++]."\n\n";
    },$out);
    return $out;
}

/**
 * 条目正文通用渲染：在标准 Markdown 之外，兼容迁移遗留的原生 HTML 表格（多个页面含 1~19 张）。
 * Parsedown 安全模式会把这些标签转义成可见乱码，因此先抽出表格→白名单消毒/链接重写→占位符嵌回。
 */
function md_html_entries(Ctx $ctx, string $raw): array {
    $tables=[];
    // 先把早期导入剥坏的裸 <tbody> 塌表重建为合法 <table>（无损，md 源不动）
    $GLOBALS['__hbRebuilt']=[];
    // v2 通用重建器先跑（精装/馆藏/小说/WT/地狱篇/深冬/获奖 + 部分平装）；
    // 其未处理的裸 tbody（平装其余 19 张）再交给已验证的 v1。
    if (function_exists('hb_rebuild_collapsed_tables_v2')) {
        $raw=hb_rebuild_collapsed_tables_v2($raw);
    }
    $raw=hb_rebuild_collapsed_tables($raw);
    if (stripos($raw,'<table')!==false) {
        $raw=preg_replace_callback('#<table\b.*?</table>#is', function($m) use ($ctx,&$tables) {
            // v1 兜底表的单元格里仍可能残留 markdown 链接/图片（v2 已转的幂等不受影响）
            $tbl=$m[0];
            if (function_exists('hbt2_inline')) $tbl=hbt2_inline($tbl);
            $tables[]=sanitize_html_table($ctx,$tbl);
            return "\n\n@@HBHTMLTBL".(count($tables)-1)."@@\n\n";
        }, $raw);
    }
    // MediaWiki / pandoc 脚注与引用残留（这些标签原本也会被转义成乱码）
    $raw=preg_replace('#<aside\b[^>]*footnotes.*?</aside>#is','',$raw);
    $raw=preg_replace('#</?references\b[^>]*>#i','',$raw);
    [$html,$toc]=md_html($raw);
    // Parsedown 安全模式会把正文里的脚注角标 <sup>1</sup> 转义成可见文字，这里还原为真标签
    $html=preg_replace_callback('/&lt;(\/?)(sup|sub)&gt;/i',fn($m)=>'<'.$m[1].$m[2].'>',$html);
    if ($tables) {
        $inject=fn(array $m)=>'<div class="hb-utable-wrap">'.$tables[(int)$m[1]].'</div>';
        $html=preg_replace_callback('#<p>\s*@@HBHTMLTBL(\d+)@@\s*</p>#s',$inject,$html);
        $html=preg_replace_callback('#@@HBHTMLTBL(\d+)@@#',$inject,$html);  // 未被 <p> 包裹的兜底
    }
    // MediaWiki 迁移遗留的全站引用：正文 [n] 角标 ↔ 页底 [n] 来源定义，双向锚定（md 源不动）
    $html=hb_linkify_references($html);
    return [$html,$toc];
}

/**
 * 锚定迁移遗留的全站引用（<references/> 体系）：
 *   正文行内 [n]  → <sup><a href="#cite-note-n">[n]</a></sup>（点击跳到来源定义）
 *   页底 <p>[n] 来源</p> → 带 id 的注释条 + 「↑」回到引用处
 * 关键保险：只锚定“页面里确实存在对应 [n] 定义”的编号，因此 [1952] 等年份/普通方括号文本绝不误伤；
 *           仅把“以 [n] 起头的段落”识别为定义，正文句中的 14.[1] 不会被当成定义。
 */
function hb_linkify_references(string $html): string {
    if (strpos($html,'[')===false) return $html;
    $seen=[];
    // 1) 重写定义段落：<p>[n] 内容</p>（同号多现只取首现）
    $html=preg_replace_callback('#<p>\s*\[(\d{1,2})\]\s+(.*?)</p>#s', function($m) use (&$seen){
        $n=(int)$m[1];
        if ($n<1 || isset($seen[$n])) return $m[0];
        $seen[$n]=true;
        return '<p class="hb-cite-note" id="cite-note-'.$n.'">'
             .'<span class="hb-cite-num">'.$n.'</span>'
             .'<span class="hb-cite-text">'.$m[2].'</span> '
             .'<a class="hb-cite-back" href="#cite-ref-'.$n.'" title="'.e('回到引用处').'" aria-label="'.e('回到引用处').'">↑</a></p>';
    },$html);
    if (!$seen) return $html;
    // 2) 行内 [n] 角标转上标链接（同一编号多处引用时 id 加后缀，回链默认到首现）
    $cnt=[];
    $html=preg_replace_callback('#\[(\d{1,2})\](?!\()#', function($m) use ($seen,&$cnt){
        $n=(int)$m[1];
        if (!isset($seen[$n])) return $m[0];
        $k=($cnt[$n]??0)+1; $cnt[$n]=$k;
        $id=$k===1 ? 'cite-ref-'.$n : 'cite-ref-'.$n.'-'.$k;
        return '<sup class="hb-ref"><a id="'.$id.'" href="#cite-note-'.$n.'">['.$n.']</a></sup>';
    },$html);
    return $html;
}

function render_entry(Ctx $ctx, string $title): void {
    $hasEnFile = $ctx->has_en($title);
    $hasMt     = $ctx->has_mt($title);
    $variant   = 'std';
    if (L()==='en') {
        $file = $hasEnFile ? PAGES_EN . '/' . $title . '.md' : PAGES . '/' . $title . '.md';
        $variant = 'en';
    } else {
        $z = $ctx->zh_body_file($title);      // 默认机译(mt)，无机译稿回退标准页(std)
        $file = $z['path']; $variant = $z['variant'];
    }
    $raw = (string)file_get_contents($file);
    // 出版时间线专用：保留彩色与层级结构
    $isTimeline = ($title === '《地狱男爵》漫画出版时间线');
    // 两种语言通用：展开被压平成一行文件名的 <gallery>（迁移遗留，仅个别页）
    if (!$isTimeline) {
        $raw = hb_expand_flat_gallery($raw);
    }
    // 中文正文：把与右侧信息框封面重复的开头图片移回文末 Gallery（EN 原文不动）
    if (!$isTimeline && L()==='zh') {
        $coverImg = $ctx->meta($title)['image'] ?? null;
        $raw = hb_relocate_lead_cover($raw, $coverImg ? (string)$coverImg : null);
        // 还原迁移残留 <File:> / 文件: 图片标记（md 源零改动，EN 不碰）
        $raw = hb_clean_gallery_legacy($raw);
    }
    if ($isTimeline) {
        [$html, $toc] = render_timeline_html($raw);
    } else {
        // 通用：标准 Markdown + 迁移遗留原生 HTML 表格的消毒渲染
        [$html, $toc] = md_html_entries($ctx, $raw);
    }
    $html = rewrite_entry_links($ctx, $html);  // 正文链接统一英文 permalink（两种语言都改）
    $ib = $ctx->ib_for($title);
    $bodyLang = detect_lang($raw);
    $disp = $ctx->disp_title($title);
    // 英文模式去掉展示标题里的消歧义括号，如 Hellboy (character) -> Hellboy；无英文页时保留本地名
    $dispClean = $disp;
    if (L()==='en' && $hasEnFile) $dispClean = preg_replace('/\s*\([^)]*\)\s*$/', '', $disp);
    $typeLabel = $ib['type_zh'] ?: '条目';
    $homeCrumb = (L()==='en') ? 'Home' : '首页';

    // 诚实提示：只依据“实际显示正文”的语言，不看标题；出版时间线是英中对照年表，不提示
    $notice = '';
    $verBar = '';
    if (!$isTimeline && L()==='zh') {
        // 三版本切换：机翻中文（默认）/ 标准中文版 / EN 原文
        $links = [];
        $links[] = $variant==='mt'
            ? '<span class="vb on vb-mt">机翻中文</span>'
            : ($hasMt ? '<a class="vb" href="'.e(ver_switch_url('mt')).'">机翻中文</a>' : '<span class="vb is-off" title="机译稿尚未生成">机翻中文</span>');
        $links[] = $variant==='std'
            ? '<span class="vb on vb-std">标准中文版</span>'
            : '<a class="vb" href="'.e(ver_switch_url('std')).'">标准中文版</a>';
        $links[] = '<a class="vb" href="'.e(ver_switch_url('en')).'">EN 原文</a>';
        $verBar = '<div class="ver-bar"><span class="vb-cap">版本</span>'.implode('<span class="vb-sep">·</span>',$links).'</div>';

        if ($variant==='mt') {
            $notice = '<div class="lang-notice is-mt"><span class="ln-flag">机译</span><div class="ln-body">本页为<b>机器初译</b>，默认展示、尚未经人工逐条校对。译名以术语库为准，链接/图片结构已保留。可切换到 <a href="'.e(ver_switch_url('std')).'">标准中文版</a> 对照，或在后台校对后固化。</div></div>';
        } elseif ($bodyLang==='en') {
            $notice = '<div class="lang-notice is-info"><span class="ln-flag">中</span><div class="ln-body">此条目尚无中文翻译（机译稿也未生成），当前显示英文原文。<a href="'.e(ver_switch_url('en')).'">EN</a></div></div>';
        } elseif ($bodyLang==='mixed') {
            $notice = '<div class="lang-notice is-info"><span class="ln-flag">中</span><div class="ln-body">标准中文版仅部分翻译（中英混杂，人工翻译已保留）。'.($hasMt?'可对照 <a href="'.e(ver_switch_url('mt')).'">机翻中文</a>。':'').'</div></div>';
        } elseif ($hasMt) {
            $notice = '<div class="lang-notice is-std"><span class="ln-flag">标准</span><div class="ln-body">当前为<b>标准中文版</b>（人工翻译 / 已逐条校对固化）。另有 <a href="'.e(ver_switch_url('mt')).'">机翻中文</a> 可对照。</div></div>';
        }
    } elseif (!$isTimeline && L()==='en' && !$hasEnFile && $bodyLang==='zh') {
        $notice = '<div class="lang-notice"><span class="ln-flag">EN</span>该词条暂无英文正文（将从 Fandom 同步）。当前显示中文翻译。<a href="'.e(lang_switch_url('zh')).'">切回中文</a></div>';
    } elseif (!$isTimeline && L()==='en' && !$hasEnFile && $bodyLang==='mixed') {
        $notice = '<div class="lang-notice is-info"><span class="ln-flag">EN</span>此条目为中英混杂（尚未完整翻译），当前显示原文。</div>';
    }
    ob_start(); ?>
    <div class="crumbs"><a href="/"><?= $homeCrumb ?></a> / <?= e($typeLabel) ?></div>
    <article class="article fluid">
      <h1 class="page-title"><?= e($dispClean) ?></h1>
      <?php if (!$isTimeline):
        $curLayer = ($variant==='en'?'en':($variant==='mt'?'mt':'std'));
        $layerZh = ['std'=>'标准中文','mt'=>'机翻中文','en'=>'EN 原文'][$curLayer];
        if (hb_admin()) {
            // 已登录：直达当前展示层的编辑器
            $editHref = '/admin/entry_edit.php?title=' . rawurlencode($title) . '&layer=' . $curLayer;
        } else {
            // 未登录：跳登录页，登录后自动回到该条目编辑器
            $afterLogin = '/admin/entry_edit.php?title=' . rawurlencode($title) . '&layer=' . $curLayer;
            $editHref = '/admin/login.php?goto=' . rawurlencode($afterLogin);
        }
      ?>
      <div class="entry-edit-row">
        <?= $verBar ?>
        <a class="aeb-btn" href="<?= e($editHref) ?>" title="<?= hb_admin() ? ($layerZh.'层') : ('登录后编辑此条目') ?>">✎ <?= L()==='en'?'Edit':'编辑' ?><?= hb_admin() ? '（'.$layerZh.'）' : '' ?></a>
      </div>
      <?php endif; ?>
      <?= $notice ?>
      <div class="prose"><?= $html ?></div>
      <?= cats_html($ib['cats'] ?? []) ?>
    </article>
    <?php
    $main = ob_get_clean();
    $ibCard = infobox_card($ib);
    $ibCard = rewrite_entry_links($ctx, $ibCard);  // 信息框值里的 /entry/ 链接也统一英文 permalink
    $right = $ibCard . rail_toc($toc);
    // 移动端：属性卡片插到 H1 正下方（桌面端 .mobile-rail 隐藏，右栏照常显示，内容零重复）
    if ($ibCard !== '') {
        $mobIb = '<div class="mobile-rail mobile-rail-top">'.$ibCard.'</div>';
        $main = preg_replace('/(<h1 class="page-title">.*?<\/h1>)/s', '$1'.$mobIb, $main, 1);
    }
    $loc = $ctx->locate_entry($title);   // 侧栏追溯：进入具体条目时展开并高亮所属系列
    layout($ctx, $dispClean, $main, ['current'=>$title,'right'=>$right,
        'mobileBottom'=>rail_toc($toc),
        'browseGroup'=>$loc['section'] ?? null, 'browseSub'=>$loc['sub'] ?? null]);
}

/* ---------- 自定义页面（Markdown） ---------- */
/* 门户独立页注册表：slug => [section,title,file,file_en]（人物/漫画/画师） */
function portal_pages(): array {
    static $cache = null;
    if ($cache === null) {
        $f = CONTENT . '/portals.json';
        $cache = is_file($f) ? (json_decode((string)file_get_contents($f), true) ?: []) : [];
    }
    return $cache;
}

function render_portal_page(Ctx $ctx, string $slug, array $cfg): void {
    $enMode = L()==='en';
    $secName = $cfg['section'];
    $sec = $ctx->find_section($secName);
    if (!$sec) { render_404($ctx, $slug); return; }
    $rel = $enMode ? ($cfg['file_en'] ?? null) : ($cfg['file'] ?? null);
    $path = CUSTOM . '/portal/' . basename((string)$rel);
    if (!is_file($path)) { render_404($ctx, $slug); return; }
    $pageTitle = $enMode ? ($cfg['title_en'] ?? $cfg['title']) : $cfg['title'];

    [$html, $toc] = md_html((string)file_get_contents($path));
    $html = render_portal_widgets($ctx, $html, $secName, $sec, $enMode);

    ob_start(); ?>
    <div class="crumbs"><a href="/"><?= $enMode?'Home':'首页' ?></a> / <?= e($pageTitle) ?></div>
    <article class="article fluid portal-page">
      <h1 class="page-title"><?= e($pageTitle) ?></h1>
      <div class="prose"><?= $html ?></div>
    </article>
    <?php
    $main = ob_get_clean();
    layout($ctx, $pageTitle, $main, ['current'=>'portal:'.$slug,'right'=>rail_toc($toc),'wide'=>true,'mobileBottom'=>rail_toc($toc)]);
}

/* 把 md 里的组件标记 {{core}}/{{subcats}}/{{az}} 替换为自动内容（等价 MediaWiki 分类页自动部分） */
function render_portal_widgets(Ctx $ctx, string $html, string $secName, array $sec, bool $enMode): string {
    $langQs = $enMode ? '&amp;lang=en' : '';
    $browseBase = '/browse?g=' . rawurlencode($secName);

    // 门户组件固定标题中英对照（portal.json 只存中文）
    $pwTr = [
      '核心人物'=>'Core Characters',
      '核心画师 / 主笔大神'=>'Core Artists',
      '按系列 / 出处'=>'By series / origin',
      '按身份 / 种族'=>'By identity / species',
      '按性别 / 状态 / 国籍'=>'By gender / status / nationality',
      '按漫画系列'=>'By comic series',
      '按职能 / 类型'=>'By role / type',
    ];
    $pw = fn(string $zh) => $enMode ? ($pwTr[$zh] ?? $zh) : $zh;

    // 手写核心块（核心人物 / 核心画师）
    $coreHtml = '';
    $portal = $ctx->portal($secName);
    if ($portal) {
        $core = array_values(array_filter($portal['core'] ?? [], fn($t)=> $ctx->exists($t)));
        if ($core) {
            $coreTitle = $portal['core_title'] ?? ($enMode?'Core':'核心');
            $coreHtml .= '<section class="portal-core"><h2 class="portal-bar">'.e($pw($coreTitle)).'</h2><ul class="portal-core-grid">';
            foreach ($core as $t) {
                $m = $enMode ? $ctx->ib_for($t) : $ctx->meta($t);
                $nm = $ctx->disp_title($t);
                $coreHtml .= '<li><a class="pcore-item" href="'.ent($t).'"';
                if (!empty($m['image'])) $coreHtml .= '><img class="pcore-thumb" src="'.img($m['image']).'" alt="" loading="lazy">';
                else $coreHtml .= '><span class="pcore-thumb noimg">'.e(mb_substr($nm,0,1,'UTF-8')).'</span>';
                $coreHtml .= '<span class="pcore-name">'.e($nm).'</span></a></li>';
            }
            $coreHtml .= '</ul></section>';
        }
    }

    // 自动子分类：优先 portal.json 规则（角色/漫画故事），无规则时用 nav 职能分组（创作者）
    $subHtml = '';
    $groups = $portal['subcat_groups'] ?? [];
    if ($groups) {
        $fi = -1;
        foreach ($groups as $grp) {
            $chips = '';
            foreach (($grp['cats'] ?? []) as $c) {
                $fi++;
                if ((int)($c['count']??0) <= 0) continue;
                $chipLabel = $enMode ? ($c['label_en'] ?? $c['label']) : $c['label'];
                $chips .= '<a class="portal-chip" href="'.$browseBase.'&amp;cat='.$fi.$langQs.'">'.e($chipLabel).'<span class="pc-num">'.(int)$c['count'].'</span></a>';
            }
            if ($chips !== '') $subHtml .= '<h2 class="portal-subcat-g">'.e($pw($grp['group'])).'</h2><div class="portal-chips">'.$chips.'</div>';
        }
    } else {
        foreach (($sec['groups'] ?? []) as $grp) {
            $n = count($grp['items'] ?? []);
            if ($n <= 0) continue;
            $subHtml .= '<a class="portal-chip" href="'.$browseBase.'&amp;s='.rawurlencode($grp['sub']).$langQs.'">'.e(tr($grp['sub'])).'<span class="pc-num">'.$n.'</span></a> ';
        }
        if ($subHtml !== '') $subHtml = '<h2 class="portal-subcat-g">'.e($pw('按职能 / 类型')).'</h2><div class="portal-chips">'.$subHtml.'</div>';
    }
    if ($subHtml !== '') $subHtml = '<section class="portal-subcats">'.$subHtml.'</section>';

    // 紧凑 A–Z 成员索引（对齐旧版分类页文字列表，非卡片墙）
    $items = $ctx->nav_items($sec);
    $buckets = [];
    foreach ($items as $t) {
        $l = strtoupper(substr(preg_replace('/^[^A-Za-z]+/','', hb_slug_of($t)),0,1));
        if ($l==='') $l='#';
        $buckets[$l][] = $t;
    }
    ksort($buckets);
    $azHtml = '<section class="portal-azlist"><h2 class="portal-bar">'.($enMode?'All entries (A–Z)':'全部条目（A–Z）').'</h2><div class="azl-cols">';
    foreach ($buckets as $l=>$ts) {
        $azHtml .= '<div class="azl-row"><span class="azl-letter">'.e($l).'</span><ul>';
        foreach ($ts as $t) $azHtml .= '<li><a href="'.ent($t).'">'.e($ctx->disp_title($t)).'</a></li>';
        $azHtml .= '</ul></div>';
    }
    $azHtml .= '</div></section>';

    // 门户页头手写介绍区右浮插图（对齐 18100 旧版 Interior Artist 分类页）
    $introImg = '<figure class="portal-introimg"><img src="'.img('Hellboy_-_The_First_20_Years_2014.png').'" alt="Hellboy - The First 20 Years (2014)"></figure>';

    return preg_replace_callback('#\s*<p>\{\{(core|subcats|az|introimg)\}\}</p>\s*#', function($m) use ($coreHtml,$subHtml,$azHtml,$introImg) {
        return ['core'=>$coreHtml,'subcats'=>$subHtml,'az'=>$azHtml,'introimg'=>$introImg][$m[1]];
    }, $html);
}

function render_custom(Ctx $ctx, string $slug, array $cfg): void {
    $path = CUSTOM . '/' . basename($cfg['file'] ?? ($slug.'.md'));
    if (!is_file($path)) { render_404($ctx, $slug); return; }
    [$html, $toc] = md_html((string)file_get_contents($path));
    $pageTitle = $cfg['title'] ?? $slug;
    ob_start(); ?>
    <div class="crumbs"><a href="/"><?= L()==='en'?'Home':'首页' ?></a> / <?= e($pageTitle) ?></div>
    <article class="article fluid">
      <h1 class="page-title"><?= e($pageTitle) ?></h1>
      <div class="prose"><?= $html ?></div>
    </article>
    <?php
    $main = ob_get_clean();
    layout($ctx, $pageTitle, $main, ['current'=>'p:'.$slug,'right'=>rail_toc($toc),'mobileBottom'=>rail_toc($toc)]);
}

/* ---------- search / 404 ---------- */
function render_search(Ctx $ctx, string $q): void {
    $enMode = L()==='en';
    $ql = mb_strtolower($q); $hits = [];
    foreach ($ctx->titles as $t) {
        $disp = $ctx->disp_title($t);
        $score=0;
        if (mb_strtolower($disp)===$ql) $score+=100;
        elseif (mb_stripos($disp,$q)!==false) $score+=40;
        if (!$enMode && mb_stripos($t,$q)!==false) $score+=40;
        // 旧英文别名 / en_title / en_redirects 常用名都能命中中文正条
        foreach ($ctx->alt_en_names($t) as $enName) {
            if (mb_strtolower($enName)===$ql) { $score+=100; break; }
            if (mb_stripos($enName,$q)!==false) { $score+=35; }
        }
        // EN 模式查英文正文，中文模式查本地正文
        $f = $enMode ? (PAGES_EN.'/'.$t.'.md') : (PAGES.'/'.$t.'.md');
        $md = is_file($f) ? (string)file_get_contents($f) : '';
        if ($md!=='' && mb_stripos($md,$q)!==false) $score+=10;
        if ($score>0) $hits[]=['t'=>$t,'score'=>$score,'ib'=>$enMode?$ctx->ib_for($t):$ctx->meta($t),'md'=>$md];
    }
    usort($hits, fn($a,$b)=>$b['score']<=>$a['score']);
    ob_start(); ?>
    <div class="crumbs"><a href="/"><?= $enMode?'Home':'首页' ?></a> / <?= $enMode?'Search':'搜索' ?></div>
    <h1 class="page-title"><?= $enMode?'Search results':'搜索结果' ?></h1>
    <p class="page-sub"><?= $enMode ? ('Keyword “'.e($q).'” · '.count($hits).' match'.(count($hits)===1?'':'es')) : ('关键词 “'.e($q).'” · 命中 '.count($hits).' 条') ?></p>
    <?php if(!$hits): ?><div class="empty"><?= $enMode?'No matching entries.':'没有匹配的条目。' ?> <a href="/"><?= $enMode?'Back to home':'返回首页' ?></a></div><?php endif; ?>
    <div class="result-list">
      <?php foreach($hits as $h):
        $snip=''; $p=mb_stripos($h['md'],$q);
        if($p!==false)$snip=mb_substr($h['md'],max(0,$p-40),120);
        $snip = preg_replace('/\[([^\]]*)\]\([^)]*\)/','$1',$snip);   // [文字](链接)->文字
        $snip = preg_replace('/\(\/?entry\/[^)\s]*/','',$snip);          // 剥残留裸路径
        ?>
        <a class="result-item" href="<?= ent($h['t']) ?>">
          <span class="card-type"><?= e($h['ib']['type_zh'] ?: ($enMode?'Entry':'条目')) ?></span>
          <h3><?= e($ctx->disp_title($h['t'])) ?></h3>
          <?php if($snip):?><p><?= e(preg_replace('/[#*`]/','',$snip)) ?>…</p><?php endif;?>
        </a>
      <?php endforeach; ?>
    </div>
    <?php
    layout($ctx,($enMode?'Search: ':'搜索：').$q,ob_get_clean());
}

function render_404(Ctx $ctx, string $wanted): void {
    http_response_code(404);
    ob_start(); ?>
    <div class="empty" style="margin-top:70px">
      <h1>404</h1><p><?= L()==='en' ? ('The entry “'.e($wanted).'” is not yet included.') : ('条目 “'.e($wanted).'” 尚未收录。') ?></p>
      <a class="btn" href="/"><?= L()==='en'?'Back to home':'返回首页' ?></a>
    </div><?php
    layout($ctx,L()==='en'?'Not found':'未找到',ob_get_clean());
}
