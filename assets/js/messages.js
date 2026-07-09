function denrNotifyError(message) {
  if (typeof showNotification === "function") {
    showNotification("error", message);
    return;
  }
  window.alert(message);
}

document.addEventListener("DOMContentLoaded", function () {
  const searchInput = document.getElementById("searchConversations");
  if (searchInput) {
    searchInput.addEventListener("input", searchConversations);
  }

  // Attach form submit handler
  const messageForm = document.getElementById("messageForm");
  if (messageForm) {
    messageForm.addEventListener("submit", handleMessageFormSubmit);
    messageForm.setAttribute("data-handler-attached", "true");
  } else {
    // If form doesn't exist yet, try again after a short delay
    setTimeout(function () {
      const messageFormRetry = document.getElementById("messageForm");
      if (messageFormRetry) {
        messageFormRetry.addEventListener("submit", handleMessageFormSubmit);
        messageFormRetry.setAttribute("data-handler-attached", "true");
      }
    }, 500);
  }

  // Also use event delegation as a fallback (only if direct attachment failed)
  document.addEventListener(
    "submit",
    function (e) {
      if (
        e.target &&
        e.target.id === "messageForm" &&
        !e.target.hasAttribute("data-handler-attached")
      ) {
        handleMessageFormSubmit(e);
      }
    },
    true
  );

  // Scroll message thread to bottom on load
  const messagesList = document.getElementById("messagesList");
  if (messagesList) {
    messagesList.scrollTop = messagesList.scrollHeight;
  }

  // Auto-refresh messages every 3 seconds
  if (messagesList) {
    setInterval(function () {
      const conversationId = document.querySelector(
        'input[name="conversation_id"]'
      );
      if (conversationId && conversationId.value) {
        fetch(window.location.href)
          .then((response) => response.text())
          .then((html) => {
            const parser = new DOMParser();
            const newDoc = parser.parseFromString(html, "text/html");
            const newMessages = newDoc.querySelector("#messagesList");
            if (
              newMessages &&
              newMessages.innerHTML !== messagesList.innerHTML
            ) {
              messagesList.innerHTML = newMessages.innerHTML;
              messagesList.scrollTop = messagesList.scrollHeight;
            }
          })
          .catch((error) => console.error("Auto-refresh error:", error));
      }
    }, 3000);
  }
});

function searchConversations() {
  const searchTerm = document
    .getElementById("searchConversations")
    .value.toLowerCase().trim();
  const conversationItems = document.querySelectorAll(
    ".msg-conv-item, .msg-empty-state"
  );

  conversationItems.forEach((item) => {
    if (item.classList.contains("msg-empty-state")) {
      item.style.display = searchTerm ? "none" : "";
      return;
    }
    const name = item.querySelector(".msg-conv-name");
    const preview = item.querySelector(".msg-conv-preview");
    if (!name && !preview) return;

    const nameText = (name ? name.textContent : "").toLowerCase();
    const previewText = (preview ? preview.textContent : "").toLowerCase();

    if (nameText.includes(searchTerm) || previewText.includes(searchTerm)) {
      item.style.display = "";
    } else {
      item.style.display = "none";
    }
  });
}

function startConversation(userId) {
  fetch("../../../handlers/start_conversation.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded",
    },
    body: "other_user_id=" + userId,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        window.location.href = "?conversation=" + data.conversation_id;
      } else {
        denrNotifyError(data.message || "Could not start conversation");
      }
    })
    .catch((error) => console.error("Error:", error));
}

// Handle form submission with proper error handling
// Version: 2.0 - Updated for msg-page- classes
function handleMessageFormSubmit(e) {
  try {
    if (e && e.preventDefault) {
      e.preventDefault();
    }

    // Get form element - try multiple ways
    const form =
      (e && e.target) ||
      (e && e.currentTarget) ||
      document.getElementById("messageForm");

    console.log("handleMessageFormSubmit - Form found:", form);

    if (!form) {
      console.error("Form not found");
      denrNotifyError("Form not found. Please refresh the page.");
      return;
    }

    // Try multiple selectors for conversation ID input
    const conversationIdInput =
      form.querySelector('input[name="conversation_id"]') ||
      form.querySelector('input[type="hidden"][name="conversation_id"]');

    // Try multiple selectors for textarea
    const messageTextarea =
      form.querySelector(".msg-composer-input") ||
      form.querySelector(".msg-page-textarea") ||
      form.querySelector('textarea[name="message_text"]') ||
      form.querySelector("textarea");

    console.log("Conversation ID input:", conversationIdInput);
    console.log("Message textarea:", messageTextarea);

    if (!conversationIdInput) {
      console.error(
        "Conversation ID input not found. Form HTML:",
        form.innerHTML
      );
      denrNotifyError("No conversation selected");
      return;
    }

    if (!messageTextarea) {
      console.error("Message textarea not found. Form HTML:", form.innerHTML);
      console.error("Available textareas:", form.querySelectorAll("textarea"));
      denrNotifyError("Message input not found. Please refresh the page.");
      return;
    }

    // Safely get values with additional null checks
    const conversationId =
      conversationIdInput && conversationIdInput.value
        ? conversationIdInput.value
        : null;
    const messageText =
      messageTextarea && messageTextarea.value
        ? messageTextarea.value.trim()
        : "";
    const submitBtn = form.querySelector('button[type="submit"]');

    console.log("Conversation ID:", conversationId);
    console.log("Message Text:", messageText);

    // Double-check we have valid values
    if (
      conversationId === null ||
      conversationId === undefined ||
      conversationId === ""
    ) {
      console.error("Conversation ID is null/undefined/empty");
      denrNotifyError("No conversation selected");
      return;
    }

    // Validation
    if (!messageText) {
      denrNotifyError("Please type a message");
      return;
    }

    // Disable submit button
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    }

    const formData = new FormData();
    formData.append("conversation_id", conversationId);
    formData.append("message_text", messageText);

    fetch("../../../handlers/send_message.php", {
      method: "POST",
      body: formData,
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error("Network response was not ok");
        }
        return response.json();
      })
      .then((data) => {
        if (data.success) {
          // Clear textarea using multiple selectors
          const textarea =
            form.querySelector(".msg-composer-input") ||
            form.querySelector(".msg-page-textarea") ||
            form.querySelector('textarea[name="message_text"]') ||
            form.querySelector("textarea");
          if (textarea) {
            textarea.value = "";
          }
          // Auto-refresh to show new message with proper alignment
          setTimeout(() => {
            location.reload();
          }, 300);
        } else {
          denrNotifyError("Error sending message: " + (data.message || "Unknown error"));
          if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i>';
          }
        }
      })
      .catch((error) => {
        console.error("Error:", error);
        denrNotifyError("Error sending message: " + error.message);
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i>';
        }
      });
  } catch (error) {
    console.error("Fatal error in handleMessageFormSubmit:", error);
    console.error("Error stack:", error.stack);
    denrNotifyError("An error occurred. Please refresh the page and try again.");
  }
}
