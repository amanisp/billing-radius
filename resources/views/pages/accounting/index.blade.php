@extends('layouts.admin')
@section('content')
    <div id="main">
        {{-- Navbar --}}
        @include('includes.v_navbar')

        @if (session('success'))
            <div class="alert alert-light-success color-danger"><i class="bi bi-exclamation-circle"></i>
                {{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-light-danger color-danger"><i class="bi bi-exclamation-circle"></i>
                {{ session('error') }}</div>
        @endif

        <div class="page-heading">
            <div class="page-title">
                <div class="row">
                    <div class="col-12 col-md-6 order-md-1 order-last">
                        <h3>Pembukuan</h3>
                        <p class="text-subtitle text-muted">Riwayat pemasukan dan pengeluaran</p>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <section class="section">
                <div class="row">
                    <!-- PEMASUKAN -->
                    <div class="col-12 col-lg-3">
                        <div class="card">
                            <div class="card-body px-4 py-4-5">
                                <div class="row">
                                    <div class="col-md-4 col-lg-12 col-xl-12 col-xxl-5 d-flex justify-content-start">
                                        <div class="stats-icon green mb-2">
                                            <i class="fa-solid fa-arrow-trend-up"></i>
                                        </div>
                                    </div>
                                    <div class="col-md-8 col-lg-12 col-xl-12 col-xxl-7">
                                        <h6 class="text-muted font-semibold">Pemasukan Bulan Ini</h6>
                                        <h6 class="font-extrabold mb-0 text-success">Rp {{ number_format($monthlyIncome, 0, ',', '.') }}</h6>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- PENGELUARAN -->
                    <div class="col-12 col-lg-3">
                        <div class="card">
                            <div class="card-body px-4 py-4-5">
                                <div class="row">
                                    <div class="col-md-4 col-lg-12 col-xl-12 col-xxl-5 d-flex justify-content-start">
                                        <div class="stats-icon red mb-2">
                                            <i class="fa-solid fa-arrow-trend-down"></i>
                                        </div>
                                    </div>
                                    <div class="col-md-8 col-lg-12 col-xl-12 col-xxl-7">
                                        <h6 class="text-muted font-semibold">Pengeluaran Bulan Ini</h6>
                                        <h6 class="font-extrabold mb-0 text-danger">Rp {{ number_format($monthlyExpense, 0, ',', '.') }}</h6>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- PROFIT -->
                    <div class="col-12 col-lg-3">
                        <div class="card">
                            <div class="card-body px-4 py-4-5">
                                <div class="row">
                                    <div class="col-md-4 col-lg-12 col-xl-12 col-xxl-5 d-flex justify-content-start">
                                        <div class="stats-icon {{ $monthlyProfit >= 0 ? 'blue' : 'orange' }} mb-2">
                                            <i class="fa-solid fa-chart-line"></i>
                                        </div>
                                    </div>
                                    <div class="col-md-8 col-lg-12 col-xl-12 col-xxl-7">
                                        <h6 class="text-muted font-semibold">Profit Bulan Ini</h6>
                                        <h6 class="font-extrabold mb-0 {{ $monthlyProfit >= 0 ? 'text-primary' : 'text-warning' }}">
                                            Rp {{ number_format($monthlyProfit, 0, ',', '.') }}
                                        </h6>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TOTAL TRANSAKSI -->
                    <div class="col-12 col-lg-3">
                        <div class="card">
                            <div class="card-body px-4 py-4-5">
                                <div class="row">
                                    <div class="col-md-4 col-lg-12 col-xl-12 col-xxl-5 d-flex justify-content-start">
                                        <div class="stats-icon purple mb-2">
                                            <i class="fa-solid fa-receipt"></i>
                                        </div>
                                    </div>
                                    <div class="col-md-8 col-lg-12 col-xl-12 col-xxl-7">
                                        <h6 class="text-muted font-semibold">Total Transaksi</h6>
                                        <h6 class="font-extrabold mb-0">{{ $totalTransactions }}</h6>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Breakdown Pemasukan -->
                <div class="row mt-3">
                    <div class="col-12 col-lg-4">
                        <div class="card">
                            <div class="card-body px-4 py-4-5">
                                <h6 class="text-muted font-semibold mb-3">Breakdown Pemasukan</h6>
                                <div class="mb-2">
                                    <small class="text-muted">💵 Tunai</small>
                                    <h6 class="font-bold">Rp {{ number_format($monthlyCash, 0, ',', '.') }}</h6>
                                </div>
                                <div class="mb-2">
                                    <small class="text-muted">🏦 Transfer Bank</small>
                                    <h6 class="font-bold">Rp {{ number_format($monthlyTransfer, 0, ',', '.') }}</h6>
                                </div>
                                <div>
                                    <small class="text-muted">💳 Payment Gateway</small>
                                    <h6 class="font-bold">Rp {{ number_format($monthlyGateway, 0, ',', '.') }}</h6>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Data Table Section -->
            <section class="row">
                <div class="card">
                    <div class="card-header">
                        <div class="row">
                            <div class="col-md-6">
                                <h5 class="card-title">Riwayat Transaksi</h5>
                            </div>
                            <div class="col-md-6 text-end">
                                <button class="btn btn-success me-2" id="btn-add-income">
                                    <i class="fa-solid fa-plus"></i> Pemasukan Lain
                                </button>
                                <button class="btn btn-danger me-2" id="btn-add-expense">
                                    <i class="fa-solid fa-plus"></i> Pengeluaran
                                </button>
                                <button class="btn btn-primary" id="btn-export">
                                    <i class="fa-solid fa-file-excel"></i> Export CSV
                                </button>
                            </div>
                        </div>
                        <hr>
                        <!-- Filters -->
                        <div class="filters">
                            <div class="row">
                                <div class="col-md-2 mb-2">
                                    <select class="form-select" id="filter-type">
                                        <option value="">Semua Tipe</option>
                                        <option value="income">Pemasukan</option>
                                        <option value="expense">Pengeluaran</option>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-2">
                                    <select class="form-select" id="filter-category">
                                        <option value="">Semua Kategori</option>
                                        <optgroup label="Pemasukan">
                                            <option value="subscription_payment">Pembayaran Langganan</option>
                                            <option value="installation_fee">Biaya Pemasangan</option>
                                            <option value="late_fee">Denda Keterlambatan</option>
                                            <option value="other_income">Pemasukan Lain</option>
                                        </optgroup>
                                        <optgroup label="Pengeluaran">
                                            <option value="salary">Gaji Karyawan</option>
                                            <option value="installation_cost">Biaya Pasang Baru</option>
                                            <option value="equipment_repair">Perbaikan Alat</option>
                                            <option value="bandwidth">Biaya Bandwidth</option>
                                            <option value="collector_fee">Bayar Kang Tagih</option>
                                            <option value="utility">Listrik/PDAM/Pulsa</option>
                                            <option value="marketing">Marketing</option>
                                            <option value="other_expense">Pengeluaran Lain</option>
                                        </optgroup>
                                    </select>
                                </div>
                                <div class="col-md-2 mb-2">
                                    <select class="form-select" id="filter-payment">
                                        <option value="">Semua Metode</option>
                                        <option value="cash">Tunai</option>
                                        <option value="bank_transfer">Transfer Bank</option>
                                        <option value="payment_gateway">Payment Gateway</option>
                                    </select>
                                </div>
                                <div class="col-md-2 mb-2">
                                    <input type="date" class="form-control" id="filter-date-from" placeholder="Dari Tanggal">
                                </div>
                                <div class="col-md-2 mb-2">
                                    <input type="date" class="form-control" id="filter-date-to" placeholder="Sampai Tanggal">
                                </div>
                                <div class="col-md-1 mb-2">
                                    <button class="btn btn-outline-secondary w-100" id="btn-reset-filter">
                                        <i class="fa-solid fa-rotate"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- DataTable -->
                        <div class="table-responsive">
                            <table class="table table-striped" id="table-accounting">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Tanggal</th>
                                        <th>Tipe</th>
                                        <th>Kategori</th>
                                        <th>Referensi</th>
                                        <th>Pihak Terkait</th>
                                        <th>Jumlah</th>
                                        <th>Metode</th>
                                        <th>User</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <!-- MODAL DETAIL -->
    <div class="modal fade" id="modalDetail" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detail Transaksi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <strong>Tipe Transaksi:</strong>
                            <p id="detail_type"></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>Kategori:</strong>
                            <p id="detail_category"></p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <strong>Jumlah:</strong>
                            <p id="detail_amount" class="fw-bold"></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>Tanggal:</strong>
                            <p id="detail_date"></p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <strong>Referensi/Invoice:</strong>
                            <p id="detail_reference"></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>Pihak Terkait:</strong>
                            <p id="detail_party"></p>
                        </div>
                    </div>
                    <div class="row" id="detail_payment_section">
                        <div class="col-md-6 mb-3">
                            <strong>Metode Pembayaran:</strong>
                            <p id="detail_payment_method"></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>User:</strong>
                            <p id="detail_user"></p>
                        </div>
                    </div>
                    <div class="mb-3">
                        <strong>Deskripsi:</strong>
                        <p id="detail_description"></p>
                    </div>
                    <div class="mb-3">
                        <strong>Catatan:</strong>
                        <p id="detail_notes"></p>
                    </div>
                    <div class="mb-3">
                        <strong>Nomor Bukti:</strong>
                        <p id="detail_receipt"></p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL ADD EXPENSE -->
    <div class="modal fade" id="modalAddExpense" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Pengeluaran</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="form-expense">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Kategori <span class="text-danger">*</span></label>
                            <select class="form-select" name="category" required>
                                <option value="">Pilih Kategori</option>
                                <option value="salary">Gaji Karyawan</option>
                                <option value="installation_cost">Biaya Pasang Baru</option>
                                <option value="equipment_repair">Perbaikan Alat</option>
                                <option value="bandwidth">Biaya Bandwidth</option>
                                <option value="collector_fee">Bayar Kang Tagih</option>
                                <option value="utility">Listrik/PDAM/Pulsa</option>
                                <option value="marketing">Marketing</option>
                                <option value="other_expense">Pengeluaran Lain</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Jumlah <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="amount" required min="0" step="0.01">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tanggal <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="transaction_date" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Deskripsi <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="description" required maxlength="255">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nomor Bukti (Opsional)</label>
                            <input type="text" class="form-control" name="receipt_number" maxlength="100">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Catatan (Opsional)</label>
                            <textarea class="form-control" name="notes" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-danger">Simpan Pengeluaran</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL ADD OTHER INCOME -->
    <div class="modal fade" id="modalAddIncome" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Pemasukan Lain</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="form-income">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Kategori <span class="text-danger">*</span></label>
                            <select class="form-select" name="category" required>
                                <option value="">Pilih Kategori</option>
                                <option value="installation_fee">Biaya Pemasangan</option>
                                <option value="late_fee">Denda Keterlambatan</option>
                                <option value="other_income">Pemasukan Lain</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Jumlah <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="amount" required min="0" step="0.01">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Metode Pembayaran <span class="text-danger">*</span></label>
                            <select class="form-select" name="payment_method" required>
                                <option value="">Pilih Metode</option>
                                <option value="cash">Tunai</option>
                                <option value="bank_transfer">Transfer Bank</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tanggal <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="transaction_date" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Deskripsi <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="description" required maxlength="255">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nama Pelanggan (Opsional)</label>
                            <input type="text" class="form-control" name="member_name" maxlength="255">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nomor Bukti (Opsional)</label>
                            <input type="text" class="form-control" name="receipt_number" maxlength="100">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Catatan (Opsional)</label>
                            <textarea class="form-control" name="notes" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-success">Simpan Pemasukan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('script-page')
    <script>
    $(document).ready(function() {
        // Set default date to today for date inputs
        const today = new Date().toISOString().split('T')[0];
        $('input[name="transaction_date"]').val(today);

        // Initialize DataTable
        const table = $('#table-accounting').DataTable({
            processing: true,
            serverSide: true,
            responsive: true,
            autoWidth: false,
            ajax: {
                url: '{{ route("accounting.getData") }}',
                data: function(d) {
                    d.transaction_type = $('#filter-type').val();
                    d.category = $('#filter-category').val();
                    d.payment_method = $('#filter-payment').val();
                    d.date_from = $('#filter-date-from').val();
                    d.date_to = $('#filter-date-to').val();
                }
            },
            columns: [
                { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
                { data: 'transaction_date_formatted', name: 'transaction_date' },
                { data: 'type_badge', name: 'transaction_type' },
                { data: 'category_label', name: 'category' },
                { data: 'reference', name: 'reference', orderable: false },
                { data: 'party_name', name: 'member_name' },
                { data: 'formatted_amount', name: 'amount' },
                { data: 'payment_method_label', name: 'payment_method' },
                { data: 'user_name', name: 'user_name', orderable: false },
                { data: 'action', name: 'action', orderable: false, searchable: false }
            ],
            order: [[1, 'desc']]
        });

        // Filter handlers
        $('#filter-type, #filter-category, #filter-payment, #filter-date-from, #filter-date-to').on('change', function() {
            table.ajax.reload();
        });

        $('#btn-reset-filter').on('click', function() {
            $('#filter-type, #filter-category, #filter-payment').val('');
            $('#filter-date-from, #filter-date-to').val('');
            table.ajax.reload();
        });

        // Detail Button
        $(document).on('click', '.btn-detail', function() {
            const id = $(this).data('id');

            $.ajax({
                url: `/accounting/show/${id}`,
                type: 'GET',
                success: function(response) {
                    if (response.success) {
                        const data = response.data;

                        // Type badge
                        const typeBadge = data.transaction_type === 'income'
                            ? '<span class="badge bg-success">Pemasukan</span>'
                            : '<span class="badge bg-danger">Pengeluaran</span>';

                        $('#detail_type').html(typeBadge);
                        $('#detail_category').text(data.category_label);

                        // Amount with color
                        const amountColor = data.transaction_type === 'income' ? 'text-success' : 'text-danger';
                        const amountSign = data.transaction_type === 'income' ? '+' : '-';
                        $('#detail_amount').html(`<span class="${amountColor}">${amountSign} ${data.formatted_amount}</span>`);

                        $('#detail_date').text(data.transaction_date);
                        $('#detail_reference').text(data.invoice_number || data.description || '-');
                        $('#detail_party').text(data.member_name || '-');
                        $('#detail_payment_method').text(data.payment_method_label || '-');
                        $('#detail_user').text(data.user_name || '-');
                        $('#detail_description').text(data.description || '-');
                        $('#detail_notes').text(data.notes || '-');
                        $('#detail_receipt').text(data.receipt_number || '-');

                        // Show/hide payment section for expenses
                        if (data.transaction_type === 'expense') {
                            $('#detail_payment_section').hide();
                        } else {
                            $('#detail_payment_section').show();
                        }

                        $('#modalDetail').modal('show');
                    }
                },
                error: function(xhr) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Gagal mengambil detail transaksi'
                    });
                }
            });
        });

        // Add Expense Button
        $('#btn-add-expense').on('click', function() {
            $('#form-expense')[0].reset();
            $('input[name="transaction_date"]').val(today);
            $('#modalAddExpense').modal('show');
        });

        // Add Income Button
        $('#btn-add-income').on('click', function() {
            $('#form-income')[0].reset();
            $('input[name="transaction_date"]').val(today);
            $('#modalAddIncome').modal('show');
        });

        // Submit Expense Form
        $('#form-expense').on('submit', function(e) {
            e.preventDefault();

            const formData = $(this).serialize();

            $.ajax({
                url: '{{ route("accounting.storeExpense") }}',
                type: 'POST',
                data: formData,
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                success: function(response) {
                    if (response.success) {
                        $('#modalAddExpense').modal('hide');
                        table.ajax.reload();

                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil',
                            text: response.message,
                            timer: 2000,
                            showConfirmButton: false
                        });
                    }
                },
                error: function(xhr) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: xhr.responseJSON?.message || 'Gagal menyimpan pengeluaran'
                    });
                }
            });
        });

        // Submit Income Form
        $('#form-income').on('submit', function(e) {
            e.preventDefault();

            const formData = $(this).serialize();

            $.ajax({
                url: '{{ route("accounting.storeOtherIncome") }}',
                type: 'POST',
                data: formData,
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                success: function(response) {
                    if (response.success) {
                        $('#modalAddIncome').modal('hide');
                        table.ajax.reload();

                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil',
                            text: response.message,
                            timer: 2000,
                            showConfirmButton: false
                        });
                    }
                },
                error: function(xhr) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: xhr.responseJSON?.message || 'Gagal menyimpan pemasukan'
                    });
                }
            });
        });

        // Delete Transaction
        $(document).on('click', '.btn-delete', function() {
            const id = $(this).data('id');

            Swal.fire({
                title: 'Konfirmasi Hapus',
                text: 'Apakah Anda yakin ingin menghapus transaksi ini?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: `/accounting/destroy/${id}`,
                        type: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        success: function(response) {
                            if (response.success) {
                                table.ajax.reload();

                                Swal.fire({
                                    icon: 'success',
                                    title: 'Berhasil',
                                    text: response.message,
                                    timer: 2000,
                                    showConfirmButton: false
                                });
                            }
                        },
                        error: function(xhr) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: xhr.responseJSON?.message || 'Gagal menghapus transaksi'
                            });
                        }
                    });
                }
            });
        });

        // Export CSV
        $('#btn-export').on('click', function() {
            const type = $('#filter-type').val();
            const dateFrom = $('#filter-date-from').val();
            const dateTo = $('#filter-date-to').val();

            let url = '{{ route("accounting.export") }}?';
            const params = [];

            if (type) params.push(`transaction_type=${type}`);
            if (dateFrom) params.push(`date_from=${dateFrom}`);
            if (dateTo) params.push(`date_to=${dateTo}`);

            url += params.join('&');
            window.location.href = url;
        });
    });
    </script>
    @endpush
@endsection
