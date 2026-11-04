# 内容编辑指南

条目内容的新增、编辑与译名规范。

## 内容组织

| 目录 / 文件 | 用途 |
| --- | --- |
| `content/pages/` | 中文正条定稿（Markdown） |
| `content/pages_mt/` | 机翻初稿（待人工校对） |
| `content/pages_en/` | 英文原文（Fandom 同步，对照用） |
| `content/meta/` | 条目元数据 / 信息框字段 |
| `content/custom/` | 自定义页面 |
| `content/images/` | 图片资源 |

## 编辑方式

### 后台编辑（推荐）

1. 登录后台 => 「条目管理」（`admin/entries.php`）
2. 选择条目进入编辑器（`admin/entry_edit.php`）
3. 前台登录后也可在条目页进行内联编辑

### 直接编辑文件

所有内容均为纯文本文件，可直接用编辑器修改后提交 Git。图片通过后台「上传图片」（`admin/upload_image.php`）放入 `content/images/`。

## 链接与映射文件

新增条目后，视情况维护以下 JSON：

- `slugs.json`：为新条目分配英文固定链接名（permalink）
- `aliases.json`：合并英文别名到中文正条，消除重复词条
- `en_redirects.json`：Fandom 英文名 => 本地中文正条，修复英文内链红链
- `en_title.json`：本地标题 => Fandom 英文标题对照
- `glossary.json`：译名表（亦可在后台「译名表」页维护）

## 译名规范

- 所有中文译名基于**「不高兴汉化」**版本
- 新译名先查 `glossary.json` 是否已有约定，避免一词多译
- 较复杂的更改（批量改名、体例调整）请先联系站长对齐译名与体例

## 信息框字段

- 元数据字段标签支持简 / 繁中文，前台会自动附加英文标签（映射表见 `index.php` 的 `IB_LABEL_EN`）
- 新增字段类型时优先复用已有标签

---

**最后更新**：2026-11-04
