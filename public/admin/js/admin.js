/**
 * International Telephone Box — settings page.
 *
 * Vanilla JS, no jQuery, no build step.
 */
(function () {
  "use strict";

  var data = window.inteleboxAdmin || {};
  var COUNTRIES = data.countries || {};
  var DEFAULTS = data.defaults || {};
  var MAX_PREFERRED = typeof data.maxPreferred === "number" ? data.maxPreferred : 5;

  var STRINGS = {
    removeCountry: "Remove %s",
    noResults: "No countries found",
    selectedCount: "%d selected",
    preferredCount: "%1$d of %2$d selected",
    selectAll: "Select all visible",
    clear: "Clear",
    modeHelpAll: "Visitors can pick any country.",
    modeHelpInclude: "Visitors can only pick these countries.",
    modeHelpExclude: "These countries are removed from the list."
  };
  var S = {};
  for (var k in STRINGS) if (STRINGS.hasOwnProperty(k)) S[k] = STRINGS[k];
  if (data.strings) for (k in data.strings) if (data.strings.hasOwnProperty(k)) S[k] = data.strings[k];

  var COUNTRIES_FIELD = "intelebox_settings[countries][]";
  var PREFERRED_FIELD = "intelebox_settings[preferred_countries][]";
  var MODE_FIELD = "intelebox_settings[country_mode]";
  var INITIAL_FIELD = "intelebox_settings[initial_country]";
  var FALLBACK_FIELD = "intelebox_settings[fallback_country]";
  var GEO_FIELD = "intelebox_settings[geo_lookup]";

  var ALWAYS_EXCLUDED = ["il"];
  var MODES = ["all", "include", "exclude"];

  // ------------------------------------------------------------- utilities

  function el(tag, className, text) {
    var node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined && text !== null) node.textContent = text;
    return node;
  }

  function fmt(str) {
    var vals = Array.prototype.slice.call(arguments, 1);
    return String(str)
      .replace(/%(\d+)\$[ds]/g, function (m, n) { return String(vals[n - 1]); })
      .replace(/%[ds]/g, function () { return String(vals.shift()); });
  }

  function qs(root, selector) { return root.querySelector(selector); }
  function qsa(root, selector) { return Array.prototype.slice.call(root.querySelectorAll(selector)); }

  function nameSel(field) { return '[name="' + field + '"]'; }

  // Localized region names, falling back to the server's English name.
  var displayNames = null;
  try {
    var locale = document.documentElement.lang || "en";
    if (typeof Intl !== "undefined" && typeof Intl.DisplayNames === "function") {
      displayNames = new Intl.DisplayNames([locale], { type: "region" });
    }
  } catch (e) {
    displayNames = null;
  }

  function localName(iso, fallback) {
    try {
      if (displayNames) {
        var n = displayNames.of(String(iso).toUpperCase());
        if (n) return n;
      }
    } catch (e) {}
    return fallback || iso;
  }

  function countryMeta(iso) {
    var c = COUNTRIES[iso] || {};
    return { name: c.name || iso, dial: c.dial || "" };
  }

  // --------------------------------------------------------------- picking

  function rowMatches(row, query) {
    if (!query) return true;
    var iso = row.getAttribute("data-iso") || "";
    var serverName = row.getAttribute("data-name") || "";
    var dial = row.getAttribute("data-dial") || "";
    var name = localName(iso, serverName);
    var hay = (name + " " + serverName + " " + dial + " +" + dial + " " + iso).toLowerCase();
    return hay.indexOf(query) !== -1;
  }

  function buildChip(iso, name, dial, hiddenName) {
    var chip = el("span", "intelebox-chip");
    chip.setAttribute("data-iso", iso);

    var flag = el("span", "iti__flag iti__" + iso);
    flag.setAttribute("aria-hidden", "true");
    chip.appendChild(flag);

    chip.appendChild(el("span", "intelebox-chip__name", name));
    if (dial) chip.appendChild(el("span", "intelebox-chip__dial", "+" + dial));

    var remove = el("button", "intelebox-chip__remove", "×");
    remove.type = "button";
    remove.setAttribute("aria-label", fmt(S.removeCountry, name));
    chip.appendChild(remove);

    if (hiddenName) {
      var hidden = document.createElement("input");
      hidden.type = "hidden";
      hidden.name = hiddenName;
      hidden.value = iso;
      chip.appendChild(hidden);
    }

    return chip;
  }

  function syncCheckedClass(list) {
    qsa(list, ".intelebox-country-row").forEach(function (row) {
      var cb = qs(row, "input[type=checkbox]");
      row.classList.toggle("intelebox-checked", !!(cb && cb.checked));
    });
  }

  function filterList(list, query) {
    var anyVisible = false;
    qsa(list, ".intelebox-country-row").forEach(function (row) {
      var match = rowMatches(row, query);
      row.hidden = !match;
      if (match) anyVisible = true;
    });
    var note = qs(list, ".intelebox-no-results");
    if (!anyVisible) {
      if (!note) list.appendChild(el("p", "intelebox-no-results", S.noResults));
    } else if (note) {
      note.parentNode.removeChild(note);
    }
  }

  // Countries picker: checkboxes are the submitted fields; chips mirror them.
  function initCountriesPicker(picker) {
    var list = qs(picker, "[data-list]");
    if (!list) return { render: function () {} };
    var search = qs(picker, "[data-search]");
    var chipsBox = qs(picker, "[data-chips]");
    var counter = qs(picker, "[data-counter]");
    var selectAllBtn = qs(picker, "[data-select-all]");
    var clearBtn = qs(picker, "[data-clear]");

    function checkboxes() { return qsa(list, "input[type=checkbox]"); }
    function checkedBoxes() { return checkboxes().filter(function (cb) { return cb.checked; }); }
    function visibleCheckboxes() {
      return qsa(list, ".intelebox-country-row").filter(function (r) { return !r.hidden; })
        .map(function (r) { return qs(r, "input[type=checkbox]"); })
        .filter(Boolean);
    }

    function render() {
      var checked = checkedBoxes();

      chipsBox.textContent = "";
      checked.forEach(function (cb) {
        var row = cb.closest(".intelebox-country-row");
        var meta = countryMeta(cb.value);
        var name = localName(cb.value, row ? row.getAttribute("data-name") : meta.name);
        var dial = (row ? row.getAttribute("data-dial") : meta.dial) || meta.dial;
        var chip = buildChip(cb.value, name, dial, null);
        chip.querySelector(".intelebox-chip__remove").addEventListener("click", function () {
          cb.checked = false;
          render();
          schedulePreview();
        });
        chipsBox.appendChild(chip);
      });

      if (counter) counter.textContent = fmt(S.selectedCount, checked.length);
      syncCheckedClass(list);
    }

    list.addEventListener("change", function (e) {
      if (e.target && e.target.type === "checkbox") render();
    });

    if (search) search.addEventListener("input", function () {
      filterList(list, search.value.trim().toLowerCase());
    });

    if (selectAllBtn) selectAllBtn.addEventListener("click", function () {
      visibleCheckboxes().forEach(function (cb) { cb.checked = true; });
      render();
      schedulePreview();
    });

    if (clearBtn) clearBtn.addEventListener("click", function () {
      checkboxes().forEach(function (cb) { cb.checked = false; });
      render();
      schedulePreview();
    });

    render();
    return { render: render };
  }

  // Preferred picker: ordered selection, max N, hidden input per chip keeps order.
  function initPreferredPicker(picker) {
    var list = qs(picker, "[data-list]");
    if (!list) return { getSelected: function () { return []; }, setSelected: function () {} };
    var search = qs(picker, "[data-search]");
    var chipsBox = qs(picker, "[data-chips]");
    var counter = qs(picker, "[data-counter]");

    var selected = [];
    var preferredAttr = picker.getAttribute("data-preferred");
    if (preferredAttr) {
      try {
        var parsed = JSON.parse(preferredAttr);
        if (Object.prototype.toString.call(parsed) === "[object Array]") {
          selected = parsed.filter(function (v) { return typeof v === "string"; });
        }
      } catch (e) {}
    }
    if (!selected.length) {
      selected = qsa(list, "input[type=checkbox]:checked").map(function (cb) { return cb.value; });
    }
    selected = selected.filter(function (iso, i, a) { return a.indexOf(iso) === i; }).slice(0, MAX_PREFERRED);

    function checkboxes() { return qsa(list, "input[type=checkbox]"); }

    // The chips' hidden inputs are the submitted fields; un-name the list checkboxes.
    checkboxes().forEach(function (cb) { cb.removeAttribute("name"); });

    function render() {
      chipsBox.textContent = "";
      selected.forEach(function (iso) {
        var row = qs(list, '.intelebox-country-row[data-iso="' + iso + '"]');
        var meta = countryMeta(iso);
        var name = localName(iso, row ? row.getAttribute("data-name") : meta.name);
        var dial = (row ? row.getAttribute("data-dial") : meta.dial) || meta.dial;
        var chip = buildChip(iso, name, dial, PREFERRED_FIELD);
        chip.querySelector(".intelebox-chip__remove").addEventListener("click", function () { toggle(iso, false); });
        chipsBox.appendChild(chip);
      });

      checkboxes().forEach(function (cb) {
        var isSelected = selected.indexOf(cb.value) !== -1;
        cb.checked = isSelected;
        cb.disabled = !isSelected && selected.length >= MAX_PREFERRED;
      });

      if (counter) counter.textContent = fmt(S.preferredCount, selected.length, MAX_PREFERRED);
      syncCheckedClass(list);
    }

    function toggle(iso, add) {
      var idx = selected.indexOf(iso);
      if (add) {
        if (idx === -1) {
          if (selected.length >= MAX_PREFERRED) return; // enforce limit
          selected.push(iso);
        }
      } else if (idx !== -1) {
        selected.splice(idx, 1);
      }
      render();
      schedulePreview();
    }

    list.addEventListener("change", function (e) {
      if (e.target && e.target.type === "checkbox") toggle(e.target.value, e.target.checked);
    });

    if (search) search.addEventListener("input", function () {
      filterList(list, search.value.trim().toLowerCase());
    });

    render();

    return {
      getSelected: function () { return selected.slice(); },
      setSelected: function (list2) { selected = (list2 || []).filter(function (v) { return typeof v === "string"; }).slice(0, MAX_PREFERRED); render(); }
    };
  }

  // ------------------------------------------------------------------ mode

  function currentMode(form) {
    var checked = qs(form, nameSel(MODE_FIELD) + ":checked");
    return checked ? checked.value : "all";
  }

  function modeHelp(mode) {
    if (mode === "include") return S.modeHelpInclude;
    if (mode === "exclude") return S.modeHelpExclude;
    return S.modeHelpAll;
  }

  function syncMode(form, pickerEl) {
    var mode = currentMode(form);
    if (pickerEl) pickerEl.hidden = mode === "all";
    var help = qs(form, "[data-mode-help]");
    if (help) help.textContent = modeHelp(mode);
  }

  // --------------------------------------------------------------- preview

  var previewInput = null;
  var previewIti = null;
  var previewTimer = null;
  var previewForm = null;

  function allCountryCodes() {
    return Object.keys(COUNTRIES).filter(function (c) { return c !== "il"; });
  }

  function readState(form) {
    var mode = currentMode(form);
    var initialEl = qs(form, nameSel(INITIAL_FIELD));
    var fallbackEl = qs(form, nameSel(FALLBACK_FIELD));
    var geoEl = qs(form, nameSel(GEO_FIELD));
    var countries = qsa(form, nameSel(COUNTRIES_FIELD) + ":checked").map(function (cb) { return cb.value; });
    var preferred = qsa(form, nameSel(PREFERRED_FIELD)).map(function (h) { return h.value; });

    return {
      mode: mode,
      countries: countries,
      preferred: preferred,
      initial: initialEl ? initialEl.value : "auto",
      fallback: fallbackEl ? fallbackEl.value : "eg",
      geo: geoEl ? !!geoEl.checked : true
    };
  }

  function computeOffered(state) {
    var all = allCountryCodes();
    var sel = state.countries.filter(function (c) { return all.indexOf(c) !== -1; });
    if (state.mode === "include") return all.filter(function (c) { return sel.indexOf(c) !== -1; });
    if (state.mode === "exclude") return all.filter(function (c) { return sel.indexOf(c) === -1; });
    return all.slice();
  }

  function buildPreviewConfig(state) {
    var offered = computeOffered(state);
    var preferred = state.preferred.filter(function (c) { return offered.indexOf(c) !== -1; }).slice(0, MAX_PREFERRED);

    var fallback = state.fallback;
    if (offered.indexOf(fallback) === -1) {
      fallback = preferred[0] || offered.slice().sort()[0] || "eg";
    }

    var initial;
    if (state.initial === "auto") {
      initial = fallback; // preview never calls ipapi.co
    } else {
      initial = state.initial;
      if (offered.indexOf(initial) === -1) initial = fallback;
    }

    var excludeCountries = ALWAYS_EXCLUDED.slice();
    if (state.mode === "exclude") {
      state.countries.forEach(function (c) {
        if (excludeCountries.indexOf(c) === -1) excludeCountries.push(c);
      });
    }

    return {
      onlyCountries: state.mode === "include" ? offered.slice() : [],
      excludeCountries: excludeCountries,
      countryOrder: preferred,
      initialCountry: initial
    };
  }

  function schedulePreview() {
    if (!previewInput) return;
    if (previewTimer) clearTimeout(previewTimer);
    previewTimer = setTimeout(initPreview, 0);
  }

  function initPreview() {
    if (!previewInput || typeof window.intlTelInput !== "function") return;
    var form = previewForm || previewInput.closest("form");
    var cfg = buildPreviewConfig(readState(form));

    if (previewIti && typeof previewIti.destroy === "function") previewIti.destroy();
    previewIti = null;

    var opts = {
      separateDialCode: true,
      countryOrder: cfg.countryOrder,
      excludeCountries: cfg.excludeCountries,
      initialCountry: cfg.initialCountry,
      numberDisplayFormat: "INTERNATIONAL",
      placeholderNumberPolicy: "AGGRESSIVE",
      dropdownParent: document.body,
      countrySelectorMode: "DROPDOWN",
      matchDropdownWidth: false,
      showFlags: true,
      placeholderNumberType: "MOBILE",
      allowedNumberTypes: null,
      allowNumberExtensions: true,
      strictMode: false
    };
    if (cfg.onlyCountries.length) opts.onlyCountries = cfg.onlyCountries;

    // Never pass initialCountryLookup here; preview never contacts ipapi.co.
    previewIti = window.intlTelInput(previewInput, opts);
  }

  // ------------------------------------------------------------------ reset

  function resetToDefaults(form, preferredApi, countriesPickerEl, countriesApi) {
    var d = DEFAULTS;

    var modeVal = MODES.indexOf(d.country_mode) !== -1 ? d.country_mode : "all";
    qsa(form, nameSel(MODE_FIELD)).forEach(function (r) { r.checked = r.value === modeVal; });

    qsa(form, nameSel(COUNTRIES_FIELD)).forEach(function (cb) { cb.checked = false; });

    var pref = (d.preferred_countries || ["eg"]).slice(0, MAX_PREFERRED);
    if (preferredApi) preferredApi.setSelected(pref);

    var initial = qs(form, nameSel(INITIAL_FIELD));
    if (initial) initial.value = d.initial_country || "auto";
    var fallback = qs(form, nameSel(FALLBACK_FIELD));
    if (fallback) fallback.value = d.fallback_country || "eg";
    var geo = qs(form, nameSel(GEO_FIELD));
    if (geo) geo.checked = d.geo_lookup === undefined ? true : !!d.geo_lookup;

    qsa(form, "[data-search]").forEach(function (s) { s.value = ""; });

    syncMode(form, countriesPickerEl);
    if (countriesApi && countriesApi.render) countriesApi.render();
    schedulePreview();
  }

  // ------------------------------------------------------------------- init

  function ready(fn) {
    if (document.readyState !== "loading") fn();
    else document.addEventListener("DOMContentLoaded", fn);
  }

  ready(function () {
    var root = document.querySelector(".intelebox-admin");
    if (!root) return;
    var form = root.querySelector("form");
    if (!form) return;

    var countriesPickerEl = qs(form, '[data-picker="countries"]');
    var preferredPickerEl = qs(form, '[data-picker="preferred"]');

    var preferredApi = preferredPickerEl ? initPreferredPicker(preferredPickerEl) : null;
    var countriesApi = countriesPickerEl ? initCountriesPicker(countriesPickerEl) : null;

    // Segmented control uses real radios; react to changes.
    qsa(form, nameSel(MODE_FIELD)).forEach(function (radio) {
      radio.addEventListener("change", function () {
        syncMode(form, countriesPickerEl);
        schedulePreview();
      });
    });
    syncMode(form, countriesPickerEl);

    // Any setting change re-runs the preview; ignore events from inside preview.
    form.addEventListener("change", function (e) {
      var t = e.target;
      if (!t || typeof t.name !== "string") return;
      if (previewInput && (t === previewInput || t.closest("[data-preview]"))) return;
      if (t.name.indexOf("intelebox_settings[") !== 0) return;
      schedulePreview();
    });

    // Preview field.
    previewInput = qs(form, "[data-preview]");
    previewForm = form;
    if (previewInput) {
      previewInput.removeAttribute("name");
      ["change", "input"].forEach(function (ev) {
        previewInput.addEventListener(ev, function (e) { e.stopPropagation(); });
      });
      initPreview();
    }

    // Reset to defaults (client-side only; user still saves).
    var resetBtn = qs(form, "[data-reset]");
    if (resetBtn) resetBtn.addEventListener("click", function () {
      resetToDefaults(form, preferredApi, countriesPickerEl, countriesApi);
    });
  });
})();
