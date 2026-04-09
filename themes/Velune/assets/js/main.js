(function () {
  const store = window.VeluneStore;

  if (!store) {
    return;
  }

  const body = document.body;
  const cartDrawer = document.querySelector("[data-cart-drawer]");
  const cartItemsContainer = document.querySelector("[data-cart-items]");
  const cartSubtotal = document.querySelector("[data-cart-subtotal]");
  const cartCountEls = document.querySelectorAll("[data-cart-count]");
  const cartPageItemsContainer = document.querySelector("[data-cart-page-items]");
  const cartPageSubtotal = document.querySelector("[data-cart-page-subtotal]");
  const cartPageShipping = document.querySelector("[data-cart-page-shipping]");
  const cartPageTotal = document.querySelector("[data-cart-page-total]");
  const mobileNav = document.querySelector("[data-mobile-nav]");
  const header = document.querySelector(".site-header");
  const themeConfig = window.veluneThemeConfig || {};
  const searchPanel = document.querySelector("[data-search-panel]");
  const searchToggle = document.querySelector("[data-search-toggle]");
  const searchClear = document.querySelector("[data-search-clear]");
  const searchInput = document.querySelector("[data-live-search-input]");
  const searchResults = document.querySelector("[data-live-search-results]");
  const searchMinChars = Math.max(1, Number.parseInt(themeConfig.searchMinChars, 10) || 2);
  const searchLimit = Math.max(3, Number.parseInt(themeConfig.searchLimit, 10) || 8);
  const searchAjaxUrl = themeConfig.ajaxUrl || "";
  const searchNonce = themeConfig.searchNonce || "";
  let liveSearchTimer = 0;
  let liveSearchRequestId = 0;
  let liveSearchController = null;

  const emptyStateHtml = `
    <div class="empty-state">
      <h3>Your cart is empty</h3>
      <p>Add products to continue.</p>
    </div>
  `;

  const escapeHtml = (value) => {
    const map = {
      "&": "&amp;",
      "<": "&lt;",
      ">": "&gt;",
      '"': "&quot;",
      "'": "&#039;"
    };

    return String(value || "").replace(/[&<>"']/g, (char) => map[char] || char);
  };

  const clearLiveSearchTimer = () => {
    if (!liveSearchTimer) {
      return;
    }

    window.clearTimeout(liveSearchTimer);
    liveSearchTimer = 0;
  };

  const abortLiveSearch = () => {
    if (!liveSearchController) {
      return;
    }

    liveSearchController.abort();
    liveSearchController = null;
  };

  const renderSearchState = (message) => {
    if (!searchResults) {
      return;
    }

    searchResults.innerHTML = `<p class="search-results-state">${escapeHtml(message)}</p>`;
  };

  const updateSearchClearVisibility = () => {
    if (!searchClear || !searchInput) {
      return;
    }

    const hasValue = String(searchInput.value || "").trim().length > 0;
    searchClear.classList.toggle("is-hidden", !hasValue);
    searchClear.setAttribute("aria-hidden", hasValue ? "false" : "true");
  };

  const getDefaultSearchLink = (query = "") => {
    const term = String(query || "").trim();
    const baseHomeUrl = String(themeConfig.homeUrl || "/");

    if (!term) {
      return baseHomeUrl;
    }

    const separator = baseHomeUrl.includes("?") ? "&" : "?";
    return `${baseHomeUrl}${separator}s=${encodeURIComponent(term)}`;
  };

  const renderLiveSearchResults = (payload, query) => {
    if (!searchResults) {
      return;
    }

    const groups = Array.isArray(payload?.groups) ? payload.groups : [];
    const hasItems = groups.some((group) => Array.isArray(group?.items) && group.items.length > 0);
    const searchUrl = payload?.search_url || getDefaultSearchLink(query);

    if (!hasItems) {
      searchResults.innerHTML = `
        <p class="search-results-state">${escapeHtml(themeConfig.searchEmptyLabel || "No matching results yet.")}</p>
        <div class="search-results-footer">
          <a href="${escapeHtml(searchUrl)}">${escapeHtml(themeConfig.searchViewAllLabel || "View full search results")}</a>
        </div>
      `;
      return;
    }

    const groupsMarkup = groups
      .filter((group) => Array.isArray(group?.items) && group.items.length > 0)
      .map((group) => {
        const itemsMarkup = group.items
          .map((item) => {
            const thumbMarkup = item.thumbnail
              ? `<img class="search-result-thumb" src="${escapeHtml(item.thumbnail)}" alt="" loading="lazy" decoding="async">`
              : `<span class="search-result-thumb" aria-hidden="true"></span>`;

            const metaLabel = item.type_label ? `<span class="pill">${escapeHtml(item.type_label)}</span>` : "";
            const priceLabel = item.price ? `<span class="search-result-price">${escapeHtml(item.price)}</span>` : "";

            return `
              <li>
                <a class="search-result-link" href="${escapeHtml(item.url || "#")}">
                  ${thumbMarkup}
                  <span class="search-result-content">
                    <span class="search-result-title">${escapeHtml(item.title || "")}</span>
                    <span class="search-result-meta">${metaLabel}${priceLabel}</span>
                  </span>
                </a>
              </li>
            `;
          })
          .join("");

        return `
          <section class="search-results-group">
            <span class="search-results-label">${escapeHtml(group.label || "")}</span>
            <ul class="search-results-list">${itemsMarkup}</ul>
          </section>
        `;
      })
      .join("");

    searchResults.innerHTML = `
      ${groupsMarkup}
      <div class="search-results-footer">
        <a href="${escapeHtml(searchUrl)}">${escapeHtml(themeConfig.searchViewAllLabel || "View full search results")}</a>
      </div>
    `;
  };

  const resetSearchUi = () => {
    abortLiveSearch();
    clearLiveSearchTimer();

    if (searchInput) {
      searchInput.value = "";
    }

    updateSearchClearVisibility();
    renderSearchState(
      themeConfig.searchHintLabel || `Type at least ${searchMinChars} characters to search.`
    );
  };

  const closeSearchPanel = () => {
    if (!header || !searchPanel || !searchToggle) {
      return;
    }

    header.classList.remove("is-search-open");
    searchPanel.setAttribute("aria-hidden", "true");
    searchToggle.setAttribute("aria-expanded", "false");
    resetSearchUi();
  };

  const openSearchPanel = () => {
    if (!header || !searchPanel || !searchToggle) {
      return;
    }

    header.classList.add("is-search-open");
    searchPanel.setAttribute("aria-hidden", "false");
    searchToggle.setAttribute("aria-expanded", "true");
    updateSearchClearVisibility();

    window.setTimeout(() => {
      if (searchInput) {
        searchInput.focus({ preventScroll: true });
      }
    }, 12);

    if (searchInput) {
      const query = searchInput.value.trim();

      if (query.length >= searchMinChars) {
        searchInput.dispatchEvent(new Event("input", { bubbles: true }));
      } else {
        renderSearchState(
          themeConfig.searchHintLabel || `Type at least ${searchMinChars} characters to search.`
        );
      }
    }
  };

  const isSearchPanelOpen = () => Boolean(header?.classList.contains("is-search-open"));

  const requestLiveSearch = async (query) => {
    const normalizedQuery = String(query || "").trim();

    if (normalizedQuery.length < searchMinChars || !searchAjaxUrl || !searchNonce) {
      return;
    }

    abortLiveSearch();
    liveSearchRequestId += 1;
    const requestId = liveSearchRequestId;
    liveSearchController = new AbortController();

    const params = new URLSearchParams();
    params.set("action", "velune_live_search");
    params.set("nonce", searchNonce);
    params.set("query", normalizedQuery);
    params.set("limit", String(searchLimit));

    try {
      const response = await fetch(searchAjaxUrl, {
        method: "POST",
        credentials: "same-origin",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8"
        },
        body: params.toString(),
        signal: liveSearchController.signal
      });

      const data = await response.json();

      if (!response.ok || !data?.success || requestId !== liveSearchRequestId) {
        throw new Error(data?.data?.message || "Search failed.");
      }

      renderLiveSearchResults(data.data || {}, normalizedQuery);
    } catch (error) {
      if (error?.name === "AbortError") {
        return;
      }

      renderSearchState(themeConfig.searchErrorLabel || "Unable to load search results right now.");
    } finally {
      if (requestId === liveSearchRequestId) {
        liveSearchController = null;
      }
    }
  };

  const queueLiveSearch = (query) => {
    const normalizedQuery = String(query || "").trim();
    clearLiveSearchTimer();

    if (normalizedQuery.length < searchMinChars) {
      abortLiveSearch();
      renderSearchState(
        themeConfig.searchHintLabel || `Type at least ${searchMinChars} characters to search.`
      );
      return;
    }

    renderSearchState(themeConfig.searchLoadingLabel || "Searching...");

    liveSearchTimer = window.setTimeout(() => {
      requestLiveSearch(normalizedQuery);
    }, 180);
  };

  if (searchInput) {
    queueLiveSearch(searchInput.value);
    searchInput.addEventListener("input", () => {
      updateSearchClearVisibility();
      queueLiveSearch(searchInput.value);
    });
  }

  const openCart = () => {
    if (!cartDrawer) return;
    cartDrawer.classList.add("is-open");
    cartDrawer.setAttribute("aria-hidden", "false");
    body.style.overflow = "hidden";
  };

  const closeCart = () => {
    if (!cartDrawer) return;
    cartDrawer.classList.remove("is-open");
    cartDrawer.setAttribute("aria-hidden", "true");
    body.style.overflow = "";
  };

  const renderCart = (state = store.getState()) => {
    const count = Number(state?.count || 0);

    cartCountEls.forEach((el) => {
      el.textContent = String(count);
    });

    if (cartSubtotal) {
      cartSubtotal.innerHTML = state?.subtotal || "$0.00";
    }

    if (!cartItemsContainer) {
      return;
    }

    cartItemsContainer.innerHTML = state?.items_html || emptyStateHtml;
  };

  const renderCartPage = (state = store.getState()) => {
    if (cartPageItemsContainer) {
      cartPageItemsContainer.innerHTML = state?.page_items_html || state?.items_html || emptyStateHtml;
    }

    if (cartPageSubtotal) {
      cartPageSubtotal.innerHTML = state?.subtotal || "$0.00";
    }

    if (cartPageShipping) {
      cartPageShipping.innerHTML = state?.shipping || "Free";
    }

    if (cartPageTotal) {
      cartPageTotal.innerHTML = state?.total || state?.subtotal || "$0.00";
    }
  };

  const getProductQuantity = (state, productId) => {
    const map = state?.items_by_product || {};
    const item = map?.[String(productId)] || map?.[productId];
    return Number(item?.quantity || 0);
  };

  const getQtyPrecision = (value) => {
    if (!Number.isFinite(value)) {
      return 0;
    }

    const raw = String(value);
    const decimal = raw.split(".")[1];
    return decimal ? decimal.length : 0;
  };

  const getSafeStep = (input) => {
    const rawStep = Number.parseFloat(input?.getAttribute("step") || "");
    return Number.isFinite(rawStep) && rawStep > 0 ? rawStep : 1;
  };

  const renderProductCards = (state = store.getState()) => {
    document.querySelectorAll("[data-product-actions][data-product-id]").forEach((actionsEl) => {
      const productId = Number(actionsEl.getAttribute("data-product-id"));

      if (!productId) {
        return;
      }

      const addBtn = actionsEl.querySelector("[data-product-add]");
      const qtyControl = actionsEl.querySelector("[data-product-qty-control]");
      const qtyValue = actionsEl.querySelector("[data-product-qty-value]");

      if (!addBtn || !qtyControl || !qtyValue) {
        return;
      }

      const quantity = getProductQuantity(state, productId);
      const inCart = quantity > 0;

      addBtn.classList.toggle("hidden", inCart);
      qtyControl.classList.toggle("hidden", !inCart);
      qtyValue.textContent = String(Math.max(0, quantity));
    });
  };

  const renderAll = (state = store.getState()) => {
    renderCart(state);
    renderCartPage(state);
    renderProductCards(state);
  };

  const runCartAction = async (callback, options = {}) => {
    const { silent = false } = options;

    try {
      const nextState = await callback();
      renderAll(nextState);
      return nextState;
    } catch (error) {
      // eslint-disable-next-line no-console
      console.error(error);

      try {
        const syncedState = await store.refreshCart();
        renderAll(syncedState);
      } catch (refreshError) {
        // eslint-disable-next-line no-console
        console.error(refreshError);
      }

      if (!silent) {
        window.dispatchEvent(
          new CustomEvent("velune:cart-error", {
            detail: {
              message: error?.message || "Unable to update cart right now."
            }
          })
        );
      }

      return null;
    }
  };

  document.addEventListener("click", async (event) => {
    if (event.target.closest("[data-search-toggle]")) {
      if (isSearchPanelOpen()) {
        closeSearchPanel();
      } else {
        openSearchPanel();
      }

      return;
    }

    if (event.target.closest("[data-search-clear]")) {
      event.preventDefault();
      if (!searchInput) {
        return;
      }

      searchInput.value = "";
      updateSearchClearVisibility();
      abortLiveSearch();
      clearLiveSearchTimer();
      renderSearchState(
        themeConfig.searchHintLabel || `Type at least ${searchMinChars} characters to search.`
      );
      searchInput.focus({ preventScroll: true });
      return;
    }

    if (isSearchPanelOpen() && searchPanel && !event.target.closest("[data-search-panel]")) {
      closeSearchPanel();
    }

    const qtyActionBtn = event.target.closest("[data-qty-action]");

    if (qtyActionBtn) {
      const qtyRoot = qtyActionBtn.closest("[data-qty-root]");
      const input = qtyRoot ? qtyRoot.querySelector("input.qty") : null;

      if (!input || input.disabled || input.readOnly) {
        return;
      }

      const step = getSafeStep(input);
      const min = Number.parseFloat(input.getAttribute("min") || "");
      const max = Number.parseFloat(input.getAttribute("max") || "");
      const current = Number.parseFloat(input.value || "");
      const precision = getQtyPrecision(step);
      const change = qtyActionBtn.getAttribute("data-qty-action") === "decrease" ? -step : step;
      let next = Number.isFinite(current) ? current + change : step;

      if (Number.isFinite(min)) {
        next = Math.max(min, next);
      }

      if (Number.isFinite(max) && max > 0) {
        next = Math.min(max, next);
      }

      input.value = String(Number(next.toFixed(precision)));
      input.dispatchEvent(new Event("input", { bubbles: true }));
      input.dispatchEvent(new Event("change", { bubbles: true }));
      return;
    }

    const addBtn = event.target.closest("[data-add-to-cart]");

    if (addBtn) {
      const productId = Number(addBtn.getAttribute("data-add-to-cart"));

      if (!productId) {
        return;
      }

      addBtn.setAttribute("disabled", "disabled");

      await runCartAction(() => store.addToCart(productId, 1));

      addBtn.removeAttribute("disabled");

      if (addBtn.getAttribute("data-open-cart") !== "false") {
        openCart();
      }

      return;
    }

    const productQtyBtn = event.target.closest("[data-product-id][data-product-change]");

    if (productQtyBtn) {
      const productId = Number(productQtyBtn.getAttribute("data-product-id"));
      const change = Number(productQtyBtn.getAttribute("data-product-change") || 0);
      const currentQty = getProductQuantity(store.getState(), productId);
      const nextQty = Math.max(0, currentQty + change);

      if (!productId) {
        return;
      }

      productQtyBtn.setAttribute("disabled", "disabled");

      await runCartAction(() => store.setProductQuantity(productId, nextQty));

      productQtyBtn.removeAttribute("disabled");
      return;
    }

    const buyNowBtn = event.target.closest("[data-buy-now]");

    if (buyNowBtn) {
      const productId = Number(buyNowBtn.getAttribute("data-buy-now"));

      if (!productId || typeof store.buyNow !== "function") {
        return;
      }

      buyNowBtn.setAttribute("disabled", "disabled");

      try {
        const payload = await store.buyNow(productId, 1);
        const redirectUrl = String(payload?.redirect_url || "");

        if (redirectUrl) {
          window.location.href = redirectUrl;
          return;
        }
      } catch (error) {
        window.dispatchEvent(
          new CustomEvent("velune:cart-error", {
            detail: {
              message: error?.message || "Unable to start checkout right now."
            }
          })
        );
      }

      buyNowBtn.removeAttribute("disabled");
      return;
    }

    if (event.target.closest("[data-cart-toggle]")) {
      closeSearchPanel();
      openCart();
      return;
    }

    if (event.target.closest("[data-cart-close]")) {
      closeCart();
      return;
    }

    const qtyBtn = event.target.closest("[data-cart-item-key][data-change]");

    if (qtyBtn) {
      const cartItemKey = qtyBtn.getAttribute("data-cart-item-key");
      const change = Number(qtyBtn.getAttribute("data-change") || 0);
      const cartItem = qtyBtn.closest("[data-cart-item]");
      const qtyText = cartItem ? cartItem.querySelector(".qty-control span") : null;
      const currentQty = Number(qtyText ? qtyText.textContent : 1) || 1;
      const nextQty = Math.max(0, currentQty + change);

      if (!cartItemKey) {
        return;
      }

      qtyBtn.setAttribute("disabled", "disabled");

      await runCartAction(() => store.updateCartItem(cartItemKey, nextQty));

      qtyBtn.removeAttribute("disabled");
      return;
    }

    const removeBtn = event.target.closest("[data-remove-item]");

    if (removeBtn) {
      const cartItemKey = removeBtn.getAttribute("data-remove-item");

      if (!cartItemKey) {
        return;
      }

      removeBtn.setAttribute("disabled", "disabled");

      await runCartAction(() => store.removeCartItem(cartItemKey));

      removeBtn.removeAttribute("disabled");
      return;
    }

    const mobileToggle = event.target.closest("[data-mobile-nav-toggle]");

    if (mobileToggle && mobileNav) {
      closeSearchPanel();
      mobileNav.classList.toggle("is-open");
      return;
    }

    const filterBtn = event.target.closest("[data-filter]");

    if (filterBtn) {
      const filter = filterBtn.getAttribute("data-filter");

      document.querySelectorAll("[data-filter]").forEach((button) => {
        button.classList.toggle("is-active", button === filterBtn);
      });

      document.querySelectorAll("[data-blog-grid] [data-category]").forEach((card) => {
        const visible = filter === "all" || card.getAttribute("data-category") === filter;
        card.classList.toggle("hidden", !visible);
      });
    }
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
      closeCart();
      closeSearchPanel();
    }
  });

  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add("is-visible");
          observer.unobserve(entry.target);
        }
      });
    },
    { threshold: 0.15 }
  );

  document.querySelectorAll(".fade-in-up").forEach((el) => observer.observe(el));

  const updateHeaderState = () => {
    if (!header) return;
    header.classList.toggle("is-scrolled", window.scrollY > 8);
  };

  updateHeaderState();
  renderAll();

  updateSearchClearVisibility();

  window.addEventListener("scroll", updateHeaderState, { passive: true });
  window.addEventListener("velune:cart-updated", (event) => {
    renderAll(event.detail);
  });

  runCartAction(() => store.refreshCart(), { silent: true });
})();
