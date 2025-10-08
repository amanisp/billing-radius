document.addEventListener("DOMContentLoaded", function () {
    let currentStep = 1;

    // Store original ODP options for filtering
    let originalOdpOptions = [];

    // Elements (with graceful checks)
    const stepContents = {
        1: document.getElementById("step-1"),
        2: document.getElementById("step-2"),
        3: document.getElementById("step-3"),
        4: document.getElementById("step-4"),
    };
    const stepElems = Array.from(
        document.querySelectorAll("#stepProgress .step") || []
    );
    const hrElems = Array.from(
        document.querySelectorAll("#stepProgress hr") || []
    );
    const addOnCheckbox = document.getElementById("addOnBillingCheckbox");
    const typeSelect = document.getElementById("typeUserSelect");
    const rowMac = document.getElementById("rowMac");
    const rowPppoe = document.getElementById("rowPppoeCreds");
    const profileSelect = document.getElementById("profileSelect");
    const areaSelect = document.getElementById("areaSelect");
    const odpSelect = document.getElementById("odpSelect");
    const macInput = document.getElementById("macAddress");
    const usernameInput = document.getElementById("usernameInput");
    const passwordInput = document.getElementById("passwordInput");
    const amountInput = document.getElementById("amount");
    const ppnInput = document.getElementById("ppn");
    const discountInput = document.getElementById("discount");
    const paymentTotalInput = document.getElementById("payment_total");
    const selectedProfileName = document.getElementById("selectedProfileName");
    const selectedProfilePrice = document.getElementById(
        "selectedProfilePrice"
    );
    const wizardForm = document.getElementById("wizardForm");
    const modalEl = document.getElementById("formCreateModal");

    // Basic validation: ensure steps exist
    if (!stepContents[1] || !stepContents[4]) {
        console.error(
            "Essential step elements missing (step-1 or step-4). Aborting script."
        );
        return;
    }

    // ========== AREA-ODP FILTERING FUNCTIONS ==========

    // Store original ODP options on page load
    function storeOriginalOdpOptions() {
        if (odpSelect) {
            originalOdpOptions = Array.from(odpSelect.options).map(
                (option) => ({
                    value: option.value,
                    text: option.textContent,
                    areaId: option.getAttribute("data-area-id"), // Assuming you'll add this attribute
                })
            );
        }
    }

    // Filter ODP options based on selected area
    function filterOdpByArea(selectedAreaId) {
        if (!odpSelect) return;

        // Clear current options except the default one
        odpSelect.innerHTML = '<option value="">Select ODP</option>';

        if (!selectedAreaId) {
            // If no area selected, show all ODPs
            originalOdpOptions.forEach((option) => {
                if (option.value) {
                    // Skip empty option
                    const optionEl = document.createElement("option");
                    optionEl.value = option.value;
                    optionEl.textContent = option.text;
                    optionEl.setAttribute("data-area-id", option.areaId || "");
                    odpSelect.appendChild(optionEl);
                }
            });
        } else {
            // Filter ODPs that belong to selected area
            const filteredOptions = originalOdpOptions.filter(
                (option) => option.value && option.areaId === selectedAreaId
            );

            filteredOptions.forEach((option) => {
                const optionEl = document.createElement("option");
                optionEl.value = option.value;
                optionEl.textContent = option.text;
                optionEl.setAttribute("data-area-id", option.areaId);
                odpSelect.appendChild(optionEl);
            });

            // Show message if no ODPs found for selected area
            if (filteredOptions.length === 0) {
                const optionEl = document.createElement("option");
                optionEl.value = "";
                optionEl.textContent = "No ODP available for selected area";
                optionEl.disabled = true;
                odpSelect.appendChild(optionEl);
            }
        }

        // Reset ODP selection
        odpSelect.value = "";

        // Remove any existing validation errors
        odpSelect.classList.remove("is-invalid");
    }

    // ========== VALIDATION FUNCTIONS ==========
    function isValidMacAddress(mac) {
        const macRegex =
            /^[0-9A-Fa-f]{2}:[0-9A-Fa-f]{2}:[0-9A-Fa-f]{2}:[0-9A-Fa-f]{2}:[0-9A-Fa-f]{2}:[0-9A-Fa-f]{2}$/;
        return macRegex.test(mac);
    }

    // Function to highlight error fields
    function highlightErrorFields(step) {
        // Remove existing highlights
        document.querySelectorAll(".is-invalid").forEach((field) => {
            field.classList.remove("is-invalid");
        });

        if (step === 1) {
            // Highlight Step 1 fields with errors
            if (typeSelect && !typeSelect.value) {
                typeSelect.classList.add("is-invalid");
            }

            if (profileSelect && !profileSelect.value) {
                profileSelect.classList.add("is-invalid");
            }

            const selectedType = typeSelect ? typeSelect.value : "pppoe";
            if (selectedType === "dhcp") {
                if (
                    !macInput ||
                    !macInput.value.trim() ||
                    !isValidMacAddress(macInput.value)
                ) {
                    if (macInput) macInput.classList.add("is-invalid");
                }
            } else if (selectedType === "pppoe") {
                if (
                    !usernameInput ||
                    !usernameInput.value.trim() ||
                    usernameInput.value.length <= 0
                ) {
                    if (usernameInput) {
                        usernameInput.classList.add("is-invalid");
                    }
                }

                if (
                    !passwordInput ||
                    !passwordInput.value.trim() ||
                    passwordInput.value.length <= 0
                ) {
                    if (passwordInput) {
                        passwordInput.classList.add("is-invalid");
                    }
                }
            }
        } else if (step === 2) {
            if (addOnCheckbox && addOnCheckbox.checked) {
                const fullnameInput = document.querySelector(
                    'input[name="fullname"]'
                );
                if (fullnameInput && !fullnameInput.value.trim()) {
                    fullnameInput.classList.add("is-invalid");
                }
            }
        } else if (step === 3) {
            if (addOnCheckbox && addOnCheckbox.checked) {
                const paymentTypeSelect =
                    document.getElementById("payment_type");
                if (paymentTypeSelect && !paymentTypeSelect.value) {
                    paymentTypeSelect.classList.add("is-invalid");
                }

                const billingPeriodSelect =
                    document.getElementById("billing_period");
                if (billingPeriodSelect && !billingPeriodSelect.value) {
                    billingPeriodSelect.classList.add("is-invalid");
                }

                if (
                    !amountInput ||
                    !amountInput.value ||
                    parseFloat(amountInput.value) <= 0
                ) {
                    if (amountInput) amountInput.classList.add("is-invalid");
                }
            }
        }
    }

    function validateStep(step) {
        const errors = [];

        if (step === 1) {
            // Validasi Step 1 - Account

            // Type User (required)
            if (typeSelect && !typeSelect.value) {
                errors.push("Type User harus dipilih");
            }

            // Profile (required)
            if (profileSelect && !profileSelect.value) {
                errors.push("Profile harus dipilih");
            }

            // Validasi berdasarkan type yang dipilih
            const selectedType = typeSelect ? typeSelect.value : "pppoe";

            if (selectedType === "dhcp") {
                // Untuk DHCP, MAC Address WAJIB
                if (!macInput || !macInput.value || !macInput.value.trim()) {
                    errors.push("MAC Address harus diisi untuk tipe DHCP");
                } else if (!isValidMacAddress(macInput.value)) {
                    errors.push(
                        "Format MAC Address tidak valid (contoh: aa:bb:cc:dd:ee:ff)"
                    );
                }
            } else if (selectedType === "pppoe") {
                if (
                    !usernameInput ||
                    !usernameInput.value ||
                    !usernameInput.value.trim()
                ) {
                    errors.push("Username harus diisi");
                }
                if (
                    !passwordInput ||
                    !passwordInput.value ||
                    !passwordInput.value.trim()
                ) {
                    errors.push("Password harus diisi");
                }
            }
        } else if (step === 2) {
            // Validasi Step 2 - Member (hanya jika add on billing dicentang)
            if (addOnCheckbox && addOnCheckbox.checked) {
                // Full Name (required)
                const fullnameInput = document.querySelector(
                    'input[name="fullname"]'
                );
                if (!fullnameInput || !fullnameInput.value.trim()) {
                    errors.push("Nama lengkap harus diisi");
                }
            }
        } else if (step === 3) {
            // Validasi Step 3 - Payment (hanya jika add on billing dicentang)
            if (addOnCheckbox && addOnCheckbox.checked) {
                // Payment Type (required) - Fixed the ID reference
                const paymentTypeSelect =
                    document.getElementById("payment_type");
                if (!paymentTypeSelect || !paymentTypeSelect.value) {
                    errors.push("Tipe payment harus dipilih");
                }

                // Billing Period (required)
                const billingPeriodSelect =
                    document.getElementById("billing_period");
                if (!billingPeriodSelect || !billingPeriodSelect.value) {
                    errors.push("Periode billing harus dipilih");
                }

                // Amount (required)
                if (
                    !amountInput ||
                    !amountInput.value ||
                    parseFloat(amountInput.value) <= 0
                ) {
                    errors.push("Amount harus lebih besar dari 0");
                }

                // PPN validation (optional but if filled must be valid)
                if (
                    ppnInput &&
                    ppnInput.value &&
                    (parseFloat(ppnInput.value) < 0 ||
                        parseFloat(ppnInput.value) > 100)
                ) {
                    errors.push("PPN harus antara 0-100%");
                }

                // Discount validation (optional but if filled must be valid)
                if (
                    discountInput &&
                    discountInput.value &&
                    parseFloat(discountInput.value) < 0
                ) {
                    errors.push("Discount tidak boleh negatif");
                }
            }
        }

        return errors;
    }

    function showValidationErrors(errors) {
        // Remove existing error alerts
        const existingAlerts = document.querySelectorAll(
            ".validation-error-alert"
        );
        existingAlerts.forEach((alert) => alert.remove());

        if (errors.length > 0) {
            const currentStepEl = stepContents[currentStep];
            if (currentStepEl) {
                const alertDiv = document.createElement("div");
                alertDiv.className =
                    "alert alert-danger alert-dismissible fade show validation-error-alert";
                alertDiv.innerHTML = `
            <strong>Mohon lengkapi data berikut:</strong>
            <ul class="mb-0 mt-2">
                ${errors.map((error) => `<li>${error}</li>`).join("")}
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;

                // Insert at the beginning of step content
                currentStepEl.insertBefore(alertDiv, currentStepEl.firstChild);

                // Auto dismiss after 10 seconds
                setTimeout(() => {
                    if (alertDiv && alertDiv.parentNode) {
                        alertDiv.remove();
                    }
                }, 10000);
            }
        }
    }

    function setVisibilityForType(type) {
        if (!type) type = typeSelect ? typeSelect.value : "pppoe";

        const rowAutoGenerate = document.getElementById("rowAutoGenerate");

        if (type === "pppoe") {
            if (rowPppoe) rowPppoe.classList.remove("d-none");
            if (rowMac) rowMac.classList.add("d-none");
            if (rowAutoGenerate) rowAutoGenerate.classList.remove("d-none");

            if (usernameInput) {
                usernameInput.disabled = false;
                usernameInput.required = true;
            }
            if (passwordInput) {
                passwordInput.disabled = false;
                passwordInput.required = true;
            }
            if (macInput) {
                macInput.disabled = true;
                macInput.value = "";
                macInput.required = false;
                macInput.classList.remove("is-invalid");
            }
        } else if (type === "dhcp") {
            if (rowMac) rowMac.classList.remove("d-none");
            if (rowPppoe) rowPppoe.classList.add("d-none");
            if (rowAutoGenerate) rowAutoGenerate.classList.add("d-none");

            if (macInput) {
                macInput.disabled = false;
                macInput.required = true;
            }
            if (usernameInput) {
                usernameInput.disabled = true;
                usernameInput.value = "";
                usernameInput.required = false;
                usernameInput.classList.remove("is-invalid");
            }
            if (passwordInput) {
                passwordInput.disabled = true;
                passwordInput.value = "";
                passwordInput.required = false;
                passwordInput.classList.remove("is-invalid");
            }
        } else {
            // fallback: show PPPoE
            if (rowPppoe) rowPppoe.classList.remove("d-none");
            if (rowMac) rowMac.classList.add("d-none");
            if (rowAutoGenerate) rowAutoGenerate.classList.remove("d-none");
        }
    }

    // -- Profile display & payment calc --
    function updateProfileDisplay() {
        if (
            !profileSelect ||
            !selectedProfileName ||
            !selectedProfilePrice ||
            !amountInput
        )
            return;

        const selectedOption =
            profileSelect.options[profileSelect.selectedIndex];
        if (selectedOption && selectedOption.value) {
            const price =
                parseFloat(selectedOption.getAttribute("data-price")) || 0;
            const name =
                selectedOption.getAttribute("data-name") ||
                selectedOption.text ||
                "-";

            selectedProfileName.textContent = name;
            selectedProfilePrice.textContent = price.toLocaleString("id-ID");
            amountInput.value = price;

            calculatePaymentTotal();
        } else {
            selectedProfileName.textContent = "-";
            selectedProfilePrice.textContent = "0";
            amountInput.value = "";
            if (paymentTotalInput) paymentTotalInput.value = "";
        }
    }

    function calculatePaymentTotal() {
        if (!amountInput || !paymentTotalInput) return;

        const amount = parseFloat(amountInput.value) || 0;
        const ppn = ppnInput ? parseFloat(ppnInput.value) || 0 : 0;
        const discount = discountInput
            ? parseFloat(discountInput.value) || 0
            : 0;

        const ppnAmount = (amount * ppn) / 100;
        const total = amount + ppnAmount - discount;

        paymentTotalInput.value = Math.max(0, total);
    }

    // -- Steps visibility logic --
    function getVisibleSteps() {
        if (addOnCheckbox && !addOnCheckbox.checked) {
            return [1, 4];
        }
        return [1, 2, 3, 4];
    }

    function updateProgressVisibility() {
        const visible = getVisibleSteps();

        // show/hide step elements in progress bar
        stepElems.forEach((el, i) => {
            const stepNum = i + 1;
            if (visible.includes(stepNum)) {
                el.classList.remove("d-none");
            } else {
                el.classList.add("d-none");
            }
        });
        if (visible.length === 2 && visible[0] === 1 && visible[1] === 4) {
            if (hrElems[0]) hrElems[0].classList.add("d-none");
            if (hrElems[1]) hrElems[1].classList.add("d-none");
            if (hrElems[2]) hrElems[2].classList.remove("d-none");
        } else {
            hrElems.forEach((h) => {
                if (h) h.classList.remove("d-none");
            });
        }
    }

    function updateProgressActive(step) {
        stepElems.forEach((el, i) => {
            const circle = el.querySelector(".circle");
            const stepNum = i + 1;

            if (circle) {
                circle.classList.remove(
                    "bg-primary",
                    "text-white",
                    "bg-success"
                );
                circle.classList.add("border");
                el.classList.remove("active", "completed");
            }

            if (!el.classList.contains("d-none")) {
                if (stepNum < step) {
                    if (circle) {
                        circle.classList.add("bg-success", "text-white");
                        circle.classList.remove("border");
                    }
                    el.classList.add("completed");
                } else if (stepNum === step) {
                    if (circle) {
                        circle.classList.add("bg-primary", "text-white");
                        circle.classList.remove("border");
                    }
                    el.classList.add("active");
                }
            }
        });
    }

    function updateReviewContent() {
        const reviewContent = document.getElementById("reviewContent");
        if (!reviewContent) return;

        const formEl = document.getElementById("wizardForm");
        const formData = formEl ? new FormData(formEl) : new FormData();
        let html = '<div class="row">';

        // Account Information
        html += '<div class="col-md-6"><div class="review-section">';
        html +=
            '<h6><i class="fa-solid fa-user-gear"></i> Account Information</h6>';
        html += `<p><strong>Type:</strong> ${
            formData.get("type") || (typeSelect ? typeSelect.value : "PPPoE")
        }</p>`;
        if (formData.get("username"))
            html += `<p><strong>Username:</strong> ${formData.get(
                "username"
            )}</p>`;
        if (formData.get("mac_address"))
            html += `<p><strong>MAC Address:</strong> ${formData.get(
                "mac_address"
            )}</p>`;

        // Profile name (safely)
        if (profileSelect) {
            const pText =
                profileSelect.options[profileSelect.selectedIndex]?.text ||
                "Not selected";
            html += `<p><strong>Profile:</strong> ${pText}</p>`;
        }
        // area select
        if (areaSelect) {
            const areaText =
                areaSelect.options[areaSelect.selectedIndex]?.text ||
                "Not selected";
            if (areaText && areaText !== "Select Area")
                html += `<p><strong>Area:</strong> ${areaText}</p>`;
        }
        // odp select
        if (odpSelect) {
            const odpText =
                odpSelect.options[odpSelect.selectedIndex]?.text ||
                "Not selected";
            if (odpText && odpText !== "Select ODP")
                html += `<p><strong>ODP:</strong> ${odpText}</p>`;
        }

        html += "</div></div>";

        // Member Information + Payment (if billing add-on checked)
        if (addOnCheckbox && addOnCheckbox.checked) {
            html += '<div class="col-md-6"><div class="review-section">';
            html +=
                '<h6><i class="fa-solid fa-user"></i> Member Information</h6>';
            html += `<p><strong>Full Name:</strong> ${
                formData.get("fullname") || "-"
            }</p>`;
            html += `<p><strong>Email:</strong> ${
                formData.get("email") || "-"
            }</p>`;
            html += `<p><strong>Phone:</strong> ${
                formData.get("phone_number") || "-"
            }</p>`;
            html += `<p><strong>Address:</strong> ${
                formData.get("address") || "-"
            }</p>`;
            html += `<input type="hidden" name="add_on_billing" value="1">`;
            html += "</div></div>";

            // Payment info
            html += '<div class="col-12"><div class="review-section">';
            html +=
                '<h6><i class="fa-solid fa-credit-card"></i> Payment Information</h6>';
            html += `<p><strong>Payment Type:</strong> ${(
                formData.get("payment_type") || "-"
            )
                .toString()
                .toUpperCase()}</p>`;
            html += `<p><strong>Billing Period:</strong> ${(
                formData.get("billing_period") || "-"
            )
                .toString()
                .replace("_", " ")
                .toUpperCase()}</p>`;
            html += `<p><strong>Amount:</strong> Rp ${parseFloat(
                formData.get("amount") || 0
            ).toLocaleString("id-ID")}</p>`;
            const paymentTotal =
                parseFloat(
                    formData.get("payment_total") ||
                        (paymentTotalInput ? paymentTotalInput.value : 0)
                ) || 0;
            html += `<p><strong>Total Payment:</strong> <span class="text-success fw-bold">Rp ${paymentTotal.toLocaleString(
                "id-ID"
            )}</span></p>`;
            if (formData.get("ppn"))
                html += `<p><strong>PPN:</strong> ${formData.get("ppn")}%</p>`;
            if (formData.get("discount"))
                html += `<p><strong>Discount:</strong> Rp ${parseFloat(
                    formData.get("discount") || 0
                ).toLocaleString("id-ID")}</p>`;
            html += "</div></div>";
        }

        html += "</div>";
        reviewContent.innerHTML = html;

        // also refresh progress UI
        updateProgressVisibility();
        updateProgressActive(currentStep);
    }

    // -- Show step and keep progress UI updated --
    function showStep(step) {
        Object.values(stepContents).forEach((el) => {
            if (el) el.classList.add("d-none");
        });
        if (stepContents[step]) stepContents[step].classList.remove("d-none");

        updateProgressVisibility();
        updateProgressActive(step);

        if (step === 4) updateReviewContent();
    }

    // -- Navigation helpers WITH VALIDATION --
    function goNext() {
        // Clear previous errors
        const existingAlerts = document.querySelectorAll(
            ".validation-error-alert"
        );
        existingAlerts.forEach((alert) => alert.remove());

        // Validate current step
        const errors = validateStep(currentStep);

        if (errors.length > 0) {
            // Show errors and highlight fields
            showValidationErrors(errors);
            highlightErrorFields(currentStep);

            // Scroll to top of modal to show error message
            const modal = document.querySelector(
                "#formCreateModal .modal-body"
            );
            if (modal) {
                modal.scrollTop = 0;
            }

            return; // Don't proceed to next step
        }

        // If validation passes, proceed to next step
        const visible = getVisibleSteps();
        const idx = visible.indexOf(currentStep);
        if (idx < visible.length - 1) {
            currentStep = visible[idx + 1];
            showStep(currentStep);
        }
    }

    function goPrev() {
        const visible = getVisibleSteps();
        const idx = visible.indexOf(currentStep);
        if (idx > 0) {
            currentStep = visible[idx - 1];
            showStep(currentStep);
        }
    }

    // Function to reset all button states
    function resetAllButtonStates() {
        const submitBtn = wizardForm
            ? wizardForm.querySelector('button[type="submit"]')
            : null;
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fa-solid fa-check"></i> Submit';
        }

        const nextBtns = document.querySelectorAll(".next-step");
        const prevBtns = document.querySelectorAll(".prev-step");

        nextBtns.forEach((btn) => {
            btn.disabled = false;
            if (btn.innerHTML.includes("fa-spinner")) {
                btn.innerHTML = '<i class="fa-solid fa-arrow-right"></i> Next';
            }
        });

        prevBtns.forEach((btn) => {
            btn.disabled = false;
            if (btn.innerHTML.includes("fa-spinner")) {
                btn.innerHTML = '<i class="fa-solid fa-arrow-left"></i> Back';
            }
        });
    }

    // -- Event Listeners --
    document.querySelectorAll(".next-step").forEach((btn) => {
        btn.addEventListener("click", goNext);
    });

    document.querySelectorAll(".prev-step").forEach((btn) => {
        btn.addEventListener("click", goPrev);
    });

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

    // MAC address auto-formatting
    if (macInput) {
        macInput.addEventListener("input", function (e) {
            let value = e.target.value.replace(/[^a-fA-F0-9]/g, "");
            let formatted = value.match(/.{1,2}/g)?.join(":") || value;
            if (formatted.length > 17) formatted = formatted.substring(0, 17);
            e.target.value = formatted.toLowerCase();
        });
    }

    if (addOnCheckbox) {
        addOnCheckbox.addEventListener("change", function () {
            updateProgressVisibility();
            const visible = getVisibleSteps();
            if (!visible.includes(currentStep)) {
                // if current step hidden, move to last visible step
                currentStep = visible[visible.length - 1];
            }
            showStep(currentStep);
        });
    }

    if (typeSelect) {
        typeSelect.addEventListener("change", function (e) {
            setVisibilityForType(e.target.value);
        });
    }

    if (profileSelect) {
        profileSelect.addEventListener("change", updateProfileDisplay);
    }

    // ========== NEW: Area Change Event Listener ==========
    if (areaSelect) {
        areaSelect.addEventListener("change", function (e) {
            const selectedAreaId = e.target.value;
            filterOdpByArea(selectedAreaId);

            // Clear validation error if exists
            if (e.target.classList.contains("is-invalid")) {
                e.target.classList.remove("is-invalid");
            }
        });
    }

    [amountInput, ppnInput, discountInput].forEach((input) => {
        if (input)
            input.addEventListener("input", () => {
                calculatePaymentTotal();
                // whenever payment changes, recalc review if visible
                if (currentStep === 4) updateReviewContent();
            });
    });

    // FIXED: Complete form submission handler with proper button reset
    if (wizardForm) {
        wizardForm.addEventListener("submit", function (e) {
            e.preventDefault();

            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            const origHtml = submitBtn ? submitBtn.innerHTML : null;

            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML =
                    '<i class="fa-solid fa-spinner fa-spin"></i> Processing...';
            }

            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            const token = csrfMeta ? csrfMeta.getAttribute("content") : "";

            if (!token) {
                Toastify({
                    text: "CSRF token not found. Please refresh the page.",
                    className: "error",
                    style: {
                        background: "red",
                    },
                }).showToast();

                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML =
                        origHtml || '<i class="fa-solid fa-check"></i> Submit';
                }
                return;
            }

            // AJAX submission
            $.ajax({
                url: "/ppp/pppoe/create-with-member",
                type: "POST",
                headers: {
                    "X-CSRF-TOKEN": token,
                },
                data: formData,
                processData: false,
                contentType: false,
                success: function (response) {
                    if (response.success) {
                        // Close modal
                        if (modalEl) {
                            const modal = bootstrap.Modal.getInstance(modalEl);
                            if (modal) modal.hide();
                        }

                        // Show success message
                        Toastify({
                            text: "Data Berhasil Ditambahkan!",
                            className: "info",
                        }).showToast();

                        // Reload DataTable if it exists
                        if (window.table && window.table.ajax) {
                            window.table.ajax.reload();
                        }
                    }
                },
                error: function (xhr) {
                    console.error("Submission error:", xhr);

                    if (xhr.status === 422) {
                        let errors = xhr.responseJSON.errors;
                        $.each(errors, function (key, messages) {
                            messages.forEach((message, index) => {
                                setTimeout(() => {
                                    Toastify({
                                        text: message,
                                        className: "error",
                                        style: {
                                            background: "red",
                                        },
                                    }).showToast();
                                }, index * 500);
                            });
                        });
                    } else {
                        Toastify({
                            text:
                                xhr.responseJSON?.message ||
                                "Terjadi kesalahan!",
                            className: "error",
                            style: {
                                background: "red",
                            },
                        }).showToast();
                    }
                },
                complete: function () {
                    // Always reset button state when request completes
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML =
                            origHtml ||
                            '<i class="fa-solid fa-check"></i> Submit';
                    }
                },
            });
        });
    }

    // Enhanced modal events with proper button state management
    if (modalEl) {
        modalEl.addEventListener("shown.bs.modal", function () {
            // Store original ODP options when modal opens
            storeOriginalOdpOptions();

            // Reset form completely
            if (wizardForm) {
                wizardForm.reset();
            }

            // Reset all button states
            resetAllButtonStates();

            setVisibilityForType(typeSelect ? typeSelect.value : "pppoe");
            updateProfileDisplay();
            calculatePaymentTotal();
            updateProgressVisibility();

            const visible = getVisibleSteps();
            if (!visible.includes(currentStep)) currentStep = visible[0];
            showStep(currentStep);

            // Clear any validation errors
            const errorFields = document.querySelectorAll(".is-invalid");
            errorFields.forEach((field) =>
                field.classList.remove("is-invalid")
            );
            const existingAlerts = document.querySelectorAll(
                ".validation-error-alert"
            );
            existingAlerts.forEach((alert) => alert.remove());
        });

        modalEl.addEventListener("hidden.bs.modal", function () {
            // Complete form reset
            if (wizardForm) {
                wizardForm.reset();
            }

            // Reset all button states
            resetAllButtonStates();

            // Reset all form elements to initial state
            setVisibilityForType("pppoe");
            updateProfileDisplay();
            if (paymentTotalInput) paymentTotalInput.value = "";

            // Reset ODP to show all options
            if (areaSelect) areaSelect.value = "";
            if (odpSelect) {
                filterOdpByArea(""); // Show all ODPs
            }

            updateProgressVisibility();
            currentStep = 1;
            showStep(currentStep);

            // Clear any validation errors
            const errorFields = document.querySelectorAll(".is-invalid");
            errorFields.forEach((field) =>
                field.classList.remove("is-invalid")
            );
            const existingAlerts = document.querySelectorAll(
                ".validation-error-alert"
            );
            existingAlerts.forEach((alert) => alert.remove());

            // Remove any backdrop remnants
            const backdrops = document.querySelectorAll(".modal-backdrop");
            backdrops.forEach((backdrop) => backdrop.remove());
            document.body.classList.remove("modal-open");
            document.body.style.removeProperty("padding-right");
        });
    }

    // Initial run (safe)
    setVisibilityForType(typeSelect ? typeSelect.value : "pppoe");
    updateProfileDisplay();
    calculatePaymentTotal();
    updateProgressVisibility();
    showStep(currentStep);
});
