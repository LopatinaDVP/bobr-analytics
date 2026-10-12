(function () {
  'use strict';
  var L = window.bobrL10n || { rows: 'Rows: %d', none: 'Nothing found', page: '%1$d-%2$d of %3$d' };
  document.querySelectorAll('.bobr-box').forEach(function (box) {
    var t = box.querySelector('table.bobr-t');
    if (!t) return;
    var tb = t.tBodies[0], rows = [].slice.call(tb.rows), per = +t.dataset.page || 25, page = 0, q = '', shown = rows;
    var pager = document.createElement('div');
    pager.className = 'bobr-pager';
    t.parentNode.after(pager);

    function render() {
      var pages = Math.max(1, Math.ceil(shown.length / per));
      if (page >= pages) page = pages - 1;
      rows.forEach(function (r) { r.style.display = 'none'; });
      shown.slice(page * per, page * per + per).forEach(function (r) { r.style.display = ''; });
      pager.innerHTML = '';
      if (shown.length <= per) { pager.textContent = shown.length ? L.rows.replace('%d', shown.length) : L.none; return; }
      var info = document.createElement('span');
      info.textContent = L.page.replace('%1$d', page * per + 1).replace('%2$d', Math.min(shown.length, page * per + per)).replace('%3$d', shown.length);
      var prev = document.createElement('button'), next = document.createElement('button');
      prev.type = next.type = 'button'; prev.className = next.className = 'button';
      prev.textContent = '←'; next.textContent = '→';
      prev.disabled = page === 0; next.disabled = page >= pages - 1;
      prev.onclick = function () { page--; render(); };
      next.onclick = function () { page++; render(); };
      pager.append(prev, info, next);
    }

    [].forEach.call(t.tHead.rows[0].cells, function (th, i) {
      th.addEventListener('click', function () {
        var dir = th.dataset.dir === 'desc' ? 'asc' : 'desc', num = th.dataset.type === 'n';
        [].forEach.call(th.parentNode.cells, function (c) { delete c.dataset.dir; });
        th.dataset.dir = dir;
        var val = function (r) { var c = r.cells[i]; return num ? parseFloat(c.dataset.v) || 0 : c.textContent.trim().toLowerCase(); };
        rows.sort(function (a, b) { var x = val(a), y = val(b); var s = x > y ? 1 : x < y ? -1 : 0; return dir === 'asc' ? s : -s; });
        rows.forEach(function (r) { tb.appendChild(r); });
        filter();
      });
    });

    function filter() {
      shown = q ? rows.filter(function (r) { return r.textContent.toLowerCase().indexOf(q) > -1; }) : rows.slice();
      page = 0;
      render();
    }
    var s = box.querySelector('.bobr-q');
    if (s) s.addEventListener('input', function () { q = s.value.trim().toLowerCase(); filter(); });

    var b = box.querySelector('.bobr-csv');
    if (b) b.addEventListener('click', function () {
      var esc = function (v) { v = String(v).replace(/\s+/g, ' ').trim(); return /[";\n]/.test(v) ? '"' + v.replace(/"/g, '""') + '"' : v; };
      var out = [[].map.call(t.tHead.rows[0].cells, function (c) { return esc(c.textContent); }).join(';')];
      shown.forEach(function (r) { out.push([].map.call(r.cells, function (c) { return esc(c.textContent); }).join(';')); });
      var blob = new Blob(['﻿' + out.join('\r\n')], { type: 'text/csv;charset=utf-8' });
      var a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = (b.dataset.file || 'bobr') + '.csv';
      document.body.appendChild(a); a.click(); a.remove();
      setTimeout(function () { URL.revokeObjectURL(a.href); }, 2000);
    });
    filter();
  });
})();
