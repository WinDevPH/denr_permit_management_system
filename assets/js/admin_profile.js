// Admin Profile JavaScript - Modern & Functional

document.addEventListener("DOMContentLoaded", function () {
  initializeProfile();
});

/**
 * Initialize Profile Functions
 */
function initializeProfile() {
  // Profile Image Upload
  setupProfileImageUpload();

  // Profile update + optional password change (same flow as landowner/verifier profile)
  setupProfileUpdateForm();
  setupPasswordToggle();

  // Notification close buttons handled by role_notifications.js
}

/**
 * Profile Image Upload Handler
 */
function setupProfileImageUpload() {
  const profileUpload = document.getElementById("profileUpload");
  const profileImage = document.getElementById("profileImage");

  if (profileUpload && profileImage) {
    profileUpload.addEventListener("change", function (e) {
      const file = e.target.files[0];
      if (file) {
        // Validate file type
        const validTypes = [
          "image/jpeg",
          "image/png",
          "image/jpg",
          "image/gif",
        ];
        if (!validTypes.includes(file.type)) {
          showError("Please select a valid image file (JPG, PNG, GIF)");
          return;
        }

        // Validate file size (max 5MB)
        if (file.size > 5 * 1024 * 1024) {
          showError("Image size must be less than 5MB");
          return;
        }

        // Create FormData
        const formData = new FormData();
        formData.append("profile_image", file);

        // Upload image
        fetch("../../../handlers/update_profile_image.php", {
          method: "POST",
          body: formData,
        })
          .then((response) => response.json())
          .then((data) => {
            if (data.status === "success") {
              // Update image preview
              const imagePath =
                "../../../assets/uploads/profiles/" + data.filename;
              profileImage.src = imagePath + "?t=" + new Date().getTime();
              profileImage.style.display = "";
              profileImage.alt = "Profile";
              const placeholder = document.getElementById("profileImagePlaceholder");
              if (placeholder) placeholder.style.display = "none";
              showSuccess("Profile image updated successfully!");
            } else {
              showError(data.message || "Failed to upload image");
            }
          })
          .catch((error) => {
            console.error("Error:", error);
            showError("An error occurred while uploading the image");
          });
      }
    });
  }
}

/**
 * Profile Update Form Handler (single form: profile + optional password change)
 */
function setupProfileUpdateForm() {
  const form = document.getElementById("updateProfileForm");

  if (!form) return;

  form.addEventListener("submit", function (e) {
    e.preventDefault();

    const fullName = (form.querySelector('[name="full_name"]') || {}).value || "";
    const email = (form.querySelector('[name="email"]') || {}).value || "";
    const currentPassword = (document.getElementById("currentPassword") || {}).value || "";
    const newPassword = (document.getElementById("newPassword") || {}).value || "";
    const confirmPassword = (document.getElementById("confirmPassword") || {}).value || "";

    const isChangingPassword = currentPassword.length > 0 || newPassword.length > 0 || confirmPassword.length > 0;

    if (!fullName || fullName.trim().length < 3) {
      showError("Full name must be at least 3 characters");
      return;
    }

    if (!email || !isValidEmail(email)) {
      showError("Please enter a valid email address");
      return;
    }

    if (isChangingPassword) {
      if (!currentPassword) {
        showError("Please enter your current password");
        return;
      }
      if (newPassword.length < 8) {
        showError("New password must be at least 8 characters");
        return;
      }
      if (newPassword !== confirmPassword) {
        showError("New passwords do not match");
        return;
      }
      if (currentPassword === newPassword) {
        showError("New password must be different from current password");
        return;
      }
    }

    const profileData = new FormData();
    profileData.append("full_name", fullName.trim());
    profileData.append("email", email.trim());
    profileData.append("contact_number", (form.querySelector('[name="contact_number"]') || {}).value || "");

    fetch("../../../handlers/update_profile.php", { method: "POST", body: profileData })
      .then((response) => response.json())
      .then((data) => {
        if (data.status !== "success") {
          showError(data.message || "Failed to update profile");
          return;
        }

        if (!isChangingPassword) {
          showSuccess("Profile updated successfully!");
          setTimeout(() => location.reload(), 1500);
          return;
        }

        const passwordData = new FormData();
        passwordData.append("current_password", currentPassword);
        passwordData.append("new_password", newPassword);
        passwordData.append("confirm_password", confirmPassword);

        return fetch("../../../handlers/change_password.php", { method: "POST", body: passwordData })
          .then((res) => res.json())
          .then((pwData) => {
            if (pwData.status === "success") {
                showSuccess("Profile and password updated successfully!");
                form.querySelector("#newPassword").value = "";
                form.querySelector("#confirmPassword").value = "";
                form.querySelector("#currentPassword").value = "";
                setTimeout(() => location.reload(), 1500);
              } else {
                showError(pwData.message || "Profile saved but password change failed");
              }
          });
      })
      .catch((error) => {
        console.error("Error:", error);
        showError("An error occurred while saving");
      });
  });
}

/**
 * Password Toggle Functionality
 */
function setupPasswordToggle() {
  const toggleButtons = document.querySelectorAll(".toggle-password");

  toggleButtons.forEach((button) => {
    button.addEventListener("click", function () {
      const input = this.previousElementSibling;
      const icon = this.querySelector("i");

      if (input.type === "password") {
        input.type = "text";
        icon.classList.remove("fa-eye");
        icon.classList.add("fa-eye-slash");
      } else {
        input.type = "password";
        icon.classList.remove("fa-eye-slash");
        icon.classList.add("fa-eye");
      }
    });
  });
}

/**
 * Show Success Notification
 */
function showSuccess(message) {
  if (typeof showNotification === "function") {
    showNotification("success", message);
  }
}

/**
 * Show Error Notification
 */
function showError(message) {
  if (typeof showNotification === "function") {
    showNotification("error", message);
  }
}

/**
 * Show Error Popup
 */
function showErrorPopup(message) {
  const popup = document.getElementById("errorPopup");
  const messageEl = document.getElementById("errorMessage");

  if (popup && messageEl) {
    messageEl.textContent = message;
    popup.classList.add("active");
  }
}

/**
 * Hide Error Popup
 */
function hideErrorPopup() {
  const popup = document.getElementById("errorPopup");
  if (popup) {
    popup.classList.remove("active");
  }
}

/**
 * Email Validation
 */
function isValidEmail(email) {
  const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return regex.test(email);
}

/**
 * Phone Number Validation
 */
function isValidPhone(phone) {
  const regex = /^[\d\s\-\+\(\)]+$/;
  return regex.test(phone) && phone.replace(/\D/g, "").length >= 10;
}

// Log script initialization
console.log("Admin Profile script loaded successfully");
