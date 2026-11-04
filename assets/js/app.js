// 18104 viewer — collapsible sidebar groups + mobile drawer + TOC scrollspy
(function () {
  // ---- sidebar accordion ----
  var folds = Array.prototype.slice.call(document.querySelectorAll('.nav-fold'));
  function setOpen(fold, on) {
    fold.classList.toggle('open', on);
    var head = fold.querySelector('.nav-fold-head');
    if (head) head.setAttribute('aria-expanded', on ? 'true' : 'false');
  }
  folds.forEach(function (fold) {
    var head = fold.querySelector('.nav-fold-head');
    if (!head) return;
    head.addEventListener('click', function () {
      var willOpen = !fold.classList.contains('open');
      setOpen(fold, willOpen); // 允许同时展开多组，不强制互斥
    });
  });
  // ---- second-level subgroup accordion ----
  document.querySelectorAll('.nav-subfold').forEach(function (sf) {
    var sh = sf.querySelector('.nav-subhead');
    if (!sh) return;
    sh.addEventListener('click', function () {
      var on = !sf.classList.contains('open');
      sf.classList.toggle('open', on);
      sh.setAttribute('aria-expanded', on ? 'true' : 'false');
    });
  });
  // 进入页面时把含当前条目的组滚到可见位置
  var activeFold = document.querySelector('.nav-fold.open');
  if (activeFold && activeFold.scrollIntoView) {
    try { activeFold.scrollIntoView({ block: 'nearest' }); } catch (e) {}
  }

  // ---- mobile drawer ----
  var fab = document.getElementById('menuFab');
  var sidebar = document.getElementById('sidebar');
  if (fab && sidebar) {
    fab.addEventListener('click', function () { sidebar.classList.toggle('open'); });
    document.addEventListener('click', function (ev) {
      if (window.innerWidth > 860) return;
      if (sidebar.classList.contains('open') && !sidebar.contains(ev.target) && ev.target !== fab)
        sidebar.classList.remove('open');
    });
  }

  // Whole page scrolls (body). Sidebar/rail are sticky.
  var scroller = window;
  var links = Array.prototype.slice.call(document.querySelectorAll('.rail-toc a'));
  var internal = links.filter(function (a) { return (a.getAttribute('href') || '').indexOf('#') === 0; });
  if (internal.length) {
    var heads = internal.map(function (a) {
      return document.getElementById(a.getAttribute('href').slice(1));
    }).filter(Boolean);

    var onScroll = function () {
      var current = null;
      heads.forEach(function (h) {
        var r = h.getBoundingClientRect();
        if (r.top <= 120) current = h.id;
      });
      internal.forEach(function (a) {
        var on = a.getAttribute('href').slice(1) === current;
        a.classList.toggle('cur', on);
      });
    };
    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', onScroll);
    onScroll();

    internal.forEach(function (a) {
      a.addEventListener('click', function (ev) {
        var h = document.getElementById(a.getAttribute('href').slice(1));
        if (h) {
          ev.preventDefault();
          var y = h.getBoundingClientRect().top + window.scrollY - 72;
          window.scrollTo({ top: y, behavior: 'smooth' });
        }
      });
    });
  }
})();

/* ---- Gallery lightbox: 点击画廊图片放大；支持遮罩/ESC关闭、←→切换、新标签看原图 ---- */
(function () {
  var box = document.createElement('div');
  box.className = 'hb-lightbox';
  box.setAttribute('role', 'dialog');
  box.setAttribute('aria-modal', 'true');
  box.innerHTML =
    '<div class="hbl-stage">' +
      '<button class="hbl-close" type="button" aria-label="关闭">&times;</button>' +
      '<button class="hbl-nav hbl-prev" type="button" aria-label="上一张">&#8249;</button>' +
      '<img class="hbl-img" alt="">' +
      '<button class="hbl-nav hbl-next" type="button" aria-label="下一张">&#8250;</button>' +
    '</div>' +
    '<div class="hbl-bar">' +
      '<span class="hbl-cap"></span>' +
      '<a class="hbl-open" target="_blank" rel="noopener">新标签打开原图</a>' +
    '</div>';
  document.body.appendChild(box);
  var bigImg = box.querySelector('.hbl-img'),
      capEl  = box.querySelector('.hbl-cap'),
      openEl = box.querySelector('.hbl-open');
  var imgs = [], idx = 0;

  function collect() {
    imgs = Array.prototype.slice.call(document.querySelectorAll('.hb-gallery .hbg-item img, table.hb-utable img.hb-cover'));
  }
  function show(i) {
    if (!imgs.length) return;
    idx = (i + imgs.length) % imgs.length;
    var im = imgs[idx];
    var full = im.getAttribute('data-full') || im.getAttribute('src');
    bigImg.src = full;
    bigImg.alt = im.alt || '';
    openEl.href = full;
    var cap = im.parentNode.querySelector('figcaption');
    capEl.textContent = cap ? cap.textContent : (im.alt || '');
    box.classList.add('open');
    document.documentElement.classList.add('hbl-lock');
  }
  function hide() {
    box.classList.remove('open');
    document.documentElement.classList.remove('hbl-lock');
    bigImg.src = '';
  }
  document.addEventListener('click', function (e) {
    var im = e.target.closest && e.target.closest('.hb-gallery .hbg-item img, table.hb-utable img.hb-cover');
    if (im) {
      e.preventDefault();
      collect();
      show(imgs.indexOf(im));
      return;
    }
    if (box.classList.contains('open')) {
      if (e.target === box || e.target.closest('.hbl-close') || e.target.classList.contains('hbl-stage')) hide();
      else if (e.target.closest('.hbl-prev')) show(idx - 1);
      else if (e.target.closest('.hbl-next')) show(idx + 1);
    }
  });
  document.addEventListener('keydown', function (e) {
    if (!box.classList.contains('open')) return;
    if (e.key === 'Escape') hide();
    else if (e.key === 'ArrowLeft') show(idx - 1);
    else if (e.key === 'ArrowRight') show(idx + 1);
  });
})();
