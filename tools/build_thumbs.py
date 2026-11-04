#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
build_thumbs.py — Hellboy Wiki 图片派生器（thumb / gallery 两档 webp）

用法：
  python tools/build_thumbs.py                 # dry-run，只打印计划
  python tools/build_thumbs.py --apply        # 实际写文件（幂等、增量）
  python tools/build_thumbs.py --apply --only "Lobster.jpg,Bill.jpg"
  python tools/build_thumbs.py --apply --force  # 忽略 mtime 重做

派生档位：
  thumb/   宽 400px, q80 —— 卡片墙 / browse / gallery 页缩略图
  gallery/ 宽 800px, q82 —— 正文画廊、信息框封面

规则：
  - 源图比目标宽度小：不放大，按原尺寸输出 webp（保持命名一致）
  - PNG 透明通道保留；GIF 只取第一帧
  - 目标已存在且 mtime >= 源 mtime → 跳过
  - 输出目录：content/images/thumb/、content/images/gallery/（自动建）
"""
import argparse
import io
import os
import sys
from pathlib import Path

from PIL import Image, ImageOps

ROOT = Path(__file__).resolve().parent.parent
IMG_DIR = ROOT / "content" / "images"
THUMB_DIR = IMG_DIR / "thumb"
GALLERY_DIR = IMG_DIR / "gallery"

# (子目录名, 目标最大宽度, webp 质量)
PROFILES = [
    ("thumb", 400, 80),
    ("gallery", 800, 82),
]

EXT = {".jpg", ".jpeg", ".png", ".gif", ".webp"}


def human(n: int) -> str:
    for u in ("B", "KB", "MB", "GB"):
        if n < 1024:
            return f"{n:.1f}{u}"
        n /= 1024
    return f"{n:.1f}TB"


def pick_source_images(only: list[str] | None):
    files = [p for p in IMG_DIR.iterdir() if p.is_file() and p.suffix.lower() in EXT]
    if only:
        only_set = set(only)
        files = [p for p in files if p.name in only_set]
    return sorted(files, key=lambda p: p.name.lower())


def derive(src: Path, out_path: Path, max_w: int, quality: int, force: bool):
    """生成一张派生图。返回 (status, src_size, out_size)。
    status: 'written' | 'skipped' | 'error'"""
    if out_path.exists() and not force and out_path.stat().st_mtime >= src.stat().st_mtime:
        return ("skipped", src.stat().st_size, out_path.stat().st_size)

    try:
        with Image.open(src) as im:
            im.load()
            # 应用 EXIF Orientation（手机/截图保存的图常带旋转元数据，不转则缩略图会歪）
            im = ImageOps.exif_transpose(im)
            # GIF 取第一帧
            if im.format == "GIF":
                im = im.convert("RGBA" if im.mode in ("RGBA", "P") else "RGB")
            else:
                # 统一处理模式：透明 PNG 保留 RGBA
                if im.mode not in ("RGB", "RGBA"):
                    im = im.convert("RGBA" if "A" in im.mode else "RGB")
            w, h = im.size
            if w > max_w:
                nh = round(h * max_w / w)
                im = im.resize((max_w, nh), Image.LANCZOS)
            buf = io.BytesIO()
            im.save(buf, format="WEBP", quality=quality, method=4)
            data = buf.getvalue()
    except Exception as e:
        print(f"  ✗ {src.name}: {e}", file=sys.stderr)
        return ("error", src.stat().st_size, 0)

    if not force and out_path.exists() and out_path.stat().st_mtime >= src.stat().st_mtime:
        return ("skipped", src.stat().st_size, out_path.stat().st_size)

    return ("written", src.stat().st_size, len(data), data)


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--apply", action="store_true", help="实际写文件（默认 dry-run）")
    ap.add_argument("--only", type=str, default="", help="逗号分隔的文件名白名单")
    ap.add_argument("--force", action="store_true", help="忽略 mtime 强制重做")
    args = ap.parse_args()

    only = [s.strip() for s in args.only.split(",") if s.strip()] or None
    sources = pick_source_images(only)
    if not sources:
        print("没有匹配的源图。")
        return 1

    print(f"模式: {'APPLY' if args.apply else 'DRY-RUN'}  源图数: {len(sources)}")
    print(f"源目录: {IMG_DIR}")
    print()

    stats = {
        "written": 0, "skipped": 0, "error": 0,
        "src_bytes": 0, "out_bytes": 0,
        "per_file": [],
    }

    for src in sources:
        row = {"name": src.name, "src_kb": round(src.stat().st_size / 1024, 1)}
        line = f"  {src.name}  ({human(src.stat().st_size)})"
        for sub, max_w, q in PROFILES:
            out_path = (THUMB_DIR if sub == "thumb" else GALLERY_DIR) / (src.stem + ".webp")
            res = derive(src, out_path, max_w, q, args.force)
            status, src_size, out_size = res[0], res[1], res[2]
            data = res[3] if len(res) > 3 else None
            if status == "written":
                if args.apply and data is not None:
                    out_path.parent.mkdir(parents=True, exist_ok=True)
                    out_path.write_bytes(data)
                stats["written"] += 1
                stats["out_bytes"] += out_size
                row[sub] = human(out_size)
                line += f"  | {sub}: {human(out_size)} {'(W)' if args.apply else '(dry)'}".replace("(W)", "WRITE")
            elif status == "skipped":
                stats["skipped"] += 1
                stats["out_bytes"] += out_size
                row[sub] = human(out_size) + " (skip)"
                line += f"  | {sub}: skip"
            else:
                stats["error"] += 1
                row[sub] = "ERR"
                line += f"  | {sub}: ERR"
            stats["src_bytes"] += src_size if sub == "thumb" else 0
        stats["per_file"].append(row)
        print(line)

    print()
    total_src = sum(r["src_kb"] * 1024 for r in stats["per_file"])
    total_out = stats["out_bytes"]
    ratio = (1 - total_out / total_src) * 100 if total_src else 0
    print(f"汇总: written={stats['written']}  skipped={stats['skipped']}  error={stats['error']}")
    print(f"源体积: {human(int(total_src))}   派生合计(thumb+gallery): {human(int(total_out))}   节省: {ratio:.1f}%")
    if not args.apply:
        print()
        print("→ 当前为 dry-run，加 --apply 实际写文件")
    return 0 if stats["error"] == 0 else 2


if __name__ == "__main__":
    sys.exit(main())
