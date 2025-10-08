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
                            }
                        },
                        columns: [{
                                data: 'DT_RowIndex',
                                orderable: false,
                                searchable: false
                            }, {
                                data: 'name',
                            },
                            {
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
                    $('#statusFilter, #typeFilter').on('change', function() {
                        table.ajax.reload();
                    });

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
                                    return false; // Mencegah modal tertutup
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
                                        "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content")
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


                    // Load Member
                    let tableMember; // taruh di luar agar bisa direuse

                    // saat tombol di klik
                    $(document).on('click', '[data-bs-target="#formCreateModal"]', function() {
                        if ($.fn.DataTable.isDataTable('#dataMember')) {
                            tableMember.destroy(); // hancurkan instance lama
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
                    // 🔹 Fungsi untuk update periode & total harga
                    function updatePeriodeAndAmount() {
                        const subsperiode = parseInt($('#subsperiode').val() || 1);
                        const dueDateVal = $('#duedate').val();

                        if (dueDateVal) {
                            let dueDate = new Date(dueDateVal);

                            // Start periode → dari awal bulan dueDate
                            let start = new Date(dueDate.getFullYear(), dueDate.getMonth(), 1);

                            // End periode → akhir bulan sesuai subsperiode
                            let end = new Date(dueDate);
                            end.setMonth(end.getMonth() + subsperiode);
                            end.setDate(0);

                            const startStr = start.toLocaleDateString('id-ID');
                            const endStr = end.toLocaleDateString('id-ID');

                            $('#periode').val(`${startStr} - ${endStr}`);
                        }

                        // 🔹 Hitung harga total
                        const basePrice = parseInt($('#amount').data('raw') || 0); // harga asli 1 bulan
                        const vat = parseFloat($('#vat').val() || 0);
                        const disc = parseFloat($('#disc').val() || 0);

                        let subtotal = basePrice * subsperiode;

                        // Hitung PPN & diskon
                        let vatAmount = (subtotal * vat) / 100;
                        let discAmount = (subtotal * disc) / 100;

                        let total = subtotal + vatAmount - discAmount;

                        $('#amount').val(total.toLocaleString('id-ID'));
                    }


                    // 🔹 Handle klik Create Invoice
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

                        // isi field form
                        $('#fullname').val(fullname);
                        $('#member_id').val(memberId);
                        $('#items').val(`${item} | ${username}`);
                        $('#vat').val(vat);
                        $('#disc').val(disc.toLocaleString('id-ID'));

                        // simpan harga asli di data attribute
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
                                    console.log(res);

                                    let dueDate;

                                    if (res.exists) {
                                        // ✅ Sudah ada invoice bulan ini → gunakan next_inv_date dari server kalau ada
                                        if (res.next_inv_date) {
                                            dueDate = new Date(res.next_inv_date);
                                        } else {
                                            // fallback → geser 1 bulan dari activeDate
                                            dueDate = new Date(parsed);
                                            dueDate.setMonth(dueDate.getMonth() + 1);
                                        }
                                    } else {
                                        // ✅ Belum ada invoice bulan ini → pakai activeDate (bulan ini)
                                        dueDate = new Date(parsed);
                                    }

                                    // Format ke yyyy-mm-dd
                                    const formatted =
                                        dueDate.getFullYear() + '-' +
                                        String(dueDate.getMonth() + 1).padStart(2, '0') + '-' +
                                        String(dueDate.getDate()).padStart(2, '0');

                                    $('#duedate').val(formatted);

                                    // 🔹 Update periode & total pertama kali
                                    updatePeriodeAndAmount();
                                }
                            });

                        }

                        // tutup modal pertama
                        $('#formCreateModal').modal('hide');

                        // setelah modal pertama benar-benar tertutup → buka modal kedua
                        $('#formCreateModal').one('hidden.bs.modal', function() {
                            $('#invoiceDetailModal').modal('show');
                        });
                    });


                    // 🔹 Event ketika subsperiode berubah
                    $(document).on('change', '#subsperiode', function() {
                        updatePeriodeAndAmount();
                    });

                    // 🔹 Event ketika VAT atau Diskon berubah → hitung ulang total
                    $(document).on('input', '#vat, #disc', function() {
                        updatePeriodeAndAmount();
                    });



                    // Select2
                    $('#customerSelect').select2({
                        'dropdownParent': '#formCreateModal',
                        theme: 'bootstrap-5'

                    });

                    // $('#customerSelect').on('change', function() {
                    //     // Ambil data dari option yang dipilih
                    //     const selected = $(this).find(':selected');
                    //     const price = selected.data('price') || 0;
                    //     const name = selected.data('fullname') || '';
                    //     const rawDate = selected.data('duedate'); // ini masih active_date

                    //     if (rawDate) {
                    //         const activeDate = new Date(rawDate);

                    //         const now = new Date();
                    //         // Ganti bulan & tahun dari activeDate ke bulan & tahun saat ini
                    //         activeDate.setMonth(now.getMonth());
                    //         activeDate.setFullYear(now.getFullYear());

                    //         const year = activeDate.getFullYear();
                    //         const month = String(activeDate.getMonth() + 1).padStart(2, '0');
                    //         const day = String(activeDate.getDate()).padStart(2, '0');
                    //         const finalDate = `${year}-${month}-${day}`;

                    //         $('#duedate').val(finalDate);
                    //     }

                    //     // Update kolom Item
                    //     $('#items').val(`${name} | Rp${price.toLocaleString('id-ID')}`);

                    // });

                });


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
            </script>
        @endpush
    @endsection
