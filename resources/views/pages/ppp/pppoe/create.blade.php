<div class="modal fade" id="formCreateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h1 class="modal-title fs-5">New User PPPoE</h1>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="card-body p-4">

                <!-- Step Progress -->
                <div class="d-flex justify-content-between align-items-center mb-4" id="stepProgress">
                    <div class="step text-center flex-fill">
                        <div style="max-width: 45px; max-height: 45px; min-width: 45px; min-height: 45px"
                            class="circle bg-primary text-white rounded-circle mx-auto d-flex justify-content-center align-items-center">
                            1</div>
                        <small>Account</small>
                    </div>
                    <hr class="flex-grow-1 mx-2">
                    <div class="step text-center flex-fill">
                        <div style="max-width: 45px; max-height: 45px; min-width: 45px; min-height: 45px"
                            class="circle bg-primary text-white rounded-circle mx-auto d-flex justify-content-center align-items-center">
                            2</div>
                        <small>Member</small>
                    </div>
                    <hr class="flex-grow-1 mx-2">
                    <div class="step text-center flex-fill">
                        <div style="max-width: 45px; max-height: 45px; min-width: 45px; min-height: 45px"
                            class="circle bg-primary text-white rounded-circle mx-auto d-flex justify-content-center align-items-center">
                            3</div>
                        <small>Payment</small>
                    </div>
                    <hr class="flex-grow-1 mx-2">
                    <div class="step text-center flex-fill">
                        <div style="max-width: 45px; max-height: 45px; min-width: 45px; min-height: 45px"
                            class="circle bg-primary text-white rounded-circle mx-auto d-flex justify-content-center align-items-center">
                            4</div>
                        <small>Review</small>
                    </div>
                </div>

                <form method="POST" id="wizardForm">
                    @csrf

                    <!-- Step 1 Account -->
                    <div class="step-content" id="step-1">
                        <div class="step-content-inner" tabindex="-1">
                            <div class="row mb-3">
                                <div class="col-12">
                                    <label class="form-label">Type User <span class="text-danger">*</span></label>
                                    <select id="typeUserSelect" class="form-select customerSelect" name="type"
                                        required>
                                        <option value="pppoe" selected>PPPoE</option>
                                        <option value="dhcp">DHCP</option>
                                    </select>
                                </div>
                            </div>

                            <!-- MAC Address (only for DHCP) -->
                            <div class="row mb-3 d-none" id="rowMac">
                                <div class="col-12">
                                    <label class="form-label">MAC Address <span class="text-danger">*</span></label>
                                    <input id="macAddress" type="text" class="form-control" name="mac_address"
                                        placeholder="xx:xx:xx:xx:xx:xx" value="{{ old('mac_address') }}">
                                </div>
                            </div>

                            <!-- PPPoE username & password (only for PPPoE) -->
                            <div class="row mb-3" id="rowPppoeCreds">
                                <div class="col-md-6">
                                    <label class="form-label">Username <span class="text-danger">*</span></label>
                                    <input id="usernameInput" type="text" class="form-control" name="username"
                                        autocomplete="new-username" placeholder="Username"
                                        value="{{ old('username') }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Password <span class="text-danger">*</span></label>
                                    <input id="passwordInput" type="password" class="form-control" name="password"
                                        autocomplete="new-password" placeholder="Password">
                                </div>
                            </div>

                            <div class="row mb-3" tabindex="-1">
                                <div class="col-md-6">
                                    <label class="form-label">Profile <span class="text-danger">*</span></label>
                                    <select class="form-select" name="profile_id" id="profileSelect" required>
                                        <option value="">Select Profile</option>
                                        @foreach ($profile as $prof)
                                            <option value="{{ $prof->id }}" data-price="{{ $prof->price }}"
                                                data-name="{{ $prof->name }}">
                                                {{ $prof->name }} — Rp {{ number_format($prof->price, 0, ',', '.') }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Area <span class="text-danger"></span></label>
                                    <select class="form-select" name="area_id" id="areaSelect">
                                        <option value="">Select Area</option>
                                        @foreach ($area as $areas)
                                            <option value="{{ $areas->id }}">{{ $areas->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">ODP <span class="text-danger"></span></label>
                                <select class="form-select" name="optical_id" id="odpSelect">
                                    <option value="">Select ODP</option>
                                    {{-- Updated: Add data-area-id attribute to each ODP option --}}
                                    @foreach ($odp as $optical)
                                        <option value="{{ $optical->id }}" data-area-id="{{ $optical->area_id }}">
                                            {{ $optical->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="form-text text-muted">
                                    <i class="fa-solid fa-info-circle"></i>
                                    Select an Area first to filter available ODPs
                                </small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Router NAS</label>
                                <select class="form-select" name="router_nas">
                                    <option value="">All</option>
                                    @foreach ($nas as $router)
                                        <option value="{{ $router->id }}">{{ $router->name }} -
                                            {{ $router->ip_address }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="form-check mb-3">
                                <input id="addOnBillingCheckbox" class="form-check-input" type="checkbox"
                                    name="add_on_billing" checked>
                                <label class="form-check-label">Add on Billing</label>
                            </div>

                            <div class="text-end">
                                <button type="button" class="btn btn-outline-primary next-step">
                                    <i class="fa-solid fa-arrow-right"></i> Next
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2 Member -->
                    <div class="step-content d-none" id="step-2">
                        <div class="step-content-inner" tabindex="-1">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Full Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="fullname">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" name="email">
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Phone</label>
                                    <input type="text" class="form-control" name="phone_number">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">NIK</label>
                                    <input type="text" class="form-control" name="id_card">
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-12">
                                    <label class="form-label">Address</label>
                                    <textarea class="form-control" name="address" rows="3"></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between">
                            <button type="button" class="btn btn-outline-secondary prev-step">
                                <i class="fa-solid fa-arrow-left"></i> Back
                            </button>
                            <button type="button" class="btn btn-outline-primary next-step">
                                <i class="fa-solid fa-arrow-right"></i> Next
                            </button>
                        </div>
                    </div>

                    <!-- Step 3 Payment -->
                    <div class="step-content d-none" id="step-3">
                        <div id="bill">
                            <hr>
                            <!-- Profile Info Display -->
                            <div class="row mb-3">
                                <div class="col-12">
                                    <div class="alert alert-info">
                                        <strong>Selected Profile:</strong> <span id="selectedProfileName">-</span><br>
                                        <strong>Base Price:</strong> Rp <span id="selectedProfilePrice">0</span>
                                    </div>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-lg-6">
                                    <label for="payment_type">Billing Type <span class="text-danger">*</span></label>
                                    <div class="form-group">
                                        <select name="payment_type" id="payment_type" class="form-select" required>
                                            <option value="prabayar">PRABAYAR</option>
                                            <option value="pascabayar">PASCABAYAR</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <label for="billing_period">Billing Period <span
                                            class="text-danger">*</span></label>
                                    <div class="form-group">
                                        <select name="billing_period" id="billing_period" class="form-select"
                                            required>
                                            <option value="fixed">Fixed Date</option>
                                            <option value="renewal">Renewal</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label for="amount">Amount <span class="text-danger">*</span></label>
                                        <div class="position-relative">
                                            <input type="number" class="form-control" id="amount" name="amount"
                                                readonly required>
                                            <small class="form-text text-muted">Amount is auto-filled from selected
                                                profile</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label for="active_date">Active Date</label>
                                        <div class="position-relative">
                                            <input name="active_date" type="date" class="form-control">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label for="ppn">PPN %</label>
                                        <div class="position-relative">
                                            <input name="ppn" type="number" class="form-control" id="ppn"
                                                min="0" max="100" step="0.01" placeholder="0">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="form-group">
                                        <label for="discount">Discount</label>
                                        <div class="position-relative">
                                            <input name="discount" type="number" class="form-control"
                                                id="discount" min="0" step="0.01" placeholder="0">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="payment_total">Payment Total</label>
                                <div class="position-relative">
                                    <input type="number" class="form-control" readonly id="payment_total"
                                        name="payment_total">
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between mt-3">
                            <button type="button" class="btn btn-outline-secondary prev-step">
                                <i class="fa-solid fa-arrow-left"></i> Back
                            </button>
                            <button type="button" class="btn btn-outline-primary next-step">
                                <i class="fa-solid fa-arrow-right"></i> Next
                            </button>
                        </div>
                    </div>

                    <!-- Step 4 Review -->
                    <div class="step-content d-none" id="step-4">
                        <h6>Review your data before submit</h6>
                        <p class="text-muted">Please review all information before submitting.</p>

                        <div class="card">
                            <div class="card-body">
                                <div id="reviewContent">
                                    <!-- Review content will be populated by JavaScript -->
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between mt-3">
                            <button type="button" class="btn btn-outline-secondary prev-step">
                                <i class="fa-solid fa-arrow-left"></i> Back
                            </button>
                            <button type="submit" class="btn btn-outline-success">
                                <i class="fa-solid fa-check"></i> Submit
                            </button>
                        </div>
                    </div>

                </form>
            </div>
        </div>
    </div>
</div>

<!-- Enhanced Styles -->
<style>
    /* Existing styles */
    .circle {
        width: 45px;
        height: 45px;
        line-height: 45px;
    }

    .step.completed .circle {
        background-color: #20c997 !important;
    }

    .step.active .circle {
        background-color: #007bff !important;
    }

    .review-section {
        border-left: 3px solid #007bff;
        padding-left: 15px;
        margin-bottom: 15px;
    }

    /* Validation styles */
    .is-invalid {
        border-color: #dc3545 !important;
        box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25) !important;
    }

    .invalid-feedback {
        display: block !important;
        width: 100%;
        margin-top: 0.25rem;
        font-size: 0.875rem;
        color: #dc3545;
    }

    /* Animation for validation errors */
    .is-invalid {
        animation: shake 0.5s ease-in-out;
    }

    @keyframes shake {

        0%,
        100% {
            transform: translateX(0);
        }

        25% {
            transform: translateX(-5px);
        }

        75% {
            transform: translateX(5px);
        }
    }

    /* Focus styles for better UX */
    .form-control:focus,
    .form-select:focus {
        border-color: #80bdff;
        outline: 0;
        box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
    }

    .is-invalid:focus {
        border-color: #dc3545;
        box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
    }

    /* Button states */
    .btn:disabled {
        opacity: 0.65;
        cursor: not-allowed;
    }

    /* Alert improvements */
    .alert {
        border-radius: 8px;
        border: none;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    /* Form improvements */
    .form-label {
        font-weight: 600;
        margin-bottom: 0.5rem;
        color: #495057;
    }

    .text-danger {
        color: #dc3545 !important;
    }

    /* Step content improvements */
    .step-content {
        min-height: 300px;
    }

    .step-content-inner {
        padding: 1rem 0;
    }

    /* Loading states */
    .loading-select {
        background-image: url("data:image/svg+xml,%3csvg width='16' height='16' viewBox='0 0 16 16' fill='none' xmlns='http://www.w3.org/2000/svg'%3e%3cpath d='M8 12a4 4 0 100-8 4 4 0 000 8zM8 0C3.58 0 0 3.58 0 8s3.58 8 8 8 8-3.58 8-8-3.58-8-8-8zm0 14c-3.31 0-6-2.69-6-6s2.69-6 6-6 6 2.69 6 6-2.69 6-6 6z' fill='%236c757d'/%3e%3cpath d='M8 4v4l3 1.5' stroke='%236c757d' stroke-linecap='round'/%3e%3c/svg%3e");
        background-repeat: no-repeat;
        background-position: right 0.75rem center;
        background-size: 16px 16px;
    }

    /* Better visual feedback for dependent selects */
    #odpSelect:disabled {
        background-color: #f8f9fa;
        opacity: 0.8;
    }

    /* Icon improvements */
    .fa-solid {
        margin-right: 5px;
    }

    /* Helper text styling */
    .form-text {
        font-size: 0.875rem;
        color: #6c757d !important;
        margin-top: 0.25rem;
    }

    .form-text .fa-solid {
        margin-right: 3px;
        color: #007bff;
    }
</style>

@push('script-page')
    <script src="{{ asset('js/pppoe-page-create.js') }}"></script>
@endpush
