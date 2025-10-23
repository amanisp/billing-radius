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

        @include('pages.billing.invoices.create')
        @include('pages.billing.invoices.detail')

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
                    <div class="btn-group gap-1">
                        <button class="btn btn-outline-primary btn-sm px-5 py-2" data-bs-toggle="modal"
                            data-bs-target="#formCreateModal"><i class="fa-solid fa-file-invoice-dollar"></i>
                            Manual Invoice
                        </button>
                        <button class="btn btn-outline-success btn-sm px-5 py-2" onclick="alert('Coming Soon')"><i
                                class="fa-solid fa-file-invoice-dollar"></i>
                            Export
                        </button>
                    </div>
                    <hr>
                    <div class="filters d-flex gap-2 flex-wrap">
                        <select id="statusFilter" class="form-select px-5 py-2" style="max-width: 200px;">
                            <option value="">All Status</option>
                            <option value="paid">Paid</option>
                            <option value="unpaid">Unpaid</option>
                        </select>
                        <select id="typeFilter" class="form-select px-5 py-2" style="max-width: 200px;">
                            <option value="">All Type</option>
                            <option value="prabayar">Prabayar</option>
                            <option value="pascabayar">Pascabayar</option>
                        </select>
                        <select id="areaFilter" class="form-select px-5 py-2" style="max-width: 200px;">
                            <option value="">All Area</option>
                        </select>
                        <select id="monthFilter" class="form-select px-5 py-2" style="max-width: 200px;">
                            <option value="">All Month</option>
                            <option value="01">January</option>
                            <option value="02">February</option>
                            <option value="03">March</option>
                            <option value="04">April</option>
                            <option value="05">May</option>
                            <option value="06">June</option>
                            <option value="07">July</option>
                            <option value="08">August</option>
                            <option value="09">September</option>
                            <option value="10">October</option>
                            <option value="11">November</option>
                            <option value="12">December</option>
                        </select>
                        <select id="yearFilter" class="form-select px-5 py-2" style="max-width: 150px;">
                            <option value="">All Year</option>
                        </select>
                        <button id="resetFilters" class="btn btn-outline-secondary btn-sm px-4 py-2">
                            <i class="fa-solid fa-rotate-right"></i> Reset
                        </button>
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
                // Enhanced Invoice Filter System with Real-time Statistics
                $(document).ready(function() {
                    const userRole = "{{ Auth::user()->role }}";

                    // Initialize filter state
                    const filterState = {
                        status: '',
                        type: '',
                        area: '',
                        month: String(new Date().getMonth() + 1).padStart(2, '0'),
                        year: new Date().getFullYear()
                    };

                    // Load available years from database
                    function loadAvailableYears() {
                        $.ajax({
                            url: '/billing/filter/years',
                            type: 'GET',
                            success: function(years) {
                                $('#yearFilter').empty().append('<option value="">All Year</option>');
                                years.forEach(function(year) {
                                    $('#yearFilter').append(`<option value="${year}">${year}</option>`);
                                });
                                $('#yearFilter').val(filterState.year);
                            }
                        });
                    }

                    // Load available months for selected year
                    function loadAvailableMonths(year) {
                        if (!year) {
                            resetMonthFilter();
                            return;
                        }

                        $.ajax({
                            url: `/billing/filter/months/${year}`,
                            type: 'GET',
                            success: function(months) {
                                $('#monthFilter').empty().append('<option value="">All Month</option>');
                                months.forEach(function(month) {
                                    $('#monthFilter').append(
                                        `<option value="${month.value}">${month.label}</option>`
                                    );
                                });
                                if (months.some(m => m.value === filterState.month)) {
                                    $('#monthFilter').val(filterState.month);
                                }
                            }
                        });
                    }

                    function resetMonthFilter() {
                        $('#monthFilter').empty().append('<option value="">All Month</option>');
                        const monthNames = [
                            'January', 'February', 'March', 'April', 'May', 'June',
                            'July', 'August', 'September', 'October', 'November', 'December'
                        ];
                        monthNames.forEach(function(name, index) {
                            const value = String(index + 1).padStart(2, '0');
                            $('#monthFilter').append(`<option value="${value}">${name}</option>`);
                        });
                    }

                    // Update statistics based on filters
                    function updateStatistics() {
                        $.ajax({
                            url: '/billing/stats/monthly',
                            type: 'GET',
                            data: {
                                month: $('#monthFilter').val(),
                                year: $('#yearFilter').val()
                            },
                            success: function(stats) {
                                // Update dashboard cards if they exist
                                updateDashboardCard('invoice', stats.total_invoices, stats.total_amount);
                                updateDashboardCard('paid', stats.paid_count, stats.paid_amount);
                                updateDashboardCard('unpaid', stats.unpaid_count, stats.unpaid_amount);
                                updateDashboardCard('overdue', stats.overdue_count, stats.overdue_amount);
                            }
                        });
                    }

                    function updateDashboardCard(type, count, amount) {
                        const cardMap = {
                            'invoice': '.status-card.total',
                            'paid': '.status-card.suspend:has(.bg-primary)',
                            'unpaid': '.status-card.suspend:has(.bg-warning)',
                            'overdue': '.status-card.active'
                        };

                        const card = $(cardMap[type]);
                        if (card.length) {
                            card.find('.fs-4').text(count);
                            card.find('.text-muted').text('Rp ' + formatNumber(amount));
                        }
                    }

                    function formatNumber(num) {
                        return new Intl.NumberFormat('id-ID').format(num);
                    }

                    // Initialize
                    loadAvailableYears();
                    resetMonthFilter();

                    // Populate area filter
                    $.ajax({
                        url: '/areas/list',
                        type: 'GET',
                        success: function(areas) {
                            areas.forEach(function(area) {
                                $('#areaFilter').append(
                                    `<option value="${area.id}">${area.name}</option>`
                                );
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
                                d.month = $('#monthFilter').val();
                                d.year = $('#yearFilter').val();
                            }
                        },
                        columns: [{
                                data: 'DT_RowIndex',
                                orderable: false,
                                searchable: false
                            },
                            {
                                data: 'name'
                            },
                            {
                                data: 'area'
                            },
                            {
                                data: 'inv_number'
                            },
                            {
                                data: 'invoice_date'
                            },
                            {
                                data: 'due_date'
                            },
                            {
                                data: 'total'
                            },
                            {
                                data: 'status'
                            },
                            {
                                data: 'type'
                            },
                            {
                                data: 'action'
                            }
                        ]
                    });

                    // Filter change events
                    $('#statusFilter, #typeFilter, #areaFilter, #monthFilter').on('change', function() {
                        table.ajax.reload();
                        updateStatistics();
                    });

                    // Year filter with month reload
                    $('#yearFilter').on('change', function() {
                        const selectedYear = $(this).val();
                        if (selectedYear) {
                            loadAvailableMonths(selectedYear);
                        } else {
                            resetMonthFilter();
                        }
                        table.ajax.reload();
                        updateStatistics();
                    });

                    // Reset filters
                    $('#resetFilters').on('click', function() {
                        $('#statusFilter').val('');
                        $('#typeFilter').val('');
                        $('#areaFilter').val('');
                        $('#monthFilter').val(filterState.month);
                        $('#yearFilter').val(filterState.year);
                        table.ajax.reload();
                        updateStatistics();
                    });

                    // Show filter summary
                    function showFilterSummary() {
                        const filters = [];

                        if ($('#statusFilter').val()) {
                            filters.push(`Status: ${$('#statusFilter option:selected').text()}`);
                        }
                        if ($('#typeFilter').val()) {
                            filters.push(`Type: ${$('#typeFilter option:selected').text()}`);
                        }
                        if ($('#areaFilter').val()) {
                            filters.push(`Area: ${$('#areaFilter option:selected').text()}`);
                        }
                        if ($('#monthFilter').val()) {
                            filters.push(`Month: ${$('#monthFilter option:selected').text()}`);
                        }
                        if ($('#yearFilter').val()) {
                            filters.push(`Year: ${$('#yearFilter option:selected').text()}`);
                        }

                        if (filters.length > 0) {
                            const summary = `<div class="alert alert-info alert-dismissible fade show mt-2" role="alert">
                <strong>Active Filters:</strong> ${filters.join(' | ')}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>`;

                            if ($('.filter-summary').length === 0) {
                                $('.filters').after('<div class="filter-summary"></div>');
                            }
                            $('.filter-summary').html(summary);
                        } else {
                            $('.filter-summary').remove();
                        }
                    }

                    // Update filter summary on change
                    $('.filters select').on('change', function() {
                        showFilterSummary();
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
                                        "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr('content')
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
                                $.ajax({
                                    url: "/billing/unpaid/pay",
                                    type: "POST",
                                    headers: {
                                        "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr('content')
                                    },
                                    data: {
                                        payment_method: result.value.paymentMethod,
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

                    // Delete Handler
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
                                        table.ajax.reload();
                                        updateStatistics();
                                    }
                                });
                            }
                        });
                    });
                });
            </script>
        @endpush
    @endsection
