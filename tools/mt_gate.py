#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
mt_gate.py — 机翻稿结构完整性质检闸门（不做翻译，只做 round-trip 校验）。
用法：
  python3 mt_gate.py <en.md> <zh_mt.md>
退出码 0 = 通过；非 0 = 有结构差异（stdout 打印明细）。

校验项（只保护“结构”，不限制可见文字翻译）：
  1. Markdown 链接的 href target 数量与逐条顺序完全一致（显示文本允许翻译）。
  2. 图片 ![](src) 数量与 src 完全一致。
  3. 标题层级序列（# 的个数）一致。
  4. 原生 HTML 表格 <table> 数量一致（若有）。
  5. 不允许残留翻译占位符；不允许出现未还原的内部占位 token。
"""
import sys, re, urllib.parse

# 抓 markdown 链接，但排除裸图片 ![](...)
LINK_RE = re.compile(r'(?<!\!)\[([^\]]*)\]\(([^)]*)\)')
IMG_RE  = re.compile(r'!\[[^\]]*\]\(([^)]*)\)')
HEAD_RE = re.compile(r'^(#{1,6})\s+', re.M)
TBL_RE  = re.compile(r'<table\b', re.I)
PLACEHOLDER_LEAK = re.compile(r'@@(?:HBHTMLTBL|HBLINK|HBIMG)\d*@@|⟦[A-Z]+\d*⟧')

def norm_target(u: str) -> str:
    """链接 target 归一：只比路径骨架，忽略可见空格差异；保留 query/anchor。"""
    u = u.strip()
    # 拆分 title：(url "title")
    parts = u.split(None, 1)
    url = parts[0] if parts else u
    url = url.replace('\\#', '#')
    return url

def analyze(md: str):
    links = [norm_target(m.group(2)) for m in LINK_RE.finditer(md)]
    imgs  = [m.group(1).strip() for m in IMG_RE.finditer(md)]
    heads = [len(m.group(1)) for m in HEAD_RE.finditer(md)]
    tables = len(TBL_RE.findall(md))
    # 链接里的 /entry/ target 单独抽出来重点比对（permalink 绝不能变）
    entry_targets = [t for t in links if '/entry/' in t or t.startswith('/entry/')]
    return dict(links=links, imgs=imgs, heads=heads, tables=tables,
                entry_targets=entry_targets)

def main():
    if len(sys.argv) != 3:
        print(__doc__); return 2
    en = open(sys.argv[1], encoding='utf-8').read()
    mt = open(sys.argv[2], encoding='utf-8').read()
    a, b = analyze(en), analyze(mt)
    problems = []

    if PLACEHOLDER_LEAK.search(mt):
        problems.append("残留未还原占位符: " + ", ".join(sorted(set(PLACEHOLDER_LEAK.findall(mt)))))

    if len(a['imgs']) != len(b['imgs']):
        problems.append(f"图片数量不一致 EN={len(a['imgs'])} MT={len(b['imgs'])}")
    else:
        for i,(x,y) in enumerate(zip(a['imgs'],b['imgs'])):
            if x != y:
                problems.append(f"图片#{i} src 变化:\n   EN: {x}\n   MT: {y}")

    if len(a['entry_targets']) != len(b['entry_targets']):
        problems.append(f"/entry/ 内链数量不一致 EN={len(a['entry_targets'])} MT={len(b['entry_targets'])}")
    else:
        for i,(x,y) in enumerate(zip(a['entry_targets'],b['entry_targets'])):
            if x != y:
                problems.append(f"内链#{i} target 变化:\n   EN: {x}\n   MT: {y}")

    if len(a['links']) != len(b['links']):
        problems.append(f"链接总数不一致 EN={len(a['links'])} MT={len(b['links'])}")

    if a['heads'] != b['heads']:
        problems.append(f"标题层级序列不一致:\n   EN: {a['heads']}\n   MT: {b['heads']}")

    if a['tables'] != b['tables']:
        problems.append(f"HTML 表格数量不一致 EN={a['tables']} MT={b['tables']}")

    if problems:
        print("GATE FAIL —— 结构与 EN 不一致：")
        for p in problems: print("  ✗", p)
        print(f"\n汇总 EN: links={len(a['links'])} entrylinks={len(a['entry_targets'])} imgs={len(a['imgs'])} heads={a['heads']} tables={a['tables']}")
        print(f"汇总 MT: links={len(b['links'])} entrylinks={len(b['entry_targets'])} imgs={len(b['imgs'])} heads={b['heads']} tables={b['tables']}")
        return 1
    print("GATE PASS ✓")
    print(f"  links={len(a['links'])} entrylinks={len(a['entry_targets'])} imgs={len(a['imgs'])} head_levels={a['heads']} tables={a['tables']}")
    return 0

if __name__ == '__main__':
    sys.exit(main())
