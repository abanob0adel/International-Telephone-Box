document.addEventListener("DOMContentLoaded", function () {
  const phoneInputs = document.querySelectorAll('input[type="tel"]');

  phoneInputs.forEach(function (phoneInput) {
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
          .then((data) => callback(data.country_code))
          .catch(() => callback("eg")); // Fallback to Egypt if geolocation fails
      },
    });

    // Handle validation
    phoneInput.addEventListener("blur", function () {
      if (!iti.isValidNumber()) {
        phoneInput.classList.add("error");
      } else {
        phoneInput.classList.remove("error");
        phoneInput.value = iti.getNumber();
      }
    });

    // Form submission handling
    const form = phoneInput.closest("form");
    if (form) {
      form.addEventListener("submit", function (e) {
        if (!iti.isValidNumber()) {
          e.preventDefault();
          phoneInput.classList.add("error");
        }
      });
    }
  });
});
