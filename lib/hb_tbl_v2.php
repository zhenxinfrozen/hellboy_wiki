<?php
/**
 * hb_tbl_v2.php — 塌表重建器 v2（渲染层，md 源不动）
 *
 * 早期英文/机译导入管线在把 MediaWiki 表格转站内 markdown 时剥掉了 <table>/<tr>/<td>，
 * 只留裸 <tbody>，v1 重建器只覆盖了「合订本·平装」一种表头。v2 按表头签名分派：
 *   - omnibus 堆叠表（Cover/Date/Contents，卷内 Collects/Additional Material/Notes 多段）
 *   - origpub（Cover/Date/Originally Published as，标题并入封面格）
 *   - 通用定位表（小说、SDCC、Weird Tales T2/T3 等，块数==列数，以封面列锚定）
 *   - anthology（书名 rowspan + 故事/作者子行）
 *   - wt_issues（期号 rowspan，编剧/画师，缺画师则编剧 colspan=2）
 *   - awards（年/作品/结果）、winter（4 列扁平，strong 表头）
 * 任何无法严格对齐的表一律原样保留（宁可不修，也不串行/丢字）。
 */

/* ------------------------------------------------------------------ */
/* 表头映射                                                            */
/* ------------------------------------------------------------------ */
function hbt2_header_map(): array {
    return [
        '#'=>'num', 'title'=>'title', '书名'=>'title',
        'date published'=>'date', 'published'=>'date', '出版日期'=>'date',
        'material collected'=>'material', 'material collected:'=>'material', '收录内容'=>'material',
        'additional material'=>'additional', 'additional materials'=>'additional',
        '附加内容'=>'additional', '附加素材'=>'additional',
        'cover'=>'cover', '封面'=>'cover',
        'library edition'=>'lib', '图书馆版（library edition）'=>'lib', '图书馆版'=>'lib',
        'omnibus'=>'omni', '大全集版（omnibus）'=>'omni', '大全集版'=>'omni',
        'author'=>'author', 'author(s)'=>'author', 'author/s'=>'author',
        '编剧'=>'writer', '作者'=>'author',
        'artist'=>'artist', 'artist(s)'=>'artist', '绘者'=>'artist', '画师'=>'artist',
        'collected in'=>'collected', '收录于'=>'collected',
        'contents'=>'contents', '内容'=>'contents',
        'originally published as'=>'origpub', '最初出版名'=>'origpub',
        'isbn'=>'isbn',
        'story'=>'story', '故事'=>'story', 'film'=>'film', '电影'=>'film',
        'containing publication'=>'pub', '所在出版物'=>'pub',
        'issue number'=>'issue', '期号'=>'issue',
        'writer(s)'=>'writer',
        'year'=>'year', 'for work on'=>'work', 'result'=>'result',
        'winter special'=>'winter', '深冬特别篇'=>'winter', 'stories'=>'stories',
        'trade paperback'=>'tpb', '平装合订本'=>'tpb', '全集合订本'=>'omni2',
    ];
}

/* 卷内语义行标签（是正文行，要保留在 Contents 列里） */
function hbt2_section_labels(): array {
    return ['collects','collect','收录',
            'additional material','additional materials','附加内容','附加素材',
            'additional notes','补充说明'];
}
/* 体内重复出现的列名标签（杂质，丢弃） */
function hbt2_header_echo_labels(): array {
    return ['cover','封面','date published','出版日期','contents','内容'];
}

/* ------------------------------------------------------------------ */
/* 文本工具                                                            */
/* ------------------------------------------------------------------ */
function hbt2_strip(string $s): string {
    $s = preg_replace('#<[^>]+>#','',$s);
    return html_entity_decode($s, ENT_QUOTES|ENT_HTML5, 'UTF-8');
}
function hbt2_norm(string $s): string {
    return trim(preg_replace('/\s+/u',' ', hbt2_strip($s)));
}
function hbt2_is_junk_name(string $b): bool {
    $t = hbt2_norm($b);
    return $t!=='' && !str_contains($b,'![](') && !preg_match('#<img\b#i',$b)
        && (bool)preg_match('/^[\w][\w .\-\'()]*\.(?:jpg|jpeg|png|gif|webp)$/iu',$t);
}
function hbt2_date_re(): string {
    return '/^\s*(?:January|February|March|April|May|June|July|August|September|October|November|December|\d{4}|[—–-]\s*\d{4}|\?+|TBD|TBA|N\s*\/?\s*A|Fall|Spring|Summer|Winter|Late|Early|\d{4}\s*年)/iu';
}
function hbt2_is_date(string $b): bool {
    return (bool)preg_match(hbt2_date_re(), hbt2_norm($b));
}
function hbt2_kind(string $b): string {
    if (preg_match('#^<(ul|ol)\b#i',$b)) return 'list';
    if (str_contains($b,'![](') || preg_match('#<img\b#i',$b)) return 'img';
    $t = hbt2_norm($b);
    if (preg_match('/^(#?\d+[a-z]?|N\/?A|NA)$/iu',$t)) return 'num';
    if (hbt2_is_date($b)) return 'date';
    return 'txt';
}

/* ------------------------------------------------------------------ */
/* 块切分（与 v1 同规则：标签未闭合 / 行尾 <br> 向下合并；ul/ol 整体）   */
/* ------------------------------------------------------------------ */
function hbt2_split_blocks(array $body): array {
    $recs=[]; $cur=[];
    $flush=function() use (&$cur,&$recs){ if($cur){$recs[]=$cur;$cur=[];} };
    $n=count($body); $i=0;
    while ($i<$n) {
        $s=trim($body[$i]);
        if ($s==='') { $flush(); $i++; continue; }
        if (preg_match('/^\s*align\s*=\s*"?left\|?\s*$/i',$s)) { $i++; continue; }
        if (preg_match('#^<(ul|ol)\b#i',$s,$mm) && stripos($s,'</'.$mm[1])===false) {
            $b=[$s]; $i++;
            while ($i<$n) {
                $b[]=trim($body[$i]);
                if (stripos($body[$i],'</'.$mm[1])!==false){$i++;break;}
                $i++;
            }
            $cur[]=implode("\n",$b); continue;
        }
        $openMore=function(string $x):bool {
            return (substr_count($x,'<em')>substr_count($x,'</em>'))
                || (substr_count($x,'<span')>substr_count($x,'</span>'))
                || (substr_count($x,'<a ')>substr_count($x,'</a>'))
                || (bool)preg_match('#<br\s*/?>$#i',$x);
        };
        if ($openMore($s)) {
            $b=[$s]; $i++;
            while ($i<$n) {
                $nx=trim($body[$i]); $b[]=$nx; $i++;
                if (preg_match('#<br\s*/?>$#i',$nx)) continue;
                $j=implode("\n",$b);
                if (substr_count($j,'<em')>substr_count($j,'</em>')) continue;
                if (substr_count($j,'<span')>substr_count($j,'</span>')) continue;
                if (substr_count($j,'<a ')>substr_count($j,'</a>')) continue;
                break;
            }
            $cur[]=implode("\n",$b); continue;
        }
        $cur[]=$s; $i++;
    }
    $flush();
    return $recs;
}

/* ------------------------------------------------------------------ */
/* 单元格内 markdown → HTML（表格在 Parsedown 之前抽出）                */
/* ------------------------------------------------------------------ */
function hbt2_inline(string $s): string {
    $s = preg_replace_callback('#!\[([^\]]*)\]\(([^)\s]+)(?:\s+"[^"]*")?\)#', function($im){
        $src=$im[2];
        if (!preg_match('#^https?://#i',$src) && !str_starts_with($src,'/')) {
            $src='/content/images/'.basename(rawurldecode(explode('?',$src)[0]));
        }
        return '<img src="'.e($src).'" alt="'.e($im[1]).'" />';
    }, $s);
    // markdown 链接 [text](/entry/Slug)（已在站的 permalink；裸标题形式的保留文字）
    $s = preg_replace_callback('#(?<!["\'])\[([^\]]+)\]\(((?:/entry/|https?://|/Special:)[^)]+)\)#u', function($m){
        return '<a href="'.e($m[2]).'">'.$m[1].'</a>';
    }, $s);
    $s = preg_replace('#\*\*([^*]+)\*\*#u','<strong>$1</strong>',$s);
    // MediaWiki 斜体残留 '' （两个单引号）
    $s = str_replace("''",'',$s);
    return $s;
}

/* ------------------------------------------------------------------ */
/* 建表原语                                                            */
/* ------------------------------------------------------------------ */
function hbt2_table(array $heads, array $rows): string {
    // $rows: list of list of cell-spec；cell-spec:
    //   null            → 占位（rowspan 已由错点输出，不输出 td）
    //   ['rs'=>n,'v'=>html]
    //   ['cs'=>n,'v'=>html]
    //   string          → 普通格
    $h='<table><thead><tr>';
    foreach ($heads as $c) $h.='<th><p>'.e($c).'</p></th>';
    $h.='</tr></thead><tbody>';
    foreach ($rows as $cells) {
        $h.='<tr>';
        foreach ($cells as $cell) {
            if ($cell===null) continue;
            if (is_string($cell)) { $h.='<td>'.hbt2_inline($cell).'</td>'; continue; }
            $attr='';
            if (!empty($cell['rs']) && $cell['rs']>1) $attr.=' rowspan="'.(int)$cell['rs'].'"';
            if (!empty($cell['cs']) && $cell['cs']>1) $attr.=' colspan="'.(int)$cell['cs'].'"';
            $h.='<td'.$attr.'>'.hbt2_inline((string)$cell['v']).'</td>';
        }
        $h.='</tr>';
    }
    $h.='</tbody></table>';
    return $h;
}

/* 找全部裸 tbody（行内独立） */
function hbt2_find_tables(array $lines): array {
    $out=[];
    $tb=[];
    foreach ($lines as $i=>$l) if (preg_match('/^\s*<tbody>\s*$/i',$l)) $tb[]=$i;
    foreach ($tb as $idx) {
        $j=$idx-1;
        while ($j>=0 && trim($lines[$j])==='') $j--;
        $hdr=[];
        while ($j>=0 && trim($lines[$j])!=='') {
            $s=trim($lines[$j]);
            if (preg_match('#<(tbody|/tbody|ul|ol|li)\b#i',$s) || preg_match('/^#{2,4}\s/',$s)) break;
            $hdr[]=$s; $j--;
        }
        $hstart=$j+1; // 表头块真实上边界（表头与 <tbody> 之间可能隔空行，不能用 idx-count(hdr)）
        $hdr=array_reverse($hdr);
        $end=count($lines);
        for ($x=$idx+1;$x<count($lines);$x++){
            $s=trim($lines[$x]);
            if ($s==='</tbody>' || preg_match('/^\s*<tbody>\s*$/i',$lines[$x]) || preg_match('/^#{2,4}\s/',$s)){ $end=$x; break; }
        }
        $body=[];
        for ($x=$idx+1;$x<$end;$x++) if (trim($lines[$x])!=='</tbody>') $body[]=$lines[$x];
        $out[]=['idx'=>$idx,'end'=>$end,'hdr'=>$hdr,'body'=>$body,'hstart'=>$hstart];
    }
    return $out;
}

function hbt2_parse_header(array $hdrLines): array {
    $map=hbt2_header_map(); $keys=[]; $raw=[];
    foreach ($hdrLines as $h) {
        $t=mb_strtolower(hbt2_norm($h),'UTF-8');
        $raw[]=hbt2_norm($h);
        $keys[]=$map[$t] ?? null;
    }
    return [$keys,$raw];
}

/* ------------------------------------------------------------------ */
/* Builder: 通用定位表（块序列按列序对齐；以封面列锚定）                 */
/* 返回 [heads, rows] 或 null                                           */
/* ------------------------------------------------------------------ */
function hbt2_build_positional(array $keys, array $rawHdr, array $groups): ?array {
    $C=count($keys);
    $coverCi=array_search('cover',$keys,true);
    $dateCi =array_search('date',$keys,true);
    $numCi  =array_search('num',$keys,true);
    $listCols=[]; foreach ($keys as $i=>$k) if (in_array($k,['material','additional'],true)) $listCols[]=$i;
    $required=['title','story','author','film','pub','isbn','num'];
    $rows=[];
    foreach ($groups as $g) {
        $slots=array_fill(0,$C,null);
        $used=[];
        if ($coverCi!==false) {
            $imgAt=null; foreach ($g as $k=>$b) if (hbt2_kind($b)==='img') { $imgAt=$k; break; }
            if ($imgAt===null) return null;
            $slots[$coverCi]=$g[$imgAt]; $used[]=$imgAt;
        }
        if ($dateCi!==false) {
            foreach ($g as $k=>$b) if (!in_array($k,$used,true) && hbt2_kind($b)==='date') {
                $slots[$dateCi]=$b; $used[]=$k; break;
            }
        }
        if ($numCi!==false && !in_array(0,$used,true) && hbt2_kind($g[0])==='num') {
            $slots[$numCi]=$g[0]; $used[]=0;
        }
        $li=0;
        foreach ($g as $k=>$b) {
            if (in_array($k,$used,true)) continue;
            if (hbt2_kind($b)==='list') {
                if (!isset($listCols[$li])) return null;
                $slots[$listCols[$li++]]=$b; $used[]=$k;
            }
        }
        $ri=0;
        foreach ($g as $k=>$b) {
            if (in_array($k,$used,true)) continue;
            while ($ri<$C && $slots[$ri]!==null) $ri++;
            if ($ri>=$C) return null;
            $slots[$ri++]=$b; $used[]=$k;
        }
        foreach ($keys as $i=>$role) {
            if ($slots[$i]===null && in_array($role,$required,true)) return null;
        }
        $rows[]=$slots;
    }
    $heads=array_map(fn($k)=>hbt2_col_name($k),$keys);
    return [$heads,$rows];
}

function hbt2_localize_heads(array $heads, array $keys, array $rawHdr): array {
    $roleLabel=[];
    foreach ($keys as $i=>$role) {
        if ($role!==null && isset($rawHdr[$i]) && $rawHdr[$i]!=='') $roleLabel[$role]=$rawHdr[$i];
    }
    $cjk=false;
    foreach ($rawHdr as $r) if (preg_match('/\p{Han}/u',$r)) { $cjk=true; break; }
    $out=[];
    foreach ($heads as $h) {
        $hit=null;
        foreach ($roleLabel as $role=>$lab) if (hbt2_col_name($role)===$h) { $hit=$lab; break; }
        if ($hit!==null) { $out[]=$hit; continue; }
        if ($h==='Volume') { $out[]= $cjk ? '卷' : 'Volume'; continue; }
        $out[]=$h;
    }
    return $out;
}
function hbt2_col_name(string $k): string {
    static $m=[
        'num'=>'#','title'=>'Title','date'=>'Date Published','material'=>'Material Collected',
        'additional'=>'Additional Material','cover'=>'Cover','lib'=>'Library Edition',
        'omni'=>'Omnibus','author'=>'Author','artist'=>'Artist','collected'=>'Collected in',
        'contents'=>'Contents','origpub'=>'Originally Published as','isbn'=>'ISBN',
        'story'=>'Story','film'=>'Film','pub'=>'Containing Publication','issue'=>'Issue Number',
        'writer'=>'Writer(s)','work'=>'For work on','result'=>'Result','year'=>'Year',
        'winter'=>'Winter Special','stories'=>'Stories','tpb'=>'Trade Paperback','omni2'=>'Omnibus',
    ];
    return $m[$k] ?? $k;
}

/* ------------------------------------------------------------------ */
/* Builder: anthology（书名/封面/日期 rowspan + 故事/作者子行）          */
/* ------------------------------------------------------------------ */
function hbt2_build_anthology(array $keys, array $groups): ?array {
    // 列：title,cover,date,story,author
    $ci=array_flip($keys);
    $heads=array_map(fn($k)=>hbt2_col_name($k),$keys);
    $rows=[]; $cur=null;
    foreach ($groups as $g) {
        if (count($g)===5) {
            // 校验 title/img/date 位置
            if (hbt2_kind($g[$ci['cover']])!=='img' || hbt2_kind($g[$ci['date']])!=='date') return null;
            $cur=['head'=>$g,'subs'=>[]]; $rows[]=&$cur; unset($cur);
        } elseif (count($g)===2 && !empty($rows)) {
            $rows[array_key_last($rows)]['subs'][]=$g;
        } else return null;
    }
    if (!$rows) return null;
    $out=[];
    foreach ($rows as $book) {
        $k=count($book['subs'])+1;
        $h=$book['head'];
        // 第一行
        $line=[];
        foreach ($keys as $idx=>$role) {
            if (in_array($role,['title','cover','date'],true)) $line[]=['rs'=>$k,'v'=>$h[$idx]];
            elseif ($role==='story') $line[]=$h[$idx];
            elseif ($role==='author') $line[]=$h[$idx];
            else $line[]=$h[$idx];
        }
        $out[]=$line;
        foreach ($book['subs'] as $sub) {
            $line=[];
            foreach ($keys as $idx=>$role) {
                if (in_array($role,['title','cover','date'],true)) $line[]=null;
                elseif ($role==='story') $line[]=$sub[0];
                elseif ($role==='author') $line[]=$sub[1];
                else $line[]='';
            }
            $out[]=$line;
        }
    }
    return [$heads,$out];
}

/* ------------------------------------------------------------------ */
/* Builder: Weird Tales 期刊表（期号/日期/封面 rowspan；编剧+画师）       */
/* ------------------------------------------------------------------ */
function hbt2_build_wt_issues(array $keys, array $groups): ?array {
    // 记录头 3 块固定为 期号/日期/封面；其后 story + 编剧(+画师) + 可选附加内容。
    // 编剧与画师为同一人时源里合并成一块 → 编剧格 colspan=2。
    $heads=array_map(fn($k)=>hbt2_col_name($k),$keys);
    $issues=[];
    foreach ($groups as $g) {
        $nb=count($g);
        $isHead = hbt2_kind($g[0])==='num' && hbt2_kind($g[1])==='date' && hbt2_kind($g[2])==='img';
        if ($isHead) {
            if ($nb!==6 && $nb!==7) return null;   // 3 + story + credits(1|2) + additional
            $issues[]=['head'=>$g,'subs'=>[]];
        } else {
            if (!$issues) return null;
            if ($nb!==2 && $nb!==3) return null;   // story + credits(1|2)
            $issues[array_key_last($issues)]['subs'][]=$g;
        }
    }
    if (!$issues) return null;
    $out=[];
    foreach ($issues as $iss) {
        $k=count($iss['subs'])+1;
        $h=$iss['head'];
        $headCredits = count($h)===7 ? [$h[4],$h[5]] : [[ 'cs'=>2,'v'=>$h[4]],null];
        $add = count($h)===7 ? $h[6] : $h[5];
        $line=[];
        $line[]=['rs'=>$k,'v'=>$h[0]];   // issue
        $line[]=['rs'=>$k,'v'=>$h[1]];   // date
        $line[]=['rs'=>$k,'v'=>$h[2]];   // cover
        $line[]=$h[3];                    // story
        $line[]=is_array($headCredits[0]) ? $headCredits[0] : $headCredits[0];
        $line[]=$headCredits[1];          // artist or null(colspan)
        $line[]=['rs'=>$k,'v'=>$add];     // additional
        $out[]=$line;
        foreach ($iss['subs'] as $sub) {
            $line=[];
            $line[]=null; $line[]=null; $line[]=null;
            $line[]=$sub[0];
            if (count($sub)===3) { $line[]=$sub[1]; $line[]=$sub[2]; }
            else { $line[]=['cs'=>2,'v'=>$sub[1]]; $line[]=null; }
            $line[]=null;
            $out[]=$line;
        }
    }
    return [$heads,$out];
}

/* ------------------------------------------------------------------ */
/* Builder: 深冬特别篇（年号 rowspan + 故事行 3 块）                     */
/* ------------------------------------------------------------------ */
function hbt2_build_winter(array $groups, array $rawHdr=[]): ?array {
    $heads=(count($rawHdr)===4)?$rawHdr:['Winter Special','Stories','Trade Paperback','Omnibus'];
    $issues=[];
    foreach ($groups as $g) {
        if (count($g)===4) $issues[]=['head'=>$g,'subs'=>[]];
        elseif (count($g)===3) {
            if (!$issues) return null;
            $issues[array_key_last($issues)]['subs'][]=$g;
        } else return null;
    }
    if (!$issues) return null;
    $out=[];
    foreach ($issues as $iss) {
        $k=count($iss['subs'])+1;
        $out[]=[['rs'=>$k,'v'=>$iss['head'][0]],$iss['head'][1],$iss['head'][2],$iss['head'][3]];
        foreach ($iss['subs'] as $sub) $out[]=[null,$sub[0],$sub[1],$sub[2]];
    }
    return [$heads,$out];
}

/* ------------------------------------------------------------------ */
/* Builder: omnibus 堆叠表                                              */
/* ------------------------------------------------------------------ */
function hbt2_title_like(string $b, array $blocksAfter): bool {
    $t=hbt2_norm($b);
    if ($t==='' || mb_strlen($t)>80) return false;
    $lt=mb_strtolower($t,'UTF-8');
    if (in_array($lt,hbt2_section_labels(),true)) return false;
    if (in_array($lt,hbt2_header_echo_labels(),true)) return false;
    if (in_array($lt,['won','nominated','nominee','result'],true)) return false;
    if (hbt2_is_date($b)) return false;   // 日期块不是卷标题
    if (preg_match('/^(19|20)\d\d$/',$t)) return false; // 纯年份（4 位）不是卷标题
    if (preg_match('#<(ul|ol|img)\b#i',$b) || str_contains($b,'![](')) return false;
    if (str_contains($b,'](/entry/') || str_contains($b,'](')) return false;
    // <strong>N</strong>
    if (preg_match('/^<strong>\s*#?\d+[a-z]?\s*<\/strong>$/iu',trim($b))) return true;
    // 单段 <em>...</em>（可带尾注 <sup>N</sup>）
    $emSingle = preg_match('/^<em>[\s\S]+<\/em>(?:\s*<sup>\s*\d+\s*<\/sup>)?$/u',trim($b));
    $looksPlain = !preg_match('/[<\[\]]/u',$t);   // 纯文本短标题（B.P.R.D. Omnibus Volume 1 / 1952–1954 走 em）
    if (!$emSingle && !$looksPlain) return false;
    // 向前看：跳过列名回声标签后，下一块须是 Collects 标签 / 图 / 日期 / 全大写封面署名
    foreach ($blocksAfter as $nx) {
        $nt=hbt2_norm($nx);
        if ($nt==='') continue;
        if (in_array(mb_strtolower($nt,'UTF-8'),hbt2_header_echo_labels(),true)) continue;
        if (in_array(mb_strtolower($nt,'UTF-8'),hbt2_section_labels(),true)) return true;
        if (hbt2_kind($nx)==='img' || hbt2_kind($nx)==='date') return true;
        if (preg_match('/^[A-Z][A-Z0-9 .,()&\'’–-]{2,40}$/u',$nt) && !preg_match('/[a-z]/',$nt)) return true;
        return false;
    }
    return false;
}

function hbt2_build_omnibus(array $keys, array $groups, bool $origPub): ?array {
    // 摊平块序列（保留组序）；先丢弃杂质/列名回声/''
    $echo=hbt2_header_echo_labels();
    $rawBlocks=[];
    foreach ($groups as $g) foreach ($g as $b) {
        if (hbt2_is_junk_name($b)) continue;
        $b=str_replace("''",'',$b);
        if (in_array(mb_strtolower(hbt2_norm($b),'UTF-8'), $echo, true)) continue;
        if (hbt2_norm($b)==='') continue;   // 纯 <br>/空白块
        $rawBlocks[]=$b;
    }
    // 段标签（Collects / Additional Material / Notes / 收录…）独占一格——
    // 与标准层 canonical 结构一致（标签一格、紧随列表/文字一格）；封面/日期在 walk 中抽走。
    $blocks=$rawBlocks;
    // 收集语义标签块（保留进 Contents）；这里只需逐块走
    $vols=[]; $cur=null;
    $newVol=function($title,$num) use (&$vols) {
        $vols[]=['title'=>$title,'num'=>$num,'cover'=>null,'date'=>null,'stack'=>[]];
        return array_key_last($vols);
    };
    $n=count($blocks);
    for ($i=0;$i<$n;$i++) {
        $b=$blocks[$i];
        $after=array_slice($blocks,$i+1,6);
        $isTitle=hbt2_title_like($b,$after);
        $kd=hbt2_kind($b);
        // 无标题卷：已有完整卷（封面+日期+至少一个列表）后，再遇图块 → 新卷
        $startsByImg = ($kd==='img' && $cur!==null && $vols[$cur]['cover']!==null
            && $vols[$cur]['date']!==null && hbt2_has_list($vols[$cur]['stack']));
        if ($isTitle) {
            $num=preg_match('/^<strong>\s*(#?\d+[a-z]?)\s*<\/strong>$/iu',trim($b),$mm)?$mm[1]:null;
            $cur=$newVol($num?null:$b,$num);
            continue;
        }
        if ($startsByImg) {
            $cur=$newVol(null,null);
        }
        if ($cur===null) $cur=$newVol(null,null);
        $vol=&$vols[$cur];
        if ($kd==='img') {
            $vol['cover'] = $vol['cover']===null ? $b : $vol['cover']."\n".$b;
        } elseif ($kd==='date' && $vol['date']===null) {
            // 日期永远属于封面格（源中日期总在首个列表之前；Collects 标签可能先入 stack）
            $vol['date']=$b;
        } else {
            // 全大写封面署名且本卷尚无封面、且尚未进入列表 → 封面格
            $t=hbt2_norm($b);
            if ($vol['cover']===null && !$vol['stack'] && $kd==='txt'
                && preg_match('/^[A-Z][A-Z0-9 .,()&\'’–-]{2,40}$/u',$t) && !preg_match('/[a-z]/',$t)) {
                $vol['cover']=$b;
            } else {
                $vol['stack'][]=$b;
            }
        }
        unset($vol);
    }
    if (!$vols) return null;
    foreach ($vols as $v) if (!$v['cover'] && !$v['date'] && !$v['stack']) return null;

    $allNum = !$origPub && !empty($vols) && array_every($vols,fn($v)=>$v['num']!==null);
    $anyTitle = !$origPub && array_some($vols,fn($v)=>$v['title']!==null);

    // origpub：标题并入封面格，列 = Cover/Date/OrigPub
    if ($origPub) {
        $heads=['Cover','Date Published','Originally Published as'];
        $rows=[];
        foreach ($vols as $v) {
            $cover=trim(($v['title']??'')."\n".($v['cover']??''));
            $nr=max(1,count($v['stack']));
            for ($r=0;$r<$nr;$r++) {
                $line=[];
                $line[] = $r===0 ? ['rs'=>$nr,'v'=>$cover] : null;
                $line[] = $r===0 ? ['rs'=>$nr,'v'=>$v['date']??''] : null;
                $line[] = $v['stack'][$r] ?? '';
                $rows[]=$line;
            }
        }
        return [$heads,$rows];
    }

    $heads=[];
    if ($allNum) $heads[]='#';
    elseif ($anyTitle) $heads[]='Volume';
    $heads[]='Cover'; $heads[]='Date Published'; $heads[]='Contents';
    $rows=[];
    foreach ($vols as $v) {
        $nr=max(1,count($v['stack']));
        for ($r=0;$r<$nr;$r++) {
            $line=[];
            if ($allNum) $line[] = $r===0 ? ['rs'=>$nr,'v'=>$v['num']] : null;
            elseif ($anyTitle) $line[] = $r===0 ? ['rs'=>$nr,'v'=>$v['title']??''] : null;
            $line[] = $r===0 ? ['rs'=>$nr,'v'=>$v['cover']??''] : null;
            $line[] = $r===0 ? ['rs'=>$nr,'v'=>$v['date']??''] : null;
            $line[] = $v['stack'][$r] ?? '';
            $rows[]=$line;
        }
    }
    return [$heads,$rows];
}

/* Rebranding 表（Cover/Date/Originally Published as）：
 * 记录以 "…Omnibus Volume N"（英）或 "…合订本 第N卷…"（译）为界。
 * 封面格 = 新名 + 图（或全大写署名，缺图卷）；日期独立；原名格 = 日期后的文本。 */
function hbt2_build_origpub(array $keys, array $groups): ?array {
    if (array_values(array_filter($keys,fn($k)=>$k!=='cover'&&$k!=='date')) !== ['origpub']) return null;
    $blocks=[];
    foreach ($groups as $g) foreach ($g as $b) {
        if (hbt2_is_junk_name($b)) continue;
        $b=str_replace("''",'',$b);
        if (hbt2_norm($b)==='') continue;
        $blocks[]=$b;
    }
    $isNewTitle=function(string $b):bool {
        return (bool)preg_match('/Omnibus\s+Volume\s+\d+/iu',$b)
            || (bool)preg_match('/合订本\s*第\s*[0-9０-９一二三四五六七八九十]+\s*卷/u',$b);
    };
    $recs=[]; $cur=null;
    foreach ($blocks as $b) {
        if ($isNewTitle($b)) { $recs[]=['title'=>$b,'cover'=>null,'date'=>null,'orig'=>[]]; continue; }
        if (!$recs) return null;
        $cur=&$recs[array_key_last($recs)];
        $kd=hbt2_kind($b); $t=hbt2_norm($b);
        if ($kd==='img') { $cur['cover'] = $cur['cover']===null ? $b : $cur['cover']."\n".$b; }
        elseif ($kd==='date' && $cur['date']===null) { $cur['date']=$b; }
        elseif ($cur['date']===null && $cur['cover']===null && $kd==='txt'
                && preg_match('/^[A-Z][A-Z0-9 .,()&\'’–-]{2,40}$/u',$t) && !preg_match('/[a-z]/',$t)) {
            $cur['cover']=$b;   // 全大写封面署名（缺图卷）
        } else { $cur['orig'][]=$b; }
        unset($cur);
    }
    if (!$recs) return null;
    foreach ($recs as $r) if ($r['date']===null || !$r['orig']) return null;
    $heads=['Cover','Date Published','Originally Published as'];
    $rows=[];
    foreach ($recs as $r) {
        $cover=trim($r['title']."\n".($r['cover']??''));
        $nr=count($r['orig']);
        for ($i=0;$i<$nr;$i++) {
            $rows[]=[
                $i===0 ? ['rs'=>$nr,'v'=>$cover] : null,
                $i===0 ? ['rs'=>$nr,'v'=>$r['date']] : null,
                $r['orig'][$i],
            ];
        }
    }
    return [$heads,$rows];
}

function hbt2_has_list(array $stack): bool {
    foreach ($stack as $s) if (preg_match('#<(ul|ol)\b#i',$s)) return true;
    return false;
}
if (!function_exists('array_every')) {
    function array_every(array $a, callable $f): bool { foreach ($a as $x) if (!$f($x)) return false; return true; }
}
if (!function_exists('array_some')) {
    function array_some(array $a, callable $f): bool { foreach ($a as $x) if ($f($x)) return true; return false; }
}

/* ------------------------------------------------------------------ */
/* 主入口                                                              */
/* ------------------------------------------------------------------ */
function hb_rebuild_collapsed_tables_v2(string $raw): string {
    // 1) 空 tbody 残留直接清除（<tbody></tbody> 可能分占两行）
    $raw=preg_replace('/[ \t]*<tbody>\s*\n\s*<\/tbody>\s*/i','',$raw);
    $raw=preg_replace('/^[ \t]*<tbody>\s*<\/tbody>[ \t]*$/im','',$raw);
    if (stripos($raw,'<tbody')===false) return $raw;

    $GLOBALS['__hbV2Built']=[];
    $lines=preg_split('/\r?\n/',$raw);
    $tables=hbt2_find_tables($lines);
    if (!$tables) return $raw;

    $built=[];   // [startLine, closeLine, html]
    foreach ($tables as $t) {
        [$keys,$rawHdr]=hbt2_parse_header($t['hdr']);
        if (in_array(null,$keys,true)) continue;          // 有识别不出的列：交回 v1/不动
        $sig=array_unique($keys);
        $body=$t['body'];
        $recs=hbt2_split_blocks($body);
        // 去杂质组
        $groups=[];
        foreach ($recs as $rec) {
            $rec=array_values(array_filter($rec,fn($b)=>!hbt2_is_junk_name($b)));
            $rec=array_map(fn($b)=>str_replace("''",'',$b),$rec);
            if ($rec) $groups[]=$rec;
        }
        if (!$groups) {
            // 空表（仅有杂质）：清除表头+tbody
            $built[]=['start'=>$t['hstart'],'close'=>$t['end'],'html'=>''];
            continue;
        }
        $pick=null;
        if (count($keys)===4 && $keys[0]==='winter' && in_array($keys[1],['stories','story'],true)
            && $keys[2]==='tpb' && in_array($keys[3],['omni2','omni'],true)) {
            $pick='winter';
        } elseif ($sig===['year','work','result'] || (count($sig)===3 && in_array('year',$sig,true) && in_array('work',$sig,true) && in_array('result',$sig,true))) {
            $pick='awards';
        } elseif (in_array('issue',$sig,true)) {
            $pick='wt_issues';
        } elseif (in_array('origpub',$sig,true)) {
            $pick='origpub';
        } elseif (in_array('contents',$sig,true)) {
            $pick='omnibus';
        } elseif (in_array('story',$sig,true) && in_array('author',$sig,true) && in_array('cover',$sig,true)
                   && in_array('date',$sig,true) && in_array('title',$sig,true) && !in_array('pub',$sig,true)) {
            $pick='anthology';
        } else {
            // 通用定位表候选：列必须全部是已知扁平角色
            $flatOk=true;
            foreach ($keys as $k) if (!in_array($k,['num','title','date','author','cover','film','story','pub','isbn','material','additional','artist','writer'],true)) $flatOk=false;
            if ($flatOk) $pick='positional';
        }
        if ($pick===null) continue;

        $res=null;
        if ($pick==='omnibus')     $res=hbt2_build_omnibus($keys,$groups,false);
        elseif ($pick==='origpub') $res=hbt2_build_origpub($keys,$groups);
        elseif ($pick==='anthology') $res=hbt2_build_anthology($keys,$groups);
        elseif ($pick==='wt_issues') $res=hbt2_build_wt_issues($keys,$groups);
        elseif ($pick==='positional') {
            // 小说改编小说化表：纯封面孤儿组并入上一组
            if (in_array('author',$sig,true)) {
                $merged=[];
                foreach ($groups as $g) {
                    $allCover = array_every($g,fn($b)=>hbt2_kind($b)==='img');
                    if ($allCover && $merged) {
                        $merged[array_key_last($merged)]=array_merge($merged[array_key_last($merged)],$g);
                    } else $merged[]=$g;
                }
                $groups=$merged;
            }
            $res=hbt2_build_positional($keys,$rawHdr,$groups);
        } elseif ($pick==='winter') {
            $res=hbt2_build_winter($groups,$rawHdr);
        } elseif ($pick==='awards') {
            // 每组应为 3 块：年 / ul / 结果；表头沿用源原文
            $rows=[]; $ok=true;
            foreach ($groups as $g) {
                if (count($g)!==3 || hbt2_kind($g[0])!=='num' || hbt2_kind($g[1])!=='list') { $ok=false; break; }
                $rows[]=$g;
            }
            $res=$ok?[count($rawHdr)===3?$rawHdr:['Year','For work on','Result'],$rows]:null;
        }
        if ($res===null) continue;
        [$heads,$rows]=$res;
        if (!$rows) continue;
        $heads=hbt2_localize_heads($heads,$keys,$rawHdr);
        $html=hbt2_table($heads,$rows);

        // 替换区间：表头块上边界 .. </tbody> 行
        $start=$t['hstart'];
        // 表头上可能贴了 <sup> 注释等，按 find 时的 j 边界更安全：
        $j=$t['idx']-1;
        while ($j>=0 && trim($lines[$j])==='') $j--;
        $hc=count($t['hdr']);
        $j2=$j;
        for ($q=0;$q<$hc-1 && $j2>0;$q++) $j2--;   // 近似；下方用 start
        $built[]=['start'=>$start,'close'=>$t['end'],'html'=>$html];
    }
    if (!$built) return $raw;
    if (!empty($GLOBALS['HBV2_DEBUG'])) $GLOBALS['HBV2_DEBUG_ITEMS']=$built;
    // 从后往前替换
    usort($built,fn($a,$b)=>$b['start']<=>$a['start']);
    foreach ($built as $bu) {
        // 替换区间必须吞掉独立的 </tbody> 关闭行（find_tables 的 end 指向该行但不含它）
        $len=$bu['close']-$bu['start'];
        if (isset($lines[$bu['close']]) && trim($lines[$bu['close']])==='</tbody>') $len++;
        array_splice($lines,$bu['start'],$len,['','@@HBV2@@','']);
        $GLOBALS['__hbV2Built'][]=$bu['html'];
    }
    $out=implode("\n",$lines);
    $gi=0;
    $out=preg_replace_callback('/@@HBV2@@/',function() use (&$gi){
        return $GLOBALS['__hbV2Built'][$gi++];
    },$out);
    return $out;
}

/* 严格 N 块组的扁平表（深冬） */
function hbt2_build_simple4(array $heads, array $groups, int $C): ?array {
    $rows=[];
    foreach ($groups as $g) {
        if (count($g)!==$C) return null;
        $rows[]=$g;
    }
    return [$heads,$rows];
}
