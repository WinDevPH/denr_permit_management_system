/**
 * Shared toast notifications for all DENR roles.
 * Requires #successNotification and #errorNotification markup (role_notifications.php).
 */
(function (w) {
  var timers = {};
  var DURATION_MS = 5000;

  function hideNotification(type) {
    var notification = document.getElementById(type + "Notification");
    if (!notification) return;
    notification.classList.remove("show");
    var progressBar = notification.querySelector(".notification-progress");
    if (progressBar) progressBar.style.animation = "none";
    if (timers[type]) {
      clearTimeout(timers[type]);
      delete timers[type];
    }
  }

  function showNotification(type, message) {
    var notification = document.getElementById(type + "Notification");
    if (!notification) {
      w.alert(message);
      return;
    }

    var msgEl = notification.querySelector(".notification-message");
    if (msgEl) msgEl.textContent = message;

    notification.classList.add("show");

    var progressBar = notification.querySelector(".notification-progress");
    if (progressBar) {
      progressBar.style.animation = "none";
      void progressBar.offsetWidth;
      progressBar.style.animation = "denrNotifProgress " + DURATION_MS + "ms linear forwards";
    }

    if (timers[type]) clearTimeout(timers[type]);
    timers[type] = setTimeout(function () {
      hideNotification(type);
    }, DURATION_MS);
  }

  w.showNotification = showNotification;
  w.hideNotification = hideNotification;

  w.showSuccess = function (message) {
    showNotification("success", message);
  };

  w.showError = function (message) {
    showNotification("error", message);
  };

  document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll(".modern-notification .notification-close").forEach(function (btn) {
      btn.addEventListener("click", function () {
        var notif = btn.closest(".modern-notification");
        if (!notif) return;
        if (notif.classList.contains("success")) hideNotification("success");
        else if (notif.classList.contains("error")) hideNotification("error");
      });
    });
  });
})(window);
