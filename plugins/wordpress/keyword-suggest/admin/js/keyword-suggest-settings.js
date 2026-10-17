/* global KSUG_SETTINGS, jQuery */
(function ($) {
  'use strict';
  if (typeof KSUG_SETTINGS === 'undefined') return;
  var C = KSUG_SETTINGS;

  // Range output
  $('#ksug-limit').on('input', function () {
    $('#ksug-limit-out').text(this.value);
  });

  // Select all / none
  $('[data-ksug-select]').on('click', function () {
    var all = $(this).data('ksug-select') === 'all';
    $(this).closest('.ksug-card').find('input[type=checkbox]').prop('checked', all);
  });

  function esc(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  // Live source test, one engine at a time so results stream in
  $('#ksug-test').on('click', function () {
    var $btn = $(this).prop('disabled', true);
    var $spin = $('#ksug-test-spinner').addClass('is-active');
    var $tbody = $('#ksug-health').prop('hidden', false).find('tbody').empty();
    $('[data-ksug-status]').text('').attr('class', 'ksug-tile-status');

    var queue = C.engines.slice();
    (function next() {
      var engine = queue.shift();
      if (!engine) {
        $btn.prop('disabled', false);
        $spin.removeClass('is-active');
        return;
      }
      var label = $('.ksug-tile input[value="' + engine + '"]').closest('.ksug-tile').find('span span').first().text() || engine;
      var $row = $('<tr><td>' + esc(label) + '</td><td class="ksug-health-status">' + esc(C.i18n.testing) + '</td></tr>').appendTo($tbody);
      $.post(C.ajaxUrl, { action: 'ksug_health', nonce: C.nonce, engine: engine })
        .done(function (res) {
          var d = res && res.data ? res.data : {};
          var ok = !!(res && res.success && d.ok);
          var detail = ok ? d.count + ' ' + C.i18n.results + ' · ' + d.ms + ' ms<small>' + esc((d.sample || []).join(', ')) + '</small>' : '<small>' + esc(d.error || C.i18n.fail) + '</small>';
          $row.find('.ksug-health-status').html('<span class="' + (ok ? 'is-ok' : 'is-fail') + '">' + (ok ? C.i18n.ok : C.i18n.fail) + '</span> ' + detail);
          $('[data-ksug-status="' + engine + '"]').text(ok ? '✓' : '✕').addClass(ok ? 'is-ok' : 'is-fail');
        })
        .fail(function () {
          $row.find('.ksug-health-status').html('<span class="is-fail">' + esc(C.i18n.fail) + '</span>');
          $('[data-ksug-status="' + engine + '"]').text('✕').addClass('is-fail');
        })
        .always(next);
    })();
  });
})(jQuery);
