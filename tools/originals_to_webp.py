#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
originals_to_webp.py — 原图 webp 化（物理替换，md 引用零改动）

策略：
  1. 扫 content/images/*.{jpg,jpeg,png,gif}
  2. 同名 stem 输出为 content/images/{stem}.webp（q=88，保 EXIF 旋转）
  3. 原 jpg/png/gif 移到 BACKUP_DIR（不删除，留作本地备份）
  4. 不碰 thumb/、gallery/ 子目录

md/meta 里仍写 "Lobster.jpg"；渲染层 img() 负责检测物理 .webp 优先。

用法：
  python tools/originals_to_webp.py                # dry-run
  python tools/originals_to_webp.py --apply       # 实际转换 + 移走原图
  python tools/originals_to_webp.py --apply --q 90
"""
import argparse
import shutil
import sys
from pathlib import Path
from PIL import Image, ImageOps

ROOT = Path(__file__).resolve().parent.parent
IMG_DIR = ROOT / "content" / "images"
BACKUP_DIR = Path(r"D:\VS CODE\hellboy_wiki-originals-backup")

EXT = {".jpg", ".jpeg", ".png", ".gif"}


def human(n):
    for u in ("B", "KB", "MB", "GB"):
        if n < 1024: return f"{n:.1f}{u}"
        n /= 1024
    return f"{n:.1f}TB"


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--apply", action="store_true")
    ap.add_argument("--q", type=int, default=88)
    args = ap.parse_args()

    srcs = [p for p in IMG_DIR.iterdir() if p.is_file() and p.suffix.lower() in EXT]
    if not srcs:
        print("没有源图"); return 1

    print(f"模式: {'APPLY' if args.apply else 'DRY-RUN'}  源图数: {len(srcs)}  q={args.q}")
    print(f"备份目录: {BACKUP_DIR}")
    print()

    total_src = total_new = 0
    big_savings = []
    for src in srcs:
        dst = IMG_DIR / (src.stem + ".webp")
        if dst.exists():
            continue  # 已经转过
        try:
            with Image.open(src) as im:
                im.load()
                im = ImageOps.exif_transpose(im)
                if im.format == "GIF":
                    im = im.convert("RGBA" if im.mode in ("RGBA", "P") else "RGB")
                elif im.mode not in ("RGB", "RGBA"):
                    im = im.convert("RGBA" if "A" in im.mode else "RGB")
                buf = __import__("io").BytesIO()
                im.save(buf, format="WEBP", quality=args.q, method=4)
                data = buf.getvalue()
        except Exception as e:
            print(f"  ✗ {src.name}: {e}", file=sys.stderr)
            continue
        s = src.stat().st_size
        total_src += s
        total_new += len(data)
        ratio = (1 - len(data)/s) * 100
        if s > 500*1024:
            big_savings.append((src.name, s, len(data), ratio))
        if args.apply:
            dst.write_bytes(data)
            BACKUP_DIR.mkdir(parents=True, exist_ok=True)
            shutil.move(str(src), str(BACKUP_DIR / src.name))

    print(f"源体积(本次): {human(total_src)}")
    print(f"新 webp 体积: {human(total_new)}")
    print(f"节省: {(1-total_new/total_src)*100:.1f}%")
    print()
    print("--- 大图节省 Top 10 (>500KB) ---")
    big_savings.sort(key=lambda x: -x[1])
    for name, s, n, r in big_savings[:10]:
        print(f"  {name[:60]:60}  {human(s):>10} → {human(n):>10}  ({r:.0f}%)")
    if not args.apply:
        print("\n→ dry-run，加 --apply 实际转换")


if __name__ == "__main__":
    sys.exit(main())
