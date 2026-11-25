/* =====================================================================
 * assets/js/app.js — สคริปต์กลางของระบบ MOU (v2)
 *  1) สลับธีมมืด/สว่าง          6) DataTables ภาษาไทย + ตัวนับตัวเลข
 *  2) Loading overlay           7) ยืนยันก่อนลบ (SweetAlert2)
 *  3) แสดง/ซ่อนรหัสผ่าน          8) สลับมุมมอง ตาราง/การ์ด, โหลดเพิ่ม, แชร์, พิมพ์
 *  4) Stagger + Reveal          9) Lightbox ดูรูปภาพ
 *  5) Navbar/Topbar เมื่อเลื่อน + ปุ่มกลับด้านบน   10) Dropzone อัปโหลดหลายรูปพร้อมพรีวิว
 *  v2.2 (Accessibility): แถบโหลดด้านบนแทน overlay, ข้อผิดพลาดใต้ช่อง (MouForm), toast (MouFlash),
 *       เตือนก่อนออกจากฟอร์มที่ยังไม่บันทึก, Lightbox คืน focus, หยุด marquee, ไอคอน aria-hidden
 * ===================================================================== */
window.DT_TH = {
  decimal: '', emptyTable: 'ไม่พบข้อมูล', info: 'แสดง _START_ ถึง _END_ จาก _TOTAL_ รายการ',
  infoEmpty: 'แสดง 0 ถึง 0 จาก 0 รายการ', infoFiltered: '(กรองจากทั้งหมด _MAX_ รายการ)',
  lengthMenu: 'แสดง _MENU_ รายการ', loadingRecords: 'กำลังโหลด...', processing: 'กำลังประมวลผล...',
  search: '', searchPlaceholder: 'ค้นหาในตาราง...', zeroRecords: 'ไม่พบรายการที่ตรงกับคำค้น',
  paginate: { first: 'หน้าแรก', last: 'หน้าสุดท้าย', next: '›', previous: '‹' },
  aria: { paginate: { first: 'หน้าแรก', last: 'หน้าสุดท้าย', next: 'หน้าถัดไป', previous: 'หน้าก่อนหน้า' },
          sortAscending: ': เรียงจากน้อยไปมาก', sortDescending: ': เรียงจากมากไปน้อย' }
};

(function () {
  'use strict';
  var root = document.documentElement;
  var cssVar = function (n) { return getComputedStyle(root).getPropertyValue(n).trim(); };
  var reduceMotion = function () { return !!(window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches); };
  window.MouReduceMotion = reduceMotion;

  /* ---------- 1) ธีมมืด/สว่าง ---------- */
  var Theme = window.MouTheme = {
    get: function () { return root.getAttribute('data-bs-theme') === 'dark' ? 'dark' : 'light'; },
    set: function (t) {
      root.classList.add('theme-anim');
      root.setAttribute('data-bs-theme', t);
      try { localStorage.setItem('mou-theme', t); } catch (e) {}
      document.querySelectorAll('.theme-toggle[data-theme-toggle]').forEach(function (b) { b.setAttribute('aria-pressed', t === 'dark'); });
      document.dispatchEvent(new CustomEvent('mou:themechange', { detail: { theme: t } }));
      setTimeout(function () { root.classList.remove('theme-anim'); }, 500);
    },
    toggle: function () { Theme.set(Theme.get() === 'dark' ? 'light' : 'dark'); },
    /** สีสำหรับ Chart.js ตามธีมปัจจุบัน */
    chart: function () {
      var dark = Theme.get() === 'dark';
      return { text: cssVar('--c-muted') || (dark ? '#93a2bf' : '#5f6b84'), grid: dark ? 'rgba(255,255,255,.07)' : 'rgba(16,30,64,.07)',
        // สีแท่ง/ชิ้นกราฟ ≥ 3:1 กับพื้น (WCAG 1.4.11) — ทองโทนเข้ม #a9841a บนพื้นขาว
        primary: dark ? '#5b8cf0' : '#0b2e6b', gold: dark ? '#ddb53c' : '#a9841a', surface: cssVar('--c-surface') || (dark ? '#0f1a2e' : '#fff'),
        palette: dark ? ['#5b8cf0', '#ddb53c', '#4fb3ff', '#3ccf8e', '#a685f0', '#ff8a5b'] : ['#0b2e6b', '#a9841a', '#2f7fd1', '#198754', '#8c5bd8', '#c4561f'] };
    }
  };
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-theme-toggle]');
    if (b) { e.preventDefault(); Theme.toggle(); }
  });
  // ทำตามระบบปฏิบัติการ ถ้าผู้ใช้ยังไม่เคยเลือกเอง
  if (window.matchMedia) {
    var mq = matchMedia('(prefers-color-scheme: dark)');
    var onSys = function (ev) { var saved = null; try { saved = localStorage.getItem('mou-theme'); } catch (e) {} if (!saved) { Theme.set(ev.matches ? 'dark' : 'light'); try { localStorage.removeItem('mou-theme'); } catch (e) {} } };
    if (mq.addEventListener) mq.addEventListener('change', onSys);
  }

  /* ---------- 2) แถบโหลดด้านบน (แทน overlay เต็มจอ) ----------
   * ไม่บังเนื้อหา และแสดงเฉพาะเมื่อรอเกิน 300 ms (หน้าโหลดเร็วจะไม่เห็นอะไรกระพริบ) */
  var Loader = window.MouLoader = {
    el: function () { return document.getElementById('pageLoader'); },
    hide: function () { clearTimeout(Loader._d); clearTimeout(Loader._t); var l = Loader.el(); if (l) l.classList.remove('is-active'); },
    show: function () {
      var l = Loader.el(); if (!l) return;
      clearTimeout(Loader._d); clearTimeout(Loader._t);
      Loader._d = setTimeout(function () { l.classList.add('is-active'); }, 300);
      Loader._t = setTimeout(Loader.hide, 10000);   // กันค้าง (เช่น ผู้ใช้กดยกเลิกการออกจากหน้า)
    }
  };
  // กลับมาด้วยปุ่ม Back (bfcache): ซ่อนแถบโหลดและคืนสถานะปุ่มที่กำลังส่ง
  window.addEventListener('pageshow', function (e) { if (e.persisted) { Loader.hide(); Busy.resetAll(); } });

  /** ปุ่มส่งฟอร์ม: แสดง spinner + aria-busy + ปิดชั่วคราว กันกดซ้ำ (ทำหลัง submit แล้ว ข้อมูลปุ่มจึงยังถูกส่งไป) */
  var Busy = window.MouBusy = {
    set: function (btn) {
      if (!btn || btn.dataset.busy) return;
      btn.dataset.busy = '1'; btn.dataset.html = btn.innerHTML;
      btn.setAttribute('aria-busy', 'true');
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>' + btn.dataset.html;
      setTimeout(function () { btn.disabled = true; }, 0);
    },
    reset: function (btn) {
      if (!btn || !btn.dataset.busy) return;
      btn.innerHTML = btn.dataset.html; btn.disabled = false; btn.removeAttribute('aria-busy');
      delete btn.dataset.busy; delete btn.dataset.html;
    },
    resetAll: function () { document.querySelectorAll('[data-busy]').forEach(Busy.reset); }
  };

  // แสดง overlay เมื่อคลิกลิงก์ไปหน้าอื่นภายในเว็บ (ยกเว้นลิงก์ดาวน์โหลด/แท็บใหม่/anchor)
  document.addEventListener('click', function (e) {
    var a = e.target.closest('a[href]');
    if (!a || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    var href = a.getAttribute('href');
    if (!href || href.charAt(0) === '#' || /^(mailto:|tel:|javascript:)/i.test(href)) return;
    if (a.target === '_blank' || a.hasAttribute('download') || a.hasAttribute('data-no-loader') || a.hasAttribute('data-bs-toggle') || a.hasAttribute('data-lightbox') || a.hasAttribute('data-lightbox-open')) return;
    if (/(export=|download|proposal_file|report_export|\.pdf$|\.docx?$|\.xlsx$|\.csv$)/i.test(href)) return;
    if (a.origin && a.origin !== location.origin) return;
    if (a.pathname === location.pathname && a.search === location.search && a.hash) return;
    Loader.show();
  });
  // ส่งฟอร์ม (ถ้าไม่มีสคริปต์อื่นยกเลิกการส่ง): แถบโหลด + ปุ่มที่กดแสดงสถานะกำลังบันทึก
  document.addEventListener('submit', function (e) {
    var f = e.target;
    var btn = e.submitter || f.querySelector('button[type=submit], button:not([type])');
    setTimeout(function () {
      if (e.defaultPrevented) return;
      Dirty.clear(f);
      if (f.hasAttribute('data-no-loader') || f.target === '_blank') return;
      Loader.show();
      if (f.method.toLowerCase() === 'post') Busy.set(btn);
    }, 0);
  });

  /* ---------- 2b) เตือนก่อนออกจากหน้าเมื่อฟอร์มยังไม่บันทึก (ฟอร์มที่มี data-dirty-guard) ---------- */
  var Dirty = window.MouDirty = {
    forms: [],
    mark: function (f) { if (f && f.hasAttribute('data-dirty-guard') && Dirty.forms.indexOf(f) < 0) Dirty.forms.push(f); },
    clear: function (f) { Dirty.forms = Dirty.forms.filter(function (x) { return x !== f; }); },
    any: function () { return Dirty.forms.length > 0; }
  };
  ['input', 'change'].forEach(function (ev) {
    document.addEventListener(ev, function (e) { var t = e.target; if (t && t.form && t.type !== 'search') Dirty.mark(t.form); }, true);
  });
  window.addEventListener('beforeunload', function (e) {
    if (!Dirty.any()) return;
    e.preventDefault(); e.returnValue = '';   // เบราว์เซอร์แสดงกล่อง "ออกจากหน้านี้หรือไม่"
    Loader.hide();
  });

  /* ---------- 2c) ข้อผิดพลาดของฟอร์ม: ข้อความใต้ช่อง + กล่องสรุปที่รับ focus (WCAG 3.3.1) ---------- */
  var MouForm = window.MouForm = {
    clear: function (form) {
      form.querySelectorAll('[data-js-error]').forEach(function (n) { n.remove(); });
      form.querySelectorAll('[data-js-invalid]').forEach(function (inp) {
        inp.classList.remove('is-invalid'); inp.removeAttribute('aria-invalid'); inp.removeAttribute('data-js-invalid');
        var d = (inp.getAttribute('aria-describedby') || '').split(' ').filter(function (x) { return x && !/-jserr$/.test(x); }).join(' ');
        if (d) inp.setAttribute('aria-describedby', d); else inp.removeAttribute('aria-describedby');
      });
      var old = document.getElementById('errorSummary'); if (old) old.remove();
    },
    /** errs = [[ชื่อช่อง, ข้อความ], ...] */
    showErrors: function (form, errs) {
      MouForm.clear(form);
      var esc = function (t) { var d = document.createElement('div'); d.textContent = t; return d.innerHTML; };
      var items = errs.map(function (er) {
        var inp = form.elements[er[0]]; if (inp && inp.length && !inp.tagName) inp = inp[0];
        if (inp && inp.id) {
          inp.classList.add('is-invalid'); inp.setAttribute('aria-invalid', 'true'); inp.setAttribute('data-js-invalid', '1');
          var msg = document.createElement('div');
          msg.className = 'invalid-feedback d-block'; msg.id = inp.id + '-jserr'; msg.setAttribute('data-js-error', ''); msg.textContent = er[1];
          var anchor = inp.closest('.input-group') || (inp.type === 'checkbox' ? inp.parentNode.querySelector('label') : null) || inp;
          var srv = document.getElementById(inp.id + '-err'); if (srv) srv.remove();
          anchor.insertAdjacentElement('afterend', msg);
          inp.setAttribute('aria-describedby', ((inp.getAttribute('aria-describedby') || '').replace(inp.id + '-err', '') + ' ' + msg.id).trim());
          return '<li><a href="#' + inp.id + '">' + esc(er[1]) + '</a></li>';
        }
        return '<li>' + esc(er[1]) + '</li>';
      });
      var box = document.createElement('div');
      box.className = 'alert alert-danger form-error-summary'; box.id = 'errorSummary';
      box.setAttribute('role', 'alert'); box.setAttribute('tabindex', '-1'); box.setAttribute('data-js-error', '');
      box.innerHTML = '<h2 class="h6 mb-2"><i class="bi bi-exclamation-octagon me-1" aria-hidden="true"></i>กรุณาแก้ไข ' + errs.length + ' รายการ</h2><ul class="mb-0">' + items.join('') + '</ul>';
      form.insertAdjacentElement('beforebegin', box);
      box.focus();
    }
  };
  // คลิกลิงก์ในกล่องสรุป → โฟกัสช่องนั้น (ไม่ใช่แค่เลื่อนหน้า)
  document.addEventListener('click', function (e) {
    var a = e.target.closest('.form-error-summary a[href^="#"]'); if (!a) return;
    var t = document.getElementById(a.getAttribute('href').slice(1)); if (!t) return;
    e.preventDefault(); t.focus(); if (t.scrollIntoView) t.scrollIntoView({ block: 'center', behavior: reduceMotion() ? 'auto' : 'smooth' });
  });
  // พิมพ์แก้ช่องที่ผิดแล้ว → เอาสถานะผิดออก
  document.addEventListener('input', function (e) {
    var t = e.target; if (!t || !t.classList || !t.classList.contains('is-invalid')) return;
    t.classList.remove('is-invalid'); t.removeAttribute('aria-invalid');
  });

  /* ---------- 2d) แจ้งผล: สำเร็จ/ข้อมูล = toast ปิดเอง, ผิดพลาด/เตือน = กล่องยืนยัน ---------- */
  window.MouFlash = function (type, msg) {
    if (!window.Swal) { return; }
    var icon = type === 'error' ? 'error' : (type === 'warning' ? 'warning' : (type === 'success' ? 'success' : 'info'));
    if (icon === 'success' || icon === 'info') {
      Swal.fire({ toast: true, position: 'top-end', icon: icon, titleText: msg, showConfirmButton: false, timer: 4500, timerProgressBar: !reduceMotion(),
        showCloseButton: true, closeButtonAriaLabel: 'ปิดข้อความ',
        didOpen: function (t) { t.addEventListener('mouseenter', Swal.stopTimer); t.addEventListener('mouseleave', Swal.resumeTimer);
          t.addEventListener('focusin', Swal.stopTimer); t.addEventListener('focusout', Swal.resumeTimer); } });
    } else {
      Swal.fire({ icon: icon, text: msg, confirmButtonText: 'ตกลง' });
    }
  };

  /* ---------- 3) แสดง/ซ่อนรหัสผ่าน (ทุกช่อง type=password อัตโนมัติ) ---------- */
  function enhancePassword(inp) {
    if (inp.dataset.pwReady || inp.hasAttribute('data-no-toggle')) return;
    inp.dataset.pwReady = '1';
    var group = inp.closest('.input-group');
    if (!group) {
      group = document.createElement('div'); group.className = 'input-group';
      inp.parentNode.insertBefore(group, inp); group.appendChild(inp);
    }
    var btn = document.createElement('button');
    btn.type = 'button'; btn.className = 'btn btn-outline-secondary pw-toggle'; btn.tabIndex = 0;
    btn.setAttribute('aria-label', 'แสดงรหัสผ่าน'); btn.title = 'แสดง/ซ่อนรหัสผ่าน';
    btn.innerHTML = '<i class="bi bi-eye"></i>';
    group.appendChild(btn);
    btn.addEventListener('click', function () {
      var show = inp.type === 'password';
      inp.type = show ? 'text' : 'password';
      btn.classList.toggle('is-on', show);
      btn.innerHTML = '<i class="bi ' + (show ? 'bi-eye-slash' : 'bi-eye') + '"></i>';
      btn.setAttribute('aria-label', show ? 'ซ่อนรหัสผ่าน' : 'แสดงรหัสผ่าน');
      inp.focus({ preventScroll: true });
      try { var l = inp.value.length; inp.setSelectionRange(l, l); } catch (er) {}
    });
    // เตือน Caps Lock
    var hint = document.createElement('div'); hint.className = 'caps-hint'; hint.innerHTML = '<i class="bi bi-capslock-fill"></i> Caps Lock เปิดอยู่';
    group.parentNode.insertBefore(hint, group.nextSibling);
    var caps = function (ev) { if (ev.getModifierState) hint.classList.toggle('show', ev.getModifierState('CapsLock')); };
    inp.addEventListener('keyup', caps); inp.addEventListener('keydown', caps);
    inp.addEventListener('blur', function () { hint.classList.remove('show'); });
    // ซ่อนกลับอัตโนมัติเมื่อส่งฟอร์ม (ไม่ให้เบราว์เซอร์จำเป็น text)
    if (inp.form) inp.form.addEventListener('submit', function () { inp.type = 'password'; });
  }

  function hideIcons(scope) {
    (scope || document).querySelectorAll('i.bi:not([aria-hidden])').forEach(function (i) { i.setAttribute('aria-hidden', 'true'); });
  }
  window.MouHideIcons = hideIcons;

  /* ---------- 4) Stagger + Reveal ---------- */
  function initStagger(scope) {
    (scope || document).querySelectorAll('.stagger').forEach(function (box) {
      Array.prototype.forEach.call(box.children, function (c, i) { c.style.setProperty('--i', Math.min(i, 14)); });
    });
  }
  function initReveal() {
    var els = document.querySelectorAll('.reveal');
    if (!('IntersectionObserver' in window)) { els.forEach(function (x) { x.classList.add('is-visible'); }); return; }
    var io = new IntersectionObserver(function (ents) {
      ents.forEach(function (en) { if (en.isIntersecting) { en.target.classList.add('is-visible'); io.unobserve(en.target); } });
    }, { threshold: .12, rootMargin: '0px 0px -40px 0px' });
    els.forEach(function (x) { io.observe(x); });
  }

  /* ---------- 9) Lightbox ---------- */
  var LB = {
    items: [], idx: 0, el: null,
    build: function () {
      if (LB.el) return;
      var d = document.createElement('div');
      d.className = 'lb'; d.setAttribute('role', 'dialog'); d.setAttribute('aria-modal', 'true'); d.setAttribute('aria-label', 'ดูรูปภาพ');
      d.innerHTML = '<span class="lb-count"></span>'
        + '<button type="button" class="lb-btn lb-close" aria-label="ปิด (Esc)"><i class="bi bi-x-lg" aria-hidden="true"></i></button>'
        + '<button type="button" class="lb-btn lb-prev" aria-label="รูปก่อนหน้า"><i class="bi bi-chevron-left" aria-hidden="true"></i></button>'
        + '<figure><img alt=""><figcaption aria-live="polite"></figcaption></figure>'
        + '<button type="button" class="lb-btn lb-next" aria-label="รูปถัดไป"><i class="bi bi-chevron-right" aria-hidden="true"></i></button>';
      document.body.appendChild(d); LB.el = d;
      d.addEventListener('click', function (e) {
        if (e.target.closest('.lb-close') || e.target === d) LB.close();
        else if (e.target.closest('.lb-prev')) LB.go(-1);
        else if (e.target.closest('.lb-next')) LB.go(1);
      });
      document.addEventListener('keydown', function (e) {
        if (!d.classList.contains('open')) return;
        if (e.key === 'Escape') LB.close(); else if (e.key === 'ArrowLeft') LB.go(-1); else if (e.key === 'ArrowRight') LB.go(1);
        else if (e.key === 'Tab') {   // สำรองสำหรับเบราว์เซอร์ที่ไม่รองรับ inert
          var btns = Array.prototype.filter.call(d.querySelectorAll('button'), function (b) { return b.offsetParent !== null; });
          var i = btns.indexOf(document.activeElement);
          if (e.shiftKey && i <= 0) { e.preventDefault(); btns[btns.length - 1].focus(); }
          else if (!e.shiftKey && i === btns.length - 1) { e.preventDefault(); btns[0].focus(); }
        }
      });
      var sx = null; // ปัดซ้าย/ขวาบนมือถือ
      d.addEventListener('touchstart', function (e) { sx = e.touches[0].clientX; }, { passive: true });
      d.addEventListener('touchend', function (e) { if (sx === null) return; var dx = e.changedTouches[0].clientX - sx; if (Math.abs(dx) > 50) LB.go(dx < 0 ? 1 : -1); sx = null; });
    },
    open: function (group, start) {
      LB.build();
      LB.items = Array.prototype.slice.call(document.querySelectorAll('[data-lightbox="' + group + '"]'));
      LB.idx = Math.max(0, LB.items.indexOf(start));
      LB.opener = document.activeElement;
      LB.show(); LB.el.classList.add('open'); document.body.style.overflow = 'hidden';
      // เนื้อหาหลังฉากกด/โฟกัสไม่ได้ระหว่างเปิด (Tab วนอยู่ใน Lightbox)
      LB.inerted = Array.prototype.filter.call(document.body.children, function (c) { return c !== LB.el && !c.hasAttribute('inert'); });
      LB.inerted.forEach(function (c) { c.setAttribute('inert', ''); });
      LB.el.querySelector('.lb-close').focus();
    },
    show: function () {
      var a = LB.items[LB.idx]; if (!a) return;
      var img = LB.el.querySelector('img'), n = LB.items.length;
      img.style.animation = 'none'; void img.offsetWidth; img.style.animation = '';
      img.src = a.getAttribute('href'); img.alt = a.getAttribute('data-caption') || '';
      LB.el.querySelector('figcaption').textContent = a.getAttribute('data-caption') || '';
      LB.el.querySelector('.lb-count').textContent = n > 1 ? 'รูปที่ ' + (LB.idx + 1) + ' / ' + n : '';
      LB.el.querySelector('.lb-prev').style.display = LB.el.querySelector('.lb-next').style.display = n > 1 ? '' : 'none';
    },
    go: function (d) { var n = LB.items.length; LB.idx = (LB.idx + d + n) % n; LB.show(); },
    close: function () {
      LB.el.classList.remove('open'); document.body.style.overflow = '';
      (LB.inerted || []).forEach(function (c) { c.removeAttribute('inert'); }); LB.inerted = null;
      if (LB.opener && LB.opener.focus) LB.opener.focus();   // คืน focus ไปที่รูปที่กดเปิด
    }
  };
  document.addEventListener('click', function (e) {
    var o = e.target.closest('[data-lightbox-open]');   // ปุ่ม/ภาพปกที่เปิดอัลบั้มจากรูปแรก
    if (o) { e.preventDefault(); var g = o.getAttribute('data-lightbox-open'); LB.open(g, document.querySelector('[data-lightbox="' + g + '"]')); return; }
    var a = e.target.closest('[data-lightbox]');
    if (!a) return;
    e.preventDefault(); LB.open(a.getAttribute('data-lightbox'), a);
  });

  /* ---------- 10) Dropzone อัปโหลดหลายรูปพร้อมพรีวิว ---------- */
  function initDropzone(dz) {
    var input = dz.querySelector('input[type=file]'); if (!input) return;
    var box = document.querySelector(dz.getAttribute('data-previews')) || dz.parentNode.querySelector('.dz-previews');
    var max = parseInt(dz.getAttribute('data-max') || '20', 10), maxMb = parseFloat(dz.getAttribute('data-max-mb') || '5');
    var maxTotalMb = parseFloat(dz.getAttribute('data-max-total-mb') || '0');   // ขนาดรวมต่อการส่ง 1 ครั้ง (post_max_size ของ PHP) — 0 = ไม่จำกัด
    var okType = /^image\/(jpeg|png|webp)$/;
    var counter = dz.querySelector('[data-dz-count]');
    var canDT = typeof DataTransfer !== 'undefined';
    var files = [];
    function sync() {
      if (canDT) { var dt = new DataTransfer(); files.forEach(function (f) { dt.items.add(f); }); input.files = dt.files; }
      if (counter) counter.textContent = files.length ? 'เลือกแล้ว ' + files.length + ' รูป' : '';
      render();
    }
    function render() {
      if (!box) return;
      box.querySelectorAll('img[data-blob]').forEach(function (im) { URL.revokeObjectURL(im.src); });
      box.innerHTML = '';
      files.forEach(function (f, i) {
        var t = document.createElement('div'); t.className = 'dz-thumb'; t.style.animationDelay = (i * 40) + 'ms';
        var img = document.createElement('img'); img.alt = f.name; img.setAttribute('data-blob', '');
        img.src = URL.createObjectURL(f); t.appendChild(img);   // object URL: ไม่ต้องอ่านไฟล์เป็น base64 ทุกครั้งที่วาดใหม่
        var sz = document.createElement('span'); sz.className = 'dz-size'; sz.textContent = (f.size / 1048576).toFixed(2) + ' MB'; t.appendChild(sz);
        if (canDT) {
          var x = document.createElement('button'); x.type = 'button'; x.className = 'dz-remove'; x.title = 'นำออก';
          x.setAttribute('aria-label', 'นำรูป ' + f.name + ' ออก'); x.innerHTML = '<i class="bi bi-x-lg" aria-hidden="true"></i>';
          x.addEventListener('click', function (ev) { ev.preventDefault(); ev.stopPropagation(); files.splice(i, 1); sync(); input.focus(); });
          t.appendChild(x);
        }
        box.appendChild(t);
      });
    }
    function add(list) {
      var bad = [];
      Array.prototype.forEach.call(list, function (f) {
        if (!okType.test(f.type)) { bad.push(f.name + ' (ชนิดไฟล์ไม่รองรับ)'); return; }
        if (f.size > maxMb * 1048576) { bad.push(f.name + ' (เกิน ' + maxMb + ' MB)'); return; }
        if (files.length >= max) { bad.push(f.name + ' (เกิน ' + max + ' รูป)'); return; }
        var total = files.reduce(function (s, o) { return s + o.size; }, 0);
        if (maxTotalMb && total + f.size > maxTotalMb * 1048576) { bad.push(f.name + ' (ขนาดรวมเกิน ' + maxTotalMb + ' MB ต่อการบันทึก 1 ครั้ง)'); return; }
        if (!files.some(function (o) { return o.name === f.name && o.size === f.size; })) files.push(f);
      });
      if (!canDT) { files = Array.prototype.slice.call(list).filter(function (f) { return okType.test(f.type); }); }
      sync();
      if (bad.length && window.Swal) {
        var ul = document.createElement('ul'); ul.className = 'text-start mb-0';
        bad.forEach(function (b) { var li = document.createElement('li'); li.textContent = b; ul.appendChild(li); });   // ชื่อไฟล์เป็นข้อความ ไม่ใช่ HTML
        Swal.fire({ icon: 'warning', title: 'บางไฟล์ไม่ถูกเพิ่ม', html: ul, confirmButtonText: 'ตกลง' });
      }
    }
    input.addEventListener('change', function () { add(input.files); });
    ['dragenter', 'dragover'].forEach(function (ev) { dz.addEventListener(ev, function (e) { e.preventDefault(); dz.classList.add('is-drag'); }); });
    ['dragleave', 'drop'].forEach(function (ev) { dz.addEventListener(ev, function (e) { e.preventDefault(); dz.classList.remove('is-drag'); }); });
    dz.addEventListener('drop', function (e) { if (e.dataTransfer && e.dataTransfer.files.length) add(e.dataTransfer.files); });
  }

  /* ---------- ตัวนับตัวเลข ---------- */
  function initCounters() {
    var counters = document.querySelectorAll('[data-count]');
    var fmt = function (n) { return n.toLocaleString('th-TH'); };
    // ตัวเลขจริงพิมพ์มาจาก PHP แล้ว — นับขึ้นเป็นลูกเล่นเฉพาะเมื่อผู้ใช้ไม่ได้ตั้งค่าลดการเคลื่อนไหว
    if (!counters.length || reduceMotion() || !('IntersectionObserver' in window)) return;
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (!en.isIntersecting) return;
        var el = en.target, target = parseInt(el.getAttribute('data-count'), 10) || 0, t0 = null, dur = 1400;
        function step(ts) {
          t0 = t0 || ts; var p = Math.min((ts - t0) / dur, 1);
          el.textContent = fmt(Math.floor(target * (1 - Math.pow(1 - p, 3))));
          if (p < 1) requestAnimationFrame(step); else el.textContent = fmt(target);
        }
        requestAnimationFrame(step); io.unobserve(el);
      });
    }, { threshold: .4 });
    counters.forEach(function (c) { io.observe(c); });
  }

  /* ---------- เริ่มทำงานเมื่อ DOM พร้อม ---------- */
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('input[type=password]').forEach(enhancePassword);
    initStagger(); initReveal(); initCounters();
    document.querySelectorAll('[data-dropzone]').forEach(initDropzone);
    document.querySelectorAll('.theme-toggle[data-theme-toggle]').forEach(function (b) { b.setAttribute('aria-pressed', Theme.get() === 'dark'); });

    // ไอคอน Bootstrap เป็นของตกแต่ง → ซ่อนจากโปรแกรมอ่านหน้าจอ (ข้อความ/aria-label ของปุ่มเป็นตัวบอกความหมาย)
    hideIcons(document);
    // ปุ่ม/ลิงก์ที่มีแต่ไอคอนและ title → ใช้ title เป็นชื่อปุ่ม (กันพลาดกรณีลืมใส่ aria-label)
    document.querySelectorAll('a[title], button[title]').forEach(function (b) {
      if (!b.hasAttribute('aria-label') && !b.textContent.trim()) b.setAttribute('aria-label', b.getAttribute('title'));
    });
    // กล่องสรุปข้อผิดพลาด/ข้อความสำเร็จจากเซิร์ฟเวอร์ → ย้าย focus ไปให้ผู้ใช้เห็นและได้ยินทันที
    var af = document.querySelector('[data-autofocus]');
    if (af) { af.focus({ preventScroll: true }); if (af.scrollIntoView) af.scrollIntoView({ block: 'center' }); }

    // แถบโลโก้หน่วยงาน: ปุ่มหยุด/เล่น (WCAG 2.2.2)
    document.querySelectorAll('[data-marquee-toggle]').forEach(function (btn) {
      var mq = document.getElementById('partnerMarquee'); if (!mq) return;
      var setState = function (paused) {
        mq.classList.toggle('is-paused', paused); btn.setAttribute('aria-pressed', paused ? 'true' : 'false');
        btn.innerHTML = paused ? '<i class="bi bi-play-fill" aria-hidden="true"></i> <span>เลื่อนต่อ</span>' : '<i class="bi bi-pause-fill" aria-hidden="true"></i> <span>หยุดเลื่อน</span>';
        try { localStorage.setItem('mou-marquee-paused', paused ? '1' : '0'); } catch (e) {}
      };
      var saved = null; try { saved = localStorage.getItem('mou-marquee-paused'); } catch (e) {}
      if (saved === '1') setState(true);
      btn.addEventListener('click', function () { setState(!mq.classList.contains('is-paused')); });
    });

    // 5) Navbar/Topbar เงาเมื่อเลื่อน + ปุ่มกลับด้านบน
    var bars = document.querySelectorAll('.site-nav, .admin-top');
    var top = document.createElement('button');
    top.type = 'button'; top.className = 'to-top no-print'; top.setAttribute('aria-label', 'กลับด้านบน'); top.innerHTML = '<i class="bi bi-arrow-up" aria-hidden="true"></i>';
    top.addEventListener('click', function () { window.scrollTo({ top: 0, behavior: 'smooth' }); });
    document.body.appendChild(top);
    var onScroll = function () {
      var y = window.scrollY || document.documentElement.scrollTop;
      bars.forEach(function (b) { b.classList.toggle('is-scrolled', y > 10); });
      top.classList.toggle('show', y > 420);
    };
    window.addEventListener('scroll', onScroll, { passive: true }); onScroll();

    // Bootstrap tooltip (ถ้ามี title + data-bs-toggle="tooltip")
    if (window.bootstrap) document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) { new bootstrap.Tooltip(el); });
  });

  /* ---------- ส่วนที่ใช้ jQuery (DataTables, confirm, view toggle ฯลฯ) ---------- */
  if (!window.jQuery) return;
  var $ = window.jQuery;
  $(function () {
    // 6) DataTables ภาษาไทย (ใส่ class js-dt ให้ตาราง)
    if ($.fn.DataTable) {
      $('table.js-dt').each(function () {
        var order = $(this).data('order');
        $(this).DataTable({
          language: window.DT_TH, pageLength: 10, lengthMenu: [10, 25, 50, 100],
          order: order ? [order] : [], responsive: false, autoWidth: false,
          columnDefs: [{ targets: 'no-sort', orderable: false }],
          initComplete: function () {
            // ช่องค้นหา/จำนวนแถวของ DataTables ไม่มีชื่อ → ตั้งชื่อให้โปรแกรมอ่านหน้าจอ
            var w = $(this.api().table().container());
            w.find('.dataTables_filter input').attr({ 'aria-label': 'ค้นหาในตาราง', type: 'search' });
            w.find('.dataTables_length select').attr('aria-label', 'จำนวนแถวต่อหน้า');
          }
        });
      });
    }

    // 7) ปุ่มยืนยันก่อนส่งฟอร์ม/ลิงก์ (data-confirm="ข้อความ")
    $(document).on('click submit', '[data-confirm]', function (e) {
      var el = this;
      if (el.dataset.ok === '1') return;
      if (e.type === 'click' && el.tagName === 'FORM') return; // ให้ไปจัดการตอน submit
      e.preventDefault();
      Swal.fire({ icon: 'warning', title: 'ยืนยันการดำเนินการ', text: el.getAttribute('data-confirm'),
        showCancelButton: true, confirmButtonText: 'ยืนยัน', cancelButtonText: 'ยกเลิก', reverseButtons: true, focusCancel: true
      }).then(function (r) {
        if (!r.isConfirmed) return;
        el.dataset.ok = '1';
        Loader.show(true);
        if (el.tagName === 'FORM') { el.submit(); }
        else if (el.tagName === 'A') { window.location.href = el.href; }
        else if (el.form) { el.form.requestSubmit ? el.form.requestSubmit(el) : el.form.submit(); }
      });
    });

    // 8) สลับมุมมอง ตาราง/การ์ด
    $('.js-view-toggle').on('click', function () {
      var v = $(this).data('view');
      $('.js-view-toggle').removeClass('active').attr('aria-pressed', 'false'); $(this).addClass('active').attr('aria-pressed', 'true');
      $('#view-table').toggleClass('d-none', v !== 'table');
      $('#view-cards').toggleClass('d-none', v !== 'cards');
      if (v === 'cards') initStagger(document.getElementById('view-cards'));
      try { localStorage.setItem('mouView', v); } catch (e) {}
    });
    try { var sv = localStorage.getItem('mouView'); if (sv) $('.js-view-toggle[data-view="' + sv + '"]').trigger('click'); } catch (e) {}

    // การ์ด: โหลดเพิ่มทีละ 12
    var $more = $('#btn-more');
    if ($more.length) {
      var $cards = $('#view-cards .card-item'), shown = 12;
      var render = function () { $cards.each(function (i) { $(this).toggleClass('d-none', i >= shown); }); $more.toggleClass('d-none', shown >= $cards.length); };
      $more.on('click', function () { shown += 12; render(); }); render();
    }

    // แชร์ลิงก์ / พิมพ์
    $('.js-share').on('click', function () {
      var u = location.href, t = document.title;
      if (navigator.share) { navigator.share({ title: t, url: u }).catch(function () {}); return; }
      if (navigator.clipboard) { navigator.clipboard.writeText(u).then(function () { window.MouFlash('success', 'คัดลอกลิงก์แล้ว'); }); }
      else { window.prompt('คัดลอกลิงก์นี้', u); }
    });
    $('.js-print').on('click', function () { window.print(); });
  });
})();

/* ---------- 11) ต่ออายุเซสชันหลังบ้านระหว่างที่ผู้ใช้ยังทำงานอยู่ (กันข้อมูลในฟอร์มหายเพราะหมดเวลา) ---------- */
(function () {
  if (!document.body || !document.body.classList.contains('admin-body') || !window.fetch) return;
  var active = false, warned = false;
  ['keydown', 'pointerdown', 'input'].forEach(function (ev) {
    document.addEventListener(ev, function () { active = true; }, true);
  });
  setInterval(function () {
    if (!active) return;                 // ไม่มีการใช้งานในช่วง 5 นาทีที่ผ่านมา → ปล่อยให้หมดเวลาตามปกติ
    active = false;
    fetch('ping.php', { credentials: 'same-origin', cache: 'no-store' }).then(function (r) {
      if (r.status === 401 && !warned && window.Swal) {
        warned = true;
        Swal.fire({ icon: 'warning', title: 'หมดเวลาการใช้งาน', confirmButtonText: 'ตกลง',
          text: 'เซสชันหมดอายุแล้ว ถ้ามีข้อความที่พิมพ์ค้างไว้ ให้คัดลอกเก็บไว้ก่อน แล้วเข้าสู่ระบบใหม่' });
      }
    }).catch(function () {});
  }, 5 * 60 * 1000);
})();
