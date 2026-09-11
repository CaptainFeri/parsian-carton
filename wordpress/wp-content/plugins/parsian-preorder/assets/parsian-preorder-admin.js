/* ============================================================
   پارسیان کارتن — پیش‌فروش (پیشخوان)
   ============================================================ */
jQuery(function ($) {
  $(document).on('change', '.preorder-status-select', function () {
    var $sel = $(this);
    $sel.prop('disabled', true).removeClass('parsian-preorder-saved parsian-preorder-error');

    $.post(parsianPreorder.ajaxUrl, {
      action: 'parsian_preorder_status',
      nonce: parsianPreorder.nonce,
      preorder_id: $sel.data('preorder-id'),
      status: $sel.val()
    }, function (resp) {
      $sel.prop('disabled', false);
      if (resp && resp.success) {
        $sel.addClass('parsian-preorder-saved');
        setTimeout(function () { $sel.removeClass('parsian-preorder-saved'); }, 1200);
      } else {
        $sel.addClass('parsian-preorder-error');
      }
    }).fail(function () {
      $sel.prop('disabled', false).addClass('parsian-preorder-error');
    });
  });
});