(function () {
  const config = window.veluneStoreConfig || {};

  const state = {
    count: 0,
    subtotal: "$0.00",
    total: "$0.00",
    shipping: "Free",
    items_html: "",
    page_items_html: "",
    items_by_product: {},
    cart_url: config.cartUrl || "",
    checkout: config.checkoutUrl || ""
  };

  const hasAjax = Boolean(config.ajaxUrl && config.nonce);

  const setState = (nextState) => {
    Object.assign(state, nextState || {});
    state.items_by_product = state.items_by_product || {};
    window.dispatchEvent(new CustomEvent("velune:cart-updated", { detail: { ...state } }));
    return { ...state };
  };

  const request = async (action, payload = {}) => {
    if (!hasAjax) {
      return { ...state };
    }

    const params = new URLSearchParams();
    params.set("action", action);
    params.set("nonce", config.nonce);

    Object.entries(payload).forEach(([key, value]) => {
      if (value === undefined || value === null) {
        return;
      }

      params.set(key, String(value));
    });

    const response = await fetch(config.ajaxUrl, {
      method: "POST",
      credentials: "same-origin",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8"
      },
      body: params.toString()
    });

    let data;

    try {
      data = await response.json();
    } catch (error) {
      throw new Error(config?.labels?.cartError || "Unable to process cart response.");
    }

    if (!response.ok || !data || !data.success) {
      throw new Error(data?.data?.message || config?.labels?.cartError || "Cart request failed.");
    }

    return data.data || {};
  };

  const refreshCart = async () => {
    const nextState = await request("velune_get_cart");
    return setState(nextState);
  };

  const addToCart = async (productId, quantity = 1) => {
    const nextState = await request("velune_add_to_cart", {
      product_id: productId,
      quantity
    });

    return setState(nextState);
  };

  const updateCartItem = async (cartItemKey, quantity) => {
    const nextState = await request("velune_update_cart_item", {
      cart_item_key: cartItemKey,
      quantity
    });

    return setState(nextState);
  };

  const removeCartItem = async (cartItemKey) => {
    const nextState = await request("velune_remove_cart_item", {
      cart_item_key: cartItemKey
    });

    return setState(nextState);
  };

  const setProductQuantity = async (productId, quantity) => {
    const nextState = await request("velune_set_product_quantity", {
      product_id: productId,
      quantity
    });

    return setState(nextState);
  };

  window.VeluneStore = {
    hasAjax,
    getState: () => ({ ...state }),
    setState,
    refreshCart,
    addToCart,
    updateCartItem,
    removeCartItem,
    setProductQuantity
  };
})();
