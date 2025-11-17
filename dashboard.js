/**
 * FURY OF SPARTA - DASHBOARD JAVASCRIPT
 * Handles all dashboard interactions
 */

let currentExtendKey = "";
let isProcessing = false;

// Tab switching with URL update
function showTab(tabName) {
  // Update URL without reloading the page
  const url = new URL(window.location);
  url.searchParams.set("tab", tabName);
  window.history.replaceState({}, "", url);

  // Hide all tab contents
  document.querySelectorAll(".tab-content").forEach((content) => {
    content.classList.remove("active");
  });

  // Remove active class from all tabs
  document.querySelectorAll(".tab").forEach((tab) => {
    tab.classList.remove("active");
  });

  // Show selected tab content
  document.getElementById(tabName).classList.add("active");

  // Add active class to clicked tab
  event.target.classList.add("active");
}

// Edit license function - CORRECTED
function editLicense(key) {
  if (isProcessing) return;

  const licenses = window.licensesData;
  const license = licenses[key];

  if (!license) {
    alert("❌ License not found!");
    return;
  }

  // Populate modal form with existing data
  document.getElementById("edit_license_key").value = key;
  document.getElementById("edit_status").value = license.status;
  document.getElementById("edit_expires").value = license.expires;
  document.getElementById("edit_max_machines").value = license.max_machines;
  document.getElementById("edit_client_info").value = license.client_info || "";

  // Show modal
  document.getElementById("editModal").style.display = "block";
}

// Toggle license status (AJAX) - CORRECTED
async function toggleStatus(key) {
  if (isProcessing) return;

  const btn = event.target;
  const originalText = btn.innerHTML;

  // Prevent double clicks
  isProcessing = true;
  btn.innerHTML = '<span class="loading"></span> Processing...';
  btn.disabled = true;

  try {
    const formData = new FormData();
    formData.append("ajax_action", "toggle_status");
    formData.append("license_key", key);

    const response = await fetch(window.location.href, {
      method: "POST",
      body: formData,
    });

    const result = await response.json();

    if (result.success) {
      btn.innerHTML = "✅ Status Updated!";
      btn.style.background = "#28a745";

      // Reload page after brief delay
      setTimeout(() => {
        window.location.reload();
      }, 1500);
    } else {
      alert("❌ Error: " + (result.message || "Unknown error"));
      btn.innerHTML = originalText;
      btn.disabled = false;
      isProcessing = false;
    }
  } catch (error) {
    console.error("Toggle status error:", error);
    alert("❌ Network error occurred");
    btn.innerHTML = originalText;
    btn.disabled = false;
    isProcessing = false;
  }
}

// Extend license function - CORRECTED
function extendLicense(key) {
  if (isProcessing) return;

  const licenses = window.licensesData;
  const license = licenses[key];

  if (!license) {
    alert("❌ License not found!");
    return;
  }

  currentExtendKey = key;

  // Populate extend modal
  document.getElementById("extend_license_info").innerHTML = `
        <strong>License:</strong> ${key}<br>
        <strong>Current Expiry:</strong> ${license.expires}<br>
        <strong>Client:</strong> ${license.client_info || "No info"}
    `;

  // Show extend modal
  document.getElementById("extendModal").style.display = "block";
}

// Confirm extend - CORRECTED
async function confirmExtend() {
  if (isProcessing || !currentExtendKey) return;

  const months = document.getElementById("extend_months").value;
  isProcessing = true;

  try {
    const formData = new FormData();
    formData.append("ajax_action", "extend_license");
    formData.append("license_key", currentExtendKey);
    formData.append("months", months);

    const response = await fetch(window.location.href, {
      method: "POST",
      body: formData,
    });

    const result = await response.json();

    if (result.success) {
      alert(
        `✅ License extended successfully!\nNew expiry date: ${result.new_expiry}`
      );
      closeModal("extendModal");
      window.location.reload();
    } else {
      alert("❌ Error: " + (result.message || "Unknown error"));
    }
  } catch (error) {
    console.error("Extend license error:", error);
    alert("❌ Network error occurred");
  } finally {
    isProcessing = false;
  }
}

// Delete license function - CORRECTED
function confirmDeleteLicense(key) {
  if (isProcessing) return;

  const licenses = window.licensesData;
  const license = licenses[key];

  if (!license) {
    alert("❌ License not found!");
    return;
  }

  const clientInfo = license.client_info || "No client info";

  if (
    confirm(
      `⚠️ Are you sure you want to permanently delete this license?\n\nLicense: ${key}\nClient: ${clientInfo}\n\nThis action cannot be undone!`
    )
  ) {
    // Set the license key to delete
    document.getElementById("delete_license_key").value = key;

    // Prevent double submission
    isProcessing = true;

    // Submit the delete form
    document.getElementById("deleteForm").submit();
  }
}

// Modal management functions
function closeModal(modalId) {
  document.getElementById(modalId).style.display = "none";
  currentExtendKey = "";
  isProcessing = false;
}

function closeModalOnOutsideClick(event, modalId) {
  if (event.target.id === modalId) {
    closeModal(modalId);
  }
}

// Form validation functions
function validateAddForm(form) {
  if (isProcessing) return false;

  isProcessing = true;
  const submitBtn = form.querySelector('button[type="submit"]');
  submitBtn.disabled = true;
  submitBtn.innerHTML = "🔄 Creating License...";

  return true;
}

function validateEditForm(form) {
  if (isProcessing) return false;

  isProcessing = true;
  const submitBtn = form.querySelector('button[type="submit"]');
  submitBtn.disabled = true;
  submitBtn.innerHTML = "🔄 Saving Changes...";

  return true;
}

// Event listeners and initialization
document.addEventListener("DOMContentLoaded", function () {
  isProcessing = false;

  // Keyboard shortcuts
  document.addEventListener("keydown", function (e) {
    // ESC key closes modals
    if (e.key === "Escape") {
      document.querySelectorAll(".modal").forEach((modal) => {
        modal.style.display = "none";
      });
      currentExtendKey = "";
      isProcessing = false;
    }

    // Ctrl/Cmd + N for new license
    if ((e.ctrlKey || e.metaKey) && e.key === "n") {
      e.preventDefault();
      showTab("add");
    }
  });

  // Auto-fade success/error messages after 5 seconds
  setTimeout(function () {
    const alerts = document.querySelectorAll(".alert");
    alerts.forEach((alert) => {
      alert.style.transition = "opacity 0.5s ease-out";
      alert.style.opacity = "0.7";
    });
  }, 5000);

  // Auto-hide messages after 10 seconds
  setTimeout(function () {
    const alerts = document.querySelectorAll(".alert");
    alerts.forEach((alert) => {
      alert.style.display = "none";
    });
  }, 10000);
});

// Handle page show event (back button)
window.addEventListener("pageshow", function (event) {
  if (event.persisted) {
    isProcessing = false;
    // Re-enable any disabled buttons
    document.querySelectorAll("button[disabled]").forEach((btn) => {
      btn.disabled = false;
    });
    // Reset button text if needed
    document.querySelectorAll(".btn").forEach((btn) => {
      if (
        btn.innerHTML.includes("Processing") ||
        btn.innerHTML.includes("Creating") ||
        btn.innerHTML.includes("Saving")
      ) {
        btn.innerHTML = btn.getAttribute("data-original-text") || btn.innerHTML;
      }
    });
  }
});

// Prevent form resubmission on page reload
if (window.history.replaceState) {
  window.history.replaceState(null, null, window.location.href);
}

// Utility function to show loading state
function showLoadingState(button, text = "Processing...") {
  if (!button.hasAttribute("data-original-text")) {
    button.setAttribute("data-original-text", button.innerHTML);
  }
  button.disabled = true;
  button.innerHTML = `<span class="loading"></span> ${text}`;
}

// Utility function to restore button state
function restoreButtonState(button) {
  button.disabled = false;
  const originalText = button.getAttribute("data-original-text");
  if (originalText) {
    button.innerHTML = originalText;
  }
}
