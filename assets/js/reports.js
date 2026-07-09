/**
 * DENR Reports — Same UX as MNHS: type selection, generate (form submit), print, download
 * Data is server-rendered; no API. Type cards update hidden input and CSV link.
 */
(function () {
  "use strict";

  function byId(id) {
    return document.getElementById(id);
  }

  function getFormParams() {
    var form = document.getElementById("reportsFilterForm");
    if (!form) return {};
    var data = new FormData(form);
    return {
      report_type: (data.get("report_type") || "permits").toString(),
      start_date: (data.get("start_date") || "").toString(),
      end_date: (data.get("end_date") || "").toString()
    };
  }

  function updateCsvLink() {
    var params = getFormParams();
    var a = document.getElementById("reportsCsvBtn");
    if (!a || a.tagName !== "A") return;
    var q = "report_type=" + encodeURIComponent(params.report_type) +
      "&start_date=" + encodeURIComponent(params.start_date) +
      "&end_date=" + encodeURIComponent(params.end_date) +
      "&export=csv";
    a.setAttribute("href", "?" + q);
  }

  function bindTypeCards() {
    var input = byId("reportsTypeInput");
    document.querySelectorAll(".reports-type-card").forEach(function (btn) {
      btn.addEventListener("click", function () {
        var report = btn.getAttribute("data-report") || "permits";
        document.querySelectorAll(".reports-type-card").forEach(function (b) {
          b.classList.remove("is-active");
          b.setAttribute("aria-pressed", "false");
        });
        btn.classList.add("is-active");
        btn.setAttribute("aria-pressed", "true");
        if (input) input.value = report;
        updateCsvLink();
      });
    });
  }

  function printReport() {
    var wrap = byId("reportsTableWrap");
    var table = document.getElementById("reportsTable");
    var empty = byId("reportsEmptyState");
    if (empty && !empty.hidden) {
      if (typeof showNotification === "function") showNotification("error", "Generate a report first.");
      else window.alert("Generate a report first.");
      return;
    }
    if (!table || !wrap || wrap.hidden) {
      if (typeof showNotification === "function") showNotification("error", "No report data to print.");
      else window.alert("No report data to print.");
      return;
    }

    var w = window.open("", "_blank");
    if (!w) {
      if (typeof showNotification === "function") showNotification("error", "Allow popups to print.");
      else window.alert("Allow popups to print.");
      return;
    }

    var title = "DENR Report — " + (document.querySelector(".reports-output-title") ? document.querySelector(".reports-output-title").textContent : "Report Results");
    var period = "";
    var dateFrom = byId("reportsDateFrom");
    var dateTo = byId("reportsDateTo");
    if (dateFrom && dateTo && dateFrom.value && dateTo.value) {
      period = dateFrom.value + " to " + dateTo.value;
    }
    var rowCount = table.querySelectorAll("tbody tr").length;
    var printedAt = new Date().toLocaleString("en-PH", { dateStyle: "medium", timeStyle: "short" });
    var logoUrl = document.querySelector('link[rel="icon"]') ? document.querySelector('link[rel="icon"]').href : "";
    var printCss = "\n        * { box-sizing: border-box; }\n        body { margin: 0; padding: 0; font-family: 'Segoe UI', system-ui, sans-serif; font-size: 13px; color: #1f2937; background: #fff; }\n        .print-page { padding: 1.5rem; max-width: 100%; }\n        .print-header { display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem; padding-bottom: 1rem; border-bottom: 3px solid #007c36; }\n        .print-logo { flex-shrink: 0; width: 56px; height: 56px; object-fit: contain; }\n        .print-title { margin: 0 0 0.2rem; font-size: 1.35rem; font-weight: 700; color: #1f2937; }\n        .print-subtitle { margin: 0; font-size: 0.875rem; color: #6b7280; }\n        .print-table-wrap { width: 100%; border: 1px solid #e8eaed; border-radius: 8px; overflow: hidden; }\n        .print-table { width: 100%; border-collapse: collapse; font-size: 12px; }\n        .print-table th, .print-table td { border: 1px solid #e8eaed; padding: 0.5rem 0.75rem; text-align: left; }\n        .print-table thead th { background: #007c36; color: #fff; font-weight: 600; }\n        .print-table tbody tr:nth-child(even) { background: #f9fafb; }\n        .print-summary { margin-top: 1rem; font-size: 12px; color: #6b7280; }\n        .print-footer { margin-top: 1rem; padding-top: 0.75rem; border-top: 1px solid #e8eaed; font-size: 11px; color: #6b7280; }\n        @media print { body { -webkit-print-color-adjust: exact; print-color-adjust: exact; } .print-page { padding: 0.5rem; } }\n      ";
    var periodHtml = period ? '<p class="print-subtitle">' + escapeHtml(period) + "</p>" : "";
    var tableHtml = table.outerHTML.replace(/reports-table/g, "print-table").replace(/reports-status-badge/g, "print-badge");
    w.document.write(
      "<!DOCTYPE html><html><head><meta charset=\"utf-8\"><title>" + escapeHtml(title) + "</title><style>" + printCss + "</style></head><body>" +
      "<div class=\"print-page\">" +
      "<header class=\"print-header\">" +
      (logoUrl ? "<img src=\"" + logoUrl.replace(/"/g, "&quot;") + "\" alt=\"\" class=\"print-logo\" />" : "") +
      "<div><h1 class=\"print-title\">" + escapeHtml(title) + "</h1>" + periodHtml + "</div></header>" +
      "<div class=\"print-table-wrap\">" + tableHtml + "</div>" +
      "<div class=\"print-summary\">Total: " + rowCount + " record(s)</div>" +
      "<footer class=\"print-footer\">Printed on " + escapeHtml(printedAt) + "</footer>" +
      "</div></body></html>"
    );
    w.document.close();
    w.focus();
    setTimeout(function () { w.print(); }, 400);
  }

  function escapeHtml(s) {
    if (s == null) return "";
    var div = document.createElement("div");
    div.textContent = s;
    return div.innerHTML;
  }

  function initDates() {
    var dateFrom = byId("reportsDateFrom");
    var dateTo = byId("reportsDateTo");
    if (!dateFrom || !dateTo) return;
    var today = new Date();
    var firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
    if (!dateFrom.value) dateFrom.value = formatYMD(firstDay);
    if (!dateTo.value) dateTo.value = formatYMD(today);
  }

  function formatYMD(d) {
    var y = d.getFullYear();
    var m = String(d.getMonth() + 1).padStart(2, "0");
    var day = String(d.getDate()).padStart(2, "0");
    return y + "-" + m + "-" + day;
  }

  function init() {
    initDates();
    bindTypeCards();
    updateCsvLink();
    var printBtn = byId("reportsPrintBtn");
    if (printBtn) printBtn.addEventListener("click", printReport);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
