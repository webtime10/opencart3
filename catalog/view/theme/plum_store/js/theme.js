(function ($) {
  "use strict";

  /* —— theme switcher —— */
  var THEME_KEY = "plum-store-theme";
  var THEMES = ["theme-dark", "theme-plum", "theme-light", "theme-ocean", "theme-olive", "theme-emerald"];

  function applyTheme(theme) {
    if (THEMES.indexOf(theme) === -1) theme = "theme-plum";
    $("body").removeClass(THEMES.join(" ")).addClass(theme);
    $("[data-theme]").each(function () {
      $(this).toggleClass("is-active", $(this).data("theme") === theme);
    });
    try {
      localStorage.setItem(THEME_KEY, theme);
    } catch (e) {}
  }

  var savedTheme = null;
  try {
    savedTheme = localStorage.getItem(THEME_KEY);
  } catch (e) {}
  applyTheme(savedTheme || document.body.getAttribute("data-default-theme") || "theme-plum");

  $(document).on("click", "[data-theme]", function () {
    applyTheme($(this).data("theme"));
  });

  /* —— logo theme popup —— */
  function closeThemePop() {
    var $root = $("[data-theme-root]");
    $root.removeClass("is-open");
    $root.find("[data-theme-toggle]").attr("aria-expanded", "false");
    $root.find("[data-theme-pop]").prop("hidden", true);
  }

  function openThemePop() {
    var $root = $("[data-theme-root]");
    $root.addClass("is-open");
    $root.find("[data-theme-toggle]").attr("aria-expanded", "true");
    $root.find("[data-theme-pop]").prop("hidden", false);
  }

  $(document).on("click", "[data-theme-toggle]", function (e) {
    e.preventDefault();
    e.stopPropagation();
    if ($("[data-theme-root]").hasClass("is-open")) closeThemePop();
    else openThemePop();
  });

  $(document).on("click", "[data-theme-pop]", function (e) {
    e.stopPropagation();
  });

  $(document).on("click", function () {
    closeThemePop();
  });

  $(document).on("keydown", function (e) {
    if (e.key === "Escape") closeThemePop();
  });

  /* —— topbar dropdowns —— */
  function closeDropdowns($except) {
    $("[data-dropdown]").each(function () {
      var $dd = $(this);
      if ($except && $dd.is($except)) return;
      $dd.removeClass("is-open");
      $dd.find(".topbar-dd-btn").attr("aria-expanded", "false");
      $dd.find(".topbar-dd-menu").prop("hidden", true);
    });
  }

  $(document).on("click", "[data-dropdown] .topbar-dd-btn", function (e) {
    e.stopPropagation();
    var $dd = $(this).closest("[data-dropdown]");
    var $menu = $dd.find(".topbar-dd-menu");
    var willOpen = !$dd.hasClass("is-open");
    closeDropdowns();
    if (willOpen) {
      $dd.addClass("is-open");
      $(this).attr("aria-expanded", "true");
      $menu.prop("hidden", false);
    }
  });

  $(document).on("click", "[data-dropdown] .topbar-dd-item", function (e) {
    e.stopPropagation();
    var $item = $(this);
    var $dd = $item.closest("[data-dropdown]");
    var $menu = $dd.find(".topbar-dd-menu");
    var $label = $dd.find("[data-dd-label]");
    $menu.find(".topbar-dd-item").removeClass("is-active");
    $item.addClass("is-active");
    if ($label.length) {
      $label.text($item.data("label") || $.trim($item.text()));
    }
    closeDropdowns();
  });

  $(document).on("click", function () {
    closeDropdowns();
  });

  $(document).on("keydown", function (e) {
    if (e.key === "Escape") closeDropdowns();
  });

  /* —— vertical catalog menu —— */
  var VISIBLE_CATS = 10;
  var VIEW_PAD = 8;

  function bindVmenu($root) {
    if (!$root.length || $root.data("vmenuBound")) return;
    $root.data("vmenuBound", 1);

    var $flyout = $root.find("[data-vmenu-flyout]").first();
    var $items = $root.find(".vmenu-list").first().children("[data-vmenu-item]");
    var $panels = $flyout.children("[data-vmenu-panel]");
    if (!$panels.length) $panels = $flyout.find("[data-vmenu-panel]");
    var $all = $root.find(".vmenu-all");
    var total = $items.length;
    var $activeCascade = $();

    if (total > VISIBLE_CATS) {
      $root.addClass("has-more");
      $items.each(function (i) {
        $(this).toggleClass("vmenu-item--extra", i >= VISIBLE_CATS);
      });
    } else {
      $root.removeClass("has-more");
      $items.removeClass("vmenu-item--extra");
    }

    function isFlatItem($item) {
      return (
        $item.is("[data-vmenu-cascade]") ||
        $item.attr("data-depth") === "1" ||
        $item.children(".vmenu-cascade").length > 0
      );
    }

    function setHidden(el, on) {
      if (!el) return;
      if (on) {
        el.setAttribute("hidden", "");
        el.hidden = true;
      } else {
        el.removeAttribute("hidden");
        el.hidden = false;
      }
    }

    function resetFlyoutFit() {
      $flyout.removeClass("is-viewport-fit");
      if ($flyout[0]) {
        $flyout[0].style.cssText = "";
        $flyout[0].removeAttribute("data-fit-top");
        $flyout[0].removeAttribute("data-fit-item");
      }
    }

    function calcViewportFit(desiredTopVP, naturalHeight) {
      var vh = window.innerHeight;
      var pad = VIEW_PAD;
      var maxH = Math.max(160, vh - pad * 2);
      var h = naturalHeight;
      var top = desiredTopVP;
      var scroll = false;

      if (h > maxH) {
        h = maxH;
        scroll = true;
      }
      if (top + h > vh - pad) {
        top = vh - pad - h;
      }
      if (top < pad) {
        top = pad;
      }

      return { topVP: top, height: h, scroll: scroll };
    }

    function hideAllCascades() {
      $root.find(".vmenu-cascade").each(function () {
        setHidden(this, true);
        this.style.cssText = "";
      });
      $activeCascade = $();
    }

    function showCascade($item) {
      hideAllCascades();
      var el = $item.children(".vmenu-cascade").get(0);
      if (!el) return;

      var itemRect = $item[0].getBoundingClientRect();
      setHidden(el, false);

      el.style.setProperty("display", "block", "important");
      el.style.setProperty("position", "fixed", "important");
      el.style.setProperty("left", Math.round(itemRect.right) + "px", "important");
      el.style.setProperty("top", Math.round(itemRect.top) + "px", "important");
      el.style.setProperty("height", "auto", "important");
      el.style.setProperty("bottom", "auto", "important");
      el.style.setProperty("max-height", "none", "important");
      el.style.setProperty("z-index", "10000", "important");
      el.style.setProperty("width", "max-content", "important");
      el.style.setProperty("min-width", "220px", "important");
      el.style.setProperty("max-width", "320px", "important");
      el.style.setProperty("margin", "0", "important");
      el.style.setProperty("padding", "0", "important");
      el.style.setProperty("box-sizing", "border-box", "important");
      el.style.setProperty("list-style", "none", "important");
      el.style.setProperty("overflow-x", "hidden", "important");
      el.style.setProperty("overflow-y", "visible", "important");
      el.style.setProperty("background", "#fff", "important");
      el.style.setProperty("border", "1px solid #ddd", "important");
      el.style.setProperty("border-radius", "4px", "important");
      el.style.setProperty("box-shadow", "0 8px 24px rgba(0,0,0,.18)", "important");

      var naturalH = el.offsetHeight;
      var fit = calcViewportFit(itemRect.top, naturalH);
      el.style.setProperty("top", Math.round(fit.topVP) + "px", "important");
      if (fit.scroll) {
        el.style.setProperty("max-height", Math.round(fit.height) + "px", "important");
        el.style.setProperty("overflow-y", "auto", "important");
      }

      $activeCascade = $(el);
    }

    function repositionOpen() {
      var $item = $items.filter(".is-open").first();
      if (!$item.length) return;
      if (isFlatItem($item) && $activeCascade.length) showCascade($item);
    }

    function show(id) {
      var $item = $items.filter(function () {
        return String(this.getAttribute("data-vmenu-item")) === String(id);
      }).first();
      if (!$item.length) return;

      if ($item.hasClass("is-open") && !isFlatItem($item) && !$flyout[0].hidden) {
        return;
      }
      if ($item.hasClass("is-open") && isFlatItem($item) && $activeCascade.length) {
        return;
      }

      $items.removeClass("is-open is-active");
      $item.addClass("is-open is-active");
      $root.addClass("is-expanded");

      if (isFlatItem($item)) {
        $root.addClass("is-flat-open");
        resetFlyoutFit();
        setHidden($flyout.get(0), true);
        $panels.each(function () {
          setHidden(this, true);
        });
        showCascade($item);
        return;
      }

      // Mega / лопата — top: 0 как в исходном дизайне
      $root.removeClass("is-flat-open");
      hideAllCascades();
      resetFlyoutFit();
      $panels.each(function () {
        setHidden(this, String(this.getAttribute("data-vmenu-panel")) !== String(id));
      });
      setHidden($flyout.get(0), false);
    }

    function hide() {
      $root.removeClass("is-expanded is-flat-open");
      hideAllCascades();
      resetFlyoutFit();
      setHidden($flyout.get(0), true);
      $panels.each(function () {
        setHidden(this, true);
      });
      $items.removeClass("is-open is-active");
    }

    $root.on("mouseenter", function () {
      if (total > VISIBLE_CATS) $root.addClass("is-expanded");
    });

    $items.on("mouseenter", function () {
      show(this.getAttribute("data-vmenu-item"));
    });

    $items.find("> a").on("click", function (e) {
      if (window.innerWidth > 991) return;
      e.preventDefault();
      show($(this).closest("[data-vmenu-item]").attr("data-vmenu-item"));
    });

    $all.on("mouseenter", function () {
      if (total > VISIBLE_CATS) $root.addClass("is-expanded");
      $items.removeClass("is-open is-active");
      $root.removeClass("is-flat-open");
      hideAllCascades();
      resetFlyoutFit();
      setHidden($flyout.get(0), true);
      $panels.each(function () {
        setHidden(this, true);
      });
    });

    $root.on("mouseleave", hide);

    $(window).on("resize.vmenufit", repositionOpen);
  }

  $("[data-vmenu]").each(function () {
    bindVmenu($(this));
  });

  /* —— home banner slider —— */
  $("[data-home-slider]").each(function () {
    var $slider = $(this);
    var $slides = $slider.find(".home-slide");
    if (!$slides.length) return;
    var index = Math.max(0, $slides.index($slides.filter(".is-active").first()));

    function go(next) {
      index = (next + $slides.length) % $slides.length;
      $slides.removeClass("is-active").eq(index).addClass("is-active");
    }

    $slider.find("[data-home-prev]").on("click", function () {
      go(index - 1);
    });
    $slider.find("[data-home-next]").on("click", function () {
      go(index + 1);
    });
  });

  /* —— select chevron rotate 180° while open —— */
  $("select.form-control, select.form-control, .category-toolbar select").each(function () {
    var $select = $(this);
    if ($select.parent().hasClass("select-wrap")) return;
    $select.wrap('<span class="select-wrap"></span>');
  });

  $(document)
    .on("mousedown", ".select-wrap select", function () {
      var $wrap = $(this).closest(".select-wrap");
      // toggle: closing click on already-focused select
      if (document.activeElement === this) {
        $wrap.toggleClass("is-open");
      } else {
        $wrap.addClass("is-open");
      }
    })
    .on("blur change", ".select-wrap select", function () {
      $(this).closest(".select-wrap").removeClass("is-open");
    })
    .on("keydown", ".select-wrap select", function (e) {
      if (e.key === "Escape") {
        $(this).closest(".select-wrap").removeClass("is-open");
      }
    });

  /* —— header catalog overlay —— */
  var $catalogRoot = $("[data-catalog-root]");
  var $catalogToggle = $("[data-catalog-toggle]");
  var $catalogOverlay = $("[data-catalog-overlay]");

  function closeCatalog() {
    $catalogRoot.removeClass("is-open");
    $catalogToggle.attr("aria-expanded", "false");
    $catalogOverlay.prop("hidden", true);
  }

  function openCatalog() {
    $catalogRoot.addClass("is-open");
    $catalogToggle.attr("aria-expanded", "true");
    $catalogOverlay.prop("hidden", false);
    var $menu = $catalogOverlay.find("[data-vmenu]").first();
    var $first = $menu.find("[data-vmenu-item]").first();
    if ($first.length) $first.trigger("mouseenter");
  }

  $catalogToggle.on("click", function (e) {
    e.preventDefault();
    e.stopPropagation();
    if ($catalogRoot.hasClass("is-open")) closeCatalog();
    else openCatalog();
  });

  $(document).on("click", function (e) {
    if (!$catalogRoot.length) return;
    if (!$(e.target).closest("[data-catalog-root]").length) closeCatalog();
  });

  $(document).on("keydown", function (e) {
    if (e.key === "Escape") closeCatalog();
  });
})(jQuery);
