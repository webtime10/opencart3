/**
 * wt_slider — jQuery product carousel
 * Seamless loop (step +1 + clones), touch, arrows, autoplay
 */
(function ($) {
  "use strict";

  function WtSlider($root, options) {
    this.$root = $root;
    this.$viewport = $root.find(".wt_slider_viewport");
    this.$track = $root.find(".wt_slider_track");
    this.$prev = $(options.prev);
    this.$next = $(options.next);
    this.loop = options.loop !== false;
    this.autoplay = options.autoplay || false;
    this.autoplayDelay = options.autoplayDelay || 4000;
    this.pauseOnHover = options.pauseOnHover !== false;

    this.$originals = this.$track.children(".wt_slider_slide").not(".wt_slider_clone").clone(true, true);
    this.realCount = this.$originals.length;

    this.gap = 24;
    this.index = 0;
    this.perView = 4;
    this.cloneCount = 0;
    this.slideWidth = 0;
    this.animating = false;
    this._autoplayTimer = null;

    this.dragging = false;
    this.startX = 0;
    this.currentX = 0;
    this.baseX = 0;
    this.moved = false;

    this.bind();
    this.layout();
    this.startAutoplay();
  }

  WtSlider.prototype.breakpoints = function () {
    var w = window.innerWidth;
    if (w <= 575) return { perView: 1, gap: 16 };
    if (w <= 767) return { perView: 2, gap: 16 };
    if (w <= 991) return { perView: 3, gap: 20 };
    return { perView: 4, gap: 24 };
  };

  WtSlider.prototype.canLoop = function () {
    return this.loop && this.realCount > this.perView;
  };

  WtSlider.prototype.build = function () {
    var self = this;
    this.$track.empty();
    this.cloneCount = this.canLoop() ? this.perView : 0;

    if (this.cloneCount) {
      this.$originals.slice(-this.cloneCount).each(function () {
        self.$track.append($(this).clone(true, true).addClass("wt_slider_clone"));
      });
    }

    this.$originals.each(function () {
      self.$track.append($(this).clone(true, true));
    });

    if (this.cloneCount) {
      this.$originals.slice(0, this.cloneCount).each(function () {
        self.$track.append($(this).clone(true, true).addClass("wt_slider_clone"));
      });
    }

    this.$slides = this.$track.children(".wt_slider_slide");
  };

  WtSlider.prototype.layout = function () {
    var bp = this.breakpoints();
    this.perView = bp.perView;
    this.gap = bp.gap;

    this.build();

    var viewportWidth = this.$viewport.width();
    this.slideWidth = (viewportWidth - this.gap * (this.perView - 1)) / this.perView;

    this.$slides.css({
      width: this.slideWidth + "px",
      marginRight: this.gap + "px",
    });

    this.index = this.cloneCount;
    this.goTo(this.index, false);
    this.updateNav();
    this.$root.addClass("wt_slider_ready");
  };

  WtSlider.prototype.offsetFor = function (index) {
    return -(index * (this.slideWidth + this.gap));
  };

  WtSlider.prototype.lastRealIndex = function () {
    return this.cloneCount + Math.max(0, this.realCount - this.perView);
  };

  WtSlider.prototype.snapIfNeeded = function () {
    if (!this.canLoop()) return;

    if (this.index >= this.cloneCount + this.realCount) {
      this.goTo(this.cloneCount, false);
      return;
    }

    if (this.index <= 0) {
      this.goTo(this.lastRealIndex(), false);
    }
  };

  WtSlider.prototype.goTo = function (index, animate) {
    var self = this;
    this.index = index;
    var x = this.offsetFor(this.index);

    this.$track.off("transitionend.wt_slider");
    clearTimeout(this._animTimer);

    if (animate === false) {
      this.$track.addClass("wt_slider_dragging");
      this.$track.css("transform", "translate3d(" + x + "px,0,0)");
      this.baseX = x;
      this.animating = false;
      return;
    }

    this.animating = true;
    this.$track.removeClass("wt_slider_dragging");
    this.$track.css("transform", "translate3d(" + x + "px,0,0)");
    this.baseX = x;

    var done = function () {
      self.$track.off("transitionend.wt_slider");
      clearTimeout(self._animTimer);
      self.snapIfNeeded();
      self.animating = false;
      self.updateNav();
    };

    this.$track.one("transitionend.wt_slider", done);
    this._animTimer = setTimeout(done, 400);
  };

  WtSlider.prototype.next = function () {
    if (this.animating) return;

    if (!this.canLoop()) {
      if (this.index >= this.lastRealIndex()) return;
      this.goTo(this.index + 1, true);
      return;
    }

    this.goTo(this.index + 1, true);
  };

  WtSlider.prototype.prev = function () {
    if (this.animating) return;

    if (!this.canLoop()) {
      if (this.index <= this.cloneCount) return;
      this.goTo(this.index - 1, true);
      return;
    }

    this.goTo(this.index - 1, true);
  };

  WtSlider.prototype.updateNav = function () {
    if (this.canLoop()) {
      this.$prev.prop("disabled", false);
      this.$next.prop("disabled", false);
      return;
    }
    this.$prev.prop("disabled", this.index <= this.cloneCount);
    this.$next.prop("disabled", this.index >= this.lastRealIndex());
  };

  WtSlider.prototype.startAutoplay = function () {
    var self = this;
    this.stopAutoplay();
    if (!this.autoplay || !this.canLoop() && this.realCount <= this.perView) return;

    this._autoplayTimer = setInterval(function () {
      if (!self.dragging && !self.animating) {
        self.next();
      }
    }, this.autoplayDelay);
  };

  WtSlider.prototype.stopAutoplay = function () {
    if (this._autoplayTimer) {
      clearInterval(this._autoplayTimer);
      this._autoplayTimer = null;
    }
  };

  WtSlider.prototype.bind = function () {
    var self = this;

    this.$prev.on("click.wt_slider", function () {
      self.prev();
      self.startAutoplay();
    });

    this.$next.on("click.wt_slider", function () {
      self.next();
      self.startAutoplay();
    });

    this.$viewport.on("pointerdown.wt_slider", function (e) {
      if (self.animating) return;
      if (e.pointerType === "mouse" && e.button !== 0) return;
      self.dragging = true;
      self.moved = false;
      self.stopAutoplay();
      self.startX = e.clientX;
      self.currentX = e.clientX;
      self.$track.addClass("wt_slider_dragging");
      self.$viewport.addClass("wt_slider_grabbing");
      try {
        self.$viewport[0].setPointerCapture(e.pointerId);
      } catch (err) {}
    });

    this.$viewport.on("pointermove.wt_slider", function (e) {
      if (!self.dragging) return;
      self.currentX = e.clientX;
      var dx = self.currentX - self.startX;
      if (Math.abs(dx) > 4) self.moved = true;
      self.$track.css("transform", "translate3d(" + (self.baseX + dx) + "px,0,0)");
    });

    var endDrag = function () {
      if (!self.dragging) return;
      self.dragging = false;
      self.$viewport.removeClass("wt_slider_grabbing");
      self.$track.removeClass("wt_slider_dragging");

      var dx = self.currentX - self.startX;
      var threshold = self.slideWidth * 0.25;

      if (dx <= -threshold) self.next();
      else if (dx >= threshold) self.prev();
      else self.goTo(self.index, true);

      self.startAutoplay();
    };

    this.$viewport.on("pointerup.wt_slider pointercancel.wt_slider", endDrag);

    this.$viewport.on("click.wt_slider", "a, button", function (e) {
      if (self.moved) {
        e.preventDefault();
        e.stopImmediatePropagation();
      }
    });

    if (this.pauseOnHover) {
      this.$root.on("mouseenter.wt_slider", function () {
        self.stopAutoplay();
      });
      this.$root.on("mouseleave.wt_slider", function () {
        self.startAutoplay();
      });
    }

    $(window).on("resize.wt_slider", function () {
      clearTimeout(self._resizeTimer);
      self._resizeTimer = setTimeout(function () {
        self.layout();
        self.startAutoplay();
      }, 120);
    });

    $(window).on("load.wt_slider", function () {
      self.layout();
    });
  };

  $.fn.wt_slider = function (options) {
    return this.each(function () {
      var $el = $(this);
      if ($el.data("wt_slider_instance")) return;
      $el.data("wt_slider_instance", new WtSlider($el, options || {}));
    });
  };
})(jQuery);
