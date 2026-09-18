(() => {
  const root = document.querySelector("[data-cart]");
  if (!root) return;

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

  root.querySelectorAll("[data-cart-remove]").forEach((btn) => {
    btn.addEventListener("click", () => {
      btn.closest("[data-cart-item]")?.remove();
    });
  });

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
})();
