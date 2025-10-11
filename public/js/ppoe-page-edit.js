// ppoe-page-edit.js — FIXED

document.addEventListener("DOMContentLoaded", function () {
  // Elements
  const editModal = document.getElementById("formEditModal");
  const editForm = document.getElementById("FormEdit");
  const editTypeSelect = document.getElementById("edit-typeUserSelect");
  const editTypeHidden = document.getElementById("edit-typeUserHidden");
  const editRowPppoe = document.getElementById("edit-rowPppoeCreds");
  const editRowMac = document.getElementById("edit-rowMac");
  const editUsernameInput = document.getElementById("edit-username");
  const editPasswordInput = document.getElementById("edit-password");
  const editMacInput = document.getElementById("edit-macAddress");
  const profileSelect = document.getElementById("edit-profileSelect");
  const areaSelect = document.getElementById("edit-area");
  const odpSelect = document.getElementById("edit-odp");

  // ===========================================
  // MODAL MANAGEMENT FUNCTIONS
  // ===========================================
  function forceCleanupModal() {
    // Remove all modal backdrops
    document.querySelectorAll(".modal-backdrop").forEach((b) => b.remove());

    // Reset body
    document.body.classList.remove("modal-open");
    document.body.style.overflow = "";
    document.body.style.paddingRight = "";

    // Hide all visible modals
    document.querySelectorAll(".modal.show").forEach((m) => {
      const bsModal = bootstrap.Modal.getInstance(m);
      if (bsModal) bsModal.dispose();
      m.classList.remove("show");
      m.style.display = "none";
      m.setAttribute("aria-hidden", "true");
      m.removeAttribute("aria-modal");
    });
  }

  // ===========================================
  // CONNECTION TYPE VISIBILITY
  // ===========================================
  function setEditVisibilityForType(type, preserveValues = false) {
    if (!type) {
      type = (editTypeHidden ? editTypeHidden.value : "pppoe").toLowerCase();
    }

    if (type === "pppoe") {
      if (editRowPppoe) editRowPppoe.classList.remove("d-none");
      if (editRowMac) editRowMac.classList.add("d-none");

      if (editUsernameInput) {
        editUsernameInput.disabled = false;
        editUsernameInput.required = true;
      }
      if (editPasswordInput) {
        editPasswordInput.disabled = false;
        editPasswordInput.required = true;
      }
      if (editMacInput) {
        editMacInput.disabled = true;
        editMacInput.required = false;
        editMacInput.classList.remove("is-invalid");
        if (!preserveValues) editMacInput.value = "";
      }
    } else if (type === "dhcp") {
      if (editRowMac) editRowMac.classList.remove("d-none");
      if (editRowPppoe) editRowPppoe.classList.add("d-none");

      if (editMacInput) {
        editMacInput.disabled = false;
        editMacInput.required = true;
      }
      if (editUsernameInput) {
        editUsernameInput.disabled = true;
        editUsernameInput.required = false;
        editUsernameInput.classList.remove("is-invalid");
        if (!preserveValues) editUsernameInput.value = "";
      }
      if (editPasswordInput) {
        editPasswordInput.disabled = true;
        editPasswordInput.required = false;
        editPasswordInput.classList.remove("is-invalid");
        if (!preserveValues) editPasswordInput.value = "";
      }
    }

    if (editTypeHidden) editTypeHidden.value = type;
  }

  // expose
  window.setEditVisibilityForType = setEditVisibilityForType;

  // ===========================================
  // AREA ↔ ODP FILTERING (single source of truth)
  // ===========================================
  function resetOdpOptions() {
    if (!odpSelect) return;

    // show/enable everything
    odpSelect.querySelectorAll("option").forEach((opt) => {
      opt.style.display = "";
      opt.disabled = false;
    });

    // reset selection & placeholder
    odpSelect.value = "";
    const def = odpSelect.querySelector('option[value=""]');
    if (def) def.textContent = "Select Area First";
  }

  function filterOdpByArea(selectedAreaId, selectOdpValue = null) {
    if (!odpSelect) return;

    // always start from a clean state
    odpSelect.querySelectorAll("option").forEach((opt) => {
      opt.style.display = "";
      opt.disabled = false;
    });

    const odpOptions = odpSelect.querySelectorAll("option[data-area]");

    // reset current selection
    odpSelect.value = "";

    let visible = 0;

    odpOptions.forEach((opt) => {
      const match =
        selectedAreaId && String(opt.dataset.area) === String(selectedAreaId);
      if (match) {
        opt.style.display = "";
        opt.disabled = false;
        visible++;
      } else {
        opt.style.display = "none";
        opt.disabled = true;
      }
    });

    // placeholder text
    const def = odpSelect.querySelector('option[value=""]');
    if (def) {
      if (!selectedAreaId) def.textContent = "Select Area First";
      else if (visible > 0) def.textContent = "Select ODP";
      else def.textContent = "No ODP available for this area";
    }

    // preselect provided ODP (if visible)
    if (selectedAreaId && selectOdpValue) {
      setTimeout(() => {
        const target = odpSelect.querySelector(
          `option[value="${selectOdpValue}"]`
        );
        if (target && target.style.display !== "none" && !target.disabled) {
          odpSelect.value = selectOdpValue;
        } else {
          console.warn("ODP option not found/visible for this area:", selectOdpValue);
        }
      }, 50);
    }
  }

  // expose
  window.filterOdpByArea = filterOdpByArea;

  // change listener
  if (areaSelect && odpSelect) {
    areaSelect.addEventListener("change", function () {
      window.filterOdpByArea(this.value);
    });
  }

  // ===========================================
  // MAC ADDRESS MASK
  // ===========================================
  if (editMacInput) {
    editMacInput.addEventListener("input", function (e) {
      let value = e.target.value.replace(/[^a-fA-F0-9]/g, "");
      let formatted = value.match(/.{1,2}/g)?.join(":") || value;
      if (formatted.length > 17) formatted = formatted.substring(0, 17);
      e.target.value = formatted.toLowerCase();
    });
  }

  // ===========================================
  // VALIDATION
  // ===========================================
  function validateEditForm() {
    const errors = [];
    document.querySelectorAll(".is-invalid").forEach((el) => {
      el.classList.remove("is-invalid");
    });

    const selectedType = (editTypeHidden ? editTypeHidden.value : "pppoe").toLowerCase();

    if (selectedType === "dhcp") {
      if (!editMacInput || !editMacInput.value.trim()) {
        errors.push("MAC Address harus diisi untuk tipe DHCP");
        if (editMacInput) editMacInput.classList.add("is-invalid");
      } else {
        const macRegex = /^[0-9A-Fa-f]{2}(:[0-9A-Fa-f]{2}){5}$/;
        if (!macRegex.test(editMacInput.value)) {
          errors.push("Format MAC Address tidak valid");
          editMacInput.classList.add("is-invalid");
        }
      }
    } else {
      if (!editUsernameInput || !editUsernameInput.value.trim()) {
        errors.push("Username harus diisi");
        if (editUsernameInput) editUsernameInput.classList.add("is-invalid");
      } else if (editUsernameInput.value.length < 3) {
        errors.push("Username minimal 3 karakter");
        if (editUsernameInput) editUsernameInput.classList.add("is-invalid");
      }

      if (!editPasswordInput || !editPasswordInput.value.trim()) {
        errors.push("Password harus diisi");
        if (editPasswordInput) editPasswordInput.classList.add("is-invalid");
      }
    }

    if (!profileSelect || !profileSelect.value) {
      errors.push("Profile harus dipilih");
      if (profileSelect) profileSelect.classList.add("is-invalid");
    }

    return errors;
  }

  function showValidationErrors(errors) {
    document.querySelectorAll(".validation-error-alert").forEach((a) => a.remove());

    if (errors.length) {
      const alertDiv = document.createElement("div");
      alertDiv.className =
        "alert alert-danger alert-dismissible fade show validation-error-alert";
      alertDiv.innerHTML = `
        <strong>Mohon perbaiki kesalahan berikut:</strong>
        <ul class="mb-0 mt-2">
          ${errors.map((e) => `<li>${e}</li>`).join("")}
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      `;
      const modalBody = editModal.querySelector(".modal-body");
      modalBody.insertBefore(alertDiv, modalBody.firstChild);
      setTimeout(() => alertDiv.remove(), 10000);
    }
  }

  // ===========================================
  // MODAL EVENTS
  // ===========================================
  if (editModal) {
    editModal.addEventListener("show.bs.modal", () => {
      forceCleanupModal();
    });

    editModal.addEventListener("shown.bs.modal", () => {
      const first = editModal.querySelector('input:not([type="hidden"]):not([disabled])');
      if (first) first.focus();
    });

    editModal.addEventListener("hide.bs.modal", () => {
      editModal.querySelectorAll(".is-invalid").forEach((f) => f.classList.remove("is-invalid"));
      editModal.querySelectorAll(".validation-error-alert").forEach((a) => a.remove());
    });

    editModal.addEventListener("hidden.bs.modal", () => {
      setTimeout(() => {
        forceCleanupModal();
        if (editForm) editForm.reset();
        setEditVisibilityForType("pppoe");
        resetOdpOptions();
      }, 100);
    });

    // explicit close buttons
    editModal.querySelectorAll('[data-bs-dismiss="modal"], .btn-close').forEach((btn) => {
      btn.addEventListener("click", (e) => {
        e.preventDefault();
        e.stopPropagation();
        try {
          const bsModal = bootstrap.Modal.getInstance(editModal) || new bootstrap.Modal(editModal);
          bsModal.hide();
        } catch {
          forceCleanupModal();
        }
      });
    });

    // backdrop click closes
    editModal.addEventListener("click", (e) => {
      if (e.target === editModal) {
        e.preventDefault();
        e.stopPropagation();
        try {
          const bsModal = bootstrap.Modal.getInstance(editModal);
          if (bsModal) bsModal.hide();
        } catch {
          forceCleanupModal();
        }
      }
    });

    // ESC key closes
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape" && editModal.classList.contains("show")) {
        e.preventDefault();
        try {
          const bsModal = bootstrap.Modal.getInstance(editModal);
          if (bsModal) bsModal.hide();
        } catch {
          forceCleanupModal();
        }
      }
    });
  }

  // ===========================================
  // FORM SUBMIT
  // ===========================================
  if (editForm) {
    editForm.addEventListener("submit", function (e) {
      e.preventDefault();

      const errors = validateEditForm();
      if (errors.length) {
        showValidationErrors(errors);
        editModal.querySelector(".modal-body").scrollTop = 0;
        return;
      }

      const selectedType = (editTypeHidden ? editTypeHidden.value : "pppoe").toLowerCase();

      // enable everything temporarily for FormData
      const allInputs = this.querySelectorAll("input, select");
      const originalStates = [];
      allInputs.forEach((el) => {
        originalStates.push({ element: el, disabled: el.disabled });
        if (el !== editTypeSelect) el.disabled = false;
      });

      // clear unused fields
      if (selectedType === "dhcp") {
        if (editUsernameInput) editUsernameInput.value = "";
        if (editPasswordInput) editPasswordInput.value = "";
      } else {
        if (editMacInput) editMacInput.value = "";
      }

      const formData = new FormData(this);

      // restore disabled states
      originalStates.forEach((s) => (s.element.disabled = s.disabled));

      const submitBtn = this.querySelector('button[type="submit"]');
      const origHtml = submitBtn.innerHTML;
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Updating...';

      const csrfMeta = document.querySelector('meta[name="csrf-token"]');
      const token = csrfMeta ? csrfMeta.getAttribute("content") : "";
      if (!token) {
        alert("CSRF token not found. Please refresh the page.");
        submitBtn.disabled = false;
        submitBtn.innerHTML = origHtml;
        return;
      }

      const actionUrl = this.getAttribute("action");

      fetch(actionUrl, {
        method: "POST",
        body: formData,
        headers: { "X-CSRF-TOKEN": token, Accept: "application/json" }
      })
        .then((response) =>
          response.text().then((t) => {
            try {
              return JSON.parse(t);
            } catch {
              throw new Error("Invalid JSON response: " + t.substring(0, 200));
            }
          })
        )
        .then((data) => {
          if (data.success) {
            alert(data.message || "Connection updated successfully!");
            try {
              const bsModal = bootstrap.Modal.getInstance(editModal);
              if (bsModal) bsModal.hide();
              else forceCleanupModal();
            } catch {
              forceCleanupModal();
            }

            if (typeof table !== "undefined" && table.ajax) table.ajax.reload();
            else window.location.reload();
          }
        })
        .catch((err) => {
          console.error("Submit error:", err);
          alert("An error occurred: " + (err.message || err));
        })
        .finally(() => {
          submitBtn.disabled = false;
          submitBtn.innerHTML = origHtml;
        });
    });
  }

  // remove field error class on interaction
  document.addEventListener("input", (e) => {
    if (e.target.classList.contains("is-invalid")) e.target.classList.remove("is-invalid");
  });
  document.addEventListener("change", (e) => {
    if (e.target.classList.contains("is-invalid")) e.target.classList.remove("is-invalid");
  });
});

// ===========================================
// GLOBAL HELPERS (outside DOMContentLoaded)
// ===========================================
function forceCleanupModal() {
  document.querySelectorAll(".modal-backdrop").forEach((b) => b.remove());
  document.body.classList.remove("modal-open");
  document.body.style.overflow = "";
  document.body.style.paddingRight = "";
  document.querySelectorAll(".modal.show").forEach((m) => {
    const bsModal = bootstrap.Modal.getInstance(m);
    if (bsModal) bsModal.dispose();
    m.classList.remove("show");
    m.style.display = "none";
    m.setAttribute("aria-hidden", "true");
    m.removeAttribute("aria-modal");
  });
}

// Fill modal with data and show it
function populateEditForm(data) {
  const form = document.getElementById("FormEdit");
  if (!form) {
    console.error("Edit form not found");
    return;
  }

  try {
    // action URL
    if (data.update_url) form.setAttribute("action", data.update_url);
    else if (data.id) form.setAttribute("action", `${window.location.origin}/pppoe/${data.id}`);

    // type
    const typeSel = document.getElementById("edit-typeUserSelect");
    const typeHid = document.getElementById("edit-typeUserHidden");
    const connType = (data.type || "pppoe").toLowerCase();
    if (typeSel) typeSel.value = connType;
    if (typeHid) typeHid.value = connType;

    // creds
    if (connType === "dhcp") {
      const mac = document.getElementById("edit-macAddress");
      if (mac && data.mac_address) mac.value = data.mac_address;
    } else {
      const u = document.getElementById("edit-username");
      const p = document.getElementById("edit-password");
      if (u && data.username) u.value = data.username;
      if (p && data.password) p.value = data.password;
    }

    // profile
    const prof = document.getElementById("edit-profileSelect");
    if (prof && data.profile_id) prof.value = data.profile_id;

    // area/odp sequence
    const area = document.getElementById("edit-area");
    const odp = document.getElementById("edit-odp");

    if (odp) {
      odp.querySelectorAll("option").forEach((opt) => {
        opt.style.display = "";
        opt.disabled = false;
      });
    }

    if (area && data.area_id) {
      area.value = data.area_id;
      setTimeout(() => {
        if (typeof window.filterOdpByArea === "function") {
          if (data.optical_id) window.filterOdpByArea(data.area_id, data.optical_id);
          else window.filterOdpByArea(data.area_id);
        }
      }, 100);
    } else if (odp) {
      const def = odp.querySelector('option[value=""]');
      if (def) def.textContent = "Select Area First";
      odp.value = "";
    }

    // visibility after values are set
    setTimeout(() => {
      if (typeof window.setEditVisibilityForType === "function") {
        window.setEditVisibilityForType(connType, true);
      }
    }, 100);
  } catch (err) {
    console.error("Error populating edit form:", err);
    alert("Error loading data: " + err.message);
  }
}

function editConnection(data) {
  forceCleanupModal();
  setTimeout(() => {
    try {
      populateEditForm(data);
      const modalEl = document.getElementById("formEditModal");
      if (modalEl) {
        new bootstrap.Modal(modalEl, { backdrop: true, keyboard: true, focus: true }).show();
      } else {
        console.error("Edit modal not found");
      }
    } catch (err) {
      console.error("Error opening edit modal:", err);
      alert("Error opening edit form: " + err.message);
    }
  }, 100);
}

// jQuery compatibility for data attributes
$(document).ready(function () {
  $(document).on("click", "#btn-edit", function (e) {
    if (this.hasAttribute("onclick")) return;

    e.preventDefault();
    const btn = $(this);
    const data = {
      id: btn.data("id"),
      type: btn.data("type") || "pppoe",
      username: btn.data("username") || "",
      password: btn.data("password") || "",
      mac_address: btn.data("mac_address") || "",
      profile_id: btn.data("profile"),
      area_id: btn.data("area"),
      optical_id: btn.data("optical"),
      update_url: btn.data("update-url")
    };
    editConnection(data);
  });

  $(window).on("beforeunload", function () {
    forceCleanupModal();
  });
});

// Emergency cleanup helper
window.emergencyCleanup = function () {
  forceCleanupModal();
  setTimeout(() => {
    $("body").removeClass("modal-open").css({ overflow: "", "padding-right": "" });
    $(".modal-backdrop").remove();
    $(".modal").removeClass("show").hide();
  }, 50);
};

// Debug helpers
window.debugOdpState = function () {
  const odp = document.getElementById("edit-odp");
  if (odp) {
    // intentionally minimal to inspect in console
    const visible = [...odp.querySelectorAll("option[data-area]")].filter(
      (o) => o.style.display !== "none" && !o.disabled
    );
    console.log("Visible ODP options:", visible.map((o) => ({ v: o.value, a: o.dataset.area, t: o.textContent })));
  }
};

window.testFilterOdp = function (areaId, odpId = null) {
  if (typeof window.filterOdpByArea === "function") {
    window.filterOdpByArea(areaId, odpId);
  }
  setTimeout(() => window.debugOdpState(), 200);
};
