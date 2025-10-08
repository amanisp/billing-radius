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
        const backdrops = document.querySelectorAll(".modal-backdrop");
        backdrops.forEach((backdrop) => {
            backdrop.remove();
        });

        // Reset body
        document.body.classList.remove("modal-open");
        document.body.style.overflow = "";
        document.body.style.paddingRight = "";

        // Hide all modals
        const modals = document.querySelectorAll(".modal.show");
        modals.forEach((modal) => {
            const bsModal = bootstrap.Modal.getInstance(modal);
            if (bsModal) {
                bsModal.dispose();
            }
            modal.classList.remove("show");
            modal.style.display = "none";
            modal.setAttribute("aria-hidden", "true");
            modal.removeAttribute("aria-modal");
        });
    }

    // ===========================================
    // CONNECTION TYPE VISIBILITY MANAGEMENT
    // ===========================================

    function setEditVisibilityForType(type, preserveValues = false) {
        if (!type) {
            type = (
                editTypeHidden ? editTypeHidden.value : "pppoe"
            ).toLowerCase();
        }

        if (type === "pppoe") {
            // Show PPPoE fields, hide MAC
            if (editRowPppoe) editRowPppoe.classList.remove("d-none");
            if (editRowMac) editRowMac.classList.add("d-none");

            // Enable/disable fields
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
                if (!preserveValues) {
                    editMacInput.value = "";
                }
            }
        } else if (type === "dhcp") {
            // Show MAC field, hide PPPoE
            if (editRowMac) editRowMac.classList.remove("d-none");
            if (editRowPppoe) editRowPppoe.classList.add("d-none");

            // Enable/disable fields
            if (editMacInput) {
                editMacInput.disabled = false;
                editMacInput.required = true;
            }
            if (editUsernameInput) {
                editUsernameInput.disabled = true;
                editUsernameInput.required = false;
                editUsernameInput.classList.remove("is-invalid");
                if (!preserveValues) {
                    editUsernameInput.value = "";
                }
            }
            if (editPasswordInput) {
                editPasswordInput.disabled = true;
                editPasswordInput.required = false;
                editPasswordInput.classList.remove("is-invalid");
                if (!preserveValues) {
                    editPasswordInput.value = "";
                }
            }
        }

        // Set hidden field value for form submission
        if (editTypeHidden) {
            editTypeHidden.value = type;
        }
    }

    // Make function globally available
    window.setEditVisibilityForType = setEditVisibilityForType;

    // ===========================================
    // AREA AND ODP FILTERING - FIXED VERSION
    // ===========================================

    function resetOdpOptions() {
        if (!odpSelect) return;

        // Reset all options to be visible and enabled (initial state)
        const allOdpOptions = odpSelect.querySelectorAll("option");
        allOdpOptions.forEach((option) => {
            option.style.display = "";
            option.disabled = false;
        });

        // Reset selection
        odpSelect.value = "";

        // Reset default option text
        const defaultOption = odpSelect.querySelector('option[value=""]');
        if (defaultOption) {
            defaultOption.textContent = "Select Area First";
        }
    }

    function filterOdpByArea(selectedAreaId, selectOdpValue = null) {
        if (!odpSelect) return;

        // IMPORTANT: First reset all options to visible state
        const allOdpOptions = odpSelect.querySelectorAll("option");
        allOdpOptions.forEach((option) => {
            option.style.display = "";
            option.disabled = false;
        });

        // Get all ODP options with data-area attribute
        const odpOptions = odpSelect.querySelectorAll("option[data-area]");

        // Reset ODP selection
        odpSelect.value = "";

        // Show/hide ODP options based on selected area
        let visibleOptionsCount = 0;
        odpOptions.forEach((option) => {
            if (
                selectedAreaId === "" ||
                option.dataset.area === selectedAreaId
            ) {
                option.style.display = "";
                option.disabled = false;
                visibleOptionsCount++;
            } else {
                option.style.display = "none";
                option.disabled = true;
            }
        });

        // Update default option text
        const defaultOption = odpSelect.querySelector('option[value=""]');
        if (defaultOption) {
            if (selectedAreaId && visibleOptionsCount > 0) {
                defaultOption.textContent = "Select ODP";
            } else if (selectedAreaId && visibleOptionsCount === 0) {
                defaultOption.textContent = "No ODP available for this area";
            } else {
                defaultOption.textContent = "Select Area First";
            }
        }

        // Set specific ODP value if provided (after filtering)
        if (selectOdpValue && selectedAreaId) {
            setTimeout(() => {
                const targetOption = odpSelect.querySelector(
                    `option[value="${selectOdpValue}"]`
                );
                if (targetOption && targetOption.style.display !== "none") {
                    odpSelect.value = selectOdpValue;
                } else {
                    console.warn(
                        "ODP option not found or not visible:",
                        selectOdpValue
                    );
                }
            }, 50);
        }
    }

    // Area change event listener
    if (areaSelect && odpSelect) {
        areaSelect.addEventListener("change", function () {
            const selectedAreaId = this.value;
            filterOdpByArea(selectedAreaId);
        });
    }

    // ===========================================
    // MAC ADDRESS FORMATTING
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
    // FORM VALIDATION
    // ===========================================

    function validateEditForm() {
        const errors = [];

        // Clear previous validation
        document.querySelectorAll(".is-invalid").forEach((el) => {
            el.classList.remove("is-invalid");
        });

        const selectedType = (
            editTypeHidden ? editTypeHidden.value : "pppoe"
        ).toLowerCase();

        // Type-specific validation
        if (selectedType === "dhcp") {
            if (!editMacInput || !editMacInput.value.trim()) {
                errors.push("MAC Address harus diisi untuk tipe DHCP");
                if (editMacInput) editMacInput.classList.add("is-invalid");
            } else {
                const macRegex =
                    /^[0-9A-Fa-f]{2}:[0-9A-Fa-f]{2}:[0-9A-Fa-f]{2}:[0-9A-Fa-f]{2}:[0-9A-Fa-f]{2}:[0-9A-Fa-f]{2}$/;
                if (!macRegex.test(editMacInput.value)) {
                    errors.push("Format MAC Address tidak valid");
                    editMacInput.classList.add("is-invalid");
                }
            }
        } else {
            // PPPoE validation
            if (!editUsernameInput || !editUsernameInput.value.trim()) {
                errors.push("Username harus diisi");
                if (editUsernameInput)
                    editUsernameInput.classList.add("is-invalid");
            } else if (editUsernameInput.value.length < 3) {
                errors.push("Username minimal 3 karakter");
                if (editUsernameInput)
                    editUsernameInput.classList.add("is-invalid");
            }

            if (!editPasswordInput || !editPasswordInput.value.trim()) {
                errors.push("Password harus diisi");
                if (editPasswordInput)
                    editPasswordInput.classList.add("is-invalid");
            }
        }

        // Common validations
        if (!profileSelect || !profileSelect.value) {
            errors.push("Profile harus dipilih");
            if (profileSelect) profileSelect.classList.add("is-invalid");
        }

        return errors;
    }

    function showValidationErrors(errors) {
        // Remove existing alerts
        const existingAlerts = document.querySelectorAll(
            ".validation-error-alert"
        );
        existingAlerts.forEach((alert) => alert.remove());

        if (errors.length > 0) {
            const alertDiv = document.createElement("div");
            alertDiv.className =
                "alert alert-danger alert-dismissible fade show validation-error-alert";
            alertDiv.innerHTML = `
                <strong>Mohon perbaiki kesalahan berikut:</strong>
                <ul class="mb-0 mt-2">
                    ${errors.map((error) => `<li>${error}</li>`).join("")}
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            `;

            const modalBody = editModal.querySelector(".modal-body");
            modalBody.insertBefore(alertDiv, modalBody.firstChild);

            // Auto dismiss after 10 seconds
            setTimeout(() => {
                if (alertDiv && alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 10000);
        }
    }

    // ===========================================
    // MODAL EVENT HANDLERS - FIXED VERSION
    // ===========================================

    if (editModal) {
        // Handle modal show event
        editModal.addEventListener("show.bs.modal", function (event) {
            forceCleanupModal();
        });

        // Handle modal shown event
        editModal.addEventListener("shown.bs.modal", function (event) {
            const firstInput = editModal.querySelector(
                'input:not([type="hidden"]):not([disabled])'
            );
            if (firstInput) {
                firstInput.focus();
            }
        });

        // Handle modal hide event
        editModal.addEventListener("hide.bs.modal", function (event) {
            // Clear validation errors
            const errorFields = editModal.querySelectorAll(".is-invalid");
            errorFields.forEach((field) =>
                field.classList.remove("is-invalid")
            );
            const existingAlerts = editModal.querySelectorAll(
                ".validation-error-alert"
            );
            existingAlerts.forEach((alert) => alert.remove());
        });

        // Handle modal hidden event - FIXED VERSION
        editModal.addEventListener("hidden.bs.modal", function (event) {
            setTimeout(() => {
                forceCleanupModal();

                // Reset form
                if (editForm) {
                    editForm.reset();
                }

                // Reset visibility
                setEditVisibilityForType("pppoe");

                // Reset ODP options to initial state (DON'T hide them)
                resetOdpOptions();
            }, 100);
        });

        // Handle close buttons
        const closeButtons = editModal.querySelectorAll(
            '[data-bs-dismiss="modal"], .btn-close'
        );
        closeButtons.forEach((button) => {
            button.addEventListener("click", function (e) {
                e.preventDefault();
                e.stopPropagation();

                try {
                    const bsModal = bootstrap.Modal.getInstance(editModal);
                    if (bsModal) {
                        bsModal.hide();
                    } else {
                        const newModal = new bootstrap.Modal(editModal);
                        newModal.hide();
                    }
                } catch (error) {
                    console.error("Error closing modal:", error);
                    forceCleanupModal();
                }
            });
        });

        // Handle backdrop click
        editModal.addEventListener("click", function (e) {
            if (e.target === editModal) {
                e.preventDefault();
                e.stopPropagation();

                try {
                    const bsModal = bootstrap.Modal.getInstance(editModal);
                    if (bsModal) {
                        bsModal.hide();
                    }
                } catch (error) {
                    forceCleanupModal();
                }
            }
        });

        // Prevent event bubbling on modal content
        const modalContent = editModal.querySelector(".modal-content");
        if (modalContent) {
            modalContent.addEventListener("click", function (e) {
                e.stopPropagation();
            });
        }

        // Handle ESC key
        document.addEventListener("keydown", function (e) {
            if (e.key === "Escape" && editModal.classList.contains("show")) {
                e.preventDefault();
                try {
                    const bsModal = bootstrap.Modal.getInstance(editModal);
                    if (bsModal) {
                        bsModal.hide();
                    }
                } catch (error) {
                    forceCleanupModal();
                }
            }
        });
    }

    // ===========================================
    // FORM SUBMISSION
    // ===========================================

    if (editForm) {
        editForm.addEventListener("submit", function (e) {
            e.preventDefault();

            const errors = validateEditForm();
            if (errors.length > 0) {
                showValidationErrors(errors);
                editModal.querySelector(".modal-body").scrollTop = 0;
                return;
            }

            const selectedType = (
                editTypeHidden ? editTypeHidden.value : "pppoe"
            ).toLowerCase();

            // Temporarily enable all fields for form submission
            const allInputs = this.querySelectorAll("input, select");
            const originalStates = [];

            allInputs.forEach((input) => {
                originalStates.push({
                    element: input,
                    disabled: input.disabled,
                });
                if (input !== editTypeSelect) {
                    input.disabled = false;
                }
            });

            // Clear unused fields based on type
            if (selectedType === "dhcp") {
                if (editUsernameInput) editUsernameInput.value = "";
                if (editPasswordInput) editPasswordInput.value = "";
            } else {
                if (editMacInput) editMacInput.value = "";
            }

            const formData = new FormData(this);

            // Restore original disabled states
            originalStates.forEach((state) => {
                state.element.disabled = state.disabled;
            });

            const submitBtn = this.querySelector('button[type="submit"]');
            const origHtml = submitBtn.innerHTML;

            submitBtn.disabled = true;
            submitBtn.innerHTML =
                '<i class="fa-solid fa-spinner fa-spin"></i> Updating...';

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
                headers: {
                    "X-CSRF-TOKEN": token,
                    Accept: "application/json",
                },
            })
                .then((response) =>
                    response.text().then((text) => {
                        try {
                            return JSON.parse(text);
                        } catch (err) {
                            throw new Error(
                                "Invalid JSON response: " +
                                    text.substring(0, 200)
                            );
                        }
                    })
                )
                .then((data) => {
                    if (data.success) {
                        alert(
                            data.message || "Connection updated successfully!"
                        );

                        try {
                            const bsModal =
                                bootstrap.Modal.getInstance(editModal);
                            if (bsModal) {
                                bsModal.hide();
                            } else {
                                forceCleanupModal();
                            }
                        } catch (err) {
                            forceCleanupModal();
                        }

                        // Reload datatable or page
                        if (typeof table !== "undefined" && table.ajax) {
                            table.ajax.reload();
                        } else {
                            window.location.reload();
                        }
                    }
                })
                .catch((error) => {
                    console.error("Submit error:", error);
                    alert("An error occurred: " + (error.message || error));
                })
                .finally(() => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = origHtml;
                });
        });
    }

    // Clear validation errors when user starts typing/selecting
    document.addEventListener("input", function (e) {
        if (e.target.classList.contains("is-invalid")) {
            e.target.classList.remove("is-invalid");
        }
    });

    document.addEventListener("change", function (e) {
        if (e.target.classList.contains("is-invalid")) {
            e.target.classList.remove("is-invalid");
        }
    });
});

// ===========================================
// GLOBAL FUNCTIONS - FIXED VERSION
// ===========================================

function forceCleanupModal() {
    const backdrops = document.querySelectorAll(".modal-backdrop");
    backdrops.forEach((backdrop) => backdrop.remove());

    document.body.classList.remove("modal-open");
    document.body.style.overflow = "";
    document.body.style.paddingRight = "";

    const modals = document.querySelectorAll(".modal.show");
    modals.forEach((modal) => {
        const bsModal = bootstrap.Modal.getInstance(modal);
        if (bsModal) {
            bsModal.dispose();
        }
        modal.classList.remove("show");
        modal.style.display = "none";
        modal.setAttribute("aria-hidden", "true");
        modal.removeAttribute("aria-modal");
    });
}

// FIXED POPULATE FUNCTION
function populateEditForm(data) {
    const editForm = document.getElementById("FormEdit");
    if (!editForm) {
        console.error("Edit form not found");
        return;
    }

    try {
        // Set form action URL
        if (data.update_url) {
            editForm.setAttribute("action", data.update_url);
        } else if (data.id) {
            const baseUrl = window.location.origin;
            editForm.setAttribute("action", `${baseUrl}/pppoe/${data.id}`);
        }

        // Set connection type
        const editTypeSelect = document.getElementById("edit-typeUserSelect");
        const editTypeHidden = document.getElementById("edit-typeUserHidden");
        const connectionType = (data.type || "pppoe").toLowerCase();

        if (editTypeSelect) {
            editTypeSelect.value = connectionType;
        }

        if (editTypeHidden) {
            editTypeHidden.value = connectionType;
        }

        // Populate connection credentials
        if (connectionType === "dhcp") {
            const editMacInput = document.getElementById("edit-macAddress");
            if (editMacInput && data.mac_address) {
                editMacInput.value = data.mac_address;
            }
        } else {
            const editUsernameInput = document.getElementById("edit-username");
            const editPasswordInput = document.getElementById("edit-password");

            if (editUsernameInput && data.username) {
                editUsernameInput.value = data.username;
            }
            if (editPasswordInput && data.password) {
                editPasswordInput.value = data.password;
            }
        }

        // Set profile
        const profileSelect = document.getElementById("edit-profileSelect");
        if (profileSelect && data.profile_id) {
            profileSelect.value = data.profile_id;
        }

        // FIXED: Set area and ODP with proper sequence
        const areaSelect = document.getElementById("edit-area");
        const odpSelect = document.getElementById("edit-odp");

        // Debug: Log initial state of ODP options
        if (odpSelect) {
            const allOdpOptions = odpSelect.querySelectorAll("option");

            // Ensure all ODP options are visible and enabled (reset any previous hiding)
            allOdpOptions.forEach((option) => {
                option.style.display = "";
                option.disabled = false;
            });
        }

        if (areaSelect && data.area_id) {
            // STEP 1: Set area value
            areaSelect.value = data.area_id;

            // STEP 2: Use a longer delay to ensure everything is properly initialized
            setTimeout(() => {
                if (data.optical_id) {
                    filterOdpByArea(data.area_id, data.optical_id);
                } else {
                    filterOdpByArea(data.area_id);
                }
            }, 100);
        } else if (odpSelect) {
            // If no area is selected, reset to default state
            const defaultOption = odpSelect.querySelector('option[value=""]');
            if (defaultOption) {
                defaultOption.textContent = "Select Area First";
            }
            odpSelect.value = "";
        }

        // Set visibility after populating values
        setTimeout(() => {
            if (typeof window.setEditVisibilityForType === "function") {
                window.setEditVisibilityForType(connectionType, true);
            }
        }, 100);
    } catch (error) {
        console.error("Error populating edit form:", error);
        alert("Error loading data: " + error.message);
    }
}

function editConnection(data) {
    forceCleanupModal();

    setTimeout(() => {
        try {
            populateEditForm(data);

            const editModal = document.getElementById("formEditModal");
            if (editModal) {
                const modal = new bootstrap.Modal(editModal, {
                    backdrop: true,
                    keyboard: true,
                    focus: true,
                });
                modal.show();
            } else {
                console.error("Edit modal not found");
            }
        } catch (error) {
            console.error("Error opening edit modal:", error);
            alert("Error opening edit form: " + error.message);
        }
    }, 100);
}

// jQuery compatibility for data attributes
$(document).ready(function () {
    $(document).on("click", "#btn-edit", function (e) {
        if (this.hasAttribute("onclick")) {
            return;
        }

        e.preventDefault();
        const button = $(this);
        const data = {
            id: button.data("id"),
            type: button.data("type") || "pppoe",
            username: button.data("username") || "",
            password: button.data("password") || "",
            mac_address: button.data("mac_address") || "",
            profile_id: button.data("profile"),
            area_id: button.data("area"),
            optical_id: button.data("optical"),
            update_url: button.data("update-url"),
        };

        editConnection(data);
    });

    $(window).on("beforeunload", function () {
        forceCleanupModal();
    });
});

// Emergency cleanup function
window.emergencyCleanup = function () {
    forceCleanupModal();

    setTimeout(() => {
        $("body").removeClass("modal-open");
        $("body").css({
            overflow: "",
            "padding-right": "",
        });
        $(".modal-backdrop").remove();
        $(".modal").removeClass("show").hide();
    }, 50);
};

// DEBUG FUNCTION - Add this to help troubleshoot
window.debugOdpState = function () {
    const odpSelect = document.getElementById("edit-odp");
    const areaSelect = document.getElementById("edit-area");

    if (odpSelect) {
        const allOptions = odpSelect.querySelectorAll("option");

        const visibleOptions = odpSelect.querySelectorAll(
            "option[data-area]:not([style*='display: none'])"
        );
    }
};

// Test function to manually trigger filtering
window.testFilterOdp = function (areaId, odpId = null) {
    filterOdpByArea(areaId, odpId);
    setTimeout(() => {
        window.debugOdpState();
    }, 200);
};

// UPDATED GLOBAL FILTER FUNCTION
function filterOdpByArea(selectedAreaId, selectOdpValue = null) {
    const odpSelect = document.getElementById("edit-odp");
    if (!odpSelect) {
        console.error("ODP select element not found");
        return;
    }

    consol;

    // ALWAYS reset all options to visible first
    const allOdpOptions = odpSelect.querySelectorAll("option");

    allOdpOptions.forEach((option, index) => {
        option.style.display = "";
        option.disabled = false;
    });

    // Get options with data-area attribute
    const odpOptions = odpSelect.querySelectorAll("option[data-area]");

    // Reset selection
    odpSelect.value = "";

    let visibleOptionsCount = 0;

    if (!selectedAreaId || selectedAreaId === "") {
        // If no area selected, hide all area-specific options
        odpOptions.forEach((option) => {
            option.style.display = "none";
            option.disabled = true;
        });

        const defaultOption = odpSelect.querySelector('option[value=""]');
        if (defaultOption) {
            defaultOption.textContent = "Select Area First";
        }

        return;
    }

    // Filter options based on selected area
    odpOptions.forEach((option) => {
        const optionAreaId = option.dataset.area;

        // Use loose equality to handle string/number mismatches
        if (optionAreaId == selectedAreaId) {
            option.style.display = "";
            option.disabled = false;
            visibleOptionsCount++;
        } else {
            option.style.display = "none";
            option.disabled = true;
        }
    });

    // Update default option text
    const defaultOption = odpSelect.querySelector('option[value=""]');
    if (defaultOption) {
        if (visibleOptionsCount > 0) {
            defaultOption.textContent = "Select ODP";
        } else {
            defaultOption.textContent = "No ODP available for this area";
        }
    }

    // Set specific ODP value if provided
    if (selectOdpValue && selectedAreaId) {
        setTimeout(() => {
            const targetOption = odpSelect.querySelector(
                `option[value="${selectOdpValue}"]`
            );
            if (targetOption) {
                if (
                    targetOption.style.display !== "none" &&
                    !targetOption.disabled
                ) {
                    odpSelect.value = selectOdpValue;
                } else {
                    console.warn("✗ Target option is hidden or disabled");
                }
            } else {
                console.error("✗ Target option not found in DOM");
            }
        }, 100); // Increased timeout for more reliability
    }
}
