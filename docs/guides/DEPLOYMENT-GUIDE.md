# 部署指南

生产环境部署与更新流程。

## 环境要求

- PHP 7.4+（推荐 8.x），无需数据库
- Web 服务器：Nginx / Apache（宝塔面板均可）
- Git（用于拉取代码与子模块）

## 首次部署

```bash
# 1. 克隆仓库（含子模块）
git clone --recursive https://github.com/zhenxinfrozen/hellboy_wiki.git

# 已克隆但未拉子模块时：
git submodule update --init --recursive
```

## 后台账户配置

后台配置文件 `admin/config.local.php` 为本地私有文件，**不入库**（见 `.gitignore`），每台环境需单独创建：

```bash
cp admin/config.example.php admin/config.local.php
```

然后修改其中的：

- `admin_user`：后台登录用户名
- `password_hash`：密码哈希（使用 `password_hash()` 生成）
- `session_name`、`login_ttl` 等会话参数（可按需保留默认）

## 日常更新（生产端）

```bash
# 1. 拉取主仓库最新代码
git pull origin main

# 2. 同步子模块到主仓库记录的提交
git submodule update --init --recursive
```

> **注意**：`git pull` 只会更新子模块指针，不会自动拉取子模块内容，第二步不可省略。

## 分支策略

- `main`：生产分支，只通过 PR 合并更新，**永不强制覆盖**
- `feat/*`：功能分支，开发完成后合并入 `main`
- `shared-admin` 子模块独立维护，当前使用 `2.0.2` 分支

## 更新后检查清单

- [ ] 前台首页可正常访问
- [ ] 后台可登录（`admin/config.local.php` 存在且配置正确）
- [ ] 后台「文档」页可打开（验证 shared-admin 子模块已就位）
- [ ] 若有静态资源改动，确认 `assets/` 文件 mtime 已更新（版本号自动破缓存）

---

**最后更新**：2026-11-04
