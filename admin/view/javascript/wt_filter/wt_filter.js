var wt_filter = wt_filter || {};
wt_filter.url = wt_filter.url || {};
wt_filter.php = wt_filter.php || {};

wt_filter.init = function () {
  this.helper.setURLVars();

  $(document).on('click', '.switcher .selected', function () {
    var $this = $(this).parent('.switcher');
    if (!$this.hasClass('active')) {
      $('.switcher').removeClass('active');
      $this.addClass('active');
    } else {
      $this.removeClass('active');
    }
  });

  $(document).on('change', '.switcher input[type=\'checkbox\']', function () {
    var $this = $(this);
    var selected = $this.parents('.switcher').find('.selected');
    var text = $this.parent('label').text();
    var length = selected.find('span').length;

    if ($this.prop('checked')) {
      selected.append('<span id="v-' + this.value + '">' + text + '</span>').find('b').remove();
    } else {
      if (length === 1) {
        $('#v-' + this.value).replaceWith('<b>' + (wt_filter.php.text_select || 'Select') + '</b>');
      } else {
        $('#v-' + this.value).remove();
      }
    }
  });

  $(document).on('click', function (e) {
    if (!$(e.target).closest('.switcher').length) {
      $('.switcher.active').removeClass('active');
    }
  });
};

wt_filter.helper = {
  setURLVars: function () {
    var query = window.location.href.replace(window.location.hash, '').split('?')[1];
    if (!query) return;
    var vars = query.split('&');
    for (var i = 0; i < vars.length; i++) {
      var parts = vars[i].split('=');
      wt_filter.url[decodeURIComponent(parts[0])] = decodeURIComponent(parts[1] || '');
    }
  }
};

wt_filter.list = {
  init: function () {
    var token = wt_filter.url['user_token'] || (wt_filter.php && wt_filter.php.user_token) || '';
    var self = this;
    var editUrl = 'index.php?route=catalog/wt_filter/edit&user_token=' + encodeURIComponent(token);

    $('#button-filter').on('click', function () {
      var url = 'index.php?route=catalog/wt_filter&user_token=' + encodeURIComponent(token);
      var filter_name = $('input[name=\'filter_name\']').val();
      var filter_category_id = $('select[name=\'filter_category_id\']').val();
      var filter_type = $('select[name=\'filter_type\']').val();
      var filter_status = $('select[name=\'filter_status\']').val();

      if (filter_name) url += '&filter_name=' + encodeURIComponent(filter_name);
      if (filter_category_id) url += '&filter_category_id=' + encodeURIComponent(filter_category_id);
      if (filter_type) url += '&filter_type=' + encodeURIComponent(filter_type);
      if (filter_status !== '') url += '&filter_status=' + encodeURIComponent(filter_status);

      location = url;
    });

    $('input[name=\'filter_name\']').on('keydown', function (e) {
      if (e.keyCode == 13) {
        $('#button-filter').trigger('click');
      }
    });

    $('.wt-filter-list').on('focus', 'select.wt-type-select__control', function () {
      $(this).data('prev-value', $(this).val());
    });

    $('.wt-filter-list').on('change', 'input.edit, select.edit', function () {
      var $el = $(this);
      var type = $el.attr('type');
      var isToggle = $el.closest('.wt-toggle').length > 0;
      var isTypeSelect = $el.hasClass('wt-type-select__control');
      var $typeWrap = isTypeSelect ? $el.closest('.wt-type-select') : $();
      var prevValue = isTypeSelect ? $el.data('prev-value') : '';
      var post = {
        field: $el.attr('name'),
        value: (type === 'checkbox' ? ($el.prop('checked') ? 1 : 0) : $el.val()),
        option_id: $el.data('option-id')
      };

      if (isTypeSelect) {
        self.setTypeClass($typeWrap, $el.val());
      }

      if (isToggle) {
        $el.closest('.wt-toggle').addClass('is-loading');
      } else {
        $el.fadeTo(200, 0.35);
      }

      $.post(editUrl, post, function (json) {
        if (json && json.status === true) {
          if (isToggle) {
            var isExpandedField = ($el.attr('name') === 'expanded_desktop' || $el.attr('name') === 'expanded_mobile');
            var label = isExpandedField
              ? ($el.prop('checked')
                ? (wt_filter.php.text_expanded_open || 'Open')
                : (wt_filter.php.text_expanded_closed || 'Closed'))
              : ($el.prop('checked')
                ? (wt_filter.php.text_enabled || 'Enabled')
                : (wt_filter.php.text_disabled || 'Disabled'));
            $el.closest('.wt-toggle')
              .removeClass('is-loading')
              .attr('title', label)
              .attr('data-bs-original-title', label);
          } else {
            if (isTypeSelect) {
              $el.data('prev-value', $el.val());
            }
            $el.fadeTo(200, 1).removeClass('is-error').addClass('is-saved');
            setTimeout(function () { $el.removeClass('is-saved'); }, 1200);
          }
        } else {
          if (isToggle) {
            $el.prop('checked', !$el.prop('checked'));
            $el.closest('.wt-toggle').removeClass('is-loading');
          } else {
            if (isTypeSelect) {
              $el.val(prevValue);
              self.setTypeClass($typeWrap, prevValue);
            }
            $el.fadeTo(200, 1).addClass('is-error');
          }
        }
      }, 'json').fail(function () {
        if (isToggle) {
          $el.prop('checked', !$el.prop('checked'));
          $el.closest('.wt-toggle').removeClass('is-loading');
        } else {
          if (isTypeSelect) {
            $el.val(prevValue);
            self.setTypeClass($typeWrap, prevValue);
          }
          $el.fadeTo(200, 1).addClass('is-error');
        }
      });
    });

    self.initSortable(token, editUrl);
  },

  setTypeClass: function ($wrap, value) {
    $wrap.removeClass(function (index, className) {
      return (className.match(/(^|\s)wt-type-select--\S+/g) || []).join(' ');
    });
    $wrap.addClass('wt-type-select--' + (value || 'none'));
  },

  initSortable: function (token, editUrl) {
    var $tbody = $('#wt-filter-sortable');

    if (!$tbody.length || typeof $tbody.sortable !== 'function') {
      return;
    }

    $tbody.sortable({
      handle: '.wt-drag-handle',
      animation: 150,
      ghostClass: 'sortable-ghost',
      dragClass: 'sortable-drag',
      onEnd: function () {
        var $rows = $tbody.children('tr[data-option-id]');
        var orders = [];

        $rows.each(function () {
          var val = parseInt($(this).find('input[name=\'sort_order\']').val(), 10);
          orders.push(isNaN(val) ? 0 : val);
        });

        orders.sort(function (a, b) { return a - b; });

        var requests = [];

        $rows.each(function (index) {
          var $row = $(this);
          var optionId = $row.data('option-id');
          var $input = $row.find('input[name=\'sort_order\']');
          var newOrder = orders[index];

          if (String($input.val()) === String(newOrder)) {
            return;
          }

          $input.val(newOrder);

          requests.push($.post(editUrl, {
            field: 'sort_order',
            value: newOrder,
            option_id: optionId
          }));
        });

        if (!requests.length) {
          return;
        }

        $.when.apply($, requests).done(function () {
          $tbody.find('input[name=\'sort_order\']').addClass('is-saved');
          setTimeout(function () {
            $tbody.find('input[name=\'sort_order\']').removeClass('is-saved');
          }, 1200);
        });
      }
    });
  }
};

wt_filter.productForm = {
  category_id: null,
  product_id: null,
  length: null,
  init: function () {
    // Как в OC3: вкладка перед «Характеристики» (#tab-attribute), после Links
    var $attrTab = $('a[href=\'#tab-attribute\']').first();
    if (!$attrTab.length || $('#tab-wt-filter').length || !$('#form-product').length) {
      return;
    }

    var tabTitle = wt_filter.php.tab_wt_filter || 'Filter options';

    $attrTab.closest('li').before(
      '<li><a href="#tab-wt-filter" data-toggle="tab">' + tabTitle + '</a></li>'
    );

    $('#tab-attribute').before(
      '<div class="tab-pane" id="tab-wt-filter"><p class="text-secondary">' + (wt_filter.php.wt_filter_select_category || '') + '</p></div>'
    );

    // Остаёмся на General — стандартное поведение формы OC4
    var $general = $('a[href=\'#tab-general\']');
    if ($general.length && typeof bootstrap !== 'undefined' && bootstrap.Tab) {
      bootstrap.Tab.getOrCreateInstance($general[0]).show();
    } else {
      $general.tab('show');
    }

    if (undefined !== wt_filter.url['product_id']) {
      this.product_id = wt_filter.url['product_id'];
    }

    this.length = $('#product-category tr[id^="product-category-"], input[name=\'product_category[]\']').length;

    setInterval(function () {
      var length = $('#product-category tr[id^="product-category-"], input[name=\'product_category[]\']').length;
      if (wt_filter.productForm.length != length) {
        wt_filter.productForm.length = length;
        wt_filter.productForm.update();
      }
    }, 500);

    this.update();
  },
  update: function () {
    if ($('input[type=\'checkbox\'][name=\'product_category[]\']:checked:last').length > 0) {
      this.category_id = $('input[name=\'product_category[]\']:checked:last').val();
    } else if ($('input[type=\'hidden\'][name=\'product_category[]\']:last').length > 0) {
      this.category_id = $('input[name=\'product_category[]\']:last').val();
    } else {
      this.category_id = null;
    }

    var token = wt_filter.url['user_token'] || (wt_filter.php && wt_filter.php.user_token) || '';
    var get = {
      user_token: token,
      category_id: this.category_id
    };

    if (this.product_id) {
      get.product_id = this.product_id;
    }

    if (!get.category_id) {
      $('#tab-wt-filter').html('<p class="text-secondary">' + (wt_filter.php.wt_filter_select_category || '') + '</p>');
      return;
    }

    $.get('index.php?route=catalog/wt_filter/callback', get, function (json) {
      if (json.message) {
        $('#tab-wt-filter').html('<p class="text-secondary">' + json.message + '</p>');
        return;
      }

      var html = [];
      html.push('<table class="table table-bordered product-wt-filter-values">');

      for (var i = 0; i < json.options.length; i++) {
        var option = json.options[i];
        var values = [];
        var selecteds = [];

        html.push('<tr' + (!option.status ? ' class="table-secondary"' : '') + '>');
        html.push('<td width="20%"><strong>' + option.name + '</strong></td><td width="80%">');

        if (option.type == 'slide' || option.type == 'slide_dual' || option.type == 'slider_single' || option.type == 'slider_range') {
          html.push('<input type="hidden" name="wt_filter_product_option[' + option.option_id + '][values][0][selected]" value="1" />');
          html.push('<input type="text" name="wt_filter_product_option[' + option.option_id + '][values][0][slide_value_min]" value="' + option.slide_value_min + '" size="5" class="form-control form-control-sm" style="width:90px;display:inline-block;" />');
          html.push('&nbsp;&mdash;&nbsp;');
          html.push('<input type="text" name="wt_filter_product_option[' + option.option_id + '][values][0][slide_value_max]" value="' + option.slide_value_max + '" size="5" class="form-control form-control-sm" style="width:90px;display:inline-block;" />');
          html.push(option.postfix || '');
        } else if (option.values && option.values.length) {
          for (var j = 0; j < option.values.length; j++) {
            var value = option.values[j];

            if (value.selected) {
              selecteds.push('<span id="v-' + value.value_id + '">' + value.name + (option.postfix || '') + '</span>');
            }

            values.push('<div class="form-check">');
            values.push('<label class="form-check-label"><input type="checkbox" class="form-check-input" name="wt_filter_product_option[' + option.option_id + '][values][' + value.value_id + '][selected]" value="' + value.value_id + '"' + (value.selected ? ' checked' : '') + ' /> ' + value.name + (option.postfix || '') + '</label>');
            values.push('</div>');
          }

          if (!selecteds.length) {
            selecteds = ['<b>' + (wt_filter.php.text_select || 'Select') + '</b>'];
          }

          html.push('<div class="switcher"><div class="selected">' + selecteds.join('') + '</div><div class="values">' + values.join('') + '</div></div>');
        } else {
          html.push('<a href="index.php?route=catalog/wt_filter/edit&user_token=' + encodeURIComponent(token) + '&option_id=' + option.option_id + '" target="_blank">' + (wt_filter.php.entry_values || 'Values') + '</a>');
        }

        html.push('</td></tr>');
      }

      html.push('</table>');
      $('#tab-wt-filter').html(html.join(''));
    }, 'json');
  }
};

$(function () {
  wt_filter.init();

  var route = wt_filter.url['route'] || '';

  if (route == 'catalog/wt_filter' || route.indexOf('catalog/wt_filter') === 0) {
    wt_filter.list.init();
  }

  // OC4: catalog/product (add/edit)
  if (
    route == 'catalog/product' ||
    route == 'catalog/product|form' ||
    route.indexOf('catalog/product') === 0 ||
    ($('a[href="#tab-attribute"]').length && $('#form-product').length)
  ) {
    wt_filter.productForm.init();
  }
});
