# 故障记录：Class "SharedAdmin\Services\DocViewer" not found

## 现象

打开后台「文档」页（`admin/docs.php`）时报错：

```
Class "SharedAdmin\Services\DocViewer" not found
# shared-admin/src/views/pages/docs.php 第 9 行
```

## 原因

- 项目不使用 Composer，没有统一的自动加载器
- `admin/ui.php` 只 `require_once` 了 `AdminCore.php`
- `DocViewer.php` 文件存在、命名空间正确，但没有任何代码加载它

## 修复

在 `shared-admin/src/AdminCore.php` 顶部（`namespace SharedAdmin;` 之后）注册轻量 PSR-4 自动加载器：

```php
\spl_autoload_register(function (string $class): void {
    $prefix = __NAMESPACE__ . '\\';
    if (!str_starts_with($class, $prefix)) return;
    $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) require_once $file;
});
```

- 映射规则：`SharedAdmin\X\Y` => `shared-admin/src/X/Y.php`
- 修复提交：子模块 `e4cd749`（分支 `2.0.2`），主仓库指针 `793f39a`（已合并入 `main`，PR #1）

## 验证方法

1. `php -l shared-admin/src/AdminCore.php` 语法检查
2. 登录后台打开「文档」页，不再报类找不到
3. 生产端更新后若仍报错，先确认子模块已同步：

```bash
git submodule update --init --recursive
```

## 同类问题预防

- 在 `shared-admin/src/` 下新增类时，保持「命名空间 = 目录层级」的 PSR-4 对应关系，即可被自动加载
- 页面模板中 `use` 的类若报 not found，先检查文件路径与命名空间是否一致

---

**最后更新**：2026-11-04
