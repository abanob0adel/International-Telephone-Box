function isVisible(el) {
  if (!el) return false;
  const r = el.getClientRects();
  if (!r || r.length === 0) return false;
  const s = window.getComputedStyle(el);
  return s.display !== "none" && s.visibility !== "hidden" && s.opacity !== "0";
}

function initializeInternationalTelephoneBox(context = document) {
  const EXCLUDED_ISO = ["il"];
  const BLOCKED_DIALS = ["+972"];
  const FALLBACK_COUNTRY = "eg";

  if (typeof window.intlTelInput !== "function") return;

  const candidates = context.querySelectorAll(
    'input[type="tel"]:not(.iti-initialized)'
  );
  const phoneInputs = Array.from(candidates).filter(isVisible);

  phoneInputs.forEach(function (phoneInput) {
    phoneInput.classList.add("iti-initialized");

    const wrapper = document.createElement("div");
    wrapper.className = "phone-input-wrapper";
    phoneInput.parentNode.insertBefore(wrapper, phoneInput);
    wrapper.appendChild(phoneInput);

    // intl-tel-input v29 option names. The v17 names used up to 1.1.5 are
    // noted next to each one.
    const iti = window.intlTelInput(phoneInput, {
      // v17: initialCountry "auto" + geoIpLookup(callback).
      initialCountryLookup: function () {
        return fetch("https://ipapi.co/json")
          .then((res) => res.json())
          .then((data) => {
            let code = (data.country_code || "").toLowerCase();
            if (!code || EXCLUDED_ISO.includes(code)) code = FALLBACK_COUNTRY;
            return code;
          })
          .catch(() => FALLBACK_COUNTRY);
      },
      separateDialCode: true,
      // v17: preferredCountries.
      countryOrder: [FALLBACK_COUNTRY],
      excludeCountries: EXCLUDED_ISO,
      // v17: formatOnDisplay true + nationalMode false.
      numberDisplayFormat: "INTERNATIONAL",
      // v17: autoPlaceholder "aggressive".
      placeholderNumberPolicy: "AGGRESSIVE",
      // v17: dropdownContainer.
      dropdownParent: phoneInput.ownerDocument.body,
      // v17: allowDropdown true. v17 always used an inline dropdown, never
      // the fullscreen popup that "AUTO" picks on narrow screens.
      countrySelectorMode: "DROPDOWN",
      // v17 sized the list to the longest country name, not to the input.
      matchDropdownWidth: false,
      showFlags: true,
      // v17: hiddenInput "full_phone".
      hiddenInputs: function () {
        return { phone: "full_phone" };
      },
      placeholderNumberType: "MOBILE",
      // v17 accepted any valid number type, extensions included, and did not
      // restrict typing.
      allowedNumberTypes: null,
      allowNumberExtensions: true,
      strictMode: false,
    });

    function isBlockedNumber(num) {
      return BLOCKED_DIALS.some((d) => num.startsWith(d));
    }

    // Returns the full international number, or null when it is invalid.
    // v17's isValidNumber() is called isValidNumberPrecise() since v19.
    function getValidNumber() {
      let valid, full;
      try {
        valid = iti.isValidNumberPrecise();
        full = iti.getNumber();
      } catch (e) {
        // Utils not loaded yet; leave validation to the next blur/submit.
        return undefined;
      }
      return valid && !isBlockedNumber(full) ? full : null;
    }

    phoneInput.addEventListener("blur", function () {
      const full = getValidNumber();
      if (full === undefined) return;
      if (full === null) {
        phoneInput.classList.add("error");
      } else {
        phoneInput.classList.remove("error");
        phoneInput.value = full;
      }
    });

    const form = phoneInput.closest("form");
    if (form) {
      form.addEventListener("submit", function (e) {
        if (getValidNumber() === null) {
          e.preventDefault();
          phoneInput.classList.add("error");
        }
      });
    }
  });
}

function initInVisiblePopups() {
  const containers = document.querySelectorAll(
    ".elementor-popup-modal, .dialog-widget"
  );
  containers.forEach((c) => {
    if (isVisible(c)) initializeInternationalTelephoneBox(c);
  });
}

function initializeInsideIframes() {
  document.querySelectorAll("iframe").forEach((iframe) => {
    try {
      const d = iframe.contentDocument || iframe.contentWindow.document;
      if (!d) return;
      const hasTel = d.querySelector('input[type="tel"]:not(.iti-initialized)');
      if (hasTel) initializeInternationalTelephoneBox(d);
    } catch (e) {}
  });
}

function startInternationalTelephoneBox() {
  initializeInternationalTelephoneBox();
  initializeInsideIframes();

  const observer = new MutationObserver(() => {
    initializeInternationalTelephoneBox();
    initInVisiblePopups();
    initializeInsideIframes();
  });

  observer.observe(document.body, {
    childList: true,
    subtree: true,
    attributes: true,
    attributeFilter: ["style", "class", "aria-hidden"],
  });

  // Elementor fires this as a jQuery event. jQuery is no longer a dependency
  // of this script, so only listen when the site already loads it.
  if (window.jQuery) {
    window.jQuery(document).on("elementor/popup/show", function () {
      setTimeout(() => {
        initInVisiblePopups();
        initializeInsideIframes();
      }, 400);
    });
  }
}

// Also start when the script is loaded after DOMContentLoaded, e.g. by
// script-delaying optimisation plugins.
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", startInternationalTelephoneBox);
} else {
  startInternationalTelephoneBox();
}
