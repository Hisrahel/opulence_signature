(function () {
  "use strict";

  // ── Constants ──────────────────────────────────────────────────────────────
  const CART_KEY = "opulence_tools_cart";
  const WA_NUMBER = "2348104240201";
  const $ = (id) => document.getElementById(id);

  const CATEGORY_LABELS = {
    styling: "Styling Tools",
    dryers: "Dryers",
    equipment: "Salon Equipment",
    accessories: "Accessories",
  };

  // ── State ──────────────────────────────────────────────────────────────────
  let PRODUCTS = [];
  let cart = loadCart();
  let activeFilter = "all";
  let modalProduct = null;
  let modalQty = 1;
  let selectedColor = "";
  let isSubmitting = false;

  // ── Cart persistence ───────────────────────────────────────────────────────
  function loadCart() {
    try {
      const raw = localStorage.getItem(CART_KEY);
      const parsed = raw ? JSON.parse(raw) : [];
      return Array.isArray(parsed) ? parsed.filter(isValidItem) : [];
    } catch {
      return [];
    }
  }
  function isValidItem(i) {
    return (
      i &&
      typeof i === "object" &&
      Number.isFinite(+i.id) &&
      Number.isFinite(+i.price) &&
      Number.isFinite(+i.qty) &&
      +i.qty > 0
    );
  }
  function saveCart() {
    try {
      localStorage.setItem(CART_KEY, JSON.stringify(cart));
    } catch {}
  }

  // ── Helpers ────────────────────────────────────────────────────────────────
  const fmt = (n) => "₦" + (Number(n) || 0).toLocaleString("en-NG");
  function escHtml(str) {
    const d = document.createElement("div");
    d.textContent = String(str ?? "");
    return d.innerHTML;
  }
  function encKey(str) {
    return encodeURIComponent(String(str ?? ""));
  }
  function decKey(str) {
    return decodeURIComponent(String(str ?? ""));
  }

  // ── Product load ───────────────────────────────────────────────────────────
  function normalizeProduct(item) {
    const specs = Array.isArray(item.specs)
      ? item.specs
      : typeof item.specs === "string"
        ? item.specs
            .split(",")
            .map((v) => v.trim())
            .filter(Boolean)
        : [];
    const colors = Array.isArray(item.colors)
      ? item.colors
      : typeof item.colors === "string"
        ? item.colors
            .split(",")
            .map((v) => v.trim())
            .filter(Boolean)
        : [];
    const inStock =
      item.in_stock !== undefined
        ? ["yes", "1", "true"].includes(String(item.in_stock).toLowerCase())
        : Boolean(item.inStock);
    const raw = item.image || "";
    const image = raw
      ? raw.startsWith("http")
        ? raw
        : raw.startsWith("uploads/")
          ? `../${raw}`
          : raw.startsWith("../") || raw.startsWith("./")
            ? raw
            : `../uploads/${raw}`
      : "../images/default-product.png";
    return {
      id: Number(item.id),
      name: item.name || "Untitled Tool",
      category: item.category || "equipment",
      price: Number(item.price || 0),
      image,
      badge: item.badge || null,
      inStock,
      desc:
        item.description || item.desc || "Premium salon tool from Opulence.",
      specs,
      colors: colors.length ? colors : ["Default"],
    };
  }

  async function loadProducts() {
    const countEl = $("productCount");
    if (countEl) countEl.textContent = "Loading...";
    try {
      const res = await fetch("../api/get-tools.php", { cache: "no-store" });
      if (!res.ok) throw new Error("HTTP " + res.status);
      const data = await res.json();
      PRODUCTS = Array.isArray(data) ? data.map(normalizeProduct) : [];
    } catch (err) {
      console.error("Failed to load tools", err);
      PRODUCTS = [];
      if (countEl) countEl.textContent = "Could not load products";
    }
    renderProducts(activeFilter, $("sortSelect")?.value || "default");
    updateCartBadge();
  }

  // ── Render products ────────────────────────────────────────────────────────
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
    if (!grid) return;

    if (count)
      count.textContent = `${list.length} product${list.length !== 1 ? "s" : ""}`;
    if (empty) empty.style.display = list.length === 0 ? "flex" : "none";

    grid.innerHTML = list
      .map(
        (p) => `
      <div class="product-card" data-id="${p.id}">
        <div class="product-card-img" data-action="open-modal" data-id="${p.id}">
          <img src="${escHtml(p.image)}" alt="${escHtml(p.name)}" loading="lazy" />
          <div class="product-card-overlay"></div>
          ${p.badge ? `<div class="product-badge">${escHtml(p.badge)}</div>` : ""}
          ${!p.inStock ? `<div class="stock-badge out">Out of Stock</div>` : ""}
          <button class="product-quick-view" type="button" data-action="quick-view" data-id="${p.id}">Quick View</button>
        </div>
        <div class="product-card-body">
          <span class="product-category-tag">${escHtml(CATEGORY_LABELS[p.category] || p.category)}</span>
          <h4 class="product-name">${escHtml(p.name)}</h4>
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

  // ── Filter ─────────────────────────────────────────────────────────────────
  function filterProducts(filter) {
    activeFilter = filter;
    document
      .querySelectorAll(".filter-btn")
      .forEach((b) =>
        b.classList.toggle("active", b.dataset.filter === filter),
      );
    renderProducts(filter, $("sortSelect")?.value || "default");
    $("products")?.scrollIntoView({ behavior: "smooth", block: "start" });
  }

  // ── Modal ──────────────────────────────────────────────────────────────────
  function openModal(id) {
    modalProduct = PRODUCTS.find((p) => p.id === id);
    if (!modalProduct) return;
    modalQty = 1;
    selectedColor = modalProduct.colors[0] || "Default";

    $("modalImg").src = modalProduct.image;
    $("modalImg").alt = modalProduct.name;
    $("modalTitle").textContent = modalProduct.name;
    $("modalPrice").textContent = fmt(modalProduct.price);
    $("modalDesc").textContent = modalProduct.desc;
    $("modalCategory").textContent = (
      CATEGORY_LABELS[modalProduct.category] || modalProduct.category
    ).toUpperCase();
    $("qtyVal").textContent = 1;

    const badge = $("modalBadge");
    if (!modalProduct.inStock) {
      badge.textContent = "Out of Stock";
      badge.style.display = "block";
    } else if (modalProduct.badge) {
      badge.textContent = modalProduct.badge;
      badge.style.display = "block";
    } else badge.style.display = "none";

    $("modalSpecs").innerHTML = modalProduct.specs
      .map((s) => `<li><i class="fa-solid fa-check"></i> ${escHtml(s)}</li>`)
      .join("");

    $("modalColors").innerHTML = modalProduct.colors
      .map(
        (c, i) =>
          `<button type="button" class="option-pill${i === 0 ? " active" : ""}" data-val="${escHtml(c)}">${escHtml(c)}</button>`,
      )
      .join("");

    document.querySelectorAll("#modalColors .option-pill").forEach((pill) => {
      pill.addEventListener("click", function () {
        document
          .querySelectorAll("#modalColors .option-pill")
          .forEach((p) => p.classList.remove("active"));
        this.classList.add("active");
        selectedColor = this.dataset.val;
      });
    });

    const addBtn = $("modalAddToCart");
    addBtn.disabled = !modalProduct.inStock;
    addBtn.querySelector("span").textContent = modalProduct.inStock
      ? "Add to Cart"
      : "Out of Stock";

    $("productModal").classList.add("open");
    $("productModalOverlay").classList.add("open");
    document.body.style.overflow = "hidden";
  }

  function closeModal() {
    $("productModal").classList.remove("open");
    $("productModalOverlay").classList.remove("open");
    document.body.style.overflow = "";
  }

  // ── Cart logic ─────────────────────────────────────────────────────────────
  function addToCart(product, qty, color) {
    if (!product?.inStock) return;
    qty = Math.max(1, Math.min(99, Math.floor(+qty || 1)));
    const key = `${product.id}-${color}`;
    const existing = cart.find((i) => i.key === key);
    if (existing) existing.qty = Math.min(existing.qty + qty, 99);
    else
      cart.push({
        key,
        id: product.id,
        name: product.name,
        price: +product.price || 0,
        image: product.image,
        color,
        qty,
      });
    saveCart();
    updateCartUI();
    flashCartBtn();
    toast(`${product.name} added to cart`);
  }

  function quickAddToCart(id) {
    const p = PRODUCTS.find((i) => i.id === id);
    if (!p) return;
    if (!p.inStock) {
      toast("This item is out of stock", true);
      return;
    }
    addToCart(p, 1, p.colors[0] || "Default");
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
    item.qty = Math.min(item.qty, 99);
    saveCart();
    updateCartUI();
    renderCartItems();
  }

  const cartSubtotal = () =>
    cart.reduce((s, i) => s + (+i.price || 0) * (+i.qty || 0), 0);
  const cartItemCount = () => cart.reduce((s, i) => s + (+i.qty || 0), 0);

  // ── Cart UI ────────────────────────────────────────────────────────────────
  function updateCartBadge() {
    const total = cartItemCount();
    const el = $("cartCount");
    if (el) {
      el.textContent = total;
      el.style.display = total > 0 ? "flex" : "none";
    }
    const hd = $("cartHeadCount");
    if (hd) hd.textContent = total;
  }

  function updateCartUI() {
    updateCartBadge();
    const subtotal = cartSubtotal();
    const set = (id, v) => {
      const el = $(id);
      if (el) el.textContent = v;
    };
    set("cartItemCountSummary", cartItemCount());
    set("cartSubtotalSummary", fmt(subtotal));
    set("cartDeliverySummary", "Free");
    set("cartTotalSummary", fmt(subtotal));

    const note = $("freeDeliveryNote");
    if (note) {
      note.textContent = "Shipping calculated at checkout";
      note.classList.remove("cart-note-highlight");
    }

    const btn = $("checkoutToggleBtn");
    if (btn) btn.disabled = cart.length === 0;
  }

  function flashCartBtn() {
    const btn = $("cartNavBtn");
    btn?.classList.add("flash");
    setTimeout(() => btn?.classList.remove("flash"), 600);
  }

  // ── Render cart items ──────────────────────────────────────────────────────
  function renderCartItems() {
    const itemsEl = $("cartItems");
    const footerEl = $("cartFooter");
    const emptyEl = $("cartEmpty");
    if (!itemsEl || !footerEl || !emptyEl) return;

    if (cart.length === 0) {
      emptyEl.style.display = "flex";
      footerEl.style.display = "none";
      itemsEl.querySelectorAll(".cart-item").forEach((el) => el.remove());
      toggleCheckoutPanel(false);
      return;
    }

    emptyEl.style.display = "none";
    footerEl.style.display = "block";
    itemsEl.querySelectorAll(".cart-item").forEach((el) => el.remove());

    const frag = document.createDocumentFragment();
    cart.forEach((item) => {
      const div = document.createElement("div");
      div.className = "cart-item";
      div.innerHTML = `
        <img src="${escHtml(item.image)}" alt="${escHtml(item.name)}" class="cart-item-img" />
        <div class="cart-item-details">
          <h5>${escHtml(item.name)}</h5>
          <span>${escHtml(item.color)}</span>
          <div class="cart-item-price-row">
            <span class="cart-item-price">${fmt((+item.price || 0) * (+item.qty || 0))}</span>
            <div class="cart-item-qty">
              <button type="button" data-qty-action="dec" data-key="${encKey(item.key)}" aria-label="Decrease">−</button>
              <span>${item.qty}</span>
              <button type="button" data-qty-action="inc" data-key="${encKey(item.key)}" aria-label="Increase">+</button>
            </div>
          </div>
        </div>
        <button class="cart-item-remove" data-remove-key="${encKey(item.key)}" type="button" aria-label="Remove item">
          <i class="fa-solid fa-xmark"></i>
        </button>
      `;
      frag.appendChild(div);
    });
    itemsEl.appendChild(frag);
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
    clearStatus();
  }

  // ── Toast ──────────────────────────────────────────────────────────────────
  let toastTimer;
  function toast(msg, isErr) {
    const el = $("cartToast");
    if (!el) return;
    el.textContent = msg;
    el.className = "cart-toast show" + (isErr ? " error" : "");
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => {
      el.className = "cart-toast";
    }, 2600);
  }

  // ── Checkout ───────────────────────────────────────────────────────────────
  function toggleCheckoutPanel(force) {
    const panel = $("checkoutPanel");
    const btn = $("checkoutToggleBtn");
    if (!panel || !btn) return;
    const show =
      typeof force === "boolean" ? force : panel.style.display === "none";
    panel.style.display = show ? "block" : "none";
    btn.classList.toggle("active", show);
    btn.innerHTML = show
      ? '<span>Hide Checkout</span><i class="fa-solid fa-chevron-up"></i>'
      : '<span>Checkout</span><i class="fa-solid fa-arrow-right"></i>';
  }

  function clearStatus() {
    const el = $("checkoutStatus");
    if (el) {
      el.textContent = "";
      el.className = "checkout-status";
    }
  }

  function setStatus(msg, type) {
    const el = $("checkoutStatus");
    if (!el) return;
    el.textContent = msg;
    el.className = "checkout-status " + (type || "");
  }

  async function submitCheckout() {
    if (isSubmitting) return;
    if (!cart.length) {
      setStatus("Your cart is empty.", "error");
      return;
    }

    const name = $("checkoutName").value.trim();
    const phone = $("checkoutPhone").value.trim();
    const email = $("checkoutEmail").value.trim();
    const address = $("checkoutAddress").value.trim();
    const notes = $("checkoutNotes").value.trim();

    if (!name || !phone || !address) {
      setStatus(
        "Please fill in your name, phone and delivery address.",
        "error",
      );
      return;
    }
    if (phone.replace(/\D/g, "").length < 10) {
      setStatus("Please enter a valid phone number.", "error");
      return;
    }

    const submitBtn = $("submitCheckoutBtn");
    isSubmitting = true;
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.innerHTML =
        '<span><i class="fa-solid fa-spinner fa-spin"></i> Placing order…</span>';
    }
    clearStatus();

    try {
      const res = await fetch("../api/place-order.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          type: "tools",
          name,
          phone,
          email,
          address,
          notes,
          items: cart.map((i) => ({ id: i.id, qty: i.qty, color: i.color })),
        }),
      });
      const result = await res.json().catch(() => {
        throw new Error("Unexpected server response");
      });

      if (!result.success) {
        setStatus(
          result.message || "Something went wrong. Please try again.",
          "error",
        );
        return;
      }

      const itemsText = cart
        .map((i) => `• ${i.name} (${i.color}) x${i.qty}`)
        .join("\n");
      const waMsg =
        `Hello Opulence Tools, I just placed an order.\n\n` +
        `Order Ref: ${result.order_ref}\n` +
        `Customer: ${name}\nPhone: ${phone}\nEmail: ${email || "N/A"}\nAddress: ${address}\nNotes: ${notes || "None"}\n\n` +
        `Items:\n${itemsText}\n\nSubtotal: ${fmt(result.subtotal)}\nDelivery: Free\nTotal: ${fmt(result.total)}`;

      setStatus(
        `Order placed! Ref: ${result.order_ref}. Opening WhatsApp to confirm…`,
        "success",
      );
      cart = [];
      saveCart();
      updateCartUI();
      renderCartItems();
      [
        "checkoutName",
        "checkoutPhone",
        "checkoutEmail",
        "checkoutAddress",
        "checkoutNotes",
      ].forEach((id) => {
        const el = $(id);
        if (el) el.value = "";
      });

      setTimeout(() => {
        window.open(
          `https://wa.me/${WA_NUMBER}?text=${encodeURIComponent(waMsg)}`,
          "_blank",
          "noopener,noreferrer",
        );
        closeCart();
      }, 1200);
    } catch (err) {
      console.error(err);
      setStatus("Network error. Check your connection and try again.", "error");
    } finally {
      isSubmitting = false;
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.innerHTML = "<span>Place Order</span>";
      }
    }
  }

  // ── Event wiring ───────────────────────────────────────────────────────────
  document.addEventListener("DOMContentLoaded", () => {
    updateCartUI();
    renderProducts(activeFilter, "default");
    loadProducts();

    document
      .querySelectorAll(".filter-btn")
      .forEach((btn) =>
        btn.addEventListener("click", () => filterProducts(btn.dataset.filter)),
      );
    $("sortSelect")?.addEventListener("change", (e) =>
      renderProducts(activeFilter, e.target.value),
    );

    document.querySelectorAll("[data-filter]").forEach((link) => {
      if (link.classList.contains("filter-btn")) return;
      link.addEventListener("click", (e) => {
        e.preventDefault();
        filterProducts(link.dataset.filter);
      });
    });

    document
      .querySelectorAll("[data-cat-filter]")
      .forEach((card) =>
        card.addEventListener("click", () =>
          filterProducts(card.dataset.catFilter),
        ),
      );

    document.addEventListener("click", (e) => {
      const action = e.target.closest("[data-action]");
      if (action) {
        const act = action.dataset.action;
        const id = Number(action.dataset.id);
        if (act === "open-modal" || act === "quick-view") {
          e.preventDefault();
          e.stopPropagation();
          openModal(id);
        }
        if (act === "add-to-cart") {
          e.preventDefault();
          e.stopPropagation();
          quickAddToCart(id);
        }
      }

      const qtyBtn = e.target.closest("[data-qty-action]");
      if (qtyBtn)
        changeCartQty(
          decKey(qtyBtn.dataset.key),
          qtyBtn.dataset.qtyAction === "inc" ? 1 : -1,
        );

      const removeBtn = e.target.closest("[data-remove-key]");
      if (removeBtn) removeFromCart(decKey(removeBtn.dataset.removeKey));
    });

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
      if (!modalProduct?.inStock) return;
      addToCart(modalProduct, modalQty, selectedColor);
      closeModal();
      openCart();
    });

    $("cartNavBtn")?.addEventListener("click", openCart);
    $("cartCloseBtn")?.addEventListener("click", closeCart);
    $("cartOverlay")?.addEventListener("click", closeCart);
    $("continueShoppingBtn")?.addEventListener("click", closeCart);
    $("checkoutToggleBtn")?.addEventListener("click", () => {
      if (cart.length) toggleCheckoutPanel();
    });
    $("submitCheckoutBtn")?.addEventListener("click", submitCheckout);

    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape") {
        closeModal();
        closeCart();
      }
    });
  });

  window.addEventListener("load", () => {
    setTimeout(() => {
      $("page-loader")?.classList.add("loaded");
      document.body.style.overflow = "";
    }, 1500);
  });
  document.body.style.overflow = "hidden";

  window.addEventListener("scroll", () =>
    $("mainNav")?.classList.toggle("scrolled", window.scrollY > 50),
  );

  const ro = new IntersectionObserver(
    (entries) =>
      entries.forEach((e) => {
        if (e.isIntersecting) e.target.classList.add("visible");
      }),
    { threshold: 0.1, rootMargin: "0px 0px -40px 0px" },
  );
  document
    .querySelectorAll(".reveal, .reveal-left, .reveal-right")
    .forEach((el) => ro.observe(el));
  document.querySelectorAll(".reveal-hero").forEach((el, i) => {
    el.style.animationDelay = 0.2 + i * 0.15 + "s";
    el.classList.add("animating");
  });

  const cursor = $("cursor"),
    ring = $("cursorRing");
  let mX = 0,
    mY = 0,
    rX = 0,
    rY = 0;
  if (cursor && ring) {
    document.addEventListener("mousemove", (e) => {
      mX = e.clientX;
      mY = e.clientY;
      cursor.style.left = mX + "px";
      cursor.style.top = mY + "px";
    });
    (function animRing() {
      rX += (mX - rX) * 0.12;
      rY += (mY - rY) * 0.12;
      ring.style.left = rX + "px";
      ring.style.top = rY + "px";
      requestAnimationFrame(animRing);
    })();
    document.body.addEventListener(
      "mouseenter",
      (e) => {
        if (e.target.closest("a,button")) {
          cursor.classList.add("hovered");
          ring.classList.add("hovered");
        }
      },
      true,
    );
    document.body.addEventListener(
      "mouseleave",
      (e) => {
        if (e.target.closest("a,button")) {
          cursor.classList.remove("hovered");
          ring.classList.remove("hovered");
        }
      },
      true,
    );
  }
})();
