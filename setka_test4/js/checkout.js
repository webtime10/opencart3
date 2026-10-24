(() => {
  const root = document.querySelector("[data-checkout]");
  if (!root) return;

  const syncPanels = (group) => {
    root.querySelectorAll(`[data-method-group="${group}"]`).forEach((item) => {
      const input = item.querySelector('input[type="radio"]');
      const panel = item.querySelector("[data-method-panel]");
      const on = !!(input && input.checked);
      item.classList.toggle("is-active", on);
      if (panel) panel.hidden = !on;
    });
  };

  root.querySelectorAll("[data-method-group]").forEach((item) => {
    const input = item.querySelector('input[type="radio"]');
    if (!input) return;
    input.addEventListener("change", () => syncPanels(item.dataset.methodGroup));
  });
  syncPanels("shipping");
  syncPanels("payment");

  root.querySelectorAll("[data-accordion]").forEach((acc) => {
    const btn = acc.querySelector("[data-accordion-btn]");
    const panel = acc.querySelector("[data-accordion-panel]");
    if (!btn || !panel) return;
    btn.addEventListener("click", () => {
      const open = acc.classList.toggle("is-open");
      btn.setAttribute("aria-expanded", open ? "true" : "false");
      panel.hidden = !open;
    });
  });

  root.querySelectorAll("[data-qty]").forEach((wrap) => {
    const input = wrap.querySelector("[data-qty-input]");
    const minus = wrap.querySelector("[data-qty-minus]");
    const plus = wrap.querySelector("[data-qty-plus]");
    if (!input) return;
    const clamp = (n) => Math.max(1, Math.min(99, n));
    minus?.addEventListener("click", () => {
      input.value = String(clamp(Number(input.value || 1) - 1));
    });
    plus?.addEventListener("click", () => {
      input.value = String(clamp(Number(input.value || 1) + 1));
    });
  });
})();
