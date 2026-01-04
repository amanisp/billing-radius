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

        {{-- @if (in_array(Auth::user()->role, ['mitra', 'kasir'])) --}}
        @include('pages.billing.invoices.create')
        @include('pages.billing.invoices.detail')
        {{-- @endif --}}

        <div class="row">
            <div class="col-md">
                <div class="card">
                    <div class="status-card total">
                        <div class="stats-icon green mb-1 rounded-circle">
                            <i class="fa-solid text-white fa-file-invoice-dollar"></i>
                        </div>
                        <div class="d-flex flex-column">
                            <small class="status-number">Monthly Invoice</small>
                            <small class="fs-4 status-number">{{ $invoiceCount }}</small>
                            <small class="text-muted">Rp
                                {{ number_format($invoiceTotal, 0, ',', '.') }}
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md">
                <div class="card">
                    <div class="status-card active">
                        <div class="stats-icon bg-danger mb-1 rounded-circle">
                            <i class="fa-solid text-white fa-hourglass-start"></i>
                        </div>
                        <div class="d-flex flex-column">
                            <small class="status-number">Total Overdue</small>
                            <small class="fs-4 status-number">{{ $overdueCount }}</small>
                            <small class="text-muted">Rp
                                {{ number_format($overdueTotal, 0, ',', '.') }}</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md">
                <div class="card">
                    <div class="status-card suspend">
                        <div class="stats-icon bg-warning mb-1 rounded-circle">
                            <i class="fa-solid text-white fa-money-check-dollar"></i>
                        </div>
                        <div class="d-flex flex-column">
                            <small class="status-number">Total Unpaid</small>
                            <small class="fs-4 status-number">{{ $unpaidCount }}</small>
                            <small class="text-muted">Rp
                                {{ number_format($unpaidTotal, 0, ',', '.') }}
                            </small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md">
                <div class="card">
                    <div class="status-card suspend">
                        <div class="stats-icon bg-primary mb-1 rounded-circle">
                            <i class="fa-solid text-white fa-money-check-dollar"></i>
                        </div>
                        <div class="d-flex flex-column">
                            <small class="status-number">Total Invoice Paid</small>
                            <small class="fs-4 status-number">{{ $paidCount }}</small>
                            <small class="text-muted">Rp
                                {{ number_format($paidTotal, 0, ',', '.') }}
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <section class="row">
            <div class="card">
                <div class="card-header">
                    {{-- Only show create button for Mitra and Kasir --}}
                    @if (in_array(Auth::user()->role, ['mitra']))
                        <div class="btn-group d-flex flex-wrap flex-md-row flex-column gap-2 w-100">
                            <button class="btn btn-outline-warning btn-sm flex-fill py-2" id="generateAllBtn">
                                <i class="fa-solid fa-file-invoice-dollar"></i> Generate All Invoice
                            </button>

                            <button class="btn btn-outline-primary btn-sm flex-fill py-2" data-bs-toggle="modal"
                                data-bs-target="#formCreateModal">
                                <i class="fa-solid fa-file-invoice-dollar"></i> Create Invoice
                            </button>

                            <button class="btn btn-outline-success btn-sm flex-fill py-2" onclick="alert('Coming Soon')">
                                <i class="fa-solid fa-file-invoice-dollar"></i> Export
                            </button>
                        </div>

                        <hr>
                    @endif
                    <div class="filters">
                        <div class="row">
                            <div class="col-12 d-flex gap-2 flex-wrap align-items-center">
                                <select id="statusFilter" class="form-select" style="max-width: 180px;">
                                    <option value="">All Status</option>
                                    <option value="paid">Paid</option>
                                    <option value="unpaid">Unpaid</option>
                                </select>
                                <select id="typeFilter" class="form-select" style="max-width: 180px;">
                                    <option value="">All Type</option>
                                    <option value="prabayar">Prabayar</option>
                                    <option value="pascabayar">Pascabayar</option>
                                </select>
                                <select id="areaFilter" class="form-select" style="max-width: 180px;">
                                    <option value="">All Area</option>
                                </select>
                            </div>
                            <div class="col-12 d-flex gap-2 flex-wrap align-items-center mt-2">
                                <div class="d-flex gap-2 align-items-center">
                                    <label class="text-nowrap mb-0 fw-semibold">Start:</label>
                                    <input type="date" id="dateFrom" class="form-control" style="max-width: 160px;">
                                </div>
                                <div class="d-flex gap-2 align-items-center">
                                    <label class="text-nowrap mb-0 fw-semibold">End:</label>
                                    <input type="date" id="dateTo" class="form-control" style="max-width: 160px;">
                                </div>
                                <button id="resetFilters" class="btn btn-outline-secondary btn-sm">
                                    <i class="fa-solid fa-rotate-right"></i> Reset
                                </button>
                            </div>
                        </div>

                    </div>
                </div>
                <hr>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table" id="dataTables">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Nama</th>
                                    <th>Area</th>
                                    <th>Nomor Invoice</th>
                                    <th>Invoice Date</th>
                                    <th>Due Date</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th>Type</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        @push('script-page')
            <script>
                $(document).ready(function() {
                    const userRole = "{{ Auth::user()->role }}";

                    // Set default date range to current month
                    const today = new Date();
                    const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
                    const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);

                    const formatDate = (date) => {
                        const year = date.getFullYear();
                        const month = String(date.getMonth() + 1).padStart(2, '0');
                        const day = String(date.getDate()).padStart(2, '0');
                        return `${year}-${month}-${day}`;
                    };

                    $('#dateFrom').val(formatDate(firstDay));
                    $('#dateTo').val(formatDate(lastDay));

                    // Update statistics based on date range
                    function updateStatistics() {
                        $.ajax({
                            url: '/billing/stats/daterange',
                            type: 'GET',
                            data: {
                                date_from: $('#dateFrom').val(),
                                date_to: $('#dateTo').val()
                            },
                            success: function(stats) {
                                // Update Monthly Invoice card
                                $('.status-card.total .fs-4').text(stats.total_invoices || 0);
                                $('.status-card.total .text-muted').text('Rp ' + formatNumber(stats
                                    .total_amount || 0));

                                // Update Overdue card
                                $('.status-card.active .fs-4').text(stats.overdue_count || 0);
                                $('.status-card.active .text-muted').text('Rp ' + formatNumber(stats
                                    .overdue_amount || 0));

                                // Update Unpaid card
                                $('.status-card.suspend:has(.bg-warning) .fs-4').text(stats.unpaid_count || 0);
                                $('.status-card.suspend:has(.bg-warning) .text-muted').text('Rp ' +
                                    formatNumber(stats.unpaid_amount || 0));

                                // Update Paid card
                                $('.status-card.suspend:has(.bg-primary) .fs-4').text(stats.paid_count || 0);
                                $('.status-card.suspend:has(.bg-primary) .text-muted').text('Rp ' +
                                    formatNumber(stats.paid_amount || 0));
                            },
                            error: function() {
                                console.log('Failed to load statistics');
                            }
                        });
                    }

                    function formatNumber(num) {
                        return new Intl.NumberFormat('id-ID').format(num);
                    }

                    // Populate area filter options
                    $.ajax({
                        url: '/areas/list',
                        type: 'GET',
                        success: function(areas) {
                            areas.forEach(function(area) {
                                $('#areaFilter').append(
                                    `<option value="${area.id}">${area.name}</option>`);
                            });
                        }
                    });

                    // Initialize DataTable
                    let table = $('#dataTables').DataTable({
                        processing: true,
                        serverSide: false,
                        responsive: true,
                        autoWidth: false,
                        columnDefs: [{
                            targets: '_all',
                            className: 'text-nowrap'
                        }],
                        ajax: {
                            url: '/billing/unpaid/read',
                            type: 'GET',
                            headers: {
                                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                            },
                            data: function(d) {
                                d.status = $('#statusFilter').val();
                                d.type = $('#typeFilter').val();
                                d.area = $('#areaFilter').val();
                                d.date_from = $('#dateFrom').val();
                                d.date_to = $('#dateTo').val();
                            }
                        },
                        columns: [{
                                data: 'DT_RowIndex',
                                orderable: false,
                                searchable: false
                            }, {
                                data: 'name',
                            }, {
                                data: 'area',
                            }, {
                                data: 'inv_number',
                            },
                            {
                                data: 'invoice_date',
                            },
                            {
                                data: 'due_date',
                            },
                            {
                                data: 'total',
                            },
                            {
                                data: 'status',
                            },
                            {
                                data: 'type',
                            },
                            {
                                data: 'action',
                            },
                        ]
                    });

                    // Filter change events
                    $('#statusFilter, #typeFilter, #areaFilter, #dateFrom, #dateTo').on('change', function() {
                        table.ajax.reload();
                        updateStatistics();
                    });

                    // Reset filters
                    $('#resetFilters').on('click', function() {
                        $('#statusFilter').val('');
                        $('#typeFilter').val('');
                        $('#areaFilter').val('');
                        $('#dateFrom').val(formatDate(firstDay));
                        $('#dateTo').val(formatDate(lastDay));
                        table.ajax.reload();
                        updateStatistics();
                    });

                    // Initial statistics load
                    updateStatistics();

                    // Payment Cancel Handler
                    $(document).on('click', '#payment-cancel', function() {
                        let id = $(this).data('id');
                        let name = $(this).data('name');
                        let inv = $(this).data('inv');

                        Swal.fire({
                            title: "Payment Cancellation!",
                            text: `Invoice ${inv} - a.n ${name}`,
                            icon: "warning",
                            showCancelButton: true,
                            confirmButtonColor: "#3085d6",
                            cancelButtonColor: "#acacac",
                            confirmButtonText: "Yes, Cancel",
                        }).then((result) => {
                            if (result.isConfirmed) {
                                $.ajax({
                                    url: "/billing/paid/cancel",
                                    type: "POST",
                                    headers: {
                                        "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr(
                                            "content")
                                    },
                                    data: {
                                        id: id
                                    },
                                    success: function(response) {
                                        Toastify({
                                            text: "Pembayaran Berhasil Dibatalkan!",
                                            className: "success",
                                        }).showToast();
                                        table.ajax.reload();
                                        updateStatistics();
                                    },
                                    error: function(error) {
                                        console.log(error)
                                        Toastify({
                                            text: "Terjadi kesalahan saat pembayaran!",
                                            className: "error",
                                        }).showToast();
                                    }
                                });
                            }
                        });
                    });

                    // Payment Handler
                    $(document).on('click', '#btn-pay', function() {
                        let id = $(this).data('id');
                        let name = $(this).data('name');
                        let inv = $(this).data('inv');

                        Swal.fire({
                            title: "Konfirmasi Pembayaran",
                            html: `
                    <small>Invoice <strong>${inv}</strong> - a.n <strong>${name}</strong></small>
                    <select id="payment-method" class="swal2-select">
                        <option selected disabled value="">Pilih Metode Pembayaran</option>
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="cash">Cash</option>
                    </select>
                    <div id="error-message" style="color: red; font-size: 12px; margin-top: 5px;"></div>
                `,
                            icon: "warning",
                            showCancelButton: true,
                            confirmButtonColor: "#3085d6",
                            cancelButtonColor: "#d33",
                            confirmButtonText: "Yes, Paid",
                            preConfirm: () => {
                                let paymentMethod = document.getElementById('payment-method').value;

                                if (!paymentMethod) {
                                    document.getElementById('error-message').innerText =
                                        "Silakan pilih metode pembayaran!";
                                    return false;
                                }

                                return {
                                    paymentMethod: paymentMethod
                                };
                            }
                        }).then((result) => {
                            if (result.isConfirmed) {
                                let paymentMethod = result.value.paymentMethod;

                                $.ajax({
                                    url: "/billing/unpaid/pay",
                                    type: "POST",
                                    headers: {
                                        "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr(
                                            "content")
                                    },
                                    data: {
                                        payment_method: paymentMethod,
                                        id: id
                                    },
                                    success: function(response) {
                                        Toastify({
                                            text: "Pembayaran Berhasil!",
                                            className: "success",
                                        }).showToast();
                                        table.ajax.reload();
                                        updateStatistics();
                                    },
                                    error: function(error) {
                                        Toastify({
                                            text: "Terjadi kesalahan saat pembayaran!",
                                            className: "error",
                                        }).showToast();
                                    }
                                });
                            }
                        });
                    });

                    // Member DataTable for Create Invoice Modal
                    let tableMember;

                    $(document).on('click', '[data-bs-target="#formCreateModal"]', function() {
                        if ($.fn.DataTable.isDataTable('#dataMember')) {
                            tableMember.destroy();
                        }

                        tableMember = $('#dataMember').DataTable({
                            processing: true,
                            serverSide: false,
                            responsive: true,
                            autoWidth: false,
                            columnDefs: [{
                                targets: '_all',
                                className: 'text-nowrap'
                            }],
                            ajax: {
                                url: '/ppp/members/read',
                                type: 'GET',
                                headers: {
                                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                                }
                            },
                            columns: [{
                                    data: 'DT_RowIndex',
                                    orderable: false,
                                    searchable: false
                                },
                                {
                                    data: 'fullname'
                                },
                                {
                                    data: 'connection.internet_number'
                                },
                                {
                                    data: 'phone_number'
                                },
                                {
                                    data: 'actionCreate'
                                },
                            ]
                        });
                    });

                    // Detail Invoice Create - Update Periode and Amount
                    function updatePeriodeAndAmount() {
                        const subsperiode = parseInt($('#subsperiode').val() || 1);
                        const dueDateVal = $('#duedate').val();

                        if (dueDateVal) {
                            let dueDate = new Date(dueDateVal);

                            let start = new Date(dueDate.getFullYear(), dueDate.getMonth(), 1);

                            let end = new Date(dueDate);
                            end.setMonth(end.getMonth() + subsperiode);
                            end.setDate(0);

                            const startStr = start.toLocaleDateString('id-ID');
                            const endStr = end.toLocaleDateString('id-ID');

                            $('#periode').val(`${startStr} - ${endStr}`);
                        }

                        const basePrice = parseInt($('#amount').data('raw') || 0);
                        const vat = $('#vat').val() || 0
                        const disc = $('#disc').val() || 0

                        let subtotal = basePrice * subsperiode;

                        let vatAmount = (subtotal * vat) / 100;
                        let discAmount = (subtotal * disc) / 100;

                        let total = subtotal + vatAmount - discAmount;

                        $('#amount').val(total.toLocaleString('id-ID'));
                    }

                    // Create Invoice Button Handler
                    $(document).on('click', '.btnCreateInvoice', function() {
                        const memberId = $(this).data('id');
                        const internet = $(this).data('internet');
                        const fullname = $(this).data('fullname');
                        const username = $(this).data('username');
                        const item = $(this).data('item');
                        const price = $(this).data('price');
                        const vat = $(this).data('vats');
                        const disc = $(this).data('discounts');
                        const activeDate = $(this).data('active');

                        $('#fullname').val(fullname);
                        $('#member_id').val(memberId);
                        $('#items').val(`${item} | ${username}`);
                        $('#vat').val(vat);
                        $('#disc').val(disc.toLocaleString('id-ID'));

                        $('#amount')
                            .val(price.toLocaleString('id-ID'))
                            .data('raw', price);

                        if (activeDate) {
                            const today = new Date();
                            let parsed = new Date(activeDate);

                            $.ajax({
                                url: '/billing/check',
                                type: 'POST',
                                data: {
                                    member_id: memberId,
                                    year: today.getFullYear(),
                                    month: today.getMonth() + 1,
                                    _token: $('meta[name="csrf-token"]').attr('content')
                                },
                                success: function(res) {
                                    console.log(res)

                                    let dueDate;

                                    if (res.invoice && Object.keys(res.invoice).length > 0) {
                                        if (res.next_inv_date) {
                                            dueDate = new Date(res.next_inv_date);
                                        } else {
                                            dueDate = new Date(parsed);
                                            dueDate.setMonth(dueDate.getMonth() + 1);
                                        }
                                    } else {
                                        dueDate = new Date(parsed);
                                    }

                                    console.log(res)

                                    const formatted =
                                        dueDate.getFullYear() + '-' +
                                        String(dueDate.getMonth() + 1).padStart(2, '0') + '-' +
                                        String(dueDate.getDate()).padStart(2, '0');

                                    $('#duedate').val(formatted);

                                    updatePeriodeAndAmount();
                                }
                            });
                        }

                        $('#formCreateModal').modal('hide');

                        $('#formCreateModal').one('hidden.bs.modal', function() {
                            $('#invoiceDetailModal').modal('show');
                        });
                    });

                    // Subsperiode Change Event
                    $(document).on('change', '#subsperiode', function() {
                        updatePeriodeAndAmount();
                    });

                    // VAT and Discount Input Event
                    $(document).on('input', '#vat, #disc', function() {
                        updatePeriodeAndAmount();
                    });

                    // Select2 Initialization
                    $('#customerSelect').select2({
                        'dropdownParent': '#formCreateModal',
                        theme: 'bootstrap-5'
                    });

                    // Delete Invoice Handler
                    $(document).on('click', '#btn-delete', function() {
                        let id = $(this).data('id');
                        let inv = $(this).data('inv');
                        Swal.fire({
                            title: "Anda Yakin?",
                            text: `Apakah ingin menghapus invoice ${inv}!`,
                            icon: "warning",
                            showCancelButton: true,
                            confirmButtonColor: "#3085d6",
                            cancelButtonColor: "#d33",
                            confirmButtonText: "Yes, delete it!"
                        }).then((result) => {
                            if (result.isConfirmed) {
                                $.ajax({
                                    url: "/billing/" + id,
                                    type: "DELETE",
                                    data: {
                                        _token: "{{ csrf_token() }}"
                                    },
                                    success: function(response) {
                                        Toastify({
                                            text: "Data Berhasil Dihapus!",
                                            className: "info",
                                        }).showToast();
                                        $('#dataTables').DataTable().ajax.reload();
                                        updateStatistics();
                                    }
                                });
                            }
                        });
                    });

                });

                // Generate Invoice
                $('#generateAllBtn').on('click', function() {
                    const groupId = $(this).data('group');

                    Swal.fire({
                        title: 'Generate semua invoice?',
                        text: "Proses ini akan membuat invoice untuk semua pelanggan yang belum punya invoice bulan ini.",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Ya, lanjutkan!',
                        cancelButtonText: 'Batal'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            $.ajax({
                                url: '/billing/generate',
                                type: 'POST',
                                data: {
                                    _token: '{{ csrf_token() }}'
                                },
                                beforeSend: function() {
                                    Swal.fire({
                                        title: 'Sedang memproses...',
                                        text: 'Mohon tunggu, sistem sedang membuat invoice.',
                                        allowOutsideClick: false,
                                        didOpen: () => Swal.showLoading()
                                    });
                                },
                                success: function(res) {
                                    Swal.fire('Berhasil!', res.message, 'success');
                                },
                                error: function(xhr) {
                                    const res = xhr.responseJSON || {};
                                    Swal.fire('Gagal!', res.message || 'Terjadi kesalahan.', 'error');
                                }
                            });
                        }
                    });
                });
            </script>
        @endpush
    @endsection
