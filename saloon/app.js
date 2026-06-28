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

// ============================================================
// STATE
// ============================================================
let selectedService = "";
let selectedLocation = "Lagos";
let selectedTime = "";

// ============================================================
// LOADER
// ============================================================
window.addEventListener("load", () => {
  setTimeout(() => {
    document.getElementById("page-loader").classList.add("loaded");
    document.body.style.overflow = "";
  }, 1500);
});
document.body.style.overflow = "hidden";

// ============================================================
// CURSOR
// ============================================================
const cursor = document.getElementById("cursor");
const cursorRing = document.getElementById("cursorRing");
let mX = 0,
  mY = 0,
  rX = 0,
  rY = 0;
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
document
  .querySelectorAll("a,button,.spick-card,.tslot,.service-card")
  .forEach((el) => {
    el.addEventListener("mouseenter", () => {
      cursor.classList.add("hovered");
      cursorRing.classList.add("hovered");
    });
    el.addEventListener("mouseleave", () => {
      cursor.classList.remove("hovered");
      cursorRing.classList.remove("hovered");
    });
  });

// ============================================================
// NAVBAR SCROLL
// ============================================================
window.addEventListener("scroll", () => {
  document
    .getElementById("mainNav")
    .classList.toggle("scrolled", window.scrollY > 50);
});

// ============================================================
// SCROLL REVEAL
// ============================================================
const ro = new IntersectionObserver(
  (entries) => {
    entries.forEach((e) => {
      if (e.isIntersecting) e.target.classList.add("visible");
    });
  },
  { threshold: 0.1, rootMargin: "0px 0px -40px 0px" },
);
document
  .querySelectorAll(".reveal,.reveal-left,.reveal-right")
  .forEach((el) => ro.observe(el));

document.querySelectorAll(".reveal-hero").forEach((el, i) => {
  el.style.animationDelay = 0.2 + i * 0.15 + "s";
  el.classList.add("animating");
});

// ============================================================
// BOOKING: date minimum = today
// ============================================================
const apptDateEl = document.getElementById("apptDate");
apptDateEl.min = new Date().toISOString().split("T")[0];

// ============================================================
// BOOKING: service picker
// ============================================================
document.querySelectorAll(".spick-card").forEach((c) => {
  c.addEventListener("click", function () {
    document
      .querySelectorAll(".spick-card")
      .forEach((x) => x.classList.remove("selected"));
    this.classList.add("selected");
    selectedService = this.dataset.service;
    document.getElementById("sumService").textContent = selectedService;
    clearFieldError("serviceError");
  });
});

// ============================================================
// BOOKING: location picker
// ============================================================
document.querySelectorAll(".loc-pick").forEach((l) => {
  l.addEventListener("click", function () {
    document
      .querySelectorAll(".loc-pick")
      .forEach((x) => x.classList.remove("active"));
    this.classList.add("active");
    selectedLocation = this.dataset.loc;
    document.getElementById("sumLocation").textContent =
      selectedLocation + " Studio";
  });
});

// ============================================================
// BOOKING: time slot picker
// ============================================================
document.querySelectorAll(".tslot").forEach((s) => {
  s.addEventListener("click", function () {
    document
      .querySelectorAll(".tslot")
      .forEach((x) => x.classList.remove("selected"));
    this.classList.add("selected");
    selectedTime = this.dataset.time;
    document.getElementById("sumTime").textContent = selectedTime;
    clearFieldError("timeError");
  });
});

// ============================================================
// BOOKING: date change -> update summary
// ============================================================
apptDateEl.addEventListener("change", function () {
  if (!this.value) {
    document.getElementById("sumDate").textContent = "-";
    return;
  }
  const d = new Date(this.value + "T00:00:00");
  document.getElementById("sumDate").textContent = d.toLocaleDateString(
    "en-US",
    {
      weekday: "long",
      year: "numeric",
      month: "long",
      day: "numeric",
    },
  );
  clearFieldError("dateError");
});

// ============================================================
// BOOKING: multi-step navigation
// ============================================================
function bNext(step) {
  clearAllFieldErrors();

  if (step === 2 && !selectedService) {
    showFieldError("serviceError", "Please select a service to continue.");
    shakeForm();
    return;
  }

  if (step === 3) {
    let ok = true;
    if (!apptDateEl.value) {
      showFieldError("dateError", "Please choose a date.");
      ok = false;
    }
    if (!selectedTime) {
      showFieldError("timeError", "Please choose a time slot.");
      ok = false;
    }
    if (!ok) {
      shakeForm();
      return;
    }
  }

  showStep(step);
}

function bPrev(step) {
  showStep(step);
}

function showStep(step) {
  document.querySelectorAll(".bform-step").forEach((s) => {
    s.style.display = "none";
  });
  const el = document.getElementById("bform" + step);
  if (el) {
    el.style.display = "block";
    el.style.animation = "stepFadeUp .4s ease both";
  }
  updateStepIndicators(step);
}

function updateStepIndicators(step) {
  for (let i = 1; i <= 3; i++) {
    const ind = document.getElementById("bstep" + i);
    ind.classList.remove("active", "done");
    if (i < step) ind.classList.add("done");
    else if (i === step) ind.classList.add("active");
  }
  document.getElementById("bline1").style.background =
    step > 1 ? "var(--gold)" : "var(--light-gray)";
  document.getElementById("bline2").style.background =
    step > 2 ? "var(--gold)" : "var(--light-gray)";
}

function shakeForm() {
  const f = document.querySelector(".booking-form-wrap");
  f.style.animation = "shake .4s ease";
  setTimeout(() => (f.style.animation = ""), 400);
}

// ============================================================
// FIELD-LEVEL ERROR HELPERS
// ============================================================
function showFieldError(id, msg) {
  const el = document.getElementById(id);
  if (el) el.textContent = msg;
}
function clearFieldError(id) {
  const el = document.getElementById(id);
  if (el) el.textContent = "";
}
function clearAllFieldErrors() {
  [
    "serviceError",
    "dateError",
    "timeError",
    "nameError",
    "emailError",
    "phoneError",
  ].forEach(clearFieldError);
}
function showFormBanner(msg) {
  const banner = document.getElementById("bookingFormError");
  const text = document.getElementById("bookingFormErrorText");
  text.textContent = msg;
  banner.style.display = "flex";
}
function hideFormBanner() {
  document.getElementById("bookingFormError").style.display = "none";
}

// ============================================================
// STEP 3 VALIDATION
// ============================================================
function validateStep3() {
  let valid = true;
  clearAllFieldErrors();

  const name = document.getElementById("clientName");
  const email = document.getElementById("clientEmail");
  const phone = document.getElementById("clientPhone");

  // NAME
  if (!name.value.trim()) {
    showFieldError("nameError", "Please enter your full name.");
    name.classList.add("is-invalid");
    valid = false;
  } else {
    name.classList.remove("is-invalid");
  }

  // EMAIL
  const emailVal = email.value.trim();
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  if (!emailVal || !emailRegex.test(emailVal)) {
    showFieldError("emailError", "Please enter a valid email address.");
    email.classList.add("is-invalid");
    valid = false;
  } else {
    email.classList.remove("is-invalid");
  }

  // PHONE - digits and + only, 10 to 15 chars
  const phoneValue = phone.value.trim();
  const phoneRegex = /^[0-9+]{10,15}$/;
  if (!phoneRegex.test(phoneValue)) {
    showFieldError(
      "phoneError",
      "Please enter a valid phone number (10-15 digits).",
    );
    phone.classList.add("is-invalid");
    valid = false;
  } else {
    phone.classList.remove("is-invalid");
  }

  return valid;
}

// ============================================================
// SUBMIT BUTTON LOADING STATE
// ============================================================
function setSubmitLoading(isLoading) {
  const btn = document.getElementById("bformSubmitBtn");
  const label = btn.querySelector(".btn-label");
  const loading = btn.querySelector(".btn-loading");
  btn.disabled = isLoading;
  label.style.display = isLoading ? "none" : "inline-flex";
  loading.style.display = isLoading ? "inline-flex" : "none";
}

// ============================================================
// FORM SUBMIT - single source of truth, posts to API
// ============================================================
document
  .getElementById("bookingForm")
  .addEventListener("submit", async function (e) {
    e.preventDefault();
    hideFormBanner();

    // Guard: make sure step 1 & 2 data is actually present
    // (covers the case where someone bypasses the Continue buttons)
    if (!selectedService) {
      showStep(1);
      showFieldError("serviceError", "Please select a service to continue.");
      shakeForm();
      return;
    }
    if (!apptDateEl.value || !selectedTime) {
      showStep(2);
      if (!apptDateEl.value)
        showFieldError("dateError", "Please choose a date.");
      if (!selectedTime)
        showFieldError("timeError", "Please choose a time slot.");
      shakeForm();
      return;
    }

    // Step 3 validation
    if (!validateStep3()) {
      shakeForm();
      return;
    }

    const bookingData = {
      service: selectedService,
      location: selectedLocation,
      appointment_date: apptDateEl.value,
      appointment_time: selectedTime,
      duration: document.getElementById("apptDuration").value,
      fullname: document.getElementById("clientName").value.trim(),
      email: document.getElementById("clientEmail").value.trim(),
      phone: document.getElementById("clientPhone").value.trim(),
      preferred_contact: document.getElementById("clientContact").value,
      notes: document.getElementById("clientNotes").value.trim(),
    };

    setSubmitLoading(true);

    try {
      const response = await fetch("../api/bookings.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(bookingData),
      });

      // Guard against non-JSON responses (e.g. PHP fatal error / 404 / 500 HTML page)
      let result;
      const contentType = response.headers.get("content-type") || "";
      if (contentType.includes("application/json")) {
        result = await response.json();
      } else {
        const text = await response.text();
        console.error("Non-JSON response from server:", text);
        throw new Error(
          "Unexpected server response. Please try again or contact us directly.",
        );
      }

      if (!response.ok || !result.success) {
        showFormBanner(
          result.message ||
            "We couldn't process your booking. Please try again.",
        );
        shakeForm();
        setSubmitLoading(false);
        return;
      }

      // SUCCESS
      document.querySelectorAll(".bform-step").forEach((s) => {
        s.style.display = "none";
      });
      const success = document.getElementById("bformSuccess");
      const refEl = document.getElementById("successRef");
      refEl.textContent = result.booking_ref
        ? `(Ref: ${result.booking_ref})`
        : "";
      success.style.display = "flex";
      success.style.animation = "stepFadeUp .6s ease both";
      document.getElementById("bstep3").classList.add("done");
    } catch (err) {
      console.error("Booking submission error:", err);
      showFormBanner(
        err.message === "Failed to fetch"
          ? "Couldn't reach the server. Please check your connection and try again."
          : err.message || "Something went wrong. Please try again.",
      );
      shakeForm();
    } finally {
      setSubmitLoading(false);
    }
  });

// ============================================================
// RESET FORM (Book Another)
// ============================================================
function resetBookingForm() {
  selectedService = "";
  selectedTime = "";
  selectedLocation = "Lagos";

  document.getElementById("bformSuccess").style.display = "none";
  document
    .querySelectorAll(".spick-card")
    .forEach((c) => c.classList.remove("selected"));
  document
    .querySelectorAll(".tslot")
    .forEach((s) => s.classList.remove("selected"));
  document
    .querySelectorAll(".loc-pick")
    .forEach((l) => l.classList.remove("active"));
  document.querySelector('.loc-pick[data-loc="Lagos"]').classList.add("active");

  document.getElementById("bookingForm").reset();
  apptDateEl.value = "";

  ["sumService", "sumDate", "sumTime"].forEach(
    (id) => (document.getElementById(id).textContent = "-"),
  );
  document.getElementById("sumLocation").textContent = "Lagos Studio";

  clearAllFieldErrors();
  hideFormBanner();

  document
    .querySelectorAll(".bform-input.is-invalid")
    .forEach((el) => el.classList.remove("is-invalid"));

  showStep(1);
}
