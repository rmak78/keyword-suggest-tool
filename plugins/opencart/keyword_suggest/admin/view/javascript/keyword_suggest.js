/* global KSUG_OC, CKEDITOR */
(function () {
  'use strict';

  if (typeof KSUG_OC === 'undefined') return;
  var C = KSUG_OC;

  // The product form keeps one set of inputs per language inside #tab-general.
  var general = document.getElementById('tab-general');
  if (!general) return;

  var state = { results: [], categories: [], added: {}, filter: '' };

  function esc(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function langId() {
    var active = general.querySelector('.tab-pane.active[id^="language-"]');
    if (active) return active.id.replace('language-', '');
    var first = general.querySelector('[id^="input-meta-keyword-"]');
    return first ? first.id.replace('input-meta-keyword-', '') : '1';
  }

  function field(prefix) {
    return document.getElementById(prefix + langId());
  }

  function descriptionEditor() {
    var id = 'input-description-' + langId();
    return window.CKEDITOR && CKEDITOR.instances && CKEDITOR.instances[id] ? CKEDITOR.instances[id] : null;
  }

  function getTitle() {
    var n = field('input-name-');
    var m = field('input-meta-title-');
    return [(n && n.value) || '', (m && m.value) || ''].filter(Boolean).join('. ');
  }

  function getContent() {
    var ed = descriptionEditor();
    if (ed) return ed.getData();
    var ta = field('input-description-');
    return ta ? ta.value : '';
  }

  // --- click actions ----------------------------------------------------------

  function appendToList(input, keyword) {
    var parts = input.value
      .split(',')
      .map(function (s) {
        return s.trim();
      })
      .filter(Boolean);
    if (
      !parts.some(function (p) {
        return p.toLowerCase() === keyword.toLowerCase();
      })
    ) {
      parts.push(keyword);
    }
    input.value = parts.join(', ');
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.dispatchEvent(new Event('change', { bubbles: true }));
  }

  var actions = {
    meta: function (kw) {
      var el = field('input-meta-keyword-');
      if (!el) return Promise.reject(new Error(C.i18n.error));
      appendToList(el, kw);
      return Promise.resolve();
    },
    tag: function (kw) {
      var el = field('input-tag-');
      if (!el) return Promise.reject(new Error(C.i18n.error));
      appendToList(el, kw);
      return Promise.resolve();
    },
    description: function (kw) {
      var ed = descriptionEditor();
      if (ed) {
        ed.insertText(kw);
        return Promise.resolve();
      }
      var ta = field('input-description-');
      if (!ta) return Promise.reject(new Error(C.i18n.error));
      var s = ta.selectionStart || 0;
      var e = ta.selectionEnd || 0;
      ta.value = ta.value.slice(0, s) + kw + ta.value.slice(e);
      ta.selectionStart = ta.selectionEnd = s + kw.length;
      return Promise.resolve();
    },
    copy: function (kw) {
      if (navigator.clipboard && navigator.clipboard.writeText) return navigator.clipboard.writeText(kw);
      var ta = document.createElement('textarea');
      ta.value = kw;
      ta.style.position = 'fixed';
      ta.style.opacity = '0';
      document.body.appendChild(ta);
      ta.select();
      document.execCommand('copy');
      ta.remove();
      return Promise.resolve();
    },
  };

  // --- panel ------------------------------------------------------------------

  function engineOptions() {
    var groups = {};
    Object.keys(C.engines).forEach(function (id) {
      var e = C.engines[id];
      (groups[e.group] = groups[e.group] || []).push({ id: id, label: e.label });
    });
    return Object.keys(groups)
      .map(function (g) {
        return (
          '<span class="ksug-group"><span class="ksug-group-label">' +
          esc(g) +
          '</span>' +
          groups[g]
            .map(function (e) {
              var on = C.defaultEngines.indexOf(e.id) !== -1;
              return (
                '<label class="ksug-engine form-check form-check-inline"><input class="form-check-input" type="checkbox" value="' +
                e.id +
                '"' +
                (on ? ' checked' : '') +
                '> <span class="form-check-label">' +
                esc(e.label) +
                '</span></label>'
              );
            })
            .join('') +
          '</span>'
        );
      })
      .join('');
  }

  var panel = document.createElement('div');
  panel.className = 'card ksug mt-3';
  panel.id = 'ksug-panel';
  panel.innerHTML =
    '<div class="card-header"><i class="fa-solid fa-lightbulb"></i> ' + esc(C.i18n.heading) + '</div>' +
    '<div class="card-body">' +
    '  <div class="row mb-3">' +
    '    <label for="ksug-seeds" class="col-sm-2 col-form-label">' + esc(C.i18n.seeds) + '</label>' +
    '    <div class="col-sm-10">' +
    '      <div class="input-group">' +
    '        <input type="text" id="ksug-seeds" class="form-control" placeholder="running shoes, trail running">' +
    '        <button type="button" class="btn btn-light" id="ksug-from-content">' + esc(C.i18n.fromContent) + '</button>' +
    '      </div>' +
    '      <div class="form-text">' + esc(C.i18n.seedsHelp) + '</div>' +
    '    </div>' +
    '  </div>' +
    '  <div class="row mb-3">' +
    '    <label class="col-sm-2 col-form-label">' + esc(C.i18n.sources) + '</label>' +
    '    <div class="col-sm-10 ksug-engines">' + engineOptions() + '</div>' +
    '  </div>' +
    '  <div class="row mb-3 align-items-center">' +
    '    <div class="col-sm-2"></div>' +
    '    <div class="col-sm-10 ksug-controls">' +
    '      <label>' + esc(C.i18n.locale) + ' <select id="ksug-locale" class="form-select form-select-sm d-inline-block w-auto">' +
    C.locales.map(function (l) { return '<option value="' + l + '"' + (l === C.locale ? ' selected' : '') + '>' + l + '</option>'; }).join('') +
    '      </select></label>' +
    '      <label>' + esc(C.i18n.onClick) + ' <select id="ksug-action" class="form-select form-select-sm d-inline-block w-auto">' +
    '        <option value="meta">' + esc(C.i18n.actMeta) + '</option>' +
    '        <option value="tag">' + esc(C.i18n.actTag) + '</option>' +
    '        <option value="description">' + esc(C.i18n.actDescription) + '</option>' +
    '        <option value="copy">' + esc(C.i18n.actCopy) + '</option>' +
    '      </select></label>' +
    '      <button type="button" class="btn btn-primary btn-sm" id="ksug-get"><i class="fa-solid fa-magnifying-glass"></i> ' + esc(C.i18n.get) + '</button>' +
    '    </div>' +
    '  </div>' +
    '  <div class="row mb-2" id="ksug-toolbar" hidden>' +
    '    <div class="col-sm-2"></div>' +
    '    <div class="col-sm-10 ksug-toolbar">' +
    '      <input type="search" id="ksug-filter" class="form-control form-control-sm w-auto" placeholder="' + esc(C.i18n.filter) + '">' +
    '      <span id="ksug-count" class="text-muted small"></span>' +
    '      <button type="button" class="btn btn-light btn-sm" id="ksug-add-all">' + esc(C.i18n.addAll) + '</button>' +
    '    </div>' +
    '  </div>' +
    '  <div class="row mb-2" id="ksug-categories-row" hidden><div class="col-sm-2"></div><div class="col-sm-10 ksug-categories" id="ksug-categories"></div></div>' +
    '  <div class="row"><div class="col-sm-2"></div><div class="col-sm-10"><div id="ksug-results" class="ksug-results"><p class="text-muted small mb-0">' + esc(C.i18n.none) + '</p></div>' +
    '  <div id="ksug-notice" class="alert alert-info py-1 px-2 mt-2 mb-0 small" hidden></div></div></div>' +
    '</div>';

  general.appendChild(panel);

  var $ = function (id) {
    return document.getElementById(id);
  };
  var seedsInput = $('ksug-seeds');
  var results = $('ksug-results');
  var noticeEl = $('ksug-notice');

  function notice(msg, kind) {
    noticeEl.textContent = msg;
    noticeEl.className = 'alert py-1 px-2 mt-2 mb-0 small alert-' + (kind === 'error' ? 'danger' : kind === 'success' ? 'success' : 'info');
    noticeEl.hidden = false;
    clearTimeout(notice.t);
    notice.t = setTimeout(function () {
      noticeEl.hidden = true;
    }, 3000);
  }

  function selectedEngines() {
    return Array.prototype.map.call(panel.querySelectorAll('.ksug-engines input:checked'), function (i) {
      return i.value;
    });
  }

  function seeds() {
    var seen = {};
    return seedsInput.value
      .split(/[,\n]+/)
      .map(function (s) {
        return s.trim();
      })
      .filter(function (s) {
        if (!s || seen[s.toLowerCase()]) return false;
        seen[s.toLowerCase()] = true;
        return true;
      });
  }

  function post(url, data) {
    var body = new URLSearchParams();
    Object.keys(data).forEach(function (k) {
      if (Array.isArray(data[k])) data[k].forEach(function (v) { body.append(k + '[]', v); });
      else body.append(k, data[k]);
    });
    return fetch(url, { method: 'POST', body: body, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) {
        return r.json();
      })
      .then(function (json) {
        if (json.error) throw new Error(json.error);
        return json;
      });
  }

  function suggestSeeds() {
    var title = getTitle();
    var content = getContent();
    if (!title && !content) return Promise.resolve([]);
    return post(C.seedsUrl, { title: title, content: content }).then(function (json) {
      if (json.seeds && json.seeds.length) seedsInput.value = json.seeds.join(', ');
      return json.seeds || [];
    });
  }

  function render() {
    var q = state.filter.toLowerCase();
    var list = state.results.filter(function (r) {
      return !q || r.keyword.toLowerCase().indexOf(q) !== -1;
    });
    $('ksug-count').textContent = list.length === state.results.length ? String(state.results.length) : list.length + ' / ' + state.results.length;
    if (!state.results.length) {
      results.innerHTML = '<p class="text-muted small mb-0">' + esc(C.i18n.empty) + '</p>';
      return;
    }
    results.innerHTML = list
      .map(function (r) {
        var labels = r.sources.map(function (s) { return C.engines[s] ? C.engines[s].label : s; }).join(', ');
        var dots = r.sources.map(function (s) { return '<i class="ksug-dot ksug-dot-' + s + '"></i>'; }).join('');
        var added = state.added[r.keyword.toLowerCase()];
        return (
          '<button type="button" class="ksug-chip' + (added ? ' is-added' : '') + '" data-kw="' + esc(r.keyword) + '" title="' + esc(labels) + '">' +
          '<span class="ksug-dots">' + dots + '</span>' + esc(r.keyword) + (added ? ' <span class="ksug-check">✓</span>' : '') +
          '</button>'
        );
      })
      .join('');
    var cats = $('ksug-categories');
    if (state.categories.length) {
      cats.innerHTML = '<span class="text-muted small me-1">' + esc(C.i18n.categories) + ':</span>' + state.categories.map(function (c) { return '<span class="badge bg-light text-dark border">' + esc(c) + '</span>'; }).join(' ');
      $('ksug-categories-row').hidden = false;
    } else {
      $('ksug-categories-row').hidden = true;
    }
  }

  function fetchSuggestions() {
    var s = seeds();
    var e = selectedEngines();
    if (!s.length) return notice(C.i18n.noSeeds, 'error');
    if (!e.length) return notice(C.i18n.noEngines, 'error');
    var btn = $('ksug-get');
    btn.disabled = true;
    results.innerHTML = '<p class="text-muted small mb-0"><i class="fa-solid fa-spinner fa-spin"></i> ' + esc(C.i18n.loading) + '</p>';
    post(C.suggestUrl, { seeds: s, engines: e, locale: $('ksug-locale').value })
      .then(function (json) {
        state.results = json.results || [];
        state.categories = json.categories || [];
        $('ksug-toolbar').hidden = false;
        render();
        var errs = Object.keys(json.errors || {});
        if (errs.length) notice(errs.map(function (k) { return (C.engines[k] ? C.engines[k].label : k) + ': ' + json.errors[k]; }).join(' · '), 'error');
      })
      .catch(function (err) {
        notice(err.message || C.i18n.error, 'error');
        results.innerHTML = '<p class="text-muted small mb-0">' + esc(C.i18n.empty) + '</p>';
      })
      .then(function () {
        btn.disabled = false;
      });
  }

  function runAction(kw) {
    var act = $('ksug-action').value;
    return actions[act](kw)
      .then(function () {
        state.added[kw.toLowerCase()] = true;
        render();
        notice((act === 'copy' ? C.i18n.copied : C.i18n.added) + ': ' + kw, 'success');
      })
      .catch(function (err) {
        notice((err && err.message) || C.i18n.error, 'error');
      });
  }

  $('ksug-get').addEventListener('click', fetchSuggestions);
  seedsInput.addEventListener('keydown', function (ev) {
    if (ev.key === 'Enter') {
      ev.preventDefault();
      fetchSuggestions();
    }
  });
  $('ksug-from-content').addEventListener('click', function () {
    var b = this;
    b.disabled = true;
    suggestSeeds()
      .catch(function (err) {
        notice(err.message || C.i18n.error, 'error');
      })
      .then(function () {
        b.disabled = false;
      });
  });
  $('ksug-filter').addEventListener('input', function () {
    state.filter = this.value;
    render();
  });
  results.addEventListener('click', function (ev) {
    var chip = ev.target.closest('.ksug-chip');
    if (chip) runAction(chip.dataset.kw);
  });
  $('ksug-add-all').addEventListener('click', function () {
    var act = $('ksug-action').value;
    if (act === 'copy' || act === 'description') act = 'meta';
    var q = state.filter.toLowerCase();
    var list = state.results.filter(function (r) {
      return (!q || r.keyword.toLowerCase().indexOf(q) !== -1) && !state.added[r.keyword.toLowerCase()];
    });
    list.forEach(function (r) {
      actions[act](r.keyword);
      state.added[r.keyword.toLowerCase()] = true;
    });
    render();
    notice(C.i18n.added + ': ' + list.length, 'success');
  });

  // Pre-fill seeds once the editor is ready.
  setTimeout(function () {
    if (!seedsInput.value) suggestSeeds().catch(function () {});
  }, 800);
})();
