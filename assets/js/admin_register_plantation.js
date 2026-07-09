/**
 * Admin register plantation: map (lot, Mohon), species rows, landowner match, digits-only phone.
 * Expects window.DENR_REG_PLANT = { geocodeSearch: string, geocodeReverse: string } (relative to current page).
 */
(function () {
  var CFG = window.DENR_REG_PLANT || {};
  var GEO_SEARCH = CFG.geocodeSearch || "../../../handlers/geocode.php?action=search&q=";
  var GEO_REV = CFG.geocodeReverse || "../../../handlers/geocode.php?action=reverse&lat=";

  var map,
    marker,
    mohonMarkers = [],
    boundaryLayer = null,
    setMohonMode = false,
    locatingSpinner = null;

  function toast(msg, isErr) {
    if (typeof showNotification === "function") {
      showNotification(isErr ? "error" : "success", msg);
      return;
    }
    window.alert(msg);
  }

  function denrDigits(val) {
    return String(val || "").replace(/\D/g, "");
  }

  function buildTreeSpeciesFromRows() {
    var rows = document.querySelectorAll("#tree_species_rows .tree-species-row");
    var parts = [];
    rows.forEach(function (row) {
      var sel = row.querySelector(".tree-species-select");
      var qty = row.querySelector(".tree-species-qty");
      var name = sel ? sel.value.trim() : "";
      var num = qty && qty.value !== "" ? parseInt(qty.value, 10) : 0;
      if (name && num > 0) parts.push(name + ":" + num);
    });
    var hid = document.getElementById("tree_species_hidden");
    if (hid) hid.value = parts.join(",");
    return parts.length > 0;
  }

  function updateRemoveSpeciesButtons() {
    var rows = document.querySelectorAll("#tree_species_rows .tree-species-row");
    rows.forEach(function (row) {
      var btn = row.querySelector(".remove-species-row");
      if (btn) btn.disabled = rows.length <= 1;
    });
  }

  function mohonNumberIcon(index) {
    return L.divIcon({
      className: "mohon-marker-icon",
      html:
        '<div style="background:#c0392b;width:26px;height:26px;border-radius:50%;border:3px solid #fff;box-shadow:0 2px 5px rgba(0,0,0,0.3);display:flex;align-items:center;justify-content:center;color:#fff;font-size:11px;font-weight:700;">' +
        index +
        "</div>",
      iconSize: [26, 26],
      iconAnchor: [13, 13],
    });
  }

  function getMohonPoints() {
    return mohonMarkers.map(function (m) {
      var ll = m.getLatLng();
      return { lat: ll.lat, lng: ll.lng };
    });
  }

  function syncMohonHiddenFields() {
    var pts = getMohonPoints();
    var el = document.getElementById("mohon_points_json");
    if (el) el.value = JSON.stringify(pts);
    if (pts.length > 0) {
      document.getElementById("landmark_latitude").value = pts[0].lat;
      document.getElementById("landmark_longitude").value = pts[0].lng;
    } else {
      document.getElementById("landmark_latitude").value = "";
      document.getElementById("landmark_longitude").value = "";
    }
    updateMohonSummary();
  }

  function updateBoundaryAreaDisplay(pts) {
    var areaEl = document.getElementById("boundaryAreaDisplay");
    if (!areaEl) return;
    if (!pts || pts.length < 3) {
      areaEl.innerHTML =
        pts && pts.length === 2
          ? '<span class="text-muted">Add one more Mohon to calculate total boundary area.</span>'
          : "";
      return;
    }
    var areaText = typeof denrFormatBoundaryArea === "function" ? denrFormatBoundaryArea(pts) : "";
    areaEl.innerHTML = areaText
      ? '<strong><i class="fas fa-ruler-combined me-1"></i>Total area:</strong> ' + areaText
      : "";
  }

  function updateMohonSummary() {
    var el = document.getElementById("mohonSummary");
    if (!el) return;
    var pts = getMohonPoints();
    if (pts.length === 0) {
      el.textContent = "No Mohon placed yet.";
      updateBoundaryAreaDisplay(pts);
      return;
    }
    el.innerHTML =
      "<strong>" +
      pts.length +
      " Mohon:</strong> " +
      pts
        .map(function (p, i) {
          return "#" + (i + 1) + " (" + p.lat.toFixed(5) + ", " + p.lng.toFixed(5) + ")";
        })
        .join(" · ");
    updateBoundaryAreaDisplay(pts);
  }

  function redrawBoundaryTrack() {
    if (!map) return;
    if (boundaryLayer) {
      map.removeLayer(boundaryLayer);
      boundaryLayer = null;
    }
    var pts = getMohonPoints().map(function (p) {
      return [p.lat, p.lng];
    });
    if (pts.length >= 3) {
      boundaryLayer = L.polygon(pts, {
        color: "#c0392b",
        weight: 2,
        fillColor: "#e74c3c",
        fillOpacity: 0.12,
      }).addTo(map);
    } else if (pts.length === 2) {
      boundaryLayer = L.polyline(pts, { color: "#c0392b", weight: 3, dashArray: "10,8" }).addTo(map);
    }
  }

  function fitMapToPlantationArea() {
    if (!map) return;
    var bounds = L.latLngBounds([]);
    var latEl = document.getElementById("latitude");
    var lngEl = document.getElementById("longitude");
    if (latEl && lngEl && latEl.value && lngEl.value) {
      bounds.extend([parseFloat(latEl.value), parseFloat(lngEl.value)]);
    }
    getMohonPoints().forEach(function (p) {
      bounds.extend([p.lat, p.lng]);
    });
    if (bounds.isValid()) map.fitBounds(bounds, { padding: [40, 40], maxZoom: 17 });
  }

  function addMohonAt(latlng) {
    if (!map) return;
    var idx = mohonMarkers.length + 1;
    var m = L.marker(latlng, { icon: mohonNumberIcon(idx), draggable: true }).addTo(map);
    m.on("drag", function () {
      syncMohonHiddenFields();
      redrawBoundaryTrack();
    });
    m.on("dragend", fitMapToPlantationArea);
    mohonMarkers.push(m);
    mohonMarkers.forEach(function (mm, i) {
      mm.setIcon(mohonNumberIcon(i + 1));
    });
    syncMohonHiddenFields();
    redrawBoundaryTrack();
    fitMapToPlantationArea();
  }

  function clearAllMohon() {
    mohonMarkers.forEach(function (m) {
      if (map) map.removeLayer(m);
    });
    mohonMarkers = [];
    if (boundaryLayer && map) {
      map.removeLayer(boundaryLayer);
      boundaryLayer = null;
    }
    syncMohonHiddenFields();
  }

  function updateLotCoordinatesDisplay(lat, lng) {
    var text = document.getElementById("coordinatesText");
    if (!text) return;
    if (lat != null && lng != null && lat !== "" && lng !== "") {
      text.textContent = " Lat: " + parseFloat(lat).toFixed(6) + ", Lng: " + parseFloat(lng).toFixed(6);
      text.classList.remove("text-muted");
    } else {
      text.textContent = "Click on map to pin — coordinates update in real time";
      text.classList.add("text-muted");
    }
  }

  function setMarker(latlng, opts) {
    opts = opts || {};
    if (!map) return;
    if (marker) map.removeLayer(marker);
    marker = L.marker(latlng, { draggable: true }).addTo(map);
    document.getElementById("latitude").value = latlng.lat;
    document.getElementById("longitude").value = latlng.lng;
    updateLotCoordinatesDisplay(latlng.lat, latlng.lng);
    if (!opts.skipGeocode) {
      fetch(GEO_REV + latlng.lat + "&lon=" + latlng.lng)
        .then(function (r) {
          return r.json();
        })
        .then(function (data) {
          var addr = document.getElementById("location_address");
          if (addr && data.display_name) addr.value = data.display_name;
        })
        .catch(function () {});
    }
    marker.on("drag", function (e) {
      var pos = e.target.getLatLng();
      document.getElementById("latitude").value = pos.lat;
      document.getElementById("longitude").value = pos.lng;
      updateLotCoordinatesDisplay(pos.lat, pos.lng);
    });
    marker.on("dragend", function (e) {
      var pos = e.target.getLatLng();
      fetch(GEO_REV + pos.lat + "&lon=" + pos.lng)
        .then(function (r) {
          return r.json();
        })
        .then(function (data) {
          var addr = document.getElementById("location_address");
          if (addr && data.display_name) addr.value = data.display_name;
        })
        .catch(function () {});
    });
    fitMapToPlantationArea();
  }

  function getCurrentLocation() {
    if (!navigator.geolocation) {
      toast("Geolocation is not supported.", true);
      return;
    }
    if (locatingSpinner) locatingSpinner.style.display = "block";
    var ms = document.querySelector(".map-status");
    if (ms) {
      ms.classList.add("show");
      var sp = ms.querySelector("span");
      if (sp) sp.textContent = "Getting your location...";
    }
    navigator.geolocation.getCurrentPosition(
      function (position) {
        var latlng = { lat: position.coords.latitude, lng: position.coords.longitude };
        setMarker(latlng);
        map.setView(latlng, 16);
        if (locatingSpinner) locatingSpinner.style.display = "none";
        if (ms) ms.classList.remove("show");
      },
      function () {
        if (locatingSpinner) locatingSpinner.style.display = "none";
        if (ms) ms.classList.remove("show");
        toast("Could not get your location.", true);
      },
      { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
    );
  }

  L.Control.Location = L.Control.extend({
    onAdd: function () {
      var div = L.DomUtil.create("div", "leaflet-bar leaflet-control");
      var button = L.DomUtil.create("a", "location-button", div);
      button.innerHTML = '<i class="fas fa-location-crosshairs"></i>';
      button.title = "My Location";
      button.href = "#";
      button.style.cssText = "display:flex;align-items:center;justify-content:center;width:34px;height:34px;";
      locatingSpinner = L.DomUtil.create("span", "spinner", button);
      locatingSpinner.style.display = "none";
      locatingSpinner.style.cssText =
        "width:14px;height:14px;border:2px solid #f3f3f3;border-top:2px solid #3388ff;border-radius:50%;animation:denrSpin 1s linear infinite;";
      L.DomEvent.on(button, "click", function (e) {
        L.DomEvent.preventDefault(e);
        getCurrentLocation();
      });
      return div;
    },
  });

  var fullScreenControl = L.Control.extend({
    onAdd: function () {
      var c = L.DomUtil.create("div", "leaflet-bar leaflet-control");
      var b = L.DomUtil.create("a", "fullscreen-button", c);
      b.innerHTML = '<i class="fas fa-expand"></i>';
      b.title = "Full screen";
      b.href = "#";
      b.style.cssText = "display:flex;align-items:center;justify-content:center;width:34px;height:34px;";
      L.DomEvent.on(b, "click", function (e) {
        L.DomEvent.preventDefault(e);
        var el = document.getElementById("map");
        if (!document.fullscreenElement) {
          (el.requestFullscreen || el.webkitRequestFullscreen || el.msRequestFullscreen).call(el);
          b.querySelector("i").classList.replace("fa-expand", "fa-compress");
        } else {
          (document.exitFullscreen || document.webkitExitFullscreen || document.msExitFullscreen).call(document);
          b.querySelector("i").classList.replace("fa-compress", "fa-expand");
        }
      });
      return c;
    },
  });

  function initMap() {
    if (map || !document.getElementById("map")) return;
    var spinStyle = document.createElement("style");
    spinStyle.textContent = "@keyframes denrSpin{to{transform:rotate(360deg)}}";
    document.head.appendChild(spinStyle);

    map = L.map("map").setView([7.1907, 122.0794], 13);
    var baseMaps = {
      Default: L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", { attribution: "© OSM" }),
      Satellite: L.tileLayer(
        "https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}",
        { attribution: "© Esri" }
      ),
      Terrain: L.tileLayer("https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png", { attribution: "© OpenTopoMap" }),
    };
    baseMaps.Default.addTo(map);
    L.control.layers(baseMaps, null, { position: "topright" }).addTo(map);
    new L.Control.Location({ position: "topleft" }).addTo(map);
    map.addControl(new fullScreenControl({ position: "topright" }));

    var searchInput = document.getElementById("searchLocation");
    var searchBtn = document.getElementById("searchBtn");
    function performSearch() {
      var searchText = searchInput && searchInput.value.trim();
      if (!searchText) return;
      searchBtn && searchBtn.classList.add("loading");
      var ms = document.querySelector(".map-status");
      if (ms) ms.classList.add("show");
      fetch(GEO_SEARCH + encodeURIComponent(searchText))
        .then(function (r) {
          return r.json();
        })
        .then(function (data) {
          if (data && data.length > 0) {
            var loc = data[0];
            var latlng = { lat: parseFloat(loc.lat), lng: parseFloat(loc.lon) };
            setMarker(latlng);
            map.setView(latlng, 16);
          } else toast("Location not found", true);
        })
        .catch(function () {
          toast("Search failed", true);
        })
        .finally(function () {
          searchBtn && searchBtn.classList.remove("loading");
          if (ms) ms.classList.remove("show");
        });
    }
    if (searchBtn) searchBtn.addEventListener("click", performSearch);
    if (searchInput)
      searchInput.addEventListener("keypress", function (e) {
        if (e.key === "Enter") {
          e.preventDefault();
          performSearch();
        }
      });

    map.on("click", function (e) {
      if (setMohonMode) addMohonAt(e.latlng);
      else setMarker(e.latlng);
    });

    var tmb = document.getElementById("toggleMohonPlaceBtn");
    if (tmb)
      tmb.addEventListener("click", function () {
        setMohonMode = true;
        tmb.classList.add("active");
        var done = document.getElementById("doneMohonBtn");
        if (done) done.style.display = "";
        var msum = document.getElementById("mohonSummary");
        if (msum)
          msum.innerHTML =
            '<span class="text-success">Click the map for each Mohon. Press <strong>Done</strong> when finished.</span>';
      });
    var doneMohonBtn = document.getElementById("doneMohonBtn");
    if (doneMohonBtn)
      doneMohonBtn.addEventListener("click", function () {
        setMohonMode = false;
        if (tmb) tmb.classList.remove("active");
        doneMohonBtn.style.display = "none";
        updateMohonSummary();
      });
    document.getElementById("clearMohonBtn") &&
      document.getElementById("clearMohonBtn").addEventListener("click", clearAllMohon);
    document.getElementById("fitMapBoundsBtn") &&
      document.getElementById("fitMapBoundsBtn").addEventListener("click", fitMapToPlantationArea);

    setTimeout(function () {
      map.invalidateSize();
    }, 300);
  }

  function wireLandowner() {
    var sel = document.querySelector('[name="landowner_user_id"]');
    var lookup = document.getElementById("landowner_lookup");
    var jsonEl = document.getElementById("landowners-json");
    var list = [];
    try {
      if (jsonEl && jsonEl.textContent) list = JSON.parse(jsonEl.textContent);
    } catch (e) {
      list = [];
    }
    if (sel)
      sel.addEventListener("change", function () {
        if (lookup && sel.value) lookup.value = "";
      });
    function tryMatch() {
      if (!lookup || !sel) return;
      var q = lookup.value.trim().toLowerCase();
      if (!q) return;
      var exact = list.filter(function (u) {
        return (
          String(u.email || "")
            .toLowerCase()
            .trim() === q ||
          String(u.full_name || "")
            .toLowerCase()
            .trim() === q
        );
      });
      if (exact.length === 1) {
        sel.value = String(exact[0].user_id);
        return;
      }
      var partial = list.filter(function (u) {
        return (
          String(u.email || "")
            .toLowerCase()
            .indexOf(q) >= 0 ||
          String(u.full_name || "")
            .toLowerCase()
            .indexOf(q) >= 0
        );
      });
      if (partial.length === 1) {
        sel.value = String(partial[0].user_id);
      }
    }
    if (lookup) {
      lookup.addEventListener("blur", tryMatch);
      lookup.addEventListener("change", tryMatch);
    }
  }

  function wirePhoneDigits() {
    var phone = document.querySelector('[name="contact_phone"]');
    if (!phone) return;
    phone.addEventListener("input", function () {
      phone.value = denrDigits(phone.value).slice(0, 15);
    });
    phone.addEventListener("paste", function (e) {
      setTimeout(function () {
        phone.value = denrDigits(phone.value).slice(0, 15);
      }, 0);
    });
  }

  function wireSpecies() {
    var addBtn = document.getElementById("add_species_row");
    if (addBtn)
      addBtn.addEventListener("click", function () {
        var container = document.getElementById("tree_species_rows");
        var firstRow = container && container.querySelector(".tree-species-row");
        if (!firstRow) return;
        var clone = firstRow.cloneNode(true);
        clone.querySelector(".tree-species-select").value = "";
        clone.querySelector(".tree-species-qty").value = "1";
        container.appendChild(clone);
        updateRemoveSpeciesButtons();
      });
    document.addEventListener("click", function (e) {
      if (!e.target.closest || !e.target.closest(".remove-species-row")) return;
      var row = e.target.closest(".tree-species-row");
      var container = document.getElementById("tree_species_rows");
      if (row && container && container.querySelectorAll(".tree-species-row").length > 1) {
        row.remove();
        updateRemoveSpeciesButtons();
      }
    });
    updateRemoveSpeciesButtons();
  }

  document.addEventListener("DOMContentLoaded", function () {
    wireLandowner();
    wirePhoneDigits();
    wireSpecies();
    initMap();

    var form = document.getElementById("adminPlantForm");
    if (!form) return;
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      if (!buildTreeSpeciesFromRows()) {
        toast("Add at least one tree species with quantity.", true);
        return;
      }
      var lat = document.getElementById("latitude").value;
      var lng = document.getElementById("longitude").value;
      if (!lat || !lng) {
        toast("Set the lot location on the map.", true);
        return;
      }
      var phone = document.querySelector('[name="contact_phone"]');
      var d = denrDigits(phone && phone.value);
      if (d.length < 7 || d.length > 15) {
        toast("Contact number must be 7–15 digits only.", true);
        return;
      }
      if (phone) phone.value = d;

      var mohonRaw = document.getElementById("mohon_points_json").value;
      var mohonLen = 0;
      try {
        var mj = JSON.parse(mohonRaw || "[]");
        if (Array.isArray(mj)) mohonLen = mj.length;
      } catch (e2) {
        mohonLen = 0;
      }
      if (mohonLen < 2) {
        toast("Place at least two Mohon points on the map for the boundary.", true);
        return;
      }

      var sel = document.querySelector('[name="landowner_user_id"]');
      var lookup = document.getElementById("landowner_lookup");
      if ((!sel || !sel.value) && (!lookup || !lookup.value.trim())) {
        toast("Select a landowner from the list or type their full name or email.", true);
        return;
      }

      var fd = new FormData(form);
      fetch("../../../handlers/admin_add_plantation.php", { method: "POST", body: fd })
        .then(function (r) {
          return r.json();
        })
        .then(function (d) {
          if (d.status === "success") {
            showNotification("success", "Plantation created.");
            window.location.href = "plantations.php";
          } else toast(d.message || "Failed", true);
        })
        .catch(function () {
          toast("Request failed", true);
        });
    });
  });
})();
