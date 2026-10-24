(() => {
  const root = document.querySelector("[data-product-page]");
  const modal = document.querySelector("[data-photo-modal]");
  if (!root || !modal || typeof Panzoom === "undefined") return;

  const gallery = root.querySelector("[data-product-gallery]");
  const mainImg = root.querySelector("[data-main-img]");
  const pageThumbs = [...root.querySelectorAll("[data-thumb]")];
  const openers = [...document.querySelectorAll("[data-photo-open]")];
  const modalThumbs = [...modal.querySelectorAll("[data-modal-thumb]")];
  const wrap = modal.querySelector("[data-zoom-wrap]");
  const modalImg = modal.querySelector("[data-modal-img]");
  const closers = [...modal.querySelectorAll("[data-photo-close]")];
  const btnIn = modal.querySelector("[data-zoom-in]");
  const btnOut = modal.querySelector("[data-zoom-out]");
  const btnReset = modal.querySelector("[data-zoom-reset]");
  const items = gallery ? JSON.parse(gallery.getAttribute("data-images") || "[]") : [];
  if (!items.length || !modalImg || !wrap) return;

  let panzoom = null;
  let currentIndex = 0;

  const setPageActive = (index) => {
    const item = items[index];
    if (!item) return;
    if (mainImg) {
      mainImg.src = item.src;
      mainImg.alt = item.alt || "";
    }
    pageThumbs.forEach((el) => {
      el.classList.toggle("is-active", Number(el.dataset.photoIndex) === index);
    });
  };

  const setModalThumbsActive = (index) => {
    modalThumbs.forEach((el) => {
      el.classList.toggle("is-active", Number(el.dataset.photoIndex) === index);
    });
  };

  const resetZoom = () => {
    if (!panzoom) return;
    panzoom.reset({ animate: false });
  };

  const ensurePanzoom = () => {
    if (panzoom) return panzoom;
    panzoom = Panzoom(modalImg, {
      maxScale: 5,
      minScale: 1,
      startScale: 1,
      contain: "outside",
      cursor: "grab",
      animate: true,
      duration: 180,
      step: 0.35,
    });
    wrap.addEventListener("wheel", panzoom.zoomWithWheel, { passive: false });
    wrap.addEventListener("dblclick", (e) => {
      e.preventDefault();
      const scale = panzoom.getScale();
      if (scale > 1.15) panzoom.reset();
      else panzoom.zoom(2.5, { animate: true });
    });
    return panzoom;
  };

  const setModalImage = (index) => {
    const item = items[index];
    if (!item) return;
    currentIndex = index;
    setModalThumbsActive(index);
    resetZoom();

    const apply = () => {
      ensurePanzoom();
      resetZoom();
    };

    if (modalImg.getAttribute("src") === item.src) {
      apply();
      return;
    }

    modalImg.alt = item.alt || "";
    modalImg.onload = () => {
      modalImg.onload = null;
      apply();
    };
    modalImg.src = item.src;
    if (modalImg.complete) {
      modalImg.onload = null;
      apply();
    }
  };

  const openModal = (index) => {
    setPageActive(index);
    modal.hidden = false;
    document.body.classList.add("modal-open");
    requestAnimationFrame(() => {
      ensurePanzoom();
      setModalImage(index);
    });
  };

  const closeModal = () => {
    resetZoom();
    modal.hidden = true;
    document.body.classList.remove("modal-open");
  };

  pageThumbs.forEach((thumb) => {
    thumb.addEventListener("click", () => {
      setPageActive(Number(thumb.dataset.photoIndex) || 0);
    });
  });

  modalThumbs.forEach((thumb) => {
    thumb.addEventListener("click", () => {
      setModalImage(Number(thumb.dataset.photoIndex) || 0);
    });
  });

  openers.forEach((btn) => {
    btn.addEventListener("click", (e) => {
      e.preventDefault();
      openModal(Number(btn.dataset.photoIndex) || 0);
    });
  });

  btnIn?.addEventListener("click", () => {
    ensurePanzoom().zoomIn();
  });
  btnOut?.addEventListener("click", () => {
    ensurePanzoom().zoomOut();
  });
  btnReset?.addEventListener("click", () => {
    ensurePanzoom().reset();
  });

  closers.forEach((btn) => btn.addEventListener("click", closeModal));
  modal.addEventListener("click", (e) => {
    if (e.target === modal) closeModal();
  });
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && !modal.hidden) closeModal();
  });
})();
