# shared-admin 子模块说明

后台共享组件库的维护方式与注意事项。

## 概述

- `shared-admin/` 是一个 **git submodule**，指向独立仓库（多个项目共用同一套后台组件）
- 提供：登录鉴权、页面框架、系统信息、文档查看器（DocViewer）等通用后台能力
- 本项目当前使用子模块的 **`2.0.2`** 分支

## 类加载机制

项目不使用 Composer。`shared-admin/src/AdminCore.php` 顶部内置了轻量 PSR-4 自动加载器：

- 命名空间前缀 `SharedAdmin\` => 目录 `shared-admin/src/`
- 例如 `SharedAdmin\Services\DocViewer` => `src/Services/DocViewer.php`
- 入口 `admin/ui.php` 只需 `require_once AdminCore.php`，其余类按需自动加载

> 2026-11-04 之前没有自动加载器，曾导致 `Class "SharedAdmin\Services\DocViewer" not found`，
> 详见 `troubleshooting/CLASS-NOT-FOUND-FIX.md`。

## 修改子模块的标准流程

```bash
# 1. 进入子模块，切到开发分支
cd shared-admin
git checkout 2.0.2

# 2. 修改、提交、推送（子模块有自己的远端）
git add -A
git commit -m "..."
git push origin 2.0.2

# 3. 回到主仓库，提交子模块指针更新
cd ..
git add shared-admin
git commit -m "chore: update shared-admin submodule"
git push
```

## 常见坑

- **在子模块里直接 commit 会处于 detached HEAD**：先 `git checkout 2.0.2` 再提交
- **主仓库只记录子模块的 commit 指针**：子模块推了但主仓库没提交指针，别人拉到的仍是旧版
- **生产端更新**：`git pull` 之后必须再跑 `git submodule update --init --recursive`
- 子模块远端为本地路径时（如 `D:/VS CODE/shared-admin`），注意该裸仓库需提前存在

---

**最后更新**：2026-11-04
