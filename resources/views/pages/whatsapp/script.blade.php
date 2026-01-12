@push('script-page')
    <script>
        $('#logsTable').DataTable({
            processing: true,
            serverSide: false,
            ajax: '/tools/whatsapp/logs',
            columns: [{
                    data: 'created_at',
                    name: 'created_at'
                },
                {
                    data: 'phone',
                    name: 'phone'
                },
                {
                    data: 'subject',
                    name: 'subject'
                },
                {
                    data: 'message',
                    name: 'message'
                },
                {
                    data: 'status',
                    name: 'status'
                }
            ]
        });

        let isConfigured = {{ isset($apiKey) && $apiKey ? 'true' : 'false' }};

        // ---------------------------
        // Utilities
        // ---------------------------
        function showNotification(message, type) {
            const alertClass = type === 'success' ? 'alert-success' :
                type === 'error' ? 'alert-danger' : 'alert-info';

            const alertDiv = document.createElement('div');
            alertDiv.className = `alert ${alertClass} alert-dismissible fade show position-fixed`;
            alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; max-width: 320px;';
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;

            document.body.appendChild(alertDiv);

            setTimeout(() => {
                if (alertDiv.parentNode) alertDiv.remove();
            }, 3000);
        }

        function escapeHtml(unsafe) {
            if (!unsafe) return '';
            return unsafe
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        // ---------------------------
        // Init
        // ---------------------------
        $(document).ready(function() {
            checkApiStatus();
            loadMessageLogs();

            if (isConfigured) {
                setInterval(checkApiStatus, 30000);
                setInterval(loadMessageLogs, 30000);
            }

            const logFilterEl = document.getElementById('logFilter');
            if (logFilterEl) logFilterEl.addEventListener('change', loadMessageLogs);

            const templateModalEl = document.getElementById('templateModal');
            if (templateModalEl) templateModalEl.addEventListener('show.bs.modal', function() {
                loadTemplates();
                loadAllTemplates();
            });
        });

        function checkApiStatus() {
            fetch('/tools/whatsapp/status')
                .then(response => response.json())
                .then(data => {
                    const statusEl = document.getElementById('connectionStatus');
                    const phoneEl = document.getElementById('phoneNumber');
                    const actionEl = document.getElementById('statusAction');

                    if (data.success && data.data && data.data.configured) {
                        if (data.data.status === 'connected') {
                            if (statusEl) {
                                statusEl.textContent = 'Connected';
                                statusEl.className = 'badge bg-success';
                            }
                            if (phoneEl) phoneEl.textContent = data.data.phone_number || '-';
                            if (actionEl) actionEl.textContent = 'Ready to use';
                        } else {
                            if (statusEl) {
                                statusEl.textContent = 'Not Connected';
                                statusEl.className = 'badge bg-warning';
                            }
                            if (phoneEl) phoneEl.textContent = data.data.phone_number || '-';
                            if (actionEl) actionEl.textContent = 'Waiting for connection...';
                        }
                    } else {
                        if (statusEl) {
                            statusEl.textContent = 'Not Connected';
                            statusEl.className = 'badge bg-danger';
                        }
                        if (phoneEl) phoneEl.textContent = '-';
                        if (actionEl) actionEl.textContent = (data && data.data && data.data.message) ? data.data
                            .message : 'Invalid API Key';
                    }
                })
                .catch(() => {
                    const statusEl = document.getElementById('connectionStatus');
                    if (statusEl) {
                        statusEl.textContent = 'Error';
                        statusEl.className = 'badge bg-danger';
                    }
                });
        }

        function testConnection() {
            const phone = document.getElementById('testPhone')?.value;
            if (!phone) {
                showNotification('Please enter phone number', 'error');
                return;
            }

            const btn = document.getElementById('testBtn');
            const originalHtml = btn ? btn.innerHTML : null;
            if (btn) {
                btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Sending...';
                btn.disabled = true;
            }

            fetch('/tools/whatsapp/test', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        phone: phone
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) showNotification('Test message sent successfully!', 'success');
                    else showNotification(data.message || 'Failed to send test message', 'error');
                })
                .catch(() => showNotification('Error sending test message', 'error'))
                .finally(() => {
                    if (btn) {
                        btn.innerHTML = originalHtml || '<i class="fa-solid fa-paper-plane"></i> Test';
                        btn.disabled = false;
                    }
                });
        }

        // ---------------------------
        // Broadcast Functions with Area
        // ---------------------------
        function updateMemberCount() {
            const areaId = document.getElementById('areaSelect')?.value || 'all';
            const recipientType = document.querySelector('input[name="recipients"]:checked')?.value || 'all';
            const displayEl = document.getElementById('memberCountDisplay');
            const areaInfoEl = document.getElementById('areaInfoDisplay');
            const sendBtn = document.getElementById('sendBroadcastBtn');

            if (!displayEl) return;

            displayEl.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Loading...';
            if (sendBtn) {
                sendBtn.disabled = true;
                sendBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Loading...';
            }

            fetch('/tools/whatsapp/broadcast/count', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    area_id: areaId,
                    recipients: recipientType
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const count = data.count;
                    const areaName = data.area_name || 'All Areas';
                    const statusText = recipientType === 'active' ? 'Active' :
                                     recipientType === 'suspended' ? 'Suspended' : 'All';

                    displayEl.innerHTML = `${count} <small>members</small>`;
                    displayEl.className = count > 0 ? 'badge bg-primary fs-6' : 'badge bg-secondary fs-6';

                    if (areaInfoEl) {
                        if (areaId === 'all') {
                            areaInfoEl.innerHTML = `<i class="fa-solid fa-map-marked-alt"></i> Broadcasting to ${statusText.toLowerCase()} members in <strong>all areas</strong>`;
                        } else {
                            areaInfoEl.innerHTML = `<i class="fa-solid fa-map-pin"></i> Broadcasting to ${statusText.toLowerCase()} members in area <strong>${areaName}</strong>`;
                        }
                    }

                    if (sendBtn) {
                        if (count === 0) {
                            sendBtn.disabled = true;
                            sendBtn.innerHTML = '<i class="fa-solid fa-exclamation-triangle"></i> No Recipients';
                            sendBtn.className = 'btn btn-secondary';
                        } else {
                            sendBtn.disabled = false;
                            sendBtn.innerHTML = `<i class="fa-solid fa-paper-plane"></i> Send to ${count} Members`;
                            sendBtn.className = 'btn btn-primary';
                        }
                    }
                } else {
                    displayEl.innerHTML = '<i class="fa-solid fa-exclamation-triangle"></i> Error';
                    displayEl.className = 'badge bg-danger fs-6';
                    if (areaInfoEl) areaInfoEl.innerHTML = '';
                    if (sendBtn) {
                        sendBtn.disabled = true;
                        sendBtn.innerHTML = '<i class="fa-solid fa-times"></i> Error Loading';
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                displayEl.innerHTML = '<i class="fa-solid fa-times"></i> Failed';
                displayEl.className = 'badge bg-danger fs-6';
                if (areaInfoEl) areaInfoEl.innerHTML = '';
                if (sendBtn) {
                    sendBtn.disabled = true;
                    sendBtn.innerHTML = '<i class="fa-solid fa-times"></i> Error';
                }
            });
        }

        function showBroadcastModal() {
            const modal = new bootstrap.Modal(document.getElementById('broadcastModal'));
            modal.show();

            // Reset form
            document.getElementById('broadcastForm')?.reset();
            document.querySelector('input[name="recipients"][value="all"]').checked = true;
            document.getElementById('areaSelect').value = 'all';

            // Load initial member count
            setTimeout(() => {
                updateMemberCount();
            }, 300);
        }

        // Event listener untuk broadcast modal
        document.addEventListener('DOMContentLoaded', function() {
            const broadcastModal = document.getElementById('broadcastModal');
            if (broadcastModal) {
                broadcastModal.addEventListener('shown.bs.modal', function() {
                    updateMemberCount();
                });

                // Prevent form submission if no recipients
                const broadcastForm = document.getElementById('broadcastForm');
                if (broadcastForm) {
                    broadcastForm.addEventListener('submit', function(e) {
                        const sendBtn = document.getElementById('sendBroadcastBtn');
                        if (sendBtn && sendBtn.disabled) {
                            e.preventDefault();
                            showNotification('Cannot send broadcast: No recipients available', 'error');
                            return false;
                        }

                        // Show confirmation
                        const areaSelect = document.getElementById('areaSelect');
                        const areaText = areaSelect.options[areaSelect.selectedIndex].text;
                        const count = document.getElementById('memberCountDisplay').textContent.split(' ')[0];

                        if (!confirm(`Are you sure you want to send broadcast to ${count} members in ${areaText}?`)) {
                            e.preventDefault();
                            return false;
                        }
                    });
                }
            }
        });

        // ---------------------------
        // Templates: fetch & render
        // ---------------------------
        function loadTemplates() {
            const container = document.getElementById('templateContainer');
            if (container) container.innerHTML =
                '<div class="text-center"><div class="spinner-border text-primary" role="status"></div><p class="mt-2">Loading templates...</p></div>';

            fetch('/tools/whatsapp/templates')
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(templates => {
                    console.log('Templates loaded from server:', templates);
                    currentTemplates = templates || [];

                    if (currentTemplates.length === 0) {
                        console.warn('No templates received from server');
                    }

                    if (!container) return;
                    let html = '<div class="row">';
                    currentTemplates.forEach(template => {
                        console.log(`Rendering template ${template.type}:`, {
                            name: template.name,
                            contentLength: template.content?.length || 0,
                            isModified: template.is_modified
                        });

                        html += `
                            <div class="col-lg-6 mb-4">
                                <div class="card h-100">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0">
                                            ${escapeHtml(template.name)}
                                            ${template.is_modified ? '<span class="badge bg-success ms-2">Modified</span>' : '<span class="badge bg-secondary ms-2">Default</span>'}
                                        </h6>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-secondary" type="button"
                                                    onclick="showVariables('${template.type}')"
                                                    title="Show Available Variables">
                                                <i class="fa-solid fa-info-circle"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label">Template Content:</label>
                                            <textarea class="form-control template-content"
                                                      id="template_${template.type}"
                                                      rows="8"
                                                      data-type="${template.type}"
                                                      style="font-family: monospace; font-size: 12px;">${escapeHtml(template.content)}</textarea>
                                            <small class="text-muted">
                                                <i class="fa-solid fa-lightbulb"></i>
                                                Use variables like [full_name], [no_invoice], etc.
                                            </small>
                                        </div>

                                        <div class="d-flex gap-2">
                                            <button class="btn btn-primary flex-fill"
                                                    onclick="updateTemplate('${template.type}')"
                                                    id="btn_${template.type}">
                                                <i class="fa-solid fa-save"></i> Update Template
                                            </button>
                                            <button class="btn btn-outline-warning"
                                                    onclick="resetTemplate('${template.type}')"
                                                    title="Reset to Default">
                                                <i class="fa-solid fa-undo"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                    });

                    html += '</div>';

                    html += `
                        <div class="card mt-3">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="fa-solid fa-tags"></i> Available Variables Reference</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6>Customer Variables:</h6>
                                        <ul class="list-unstyled small">
                                            <li><code>[full_name]</code> - Customer full name</li>
                                            <li><code>[uid]</code> - Customer ID</li>
                                            <li><code>[pppoe_user]</code> - PPPoE username</li>
                                            <li><code>[pppoe_profile]</code> - PPPoE profile</li>
                                        </ul>
                                    </div>
                                    <div class="col-md-6">
                                        <h6>Invoice Variables:</h6>
                                        <ul class="list-unstyled small">
                                            <li><code>[no_invoice]</code> - Invoice number</li>
                                            <li><code>[amount]</code> - Invoice amount</li>
                                            <li><code>[ppn]</code> - Tax amount</li>
                                            <li><code>[discount]</code> - Discount amount</li>
                                            <li><code>[total]</code> - Total amount</li>
                                            <li><code>[due_date]</code> - Due date</li>
                                            <li><code>[period]</code> - Billing period</li>
                                        </ul>
                                    </div>
                                </div>
                                <div class="row mt-2">
                                    <div class="col-md-6">
                                        <h6>Payment Variables:</h6>
                                        <ul class="list-unstyled small">
                                            <li><code>[payment_gateway]</code> - Payment gateway link</li>
                                            <li><code>[paid_method]</code> - Payment method used</li>
                                            <li><code>[invoice_date]</code> - Invoice creation date</li>
                                        </ul>
                                    </div>
                                    <div class="col-md-6">
                                        <h6>System Variables:</h6>
                                        <ul class="list-unstyled small">
                                            <li><code>[footer]</code> - Company footer/signature</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;

                    container.innerHTML = html;
                })
                .catch(error => {
                    console.error('Error loading templates:', error);
                    const container = document.getElementById('templateContainer');
                    if (container) container.innerHTML =
                        '<div class="alert alert-danger">Error loading templates: ' + error.message + '</div>';
                });
        }

        function loadAllTemplates() {
            return loadTemplates();
        }

        // ---------------------------
        // Select-editor functions
        // ---------------------------
        let currentType = null;
        let originalContent = '';

        function loadTemplateContent() {
            currentType = document.getElementById('templateTypeSelect').value;
            document.getElementById('saveBtn');
            document.getElementById('resetBtn');

            fetch("{{ route('whatsapp.templates.get') }}", {
                    method: 'POST',
                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": "{{ csrf_token() }}"
                    },
                    body: JSON.stringify({
                        type: currentType
                    })
                })
                .then(res => res.json())
                .then(data => {
                    document.getElementById('templateContent').value = data.content;
                    originalContent = data.content;
                    document.getElementById('saveBtn');
                });
        }

        document.addEventListener("DOMContentLoaded", function() {
            const select = document.getElementById('templateTypeSelect');
            if (select) {
                select.value = "invoice_terbit";
                loadTemplateContent();
            }
        });

        function saveTemplate() {
            const content = document.getElementById('templateContent').value;

            fetch("{{ route('whatsapp.templates.save') }}", {
                    method: 'POST',
                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": "{{ csrf_token() }}"
                    },
                    body: JSON.stringify({
                        type: currentType,
                        content
                    })
                })
                .then(res => res.json())
                .then(data => {
                    Toastify({
                        text: "Template Berhasil Diupdate!",
                        className: "info",
                    }).showToast();
                    originalContent = content;
                });
        }

        function resetTemplate() {
            Swal.fire({
                title: "Konfirmasi",
                text: "Apakah Anda yakin ingin reset template ini ke default?",
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: "Ya, Reset",
                cancelButtonText: "Batal",
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch("{{ route('whatsapp.templates.reset') }}", {
                            method: 'POST',
                            headers: {
                                "Content-Type": "application/json",
                                "X-CSRF-TOKEN": "{{ csrf_token() }}"
                            },
                            body: JSON.stringify({
                                type: currentType
                            })
                        })
                        .then(res => res.json())
                        .then(data => {
                            document.getElementById('templateContent').value = data.data.content;
                            originalContent = data.data.content;

                            Swal.fire({
                                title: "Berhasil!",
                                text: "Template berhasil direset ke default.",
                                icon: "success"
                            });
                        })
                        .catch(() => {
                            Swal.fire({
                                title: "Error!",
                                text: "Gagal mereset template, silakan coba lagi.",
                                icon: "error"
                            });
                        });
                }
            });
        }
    </script>
@endpush
