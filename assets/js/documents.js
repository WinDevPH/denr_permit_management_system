var uploadForm = document.getElementById("uploadDocumentForm");
if (uploadForm) {
  uploadForm.addEventListener("submit", function (e) {
    e.preventDefault();
    var fileInput = this.querySelector('input[name="document"]');
    if (fileInput && fileInput.files.length) {
      var file = fileInput.files[0];
      var allowed = ["application/pdf", "application/msword", "application/vnd.openxmlformats-officedocument.wordprocessingml.document"];
      if (allowed.indexOf(file.type) === -1) {
        showNotification("error", "Invalid file type. Only PDF, DOC, and DOCX are allowed.");
        return;
      }
      if (file.size > 5 * 1024 * 1024) {
        showNotification("error", "File size must be less than 5MB.");
        return;
      }
    }
    const formData = new FormData(this);
    const loadingOverlay = document.querySelector(".loading-overlay");
    let progress = 0;

    // Show loading overlay
    loadingOverlay.style.display = "flex";

    // Simulate progress
    const progressInterval = setInterval(() => {
      progress += Math.random() * 30;
      if (progress > 90) clearInterval(progressInterval);
      document.querySelector(".loading-percentage").textContent =
        Math.min(Math.round(progress), 90) + "%";
    }, 500);

    fetch("../../../handlers/upload_document.php", {
      method: "POST",
      body: formData,
    })
      .then((response) => response.json())
      .then((data) => {
        clearInterval(progressInterval);
        document.querySelector(".loading-percentage").textContent = "100%";

        setTimeout(() => {
          loadingOverlay.style.display = "none";

          if (data.status === "success") {
            showNotification("success", "Document uploaded successfully");
            setTimeout(() => location.reload(), 1500);
          } else {
            throw new Error(data.message || "Failed to upload document");
          }
        }, 500);
      })
      .catch((error) => {
        clearInterval(progressInterval);
        loadingOverlay.style.display = "none";
        showNotification("error", error.message || "Error uploading document");
      });
  });
}

// View document function
function viewDocument(filePath, documentName) {
  const viewerUrl = `../../../handlers/view_document.php?file=${encodeURIComponent(
    filePath
  )}&name=${encodeURIComponent(documentName)}`;
  window.open(viewerUrl, "_blank");
}

// Delete document function
function deleteDocument(docId) {
  if (confirm("Are you sure you want to delete this document?")) {
    const loadingOverlay = document.querySelector(".loading-overlay");
    loadingOverlay.style.display = "flex";

    fetch("../../../handlers/delete_document.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({ doc_id: docId }),
    })
      .then((response) => response.json())
      .then((data) => {
        loadingOverlay.style.display = "none";
        if (data.status === "success") {
          showNotification("success", "Document deleted successfully");
          setTimeout(() => location.reload(), 1500);
        } else {
          throw new Error(data.message || "Failed to delete document");
        }
      })
      .catch((error) => {
        loadingOverlay.style.display = "none";
        showNotification("error", error.message || "Error deleting document");
      });
  }
}

