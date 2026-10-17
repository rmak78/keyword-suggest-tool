/* global KSUG, jQuery, wp, tinymce, YoastSEO */
(function ($) {
  'use strict';

  if (typeof KSUG === 'undefined') return;

  var root = document.getElementById('ksug-app');
  if (!root) return;

  var state = {
    results: [],
    categories: [],
    added: {},
    filter: '',
  };

  // ---------------------------------------------------------------------------
  // Editor access (block editor and classic editor)
  // ---------------------------------------------------------------------------

  function isBlockEditor() {
    return !!(window.wp && wp.data && wp.data.select && wp.data.select('core/editor') && document.body.classList.contains('block-editor-page'));
  }

  function getTitle() {
    if (isBlockEditor()) return wp.data.select('core/editor').getEditedPostAttribute('title') || '';
    var t = document.getElementById('title');
    return t ? t.value : '';
  }

  function getContent() {
    if (isBlockEditor()) return wp.data.select('core/editor').getEditedPostContent() || '';
    if (window.tinymce && tinymce.get('content') && !tinymce.get('content').isHidden()) return tinymce.get('content').getContent();
    var c = document.getElementById('content');
    return c ? c.value : '';
  }

  // ---------------------------------------------------------------------------
  // Click actions
  // ---------------------------------------------------------------------------

  function addTag(keyword) {
    if (!KSUG.tagTaxonomy) return Promise.reject(new Error(KSUG.i18n.noTagTax));

    if (isBlockEditor()) {
      var base = KSUG.tagRestBase;
      return wp
        .apiFetch({ path: '/wp/v2/' + base + '?search=' + encodeURIComponent(keyword) + '&per_page=10' })
        .then(function (terms) {
          var exact = (terms || []).filter(function (t) {
            return t.name.toLowerCase() === keyword.toLowerCase();
          })[0];
          return exact ? exact : wp.apiFetch({ path: '/wp/v2/' + base, method: 'POST', data: { name: keyword } });
        })
        .catch(function (err) {
          // Term already exists but was not in the search page.
          if (err && err.code === 'term_exists' && err.data && err.data.term_id) return { id: err.data.term_id };
          throw err;
        })
        .then(function (term) {
          var current = wp.data.select('core/editor').getEditedPostAttribute(base) || [];
          if (current.indexOf(term.id) === -1) {
            var patch = {};
            patch[base] = current.concat([term.id]);
            wp.data.dispatch('core/editor').editPost(patch);
          }
        });
    }

    // Classic editor tag box.
    var box = $('#' + KSUG.tagTaxonomy);
    if (box.length && window.tagBox) {
      box.find('input.newtag').val(keyword);
      window.tagBox.flushTags(box);
      return Promise.resolve();
    }
    // Hierarchical fallback is not supported; copy instead.
    return copy(keyword).then(function () {
      throw new Error(KSUG.i18n.noTagTax);
    });
  }

  function setFocusKeyword(keyword) {
    if (KSUG.seo.yoast) {
      if (window.YoastSEO && YoastSEO.store && YoastSEO.store.dispatch) {
        YoastSEO.store.dispatch({ type: 'SET_FOCUS_KEYWORD', keyword: keyword });
        return Promise.resolve();
      }
      var yi = document.getElementById('focus-keyword-input-metabox') || document.getElementById('yoast_wpseo_focuskw');
      if (yi) {
        setNativeValue(yi, keyword);
        return Promise.resolve();
      }
    }
    if (KSUG.seo.rankmath) {
      if (window.wp && wp.data && wp.data.dispatch('rank-math') && wp.data.dispatch('rank-math').updateKeywords) {
        wp.data.dispatch('rank-math').updateKeywords(keyword);
        return Promise.resolve();
      }
      var ri = document.getElementById('rank-math-focus-keyword') || document.querySelector('input[name="rank_math_focus_keyword"]');
      if (ri) {
        setNativeValue(ri, keyword);
        return Promise.resolve();
      }
    }
    return copy(keyword).then(function () {
      throw new Error(KSUG.i18n.noSeo);
    });
  }

  function setNativeValue(input, value) {
    var setter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;
    setter.call(input, value);
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.dispatchEvent(new Event('change', { bubbles: true }));
  }

  function insertAtCursor(keyword) {
    if (isBlockEditor()) {
      var active = document.activeElement;
      if (active && active.isContentEditable && active.closest('.block-editor-block-list__layout')) {
        document.execCommand('insertText', false, keyword);
        return Promise.resolve();
      }
      var block = wp.blocks.createBlock('core/paragraph', { content: keyword });
      var selected = wp.data.select('core/block-editor').getSelectedBlockClientId();
      var index;
      if (selected) {
        var rootId = wp.data.select('core/block-editor').getBlockRootClientId(selected);
        index = wp.data.select('core/block-editor').getBlockIndex(selected, rootId) + 1;
        wp.data.dispatch('core/block-editor').insertBlocks(block, index, rootId);
      } else {
        wp.data.dispatch('core/block-editor').insertBlocks(block);
      }
      return Promise.resolve();
    }
    if (window.tinymce && tinymce.get('content') && !tinymce.get('content').isHidden()) {
      tinymce.get('content').execCommand('mceInsertContent', false, keyword);
      return Promise.resolve();
    }
    var ta = document.getElementById('content');
    if (ta) {
      var start = ta.selectionStart || 0;
      var end = ta.selectionEnd || 0;
      ta.value = ta.value.slice(0, start) + keyword + ta.value.slice(end);
      ta.selectionStart = ta.selectionEnd = start + keyword.length;
      ta.dispatchEvent(new Event('input', { bubbles: true }));
      return Promise.resolve();
    }
    return Promise.reject(new Error(KSUG.i18n.error));
  }

  function copy(text) {
    if (navigator.clipboard && navigator.clipboard.writeText) return navigator.clipboard.writeText(text);
    var ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.select();
    document.execCommand('copy');
    ta.remove();
    return Promise.resolve();
  }

  var actions = { tag: addTag, focus: setFocusKeyword, insert: insertAtCursor, copy: copy };

  // ---------------------------------------------------------------------------
  // UI
  // ---------------------------------------------------------------------------

  function esc(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function engineOptions() {
    var groups = {};
    Object.keys(KSUG.engines).forEach(function (id) {
      var e = KSUG.engines[id];
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
              var on = KSUG.defaultEngines.indexOf(e.id) !== -1;
              return '<label class="ksug-engine"><input type="checkbox" value="' + e.id + '"' + (on ? ' checked' : '') + '> ' + esc(e.label) + '</label>';
            })
            .join('') +
          '</span>'
        );
      })
      .join('');
  }

  function actionOptions() {
    var opts = [];
    if (KSUG.tagTaxonomy) opts.push(['tag', KSUG.i18n.actTag]);
    if (KSUG.seo.yoast || KSUG.seo.rankmath) opts.push(['focus', KSUG.i18n.actFocus]);
    opts.push(['insert', KSUG.i18n.actInsert]);
    opts.push(['copy', KSUG.i18n.actCopy]);
    return opts
      .map(function (o) {
        return '<option value="' + o[0] + '">' + esc(o[1]) + '</option>';
      })
      .join('');
  }

  root.innerHTML =
    '<div class="ksug-row">' +
    '  <label class="ksug-label" for="ksug-seeds">' + esc(KSUG.i18n.seeds) + '</label>' +
    '  <div class="ksug-seeds-wrap">' +
    '    <input type="text" id="ksug-seeds" class="widefat" placeholder="running shoes, trail running">' +
    '    <button type="button" class="button" id="ksug-from-content">' + esc(KSUG.i18n.fromContent) + '</button>' +
    '  </div>' +
    '  <p class="description">' + esc(KSUG.i18n.seedsHelp) + '</p>' +
    '</div>' +
    '<div class="ksug-row ksug-engines"><span class="ksug-label">' + esc(KSUG.i18n.sources) + '</span>' + engineOptions() + '</div>' +
    '<div class="ksug-row ksug-controls">' +
    '  <label>' + esc(KSUG.i18n.locale) + ' <select id="ksug-locale">' +
    KSUG.locales.map(function (l) { return '<option value="' + l + '"' + (l === KSUG.locale ? ' selected' : '') + '>' + l + '</option>'; }).join('') +
    '  </select></label>' +
    '  <label>' + esc(KSUG.i18n.onClick) + ' <select id="ksug-action">' + actionOptions() + '</select></label>' +
    '  <button type="button" class="button button-primary" id="ksug-get">' + esc(KSUG.i18n.get) + '</button>' +
    '  <span class="spinner" id="ksug-spinner"></span>' +
    '</div>' +
    '<div class="ksug-row ksug-toolbar" id="ksug-toolbar" hidden>' +
    '  <input type="search" id="ksug-filter" placeholder="' + esc(KSUG.i18n.filter) + '">' +
    '  <span id="ksug-count" class="ksug-count"></span>' +
    (KSUG.tagTaxonomy ? '  <button type="button" class="button" id="ksug-add-all">' + esc(KSUG.i18n.addAll) + '</button>' : '') +
    '</div>' +
    '<div id="ksug-categories" class="ksug-row ksug-categories" hidden></div>' +
    '<div id="ksug-results" class="ksug-results"><p class="description">' + esc(KSUG.i18n.none) + '</p></div>' +
    '<div id="ksug-notice" class="ksug-notice" hidden></div>';

  var $seeds = $('#ksug-seeds');
  var $results = $('#ksug-results');
  var $notice = $('#ksug-notice');
  var $spinner = $('#ksug-spinner');

  function notice(msg, kind) {
    $notice.text(msg).attr('class', 'ksug-notice ksug-notice-' + (kind || 'info')).prop('hidden', false);
    clearTimeout(notice.t);
    notice.t = setTimeout(function () {
      $notice.prop('hidden', true);
    }, 3000);
  }

  function selectedEngines() {
    return $('.ksug-engines input:checked')
      .map(function () {
        return this.value;
      })
      .get();
  }

  function seeds() {
    var seen = {};
    return $seeds
      .val()
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

  function post(action, data) {
    return $.ajax({
      url: KSUG.ajaxUrl,
      method: 'POST',
      dataType: 'json',
      data: $.extend({ action: action, nonce: KSUG.nonce }, data),
    }).then(function (res) {
      if (!res || !res.success) throw new Error((res && res.data && res.data.message) || KSUG.i18n.error);
      return res.data;
    });
  }

  function suggestSeeds() {
    var title = getTitle();
    var content = getContent();
    if (!title && !content) return Promise.resolve([]);
    return post('ksug_seeds', { title: title, content: content }).then(function (data) {
      if (data.seeds && data.seeds.length) $seeds.val(data.seeds.join(', '));
      return data.seeds || [];
    });
  }

  function render() {
    var q = state.filter.toLowerCase();
    var list = state.results.filter(function (r) {
      return !q || r.keyword.toLowerCase().indexOf(q) !== -1;
    });
    $('#ksug-count').text(list.length === state.results.length ? state.results.length : list.length + ' / ' + state.results.length);
    if (!state.results.length) {
      $results.html('<p class="description">' + esc(KSUG.i18n.empty) + '</p>');
      return;
    }
    $results.html(
      list
        .map(function (r) {
          var dots = r.sources
            .map(function (s) {
              return '<i class="ksug-dot ksug-dot-' + s + '" title="' + esc(KSUG.engines[s] ? KSUG.engines[s].label : s) + '"></i>';
            })
            .join('');
          var added = state.added[r.keyword.toLowerCase()];
          return (
            '<button type="button" class="ksug-chip' + (added ? ' is-added' : '') + '" data-kw="' + esc(r.keyword) + '" title="' + esc(r.sources.map(function (s) { return KSUG.engines[s] ? KSUG.engines[s].label : s; }).join(', ')) + '">' +
            '<span class="ksug-dots">' + dots + '</span>' +
            esc(r.keyword) +
            (added ? '<span class="ksug-check" aria-label="' + esc(KSUG.i18n.added) + '">✓</span>' : '') +
            '</button>'
          );
        })
        .join('')
    );
    var $cats = $('#ksug-categories');
    if (state.categories.length) {
      $cats.html('<span class="ksug-label">' + esc(KSUG.i18n.categories) + '</span>' + state.categories.map(function (c) { return '<span class="ksug-cat">' + esc(c) + '</span>'; }).join('')).prop('hidden', false);
    } else {
      $cats.prop('hidden', true);
    }
  }

  function fetchSuggestions() {
    var s = seeds();
    var e = selectedEngines();
    if (!s.length) return notice(KSUG.i18n.noSeeds, 'error');
    if (!e.length) return notice(KSUG.i18n.noEngines, 'error');
    $spinner.addClass('is-active');
    $('#ksug-get').prop('disabled', true);
    $results.html('<p class="description">' + esc(KSUG.i18n.loading) + '</p>');
    post('ksug_fetch', { seeds: s, engines: e, locale: $('#ksug-locale').val() })
      .then(function (data) {
        state.results = data.results || [];
        state.categories = data.categories || [];
        $('#ksug-toolbar').prop('hidden', false);
        render();
        var errs = Object.keys(data.errors || {});
        if (errs.length) notice(errs.map(function (k) { return (KSUG.engines[k] ? KSUG.engines[k].label : k) + ': ' + data.errors[k]; }).join(' · '), 'error');
      })
      .catch(function (err) {
        notice(err.message || KSUG.i18n.error, 'error');
        $results.html('<p class="description">' + esc(KSUG.i18n.empty) + '</p>');
      })
      .always(function () {
        $spinner.removeClass('is-active');
        $('#ksug-get').prop('disabled', false);
      });
  }

  function runAction(keyword) {
    var act = $('#ksug-action').val();
    return actions[act](keyword)
      .then(function () {
        state.added[keyword.toLowerCase()] = true;
        render();
        notice((act === 'copy' ? KSUG.i18n.copied : KSUG.i18n.added) + ': ' + keyword, 'success');
      })
      .catch(function (err) {
        notice(err && err.message ? err.message : KSUG.i18n.error, 'error');
      });
  }

  // Events
  $('#ksug-get').on('click', fetchSuggestions);
  $seeds.on('keydown', function (ev) {
    if (ev.key === 'Enter') {
      ev.preventDefault();
      fetchSuggestions();
    }
  });
  $('#ksug-from-content').on('click', function () {
    var $b = $(this).prop('disabled', true);
    suggestSeeds()
      .catch(function (err) {
        notice(err.message || KSUG.i18n.error, 'error');
      })
      .then(function () {
        $b.prop('disabled', false);
      });
  });
  $('#ksug-filter').on('input', function () {
    state.filter = this.value;
    render();
  });
  $results.on('click', '.ksug-chip', function () {
    runAction(this.dataset.kw);
  });
  $('#ksug-add-all').on('click', function () {
    var q = state.filter.toLowerCase();
    var list = state.results.filter(function (r) {
      return (!q || r.keyword.toLowerCase().indexOf(q) !== -1) && !state.added[r.keyword.toLowerCase()];
    });
    var chain = Promise.resolve();
    list.forEach(function (r) {
      chain = chain.then(function () {
        return addTag(r.keyword).then(function () {
          state.added[r.keyword.toLowerCase()] = true;
        });
      });
    });
    chain
      .then(function () {
        render();
        notice(KSUG.i18n.added + ': ' + list.length, 'success');
      })
      .catch(function (err) {
        render();
        notice(err.message || KSUG.i18n.error, 'error');
      });
  });

  // Seed from content on load, once the editor has settled.
  setTimeout(function () {
    if (!$seeds.val()) {
      suggestSeeds().catch(function () {});
    }
  }, isBlockEditor() ? 1500 : 300);
})(jQuery);
