/* پارسیان کارتن — ورود با پیامک OTP */
(function ($) {
  'use strict';

  var form = $('#parsianOtpForm');
  if (!form.length) {
    return;
  }

  var $msg = $('.parsian-otp-msg', form);
  var $codeStep = $('.parsian-otp-code-step', form);
  var $phone = $('#parsian-otp-phone', form);
  var $code = $('#parsian-otp-code', form);
  var $sendBtn = form.find('[data-step="send"]');
  var $verifyBtn = form.find('[data-step="verify"]');
  var $resend = $('#parsianOtpResend', form);
  var $hint = $('.parsian-otp-hint', form);

  function setMessage(text, type) {
    $msg.removeClass('is-error is-success').addClass(type || '');
    $msg.text(text).show();
  }

  function lockBtn($btn, lock, text) {
    if (lock) {
      $btn.attr('disabled', 'disabled').addClass('is-loading');
      if (text) { $btn.data('txt', $btn.text()); $btn.text(text); }
    } else {
      $btn.removeAttr('disabled').removeClass('is-loading');
      if ($btn.data('txt')) { $btn.text($btn.data('txt')); $btn.removeData('txt'); }
    }
  }

  function api(action, data) {
    return $.post(parsianOtp.ajaxUrl, $.extend({}, data, {
      action: action,
      nonce: parsianOtp.nonce
    }));
  }

  function startCountdown(seconds) {
    var sec = seconds;
    $sendBtn.attr('disabled', 'disabled').addClass('is-loading');
    var t = setInterval(function () {
      $sendBtn.text('ارسال دوباره (' + sec + ')');
      sec--;
      if (sec < 0) {
        clearInterval(t);
        $sendBtn.removeAttr('disabled').removeClass('is-loading').text('ارسال دوباره کد');
      }
    }, 1000);
  }

  $sendBtn.on('click', function (e) {
    e.preventDefault();
    if (!/^(\+98|0|0098)?9[0-9]{9}$/.test($phone.val().replace(/[\s-]/g, ''))) {
      setMessage('لطفاً شماره موبایل را به‌درستی وارد کنید (مثال: 09121234567).', 'is-error');
      return;
    }
    lockBtn($sendBtn, true, 'در حال ارسال...');
    api('parsian_otp_send', { phone: $phone.val() }).done(function (r) {
      lockBtn($sendBtn, false);
      if (r.success) {
        setMessage(r.data.message, 'is-success');
        $codeStep.show();
        if (r.data.dev_code) {
          $code.val(r.data.dev_code);
          setMessage('حالت آزمایشی: پیامک واقعی ارسال نشد؛ کد تست به‌صورت خودکار پر شد.', 'is-success');
        }
        $code.trigger('focus');
        startCountdown(60);
      } else {
        setMessage(r.data.message, 'is-error');
      }
    }).fail(function () {
      lockBtn($sendBtn, false);
      setMessage('خطای ارتباط با سرور؛ دوباره تلاش کنید.', 'is-error');
    });
  });

  form.on('submit', function (e) {
    e.preventDefault();
    $verifyBtn.trigger('click');
  });

  $verifyBtn.on('click', function () {
    if ($code.val().trim() === '') {
      setMessage('کد تأیید را وارد کنید.', 'is-error');
      return;
    }
    lockBtn($verifyBtn, true, 'در حال بررسی...');
    api('parsian_otp_verify', {
      phone: $phone.val(),
      code: $code.val().trim()
    }).done(function (r) {
      lockBtn($verifyBtn, false);
      if (r.success) {
        setMessage(r.data.message, 'is-success');
        window.location.href = r.data.redirect;
      } else {
        setMessage(r.data.message, 'is-error');
      }
    }).fail(function () {
      lockBtn($verifyBtn, false);
      setMessage('خطای ارتباط با سرور؛ دوباره تلاش کنید.', 'is-error');
    });
  });

  $resend.on('click', function (e) {
    e.preventDefault();
    $sendBtn.trigger('click');
  });
})(jQuery);