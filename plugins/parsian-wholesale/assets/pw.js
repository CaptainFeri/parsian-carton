/* ============================================================
   فروش عمده — جدول قیمت پلکانی صفحهٔ محصول
   ردیف فعال با تغییر تعداد عوض می‌شود و با انتخاب واریاسیون،
   قیمت هر پله از قیمت همان واریاسیون دوباره حساب می‌شود.
   ============================================================ */
(function ($) {
  'use strict';

  var data = window.pwData || {};

  function formatNumber(value) {
    var decimals = parseInt(data.decimals, 10) || 0;
    var parts = Number(value).toFixed(decimals).split('.');
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, data.thousand || ',');
    return parts.join(data.decimal || '.');
  }

  function formatPrice(value) {
    var format = data.format || '%2$s %1$s';
    return format.replace('%1$s', data.symbol || '').replace('%2$s', formatNumber(value));
  }

  function tierIndex(tiers, qty) {
    var index = 0;
    for (var i = 0; i < tiers.length; i++) {
      if (qty >= tiers[i][0]) { index = i; }
    }
    return index;
  }

  function highlight($wrap, tiers, qty) {
    var active = tierIndex(tiers, qty);
    $wrap.find('.pw-tier').each(function (i) {
      var on = i === active;
      $(this).toggleClass('is-active', on);
      if (on) { $(this).attr('aria-current', 'true'); } else { $(this).removeAttr('aria-current'); }
      $(this).find('.pw-tier-badge').prop('hidden', !on);
    });
  }

  function reprice($wrap, base) {
    if (!(base > 0)) { return; }
    var decimals = parseInt(data.decimals, 10) || 0;
    var factor = Math.pow(10, decimals);
    $wrap.find('.pw-tier').each(function () {
      var discount = parseFloat($(this).attr('data-pw-discount')) || 0;
      var price = Math.round(base * (100 - discount) / 100 * factor) / factor;
      $(this).find('.pw-tier-price').text(formatPrice(price));
    });
  }

  $(function () {
    var $wrap = $('.pw-tiers').first();
    if (!$wrap.length) { return; }

    var tiers = $wrap.data('pwTiers') || [];
    var base = parseFloat($wrap.attr('data-pw-base')) || 0;
    var $form = $('form.cart').first();

    $form.on('input change', 'input.qty', function () {
      highlight($wrap, tiers, parseFloat(this.value) || 0);
    });

    $form.on('found_variation', function (event, variation) {
      reprice($wrap, parseFloat(variation.display_price));
    });

    $form.on('reset_data', function () {
      reprice($wrap, base);
    });
  });
})(jQuery);
