/* ============================================================
   پارسیان کارتن — فرم پیش‌فروش
   ============================================================ */
(function ($) {
  'use strict';

  function faToEnDigits(str) {
    return String(str).replace(/[۰-۹]/g, function (d) {
      return '۰۱۲۳۴۵۶۷۸۹'.indexOf(d);
    });
  }

  function isValidPhone(phone) {
    var p = faToEnDigits(phone).replace(/[^0-9]/g, '');
    return /^(0?9\d{9}|98\d{10}|\+98\d{10})$/.test(p);
  }

  function showMsg($msg, text, isError) {
    $msg.removeClass('is-error is-ok').addClass(isError ? 'is-error' : 'is-ok').text(text).show();
  }

  $(document).on('submit', '.preorder-form', function (e) {
    e.preventDefault();

    var $form = $(this);
    var $msg = $form.find('.preorder-form-msg');
    var $btn = $form.find('.preorder-submit');
    var originalText = $btn.text();
    var phone = $.trim($form.find('[name="phone"]').val());

    if (!isValidPhone(phone)) {
      showMsg($msg, 'شماره موبایل معتبر وارد کنید (مثال: 09123456789).', true);
      return;
    }

    $btn.prop('disabled', true).addClass('loading').text('در حال ثبت درخواست...');

    $.post(parsianPreorder.ajaxUrl, {
      action: 'parsian_preorder_submit',
      nonce: parsianPreorder.nonce,
      product_id: $form.data('product-id'),
      quantity: $form.find('[name="quantity"]').val() || 1,
      phone: faToEnDigits(phone),
      name: $.trim($form.find('[name="name"]').val()),
      note: $.trim($form.find('[name="note"]').val())
    }, function (resp) {
      $btn.prop('disabled', false).removeClass('loading').text(originalText);
      if (resp && resp.success) {
        $form.find('input, textarea').prop('disabled', true);
        showMsg($msg, resp.data.message, false);
      } else {
        var msg = resp && resp.data && resp.data.message ? resp.data.message : 'ثبت درخواست ناموفق بود؛ دوباره تلاش کنید.';
        showMsg($msg, msg, true);
      }
    }).fail(function () {
      $btn.prop('disabled', false).removeClass('loading').text(originalText);
      showMsg($msg, 'خطا در ارتباط با سرور؛ دوباره تلاش کنید.', true);
    });
  });
})(jQuery);