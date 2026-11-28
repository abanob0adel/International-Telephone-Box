function isVisible(el) {
  if (!el) return false;
  const r = el.getClientRects();
  if (!r || r.length === 0) return false;
  const s = window.getComputedStyle(el);
  return s.display !== "none" && s.visibility !== "hidden" && s.opacity !== "0";
}

function initializeInternationalTelephoneBox(context = document) {
  // Site settings, injected as JSON by wp_add_inline_script. Defaults match 1.2.0.
  const config =
    typeof window.inteleboxConfig === "object" && window.inteleboxConfig
      ? window.inteleboxConfig
      : {};

  const BLOCKED_DIALS = ["+972"];

  const FALLBACK_COUNTRY =
    typeof config.fallbackCountry === "string" && config.fallbackCountry
      ? config.fallbackCountry.toLowerCase()
      : "eg";

  // Only used in "include" mode; empty means every country is offered.
  const ONLY_COUNTRIES = (Array.isArray(config.onlyCountries)
    ? config.onlyCountries
    : []
  )
    .filter((c) => typeof c === "string")
    .map((c) => c.toLowerCase());

  // "il" is always excluded; site settings can exclude more.
  const EXCLUDED_ISO = ["il"]
    .concat(
      Array.isArray(config.excludeCountries)
        ? config.excludeCountries
            .filter((c) => typeof c === "string")
            .map((c) => c.toLowerCase())
        : []
    )
    .filter((c, i, arr) => arr.indexOf(c) === i);

  // Preferred countries shown at the top, in order. Default: fallback first.
  const COUNTRY_ORDER = (Array.isArray(config.countryOrder)
    ? config.countryOrder
        .filter((c) => typeof c === "string")
        .map((c) => c.toLowerCase())
    : [FALLBACK_COUNTRY]
  ).filter((c, i, arr) => arr.indexOf(c) === i);

  const INITIAL_COUNTRY =
    typeof config.initialCountry === "string" && config.initialCountry
      ? config.initialCountry.toLowerCase()
      : "auto";

  // `geoLookup` is a bool from PHP; accept the usual serialized forms too.
  const GEO_LOOKUP = (function () {
    const v = config.geoLookup;
    if (v === undefined || v === null || v === "") return true;
    if (typeof v === "boolean") return v;
    if (typeof v === "number") return v !== 0;
    const s = String(v).toLowerCase();
    if (s === "false" || s === "0" || s === "no" || s === "off") return false;
    if (s === "true" || s === "1" || s === "yes" || s === "on") return true;
    return true;
  })();

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

    // The ipapi.co lookup's result falls back to the fallback country when it
    // is empty, excluded, or (in include mode) not in onlyCountries.
    function resolveAutoCountry(code) {
      code = String(code || "").toLowerCase();
      if (!code || EXCLUDED_ISO.includes(code)) return FALLBACK_COUNTRY;
      if (ONLY_COUNTRIES.length && !ONLY_COUNTRIES.includes(code)) {
        return FALLBACK_COUNTRY;
      }
      return code;
    }

    // intl-tel-input v29 option names. The v17 names used up to 1.1.5 are
    // noted next to each one.
    const itiOptions = {
      separateDialCode: true,
      // v17: preferredCountries.
      countryOrder: COUNTRY_ORDER,
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
    };

    // Only restrict the offered countries when a list is given.
    if (ONLY_COUNTRIES.length) {
      itiOptions.onlyCountries = ONLY_COUNTRIES;
    }

    if (INITIAL_COUNTRY !== "auto") {
      // A fixed starting country; no IP lookup.
      itiOptions.initialCountry = INITIAL_COUNTRY;
    } else if (GEO_LOOKUP) {
      // v17: initialCountry "auto" + geoIpLookup(callback).
      // v29: initialCountryLookup.
      itiOptions.initialCountryLookup = function () {
        return fetch("https://ipapi.co/json")
          .then((res) => res.json())
          .then((data) => resolveAutoCountry(data && data.country_code))
          .catch(() => FALLBACK_COUNTRY);
      };
    } else {
      // Detection off: start on the fallback country; no request is made.
      itiOptions.initialCountry = FALLBACK_COUNTRY;
    }

    const iti = window.intlTelInput(phoneInput, itiOptions);

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
