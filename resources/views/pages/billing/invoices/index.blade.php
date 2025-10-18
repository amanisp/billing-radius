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

        {{-- Only show create modal for Mitra and Kasir --}}
        @if (in_array(Auth::user()->role, ['mitra', 'kasir']))
            @include('pages.billing.invoices.create')
            @include('pages.billing.invoices.detail')
        @endif

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
                    @if (in_array(Auth::user()->role, ['mitra', 'kasir']))
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
                    @endif
                    <div class="filters d-flex gap-2 ">
                        <select id="statusFilter" class="form-select px-5 py-2">
                            <option value="">Status</option>
                            <option value="paid">Paid</option>
                            <option value="unpaid">Unpaid</option>
                        </select>
                        <select id="typeFilter" class="form-select px-5 py-2">
                            <option value="">Type</option>
                            <option value="prabayar">Prabayar</option>
                            <option value="pascabayar">Pascabayar</option>
                        </select>
                        <select id="areaFilter" class="form-select px-5 py-2">
                            <option value="">Area</option>
                            <!-- Area options will be populated dynamically -->
                        </select>
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
                    // Populate area filter options
                    $.ajax({
                        url: '/areas/list', // Buat endpoint baru untuk get list area
                        type: 'GET',
                        success: function(areas) {
                            areas.forEach(function(area) {
                                $('#areaFilter').append(
                                    `<option value="${area.id}">${area.name}</option>`);
                            });
                        }
                    });

                    // Read Data
                    let table = $('#dataTables').DataTable({
                        processing: true,
                        serverSide: true,
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

                    // Filter Event
                    $('#statusFilter, #typeFilter, #areaFilter').on('change', function() {
                        table.ajax.reload();
                    });

                    // Payment Cancel - Only for Mitra
                    if (userRole === 'mitra') {
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
                    }

                    // Pay Button - For Mitra and Kasir
                    if (userRole === 'mitra' || userRole === 'kasir') {
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
                    }

                    // Load Member - Only for Mitra and Kasir
                    if (userRole === 'mitra' || userRole === 'kasir') {
                        let tableMember;

                        $(document).on('click', '[data-bs-target="#formCreateModal"]', function() {
                            if ($.fn.DataTable.isDataTable('#dataMember')) {
                                tableMember.destroy();
                            }

                            tableMember = $('#dataMember').DataTable({
                                processing: true,
                                serverSide: true,
                                responsive: true,
                                autoWidth: false,
                                paging: false,
                                info: false,
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

                        // Detail Invoice Create
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
                            const vat = parseFloat($('#vat').val() || 0);
                            const disc = parseFloat($('#disc').val() || 0);

                            let subtotal = basePrice * subsperiode;

                            let vatAmount = (subtotal * vat) / 100;
                            let discAmount = (subtotal * disc) / 100;

                            let total = subtotal + vatAmount - discAmount;

                            $('#amount').val(total.toLocaleString('id-ID'));
                        }

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
                                        let dueDate;

                                        if (res.exists) {
                                            if (res.next_inv_date) {
                                                dueDate = new Date(res.next_inv_date);
                                            } else {
                                                dueDate = new Date(parsed);
                                                dueDate.setMonth(dueDate.getMonth() + 1);
                                            }
                                        } else {
                                            dueDate = new Date(parsed);
                                        }

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

                        $(document).on('change', '#subsperiode', function() {
                            updatePeriodeAndAmount();
                        });

                        $(document).on('input', '#vat, #disc', function() {
                            updatePeriodeAndAmount();
                        });

                        $('#customerSelect').select2({
                            'dropdownParent': '#formCreateModal',
                            theme: 'bootstrap-5'
                        });
                    }

                    // Delete Invoice - Only for Mitra
                    if (userRole === 'mitra') {
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
                                        }
                                    });
                                }
                            });
                        });
                    }
                });
            </script>
        @endpush
    @endsection
