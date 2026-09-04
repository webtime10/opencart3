(function($) {
	'use strict';

	function routeUrl(route) {
		return 'index.php?route=' + route;
	}

	function wtConfig() {
		var $root = $('#wt-checkout');
		return {
			$root: $root,
			logged: $root.data('logged') === 1 || $root.data('logged') === '1',
			shippingRequired: $root.data('shipping-required') === 1 || $root.data('shipping-required') === '1',
			customerRoute: $root.data('customer-route'),
			customerSave: $root.data('customer-save'),
			addressRoute: $root.data('address-route'),
			addressSave: $root.data('address-save'),
			shippingRoute: $root.data('shipping-route'),
			shippingSave: $root.data('shipping-save'),
			paymentRoute: $root.data('payment-route'),
			paymentSave: $root.data('payment-save'),
			confirmRoute: $root.data('confirm-route')
		};
	}

	function slotSelector(code) {
		return '#wt-checkout-slot-' + code;
	}

	function slotExists(code) {
		return $(slotSelector(code)).length > 0;
	}

	function slotInputs($slot) {
		return $slot.find('input[type="text"], input[type="date"], input[type="datetime-local"], input[type="time"], input[type="password"], input[type="hidden"], input[type="checkbox"]:checked, input[type="radio"]:checked, textarea, select');
	}

	function hideSlotButtons($slot) {
		$slot.find('.buttons, #button-guest, #button-payment-address, #button-shipping-address, #button-guest-shipping, #button-shipping-method, #button-payment-method, #button-register, #button-account, #button-login').closest('.buttons, .pull-right, .text-right').hide();
		$slot.find('#button-guest, #button-payment-address, #button-shipping-address, #button-guest-shipping, #button-shipping-method, #button-payment-method, #button-register, #button-account, #button-login').hide();
	}

	function showErrors($slot, json) {
		$slot.find('.alert-dismissible, .text-danger').remove();
		$slot.find('.form-group').removeClass('has-error');

		if (!json || !json.error) {
			return;
		}

		if (json.error.warning) {
			$slot.prepend('<div class="alert alert-danger alert-dismissible"><i class="fa fa-exclamation-circle"></i> ' + json.error.warning + '<button type="button" class="close" data-dismiss="alert">&times;</button></div>');
		}

		for (var i in json.error) {
			if (i === 'warning') {
				continue;
			}

			var element = $slot.find('#input-payment-' + i.replace(/_/g, '-') + ', #input-shipping-' + i.replace(/_/g, '-'));
			if (element.length) {
				if (element.parent().hasClass('input-group')) {
					element.parent().after('<div class="text-danger">' + json.error[i] + '</div>');
				} else {
					element.after('<div class="text-danger">' + json.error[i] + '</div>');
				}
			}
		}

		$slot.find('.text-danger').closest('.form-group').addClass('has-error');
	}

	function loadHtml(route, $slot) {
		return $.ajax({
			url: routeUrl(route),
			dataType: 'html'
		}).done(function(html) {
			$slot.html(html);
			hideSlotButtons($slot);
		});
	}

	function saveJson(route, $slot) {
		return $.ajax({
			url: routeUrl(route),
			type: 'post',
			data: slotInputs($slot),
			dataType: 'json'
		});
	}

	function reloadShipping(cfg) {
		if (!cfg.shippingRequired || !slotExists('shipping')) {
			return $.when();
		}
		return loadHtml(cfg.shippingRoute, $(slotSelector('shipping')));
	}

	function reloadPayment(cfg) {
		if (!slotExists('payment')) {
			return $.when();
		}
		return loadHtml(cfg.paymentRoute, $(slotSelector('payment')));
	}

	function reloadAddress(cfg) {
		if (!cfg.shippingRequired || !slotExists('address')) {
			return $.when();
		}
		return loadHtml(cfg.addressRoute, $(slotSelector('address')));
	}

	function saveCustomer(cfg) {
		var $slot = $(slotSelector('customer'));
		if (!$slot.length) {
			return $.when(true);
		}

		return saveJson(cfg.customerSave, $slot).then(function(json) {
			if (json.redirect) {
				location = json.redirect;
				return $.Deferred().reject().promise();
			}
			if (json.error) {
				showErrors($slot, json);
				return $.Deferred().reject().promise();
			}
			return true;
		});
	}

	function saveAddress(cfg) {
		var $slot = $(slotSelector('address'));
		if (!$slot.length || !cfg.shippingRequired) {
			return $.when(true);
		}

		return saveJson(cfg.addressSave, $slot).then(function(json) {
			if (json.redirect) {
				location = json.redirect;
				return $.Deferred().reject().promise();
			}
			if (json.error) {
				showErrors($slot, json);
				return $.Deferred().reject().promise();
			}
			return true;
		});
	}

	function saveShipping(cfg) {
		var $slot = $(slotSelector('shipping'));
		if (!$slot.length || !cfg.shippingRequired) {
			return $.when(true);
		}

		return saveJson(cfg.shippingSave, $slot).then(function(json) {
			if (json.redirect) {
				location = json.redirect;
				return $.Deferred().reject().promise();
			}
			if (json.error) {
				showErrors($slot, json);
				return $.Deferred().reject().promise();
			}
			return true;
		});
	}

	function savePayment(cfg) {
		var $slot = $(slotSelector('payment'));
		if (!$slot.length) {
			return $.when(true);
		}

		return saveJson(cfg.paymentSave, $slot).then(function(json) {
			if (json.redirect) {
				location = json.redirect;
				return $.Deferred().reject().promise();
			}
			if (json.error) {
				showErrors($slot, json);
				return $.Deferred().reject().promise();
			}
			return true;
		});
	}

	function refreshDownstream(cfg) {
		return saveCustomer(cfg).then(function() {
			return reloadAddress(cfg);
		}).then(function() {
			return saveAddress(cfg);
		}).then(function() {
			return reloadShipping(cfg);
		}).then(function() {
			return reloadPayment(cfg);
		});
	}

	var saveTimer = null;

	function scheduleCustomerSave(cfg) {
		clearTimeout(saveTimer);
		saveTimer = setTimeout(function() {
			refreshDownstream(cfg);
		}, 400);
	}

	$(document).ready(function() {
		var cfg = wtConfig();
		if (!cfg.$root.length) {
			return;
		}

		if (slotExists('customer')) {
			loadHtml(cfg.customerRoute, $(slotSelector('customer'))).done(function() {
				if (cfg.shippingRequired) {
					reloadShipping(cfg);
				}
				reloadPayment(cfg);
			});
		}

		if (slotExists('address') && cfg.shippingRequired) {
			loadHtml(cfg.addressRoute, $(slotSelector('address')));
		}

		$(document).on('change blur', slotSelector('customer') + ' input, ' + slotSelector('customer') + ' select, ' + slotSelector('customer') + ' textarea', function() {
			scheduleCustomerSave(cfg);
		});

		$(document).on('change blur', slotSelector('address') + ' input, ' + slotSelector('address') + ' select, ' + slotSelector('address') + ' textarea', function() {
			clearTimeout(saveTimer);
			saveTimer = setTimeout(function() {
				saveAddress(cfg).then(function() {
					return reloadShipping(cfg);
				}).then(function() {
					return reloadPayment(cfg);
				});
			}, 400);
		});

		$(document).on('change', slotSelector('shipping') + ' input[name="shipping_method"]', function() {
			saveShipping(cfg).then(function() {
				return reloadPayment(cfg);
			});
		});

		$(document).on('change', slotSelector('payment') + ' input[name="payment_method"]', function() {
			savePayment(cfg);
		});

		$('#wt-checkout-place').on('click', function() {
			var $btn = $(this);
			$btn.button('loading');

			savePayment(cfg).then(function() {
				return loadHtml(cfg.confirmRoute, $(slotSelector('confirm')));
			}).always(function() {
				$btn.button('reset');
			});
		});
	});
})(jQuery);
