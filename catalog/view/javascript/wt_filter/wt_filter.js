/**
 * WT Filter — AJAX без reload.
 * Клик → лёгкий callback (счётчики) → debounce → refresh (HTML товаров) → pushState
 */
(function ($) {
  'use strict';

  var CALLBACK_MS = 80;
  var REFRESH_MS = 320;
  var xhrCallback = null;
  var xhrRefresh = null;
  var timerCallback = null;
  var timerRefresh = null;

  function getRoot() {
    return $('#wt-filter');
  }

  function getUrlKey($root) {
    return $root.data('url-key') || 'filter_wt_filter';
  }

  function collectParams($root) {
    var parts = [];

    $root.find('.wt-filter__group').each(function () {
      var $group = $(this);
      var optionId = String($group.data('option'));
      var type = String($group.data('type'));
      var values = [];

      if (type === 'price') {
        var min = parseFloat($group.find('[data-price-range="from"]').attr('min'));
        var max = parseFloat($group.find('[data-price-range="to"]').attr('max'));
        var from = parseFloat($group.find('[data-price="from"]').val());
        var to = parseFloat($group.find('[data-price="to"]').val());

        if (!isNaN(from) && !isNaN(to) && (from > min || to < max)) {
          if (from > to) {
            var tmp = from;
            from = to;
            to = tmp;
          }
          parts.push('p:' + from + '-' + to);
        }
        return;
      }

      if (type === 'slider_range' || type === 'slide_dual') {
        var sMin = parseFloat($group.find('[data-slide-range="from"]').attr('min') || $group.find('[data-slide="from"]').attr('min'));
        var sMax = parseFloat($group.find('[data-slide-range="to"]').attr('max') || $group.find('[data-slide="to"]').attr('max'));
        var sFrom = parseFloat($group.find('[data-slide-range="from"]').val() || $group.find('[data-slide="from"]').val());
        var sTo = parseFloat($group.find('[data-slide-range="to"]').val() || $group.find('[data-slide="to"]').val());
        var sScale = getGroupScale($group);

        if (!isNaN(sFrom) && !isNaN(sTo) && (sFrom > sMin || sTo < sMax)) {
          if (sFrom > sTo) {
            var t = sFrom;
            sFrom = sTo;
            sTo = t;
          }
          parts.push(optionId + ':' + formatRangeValue(sFrom, sScale) + '-' + formatRangeValue(sTo, sScale));
        }
        return;
      }

      if (type === 'slider_single' || type === 'slide') {
        var ssMin = parseFloat($group.find('[data-slide-range="to"]').attr('min') || $group.find('[data-slide="to"]').attr('min'));
        var ssMax = parseFloat($group.find('[data-slide-range="to"]').attr('max') || $group.find('[data-slide="to"]').attr('max'));
        var ssTo = parseFloat($group.find('[data-slide-range="to"]').val() || $group.find('[data-slide="to"]').val());
        var ssScale = getGroupScale($group);

        // Порог: от min категории до выбранного значения
        if (!isNaN(ssTo) && !isNaN(ssMin) && !isNaN(ssMax) && ssTo < ssMax) {
          parts.push(optionId + ':' + formatRangeValue(ssMin, ssScale) + '-' + formatRangeValue(ssTo, ssScale));
        }
        return;
      }

      if (type === 'select') {
        var selected = $group.find('[data-select]').val();
        if (selected) {
          parts.push(optionId + ':' + selected);
        }
        return;
      }

      $group.find('input:checked').each(function () {
        values.push(String(this.value));
      });

      if (values.length) {
        parts.push(optionId + ':' + values.join(','));
      }
    });

    return parts.join(';');
  }

  function readListState() {
    var sortVal = $('#input-sort').val() || '';
    var limitVal = $('#input-limit').val() || '';
    var sort = 'p.sort_order';
    var order = 'ASC';
    var limit = 0;

    if (sortVal) {
      try {
        var u = new URL(sortVal, window.location.origin);
        sort = u.searchParams.get('sort') || sort;
        order = u.searchParams.get('order') || order;
      } catch (e) {
        var m = /[?&]sort=([^&]+)/.exec(sortVal);
        var o = /[?&]order=([^&]+)/.exec(sortVal);
        if (m) sort = decodeURIComponent(m[1]);
        if (o) order = decodeURIComponent(o[1]);
      }
    }

    if (limitVal) {
      try {
        var ul = new URL(limitVal, window.location.origin);
        limit = parseInt(ul.searchParams.get('limit'), 10) || 0;
      } catch (e2) {
        var lm = /[?&]limit=([^&]+)/.exec(limitVal);
        if (lm) limit = parseInt(lm[1], 10) || 0;
      }
    }

    return { sort: sort, order: order, limit: limit };
  }

  function valueTotal(values, key) {
    if (!values || !Object.prototype.hasOwnProperty.call(values, key)) {
      return 0;
    }

    var item = values[key];

    if (item && typeof item === 'object' && Object.prototype.hasOwnProperty.call(item, 't')) {
      return parseInt(item.t, 10) || 0;
    }

    return parseInt(item, 10) || 0;
  }

  function valuesLimit($root) {
    var n = parseInt($root.attr('data-values-limit'), 10);
    return n > 0 ? n : 8;
  }

  function formatMoreText($root, count) {
    var tpl = String($root.attr('data-text-more') || 'Show more (%s)');
    return tpl.replace('%s', String(count));
  }

  function refreshShowMore($root) {
    if (!$root || !$root.length) {
      $root = getRoot();
    }

    if (!$root.length) {
      return;
    }

    var limit = valuesLimit($root);
    var textLess = String($root.attr('data-text-less') || 'Show less');

    $root.find('.wt-filter__values').each(function () {
      var $values = $(this);
      var expanded = $values.hasClass('is-expanded');
      var $items = $values.find('> .wt-filter__value, > .wt-filter__tile');

      $items.removeClass('is-collapsed');

      var $available = $items.filter(function () {
        return !$(this).hasClass('is-hidden');
      });
      var $btn = $values.next('.wt-filter__more');

      if ($available.length <= limit) {
        if ($btn.length) {
          $btn.remove();
        }
        $values.removeClass('is-expanded');
        return;
      }

      if (!expanded) {
        var index = 0;
        $available.each(function () {
          var $item = $(this);
          var checked = $item.find('input').is(':checked');

          if (index < limit) {
            index += 1;
            return;
          }

          // Выбранные оставляем видимыми, даже если они дальше лимита
          if (!checked) {
            $item.addClass('is-collapsed');
          }
        });
      }

      var hiddenCount = $available.filter('.is-collapsed').length;

      if (!$btn.length) {
        $btn = $('<button type="button" class="wt-filter__more"></button>');
        $values.after($btn);
      }

      if (expanded) {
        $btn.html(textLess + ' <i class="fa fa-angle-down"></i>').attr('aria-expanded', 'true');
      } else {
        $btn.html(formatMoreText($root, hiddenCount || ($available.length - limit)) + ' <i class="fa fa-angle-down"></i>').attr('aria-expanded', 'false');
      }
    });
  }

  function updateCounters(values) {
    if (!values) return;

    var $root = getRoot();

    if ($root.data('show-counts') === 0 || $root.data('show-counts') === '0' || $root.data('show-counts') === false) {
      return;
    }

    $('[data-count-key]').each(function () {
      var key = String($(this).data('count-key'));
      var total = valueTotal(values, key);
      var $el = $(this);

      if ($el.is('option')) {
        var name = $el.text().replace(/\s*\(\d+\)\s*$/, '');
        var selected = $el.is(':selected');

        $el.text(name + ' (' + total + ')');

        // 0 товаров — скрыть; выбранный оставляем, чтобы можно было снять
        if (total < 1 && !selected) {
          $el.prop('hidden', true).prop('disabled', true);
        } else {
          $el.prop('hidden', false).prop('disabled', false);
        }
      } else {
        $el.text(total);

        var $label = $el.closest('.wt-filter__value, .wt-filter__tile');
        var $input = $label.find('input');
        var checked = $input.is(':checked');

        if (total < 1 && !checked) {
          $label.addClass('is-hidden').removeClass('is-disabled is-collapsed').css('display', '');
          $input.prop('disabled', true);
        } else {
          $label.removeClass('is-hidden is-disabled').css('display', '');
          $input.prop('disabled', false);
        }
      }
    });

    updateEmptyGroups();
    refreshShowMore(getRoot());
  }

  function updateEmptyGroups() {
    getRoot().find('.wt-filter__group').each(function () {
      var $group = $(this);
      var type = String($group.data('type') || '');

      if (type === 'price' || type === 'slider_range' || type === 'slider_single' || type === 'slide_dual' || type === 'slide') {
        return;
      }

      if (type === 'select') {
        var $select = $group.find('select[data-select]');
        var hasOptions = $select.find('option[value!=""]:not([hidden])').length > 0;
        var hasValue = !!$select.val();
        $group.toggle(hasOptions || hasValue);
        return;
      }

      var $items = $group.find('.wt-filter__value, .wt-filter__tile').not('.is-hidden');
      $group.toggle($items.length > 0);
    });
  }

  function ensureResultsShell() {
    if ($('#wt-filter-results').length) {
      return $('#wt-filter-results');
    }

    // OC4 default: #product-list; OC3 themes: .product-layout inside .row
    var $productsRow = $('#product-list');
    if (!$productsRow.length) {
      $productsRow = $('#content .product-layout').first().closest('.row');
    }

    var $pagerRow = $();
    if ($productsRow.length) {
      $pagerRow = $productsRow.nextAll('.row').filter(function () {
        return $(this).find('.pagination, .text-start, .text-end').length > 0;
      }).first();
      if (!$pagerRow.length) {
        $pagerRow = $productsRow.next('.row');
      }
    }

    if (!$productsRow.length) {
      var $host = $('<div id="wt-filter-results"><div id="wt-filter-products"></div><div id="wt-filter-pagination"></div></div>');
      $('#content').append($host);
      return $host;
    }

    $productsRow.attr('id', 'wt-filter-products');

    if ($pagerRow.length) {
      $pagerRow.attr('id', 'wt-filter-pagination');
    } else {
      $pagerRow = $('<div class="row" id="wt-filter-pagination"></div>').insertAfter($productsRow);
    }

    return $productsRow.add($pagerRow).wrapAll('<div id="wt-filter-results"></div>').end();
  }

  function applyDisplayMode() {
    var display = localStorage.getItem('display');

    if (display === 'list') {
      $('#list-view').trigger('click');
    } else {
      $('#grid-view').trigger('click');
    }
  }

  function pushUrl(href) {
    if (!href || !window.history || !window.history.pushState) {
      return;
    }

    href = String(href).replace(/&amp;/g, '&');
    window.history.pushState({ wtFilter: true }, '', href);
  }

  function applyCallback(json) {
    updateCounters(json.values);

    if (json.text_total) {
      getRoot().find('[data-role="status"]').text(json.text_total);
    }

    if (json.href) {
      pushUrl(json.href);
    }

    applySeo(json.seo);
    refreshMobileNavState(getRoot());
  }

  function applyProducts(json) {
    var $results = $('#wt-filter-results');

    if (!$results.length) {
      ensureResultsShell();
      $results = $('#wt-filter-results');
    }

    if (json.products) {
      $results.html(json.products);
      applyDisplayMode();
    }

    updateCounters(json.values);

    if (json.text_total) {
      getRoot().find('[data-role="status"]').text(json.text_total);
    }

    pushUrl(json.href || json.url);
    applySeo(json.seo);
    refreshMobileNavState(getRoot());
  }

  function getSeoTextPosition() {
    var pos = $('#content').data('seo-text-position') || getRoot().data('seo-text-position') || 'below';
    return pos === 'above' ? 'above' : 'below';
  }

  function ensureSeoTextBlock() {
    var $seoText = $('#filter-seo-text');
    var position = getSeoTextPosition();
    var $h1 = $('#content h1').first();
    var $results = $('#wt-filter-results');

    if (!$seoText.length) {
      $seoText = $('<div id="filter-seo-text" class="wt-filter-seo-text"></div>');
    }

    if (position === 'below') {
      if ($results.length) {
        $results.after($seoText);
      } else if ($h1.length) {
        $('#content').append($seoText);
      } else {
        $('#content').append($seoText);
      }
    } else if ($h1.length) {
      $h1.after($seoText);
    } else {
      $('#content').prepend($seoText);
    }

    return $seoText;
  }

  function applySeo(seo) {
    var $h1 = $('#content h1').first();
    var $seoText = ensureSeoTextBlock();

    if (!$h1.data('wt-default-h1')) {
      $h1.data('wt-default-h1', $h1.text());
    }

    if (seo) {
      if (seo.title) {
        document.title = seo.title;
      }

      if (seo.description) {
        $('meta[name="description"]').attr('content', seo.description);
      }

      if (seo.keywords) {
        var $kw = $('meta[name="keywords"]');
        if ($kw.length) {
          $kw.attr('content', seo.keywords);
        } else {
          $('head').append($('<meta name="keywords">').attr('content', seo.keywords));
        }
      }

      if (seo.h1) {
        $h1.text(seo.h1);
      }

      if (seo.seo_text) {
        $seoText.html(seo.seo_text).show();
      } else {
        $seoText.empty().hide();
      }

      if (seo.breadcrumb) {
        var $lastBc = $('.breadcrumb li.filter-seo-bc');

        if (!$lastBc.length) {
          $('.breadcrumb').append(
            $('<li class="filter-seo-bc"></li>').append($('<span></span>').text(seo.breadcrumb))
          );
        } else {
          $lastBc.find('span, a').text(seo.breadcrumb);
        }
      }
    } else {
      if ($h1.data('wt-default-h1')) {
        $h1.text($h1.data('wt-default-h1'));
      }

      $seoText.empty().hide();
      $('.breadcrumb li.filter-seo-bc').remove();
    }
  }

  var spinnerScrollBound = false;

  function updateSpinnerPosition() {
    var $results = $('#wt-filter-results');

    if (!$results.length || !$results.hasClass('is-updating')) {
      return;
    }

    // На мобилке со открытым drawer спиннер в окне фильтра
    if ($('body').hasClass('wt-filter-mobile-open')) {
      return;
    }

    var el = $results[0];
    var rect = el.getBoundingClientRect();
    var viewTop = 0;
    var viewBottom = window.innerHeight || document.documentElement.clientHeight;
    var viewLeft = 0;
    var viewRight = window.innerWidth || document.documentElement.clientWidth;

    var visibleTop = Math.max(rect.top, viewTop);
    var visibleBottom = Math.min(rect.bottom, viewBottom);
    var visibleLeft = Math.max(rect.left, viewLeft);
    var visibleRight = Math.min(rect.right, viewRight);

    if (visibleBottom <= visibleTop || visibleRight <= visibleLeft) {
      return;
    }

    var x = (visibleLeft + visibleRight) / 2;
    var y = (visibleTop + visibleBottom) / 2;

    el.style.setProperty('--wt-spinner-x', x + 'px');
    el.style.setProperty('--wt-spinner-y', y + 'px');
  }

  function bindSpinnerPosition(active) {
    if (active && !spinnerScrollBound) {
      spinnerScrollBound = true;
      $(window).on('scroll.wtFilterSpinner resize.wtFilterSpinner', updateSpinnerPosition);
    } else if (!active && spinnerScrollBound) {
      spinnerScrollBound = false;
      $(window).off('scroll.wtFilterSpinner resize.wtFilterSpinner');
    }
  }

  function setBusy(busy) {
    ensureResultsShell();
    getRoot().toggleClass('is-loading', !!busy);
    $('#wt-filter-results').toggleClass('is-updating', !!busy);
    bindSpinnerPosition(!!busy);

    if (busy) {
      // после paint, чтобы размеры блока были актуальны
      window.requestAnimationFrame(function () {
        updateSpinnerPosition();
      });
    }
  }

  function isMobileFilter() {
    return window.matchMedia && window.matchMedia('(max-width: 991px)').matches;
  }

  function groupHasActive($group) {
    var type = String($group.data('type'));

    if (type === 'price' || type === 'slider_range' || type === 'slide_dual') {
      var $from = $group.find('[data-price-range="from"], [data-slide-range="from"]').first();
      var $to = $group.find('[data-price-range="to"], [data-slide-range="to"]').first();
      if (!$from.length || !$to.length) return false;
      return String($from.val()) !== String($from.attr('min')) || String($to.val()) !== String($to.attr('max'));
    }

    if (type === 'slider_single' || type === 'slide') {
      var $single = $group.find('[data-slide-range="to"]').first();
      return $single.length && String($single.val()) !== String($single.attr('max'));
    }

    if (type === 'select') {
      return !!$group.find('[data-select]').val();
    }

    return $group.find('input:checked').length > 0;
  }

  function refreshMobileNavState($root) {
    if (!$root || !$root.length) {
      $root = getRoot();
    }

    $root.find('.wt-filter__group').each(function () {
      $(this).toggleClass('has-active', groupHasActive($(this)));
    });
  }

  function prepareAccordion($root) {
    $root.find('.wt-filter__group').each(function () {
      var $group = $(this);
      var $title = $group.children('.wt-filter__title').first();

      if (!$title.length) {
        return;
      }

      if (!$group.children('.wt-filter__panel').length) {
        $title.nextAll().wrapAll('<div class="wt-filter__panel"></div>');
      }

      if (!$title.find('.wt-filter__chevron').length) {
        $title.append('<i class="fa fa-angle-down wt-filter__chevron" aria-hidden="true"></i>');
      }

      $title.attr({
        role: 'button',
        tabindex: '0'
      });
    });

    // Desktop: открыты; mobile: свёрнуты (один раз)
    if (!$root.data('wt-accordion-ready')) {
      setDefaultAccordionState($root);
      $root.data('wt-accordion-ready', true);
    }

    refreshMobileNavState($root);
  }

  function setDefaultAccordionState($root) {
    var mobile = isMobileFilter();

    $root.find('.wt-filter__group').each(function () {
      var $group = $(this);
      var raw = mobile ? $group.attr('data-expanded-mobile') : $group.attr('data-expanded-desktop');
      var expanded;

      if (raw === undefined || raw === null || raw === '') {
        expanded = !mobile;
      } else {
        expanded = String(raw) === '1' || String(raw) === 'true';
      }

      $group.toggleClass('is-expanded', expanded);
      $group.children('.wt-filter__title').attr('aria-expanded', expanded ? 'true' : 'false');
    });
  }

  function syncMobileMount($root) {
    var $fab = $('#wt-filter-fab');
    var $slot = $('#wt-filter-slot');

    if (!$slot.length) {
      $slot = $('<div id="wt-filter-slot"></div>');
      if ($fab.length) {
        $fab.before($slot);
      } else {
        $root.before($slot);
      }
    }

    if (isMobileFilter()) {
      if (!$root.parent().is('body')) {
        $('body').append($fab);
        $('body').append($root);
      }
    } else {
      closeMobileFilter($root, true);
      if ($fab.length && !$fab.next().is($slot) && !$slot.prev().is($fab)) {
        $slot.before($fab);
      }
      if (!$slot.next().is($root)) {
        $slot.after($root);
      }
    }
  }

  function openMobileFilter($root) {
    if (!$root.length) return;
    syncMobileMount($root);
    prepareAccordion($root);

    // Если страница грузилась на desktop — при первом открытии drawer применить mobile defaults
    if (!$root.data('wt-mobile-defaults-once')) {
      setDefaultAccordionState($root);
      $root.data('wt-mobile-defaults-once', true);
    }

    $root.addClass('is-open');
    $('body').addClass('wt-filter-mobile-open');
    $('#wt-filter-fab').attr('aria-expanded', 'true');
  }

  function closeMobileFilter($root, silent) {
    if (!$root.length) return;
    $root.removeClass('is-open');
    $('body').removeClass('wt-filter-mobile-open');
    $('#wt-filter-fab').attr('aria-expanded', 'false');
    if (!silent) {
      refreshMobileNavState($root);
    }
  }

  function toggleAccordionGroup($group) {
    var expanded = !$group.hasClass('is-expanded');
    $group.toggleClass('is-expanded', expanded);
    $group.children('.wt-filter__title').attr('aria-expanded', expanded ? 'true' : 'false');
  }

  function baseData($root, page) {
    var list = readListState();
    var urlKey = getUrlKey($root);
    var manufacturerId = parseInt($root.data('manufacturer-id'), 10) || 0;
    var special = parseInt($root.data('special'), 10) === 1;
    var data = {
      path: $root.data('path') || '',
      sort: list.sort,
      order: list.order,
      page: page || 1
    };

    if (manufacturerId > 0) {
      data.manufacturer_id = manufacturerId;
    }

    if (special) {
      data.special = 1;
    }

    data[urlKey] = collectParams($root);

    if (list.limit > 0) {
      data.limit = list.limit;
    }

    return data;
  }

  function runCallback() {
    var $root = getRoot();

    if (!$root.length || !$root.data('callback')) {
      return;
    }

    if (xhrCallback && xhrCallback.readyState !== 4) {
      xhrCallback.abort();
    }

    xhrCallback = $.ajax({
      url: $root.data('callback'),
      type: 'post',
      dataType: 'json',
      data: baseData($root, 1),
      success: function (json) {
        if (json && json.success) {
          applyCallback(json);
        }
      }
    });
  }

  function runRefresh(options) {
    var $root = getRoot();

    if (!$root.length) {
      return;
    }

    options = options || {};

    if (xhrRefresh && xhrRefresh.readyState !== 4) {
      xhrRefresh.abort();
    }

    setBusy(true);

    xhrRefresh = $.ajax({
      url: $root.data('refresh'),
      type: 'post',
      dataType: 'json',
      data: baseData($root, options.page || 1),
      success: function (json) {
        setBusy(false);

        if (!json || !json.success) {
          return;
        }

        applyProducts(json);
      },
      error: function (jqXHR, textStatus) {
        if (textStatus !== 'abort') {
          setBusy(false);
        }
      }
    });
  }

  function scheduleFilter() {
    clearTimeout(timerCallback);
    clearTimeout(timerRefresh);

    setBusy(true);

    timerCallback = setTimeout(runCallback, CALLBACK_MS);
    timerRefresh = setTimeout(function () {
      runRefresh({ page: 1 });
    }, REFRESH_MS);
  }

  function getGroupScale($group) {
    var scale = parseInt($group.find('[data-scale]').data('scale'), 10);
    if (!isNaN(scale) && scale >= 0) {
      return scale;
    }

    var step = parseFloat($group.find('[data-step]').data('step') || $group.find('[data-slide-range], [data-price-range]').first().attr('step'));
    if (!isNaN(step) && step > 0 && step < 1) {
      return Math.max(0, Math.round(Math.log10(1 / step)));
    }

    return 0;
  }

  function formatRangeValue(val, scale) {
    if (isNaN(val)) {
      return val;
    }

    scale = parseInt(scale, 10) || 0;

    if (scale <= 0) {
      return String(Math.round(val));
    }

    return parseFloat(val.toFixed(scale)).toString();
  }

  function updateDualFill($group) {
    var $slider = $group.find('.wt-filter__dual-slider');

    if (!$slider.length) {
      return;
    }

    var $fromRange = $group.find('[data-price-range="from"], [data-slide-range="from"]').first();
    var $toRange = $group.find('[data-price-range="to"], [data-slide-range="to"]').first();
    var min = parseFloat($slider.data('min'));
    var max = parseFloat($slider.data('max'));
    var from = parseFloat($fromRange.val());
    var to = parseFloat($toRange.val());

    if (isNaN(min) || isNaN(max) || max <= min) {
      return;
    }

    if (isNaN(from)) from = min;
    if (isNaN(to)) to = max;

    var left = ((from - min) / (max - min)) * 100;
    var right = ((to - min) / (max - min)) * 100;

    $slider.find('[data-role="fill"]').css({
      left: left + '%',
      width: Math.max(0, right - left) + '%'
    });

    if (from > max - (max - min) * 0.02) {
      $fromRange.css('z-index', 5);
      $toRange.css('z-index', 4);
    } else {
      $fromRange.css('z-index', 3);
      $toRange.css('z-index', 4);
    }
  }

  function syncRangeInputs($group, source) {
    var $fromNum = $group.find('[data-price="from"], [data-slide="from"]').first();
    var $toNum = $group.find('[data-price="to"], [data-slide="to"]').first();
    var $fromRange = $group.find('[data-price-range="from"], [data-slide-range="from"]').first();
    var $toRange = $group.find('[data-price-range="to"], [data-slide-range="to"]').first();
    var scale = getGroupScale($group);
    var isSingle = !$fromRange.length || $fromRange.is($toRange);

    if (isSingle || !$fromRange.length) {
      var singleVal = parseFloat($toRange.val());
      if (!isNaN(singleVal) && $toNum.length) {
        var formattedSingle = formatRangeValue(singleVal, scale);
        $toNum.val(formattedSingle);
        $toRange.val(formattedSingle);
      }
      return;
    }

    var min = parseFloat($fromRange.attr('min'));
    var max = parseFloat($toRange.attr('max'));
    var from = parseFloat($fromRange.val());
    var to = parseFloat($toRange.val());

    if (isNaN(from)) from = min;
    if (isNaN(to)) to = max;

    if (source === 'from' && from > to) {
      from = to;
    } else if (source === 'to' && to < from) {
      to = from;
    } else if (from > to) {
      var t = from;
      from = to;
      to = t;
    }

    from = formatRangeValue(from, scale);
    to = formatRangeValue(to, scale);

    $fromRange.val(from);
    $toRange.val(to);
    if ($fromNum.length) $fromNum.val(from);
    if ($toNum.length) $toNum.val(to);
    updateDualFill($group);
  }

  var brandSearchTimer = null;
  var brandSearchXhr = null;

  function hideBrandSuggest($group) {
    $group.find('[data-role="brand-suggest"]').attr('hidden', true).empty();
  }

  function selectBrand($group, valueId) {
    var $input = $group.find('input[type="checkbox"][value="' + String(valueId) + '"]');

    if (!$input.length) {
      return;
    }

    var $label = $input.closest('.wt-filter__value');
    var $values = $group.find('.wt-filter__values');

    $label.removeClass('is-collapsed is-hidden');
    $values.addClass('is-expanded');
    refreshShowMore(getRoot());

    if (!$input.prop('checked')) {
      $input.prop('checked', true).trigger('change');
    } else {
      $label.addClass('is-selected');
    }

    $group.find('[data-role="brand-search"]').val('');
    hideBrandSuggest($group);
  }

  function renderBrandSuggest($group, items) {
    var $box = $group.find('[data-role="brand-suggest"]');
    var emptyText = getRoot().data('text-brand-empty') || '';

    if (!items || !items.length) {
      $box.html('<div class="wt-filter__suggest-empty">' + $('<div>').text(emptyText).html() + '</div>').removeAttr('hidden');
      return;
    }

    var html = '';

    var showCounts = !(getRoot().data('show-counts') === 0 || getRoot().data('show-counts') === '0' || getRoot().data('show-counts') === false);

    $.each(items, function (i, item) {
      html += '<button type="button" class="wt-filter__suggest-item" data-value-id="' + $('<div>').text(item.value_id).html() + '">';
      html += '<span class="wt-filter__suggest-name">' + $('<div>').text(item.name || '').html() + '</span>';
      if (showCounts) {
        html += '<span class="wt-filter__suggest-count">' + $('<div>').text(String(item.count || 0)).html() + '</span>';
      }
      html += '</button>';
    });

    $box.html(html).removeAttr('hidden');
  }

  function runBrandSearch($group, q) {
    var $root = getRoot();
    var url = $root.data('search-manufacturers');

    if (!url) {
      return;
    }

    if (brandSearchXhr && brandSearchXhr.readyState !== 4) {
      brandSearchXhr.abort();
    }

    var data = {
      path: $root.data('path') || '',
      q: q
    };

    if (parseInt($root.data('special'), 10) === 1) {
      data.special = 1;
    }

    data[getUrlKey($root)] = collectParams($root);

    brandSearchXhr = $.ajax({
      url: url,
      type: 'post',
      dataType: 'json',
      data: data,
      success: function (json) {
        if (!$group.find('[data-role="brand-search"]').is(':focus')) {
          return;
        }

        if (!json || !json.success) {
          hideBrandSuggest($group);
          return;
        }

        renderBrandSuggest($group, json.items || []);
      }
    });
  }

  function bindBrandSearch($root) {
    $root.on('input', '[data-role="brand-search"]', function () {
      var $input = $(this);
      var $group = $input.closest('.wt-filter__group');
      var q = $.trim($input.val() || '');

      clearTimeout(brandSearchTimer);

      if (q.length < 2) {
        hideBrandSuggest($group);
        return;
      }

      brandSearchTimer = setTimeout(function () {
        runBrandSearch($group, q);
      }, 220);
    });

    $root.on('keydown', '[data-role="brand-search"]', function (e) {
      var $group = $(this).closest('.wt-filter__group');
      var $items = $group.find('.wt-filter__suggest-item');
      var $active = $items.filter('.is-active');

      if (e.key === 'Escape') {
        hideBrandSuggest($group);
        return;
      }

      if (!$items.length) {
        return;
      }

      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        e.preventDefault();
        var idx = $items.index($active);

        if (e.key === 'ArrowDown') {
          idx = idx < $items.length - 1 ? idx + 1 : 0;
        } else {
          idx = idx > 0 ? idx - 1 : $items.length - 1;
        }

        $items.removeClass('is-active');
        $items.eq(idx).addClass('is-active');
        return;
      }

      if (e.key === 'Enter' && $active.length) {
        e.preventDefault();
        selectBrand($group, $active.data('value-id'));
      }
    });

    $root.on('mousedown', '.wt-filter__suggest-item', function (e) {
      e.preventDefault();
      var $group = $(this).closest('.wt-filter__group');
      selectBrand($group, $(this).data('value-id'));
    });

    $root.on('blur', '[data-role="brand-search"]', function () {
      var $group = $(this).closest('.wt-filter__group');
      setTimeout(function () {
        hideBrandSuggest($group);
      }, 150);
    });
  }

  function bind() {
    var $root = getRoot();

    if (!$root.length) {
      return;
    }

    ensureResultsShell();
    $('#input-sort, #input-limit').removeAttr('onchange');
    bindBrandSearch($root);
    prepareAccordion($root);
    syncMobileMount($root);

    $(document).on('click', '[data-wt-mobile-open]', function (e) {
      e.preventDefault();
      openMobileFilter(getRoot());
    });

    $root.on('click', '[data-wt-mobile-close]', function (e) {
      e.preventDefault();
      closeMobileFilter($root);
    });

    $root.on('click', '[data-wt-mobile-apply]', function (e) {
      e.preventDefault();
      closeMobileFilter($root);
    });

    $root.on('click', '.wt-filter__title', function (e) {
      e.preventDefault();
      toggleAccordionGroup($(this).closest('.wt-filter__group'));
    });

    $root.on('keydown', '.wt-filter__title', function (e) {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        toggleAccordionGroup($(this).closest('.wt-filter__group'));
      }
    });

    if (window.matchMedia) {
      var mobileMq = window.matchMedia('(max-width: 991px)');
      var onMobileMq = function () {
        syncMobileMount(getRoot());
      };

      if (typeof mobileMq.addEventListener === 'function') {
        mobileMq.addEventListener('change', onMobileMq);
      } else if (typeof mobileMq.addListener === 'function') {
        mobileMq.addListener(onMobileMq);
      }
    }

    $(document).on('keyup.wtFilterMobile', function (e) {
      if (e.key === 'Escape' && getRoot().hasClass('is-open')) {
        closeMobileFilter(getRoot());
      }
    });

    $root.on('change', 'input[type="checkbox"], input[type="radio"], select[data-select]', function () {
      var $label = $(this).closest('.wt-filter__value, .wt-filter__tile');
      if ($label.length) {
        $label.toggleClass('is-selected', this.checked);
        if (this.type === 'radio') {
          $root.find('input[name="' + this.name + '"]').closest('.wt-filter__value, .wt-filter__tile').removeClass('is-selected');
          if (this.checked) $label.addClass('is-selected');
        }
      }
      scheduleFilter();
      refreshMobileNavState($root);
    });

    $root.on('change input', '[data-price-range], [data-slide-range]', function () {
      var source = ($(this).data('price-range') || $(this).data('slide-range')) === 'to' ? 'to' : 'from';
      syncRangeInputs($(this).closest('.wt-filter__group'), source);
      scheduleFilter();
      refreshMobileNavState($root);
    });

    $root.on('change', '[data-price], [data-slide]', function () {
      var $group = $(this).closest('.wt-filter__group');
      var $from = $group.find('[data-price="from"], [data-slide="from"]').first();
      var $to = $group.find('[data-price="to"], [data-slide="to"]').first();
      var $fromRange = $group.find('[data-price-range="from"], [data-slide-range="from"]').first();
      var $toRange = $group.find('[data-price-range="to"], [data-slide-range="to"]').first();
      var source = $(this).is('[data-price="to"], [data-slide="to"]') ? 'to' : 'from';

      if ($toRange.length) {
        if ($fromRange.length && $from.length) {
          $fromRange.val($from.val());
        }
        if ($to.length) {
          $toRange.val($to.val());
        }
        syncRangeInputs($group, source);
      }

      scheduleFilter();
      refreshMobileNavState($root);
    });

    $root.on('click', '.wt-filter__more', function (e) {
      e.preventDefault();
      var $btn = $(this);
      var $values = $btn.prev('.wt-filter__values');

      if (!$values.length) {
        return;
      }

      $values.toggleClass('is-expanded');
      refreshShowMore($root);
    });

    $root.on('click', '[data-action="reset"]', function (e) {
      e.preventDefault();
      $root.find('input[type="checkbox"], input[type="radio"]').prop('checked', false);
      $root.find('.wt-filter__value, .wt-filter__tile').removeClass('is-selected');
      $root.find('.wt-filter__values').removeClass('is-expanded');
      $root.find('select[data-select]').val('');
      $root.find('.wt-filter__group[data-type="price"]').each(function () {
        var $g = $(this);
        $g.find('[data-price="from"], [data-price-range="from"]').val($g.find('[data-price-range="from"]').attr('min'));
        $g.find('[data-price="to"], [data-price-range="to"]').val($g.find('[data-price-range="to"]').attr('max'));
        updateDualFill($g);
      });
      $root.find('.wt-filter__group[data-type="slider_range"], .wt-filter__group[data-type="slide_dual"]').each(function () {
        var $g = $(this);
        var min = $g.find('[data-slide-range="from"]').attr('min');
        var max = $g.find('[data-slide-range="to"]').attr('max');
        $g.find('[data-slide="from"], [data-slide-range="from"]').val(min);
        $g.find('[data-slide="to"], [data-slide-range="to"]').val(max);
        updateDualFill($g);
      });
      $root.find('.wt-filter__group[data-type="slider_single"], .wt-filter__group[data-type="slide"]').each(function () {
        var $g = $(this);
        var max = $g.find('[data-slide-range="to"]').attr('max');
        $g.find('[data-slide="to"], [data-slide-range="to"]').val(max);
      });
      refreshShowMore($root);
      refreshMobileNavState($root);
      scheduleFilter();
    });

    $root.find('.wt-filter__group[data-type="price"], .wt-filter__group[data-type="slider_range"], .wt-filter__group[data-type="slide_dual"]').each(function () {
      updateDualFill($(this));
    });

    refreshShowMore($root);

    $(document).on('change', '#input-sort, #input-limit', function (e) {
      if (!$root.length) return;
      e.preventDefault();
      e.stopImmediatePropagation();
      runRefresh({ page: 1 });
      return false;
    });

    $(document).on('click', '#wt-filter-pagination a', function (e) {
      if (!$root.length) return;
      e.preventDefault();
      var href = $(this).attr('href') || '';
      var page = 1;
      var m = /[?&]page=(\d+)/.exec(href);
      if (m) page = parseInt(m[1], 10) || 1;
      runRefresh({ page: page });
    });

    window.addEventListener('popstate', function () {
      window.location.reload();
    });
  }

  $(bind);
})(jQuery);
