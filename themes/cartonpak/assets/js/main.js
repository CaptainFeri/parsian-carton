/* ============================================================
   کارتن‌پک — جاوااسکریپت اصلی
   ============================================================ */
(function ($) {
  'use strict';

  var FA_DIGITS = '۰۱۲۳۴۵۶۷۸۹';

  function faDigits(value) {
    return String(value).replace(/\d/g, function (d) { return FA_DIGITS[d]; });
  }

  function escapeHtml(text) {
    return String(text).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  /* ---------- پاپ‌آپ افزودن به سبد خرید ---------- */
  var toastTimer = null;
  var toastProgress = 0;
  var TOAST_DURATION = 4200;

  function cartonpakToast(productTitle, productImg) {
    $('#cartToast').remove();

    var media = productImg
      ? '<img src="' + escapeHtml(productImg) + '" alt="">'
      : '<span class="cart-toast-ph" aria-hidden="true"></span>';

    var $toast = $(
      '<div class="cart-toast" id="cartToast" role="status">' +
        '<div class="cart-toast-main">' +
          '<span class="cart-toast-check" aria-hidden="true">✓</span>' +
          '<div class="cart-toast-body">' +
            '<strong class="cart-toast-title">به سبد خرید اضافه شد</strong>' +
            '<span class="cart-toast-product">' + media + '<em>' + escapeHtml(productTitle) + '</em></span>' +
            '<div class="cart-toast-actions">' +
              '<a class="btn btn-primary btn-small" href="' + escapeHtml(window.cartonpakData ? cartonpakData.cartUrl : '/') + '">مشاهده سبد خرید</a>' +
              '<button type="button" class="btn btn-ghost btn-small cart-toast-close">ادامه خرید</button>' +
            '</div>' +
          '</div>' +
        '</div>' +
        '<div class="cart-toast-bar"><i></i></div>' +
      '</div>'
    );

    $toast.find('.cart-toast-close').on('click', function () { dismissToast(); });
    $toast.on('mouseenter focusin', function () { if (toastTimer) { clearInterval(toastTimer); toastTimer = null; } });

    $('body').append($toast);
    requestAnimationFrame(function () { $toast.addClass('is-show'); });
    startToastTimer();
  }

  function startToastTimer() {
    if (toastTimer) { clearInterval(toastTimer); }
    var $bar = $('#cartToast .cart-toast-bar i');
    toastProgress = 0;
    toastTimer = setInterval(function () {
      toastProgress += 50;
      $bar.css('width', Math.min(100, (toastProgress / TOAST_DURATION) * 100) + '%');
      if (toastProgress >= TOAST_DURATION) { dismissToast(true); }
    }, 50);
  }

  function dismissToast(animated) {
    if (toastTimer) { clearInterval(toastTimer); toastTimer = null; }
    var $toast = $('#cartToast');
    if (!$toast.length) { return; }
    if (animated) {
      $toast.removeClass('is-show');
      setTimeout(function () { $toast.remove(); }, 300);
    } else {
      $toast.remove();
    }
  }

  function bounceCart() {
    var $cart = $('.header-cart');
    $cart.addClass('is-bounce');
    setTimeout(function () { $cart.removeClass('is-bounce'); }, 700);
  }

  /* افزودن از کارت محصول (اسکریپت ووکامرس) */
  $(document.body).on('added_to_cart', function (event, fragments, cartHash, button) {
    var $card = $(button).closest('.pc-card, li.product');
    var title = $card.find('.pc-card-title').text().trim();
    var img = $card.find('img').first().attr('src') || '';
    if (title) { cartonpakToast(title, img); }
    bounceCart();
  });

  /* ---------- افزودن به سبد از صفحهٔ محصول (بدون رفرش) ----------
     کل فرم (واریاسیون، چاپ لوگو و…) به همان نشانی فرم فرستاده می‌شود تا
     پردازش استاندارد ووکامرس انجام شود؛ بعد فرگمنت‌های سبد تازه می‌شوند. */
  $(document).on('submit', 'form.cart', function (e) {
    var form = this;
    var $form = $(form);
    var $btn = $form.find('.single_add_to_cart_button');

    if (!$btn.length || $form.hasClass('grouped_form') || !window.fetch || !window.FormData || !window.DOMParser) { return; }
    if ($btn.is('.disabled, :disabled, .loading')) { return; }
    e.preventDefault();

    var data = new FormData(form);
    if ($btn.attr('name') && $btn.val()) { data.append($btn.attr('name'), $btn.val()); }

    $btn.addClass('loading').attr('aria-busy', 'true');
    $form.closest('.product').find('.pc-add-error').remove();

    fetch(form.getAttribute('action') || window.location.href, { method: 'POST', body: data, credentials: 'same-origin' })
      .then(function (response) { return response.text(); })
      .then(function (html) {
        var doc = new DOMParser().parseFromString(html, 'text/html');
        var error = doc.querySelector('.woocommerce-error');
        if (error) {
          var $notice = $('<div class="pc-add-error woocommerce-error" role="alert"></div>').html(error.innerHTML);
          $form.before($notice);
          return;
        }
        $(document.body).trigger('wc_fragment_refresh');
        var title = $('.product_title').first().text().trim();
        var img = $('.woocommerce-product-gallery__image img').first().attr('src') || '';
        cartonpakToast(title, img);
        bounceCart();
      })
      .catch(function () { form.submit(); })
      .then(function () { $btn.removeClass('loading').removeAttr('aria-busy'); });
  });

  /* ---------- دکمه‌های +/− تعداد در سبد خرید ---------- */
  $(document).on('click', '.qty-step', function () {
    var $input = $(this).closest('.qty-stepper').find('.qty');
    if (!$input.length) { return; }
    var val = parseInt($input.val(), 10) || 0;
    var min = parseInt($input.attr('min'), 10);
    var max = parseInt($input.attr('max'), 10);
    if (isNaN(min)) { min = 0; }
    if (isNaN(max)) { max = 9999; }
    if ($(this).hasClass('qty-plus')) { if (val < max) { val += 1; } } else if (val > min) { val -= 1; }
    $input.val(val).trigger('change');
  });

  /* ---------- شمارندهٔ تعداد صفحهٔ محصول ---------- */
  function syncQtyUi($block) {
    var $input = $block.find('input.qty');
    var qty = parseInt($input.val(), 10) || 0;
    $block.find('.pc-preset').each(function () {
      $(this).attr('aria-pressed', String(parseInt($(this).attr('data-pc-qty'), 10) === qty));
    });
    return qty;
  }

  function setQty($block, qty) {
    var $input = $block.find('input.qty');
    var min = parseInt($input.attr('min'), 10);
    var max = parseInt($input.attr('max'), 10);
    if (isNaN(min) || min < 1) { min = 1; }
    qty = Math.max(min, qty);
    if (!isNaN(max) && max > 0) { qty = Math.min(max, qty); }
    $input.val(qty).trigger('change');
    syncQtyUi($block);
    $block.find('[data-pc-qty-live]').text('تعداد: ' + faDigits(qty));
  }

  $(document).on('click', '.pc-step', function () {
    var $block = $(this).closest('.pc-qty-block');
    var step = parseInt($block.attr('data-pc-step'), 10) || 1;
    var current = parseInt($block.find('input.qty').val(), 10) || 0;
    var dir = parseInt($(this).attr('data-pc-dir'), 10);
    setQty($block, current + dir * step);
  });

  $(document).on('click', '.pc-preset', function () {
    setQty($(this).closest('.pc-qty-block'), parseInt($(this).attr('data-pc-qty'), 10) || 1);
  });

  $(document).on('input change', '.pc-qty-block input.qty', function () {
    syncQtyUi($(this).closest('.pc-qty-block'));
  });

  /* ---------- واریاسیون‌ها به شکل دکمه ---------- */
  function buildOptionButtons($form) {
    $form.find('table.variations select').each(function () {
      var $select = $(this);
      if ($select.hasClass('pc-enhanced')) { return; }

      var options = $select.find('option').filter(function () { return this.value !== ''; });
      if (!options.length || options.length > 8) { return; }

      var labelId = $select.attr('id') + '-label';
      $form.find('label[for="' + $select.attr('id') + '"]').attr('id', labelId);

      var $group = $('<div class="pc-options" role="group"></div>').attr('aria-labelledby', labelId);
      options.each(function () {
        $('<button type="button" class="pc-option" aria-pressed="false"></button>')
          .attr('data-value', this.value)
          .text($(this).text())
          .appendTo($group);
      });

      $select.addClass('pc-enhanced').attr({ 'aria-hidden': 'true', tabindex: '-1' }).after($group);
    });
    syncOptionButtons($form);
  }

  function syncOptionButtons($form) {
    $form.find('table.variations select.pc-enhanced').each(function () {
      var $select = $(this);
      var value = $select.val();
      var available = {};
      $select.find('option').each(function () {
        if (this.value !== '') { available[this.value] = !this.disabled; }
      });
      $select.siblings('.pc-options').find('.pc-option').each(function () {
        var v = $(this).attr('data-value');
        $(this).attr('aria-pressed', String(v === value));
        $(this).prop('disabled', Object.prototype.hasOwnProperty.call(available, v) ? !available[v] : true);
      });
    });
  }

  function updateSpecRows($form) {
    $form.find('table.variations select').each(function () {
      var name = $(this).attr('name');
      var $cell = $('.pc-specs [data-pc-attr="' + name + '"]');
      if (!$cell.length) { return; }
      var text = $(this).val() ? $(this).find('option:selected').text() : $cell.attr('data-pc-default');
      $cell.text(text);
    });
  }

  $(document).on('click', '.pc-option', function () {
    var $select = $(this).closest('.pc-options').siblings('select');
    var value = $(this).attr('data-value');
    $select.val($select.val() === value ? '' : value).trigger('change');
  });

  $(function () {
    $('.product-wrap form.variations_form').each(function () {
      var $form = $(this);
      var $price = $('.pc-price-amount').first();

      buildOptionButtons($form);

      $form.on('woocommerce_update_variation_values', function () { buildOptionButtons($form); });
      $form.on('change', 'table.variations select', function () {
        syncOptionButtons($form);
        updateSpecRows($form);
      });
      $form.on('found_variation', function (event, variation) {
        if (variation.price_html) { $price.html($(variation.price_html).html() || variation.price_html); }
      });
      $form.on('reset_data', function () {
        $price.html($price.attr('data-pc-default'));
        syncOptionButtons($form);
        updateSpecRows($form);
      });
    });
  });

  /* ---------- منوی موبایل ---------- */
  $(function () {
    var $toggle = $('.nav-toggle');
    var $nav = $('#siteNav');
    var $overlay = $('.nav-overlay');
    if (!$toggle.length || !$nav.length) { return; }

    function openNav() {
      $nav.addClass('is-open');
      $overlay.prop('hidden', false);
      $toggle.attr('aria-expanded', 'true');
      $('body').css('overflow', 'hidden');
      /* پس از اعمال visibility، فوکوس به داخل منو برود */
      setTimeout(function () { $nav.find('.nav-close').trigger('focus'); }, 50);
    }

    function closeNav(returnFocus) {
      if (!$nav.hasClass('is-open')) { return; }
      $nav.removeClass('is-open');
      $overlay.prop('hidden', true);
      $toggle.attr('aria-expanded', 'false');
      $('body').css('overflow', '');
      if (returnFocus) { $toggle.trigger('focus'); }
    }

    $toggle.on('click', openNav);
    $nav.find('.nav-close').on('click', function () { closeNav(true); });
    $overlay.on('click', function () { closeNav(true); });
    $(document).on('keydown', function (e) { if (e.key === 'Escape') { closeNav(true); } });
    $nav.on('click', 'a', function () { closeNav(false); });
    $(window).on('resize', function () { if (window.innerWidth > 1180) { closeNav(false); } });
  });
})(jQuery);
