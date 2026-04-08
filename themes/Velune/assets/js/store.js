(function () {
  const PRODUCTS = {
    "body-wash": {
      id: "body-wash",
      name: "Body Wash",
      price: 28,
      image: "assets/images/products/body-wash.webp",
      meta: "250 ml"
    },
    cream: {
      id: "cream",
      name: "Face Cream",
      price: 42,
      image: "assets/images/products/cream.webp",
      meta: "50 ml"
    },
    serum: {
      id: "serum",
      name: "Serum",
      price: 48,
      image: "assets/images/products/serum.webp",
      meta: "30 ml"
    },
    "complete-bundle": {
      id: "complete-bundle",
      name: "Complete Bundle",
      price: 108,
      image: "assets/images/bundle/bundle.webp",
      meta: "Body Wash + Cream + Serum"
    },
    "complete-bundle-subscription": {
      id: "complete-bundle-subscription",
      name: "Complete Bundle Subscription",
      price: 86,
      image: "assets/images/bundle/bundle.webp",
      meta: "Recurring delivery"
    }
  };

  const STORAGE_KEY = "velune-cart";

  const readCart = () => {
    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      return raw ? JSON.parse(raw) : [];
    } catch (error) {
      return [];
    }
  };

  const writeCart = (cart) => {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(cart));
    window.dispatchEvent(new CustomEvent("velune:cart-updated", { detail: cart }));
  };

  const addToCart = (productId, quantity = 1) => {
    const product = PRODUCTS[productId];
    if (!product) return;

    const cart = readCart();
    const existing = cart.find((item) => item.id === productId);

    if (existing) {
      existing.quantity += quantity;
    } else {
      cart.push({ id: productId, quantity });
    }

    writeCart(cart);
  };

  const updateQuantity = (productId, quantity) => {
    const cart = readCart()
      .map((item) => item.id === productId ? { ...item, quantity } : item)
      .filter((item) => item.quantity > 0);

    writeCart(cart);
  };

  const removeFromCart = (productId) => {
    const cart = readCart().filter((item) => item.id !== productId);
    writeCart(cart);
  };

  const cartDetailed = () => readCart().map((item) => ({
    ...PRODUCTS[item.id],
    quantity: item.quantity,
    lineTotal: PRODUCTS[item.id].price * item.quantity
  })).filter(Boolean);

  const getSubtotal = () => cartDetailed().reduce((sum, item) => sum + item.lineTotal, 0);

  window.VeluneStore = {
    PRODUCTS,
    readCart,
    writeCart,
    addToCart,
    updateQuantity,
    removeFromCart,
    cartDetailed,
    getSubtotal
  };
})();
