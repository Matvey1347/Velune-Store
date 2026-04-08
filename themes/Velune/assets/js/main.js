(function () {
  const store = window.VeluneStore;
  if (!store) return;

  const body = document.body;
  const cartDrawer = document.querySelector("[data-cart-drawer]");
  const cartItemsContainer = document.querySelector("[data-cart-items]");
  const cartSubtotal = document.querySelector("[data-cart-subtotal]");
  const cartCountEls = document.querySelectorAll("[data-cart-count]");
  const mobileNav = document.querySelector("[data-mobile-nav]");
  const header = document.querySelector(".site-header");

  const formatMoney = (value) => `$${value.toFixed(2)}`;

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

  const renderCart = () => {
    const items = store.cartDetailed();
    const subtotal = store.getSubtotal();
    const count = items.reduce((sum, item) => sum + item.quantity, 0);

    cartCountEls.forEach((el) => {
      el.textContent = String(count);
    });

    if (cartSubtotal) {
      cartSubtotal.textContent = formatMoney(subtotal);
    }

    if (!cartItemsContainer) return;

    if (!items.length) {
      cartItemsContainer.innerHTML = `
        <div class="empty-state">
          <h3>Your cart is empty</h3>
          <p>Add products or bundles to continue.</p>
        </div>
      `;
      return;
    }

    cartItemsContainer.innerHTML = items.map((item) => `
      <article class="cart-item">
        <div class="cart-item__media">
          <img src="${item.image}" alt="${item.name}" />
        </div>
        <div class="cart-item__body">
          <h4>${item.name}</h4>
          <p>${item.meta}</p>
          <div class="cart-item__meta">
            <div class="qty-control">
              <button type="button" data-qty-change="${item.id}" data-change="-1">−</button>
              <span>${item.quantity}</span>
              <button type="button" data-qty-change="${item.id}" data-change="1">+</button>
            </div>
            <strong>${formatMoney(item.lineTotal)}</strong>
          </div>
          <button type="button" class="cart-remove" data-remove-item="${item.id}">Remove</button>
        </div>
      </article>
    `).join("");
  };

  document.addEventListener("click", (event) => {
    const addBtn = event.target.closest("[data-add-to-cart]");
    if (addBtn) {
      const id = addBtn.getAttribute("data-add-to-cart");
      store.addToCart(id, 1);
      renderCart();
      openCart();
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

    const qtyBtn = event.target.closest("[data-qty-change]");
    if (qtyBtn) {
      const id = qtyBtn.getAttribute("data-qty-change");
      const change = Number(qtyBtn.getAttribute("data-change")) || 0;
      const item = store.readCart().find((entry) => entry.id === id);
      if (!item) return;
      store.updateQuantity(id, item.quantity + change);
      renderCart();
      return;
    }

    const removeBtn = event.target.closest("[data-remove-item]");
    if (removeBtn) {
      const id = removeBtn.getAttribute("data-remove-item");
      store.removeFromCart(id);
      renderCart();
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

  const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        entry.target.classList.add("is-visible");
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.15 });

  document.querySelectorAll(".fade-in-up").forEach((el) => observer.observe(el));

  const updateHeaderState = () => {
    if (!header) return;
    header.classList.toggle("is-scrolled", window.scrollY > 8);
  };

  updateHeaderState();
  renderCart();
  window.addEventListener("scroll", updateHeaderState, { passive: true });
  window.addEventListener("velune:cart-updated", renderCart);
})();
