(() => {
  const root = document.querySelector("[data-category-ajax]");
  if (!root) return;

  const endpoint = root.dataset.endpoint;
  const grid = root.querySelector("[data-products-grid]");
  const pagerWrap = root.querySelector("[data-pager-wrap]");
  const meta = root.querySelector("[data-page-meta]");
  const found = root.querySelector("[data-found-meta]");
  const loadMore = root.querySelector("[data-load-more]");
  const limitSelect = root.querySelector("[data-limit]");
  const sortSelect = root.querySelector("[data-sort]");

  let page = Number(root.dataset.page || 1);
  let pages = Number(root.dataset.pages || 1);
  let limit = Number(root.dataset.limit || 8);
  let loading = false;

  const setLoading = (on) => {
    loading = on;
    root.classList.toggle("is-loading", on);
    if (loadMore) {
      loadMore.disabled = on || page >= pages;
      loadMore.setAttribute("aria-busy", on ? "true" : "false");
    }
  };

  const applyState = (data, append) => {
    page = data.page;
    pages = data.pages;
    limit = data.limit;
    root.dataset.page = String(page);
    root.dataset.pages = String(pages);

    if (append) {
      grid.insertAdjacentHTML("beforeend", data.html);
    } else {
      grid.innerHTML = data.html;
      root.scrollIntoView({ behavior: "smooth", block: "start" });
    }

    const shown = grid.querySelectorAll(".card").length;
    if (meta) {
      meta.textContent = append
        ? "Показано 1–" + shown + " з " + data.total
        : data.meta;
    }
    if (found) found.textContent = data.found;
    if (pagerWrap) pagerWrap.innerHTML = data.pager;
    if (loadMore) {
      loadMore.hidden = !data.has_more;
      loadMore.disabled = !data.has_more;
    }
  };

  const fetchPage = async (nextPage, append = false) => {
    if (loading) return;
    setLoading(true);
    try {
      const url = new URL(endpoint, window.location.href);
      url.searchParams.set("page", String(nextPage));
      url.searchParams.set("limit", String(limit));
      if (append) url.searchParams.set("append", "1");
      if (sortSelect) url.searchParams.set("sort", sortSelect.value);

      const res = await fetch(url.toString(), {
        headers: { "X-Requested-With": "XMLHttpRequest" },
      });
      if (!res.ok) throw new Error("HTTP " + res.status);
      const data = await res.json();
      if (!data.ok) throw new Error("Bad payload");
      applyState(data, append);
    } catch (err) {
      console.error(err);
      alert("Не вдалося завантажити товари");
    } finally {
      setLoading(false);
    }
  };

  root.addEventListener("click", (e) => {
    const link = e.target.closest("[data-page]");
    if (!link || !root.contains(link)) return;
    if (link.closest(".disabled")) return;
    e.preventDefault();
    const next = Number(link.dataset.page || 1);
    if (!next || next === page) return;
    fetchPage(next, false);
  });

  if (loadMore) {
    loadMore.addEventListener("click", () => {
      if (page >= pages) return;
      fetchPage(page + 1, true);
    });
  }

  if (limitSelect) {
    limitSelect.addEventListener("change", () => {
      limit = Number(limitSelect.value || 8);
      root.dataset.limit = String(limit);
      fetchPage(1, false);
    });
  }

  if (sortSelect) {
    sortSelect.addEventListener("change", () => fetchPage(1, false));
  }
})();
