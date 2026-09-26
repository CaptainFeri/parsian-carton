/* ============================================================
   به نگار زرین پارسیان — جاوااسکریپت اصلی
   ============================================================ */
(function ($) {
  'use strict';

  /* ---------- پاپ‌آپ افزودن به سبد خرید ---------- */
  var toastTimer = null;
  var toastProgress = 0;
  var TOAST_DURATION = 4200;

  function cartonpakToast(productTitle, productImg) {
    var $existing = $('#cartToast');
    if ($existing.length) {
      $existing.remove();
    }
    var $toast = $(
      '<div class="cart-toast" id="cartToast" role="status">' +
        '<div class="cart-toast-main">' +
          '<span class="cart-toast-check">✓</span>' +
          '<div class="cart-toast-body">' +
            '<strong class="cart-toast-title">به سبد خرید اضافه شد</strong>' +
            '<span class="cart-toast-product">' + (productImg ? '<img src="' + productImg + '" alt="">' : '<span class="cart-toast-ph">📦</span>') + '<em>' + productTitle + '</em></span>' +
            '<div class="cart-toast-actions">' +
              '<a class="btn btn-primary btn-small" href="' + cartonpakData.cartUrl + '">مشاهده سبد خرید</a>' +
              '<button type="button" class="btn btn-ghost btn-small cart-toast-close">ادامه خرید</button>' +
            '</div>' +
          '</div>' +
        '</div>' +
        '<div class="cart-toast-bar"><i></i></div>' +
      '</div>'
    );

    $toast.find('.cart-toast-close').on('click', function () {
      dismissToast();
    });

    $('body').append($toast);
    requestAnimationFrame(function () {
      $toast.addClass('is-show');
    });

    startToastTimer();
  }

  function startToastTimer() {
    if (toastTimer) { clearInterval(toastTimer); toastTimer = null; }
    var $bar = $('#cartToast .cart-toast-bar i');
    toastProgress = 0;
    toastTimer = setInterval(function () {
      toastProgress += 50;
      var pct = Math.min(100, (toastProgress / TOAST_DURATION) * 100);
      if ($bar.length) { $bar.css('width', pct + '%'); }
      if (toastProgress >= TOAST_DURATION) {
        dismissToast(true);
      }
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

  /* ---------- لرزش آیکون سبد ---------- */
  function bounceCart() {
    var $cart = $('.header-cart');
    $cart.addClass('is-bounce');
    setTimeout(function () { $cart.removeClass('is-bounce'); }, 700);
  }

  $(document.body).on('added_to_cart', function (event, fragments, cart_hash, button) {
    if (fragments && fragments['.cart-count']) {
      $('.cart-count').replaceWith(fragments['.cart-count']);
    }

    var $btn = $(button);
    var $card = $btn.closest('.product-card, li.product');
    var title = $card.find('.product-card-title').text().trim();
    if (!title) {
      title = $btn.attr('data-product_id') ? 'محصول' : '';
    }
    var img = '';
    var $img = $card.find('img').first();
    if ($img.length) { img = $img.attr('src') || ''; }

    if (title) {
      cartonpakToast(title, img);
    }
    bounceCart();
  });

  $(document.body).on('removed_from_cart', function () {
    $('.cart-count').text(function () {
      return cartonpakData.cartCount ? String(cartonpakData.cartCount) : '۰';
    });
  });

  /* ---------- افزودن به سبد از صفحه محصول (بدون رفرش) ---------- */
  $(document).on('submit', 'form.cart', function (e) {
    var $form = $(this);
    if (!$form.find('.single_add_to_cart_button').length) { return; }
    e.preventDefault();

    var $btn = $form.find('.single_add_to_cart_button');
    var pid = $form.find('[name="add-to-cart"]').first().val() || $form.attr('id').replace('product-', '');
    var qty = $form.find('input[name="quantity"]').val() || 1;
    $btn.addClass('loading');

    $.post(cartonpakData.ajaxUrl, { product_id: pid, quantity: qty }, function (resp) {
      $btn.removeClass('loading');
      if (resp && resp.fragments) {
        if (resp.fragments['.cart-count']) {
          $('.cart-count').replaceWith(resp.fragments['.cart-count']);
        }
        var title = $('.product_title').text().trim();
        var img = $('.woocommerce-product-gallery__image img').first().attr('src') || $('.product-card-media img').first().attr('src') || '';
        cartonpakToast(title, img);
        bounceCart();
      }
    }).fail(function () {
      $btn.removeClass('loading');
      window.location.reload();
    });
  });

  /* ---------- دکمه‌های +/− تعداد در سبد خرید ---------- */
  $(document).on('click', '.qty-step', function () {
    var $btn = $(this);
    var $input = $btn.closest('.qty-stepper').find('.qty');
    if (!$input.length) { return; }
    var val = parseInt($input.val(), 10) || 0;
    var min = parseInt($input.attr('min'), 10);
    var max = parseInt($input.attr('max'), 10);
    if (isNaN(min)) { min = 0; }
    if (isNaN(max)) { max = 9999; }

    if ($btn.hasClass('qty-plus')) {
      if (val < max) { val += 1; }
    } else {
      if (val > min) { val -= 1; }
    }
    $input.val(val).trigger('change');
  });

  /* ---------- منوی موبایل ---------- */
  $(function () {
    var navToggle = $('#navToggle');
    var mainNav = $('#mainNav');
    var navOverlay = $('#navOverlay');

    navToggle.on('click', function () {
      var open = mainNav.hasClass('is-open');
      mainNav.toggleClass('is-open', !open);
      navOverlay.toggleClass('is-visible', !open);
      navToggle.toggleClass('is-open', !open);
      navToggle.attr('aria-expanded', String(!open));
      $('body').css('overflow', open ? '' : 'hidden');
    });
    navOverlay.on('click', function () {
      mainNav.removeClass('is-open');
      navOverlay.removeClass('is-visible');
      navToggle.removeClass('is-open');
      navToggle.attr('aria-expanded', 'false');
      $('body').css('overflow', '');
    });

    /* دسته‌بندی‌ها در موبایل */
    $('#navCatsToggle').on('click', function (e) {
      e.stopPropagation();
      $('#navCats').toggleClass('is-open');
    });

    /* ---------- اسلایدر هیرو ---------- */
    var slides = $('.hero-slide');
    var dotsWrap = $('#heroDots');
    var current = 0;

    if (slides.length > 1) {
      slides.each(function (i) {
        var d = $('<button aria-label="اسلاید ' + (i + 1) + '"></button>');
        d.on('click', function () { goTo(i); });
        dotsWrap.append(d);
      });
      $('.hero-dots button').eq(0).addClass('is-active');

      function goTo(index) {
        slides.eq(current).removeClass('is-active');
        current = (index + slides.length) % slides.length;
        slides.eq(current).addClass('is-active');
        dotsWrap.children().removeClass('is-active').eq(current).addClass('is-active');
      }

      $('#heroNext').on('click', function () { goTo(current + 1); });
      $('#heroPrev').on('click', function () { goTo(current - 1); });

      var timer = setInterval(function () { goTo(current + 1); }, 6000);
      $('.hero').on('mouseenter', function () { clearInterval(timer); })
        .on('mouseleave', function () { timer = setInterval(function () { goTo(current + 1); }, 6000); });
    } else {
      dotsWrap.hide();
      $('.hero-arrow').hide();
    }

    /* ---------- اسکرول افقی محصولات ---------- */
    $('.products-carousel').each(function () {
      var track = $(this).find('.carousel-track');
      var step = track.find('.product-card').outerWidth(true) || 260;
      $(this).find('.carousel-next').on('click', function () {
        track.animate({ scrollRight: track.scrollLeft() + step * 2 }, 300);
      });
      $(this).find('.carousel-prev').on('click', function () {
        track.animate({ scrollRight: track.scrollLeft() - step * 2 }, 300);
      });
    });

    /* ---------- خبرنامه ---------- */
    $('#newsletterForm').on('submit', function (e) {
      e.preventDefault();
      var input = $(this).find('input');
      if (!input.val().trim()) {
        alert('لطفاً شماره موبایل خود را وارد کنید.');
        return;
      }
      alert('✓ با تشکر! شماره شما در خبرنامه پارسیان کارتن ثبت شد.');
      input.val('');
    });
  });
})(jQuery);
