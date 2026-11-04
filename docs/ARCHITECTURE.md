# 项目架构

地狱男爵中文维基（hellboy.wiki）整体架构说明。

## 技术栈

- **纯 PHP + Markdown**：无数据库、无 Docker、无 Node.js
- 所有内容以文件形式存放在 `content/` 目录，直接纳入 Git 版本管理
- 前台为单一前端控制器 `index.php`，后台入口在 `admin/`

## 目录结构

```
hellboy_wiki/
├── index.php            # 前台前端控制器（路由 + 渲染）
├── content/             # 全部站点内容（核心资产）
│   ├── pages/           # 中文正条文稿（Markdown）
│   ├── pages_en/        # 英文正文（Fandom 同步）
│   ├── pages_mt/        # 机翻初稿
│   ├── meta/            # 条目元数据 / 信息框字段
│   ├── custom/          # 自定义页面（Markdown）
│   ├── images/          # 图片资源
│   ├── slugs.json       # 本地标题 => 英文固定链接名（permalink）
│   ├── aliases.json     # 英文别名 => 中文正条（合并重复词条）
│   ├── en_title.json    # 本地标题 => Fandom 英文标题
│   ├── en_redirects.json# Fandom 英文名 => 本地中文正条（修英文红链）
│   ├── glossary.json    # 译名表
│   ├── nav.json         # 导航配置
│   ├── portal.json      # 门户页配置（手写核心块 + 自动子分类规则）
│   └── translation.json # 界面翻译
├── admin/               # 管理后台
│   ├── bootstrap.php    # 引导：路径、配置、登录态
│   ├── ui.php           # 后台页面框架（SharedAdmin 入口）
│   ├── entries.php      # 条目管理
│   ├── entry_edit.php   # 条目编辑器
│   ├── glossary.php     # 译名表管理
│   ├── redirects.php    # 重定向管理
│   ├── audit.php        # 审计日志
│   ├── system-info.php  # 系统信息
│   ├── docs.php         # 文档 wiki（本模块）
│   └── config.local.php # 本地私有配置（不入库）
├── assets/              # 前台静态资源（css/js/img）
├── lib/                 # 第三方库（Parsedown 等）
├── shared-admin/        # 共享后台组件库（git submodule）
├── tools/               # 辅助脚本（Python 等）
└── docs/                # 项目文档（本文档中心）
```

## 核心设计

- **文件即数据库**：条目正文、元数据、配置全部为文件，可直接编辑、Git diff、备份
- **多稿并存**：`pages`（定稿中文）、`pages_mt`（机翻初稿）、`pages_en`（英文原文）三轨并行，便于对照翻译
- **红链修复体系**：`aliases.json` / `en_redirects.json` / `en_title.json` 三层映射，把 Fandom 英文内链对齐到本地中文正条
- **固定链接**：`slugs.json` 让中文标题拥有稳定的英文 URL，与界面语言无关

## shared-admin 子模块

- 后台通用组件（登录、文档查看器、系统信息等）抽取为独立仓库，以 git submodule 挂载
- 当前分支：`2.0.2`
- 类加载：`AdminCore.php` 内置 PSR-4 自动加载器，映射 `SharedAdmin\*` => `src/*.php`
- 更新子模块后，生产端需执行：`git submodule update --init --recursive`

## 缓存策略

- 静态资源版本号取文件 mtime（`ASSET_VER_CSS` / `ASSET_VER_JS`），文件改动后 URL 自动变化，强制浏览器刷新缓存

---

**最后更新**：2026-11-04
