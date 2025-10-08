@extends('layouts.admin')
@section('content')
    <div id="main">
        @include('includes.v_navbar')

        @if (session('success'))
            <div class="alert alert-light-success color-success">
                <i class="bi bi-check-circle"></i> {{ session('success') }}
            </div>
        @endif
        @if (session('error'))
            <div class="alert alert-light-danger color-danger">
                <i class="bi bi-exclamation-circle"></i> {{ session('error') }}
            </div>
        @endif

        <section class="row">
            <!-- WhatsApp Configuration Card -->
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0">WhatsApp Configuration</h6>
                    </div>
                    <hr>

                    <div class="row">
                        <div class="col-md-6">
                            <!-- API Key Form -->
                            <div class="mb-4">
                                <label for="whatsappApiKey" class="form-label">
                                    <strong>Wisender API Key</strong>
                                </label>
                                <form action="{{ route('whatsapp.saveapi') }}" method="post">
                                    @csrf
                                    <div class="input-group">
                                        <input name="apikey" type="text" class="form-control"
                                            placeholder="Enter your Wisender API Key" value="{{ $apiKey ?? '' }}">
                                        <button type="submit" class="btn btn-outline-primary" type="button">
                                            <i class="fa-solid fa-save"></i> Save
                                        </button>
                                    </div>
                                </form>
                                <small class="text-muted">Belum punya API key? Silahkan masuk <a
                                        href="https://wisender.amanisp.net.id/app/login" target="_blank"
                                        rel="noopener noreferrer">Wisender</a></small>
                            </div>

                            <!-- Status Display -->
                            <h6>Connection Status</h6>
                            <table class="table table-sm">
                                <tr>
                                    <td width="100">Status</td>
                                    <td>:</td>
                                    <td>
                                        <span id="connectionStatus"
                                            class="badge bg-secondary whatsapp-wait">Checking...</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Phone</td>
                                    <td>:</td>
                                    <td id="phoneNumber" class="whatsapp-wait">-</td>
                                </tr>
                                <tr>
                                    <td>Service</td>
                                    <td>:</td>
                                    <td>Wisender API</td>
                                </tr>
                                <tr>
                                    <td>Action</td>
                                    <td>:</td>
                                    <td id="statusAction" class="whatsapp-wait">-</td>
                                </tr>
                            </table>
                        </div>

                        <div class="col-md-6">
                            @if (!isset($apiKey) || !$apiKey)
                                <div class="alert alert-warning">
                                    <i class="fa-solid fa-exclamation-triangle"></i>
                                    <strong>Not Configured</strong><br>
                                    Enter your Wisender API key to start.
                                </div>
                            @else
                                <div class="alert alert-success">
                                    <i class="fa-solid fa-check-circle"></i>
                                    <strong>API Configured</strong><br>
                                    Ready to use WhatsApp features.
                                </div>
                            @endif

                            <div class="card">
                                <div class="card-body">
                                    <h6>Webhook URL</h6>
                                    <small class="text-muted">
                                        Set this URL in your Wisender dashboard:
                                    </small>
                                    <div class="bg-light p-2 rounded mt-2">
                                        <code style="font-size: 11px;">
                                            {{ url('/api/whatsapp/webhook/' . auth()->user()->group_id) }}
                                        </code>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <!-- Test & Actions -->
                    <div class="row">
                        <div class="col-md-6">
                            <label for="testPhone" class="form-label">Test Connection</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="testPhone" placeholder="Enter phone number">
                                <button class="btn btn-outline-primary" type="button" onclick="testConnection()"
                                    id="testBtn">
                                    <i class="fa-solid fa-paper-plane"></i> Test
                                </button>
                            </div>
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <div class="btn-group w-100 gap-1">
                                <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal"
                                    data-bs-target="#templateModal">
                                    <i class="fa-solid fa-list"></i> Templates
                                </button>
                                <button type="button" class="btn btn-outline-success" onclick="showBroadcastModal()">
                                    <i class="fa-solid fa-paper-plane"></i> Broadcast
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Message Logs Section -->
        <section class="row mt-4">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Message Logs</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table" id="logsTable">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Recipient</th>
                                    <th>Subject</th>
                                    <th>Message</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="logsTableBody">
                                <!-- Logs will be loaded here -->
                            </tbody>
                        </table>
                        <div id="logsLoader" class="text-center py-3 d-none">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </section>

        <!-- Broadcast Modal -->
        <div class="modal fade" id="broadcastModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Broadcast Message</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form action="{{ route('whatsapp.broadcast') }}" method="post">
                        @csrf
                        <div class="modal-body">
                            <div class="mb-3">
                                <div class="row">
                                    <div class="col">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="recipients"
                                                value="all" id="recipientAll" checked>
                                            <label class="form-check-label" for="recipientAll">All Members</label>
                                        </div>
                                    </div>
                                    <div class="col">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="recipients"
                                                value="active" id="recipientActive">
                                            <label class="form-check-label" for="recipientActive">Active Members</label>
                                        </div>
                                    </div>
                                    <div class="col">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="recipients"
                                                value="suspended" id="recipientSuspended">
                                            <label class="form-check-label" for="recipientSuspended">Suspended
                                                Members</label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="broadcastSubject" class="form-label">Subject</label>
                                <input required name="subject" type="text"
                                    class="form-control @error('subject')is-invalid @enderror" id="broadcastSubject"
                                    placeholder="Message subject">
                                @error('subject')
                                    <div class="invalid-feedback">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="broadcastMessage" class="form-label">Message</label>
                                <textarea required name="message" class="form-control @error('message')is-invalid @enderror" id="broadcastMessage"
                                    placeholder="Text Message"></textarea>
                                @error('message')
                                    <div class="invalid-feedback">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-danger" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-outline-primary">
                                <i class="fa-solid fa-paper-plane"></i> Send Broadcast
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Template Modal -->
        <!-- Template Modal -->
        <div class="modal fade template-modal" id="templateModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            Templates Message
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                            aria-label="Close"></button>
                    </div>

                    <div class="modal-body">
                        <div class="row g-4">
                            <!-- Left Panel - Template Content -->
                            <div class="col-lg-7">
                                <div class="template-content-panel">
                                    <!-- Template Type Selection -->
                                    <div class="mb-4">
                                        <label class="form-label-custom">
                                            Type
                                        </label>
                                        <select class="form-select template-select" id="templateTypeSelect"
                                            onchange="loadTemplateContent()">
                                            <option value="invoice_terbit">Invoice Terbit</option>
                                            <option value="payment_paid">Payment Paid</option>
                                            <option value="payment_cancel">Payment Cancel</option>
                                            <option value="account_suspend">Account Suspend</option>
                                            <option value="account_active">Account Active</option>
                                            <option value="invoice_reminder">Invoice Reminder</option>
                                            <option value="invoice_overdue">Invoice Overdue</option>
                                        </select>
                                    </div>

                                    <!-- Template Content -->
                                    <div class="mb-4 position-relative">
                                        <label class="form-label-custom">
                                            Text
                                            <span class="status-indicator status-default" id="templateStatus"></span>
                                        </label>
                                        <textarea class="form-control template-textarea" id="templateContent" rows="16"
                                            placeholder="Pilih template terlebih dahulu..." oninput="onTemplateChange()"></textarea>
                                    </div>

                                    <!-- Action Buttons -->
                                    <div class="template-actions">
                                        <button class="btn btn-outline-light btn-template" onclick="resetTemplate()"
                                            id="resetBtn">
                                            <i class="fas fa-undo"></i> Reset Default
                                        </button>
                                        <button class="btn btn-outline-success btn-template" onclick="saveTemplate()"
                                            id="saveBtn">
                                            Save Changes
                                        </button>

                                    </div>

                                    <!-- Template Info -->
                                    <div class="template-info">
                                        <small>
                                            <i class="fas fa-info-circle text-info"></i>
                                            Gunakan variabel dalam [kurung] untuk kustomisasi pesan.
                                            <strong>huruf tebal</strong> dan <em>huruf miring</em> dapat digunakan.
                                        </small>
                                    </div>
                                </div>
                            </div>

                            <!-- Right Panel - Variables -->
                            <div class="col-lg-5">
                                <div class="variables-panel">
                                    <h6 class="text-white fw-bold mb-3">
                                        Code Variable
                                    </h6>
                                    <!-- Variables List -->
                                    <div class="variables-list" id="variablesList">
                                        <!-- Customer Variables -->
                                        <div class="variable-category">
                                            <ul>
                                                <li class="variable-item">
                                                    <small><span class="variable-code text-warning">[fullname]</span> -
                                                        Nama
                                                        Pelanggan</small>
                                                </li>
                                                <li class="variable-item">
                                                    <small><span class="variable-code text-warning">[uid]</span> - ID
                                                        Pelanggan</small>
                                                </li>
                                                <li class="variable-item">
                                                    <small><span class="variable-code text-warning">[pppoe_user]</span> -
                                                        PPPoE
                                                        Username</small>
                                                </li>
                                                <li class="variable-item">
                                                    <small><span class="variable-code text-warning">[pppoe_pass]</span> -
                                                        PPPoE
                                                        Password</small>
                                                </li>
                                                <li class="variable-item">
                                                    <small><span class="variable-code text-warning">[pppoe_profile]</span>
                                                        - PPPoE
                                                        Profile</small>
                                                </li>
                                                <li class="variable-item">
                                                    <small><span class="variable-code text-warning">[no_invoice]</span> -
                                                        Nomor
                                                        Invoice</small>
                                                </li>
                                                <li class="variable-item">
                                                    <small><span class="variable-code text-warning">[invoice_date]</span> -
                                                        Tanggal
                                                        Invoice</small>
                                                </li>
                                                <li class="variable-item">
                                                    <small><span class="variable-code text-warning">[amount]</span> -
                                                        Jumlah
                                                        Tagihan</small>
                                                </li>
                                                <li class="variable-item">
                                                    <small><span class="variable-code text-warning">[ppn]</span> -
                                                        PPN</small>
                                                </li>
                                                <li class="variable-item">
                                                    <small><span class="variable-code text-warning">[discount]</span> -
                                                        Discount</small>
                                                </li>
                                                <li class="variable-item">
                                                    <small><span class="variable-code text-warning">[total]</span> - Jumlah
                                                        Total</small>
                                                </li>
                                                <li class="variable-item">
                                                    <small><span class="variable-code text-warning">[period]</span> -
                                                        Periode
                                                        Billing</small>
                                                </li>
                                                <li class="variable-item">
                                                    <small><span class="variable-code text-warning">[due_date]</span> -
                                                        Jatuh
                                                        Tempo</small>
                                                </li>
                                                <li class="variable-item">
                                                    <small><span
                                                            class="variable-code text-warning">[payment_gateway]</span> -
                                                        Payment
                                                        Gateway</small>
                                                </li>
                                                <li class="variable-item">
                                                    <small><span class="variable-code text-warning">[footer]</span> -
                                                        Footer
                                                        Perusahaan</small>
                                                </li>
                                            </ul>
                                            <span>Gunakan *contoh* agar teks menjadi tebal.</span><br>
                                            <span>Gunakan _contoh_ agar teks menjadi miring.</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Include the JavaScript -->

        @include('pages.whatsapp.script')
    </div>
@endsection
