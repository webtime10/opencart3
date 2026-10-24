(function ($) {
  "use strict";

  var $drawer = $("[data-cart-drawer]");
  if (!$drawer.length) return;

  function openDrawer() {
    $drawer.prop("hidden", false);
    $("body").addClass("wt_cart_drawer_open");
    requestAnimationFrame(function () {
      if (window.wtSliderInit) window.wtSliderInit($drawer.get(0));
    });
  }

  function closeDrawer() {
    $drawer.prop("hidden", true);
    $("body").removeClass("wt_cart_drawer_open");
  }

  $(document).on("click", "[data-cart-open]", function (e) {
    e.preventDefault();
    openDrawer();
  });

  $drawer.on("click", "[data-cart-close]", closeDrawer);

  $(document).on("keydown", function (e) {
    if (e.key === "Escape" && !$drawer.prop("hidden")) closeDrawer();
  });

  $drawer.find("[data-drawer-qty]").each(function () {
    var $wrap = $(this);
    var $input = $wrap.find("input");
    if (!$input.length) return;

    function clamp(n) {
      return Math.max(1, Math.min(99, n));
    }

    $wrap.find("[data-qty-minus]").on("click", function () {
      $input.val(String(clamp(Number($input.val() || 1) - 1)));
    });
    $wrap.find("[data-qty-plus]").on("click", function () {
      $input.val(String(clamp(Number($input.val() || 1) + 1)));
    });
  });
})(jQuery);
