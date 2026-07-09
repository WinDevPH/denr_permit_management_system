// Profile Image Upload
document
  .getElementById("profileUpload")
  .addEventListener("change", function (e) {
    const file = e.target.files[0];
    if (!file) return;

    showLoading();
    const formData = new FormData();
    formData.append("profile_image", file);

    fetch("../../../handlers/update_profile_image.php", {
      method: "POST",
      body: formData,
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.status === "success") {
          const profileImage = document.getElementById("profileImage");
          profileImage.src =
            "../../../assets/uploads/profiles/" + data.filename;
          profileImage.style.display = "";
          profileImage.alt = "Profile";
          const placeholder = document.getElementById("profileImagePlaceholder");
          if (placeholder) placeholder.style.display = "none";
          showNotification("success", "Profile image updated successfully");
        } else {
          throw new Error(data.message || "Error updating profile image");
        }
      })
      .catch((error) => {
        showNotification("error", error.message);
      })
      .finally(() => {
        hideLoading();
      });
  });

// Update Profile Form
document
  .getElementById("updateProfileForm")
  .addEventListener("submit", function (e) {
    e.preventDefault();
    const phoneInput = this.querySelector('[name="contact_number"]');
    if (phoneInput) {
      const d = String(phoneInput.value || "").replace(/\D/g, "");
      if (d.length < 7 || d.length > 15) {
        showErrorPopup("Contact number must be 7–15 digits only (no letters).");
        return;
      }
      phoneInput.value = d;
    }

    showLoading();
    let progress = 0;

    const progressInterval = setInterval(() => {
      progress += Math.random() * 30;
      if (progress > 90) clearInterval(progressInterval);
      document.querySelector(".loading-percentage").textContent =
        Math.min(Math.round(progress), 90) + "%";
    }, 500);

    fetch("../../../handlers/update_profile.php", {
      method: "POST",
      body: new FormData(this),
    })
      .then((response) => response.json())
      .then((data) => {
        clearInterval(progressInterval);
        document.querySelector(".loading-percentage").textContent = "100%";

        setTimeout(() => {
          hideLoading();
          if (data.status === "success") {
            showNotification("success", data.message);
            setTimeout(() => location.reload(), 1500);
          } else {
            showErrorPopup(data.message || "Error updating profile");
          }
        }, 500);
      })
      .catch((error) => {
        clearInterval(progressInterval);
        hideLoading();
        showErrorPopup(error.message || "An unexpected error occurred");
      });
  });

// Change Password Form
document
  .getElementById("changePasswordForm")
  .addEventListener("submit", function (e) {
    e.preventDefault();
    showLoading();
    let progress = 0;

    const progressInterval = setInterval(() => {
      progress += Math.random() * 30;
      if (progress > 90) clearInterval(progressInterval);
      document.querySelector(".loading-percentage").textContent =
        Math.min(Math.round(progress), 90) + "%";
    }, 500);

    fetch("../../../handlers/change_password.php", {
      method: "POST",
      body: new FormData(this),
    })
      .then((response) => response.json())
      .then((data) => {
        clearInterval(progressInterval);
        document.querySelector(".loading-percentage").textContent = "100%";

        setTimeout(() => {
          hideLoading();
          if (data.status === "success") {
            showNotification("success", data.message);
            this.reset();
          } else {
            showErrorPopup(data.message || "Error changing password");
          }
        }, 500);
      })
      .catch((error) => {
        clearInterval(progressInterval);
        hideLoading();
        showErrorPopup(error.message || "An unexpected error occurred");
      });
  });

// Toggle Password Visibility
document.querySelectorAll(".toggle-password").forEach((button) => {
  button.addEventListener("click", function () {
    const input = this.previousElementSibling;
    const icon = this.querySelector("i");

    if (input.type === "password") {
      input.type = "text";
      icon.classList.replace("fa-eye", "fa-eye-slash");
    } else {
      input.type = "password";
      icon.classList.replace("fa-eye-slash", "fa-eye");
    }
  });
});

// Loading functions
function showLoading() {
  document.querySelector(".loading-overlay").style.display = "flex";
}

function hideLoading() {
  document.querySelector(".loading-overlay").style.display = "none";
}

// Notification Functions
function showNotification(type, message) {
  const notification = document.getElementById(type + "Notification");
  notification.querySelector(".message").textContent = message;
  notification.classList.add("show");

  // Auto hide after 3 seconds
  setTimeout(() => hideNotification(type), 3000);
}

function hideNotification(type) {
  const notification = document.getElementById(type + "Notification");
  notification.classList.remove("show");
}

// Error Popup Functions
function showErrorPopup(message) {
  const popup = document.getElementById("errorPopup");
  const messageEl = document.getElementById("errorMessage");
  messageEl.textContent = message;
  popup.style.display = "flex";
}

function hideErrorPopup() {
  document.getElementById("errorPopup").style.display = "none";
}

// Add click handlers to notification close buttons
document.querySelectorAll(".notification .close-btn").forEach((btn) => {
  btn.addEventListener("click", function () {
    const notification = this.closest(".notification");
    notification.classList.remove("show");
  });
});
