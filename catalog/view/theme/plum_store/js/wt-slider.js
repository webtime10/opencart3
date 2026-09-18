/**
 * slider — vanilla product carousel (from myslider)
 * Loop, touch/pointer drag, arrows, optional autoplay
 */
(() => {
  "use strict";

  const defaults = {
    loop: true,
    autoplay: false,
    autoplayDelay: 4000,
    pauseOnHover: true,
    gap: 24,
    perView: 5,
    breakpoints: {
      575: { perView: 1, gap: 16 },
      767: { perView: 2, gap: 16 },
      991: { perView: 3, gap: 16 },
      1199: { perView: 5, gap: 18 },
    },
  };

  function parseJson(value, fallback) {
    if (!value) return fallback;
    try {
      return JSON.parse(value);
    } catch (_) {
      return fallback;
    }
  }

  function boolAttr(el, name, fallback) {
    if (!el.hasAttribute(name)) return fallback;
    const v = el.getAttribute(name);
    if (v === "" || v === "true" || v === "1") return true;
    if (v === "false" || v === "0") return false;
    return fallback;
  }

  function numAttr(el, name, fallback) {
    const v = el.getAttribute(name);
    if (v == null || v === "") return fallback;
    const n = Number(v);
    return Number.isFinite(n) ? n : fallback;
  }

  function isHidden(el) {
    return !el || el.hidden || el.closest("[hidden]") != null;
  }

  class WtSlider {
    constructor(root) {
      this.root = root;
      this.slider = root.matches("[data-wt-slider]")
        ? root
        : root.querySelector("[data-wt-slider]");
      if (!this.slider) return;

      this.viewport = this.slider.querySelector(".slider-viewport");
      this.track = this.slider.querySelector(".slider-track");
      this.prevBtn = root.querySelector("[data-wt-slider-prev]");
      this.nextBtn = root.querySelector("[data-wt-slider-next]");

      if (!this.viewport || !this.track) return;

      const bpAttr = root.getAttribute("data-breakpoints");
      const breakpoints =
        bpAttr == null
          ? defaults.breakpoints
          : parseJson(bpAttr, {}) || {};

      this.options = {
        loop: boolAttr(root, "data-loop", defaults.loop),
        autoplay: boolAttr(root, "data-autoplay", defaults.autoplay),
        autoplayDelay: numAttr(root, "data-autoplay-delay", defaults.autoplayDelay),
        pauseOnHover: boolAttr(root, "data-pause-on-hover", defaults.pauseOnHover),
        gap: numAttr(root, "data-gap", defaults.gap),
        perView: numAttr(root, "data-per-view", defaults.perView),
        breakpoints,
      };

      // Snapshot slides once; rebuild from clones every layout
      this.originals = Array.from(
        this.track.querySelectorAll(".slider-slide:not(.slider-clone)")
      ).map((node) => node.cloneNode(true));
      this.realCount = this.originals.length;
      if (!this.realCount) return;

      this.index = 0;
      this.perView = this.options.perView;
      this.gap = this.options.gap;
      this.cloneCount = 0;
      this.slideWidth = 0;
      this.animating = false;
      this._autoplayTimer = null;
      this._animTimer = null;
      this._resizeTimer = null;
      this._onTransitionEnd = null;

      this.dragging = false;
      this.startX = 0;
      this.currentX = 0;
      this.baseX = 0;
      this.moved = false;
      this.activePointer = null;

      this.root._wtSlider = this;
      this.bind();
      this.layout();
      this.startAutoplay();
    }

    resolveBreakpoints() {
      const w = window.innerWidth;
      let perView = this.options.perView;
      let gap = this.options.gap;
      const entries = Object.keys(this.options.breakpoints)
        .map((k) => [Number(k), this.options.breakpoints[k]])
        .filter(([max]) => Number.isFinite(max))
        .sort((a, b) => a[0] - b[0]);

      for (const [max, cfg] of entries) {
        if (w <= max) {
          perView = cfg.perView ?? perView;
          gap = cfg.gap ?? gap;
          break;
        }
      }
      return { perView, gap };
    }

    canLoop() {
      return this.options.loop && this.realCount > this.perView;
    }

    build() {
      this.track.innerHTML = "";
      this.cloneCount = this.canLoop() ? this.perView : 0;

      if (this.cloneCount) {
        this.originals.slice(-this.cloneCount).forEach((node) => {
          const clone = node.cloneNode(true);
          clone.classList.add("slider-clone");
          this.track.appendChild(clone);
        });
      }

      this.originals.forEach((node) => {
        this.track.appendChild(node.cloneNode(true));
      });

      if (this.cloneCount) {
        this.originals.slice(0, this.cloneCount).forEach((node) => {
          const clone = node.cloneNode(true);
          clone.classList.add("slider-clone");
          this.track.appendChild(clone);
        });
      }

      this.slides = Array.from(this.track.children);
    }

    layout() {
      if (!this.viewport || !this.track || !this.realCount) return;

      const bp = this.resolveBreakpoints();
      this.perView = Math.max(1, bp.perView | 0);
      this.gap = Number.isFinite(bp.gap) ? bp.gap : this.options.gap;
      this.build();

      const viewportWidth = this.viewport.clientWidth;
      if (viewportWidth < 10) {
        // Hidden / not laid out yet — keep CSS fallback widths
        this.slider.classList.add("slider-ready");
        return;
      }

      this.slideWidth = (viewportWidth - this.gap * (this.perView - 1)) / this.perView;
      if (!Number.isFinite(this.slideWidth) || this.slideWidth <= 0) {
        this.slider.classList.add("slider-ready");
        return;
      }

      this.slides.forEach((slide) => {
        slide.style.width = `${this.slideWidth}px`;
        slide.style.marginRight = `${this.gap}px`;
      });

      this.index = this.cloneCount;
      this.goTo(this.index, false);
      this.updateNav();
      this.slider.classList.add("slider-ready");
    }

    offsetFor(index) {
      return -(index * (this.slideWidth + this.gap));
    }

    lastRealIndex() {
      return this.cloneCount + Math.max(0, this.realCount - this.perView);
    }

    snapIfNeeded() {
      if (!this.canLoop()) return;
      if (this.index >= this.cloneCount + this.realCount) {
        this.goTo(this.cloneCount, false);
        return;
      }
      if (this.index <= 0) {
        this.goTo(this.lastRealIndex(), false);
      }
    }

    goTo(index, animate) {
      this.index = index;
      const x = this.offsetFor(this.index);

      if (this._onTransitionEnd) {
        this.track.removeEventListener("transitionend", this._onTransitionEnd);
        this._onTransitionEnd = null;
      }
      clearTimeout(this._animTimer);

      if (animate === false) {
        this.track.classList.add("slider-dragging");
        this.track.style.transform = `translate3d(${x}px,0,0)`;
        this.baseX = x;
        this.animating = false;
        return;
      }

      this.animating = true;
      this.track.classList.remove("slider-dragging");
      this.track.style.transform = `translate3d(${x}px,0,0)`;
      this.baseX = x;

      const done = () => {
        if (this._onTransitionEnd) {
          this.track.removeEventListener("transitionend", this._onTransitionEnd);
          this._onTransitionEnd = null;
        }
        clearTimeout(this._animTimer);
        this.snapIfNeeded();
        this.animating = false;
        this.updateNav();
      };

      this._onTransitionEnd = done;
      this.track.addEventListener("transitionend", done);
      this._animTimer = setTimeout(done, 400);
    }

    next() {
      if (this.animating || this.slideWidth <= 0) return;
      if (!this.canLoop()) {
        if (this.index >= this.lastRealIndex()) return;
        this.goTo(this.index + 1, true);
        return;
      }
      this.goTo(this.index + 1, true);
    }

    prev() {
      if (this.animating || this.slideWidth <= 0) return;
      if (!this.canLoop()) {
        if (this.index <= this.cloneCount) return;
        this.goTo(this.index - 1, true);
        return;
      }
      this.goTo(this.index - 1, true);
    }

    updateNav() {
      if (!this.prevBtn || !this.nextBtn) return;
      if (this.canLoop()) {
        this.prevBtn.disabled = false;
        this.nextBtn.disabled = false;
        return;
      }
      this.prevBtn.disabled = this.index <= this.cloneCount;
      this.nextBtn.disabled = this.index >= this.lastRealIndex();
    }

    startAutoplay() {
      this.stopAutoplay();
      if (!this.options.autoplay) return;
      if (!this.canLoop() && this.realCount <= this.perView) return;

      this._autoplayTimer = setInterval(() => {
        if (!this.dragging && !this.animating) this.next();
      }, this.options.autoplayDelay);
    }

    stopAutoplay() {
      if (this._autoplayTimer) {
        clearInterval(this._autoplayTimer);
        this._autoplayTimer = null;
      }
    }

    bind() {
      this.prevBtn?.addEventListener("click", () => {
        this.prev();
        this.startAutoplay();
      });
      this.nextBtn?.addEventListener("click", () => {
        this.next();
        this.startAutoplay();
      });

      this.viewport.addEventListener("pointerdown", (e) => {
        if (this.animating || this.slideWidth <= 0) return;
        if (e.pointerType === "mouse" && e.button !== 0) return;
        if (e.target.closest("a, button, input, label")) return;
        this.dragging = true;
        this.moved = false;
        this.activePointer = e.pointerId;
        this.stopAutoplay();
        this.startX = e.clientX;
        this.currentX = e.clientX;
        this.track.classList.add("slider-dragging");
        this.viewport.classList.add("slider-grabbing");
        try {
          this.viewport.setPointerCapture(e.pointerId);
        } catch (_) {}
      });

      this.viewport.addEventListener("pointermove", (e) => {
        if (!this.dragging || e.pointerId !== this.activePointer) return;
        this.currentX = e.clientX;
        const dx = this.currentX - this.startX;
        if (Math.abs(dx) > 4) this.moved = true;
        this.track.style.transform = `translate3d(${this.baseX + dx}px,0,0)`;
      });

      const endDrag = (e) => {
        if (!this.dragging) return;
        if (e && this.activePointer != null && e.pointerId !== this.activePointer) return;
        this.dragging = false;
        this.activePointer = null;
        this.viewport.classList.remove("slider-grabbing");
        this.track.classList.remove("slider-dragging");

        const dx = this.currentX - this.startX;
        const threshold = this.slideWidth * 0.25;
        if (dx <= -threshold) this.next();
        else if (dx >= threshold) this.prev();
        else this.goTo(this.index, true);

        this.startAutoplay();
      };

      this.viewport.addEventListener("pointerup", endDrag);
      this.viewport.addEventListener("pointercancel", endDrag);

      this.viewport.addEventListener(
        "click",
        (e) => {
          if (!this.moved) return;
          const t = e.target.closest("a, button");
          if (!t) return;
          e.preventDefault();
          e.stopPropagation();
        },
        true
      );

      if (this.options.pauseOnHover) {
        this.root.addEventListener("mouseenter", () => this.stopAutoplay());
        this.root.addEventListener("mouseleave", () => this.startAutoplay());
      }

      window.addEventListener("resize", () => {
        clearTimeout(this._resizeTimer);
        this._resizeTimer = setTimeout(() => {
          this.layout();
          this.startAutoplay();
        }, 120);
      });
    }
  }

  function initAll(scope) {
    const roots = (scope || document).querySelectorAll("[data-wt-slider-root]");
    roots.forEach((root) => {
      try {
        if (isHidden(root) && !root._wtSlider) return;
        if (root._wtSlider) {
          root._wtSlider.layout();
          return;
        }
        new WtSlider(root);
      } catch (err) {
        console.error("slider init failed", err);
        const slider = root.querySelector("[data-wt-slider]");
        slider?.classList.add("slider-ready");
      }
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () => initAll());
  } else {
    initAll();
  }

  window.addEventListener("load", () => initAll());
  window.wtSliderInit = initAll;
})();
