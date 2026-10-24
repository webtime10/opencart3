(() => {
  const root = document.querySelector("[data-product-page]");
  if (!root) return;

  const tabsRoot = root.querySelector("[data-tabs]");
  const tabButtons = tabsRoot ? [...tabsRoot.querySelectorAll("[data-tab]")] : [];
  const panels = tabsRoot ? [...tabsRoot.querySelectorAll("[data-panel]")] : [];

  const activateTab = (id) => {
    tabButtons.forEach((btn) => {
      const on = btn.dataset.tab === id;
      btn.classList.toggle("is-active", on);
      btn.setAttribute("aria-selected", on ? "true" : "false");
    });
    panels.forEach((panel) => {
      const on = panel.dataset.panel === id;
      panel.classList.toggle("is-active", on);
      panel.hidden = !on;
    });
  };

  tabButtons.forEach((btn) => {
    btn.addEventListener("click", () => activateTab(btn.dataset.tab));
  });
})();
