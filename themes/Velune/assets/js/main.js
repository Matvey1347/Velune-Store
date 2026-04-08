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

  const emptyStateHtml = `
    <div class="empty-state">
      <h3>Your cart is empty</h3>
      <p>Add products to continue.</p>
    </div>
  `;

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

      addBtn.hidden = inCart;
      qtyControl.hidden = !inCart;
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
    const addBtn = event.target.closest("[data-add-to-cart]");

    if (addBtn) {
      const productId = Number(addBtn.getAttribute("data-add-to-cart"));

      if (!productId) {
        return;
      }

      addBtn.setAttribute("disabled", "disabled");

      await runCartAction(() => store.addToCart(productId, 1));

      addBtn.removeAttribute("disabled");
      openCart();
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

    if (event.target.closest("[data-cart-toggle]")) {
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

  window.addEventListener("scroll", updateHeaderState, { passive: true });
  window.addEventListener("velune:cart-updated", (event) => {
    renderAll(event.detail);
  });

  runCartAction(() => store.refreshCart(), { silent: true });
})();
