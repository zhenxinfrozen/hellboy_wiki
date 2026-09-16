# 更新与备份机制说明

> 本页说明站点数据的更新方式、备份策略与恢复办法，供回溯和运维参考。
> 最近更新：2026-09-14

## 一、站点技术形态

- 纯 PHP + Markdown 文件，**无数据库、无框架、无 Node 依赖**；内容即文件，全部位于站点根 `/www/wwwroot/hellboy-viewer-18106/content/`。
- PHP-FPM 以 `www` 用户运行；内容目录归属 `www:www`。
- 英文快照（`pages_en/`）只读冻结；所有修改只发生在中文层、JSON 数据和渲染代码上。

## 二、关键文件与目录

| 路径 | 作用 | 更新频率 |
| --- | --- | --- |
| `content/pages_en/` | 英文 Fandom 快照（冻结） | 基本不动 |
| `content/pages/` | 标准中文版（手译/固化） | 校对后更新 |
| `content/pages_mt/` | AI 机译层（默认呈现） | 批量翻译时更新 |
| `content/translation.json` | 每个条目的状态 none/partial/machine/reviewed、机译层标记、中文显示名 `zh_title` | 每轮翻译回写 |
| `content/glossary.json` | 术语表（强制译名、别名、slug、pending/confirmed 审核状态） | 译名研究后更新 |
| `content/meta/` | 条目属性框元数据（编剧/画师/封面/分类等） | 补条目时更新 |
| `content/slugs.json` / `en_title.json` / `en_redirects.json` / `aliases.json` | 英文固定链接、标题映射、301 重定向、旧名合并 | 改名/合并时更新 |
| `content/nav.json` | 左侧菜单结构（漫画故事/角色/电影/创作者/世界观/其它） | 结构调整时更新 |
| `content/custom/` | 关于本站与本报告系列页面 | 低频 |
| `index.php` / `assets/app.css` / `assets/app.js` | 渲染层、样式、灯箱脚本 | 功能改动时更新 |
| `admin/` | 后台管理（登录、条目编辑、术语审核、重定向、体检） | 低频 |

## 三、更新方式（三种途径）

1. **后台手动编辑**：`/admin/`（entry_edit.php / glossary.php / redirects.php）。所有写操作统一走 `admin_save_with_backup()`：写前自动在 `admin/backups/` 留一份带时间戳的备份，原子写入（临时文件 + rename），UTF-8 不转义。
2. **批量翻译脚本 / AI 子代理**：批量写 `pages_mt/` 并回写 `translation.json`。批量作业前整目录打 tar 级备份到 `/tmp/<作业名>-bak-<时间戳>/`（见下）。
3. **渲染层改动**：改 `index.php` / CSS / JS 前先复制到 `/tmp/` 带时间戳备份；改完跑 `php -l`，再做全站三视图回归。

## 四、备份体系

### 1. 后台自动备份（日常）

- 目录：`admin/backups/`（nginx 已配置外网 403，禁止直接访问）。
- 命名：`<路径分隔符转义后的文件名>.<YYYYMMDD-HHMMSS>.bak`。
- 每个被后台修改的文件默认保留最近 **40** 份（`backup_keep=40`），超出自动清理最旧的。
- 覆盖范围：后台触发的 JSON 与条目写入（术语表、重定向、条目正文等）。
- 敏感配置 `admin/config.local.php`（密码哈希）不入库、不进备份目录，nginx 显式 deny。

### 2. 批量作业前整包备份（翻译/重构轮次）

每轮批量翻译或结构改造前，在 `/tmp/` 下建立带作业名与时间戳的备份目录，例如：

- `hoefix-bak-170336/`：人间炼狱系列全量 682 页备份；
- `abe-bak-211321/`：亚伯·塞皮恩系列批次；
- `dotfix-bak-212546/`：间隔号（·/•）全站规范化；
- `headings-bak-142330/`：章节标题中文化；
- `index18106.bak-*.php` / `app18106.css.bak-*`：渲染层与样式的单文件时点备份；
- `translation.json.bak-<作业名>-*`：状态文件每次批量回写前的单独备份。

`/tmp` 为机器本地临时盘，定位是**近期回滚保险**，不是长期归档；重要里程碑版本另以站点目录整体快照形式留存（18100 / 18104 基线的约定）。

### 3. 合并去重时的安全删除

历史重复条目合并时，原文件不直接删除，而是 `mv` 到 `/tmp/merged-away-<时间戳>/`，确认零损失并回归通过后再考虑清理。

## 五、回滚方法

1. **单条内容回滚**：从 `admin/backups/` 找到对应文件最近的 `.bak`，复制覆盖回 `content/` 原路径即可（注意保持 `www:www` 属主）。
2. **整轮回滚**：从 `/tmp/<作业名>-bak-*/` 恢复对应目录，再重新请求页面确认。
3. **渲染层回滚**：`/tmp/index18106.bak-*.php` 覆盖 `index.php`，`php -l` 校验后即生效（opcache 已确认关闭，无需重启 PHP-FPM）。
4. 任何回滚后都要重跑全站回归（见下）。

## 六、写操作的安全约束

- 英文快照层 `pages_en/` 任何情况下不修改。
- 标准中文版 `pages/` 已固化内容不被机译覆盖；机译只写 `pages_mt/`。
- 链接 href 不允许翻译或改写；改名 / 合并必须同步 `slugs.json`、`en_redirects.json`、`nav.json`、`translation.json`、`glossary.json` 并保留 301。
- 不执行无确认的批量删除 / 覆盖 / 权限修改；删除优先移入暂存目录而非 `rm`。
- 后台写 JSON 一律原子写（tmp + rename），避免写一半损坏。

## 七、回归检查清单

每次内容或代码更新后：

1. `php -l` 检查所有改动的 PHP 文件；
2. 全部条目 ×（机译 / 标准 / EN）三视图批量请求，要求全 200、零 Fatal；
3. 扫描渲染输出：无 `</tth></ttd>`、无 `@@HBHTMLTBL@@` 占位泄漏、无 MediaWiki `File:/文件:/colspan` 残留；
4. 新译稿逐篇过 `tools/mt_gate.py` 结构闸门；
5. 移动端 390px / 平板 / 桌面（含 4K）抽样截图检查属性卡片、画廊网格、灯箱；
6. CSS / JS 改动通过 `?v=filemtime` 版本号自动破缓存，无需用户强刷。

## 八、已知运维事项

- 本机宝塔多 PHP 版本并存，本站 vhost 固定走 php-cgi-8.1 的 socket；机器重启后若 502，先确认 PHP-FPM 8.1 是否已自启。
- 后台密码为弱口令且仅限内网 / Tailscale 使用；若端口映射到公网必须先更换强密码。
- 约 38 张 Fandom 源缺图、9 个本地缺失条目链接、合订本页裸 HTML 标签三项遗留问题，在《翻译进度与更新日志》和《翻译方法与译名依据》中持续登记。
