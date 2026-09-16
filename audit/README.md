# 18106 汉化与结构体检报告

生成时间：2026-09-12 23:00（Asia/Shanghai）
数据源：18105 克隆至 18106 后的 content/

## 一、规模
- 条目（中文目录 pages/）：682
- 英文源（pages_en/）：655（缺英文源 27 篇，多为中文原创/画师页）
- 条目标题已译中文：204 / 682
- 正文内链（/entry/…）：4855 条

## 二、正文汉化分级（按中文字符数粗略判定）
- full（≥200 字，较完整）：6
- partial（20–200 字，零星汉化）：24
- raw（<20 字，基本英文原文）：652

> 结论：约 652 篇正文仍是英文，主体翻译量很大，需按优先级分批，不宜一次性铺开。

## 三、内链断链
- 断链实例：363；涉及页面：202；不同断链目标：22
- 最高频（可优先用规则文件修，无需改正文）：
  - 279× TPB
  -  12× B.P.R.D. Omnibus Editions
  -  12× Vril
  -   8× Trade Paperback Collections
  -   6× Ghost Hellboy
  -   6× Batman Hellboy Starman
  -   5× Lobster Johnson (character)
  -   5× Fenix
  -   5× Harry McTell
  -   4× Hellboy: The Science of Evil
  -   4× Hellboy Winter Special
  -   3× BPRD
  -   2× Get the Lobster
  -   2× Magic
  -   2× Archibald Muraro
  -   2× Tonči Zonjić
  -   1× 地獄怪客：血后的崛起
  -   1× Alessandro Divizia
  -   1× Ogdru Hem
  -   1× Hyperboreans
  -   1× Witch ball
  -   1× BPRD:Hell on Earth

修复手段：优先在 content/en_redirects.json 增加「英文名 -> 本地正条(permalink)」映射，
渲染层已支持，正文 4855 条链接零改动即可复活；仅在确实没有对应条目时才新建词条。

## 四、建议的统一词典（待建）
- 新增 content/glossary.json 作为唯一译名事实源：
  {"英文标准名": {"zh": "规范中译", "aliases": ["旧译/变体", "..."]}}
- 由它反向生成/校验 slugs.json、en_title.json、en_redirects.json、aliases.json。
- 首条已定调：Hellboy -> 地狱男爵（aliases 收录 地狱小子/地狱男孩，做兼容重定向）。

## 五、建议批次（优先级）
1. 核心人物 35（门户核心名单）
2. 核心漫画系列 15
3. 核心画师 8
4. 参考/表格页：出版时间线、宇宙全系列、平装/精装/馆藏版/即将推出
5. 其余 A–Z（按内链入度排序，先译被引用最多的）

明细见同目录 i18n-audit.csv（可用 Excel/Numbers 打开，含每页汉化级别、内链数、断链清单）。
