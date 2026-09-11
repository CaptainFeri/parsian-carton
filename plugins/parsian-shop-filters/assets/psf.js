/*
 * پنل فیلتر و جستجوی فروشگاه — پارسیان کارتن
 *
 * بدون جاوااسکریپت هم همه‌چیز کار می‌کند (فرم GET ساده)؛ این فایل فقط تجربهٔ
 * کاربری را بهتر می‌کند: پنل کشویی موبایل، اسلایدر قیمت، پیشنهاد زندهٔ جستجو و
 * به‌روزرسانی شبکهٔ محصولات بدون بارگذاری دوبارهٔ صفحه.
 */
(function () {
  'use strict';

  var data = window.psfData || {};
  var root = document.querySelector('[data-psf-root]');

  if (!root) {
    return;
  }

  var form = root.querySelector('[data-psf-form]');
  var panel = root.querySelector('[data-psf-panel]');
  var toggle = root.querySelector('[data-psf-toggle]');
  var overlay = root.querySelector('[data-psf-overlay]');
  var busy = false;
  var suggestTimer = null;
  var suggestController = null;

  /* ------------------------------ کمکی‌ها ------------------------------ */

  function isMobile() {
    return window.matchMedia('(max-width: 782px)').matches;
  }

  function toEnglishDigits(value) {
    return String(value)
      .replace(/[۰-۹]/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'.indexOf(d); })
      .replace(/[٠-٩]/g, function (d) { return '٠١٢٣٤٥٦٧٨٩'.indexOf(d); });
  }

  function digitsOnly(value) {
    return toEnglishDigits(value).replace(/[^\d]/g, '');
  }

  function group(value) {
    return String(value).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  }

  /* --------------------------- باز و بستن پنل --------------------------- */

  function openPanel() {
    panel.classList.add('is-open');
    if (toggle) {
      toggle.setAttribute('aria-expanded', 'true');
    }
    if (isMobile() && overlay) {
      overlay.hidden = false;
      document.body.style.overflow = 'hidden';
    }
  }

  function closePanel() {
    panel.classList.remove('is-open');
    if (toggle) {
      toggle.setAttribute('aria-expanded', 'false');
    }
    if (overlay) {
      overlay.hidden = true;
    }
    document.body.style.overflow = '';
  }

  if (panel) {
    // در موبایل پنل همیشه بسته شروع می‌شود، حتی اگر تنظیمات «باز» باشد.
    if (isMobile()) {
      panel.classList.remove('is-open');
    }

    if (toggle) {
      toggle.addEventListener('click', function () {
        if (panel.classList.contains('is-open')) {
          closePanel();
        } else {
          openPanel();
        }
      });
    }

    var closeBtn = root.querySelector('[data-psf-close]');
    if (closeBtn) {
      closeBtn.addEventListener('click', closePanel);
    }
    if (overlay) {
      overlay.addEventListener('click', closePanel);
    }

    document.addEventListener('keydown', function (event) {
      if ('Escape' === event.key && panel.classList.contains('is-open') && isMobile()) {
        closePanel();
      }
    });
  }

  /* ----------------------------- اسلایدر قیمت ----------------------------- */

  function setupPriceSlider() {
    var box = root.querySelector('[data-psf-price]');

    if (!box) {
      return;
    }

    var lo = box.querySelector('[data-psf-slider-lo]');
    var hi = box.querySelector('[data-psf-slider-hi]');
    var range = box.querySelector('[data-psf-slider-range]');
    var minInput = box.querySelector('[data-psf-price-min]');
    var maxInput = box.querySelector('[data-psf-price-max]');
    var bottom = parseFloat(box.getAttribute('data-min')) || 0;
    var top = parseFloat(box.getAttribute('data-max')) || 0;
    var rtl = 'rtl' === (document.documentElement.getAttribute('dir') || '').toLowerCase();

    if (!lo || !hi || top <= bottom) {
      return;
    }

    function paint() {
      var span = top - bottom;
      var start = ((parseFloat(lo.value) - bottom) / span) * 100;
      var end = ((parseFloat(hi.value) - bottom) / span) * 100;

      range.style[rtl ? 'right' : 'left'] = start + '%';
      range.style.width = Math.max(0, end - start) + '%';
    }

    function clamp() {
      var a = parseFloat(lo.value);
      var b = parseFloat(hi.value);

      if (a > b) {
        // دستگیره‌ها جای هم را نمی‌گیرند؛ هرکدام که حرکت کرده به دیگری می‌چسبد.
        if (document.activeElement === lo) {
          lo.value = b;
        } else {
          hi.value = a;
        }
      }
    }

    function syncInputs() {
      minInput.value = parseFloat(lo.value) <= bottom ? '' : group(lo.value);
      maxInput.value = parseFloat(hi.value) >= top ? '' : group(hi.value);
    }

    [lo, hi].forEach(function (handle) {
      handle.addEventListener('input', function () {
        clamp();
        paint();
        syncInputs();
      });
      handle.addEventListener('change', function () {
        submitFilters();
      });
    });

    [minInput, maxInput].forEach(function (input) {
      if (!input) {
        return;
      }

      input.addEventListener('input', function () {
        var raw = digitsOnly(input.value);
        input.value = raw ? group(raw) : '';

        var value = raw ? parseFloat(raw) : (input === minInput ? bottom : top);
        (input === minInput ? lo : hi).value = Math.min(Math.max(value, bottom), top);
        clamp();
        paint();
      });

      input.addEventListener('change', function () {
        submitFilters();
      });
    });

    // مقدار اولیه را با گروه‌بندی هزارگان نشان می‌دهد.
    [minInput, maxInput].forEach(function (input) {
      if (input && input.value) {
        input.value = group(digitsOnly(input.value));
      }
    });

    paint();
  }

  setupPriceSlider();

  /* -------------------------- به‌روزرسانی آژاکسی -------------------------- */

  function buildUrl() {
    var params = new URLSearchParams();

    Array.prototype.forEach.call(form.elements, function (field) {
      if (!field.name || field.disabled) {
        return;
      }

      if (('checkbox' === field.type || 'radio' === field.type) && !field.checked) {
        return;
      }

      var value = field.value;

      if ('psf_min' === field.name || 'psf_max' === field.name) {
        value = digitsOnly(value);
      }

      if ('' === String(value).trim()) {
        return;
      }

      params.append(field.name, value);
    });

    var query = params.toString();

    return form.getAttribute('action') + (query ? (form.getAttribute('action').indexOf('?') > -1 ? '&' : '?') + query : '');
  }

  function swap(selector, doc, container, before) {
    var incoming = doc.querySelector(selector);
    var current = container.querySelector(selector);

    // گره از سند دیگری می‌آید و باید پیش از درج به سند جاری منتقل شود.
    if (incoming) {
      incoming = document.importNode(incoming, true);
    }

    if (incoming && current) {
      current.parentNode.replaceChild(incoming, current);
    } else if (incoming && !current) {
      if (before && before.parentNode === container) {
        container.insertBefore(incoming, before);
      } else {
        container.appendChild(incoming);
      }
    } else if (!incoming && current) {
      current.parentNode.removeChild(current);
    }
  }

  function refreshCounts(doc) {
    var incoming = doc.querySelectorAll('[data-psf-panel] .psf-option input[type="checkbox"]');

    Array.prototype.forEach.call(incoming, function (field) {
      var selector = '[data-psf-panel] input[name="' + field.name + '"][value="' + field.value + '"]';
      var current = root.querySelector(selector);

      if (!current) {
        return;
      }

      var from = field.parentNode.querySelector('.psf-count');
      var to = current.parentNode.querySelector('.psf-count');

      if (from && to) {
        to.textContent = from.textContent;
      }

      // وضعیت انتخاب کاربر دست‌نخورده می‌ماند؛ فقط در دسترس بودن گزینه به‌روز می‌شود.
      current.disabled = field.disabled && !current.checked;
      current.parentNode.classList.toggle('is-empty', current.disabled);
    });
  }

  function setBusy(state) {
    busy = state;
    var container = document.querySelector('.shop-wrap') || root.parentNode;

    if (!container) {
      return;
    }

    container.classList.toggle('psf-busy', state);

    var loader = root.querySelector('.psf-loading');

    if (state && !loader) {
      loader = document.createElement('div');
      loader.className = 'psf-loading';
      loader.innerHTML = '<span class="psf-spinner"></span><span></span>';
      loader.lastChild.textContent = (data.i18n && data.i18n.loading) || '…';
      root.appendChild(loader);
    } else if (!state && loader) {
      loader.parentNode.removeChild(loader);
    }
  }

  function load(url, push) {
    if (!data.useAjax || !window.fetch || !window.DOMParser) {
      window.location.href = url;
      return;
    }

    if (busy) {
      return;
    }

    setBusy(true);

    fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (response) {
        if (!response.ok) {
          throw new Error('bad status');
        }
        return response.text();
      })
      .then(function (html) {
        var doc = new DOMParser().parseFromString(html, 'text/html');
        var container = document.querySelector('.shop-wrap') || root.parentNode;

        ['.woocommerce-result-count', 'ul.products', '.woocommerce-pagination', '.psf-empty', '.woocommerce-info'].forEach(function (selector) {
          swap(selector, doc, container);
        });

        swap('.psf-chips', doc, form, panel);

        var incomingBadge = doc.querySelector('[data-psf-toggle] .psf-badge');
        var currentBadge = toggle ? toggle.querySelector('.psf-badge') : null;

        if (incomingBadge && currentBadge) {
          currentBadge.textContent = incomingBadge.textContent;
        } else if (incomingBadge && toggle) {
          toggle.appendChild(document.importNode(incomingBadge, true));
        } else if (!incomingBadge && currentBadge) {
          currentBadge.parentNode.removeChild(currentBadge);
        }

        refreshCounts(doc);

        if (false !== push) {
          window.history.pushState({ psf: true }, '', url);
        }

        setBusy(false);

        var anchor = document.querySelector('.shop-toolbar') || root;
        var offset = anchor.getBoundingClientRect().top + window.pageYOffset - 90;

        if (window.pageYOffset > offset) {
          window.scrollTo({ top: offset, behavior: 'smooth' });
        }
      })
      .catch(function () {
        // در صورت هر خطایی به رفتار عادی مرورگر برمی‌گردیم.
        setBusy(false);
        window.location.href = url;
      });
  }

  function submitFilters() {
    load(buildUrl(), true);
  }

  if (form) {
    form.addEventListener('submit', function (event) {
      if (!data.useAjax) {
        return;
      }

      event.preventDefault();
      closeSuggest();

      if (isMobile()) {
        closePanel();
      }

      submitFilters();
    });

    form.addEventListener('change', function (event) {
      var field = event.target;

      if (!field.name || 'psf_min' === field.name || 'psf_max' === field.name) {
        return;
      }

      if ('checkbox' === field.type) {
        submitFilters();
      }
    });
  }

  // تراشه‌ها، صفحه‌بندی و مرتب‌سازی هم آژاکسی می‌شوند.
  document.addEventListener('click', function (event) {
    if (!data.useAjax) {
      return;
    }

    var link = event.target.closest('.psf-chip, .woocommerce-pagination a, .psf-empty-reset, .psf-reset, .psf-search-clear, .psf-option-link');

    if (!link || !link.href || event.metaKey || event.ctrlKey || 1 === event.button) {
      return;
    }

    if (link.classList.contains('psf-option-link')) {
      // پیوند به بایگانی دستهٔ دیگر است، نه فیلتر همین صفحه.
      return;
    }

    event.preventDefault();
    load(link.href, true);
  });

  document.addEventListener('change', function (event) {
    if (!data.useAjax || !event.target.closest('.woocommerce-ordering')) {
      return;
    }

    var select = event.target;
    var params = new URLSearchParams(window.location.search);

    params.set('orderby', select.value);
    params.delete('paged');

    event.preventDefault();
    load(window.location.pathname + '?' + params.toString(), true);
  });

  window.addEventListener('popstate', function () {
    if (data.useAjax) {
      load(window.location.href, false);
    }
  });

  /* ---------------------------- جستجوی زنده ---------------------------- */

  var searchBox = root.querySelector('[data-psf-search]');
  var searchInput = root.querySelector('[data-psf-search-input]');
  var suggestBox = root.querySelector('[data-psf-suggest]');

  function closeSuggest() {
    if (suggestBox) {
      suggestBox.hidden = true;
      suggestBox.innerHTML = '';
    }
  }

  function renderSuggest(payload) {
    if (!suggestBox) {
      return;
    }

    if (!payload.items.length) {
      suggestBox.innerHTML = '<span class="psf-suggest-note"></span>';
      suggestBox.firstChild.textContent = (data.i18n && data.i18n.noSuggest) || '';
      suggestBox.hidden = false;
      return;
    }

    suggestBox.innerHTML = '';

    payload.items.forEach(function (item) {
      var link = document.createElement('a');
      link.className = 'psf-suggest-item';
      link.href = item.url;

      var img = document.createElement('img');
      img.src = item.image;
      img.alt = '';
      img.loading = 'lazy';

      var body = document.createElement('div');
      body.className = 'psf-suggest-body';

      var title = document.createElement('span');
      title.className = 'psf-suggest-title';
      title.textContent = item.title;

      var price = document.createElement('span');
      price.className = 'psf-suggest-price';
      price.textContent = item.price;

      body.appendChild(title);
      body.appendChild(price);
      link.appendChild(img);
      link.appendChild(body);
      suggestBox.appendChild(link);
    });

    if (payload.more) {
      var more = document.createElement('a');
      more.className = 'psf-suggest-more';
      more.href = payload.more;
      more.textContent = (data.i18n && data.i18n.allResults) || '';
      suggestBox.appendChild(more);
    }

    suggestBox.hidden = false;
  }

  function fetchSuggest(term) {
    if (suggestController) {
      suggestController.abort();
    }

    suggestController = window.AbortController ? new AbortController() : null;

    var body = new URLSearchParams();
    body.set('action', 'psf_suggest');
    body.set('nonce', data.nonce || '');
    body.set('term', term);
    body.set('category', root.getAttribute('data-category') || '');

    fetch(data.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      body: body,
      signal: suggestController ? suggestController.signal : undefined
    })
      .then(function (response) { return response.json(); })
      .then(function (json) {
        if (json && json.success) {
          renderSuggest(json.data);
        }
      })
      .catch(function () {
        closeSuggest();
      });
  }

  if (searchInput && data.liveSearch && window.fetch) {
    searchInput.addEventListener('input', function () {
      var term = searchInput.value.trim();

      window.clearTimeout(suggestTimer);

      if (term.length < 2) {
        closeSuggest();
        return;
      }

      suggestTimer = window.setTimeout(function () {
        fetchSuggest(term);
      }, 280);
    });

    searchInput.addEventListener('focus', function () {
      if (suggestBox && suggestBox.innerHTML && searchInput.value.trim().length >= 2) {
        suggestBox.hidden = false;
      }
    });

    document.addEventListener('click', function (event) {
      if (searchBox && !searchBox.contains(event.target)) {
        closeSuggest();
      }
    });

    searchInput.addEventListener('keydown', function (event) {
      if ('Escape' === event.key) {
        closeSuggest();
      }
    });
  }
})();
