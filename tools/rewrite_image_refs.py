#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
rewrite_image_refs.py — 原图 webp 化后，全局改 md / JSON / PHP 里的图片引用。

规则（精确，不误伤正文讨论人名）：
  1. md 文件：路径形式 `images/xxx.jpg|jpeg|png|gif` → `images/xxx.webp`
     （匹配 /content/images/、../images/、images/ 等所有带路径的引用）
  2. meta/*.json：`"image": "xxx.ext"` → `"image": "xxx.webp"`
  3. content/*.json（nav/portal/portals）：所有字符串值里的图片路径
  4. index.php / admin/*.php：硬编码的图片字面量（仅替换 content/images/ 路径上下文）

用法：
  python tools/rewrite_image_refs.py            # dry-run
  python tools/rewrite_image_refs.py --apply
"""
import argparse
import json
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
CONTENT = ROOT / "content"

# 匹配 / 任意 images/ 路径 + 图片扩展名（不带文件名边界问题）
IMG_PATH_RE = re.compile(r'(images/[^\s"\'\)\]]+?)\.(jpg|jpeg|png|gif)\b', re.IGNORECASE)
# meta JSON 的 image 字段值："image": "xxx.jpg"
META_IMAGE_RE = re.compile(r'("image"\s*:\s*")([^"]+?)\.(jpg|jpeg|png|gif)(")', re.IGNORECASE)


def rewrite_text(text: str) -> tuple[str, int]:
    """返回 (新文本, 替换次数)"""
    n1 = 0
    def path_sub(m):
        nonlocal n1
        n1 += 1
        return m.group(1) + ".webp"
    text = IMG_PATH_RE.sub(path_sub, text)
    n2 = 0
    def meta_sub(m):
        nonlocal n2
        n2 += 1
        return m.group(1) + m.group(2) + ".webp" + m.group(4)
    text = META_IMAGE_RE.sub(meta_sub, text)
    return text, n1 + n2


def process_file(p: Path, apply: bool) -> int:
    try:
        text = p.read_text(encoding="utf-8")
    except UnicodeDecodeError:
        return 0
    new, n = rewrite_text(text)
    if n == 0:
        return 0
    if apply:
        p.write_text(new, encoding="utf-8", newline="")
    print(f"  {p.relative_to(ROOT)}  ×{n}")
    return n


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--apply", action="store_true")
    args = ap.parse_args()

    targets = []
    # md 四目录
    for sub in ["pages", "pages_mt", "pages_en", "custom"]:
        targets.extend((CONTENT / sub).glob("*.md"))
    # meta JSON
    targets.extend((CONTENT / "meta").glob("*.json"))
    # 顶层 JSON
    for jf in ["nav.json", "portal.json", "portals.json"]:
        p = CONTENT / jf
        if p.exists():
            targets.append(p)
    # PHP 文件（index.php, admin/*.php）
    targets.append(ROOT / "index.php")
    targets.extend((ROOT / "admin").glob("*.php"))

    print(f"模式: {'APPLY' if args.apply else 'DRY-RUN'}  目标文件数: {len(targets)}")
    total = 0
    for p in sorted(targets):
        total += process_file(p, args.apply)
    print()
    print(f"总替换次数: {total}")
    if not args.apply:
        print("→ dry-run，加 --apply 实际写盘")


if __name__ == "__main__":
    sys.exit(main())
