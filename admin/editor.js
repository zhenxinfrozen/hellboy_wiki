/* Hellboy Wiki 后台结构化条目编辑器
 * 状态模型 meta = {type,type_zh,image,cats:[],rows:[{label,lines}],rows_en:[{label,lines}]}
 * lines = 段落模型 [[{t:'text'|'link',...}]]，保存时 serializeLines 转回 <a>/<br>
 * 落盘：正文仍是标准 Markdown；属性卡片序列化为现有 meta/*.json 结构（前台零改动）。
 */
(function () {
  'use strict';
  const D = EE;                       // PHP 注入的引导数据
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

  // ---------- 属性值：存储HTML ↔ 可视化标签编辑器 ----------
  // 存储：<a href="/entry/slug">名</a>，多值用 <br>
  // 编辑：contenteditable，链接=蓝色标签 chip，多值=换行。用户全程看不到任何代码。
  let _slugByDisplay = null;
  function buildEntryMaps() {
    if (_slugByDisplay) return;
    _slugByDisplay = new Map();
    (D.entries || []).forEach(e => {
      const sg = slugify(String(e.s));
      if (sg) {
        if (!_slugByDisplay.has(sg)) _slugByDisplay.set(sg, sg);
        if (!_slugByDisplay.has(sg.replace(/_/g, ' '))) _slugByDisplay.set(sg.replace(/_/g, ' '), sg);
      }
    });
  }
  // 存储 HTML → 段落模型：[[ {t:'text',v} | {t:'link',slug,text} , ... ], line2... ]
  function parseStored(value) {
    let v = String(value ?? '');
    if (!v) return [[]];
    buildEntryMaps();
    v = v.replace(/<br\s*\/?>/gi, '\n');
    const lines = v.split('\n');
    const decodeEnt = s => s.replace(/&lt;/g,'<').replace(/&gt;/g,'>').replace(/&quot;/g,'"').replace(/&#0?39;/g,"'").replace(/&amp;/g,'&');
    return lines.map(line => {
      const segs = [];
      const re = /<a\s+href="(\/entry\/[^"]*)">([\s\S]*?)<\/a>/gi;
      let last = 0, m;
      while ((m = re.exec(line))) {
        if (m.index > last) segs.push({ t: 'text', v: decodeEnt(line.slice(last, m.index)) });
        const raw = decodeURIComponent(m[1].replace(/^\/entry\//, ''));
        const slug = _slugByDisplay.get(raw) || _slugByDisplay.get(raw.replace(/\s+/g,'_')) || raw.replace(/\s+/g,'_');
        segs.push({ t: 'link', slug, text: decodeEnt(m[2]) });
        last = m.index + m[0].length;
      }
      if (last < line.length) segs.push({ t: 'text', v: decodeEnt(line.slice(last)) });
      return segs;
    });
  }
  // 段落模型 → 存储 HTML
  function serializeLines(lines) {
    buildEntryMaps();
    const escA = s => s.replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    const escT = s => s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    const out = lines.map(segs => segs.map(s => {
      if (s.t === 'link') return `<a href="/entry/${escA(String(s.slug).replace(/\s+/g,'_'))}">${escT(s.text)}</a>`;
      return escT(s.v);
    }).join('').replace(/^[\s\u00A0]+|[\s\u00A0]+$/g, ''))
    return out.filter(s => s !== '').join('<br>');
  }

  // ---------- 编辑态状态 ----------
  const initial = D.meta && D.meta.type ? D.meta : { type: 'Other', type_zh: '', image: '', cats: [], rows: [] };
  const meta = {
    type: initial.type || 'Other',
    type_zh: initial.type_zh || (D.types[initial.type] || ''),
    image: initial.image || '',
    cats: Array.isArray(initial.cats) ? initial.cats.slice() : [],
    rows: (initial.rows || []).map(r => ({ label: r.label || '', lines: parseStored(r.value || '') })),
    rows_en: (initial.rows_en || []).map(r => ({ label: r.label || '', lines: parseStored(r.value || '') })),
  };

  /* ================= 类型选择 + 常用字段 ================= */
  const fType = $('#fType');
  Object.entries(D.types).forEach(([k, zh]) => {
    const o = document.createElement('option');
    o.value = k; o.textContent = `${zh}（${k}）`;
    fType.appendChild(o);
  });
  fType.value = meta.type;
  fType.addEventListener('change', () => {
    meta.type = fType.value;
    meta.type_zh = D.types[fType.value] || fType.value;
    renderAddFields();
  });

  const addField = $('#addField');
  function renderAddFields() {
    const used = new Set(meta.rows.map(r => r.label));
    addField.innerHTML = '<option value="">＋ 添加常用属性…</option>';
    (D.fields[meta.type] || []).forEach(lb => {
      if (used.has(lb)) return;
      const o = document.createElement('option');
      o.value = lb; o.textContent = lb;
      addField.appendChild(o);
    });
  }
  addField.addEventListener('change', () => {
    if (addField.value) { meta.rows.push({ label: addField.value, lines: [[]] }); addField.value = ''; renderRows(); }
  });
  $('#addCustom').addEventListener('click', () => {
    const lb = prompt('自定义属性名（中文标签）：');
    if (lb && lb.trim()) { meta.rows.push({ label: lb.trim(), lines: [[]] }); renderRows(); }
  });

  const LINK_SVG = '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>';

  /* ============ 属性值：可视化标签编辑器（chip editor） ============ */
  // 链接渲染为蓝色胶囊，普通文字直接打，回车换行=多值；底层仍存取 <a>/<br>，前台零改动。
  const rowsBox = $('#metaRows');
  const enBox = $('#metaRowsEn');

  function makeChip(slug, text) {
    const c = document.createElement('span');
    c.className = 'mv-chip'; c.contentEditable = 'false';
    c.dataset.slug = slug; c.dataset.text = text;
    const t = document.createElement('span'); t.className = 'mv-chip-tx'; t.textContent = text;
    const x = document.createElement('span'); x.className = 'mv-chip-x'; x.textContent = '×';
    x.addEventListener('mousedown', e => e.preventDefault());
    x.addEventListener('click', e => {
      e.stopPropagation();
      const ed = c.closest('.mv-edit');
      c.remove();
      if (ed) { updateEmpty(ed); ed.dispatchEvent(new Event('input', { bubbles: true })); }
    });
    c.appendChild(t); c.appendChild(x);
    return c;
  }
  function renderChipDom(ed, lines) {
    ed.innerHTML = '';
    (lines.length ? lines : [[]]).forEach(segs => {
      const block = document.createElement('div'); block.className = 'mv-line';
      if (!segs.length) block.appendChild(document.createElement('br'));
      segs.forEach(s => block.appendChild(s.t === 'link' ? makeChip(s.slug, s.text) : document.createTextNode(s.v)));
      ed.appendChild(block);
    });
    updateEmpty(ed);
  }
  function readChipDom(ed) {
    const blocks = ed.querySelectorAll(':scope > div');
    const list = blocks.length ? Array.from(blocks) : [ed];
    const lines = list.map(block => {
      const segs = [];
      (function walk(n) {
        n.childNodes.forEach(cn => {
          if (cn.nodeType === 3) { if (cn.textContent) segs.push({ t: 'text', v: cn.textContent }); }
          else if (cn.nodeType === 1) {
            if (cn.classList && cn.classList.contains('mv-chip')) segs.push({ t: 'link', slug: cn.dataset.slug, text: cn.dataset.text });
            else if (cn.tagName !== 'BR') walk(cn);
          }
        });
      })(block);
      return segs;
    });
    updateEmpty(ed);
    return lines;
  }
  function updateEmpty(ed) {
    ed.classList.toggle('is-empty', !ed.textContent.trim() && !ed.querySelector('.mv-chip'));
  }
  function insertChipAtCursor(ed, slug, text) {
    ed.focus();
    const sel = window.getSelection();
    let range;
    if (sel.rangeCount && ed.contains(sel.anchorNode)) range = sel.getRangeAt(0);
    else { range = document.createRange(); range.selectNodeContents(ed); range.collapse(false); }
    const chip = makeChip(slug, text);
    range.insertNode(chip);
    const sp = document.createTextNode('\u00A0');
    chip.after(sp);
    range.setStartAfter(sp); range.collapse(true);
    sel.removeAllRanges(); sel.addRange(range);
    ed.dispatchEvent(new Event('input', { bubbles: true }));
  }

  function rowShell(lab, phVal) {
    const div = document.createElement('div');
    div.className = 'meta-row';
    div.innerHTML =
      `<input list="rowLabelList" value="${esc(lab)}" placeholder="属性" data-k="label">
       <div class="mr-val">
         <div class="mv-edit" contenteditable="true" data-ph="${esc(phVal)}" spellcheck="false"></div>
         <button type="button" class="mr-link" title="插入站内条目链接">${LINK_SVG}</button>
       </div>
       <button type="button" class="x" title="删除">×</button>
       <datalist id="rowLabelList"></datalist>`;
    return div;
  }
  function wireRow(div, r, onChangeLabel) {
    const labIn = div.querySelector('input[data-k=label]');
    const ed = div.querySelector('.mv-edit');
    const linkBtn = div.querySelector('.mr-link');
    const xBtn = div.querySelector('.x');
    const dl = div.querySelector('datalist');
    (D.fields[meta.type] || []).forEach(lb => { const o = document.createElement('option'); o.value = lb; dl.appendChild(o); });
    renderChipDom(ed, r.lines);
    ed.addEventListener('input', () => { r.lines = readChipDom(ed); });
    labIn.addEventListener('input', () => { r.label = labIn.value; if (onChangeLabel) onChangeLabel(); });
    linkBtn.addEventListener('mousedown', e => e.preventDefault());
    linkBtn.addEventListener('click', () => openLinkPop(linkBtn, (slug, label) => insertChipAtCursor(ed, slug, label)));
    return xBtn;
  }
  function renderRows() {
    rowsBox.innerHTML = '';
    meta.rows.forEach((r, i) => {
      const div = rowShell(r.label, '直接输入文字；点右侧链接图标插站内条目；回车可换多值');
      const xBtn = wireRow(div, r, renderAddFields);
      xBtn.addEventListener('click', () => { meta.rows.splice(i, 1); renderRows(); });
      rowsBox.appendChild(div);
    });
    renderAddFields();
  }
  function renderRowsEn() {
    enBox.innerHTML = '';
    meta.rows_en.forEach((r, i) => {
      const div = rowShell(r.label, 'EN value, one per line, link icon to insert');
      const xBtn = wireRow(div, r, null);
      xBtn.addEventListener('click', () => { meta.rows_en.splice(i, 1); renderRowsEn(); });
      enBox.appendChild(div);
    });
  }
  $('#syncEn').addEventListener('click', () => {
    // 同步结构：深拷贝中文行的链接/文本模型，label 留空待填英文标签
    meta.rows_en = meta.rows.map(r => ({ label: '', lines: JSON.parse(JSON.stringify(r.lines)) }));
    renderRowsEn();
  });

  /* ================= 特色图 ================= */
  const coverPrev = $('#coverPrev'), coverImg = $('#coverImg'), coverName = $('#coverName');
  const coverDrop = $('#coverDrop'), coverFile = $('#coverFile'), coverStatus = $('#coverStatus');
  function imgUrl(name) { return D.imgBase + encodeURIComponent(name).replace(/%2F/g, '/'); }
  function refreshCover() {
    if (meta.image) {
      coverPrev.hidden = false;
      coverImg.src = imgUrl(meta.image);
      coverName.textContent = meta.image;
      coverName.title = meta.image;
      coverDrop.hidden = true;
    } else {
      coverPrev.hidden = true;
      coverDrop.hidden = false;
    }
  }
  $('#coverClear').addEventListener('click', () => { meta.image = ''; refreshCover(); });
  // 点卡片 / “更换” / 空态 → 打开已含「上传 + 媒体库」双 Tab 的弹窗
  $('#coverCard').addEventListener('click', () => openImageModal('cover'));
  $('#coverChange').addEventListener('click', () => openImageModal('cover'));
  coverDrop.addEventListener('click', () => openImageModal('cover'));

  /* ---- 通用上传（statusEl 为进度提示元素） ---- */
  function setStatus(el, on, msg) {
    if (!el) return;
    if (on) { el.hidden = false; el.textContent = msg || '上传中…'; }
    else { el.hidden = true; el.textContent = ''; }
  }
  async function uploadImage(file, statusEl) {
    if (!file) return null;
    if (!/^image\/(jpeg|png|gif|webp)$/i.test(file.type)) { alert('仅支持 JPG / PNG / GIF / WEBP 图片。'); return null; }
    if (file.size > 8 * 1024 * 1024) { alert('图片不能超过 8MB。'); return null; }
    const fd = new FormData();
    fd.append('csrf', D.csrf);
    fd.append('file', file);
    setStatus(statusEl, true, '上传中… ' + file.name);
    try {
      const res = await fetch(D.upUrl, { method: 'POST', body: fd, credentials: 'same-origin' });
      const data = await res.json().catch(() => ({ ok: false, error: '服务器返回了无法解析的响应' }));
      if (!res.ok || !data.ok) { alert('上传失败：' + (data.error || ('HTTP ' + res.status))); return null; }
      if (!D.images.includes(data.name)) D.images.unshift(data.name);   // 立即进入媒体库缓存
      return data;
    } catch (e) {
      alert('上传出错：' + e.message);
      return null;
    } finally {
      setStatus(statusEl, false);
    }
  }
  async function uploadAsCover(file) {
    const data = await uploadImage(file, coverStatus);
    if (data && data.name) { meta.image = data.name; refreshCover(); }
  }
  coverFile.addEventListener('change', () => { const f = coverFile.files[0]; if (f) uploadAsCover(f); coverFile.value = ''; });
  // 空态：拖拽直接设为特色图（点击已在上面绑定为开弹窗）
  ['dragenter', 'dragover'].forEach(ev => coverDrop.addEventListener(ev, (e) => {
    e.preventDefault(); e.stopPropagation(); coverDrop.classList.add('drag');
  }));
  ['dragleave', 'dragend'].forEach(ev => coverDrop.addEventListener(ev, (e) => {
    e.preventDefault(); e.stopPropagation(); coverDrop.classList.remove('drag');
  }));
  coverDrop.addEventListener('drop', (e) => {
    e.preventDefault(); e.stopPropagation(); coverDrop.classList.remove('drag');
    const f = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
    if (f) uploadAsCover(f);
  });

  /* ================= 分类 chips ================= */
  const catChips = $('#catChips'), catInput = $('#catInput'), catList = $('#catList');
  D.cats.forEach(c => { const o = document.createElement('option'); o.value = c; catList.appendChild(o); });
  function renderCats() {
    catChips.innerHTML = '';
    meta.cats.forEach((c, i) => {
      const chip = document.createElement('span');
      chip.className = 'cat-chip';
      chip.innerHTML = `${esc(c)}<button type="button" title="移除">×</button>`;
      chip.querySelector('button').addEventListener('click', () => { meta.cats.splice(i, 1); renderCats(); });
      catChips.appendChild(chip);
    });
  }
  catInput.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' || e.key === ',') {
      e.preventDefault();
      const v = catInput.value.trim().replace(/,$/, '');
      if (v && !meta.cats.includes(v)) { meta.cats.push(v); renderCats(); }
      catInput.value = '';
    }
  });
  catInput.addEventListener('change', () => {
    const v = catInput.value.trim();
    if (v && !meta.cats.includes(v)) { meta.cats.push(v); renderCats(); }
    catInput.value = '';
  });

  /* ================= 站内条目内链弹层（仅条目） ================= */
  const pop = $('#linkPop'), lpQuery = $('#lpQuery'), lpList = $('#lpList');
  let popCallback = null;
  function openPop(anchorEl, cb) {
    popCallback = cb;
    pop.classList.add('open');
    const r = anchorEl.getBoundingClientRect();
    pop.style.left = Math.min(r.left, window.innerWidth - 360) + 'px';
    pop.style.top = (r.bottom + window.scrollY + 6) + 'px';
    lpQuery.value = ''; fillPop(''); lpQuery.focus();
  }
  function openLinkPop(anchorEl, cb) { openPop(anchorEl, cb); }
  function fillPop(q) {
    q = q.trim().toLowerCase();
    const items = D.entries
      .filter(e => !q || String(e.t).toLowerCase().includes(q) || String(e.s).toLowerCase().includes(q))
      .slice(0, 60)
      .map(e => ({ primary: e.t, secondary: e.s === e.t ? '' : e.s, slug: slugify(e.s) }));
    lpList.innerHTML = '';
    if (!items.length) { lpList.innerHTML = '<div class="lp-empty">无匹配</div>'; return; }
    items.forEach((it) => {
      const div = document.createElement('div');
      div.className = 'lp-item';
      div.innerHTML = `${esc(it.primary)}${it.secondary ? `<span class="en">${esc(it.secondary)}</span>` : ''}`;
      div.addEventListener('mousedown', (e) => { e.preventDefault(); });
      div.addEventListener('click', () => { const cb = popCallback; closePop(); if (cb) cb(it.slug, it.primary); });
      lpList.appendChild(div);
    });
  }
  function closePop() { pop.classList.remove('open'); popCallback = null; }
  lpQuery.addEventListener('input', () => fillPop(lpQuery.value));
  document.addEventListener('mousedown', (e) => {
    if (pop.classList.contains('open') && !pop.contains(e.target)) closePop();
  });

  /* ================= 图片弹窗：上传 / 媒体库 两套独立逻辑 ================= */
  const modal = $('#imgModal'), mTabs = $$('.m-tab'), paneUp = $('#paneUpload'), paneLib = $('#paneLibrary');
  const mDrop = $('#mDrop'), mFile = $('#mFile'), mProgress = $('#mProgress');
  const mUpResult = $('#mUpResult'), mUpImg = $('#mUpImg'), mUpName = $('#mUpName'), mUpMeta = $('#mUpMeta');
  const mGrid = $('#mGrid'), mSearch = $('#mSearch'), mCount = $('#mCount'), mMore = $('#mMore');
  const mConfirm = $('#mConfirm'), mCancel = $('#mCancel'), mSelInfo = $('#mSelInfo'), mTitle = $('#imgModalTitle');
  let modalPurpose = 'body';          // 'body' 插入正文 | 'cover' 设为特色图
  let modalTab = 'upload';
  let libSelected = null;             // 媒体库当前选中文件名
  let libShown = 0; const LIB_PAGE = 60;

  function switchTab(tab) {
    modalTab = tab;
    mTabs.forEach(b => b.classList.toggle('on', b.dataset.tab === tab));
    paneUp.hidden = (tab !== 'upload');
    paneLib.hidden = (tab !== 'library');
    if (tab === 'library') { renderLibrary(mSearch.value); mSearch.focus(); }
    else mFile.value = '';
  }
  function resetUploadPane() {
    mUpResult.hidden = true; mUpImg.removeAttribute('src'); mUpName.textContent = ''; mUpMeta.textContent = '';
    mProgress.hidden = true; mFile.value = '';
  }
  function openImageModal(purpose) {
    modalPurpose = purpose;
    mTitle.textContent = purpose === 'cover' ? '设置特色图' : '插入图片';
    mConfirm.textContent = purpose === 'cover' ? '设为特色图' : '插入到正文';
    resetUploadPane();
    libSelected = null; mSearch.value = '';
    updateConfirmState();
    switchTab('upload');
    modal.hidden = false; document.body.classList.add('modal-open');
  }
  function closeImageModal() { modal.hidden = true; document.body.classList.remove('modal-open'); }
  function updateConfirmState() {
    // 确认按钮：上传页有待插入新图，或媒体库选中了图
    const upReady = !mUpResult.hidden && mUpName.dataset.name;
    const ready = (modalTab === 'upload') ? !!upReady : !!libSelected;
    mConfirm.disabled = !ready;
    if (ready) mSelInfo.textContent = (modalTab === 'upload' ? '已上传：' : '已选择：') + ((modalTab === 'upload') ? mUpName.dataset.name : libSelected);
    else mSelInfo.textContent = '';
  }
  function confirmImage() {
    let name = null;
    if (modalTab === 'upload') name = mUpName.dataset.name || null;
    else name = libSelected;
    if (!name) return;
    closeImageModal();
    if (modalPurpose === 'cover') {
      meta.image = name; refreshCover();
    } else {
      body.focus();
      insertAtCursor(body, `![$SEL$](${D.imgBase}${encodeURIComponent(name).replace(/%2F/g,'/')})`);
    }
  }

  /* --- 上传页：拖拽 + 点击 --- */
  mDrop.addEventListener('click', () => mFile.click());
  mFile.addEventListener('change', async () => {
    const f = mFile.files[0];
    if (f) {
      const data = await uploadImage(f, mProgress);
      if (data) {
        mUpResult.hidden = false;
        mUpImg.src = data.url + '?t=' + Date.now();
        mUpName.textContent = data.name; mUpName.dataset.name = data.name;
        mUpMeta.textContent = `${data.w}×${data.h}px · ${(data.size/1024).toFixed(1)} KB`;
        updateConfirmState();
      }
    }
    mFile.value = '';
  });
  ['dragenter','dragover'].forEach(ev => mDrop.addEventListener(ev, e => { e.preventDefault(); mDrop.classList.add('drag'); }));
  ['dragleave','dragend'].forEach(ev => mDrop.addEventListener(ev, e => { e.preventDefault(); mDrop.classList.remove('drag'); }));
  mDrop.addEventListener('drop', async (e) => {
    e.preventDefault(); mDrop.classList.remove('drag');
    const f = e.dataTransfer.files && e.dataTransfer.files[0];
    if (f) {
      const data = await uploadImage(f, mProgress);
      if (data) {
        mUpResult.hidden = false;
        mUpImg.src = data.url + '?t=' + Date.now();
        mUpName.textContent = data.name; mUpName.dataset.name = data.name;
        mUpMeta.textContent = `${data.w}×${data.h}px · ${(data.size/1024).toFixed(1)} KB`;
        updateConfirmState();
      }
    }
  });

  /* --- 媒体库页：缩略图网格（懒加载） --- */
  let libFiltered = [];
  function renderLibrary(q) {
    q = (q || '').trim().toLowerCase();
    libFiltered = D.images.filter(n => !q || String(n).toLowerCase().includes(q));
    libShown = 0; libSelected = null;
    mGrid.innerHTML = '';
    if (!libFiltered.length) { mGrid.innerHTML = '<div class="m-empty">没有匹配的图片，可切到「上传新图片」添加。</div>'; mCount.textContent=''; mMore.hidden=true; updateConfirmState(); return; }
    mCount.textContent = `共 ${libFiltered.length} 张`;
    appendLibraryChunk();
    updateConfirmState();
  }
  function appendLibraryChunk() {
    const slice = libFiltered.slice(libShown, libShown + LIB_PAGE);
    slice.forEach(name => {
      const cell = document.createElement('div');
      cell.className = 'm-cell' + (libSelected === name ? ' selected' : '');
      cell.title = name;
      const img = document.createElement('img');
      img.loading = 'lazy'; img.alt = '';
      img.src = imgUrl(name);
      const cap = document.createElement('div'); cap.className = 'm-cap'; cap.textContent = name;
      cell.appendChild(img); cell.appendChild(cap);
      cell.addEventListener('click', () => {
        libSelected = name;
        $$('.m-cell', mGrid).forEach(c => c.classList.toggle('selected', c.title === name));
        updateConfirmState();
      });
      cell.addEventListener('dblclick', () => { libSelected = name; updateConfirmState(); confirmImage(); });
      mGrid.appendChild(cell);
    });
    libShown += slice.length;
    mMore.hidden = (libShown >= libFiltered.length);
  }
  mMore.addEventListener('click', appendLibraryChunk);
  let libSearchTimer;
  mSearch.addEventListener('input', () => { clearTimeout(libSearchTimer); libSearchTimer = setTimeout(() => renderLibrary(mSearch.value), 180); });

  mTabs.forEach(b => b.addEventListener('click', () => switchTab(b.dataset.tab)));
  mConfirm.addEventListener('click', confirmImage);
  mCancel.addEventListener('click', closeImageModal);
  $('#imgModalClose').addEventListener('click', closeImageModal);
  modal.addEventListener('mousedown', (e) => { if (e.target === modal) closeImageModal(); });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !modal.hidden) closeImageModal(); });

  // 与前台 slug_url 一致：空格→下划线，保留 () : ' ; /，其余百分号编码
  function slugify(slug) {
    let s = slug.trim().replace(/ /g, '_');
    s = encodeURIComponent(s);
    return s.replace(/%28/g, '(').replace(/%29/g, ')').replace(/%3A/g, ':')
            .replace(/%27/g, "'").replace(/%3B/g, ';').replace(/%2F/g, '/');
  }

  /* ================= 正文工具栏 ================= */
  const body = $('#fBody');
  function insertAtCursor(el, text, selectLen) {
    const st = el.selectionStart, en = el.selectionEnd;
    const before = el.value.slice(0, st), sel = el.value.slice(st, en), after = el.value.slice(en);
    el.value = before + text.replace('$SEL$', sel) + after;
    const pos = st + text.indexOf('$SEL$') >= 0 ? st + text.indexOf('$SEL$') : st + text.length;
    el.focus();
    if (text.includes('$SEL$')) {
      const a = st + text.indexOf('$SEL$');
      el.setSelectionRange(a, a + (sel.length || (selectLen || 0)));
    } else {
      el.setSelectionRange(pos, pos);
    }
    updateStats();
  }
  function wrapLine(prefix) {
    const st = body.selectionStart, en = body.selectionEnd;
    const sol = body.value.lastIndexOf('\n', st - 1) + 1;
    const sel = body.value.slice(st, en) || '';
    body.value = body.value.slice(0, sol) + prefix + body.value.slice(sol);
    body.focus();
    const shift = prefix.length;
    body.setSelectionRange(st + shift, en + shift);
    updateStats();
  }
  $('#mdbar').addEventListener('click', (e) => {
    const btn = e.target.closest('button[data-act]');
    if (!btn) return;
    const act = btn.dataset.act;
    body.focus();
    switch (act) {
      case 'bold': insertAtCursor(body, '**$SEL$**', 2); break;
      case 'italic': insertAtCursor(body, '*$SEL$*', 1); break;
      case 'h2': wrapLine('## '); break;
      case 'h3': wrapLine('### '); break;
      case 'ul': wrapLine('- '); break;
      case 'quote': wrapLine('> '); break;
      case 'link': {
        const url = prompt('外部链接 URL：', 'https://');
        if (url) insertAtCursor(body, `[$SEL$](${url})`);
        break;
      }
      case 'ilink':
        openLinkPop(btn, (href, label) => {
          body.focus();
          insertAtCursor(body, `[${label}](/entry/${href})`);
        });
        break;
      case 'img':
        openImageModal('body');
        break;
    }
  });

  /* ================= 统计 + 轻量结构提示 ================= */
  const stChars = $('#stChars'), stLinks = $('#stLinks'), stImgs = $('#stImgs'), stHeads = $('#stHeads'), stWarn = $('#stWarn');
  function updateStats() {
    const v = body.value;
    const cjk = (v.match(/[一-鿿]/g) || []).length;
    stChars.textContent = cjk;
    stLinks.textContent = (v.match(/\]\(\/entry\//g) || []).length;
    stImgs.textContent = (v.match(/!\[/g) || []).length;
    stHeads.textContent = (v.match(/^#{2,4}\s/gm) || []).length;
    if (/@@(?:HBHTMLTBL|HBLINK|HBIMG)\d*@@|⟦[A-Z]+\d*⟧/.test(v)) {
      stWarn.innerHTML = '<span class="bad">⚠ 检测到占位符泄漏，请勿保存</span>';
    } else stWarn.textContent = '';
  }
  body.addEventListener('input', updateStats);

  /* ================= 版本层切换（带未保存提醒） ================= */
  $$('#layerTabs a').forEach(a => {
    a.addEventListener('click', () => {
      const t = a.dataset.layer;
      if (t === meta && false) {}
      const go = () => {
        const nt = $('#fTitle').value.trim();
        const q = new URLSearchParams({ title: nt, layer: t });
        location.href = 'entry_edit.php?' + q.toString() + (D.isNew ? '&new=1' : '');
      };
      if (body.value && !a.classList.contains('on')) {
        if (confirm('切换版本层将丢弃当前未保存的正文修改，确定继续？')) go();
      } else go();
    });
  });

  /* ================= 提交：序列化 meta_json ================= */
  $('#eeForm').addEventListener('submit', (e) => {
    // 触发预览时也带上最新 meta（预览不保存 meta，无妨）
    collectMeta();
  });
  function collectMeta() {
    // 以输入框实时值为准（rows 已双向绑定；image/cats 也已同步）
    const payload = {
      type: meta.type,
      type_zh: meta.type_zh || (D.types[meta.type] || ''),
      image: (meta.image || '').trim(),
      cats: meta.cats,
      rows: meta.rows.filter(r => r.label.trim() !== '').map(r => ({ label: r.label.trim(), value: serializeLines(r.lines) })),
      rows_en: meta.rows_en.filter(r => r.label.trim() !== '' || serializeLines(r.lines).trim() !== '').map(r => ({ label: r.label.trim(), value: serializeLines(r.lines) })),
    };
    $('#metaJson').value = JSON.stringify(payload);
  }

  /* ================= 初始化 ================= */
  renderRows(); renderRowsEn(); renderCats(); refreshCover(); updateStats();
})();
