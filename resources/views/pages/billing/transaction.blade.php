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


        <section class="row">
            <div class="row">
                <div class="col-md">
                    <div class="card">
                        <div class="status-card total">
                            <div class="stats-icon green mb-1 rounded-circle">
                                <i class="text-white fa-solid fa-money-bill-trend-up"></i>
                            </div>
                            <div class="d-flex flex-column">
                                <small class="status-number">Monthly Earning</small>
                                <p class="fs-4 status-number">Rp {{ number_format($monthly_earning, 0, ',', '.') }}</p>
                                <small class="text-muted">Rp {{ number_format($monthly_earning_last_month, 0, ',', '.') }}
                                    Last Month</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md">
                    <div class="card">
                        <div class="status-card active">
                            <div class="stats-icon bg-danger mb-1 rounded-circle">
                                <i class="text-white fa-solid fa-cash-register"></i>
                            </div>
                            <div class="d-flex flex-column">
                                <small class="status-number">Monthly Expenses</small>
                                <p class="fs-4 status-number">Rp
                                    {{ number_format($totalUnpaidLastMonth ?? 0, 0, ',', '.') }}</p>
                                <small class="text-muted">Invoice Unpaid Last Month</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md">
                    <div class="card">
                        <div class="status-card suspend">
                            <div class="stats-icon bg-warning mb-1 rounded-circle">
                                <i class="text-white fa-solid fa-map-location-dot"></i>
                            </div>
                            <div class="d-flex flex-column">
                                <small class="status-number">Total Unpaid</small>
                                <p class="fs-4 status-number">Rp {{ number_format($unpaidTotal, 0, ',', '.') }}</p>
                                <small class="text-muted">Total Invoice Value Unpaid</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Table Section --}}
            <div class="row">
                <div class="col-12 col-lg-12">
                    <div class="card">
                        <div class="card-header">
                            <h5>Invoice Paid</h5>
                            <hr>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <select id="payerFilter" class="form-select form-select-sm w-auto d-none">
                                    <option value="">Paid By</option>
                                    <option value="kasir">Kasir</option>
                                    <option value="admin">Admin</option>
                                    <option value="teknisi">Teknisi</option>
                                </select>

                                <table class="table" id="dataTables">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Nama</th>
                                            <th>Nomor Invoice</th>
                                            <th>Admin</th>
                                            <th>Paid Date</th>
                                            <th>Payment Method</th>
                                            <th>Total</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        @push('script-page')
            <script>
                $(document).ready(function() {
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
                            url: '/billing/unpaid/read?status=paid',
                            type: 'GET',
                            headers: {
                                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                            },
                            data: function(d) {
                                d.payer = $('#payerFilter').val();
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
                                data: 'inv_number'
                            },
                            {
                                data: 'payer'
                            },
                            {
                                data: 'paid_at'
                            },
                            {
                                data: 'payment_method'
                            },
                            {
                                data: 'total'
                            },
                            {
                                data: 'status'
                            },
                            {
                                data: 'action'
                            },
                        ]
                    });

                    $('#payerFilter')
                        .removeClass('d-none')
                        .appendTo('#dataTables_filter')
                        .addClass('ms-2');

                    // Optional: filter table by dropdown
                    $('#payerFilter').on('change', function() {
                        table.ajax.reload();
                    });

                    // Cancel payment
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
                                        "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content")
                                    },
                                    data: {
                                        id: id
                                    },
                                    success: function(response) {
                                        Toastify({
                                            text: "Pembayaran Berhasil Dibatalkan!",
                                            className: "success"
                                        }).showToast();
                                        table.ajax.reload();
                                    },
                                    error: function(error) {
                                        Toastify({
                                            text: "Terjadi kesalahan saat pembayaran!",
                                            className: "error"
                                        }).showToast();
                                    }
                                });
                            }
                        });
                    });

                    // Delete invoice
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
                                            className: "info"
                                        }).showToast();
                                        table.ajax.reload();
                                    }
                                });
                            }
                        });
                    });
                });
            </script>
            <style>
                /* make search + filter inline */
                #dataTables_filter {
                    display: flex;
                    align-items: center;
                    justify-content: flex-end;
                    gap: 0.5rem;
                }

                #dataTables_filter label {
                    margin-bottom: 0;
                    display: flex;
                    align-items: center;
                    gap: 0.25rem;
                }
            </style>
        @endpush
    @endsection
