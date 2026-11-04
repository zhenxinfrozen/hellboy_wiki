# 图片体系优化与全站 WebP 化

**日期**：2026-11-05
**提交**：`86e0f53`（webp 化主体）、`bb231f2`（登录跳转修复）
**范围**：content/images/、index.php、admin/、tools/、.gitignore

---

## 1. 背景与问题

原站图片体系存在三个痛点：

1. **gallery 卡片墙直接加载原图**：一张漫画封面 jpg 动辄 1-5MB，30 张卡片墙一次加载 60-150MB，首屏白屏严重。
2. **无缩略图机制**：所有调用点都用同一个原图 URL，没有按场景分级（卡片墙 / 正文 / 灯箱）。
3. **格式混乱**：2064 张图片中 jpg 1631 张、png 280 张、gif 2 张、webp 仅 2 张，总计 613MB，git 仓库体积膨胀。

## 2. 图片分级派生方案

采用三档分层，由 `img($f, $scene)` 统一调度：

| 档位 | 目录 | 宽度 | 质量 | 用途 |
|---|---|---|---|---|
| thumb | `content/images/thumb/` | 400px | q80 | 卡片墙、browse 列表、首页 |
| gallery | `content/images/gallery/` | 800px | q82 | 正文画廊、信息框、portal |
| full | `content/images/` 根目录 | 原图宽 | q88 | 灯箱放大、SEO 原图 |

**回退机制**：派生图不存在时自动回退到原图 full，保证旧条目不会 404。

**灯箱增强**：正文图通过 `hb_upgrade_body_images()` 自动注入 `data-full` 属性，灯箱优先使用 data-full 原图，而非 gallery 档。

## 3. 全站 WebP 化决策

**决策**：原图全部转为 webp，git 库不再保留 jpg/png。

| 指标 | 转换前 | 转换后 |
|---|---|---|
| 原图格式 | jpg/png/gif 2064 张 | webp 2061 张 |
| 原图体积 | 599 MB | 366 MB（省 39%） |
| thumb/ | — | 60.8 MB |
| gallery/ | — | 148.2 MB |
| content/images 总计 | 613 MB | 575 MB |

**质量选择**：q88。灯箱放大看漫画封面基本无损，比原 jpg q85 还干净。

**本地备份**：原 jpg/png/gif 物理移到仓库外 `D:\VS CODE\hellboy_wiki-originals-backup\`（599MB），永久保留。

**`.gitignore`**：
```
/content/images/*.jpg
/content/images/*.jpeg
/content/images/*.png
/content/images/*.gif
```

只忽略根目录老格式，thumb/ 和 gallery/ 子目录的 webp 正常入库。

## 4. 工具脚本

三个增量幂等脚本，放在 `tools/`：

### `tools/build_thumbs.py`
增量生成 thumb/gallery 派生图。支持 `--apply` / `--dry-run` / `--only thumb|gallery` / `--force`。自动应用 EXIF Orientation（修复手机/相机照片缩略图旋转 90° 的问题）。

### `tools/originals_to_webp.py`
原图 jpg/png/gif → 同名 webp q88，原文件移到备份目录。增量幂等，已存在的 webp 自动跳过。

### `tools/rewrite_image_refs.py`
批量改引用扩展名。规则：
- md 文件：`images/xxx.jpg` → `images/xxx.webp`（路径形式，不误伤正文）
- meta JSON：`"image": "xxx.jpg"` → `"image": "xxx.webp"`
- 顶层 nav.json / portal.json / portals.json
- PHP 文件中的硬编码图片字面量（单独 grep 清单处理）

**总替换量**：5091 处（md 约 3000 个文件 + meta 1198 个 + PHP 19 处硬编码）。

## 5. 后台图片管理页

新增 `admin/images.php`，菜单挂在「系统与质量」section 下：

- **覆盖率统计**：2061 张原图中 thumb/gallery 派生各多少，缺失多少
- **超大图 Top 20**：按体积排序，方便手动优化
- **一键补全**：调用 `build_thumbs.py --apply` 的逻辑，在服务器端补齐缺失派生

## 6. 部署方案

**关键问题**：`.gitignore` 对已追踪的老文件无效。要让生产服务器真正删除老 jpg，必须靠 commit 里的 **delete 记录**。

**宝塔部署脚本**（`仓库` → `部署脚本`）：

```bash
set -e
cd "$(git rev-parse --show-toplevel)"

# 强制对齐远端 main（含老文件物理删除）
git -c 'url.git@github.com:.insteadOf=https://github.com/' fetch origin
git reset --hard origin/main

# 子模块同步
git submodule sync --recursive
git -c 'url.git@github.com:.insteadOf=https://github.com/' submodule update --init --recursive

# 核对输出
echo "老 jpg/png/gif 残留: $(find content/images -maxdepth 1 -type f \( -iname '*.jpg' -o -iname '*.jpeg' -o -iname '*.png' -o -iname '*.gif' \) | wc -l)"
echo "webp 原图: $(find content/images -maxdepth 1 -type f -iname '*.webp' | wc -l)"
```

**为什么用 `reset --hard` 而不是 `pull`**：
- 生产工作区可能有本地 modified 文件（权限/时间戳被 PHP 进程碰过），普通 pull 会报 `local changes would be overwritten`
- `reset --hard origin/main` 强制复刻远端状态，老 jpg 在 commit 里是 delete 记录，会被物理删除
- 不会删 untracked 文件（`admin/config.local.php`、`admin/backups/` 安全）

## 7. Bug 修复

### 7.1 缩略图旋转 90°
**现象**：部分图片（Bill、Harry McTell、Jim Sacks 等 10 张）的缩略图逆时针旋转 90°。
**根因**：原图来自手机/相机，EXIF Orientation=6，但 Pillow 默认不应用 EXIF 旋转。
**修复**：`ImageOps.exif_transpose()` 在生成缩略图前先应用 EXIF 方向。

### 7.2 正文图渲染 TypeError
**现象**：`hb_upgrade_body_images()` 报 `Return value must be of type string, null returned`。
**根因**：正则用 `#` 当分隔符，但字符类 `[^"?#]` 含 `#`，导致 preg 报错返回 null。
**修复**：改用 `~` 分隔符 + `is_string` 兜底。

### 7.3 登录后跳回前台
**现象**：登录成功后直接跳回前台页面，而不是后台首页。
**根因**：`admin_safe_goto()` 原来接受所有 `/` 开头的路径。用户从前台点"管理后台"链接进来时，URL 带了 `?goto=/entry/xxx`，登录成功就跳回那个前台页。
**修复**：goto 正则改为只接受 `/admin/` 开头的路径，其他一律 fallback 到 `index.php`。

## 8. 后续待办

- [ ] 生产服务器首次部署时手动跑 `git reset --hard origin/main`（清掉本地 modified）
- [ ] 生产服务器安装 Python 3 + Pillow（让 `admin/images.php` 一键补全能在服务器端直接跑）
- [ ] 考虑未来引入 AVIF 格式（比 webp 再省 20-30%，但兼容性 2026 年已基本就绪）

---

**最后更新**：2026-11-05
