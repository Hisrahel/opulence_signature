(function () {
  "use strict";

  // =====================================================
  // CONSTANTS
  // =====================================================
  const FREE_DELIVERY_THRESHOLD = 50000;
  const DELIVERY_FEE = 2500;
  const CART_STORAGE_KEY = "opulence_luxury_cart";
  const WHATSAPP_NUMBER = "2348104240201";

  const $ = (id) => document.getElementById(id);

  // =====================================================
  // STATE
  // =====================================================
  let PRODUCTS = [];
  let cart = [];
  let activeFilter = "all";
  let modalProduct = null;
  let modalQty = 1;
  let selectedLength = "";
  let selectedTexture = "";
  let isSubmittingOrder = false;

  // =====================================================
  // CART PERSISTENCE
  // =====================================================
  function loadCart() {
    try {
      const raw = localStorage.getItem(CART_STORAGE_KEY);
      const parsed = raw ? JSON.parse(raw) : [];
      return Array.isArray(parsed) ? parsed.filter(isValidCartItem) : [];
    } catch {
      return [];
    }
  }

  function isValidCartItem(item) {
    return (
      item &&
      typeof item === "object" &&
      Number.isFinite(Number(item.id)) &&
      Number.isFinite(Number(item.price)) &&
      Number.isFinite(Number(item.qty)) &&
      Number(item.qty) > 0
    );
  }

  function saveCart() {
    try {
      localStorage.setItem(CART_STORAGE_KEY, JSON.stringify(cart));
    } catch (err) {
      console.error("Failed to save cart", err);
    }
  }

  cart = loadCart();

  // =====================================================
  // FORMAT HELPERS
  // =====================================================
  function fmt(n) {
    const num = Number(n) || 0;
    return "₦" + num.toLocaleString("en-NG");
  }

  function escapeHtml(str) {
    const div = document.createElement("div");
    div.textContent = String(str ?? "");
    return div.innerHTML;
  }

  // =====================================================
  // PRODUCT NORMALIZATION
  // =====================================================
  function normalizeProduct(p) {
    const safeCategory = String(p.category || p.cat || "").toLowerCase();
    const safeLengths = Array.isArray(p.lengths)
      ? p.lengths
      : typeof p.lengths === "string"
        ? p.lengths
            .split(",")
            .map((v) => v.trim())
            .filter(Boolean)
        : ["12 inch", "14 inch"];
    const safeTextures = Array.isArray(p.textures)
      ? p.textures
      : typeof p.textures === "string"
        ? p.textures
            .split(",")
            .map((v) => v.trim())
            .filter(Boolean)
        : ["Straight", "Body Wave"];

    return {
      id: Number(p.id),
      name: p.name || p.product_name || "Unnamed Product",
      category: safeCategory,
      price: Number(p.price || 0),
      image: p.image || p.img || "../images/extension.webp",
      desc:
        p.desc ||
        p.description ||
        "Premium human hair curated for a soft, natural finish.",
      badge: p.badge || "",
      inStock: p.in_stock === undefined ? true : !!Number(p.in_stock),
      lengths: safeLengths.length ? safeLengths : ["12 inch", "14 inch"],
      textures: safeTextures.length ? safeTextures : ["Straight", "Body Wave"],
    };
  }

  async function loadProducts() {
    const countEl = $("productCount");
    if (countEl) countEl.textContent = "Loading...";
    try {
      const res = await fetch("../api/get-products.php", {
        cache: "no-store",
      });
      if (!res.ok) throw new Error("Request failed: " + res.status);
      const data = await res.json();
      PRODUCTS = Array.isArray(data) ? data.map(normalizeProduct) : [];
    } catch (err) {
      console.error("Failed to load products", err);
      PRODUCTS = [];
      if (countEl) countEl.textContent = "Failed to load products";
    }
    renderProducts(activeFilter, $("sortSelect")?.value || "default");
    updateCartUI();
  }

  // =====================================================
  // RENDER PRODUCTS
  // =====================================================
  function renderProducts(filter, sort) {
    let list =
      filter === "all"
        ? [...PRODUCTS]
        : PRODUCTS.filter((p) => p.category === filter);

    if (sort === "price-low") list.sort((a, b) => a.price - b.price);
    else if (sort === "price-high") list.sort((a, b) => b.price - a.price);
    else if (sort === "name") list.sort((a, b) => a.name.localeCompare(b.name));

    const grid = $("productsGrid");
    const empty = $("productsEmpty");
    const count = $("productCount");
    if (!grid || !empty || !count) return;

    count.textContent = `${list.length} product${list.length !== 1 ? "s" : ""}`;

    if (list.length === 0) {
      grid.innerHTML = "";
      empty.style.display = "flex";
      return;
    }
    empty.style.display = "none";

    grid.innerHTML = list
      .map(
        (p) => `
          <div class="product-card" data-id="${p.id}">
            <div class="product-card-img" data-action="open-modal" data-id="${p.id}">
              <img src="${escapeHtml(p.image)}" alt="${escapeHtml(p.name)}" loading="lazy" />
              <div class="product-card-overlay"></div>
              ${p.badge ? `<div class="product-badge">${escapeHtml(p.badge)}</div>` : ""}
              ${!p.inStock ? `<div class="stock-badge out">Out of Stock</div>` : ""}
              <button class="product-quick-view" type="button" data-action="quick-view" data-id="${p.id}">
                Quick View
              </button>
            </div>
            <div class="product-card-body">
              <span class="product-category-tag">${escapeHtml(p.category)}</span>
              <h4 class="product-name">${escapeHtml(p.name)}</h4>
              <div class="product-price-row">
                <span class="product-price">${fmt(p.price)}</span>
                <button class="product-add-btn" type="button" data-action="add-to-cart" data-id="${p.id}"
                  aria-label="Add to cart" ${!p.inStock ? "disabled" : ""}>
                  <i class="fa-solid fa-plus"></i>
                </button>
              </div>
            </div>
          </div>
        `,
      )
      .join("");
  }

  // =====================================================
  // FILTER
  // =====================================================
  function filterProducts(filter) {
    activeFilter = filter;
    document.querySelectorAll(".filter-btn").forEach((b) => {
      b.classList.toggle("active", b.dataset.filter === filter);
    });
    renderProducts(filter, $("sortSelect") ? $("sortSelect").value : "default");
    $("products")?.scrollIntoView({ behavior: "smooth", block: "start" });
  }

  // =====================================================
  // MODAL
  // =====================================================
  function openModal(id) {
    modalProduct = PRODUCTS.find((p) => p.id === id);
    if (!modalProduct) return;

    modalQty = 1;
    selectedLength = modalProduct.lengths[0] || "12 inch";
    selectedTexture = modalProduct.textures[0] || "Straight";

    $("modalImg").src = modalProduct.image;
    $("modalImg").alt = modalProduct.name;
    $("modalTitle").textContent = modalProduct.name;
    $("modalPrice").textContent = fmt(modalProduct.price);
    $("modalDesc").textContent = modalProduct.desc;
    $("modalCategory").textContent = modalProduct.category.toUpperCase();
    $("qtyVal").textContent = 1;

    const badge = $("modalBadge");
    if (!modalProduct.inStock) {
      badge.textContent = "Out of Stock";
      badge.style.display = "block";
    } else if (modalProduct.badge) {
      badge.textContent = modalProduct.badge;
      badge.style.display = "block";
    } else {
      badge.style.display = "none";
    }

    const lengthsEl = $("modalLengths");
    lengthsEl.innerHTML = modalProduct.lengths
      .map(
        (l, i) =>
          `<button type="button" class="option-pill ${i === 0 ? "active" : ""}" data-type="length" data-val="${escapeHtml(l)}">${escapeHtml(l)}</button>`,
      )
      .join("");

    const texturesEl = $("modalTextures");
    texturesEl.innerHTML = modalProduct.textures
      .map(
        (t, i) =>
          `<button type="button" class="option-pill ${i === 0 ? "active" : ""}" data-type="texture" data-val="${escapeHtml(t)}">${escapeHtml(t)}</button>`,
      )
      .join("");

    // Re-bind pill listeners (elements were just recreated)
    document
      .querySelectorAll(
        "#modalLengths .option-pill, #modalTextures .option-pill",
      )
      .forEach((pill) => {
        pill.addEventListener("click", function () {
          const type = this.dataset.type;
          this.parentElement
            .querySelectorAll(".option-pill")
            .forEach((p) => p.classList.remove("active"));
          this.classList.add("active");
          if (type === "length") selectedLength = this.dataset.val;
          else selectedTexture = this.dataset.val;
        });
      });

    const addBtn = $("modalAddToCart");
    if (!modalProduct.inStock) {
      addBtn.disabled = true;
      addBtn.querySelector("span").textContent = "Out of Stock";
    } else {
      addBtn.disabled = false;
      addBtn.querySelector("span").textContent = "Add to Cart";
    }

    $("productModal").classList.add("open");
    $("productModalOverlay").classList.add("open");
    document.body.style.overflow = "hidden";
  }

  function closeModal() {
    $("productModal").classList.remove("open");
    $("productModalOverlay").classList.remove("open");
    document.body.style.overflow = "";
  }

  // =====================================================
  // CART LOGIC
  // =====================================================
  function addToCart(product, qty, length, texture) {
    if (!product || !product.inStock) return;
    qty = Math.max(1, Math.floor(Number(qty) || 1));

    const key = `${product.id}-${length}-${texture}`;
    const existing = cart.find((item) => item.key === key);
    if (existing) {
      existing.qty += qty;
    } else {
      cart.push({
        key,
        id: product.id,
        name: product.name,
        price: Number(product.price) || 0,
        image: product.image,
        length,
        texture,
        qty,
      });
    }
    saveCart();
    updateCartUI();
    flashCartBtn();
    showToast(`${product.name} added to cart`);
  }

  function quickAddToCart(id) {
    const p = PRODUCTS.find((item) => item.id === id);
    if (!p) return;
    if (!p.inStock) {
      showToast("This item is currently out of stock", true);
      return;
    }
    addToCart(p, 1, p.lengths[0] || "12 inch", p.textures[0] || "Straight");
  }

  function removeFromCart(key) {
    cart = cart.filter((i) => i.key !== key);
    saveCart();
    updateCartUI();
    renderCartItems();
  }

  function changeCartQty(key, delta) {
    const item = cart.find((i) => i.key === key);
    if (!item) return;
    item.qty += delta;
    if (item.qty <= 0) {
      removeFromCart(key);
      return;
    }
    saveCart();
    updateCartUI();
    renderCartItems();
  }

  function getCartSubtotal() {
    return cart.reduce(
      (s, i) => s + (Number(i.price) || 0) * (Number(i.qty) || 0),
      0,
    );
  }

  function getCartItemCount() {
    return cart.reduce((s, i) => s + (Number(i.qty) || 0), 0);
  }

  function getDeliveryFee(subtotal) {
    return subtotal >= FREE_DELIVERY_THRESHOLD ? 0 : DELIVERY_FEE;
  }

  // =====================================================
  // CART UI
  // =====================================================
  function updateCartUI() {
    const total = getCartItemCount();
    const countEl = $("cartCount");
    if (countEl) {
      countEl.textContent = total;
      countEl.style.display = total > 0 ? "flex" : "none";
    }

    const headCount = $("cartHeadCount");
    if (headCount) headCount.textContent = total;

    const subtotal = getCartSubtotal();
    const delivery = getDeliveryFee(subtotal);
    const grandTotal = subtotal + delivery;

    const itemCountSummary = $("cartItemCountSummary");
    const subtotalSummary = $("cartSubtotalSummary");
    const deliverySummary = $("cartDeliverySummary");
    const totalSummary = $("cartTotalSummary");
    const freeNote = $("freeDeliveryNote");

    if (itemCountSummary) itemCountSummary.textContent = total;
    if (subtotalSummary) subtotalSummary.textContent = fmt(subtotal);
    if (deliverySummary)
      deliverySummary.textContent = delivery === 0 ? "Free" : fmt(delivery);
    if (totalSummary) totalSummary.textContent = fmt(grandTotal);

    if (freeNote) {
      if (subtotal > 0 && subtotal < FREE_DELIVERY_THRESHOLD) {
        const remaining = FREE_DELIVERY_THRESHOLD - subtotal;
        freeNote.classList.add("cart-note-highlight");
      } else {
        freeNote.textContent = "Shipping & taxes calculated at checkout";
        freeNote.classList.remove("cart-note-highlight");
      }
    }

    // Disable checkout entirely when cart is empty (extra safety beyond the empty-state swap)
    const checkoutBtn = $("checkoutToggleBtn");
    if (checkoutBtn) checkoutBtn.disabled = cart.length === 0;
  }

  function flashCartBtn() {
    const btn = $("cartNavBtn");
    if (!btn) return;
    btn.classList.add("flash");
    setTimeout(() => btn.classList.remove("flash"), 600);
  }

  function renderCartItems() {
    const el = $("cartItems");
    const footer = $("cartFooter");
    const empty = $("cartEmpty");
    if (!el || !footer || !empty) return;

    if (cart.length === 0) {
      empty.style.display = "flex";
      footer.style.display = "none";
      el.innerHTML = "";
      el.appendChild(empty);
      toggleCheckoutPanel(false);
      return;
    }

    empty.style.display = "none";
    footer.style.display = "block";

    el.innerHTML = cart
      .map(
        (item) => `
          <div class="cart-item">
            <img src="${escapeHtml(item.image)}" alt="${escapeHtml(item.name)}" class="cart-item-img" />
            <div class="cart-item-details">
              <h5>${escapeHtml(item.name)}</h5>
              <span>${escapeHtml(item.length)} · ${escapeHtml(item.texture)}</span>
              <div class="cart-item-price-row">
                <span class="cart-item-price">${fmt((Number(item.price) || 0) * (Number(item.qty) || 0))}</span>
                <div class="cart-item-qty">
                  <button type="button" data-qty-action="dec" data-key="${escapeHtml(item.key)}" aria-label="Decrease quantity">−</button>
                  <span>${item.qty}</span>
                  <button type="button" data-qty-action="inc" data-key="${escapeHtml(item.key)}" aria-label="Increase quantity">+</button>
                </div>
              </div>
            </div>
            <button class="cart-item-remove" data-remove-key="${escapeHtml(item.key)}" aria-label="Remove item">
              <i class="fa-solid fa-xmark"></i>
            </button>
          </div>
        `,
      )
      .join("");

    updateCartUI();
  }

  function openCart() {
    renderCartItems();
    $("cartDrawer").classList.add("open");
    $("cartOverlay").classList.add("open");
    document.body.style.overflow = "hidden";
  }

  function closeCart() {
    $("cartDrawer").classList.remove("open");
    $("cartOverlay").classList.remove("open");
    document.body.style.overflow = "";
    toggleCheckoutPanel(false);
    clearCheckoutStatus();
  }

  // =====================================================
  // TOAST
  // =====================================================
  let toastTimer = null;
  function showToast(message, isError) {
    const toast = $("cartToast");
    if (!toast) return;
    toast.textContent = message;
    toast.className = "cart-toast show" + (isError ? " error" : "");
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => {
      toast.className = "cart-toast";
    }, 2600);
  }

  // =====================================================
  // CHECKOUT
  // =====================================================
  function toggleCheckoutPanel(force) {
    const panel = $("checkoutPanel");
    const button = $("checkoutToggleBtn");
    if (!panel || !button) return;
    const show =
      typeof force === "boolean" ? force : panel.style.display === "none";
    panel.style.display = show ? "block" : "none";
    button.classList.toggle("active", show);
    button.innerHTML = show
      ? '<span>Hide Checkout</span><i class="fa-solid fa-chevron-up"></i>'
      : '<span>Checkout</span><i class="fa-solid fa-arrow-right"></i>';
  }

  function clearCheckoutStatus() {
    const status = $("checkoutStatus");
    if (status) {
      status.textContent = "";
      status.className = "checkout-status";
    }
  }

  function setCheckoutStatus(message, type) {
    const status = $("checkoutStatus");
    if (!status) return;
    status.textContent = message;
    status.className = "checkout-status " + (type || "");
  }

  async function submitCheckout() {
    if (isSubmittingOrder) return;
    if (cart.length === 0) {
      setCheckoutStatus("Your cart is empty.", "error");
      return;
    }

    const name = $("checkoutName").value.trim();
    const phone = $("checkoutPhone").value.trim();
    const email = $("checkoutEmail").value.trim();
    const address = $("checkoutAddress").value.trim();
    const notes = $("checkoutNotes").value.trim();

    if (!name || !phone || !address) {
      setCheckoutStatus(
        "Please fill in your name, phone, and delivery address.",
        "error",
      );
      return;
    }
    const digitsOnly = phone.replace(/\D/g, "");
    if (digitsOnly.length < 10) {
      setCheckoutStatus("Please enter a valid phone number.", "error");
      return;
    }

    const submitBtn = $("submitCheckoutBtn");
    isSubmittingOrder = true;
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.innerHTML =
        '<span><i class="fa-solid fa-spinner fa-spin"></i> Placing order...</span>';
    }
    setCheckoutStatus("", "");

    const payload = {
      type: "luxury",
      name,
      phone,
      email,
      address,
      notes,
      items: cart.map((item) => ({
        id: item.id,
        qty: item.qty,
        length: item.length,
        texture: item.texture,
      })),
    };

    try {
      const res = await fetch("../api/place-order.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });

      let result;
      try {
        result = await res.json();
      } catch {
        throw new Error("Unexpected server response.");
      }

      if (!result.success) {
        setCheckoutStatus(
          result.message || "Something went wrong. Please try again.",
          "error",
        );
        isSubmittingOrder = false;
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.innerHTML = "<span>Place Order</span>";
        }
        return;
      }

      // Build WhatsApp confirmation message using server-confirmed totals
      const itemsText = cart
        .map(
          (item) =>
            `• ${item.name} (${item.length}, ${item.texture}) x${item.qty}`,
        )
        .join("\n");

      const waMessage =
        `Hello Opulence Luxury, I just placed an order.\n\n` +
        `Order Ref: ${result.order_ref}\n` +
        `Customer: ${name}\nPhone: ${phone}\nEmail: ${email || "Not provided"}\n` +
        `Address: ${address}\nNotes: ${notes || "None"}\n\n` +
        `Items:\n${itemsText}\n\n` +
        `Subtotal: ${fmt(result.subtotal)}\n` +
        `Delivery: ${result.delivery === 0 ? "Free" : fmt(result.delivery)}\n` +
        `Total: ${fmt(result.total)}`;

      setCheckoutStatus(
        `Order placed! Ref: ${result.order_ref}. Redirecting to WhatsApp to confirm...`,
        "success",
      );

      // Clear cart now that the order is safely recorded server-side
      cart = [];
      saveCart();
      updateCartUI();
      renderCartItems();

      $("checkoutName").value = "";
      $("checkoutPhone").value = "";
      $("checkoutEmail").value = "";
      $("checkoutAddress").value = "";
      $("checkoutNotes").value = "";

      setTimeout(() => {
        window.open(
          `https://wa.me/${WHATSAPP_NUMBER}?text=${encodeURIComponent(waMessage)}`,
          "_blank",
          "noopener,noreferrer",
        );
        closeCart();
      }, 1200);
    } catch (err) {
      console.error(err);
      setCheckoutStatus(
        "Network error. Please check your connection and try again.",
        "error",
      );
    } finally {
      isSubmittingOrder = false;
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.innerHTML = "<span>Place Order</span>";
      }
    }
  }

  // =====================================================
  // EVENT WIRING
  // =====================================================
  document.addEventListener("DOMContentLoaded", () => {
    renderProducts(activeFilter, "default");
    updateCartUI();
    loadProducts();

    // Filter buttons
    document.querySelectorAll(".filter-btn").forEach((btn) => {
      btn.addEventListener("click", () => filterProducts(btn.dataset.filter));
    });

    // Sort
    $("sortSelect")?.addEventListener("change", (e) => {
      renderProducts(activeFilter, e.target.value);
    });

    // Nav + footer filter links
    document.querySelectorAll("[data-filter]").forEach((link) => {
      if (link.classList.contains("filter-btn")) return; // already bound above
      link.addEventListener("click", (e) => {
        e.preventDefault();
        filterProducts(link.dataset.filter);
      });
    });

    // Delegated product grid actions
    document.addEventListener("click", (event) => {
      const trigger = event.target.closest("[data-action]");
      if (trigger) {
        const action = trigger.dataset.action;
        const id = Number(trigger.dataset.id);

        if (action === "quick-view" || action === "open-modal") {
          event.preventDefault();
          event.stopPropagation();
          openModal(id);
        }
        if (action === "add-to-cart") {
          event.preventDefault();
          event.stopPropagation();
          quickAddToCart(id);
        }
      }

      // Delegated cart item qty / remove buttons
      const qtyBtn = event.target.closest("[data-qty-action]");
      if (qtyBtn) {
        const key = qtyBtn.dataset.key;
        const delta = qtyBtn.dataset.qtyAction === "inc" ? 1 : -1;
        changeCartQty(key, delta);
      }

      const removeBtn = event.target.closest("[data-remove-key]");
      if (removeBtn) {
        removeFromCart(removeBtn.dataset.removeKey);
      }
    });

    // Modal controls
    $("modalClose")?.addEventListener("click", closeModal);
    $("productModalOverlay")?.addEventListener("click", closeModal);
    $("qtyMinus")?.addEventListener("click", () => {
      if (modalQty > 1) {
        modalQty--;
        $("qtyVal").textContent = modalQty;
      }
    });
    $("qtyPlus")?.addEventListener("click", () => {
      modalQty = Math.min(modalQty + 1, 99);
      $("qtyVal").textContent = modalQty;
    });
    $("modalAddToCart")?.addEventListener("click", () => {
      if (!modalProduct || !modalProduct.inStock) return;
      addToCart(modalProduct, modalQty, selectedLength, selectedTexture);
      closeModal();
      openCart();
    });

    // Cart drawer controls
    $("cartNavBtn")?.addEventListener("click", openCart);
    $("cartCloseBtn")?.addEventListener("click", closeCart);
    $("cartOverlay")?.addEventListener("click", closeCart);
    $("continueShoppingBtn")?.addEventListener("click", closeCart);
    $("checkoutToggleBtn")?.addEventListener("click", () => {
      if (cart.length === 0) return;
      toggleCheckoutPanel();
    });
    $("submitCheckoutBtn")?.addEventListener("click", submitCheckout);

    // Escape key closes modal/cart
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape") {
        closeModal();
        closeCart();
      }
    });
  });

  // =====================================================
  // CURSOR
  // =====================================================
  const cursor = $("cursor");
  const cursorRing = $("cursorRing");
  let mX = 0,
    mY = 0,
    rX = 0,
    rY = 0;
  if (cursor && cursorRing) {
    document.addEventListener("mousemove", (e) => {
      mX = e.clientX;
      mY = e.clientY;
      cursor.style.left = mX + "px";
      cursor.style.top = mY + "px";
    });
    (function animRing() {
      rX += (mX - rX) * 0.12;
      rY += (mY - rY) * 0.12;
      cursorRing.style.left = rX + "px";
      cursorRing.style.top = rY + "px";
      requestAnimationFrame(animRing);
    })();
    document.body.addEventListener(
      "mouseenter",
      (e) => {
        if (e.target.closest("a, button")) {
          cursor.classList.add("hovered");
          cursorRing.classList.add("hovered");
        }
      },
      true,
    );
    document.body.addEventListener(
      "mouseleave",
      (e) => {
        if (e.target.closest("a, button")) {
          cursor.classList.remove("hovered");
          cursorRing.classList.remove("hovered");
        }
      },
      true,
    );
  }

  // =====================================================
  // PAGE LOADER
  // =====================================================
  window.addEventListener("load", () => {
    setTimeout(() => {
      $("page-loader")?.classList.add("loaded");
      document.body.style.overflow = "";
    }, 1500);
  });
  document.body.style.overflow = "hidden";

  // =====================================================
  // NAVBAR SCROLL
  // =====================================================
  window.addEventListener("scroll", () => {
    $("mainNav")?.classList.toggle("scrolled", window.scrollY > 50);
  });

  // =====================================================
  // SCROLL REVEAL
  // =====================================================
  const ro = new IntersectionObserver(
    (entries) => {
      entries.forEach((e) => {
        if (e.isIntersecting) e.target.classList.add("visible");
      });
    },
    { threshold: 0.1, rootMargin: "0px 0px -40px 0px" },
  );
  document
    .querySelectorAll(".reveal, .reveal-left, .reveal-right")
    .forEach((el) => ro.observe(el));

  document.querySelectorAll(".reveal-hero").forEach((el, i) => {
    el.style.animationDelay = 0.2 + i * 0.15 + "s";
    el.classList.add("animating");
  });
})();

/* ══════════════════════════════════════════
   CODE PROTECTION
══════════════════════════════════════════ */
(function protect() {
  // 1. Disable right-click context menu
  document.addEventListener("contextmenu", (e) => e.preventDefault());

  // 2. Disable F12, Ctrl+Shift+I/J/C/U, Ctrl+U, Ctrl+S, Ctrl+A
  document.addEventListener(
    "keydown",
    function (e) {
      const key = e.key;
      const ctrl = e.ctrlKey || e.metaKey;
      const shift = e.shiftKey;

      // F12
      if (key === "F12") {
        e.preventDefault();
        e.stopPropagation();
        return false;
      }
      // Ctrl+Shift+I (DevTools)
      if (ctrl && shift && (key === "I" || key === "i")) {
        e.preventDefault();
        return false;
      }
      // Ctrl+Shift+J (Console)
      if (ctrl && shift && (key === "J" || key === "j")) {
        e.preventDefault();
        return false;
      }
      // Ctrl+Shift+C (Inspector)
      if (ctrl && shift && (key === "C" || key === "c")) {
        e.preventDefault();
        return false;
      }
      // Ctrl+U (View source)
      if (ctrl && (key === "U" || key === "u")) {
        e.preventDefault();
        return false;
      }
      // Ctrl+S (Save page)
      if (ctrl && (key === "S" || key === "s")) {
        e.preventDefault();
        return false;
      }
      // Ctrl+A (Select all)
      if (ctrl && (key === "A" || key === "a")) {
        e.preventDefault();
        return false;
      }
      // Ctrl+C (Copy) - optional, uncomment to also block copying
      // if (ctrl && (key === "C" || key === "c")) { e.preventDefault(); return false; }
      // Ctrl+P (Print)
      if (ctrl && (key === "P" || key === "p")) {
        e.preventDefault();
        return false;
      }
    },
    true,
  );

  // 3. Disable text selection
  document.addEventListener("selectstart", (e) => e.preventDefault());

  // 4. Disable drag
  document.addEventListener("dragstart", (e) => e.preventDefault());

  // 5. Disable copy/cut
  document.addEventListener("copy", (e) => e.preventDefault());
  document.addEventListener("cut", (e) => e.preventDefault());

  // 6. DevTools open detection - freezes the page or redirects
  (function devToolsDetect() {
    const threshold = 160;
    let devOpen = false;

    function check() {
      const widthDiff = window.outerWidth - window.innerWidth;
      const heightDiff = window.outerHeight - window.innerHeight;
      if (widthDiff > threshold || heightDiff > threshold) {
        if (!devOpen) {
          devOpen = true;
          // Blur the page content
          document.body.style.filter = "blur(12px)";
          document.body.style.pointerEvents = "none";
          // Show warning overlay
          showDevWarning();
        }
      } else {
        if (devOpen) {
          devOpen = false;
          document.body.style.filter = "";
          document.body.style.pointerEvents = "";
          hideDevWarning();
        }
      }
    }

    setInterval(check, 800);
  })();

  // 7. Debugger trap - slows down anyone who opens console
  (function debugTrap() {
    function trap() {
      try {
        (function () {}).constructor("debugger")();
      } catch (e) {}
    }
    setInterval(trap, 3000);
  })();

  // 8. Disable print screen / Ctrl+P via CSS
  const noPrint = document.createElement("style");
  noPrint.textContent = `
    @media print { body { display: none !important; } }
    * { -webkit-user-select: none !important; -moz-user-select: none !important; user-select: none !important; }
    img { pointer-events: none !important; -webkit-user-drag: none !important; }
  `;
  document.head.appendChild(noPrint);

  // 9. Dev tools warning overlay
  function showDevWarning() {
    if (document.getElementById("devWarn")) return;
    const el = document.createElement("div");
    el.id = "devWarn";
    el.style.cssText = `
      position:fixed;inset:0;z-index:99999;
      background:rgba(10,5,20,0.97);
      display:flex;flex-direction:column;align-items:center;justify-content:center;
      font-family:'Inter',sans-serif;text-align:center;padding:40px;
    `;
    el.innerHTML = `
      <div style="font-size:3rem;margin-bottom:16px">🚫</div>
      <div style="font-size:1.4rem;font-weight:600;color:#ff6fd8;margin-bottom:10px">Access Restricted</div>
      <div style="font-size:.95rem;color:#b8b0d8;line-height:1.8;max-width:320px">
        Developer tools are not allowed on this page.<br/>
        Please close DevTools to continue.
      </div>
    `;
    document.body.appendChild(el);
  }

  function hideDevWarning() {
    const el = document.getElementById("devWarn");
    if (el) el.remove();
  }
})();
