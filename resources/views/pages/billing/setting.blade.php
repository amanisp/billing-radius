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

        <section class="section">
            <div class="card shadow-sm mb-2">
                <div class="card-body">
                    <h4 class="card-title mb-3">Balance</h4>
                    <small class="text-danger">* Balance adalah saldo pembayaran dari pelanggan yang melakukan pembayaran
                        melalui payment gateway milik PT Anugerah Media Data Nusantara dengan biaya admin Rp 2.500/transaksi
                        24/7.</small>
                    <h2>Rp {{ number_format($globalSet->xendit_balance, 0, ',', '.') }}
                    </h2>
                    {{-- <form action="{{ route('billing.payout') }}" method="POST">
                        @csrf --}}
                    <button data-id="{{ $globalSet->group_id }}" id="btn-withdraw"
                        class="btn btn-success btn-sm mt-2 px-4 rounded-pill"><i
                            class="fa-solid fa-money-bill-transfer"></i>
                        Tarik Saldo</button>
                    {{-- </form> --}}
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table" id="dataTables">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>ID</th>
                                    <th>Email</th>
                                    <th>Nominal</th>
                                    <th>EXP</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
            <div class="card shadow-sm">
                <div class="card-body">
                    <h4 class="card-title mb-3">Billing Setting</h4>
                    <form class=" mb-3" action="{{ route('ppp.settings.bill', ['id' => $globalSet->group_id]) }}"
                        method="post">
                        @csrf
                        @method('PUT')
                        <div class="row">
                            <div class="col-12">
                                <div class="form-group row align-items-center">
                                    <div class="col-lg-10 col-9">
                                        <label class="col-form-label" for="first-name">Tanggal jatuh tempo untuk metode
                                            pembayaran Pascabayar</label>
                                    </div>
                                    <div class="col-lg-2 col-3">
                                        <input type="number" id="first-name" value="{{ $globalSet->due_date_pascabayar }}"
                                            class="form-control" name="due_date_pascabayar">
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-group row align-items-center">
                                    <div class="col-lg-10 col-9">
                                        <label class="col-form-label" for="first-name">Tanggal pembuatan faktur sebelum
                                            tanggal jatuh tempo</label>
                                    </div>
                                    <div class="col-lg-2 col-3">
                                        <input type="number" id="first-name" class="form-control"
                                            value="{{ $globalSet->invoice_generate_days }}" name="invoice_generate_days">
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-group row align-items-center">
                                    <div class="col-lg-10 col-9">
                                        <label class="col-form-label" for="first-name">Waktu pelanggan
                                            ditangguhkan/diisolasi
                                            oleh sistem jika tagihan belum dibayar</label>
                                    </div>
                                    <div class="col-lg-2 col-3">
                                        <input type="time" value="{{ $globalSet->isolir_time }}" id="first-name"
                                            class="form-control" name="isolir_time">
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-group mb-3">
                                    <label for="exampleFormControlTextarea1" class="form-label">Footer <small
                                            class="text-danger">* Masukan informasi akun rekening atau lainya jika ingin
                                            menggunakan metode pembayan ke rekening pribadi</small></label>
                                    <textarea class="form-control" id="exampleFormControlTextarea1" rows="3" name="footer">{{ $globalSet->footer }}</textarea>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-sm btn-primary mt-2 px-4 rounded-pill">Save</button>
                    </form>


                </div>
            </div>

        </section>

        @push('script-page')
            <script>
                $(document).ready(function() {

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
                            url: '/billing/payout/read',
                            type: 'GET',
                            headers: {
                                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                            }
                        },
                        columns: [{
                                data: 'DT_RowIndex',
                                orderable: false,
                                searchable: false
                            }, {
                                data: 'id',
                            },
                            {
                                data: 'email',
                            },
                            {
                                data: 'nominal',
                            },
                            {
                                data: 'exp',
                            },
                            {
                                data: 'status',
                            },
                            {
                                data: 'action',
                            },
                        ]
                    });

                    $(document).on('click', '#btn-withdraw', function() {
                        let id = $(this).data('id');

                        Swal.fire({
                            title: "Masukan Nominal",
                            html: `
            <div class="row">
                <div class="col-12 mb-1">
                    <input type="number" id="amount" class="swal2-input" placeholder="Masukan Nominal: 100000">
                </div>
                <div class="col-12 mb-1">
                    <input type="email" id="email" class="swal2-input" placeholder="yourmail@mail.com">
                </div>
                <div id="error-message" style="color: red; font-size: 12px; margin-top: 5px;"></div>
            </div>
        `,
                            icon: "warning",
                            showCancelButton: true,
                            confirmButtonColor: "#3085d6",
                            cancelButtonColor: "#d33",
                            confirmButtonText: "Yes, Process",
                            preConfirm: () => {
                                let amount = document.getElementById('amount').value;
                                let email = document.getElementById('email').value;

                                if (!amount) {
                                    document.getElementById('error-message').innerText =
                                        "Silakan masukan nominal!";
                                    return false;
                                } else if (amount < 10000) {
                                    document.getElementById('error-message').innerText =
                                        "Masukan nominal minimal Rp10.000!";
                                    return false;
                                } else if (amount > <?= $globalSet->xendit_balance ?>) {
                                    document.getElementById('error-message').innerText =
                                        "Saldo kamu hanya Rp<?= number_format($globalSet->xendit_balance, 0, ',', '.') ?>!";
                                    return false;
                                }

                                if (!email) {
                                    document.getElementById('error-message').innerText =
                                        "Silakan isi email kamu!";
                                    return false;
                                }

                                // ✅ PERBAIKAN di sini
                                return {
                                    amount: amount,
                                    email: email
                                };
                            }
                        }).then((result) => {
                            if (result.isConfirmed && result.value) {
                                let amount = result.value.amount;
                                let email = result.value.email;

                                $.ajax({
                                    url: "{{ route('billing.payout') }}", // ❗ pastikan ini string, bukan variabel
                                    type: "POST",
                                    headers: {
                                        "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content")
                                    },
                                    data: {
                                        amount: amount,
                                        email: email,
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

                })
            </script>
        @endpush
    @endsection
