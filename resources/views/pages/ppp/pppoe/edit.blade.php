<!-- edit.blade.php (FULL modal) -->
<div class="modal fade" id="formEditModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h1 class="modal-title fs-5">Edit Connection</h1>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="FormEdit" method="POST">
                @method('PUT')
                @csrf
                <div class="modal-body">
                    <!-- Connection Type (read-only but submitted via hidden) -->
                    <div class="mb-4">
                        <h6><i class="fa-solid fa-network-wired"></i> Connection Type</h6>
                        <hr>
                        <div class="mb-3">
                            <label class="form-label">Type User</label>
                            <input type="hidden" id="edit-typeUserHidden" name="type">
                            <select id="edit-typeUserSelect" class="form-select" disabled>
                                <option value="pppoe">PPPoE</option>
                                <option value="dhcp">DHCP</option>
                            </select>
                            <div class="form-text"><i class="fa-solid fa-lock"></i> Connection type cannot be changed
                                after creation</div>
                        </div>
                    </div>
                    <!-- Connection Credentials -->
                    <div class="mb-4">
                        <h6><i class="fa-solid fa-key"></i> Connection Credentials</h6>
                        <hr>

                        <!-- PPPoE Credentials (shown for PPPoE only) -->
                        <div id="edit-rowPppoeCreds" class="fade-transition">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Username <span class="text-danger">*</span></label>
                                    <input id="edit-username" name="username" type="text" class="form-control"
                                        autocomplete="new-username" placeholder="Username">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Password <span class="text-danger">*</span></label>
                                    <input id="edit-password" name="password" type="text" class="form-control"
                                        autocomplete="new-password" placeholder="Password">
                                </div>
                            </div>
                        </div>

                        <!-- DHCP Credentials (MAC) -->
                        <div id="edit-rowMac" class="fade-transition d-none">
                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <label class="form-label">MAC Address <span class="text-danger">*</span></label>
                                    <input id="edit-macAddress" name="mac_address" type="text"
                                        class="form-control mac-format" placeholder="xx:xx:xx:xx:xx:xx" maxlength="17">
                                    <div class="form-text">Format: 00:1A:2B:3C:4D:5E or 00-1A-2B-3C-4D-5E</div>
                                </div>
                            </div>
                        </div>

                    </div>
                    <!-- Service Configuration -->
                    <div class="mb-4">
                        <h6><i class="fa-solid fa-cog"></i> Service Configuration</h6>
                        <hr>
                        <div class="mb-3">
                            <label class="form-label">Assign Profile <span class="text-danger">*</span></label>
                            <select name="profile_id" class="form-select" id="edit-profileSelect" required>
                                <option value="">Select Profile</option>
                                @foreach ($profile as $prof)
                                    <option value="{{ $prof->id }}" data-price="{{ $prof->price }}"
                                        data-name="{{ $prof->name }}">
                                        {{ $prof->name }} — Rp {{ number_format($prof->price, 0, ',', '.') }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <!-- Location Information -->
                    <div class="mb-4">
                        <h6><i class="fa-solid fa-map-marker-alt"></i> Location Information</h6>
                        <hr>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Area</label>
                                <select name="area_id" class="form-select" id="edit-area">
                                    <option value="">Select Area</option>
                                    @foreach ($area as $areas)
                                        {{-- ensure you pass $areas or $odp that represents areas --}}
                                        <option value="{{ $areas->id }}">
                                            {{ $areas->name ?? ($areas->title ?? 'Area ' . $areas->id) }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">ODP</label>
                                <select name="optical_id" class="form-select" id="edit-odp">
                                    <option value="">Select Area First</option>
                                    @foreach ($odp as $optical)
                                        <option value="{{ $optical->id }}" data-area="{{ $optical->area_id }}">
                                            {{ $optical->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                </div> <!-- /.modal-body -->

                <div class="modal-footer">
                    <div class="btn-group gap-1">
                        <button type="button" class="btn btn-outline-danger" data-bs-dismiss="modal"><i
                                class="fa-solid fa-times"></i> Cancel</button>
                        <button type="submit" class="btn btn-outline-primary"><i class="fa-solid fa-save"></i>
                            Update
                            Connection</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>



<style>
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

    .form-label {
        font-weight: 600;
        margin-bottom: 0.5rem;
        color: #495057;
    }

    .text-danger {
        color: #dc3545 !important;
    }

    .alert {
        border-radius: 8px;
        border: none;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .fade-transition {
        transition: all 0.3s ease-in-out;
    }

    .mac-format {
        text-transform: lowercase;
    }

    /* Style for disabled/read-only connection type */
    .form-select:disabled {
        background-color: #f8f9fa;
        border-color: #e9ecef;
        opacity: 0.8;
        cursor: not-allowed;
    }

    .form-text {
        font-size: 0.875rem;
        color: #6c757d;
    }

    .form-text .fa-lock {
        color: #ffc107;
    }

    .modal-backdrop {
        opacity: 0.5 !important;
        transition: opacity 0.15s ease-out !important;
    }

    .modal-backdrop.fade.show {
        opacity: 0.5 !important;
    }

    /* Ensure modal closes properly */
    .modal.fade.show {
        opacity: 1 !important;
        transition: opacity 0.15s ease-out !important;
    }

    /* Fix untuk body saat modal ditutup */
    body.modal-open {
        overflow: hidden !important;
    }

    body:not(.modal-open) {
        overflow: auto !important;
        padding-right: 0 !important;
    }

    /* Fix untuk modal yang stuck */
    .modal {
        z-index: 1055 !important;
    }

    .modal-backdrop {
        z-index: 1050 !important;
    }

    /* Prevent scroll issues */
    .modal-body {
        max-height: calc(100vh - 200px);
        overflow-y: auto;
    }

    /* Fix untuk close button */
    .modal-header .btn-close {
        padding: 0.5rem 0.5rem;
        margin: -0.5rem -0.5rem -0.5rem auto;
        background: transparent;
        border: 0;
        border-radius: 0.25rem;
        opacity: 0.5;
        cursor: pointer;
        transition: opacity 0.15s ease-in-out;
    }

    .modal-header .btn-close:hover {
        opacity: 0.75;
    }

    .modal-header .btn-close:focus {
        opacity: 1;
        outline: 0;
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
    }

    /* Ensure modal animation works properly */
    .modal.fade {
        transition: opacity 0.15s linear !important;
    }

    .modal.fade:not(.show) {
        opacity: 0 !important;
    }

    .modal.fade.show {
        opacity: 1 !important;
    }

    /* Additional backdrop fix */
    .modal-backdrop.fade {
        transition: opacity 0.15s linear !important;
    }

    .modal-backdrop:not(.show) {
        opacity: 0 !important;
    }

    .modal-backdrop.show {
        opacity: 0.5 !important;
    }
</style>

@push('script-page')
    <script src="{{ asset('js/ppoe-page-edit.js') }}"></script>
@endpush
