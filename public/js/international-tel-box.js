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

    const iti = window.intlTelInput(phoneInput, {
      initialCountry: "auto",
      separateDialCode: true,
      utilsScript:
        "https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.19/js/utils.js",
      preferredCountries: ["eg"],
      excludeCountries: EXCLUDED_ISO,
      formatOnDisplay: true,
      autoPlaceholder: "aggressive",
      dropdownContainer: document.body,
      allowDropdown: true,
      showFlags: true,
      nationalMode: false,
      hiddenInput: "full_phone",
      placeholderNumberType: "MOBILE",
      geoIpLookup: function (callback) {
        fetch("https://ipapi.co/json")
          .then((res) => res.json())
          .then((data) => {
            let code = (data.country_code || "").toLowerCase();
            if (EXCLUDED_ISO.includes(code)) code = "eg";
            callback(code);
          })
          .catch(() => callback("eg"));
      },
    });

    function isBlockedNumber(num) {
      return BLOCKED_DIALS.some((d) => num.startsWith(d));
    }

    phoneInput.addEventListener("blur", function () {
      const full = iti.getNumber();
      if (!iti.isValidNumber() || isBlockedNumber(full)) {
        phoneInput.classList.add("error");
      } else {
        phoneInput.classList.remove("error");
        phoneInput.value = full;
      }
    });

    const form = phoneInput.closest("form");
    if (form) {
      form.addEventListener("submit", function (e) {
        const full = iti.getNumber();
        if (!iti.isValidNumber() || isBlockedNumber(full)) {
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

document.addEventListener("DOMContentLoaded", function () {
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
});

jQuery(document).on("elementor/popup/show", function () {
  setTimeout(() => {
    initInVisiblePopups();
    initializeInsideIframes();
  }, 400);
});
