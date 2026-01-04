@extends('layouts.admin')
@section('content')
    <div class="main">
        <div class="page-title">
            <div class="row">

                @if (session('success'))
                    <div class="alert alert-light-success color-danger"><i class="bi bi-exclamation-circle"></i>
                        {{ session('success') }}</div>
                @endif
                @if (session('error'))
                    <div class="alert alert-light-danger color-danger"><i class="bi bi-exclamation-circle"></i>
                        {{ session('error') }}</div>
                @endif


                <div class="col-12 col-md-6 order-md-1 order-last">
                    <h3>Data Pelanggan</h3>
                </div>
                <div class="col-12 col-md-6 order-md-2 order-first">
                    <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard.index') }}">Dashboard</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Pelanggan</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
        <button class="btn btn-primary btn-sm  px-4 rounded-pill mt-2 mb-4" data-bs-toggle="modal"
            data-bs-target="#formCreateModal"><i class="fa-solid fa-plus"></i>
            Create
        </button>

        {{-- Modal Create --}}
        @include('pages.super.mitra.create')

        {{-- Modal Edit --}}
        @include('pages.super.mitra.update')
        {{-- @include('pages.super.mitra.profile') --}}

        <section class="section">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table" id="dataTables">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Nama</th>
                                    <th>Nomor HP</th>
                                    <th>Email</th>
                                    <th>Area</th>
                                    <th>POP</th>
                                    <th>Tipe</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </section>
    </div>



    <script>
        $(document).ready(function() {
            let table = $('#dataTables').DataTable({
                processing: true,
                serverSide: false,
                responsive: true,
                autoWidth: false,
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                ajax: '/mitra/read',
                columns: [{
                        data: 'DT_RowIndex',
                        orderable: false,
                        searchable: false
                    }, {
                        data: 'name',
                    },
                    {
                        data: 'phone_number',
                    },
                    {
                        data: 'email',
                    },
                    {
                        data: 'area',
                    },
                    {
                        data: 'pop',
                    },
                    {
                        data: 'segmentasi',
                    },
                    {
                        data: 'action',
                    },
                ]
            });

            $(document).on('click', '#btn-edit', function() {
                let id = $(this).data("id");
                // Isi nilai form dengan data dari tombol edit
                $("#edit-name").val($(this).data("name"));
                $("#edit-email").val($(this).data("email"));
                $("#edit-phone").val($(this).data("phone"));
                $("#edit-price").val($(this).data("price"));
                $("#edit-capacity").val($(this).data("capacity"));
                $("#edit-transmit").val($(this).data("phone"));

                let selectedProfile = $(this).data("profile");
                $("#edit-profileSelect").val(selectedProfile).trigger("change");
                let selectedArea = $(this).data("area");
                $("#edit-area").val(selectedArea).trigger("change");
                let selectedMember = $(this).data("member");
                $("#edit-member").val(selectedMember).trigger("change");
                let selectedOptical = $(this).data("optical");
                $("#edit-odp").val(selectedOptical).trigger("change");


                // Set action form dengan URL update
                $("#FormEdit").attr("action", `/ppp/pppoe/${id}`);

                // Tampilkan modal edit
                $("#formEditModal").modal("show");
            })


            $(document).on('click', '#btn-delete', function() {
                let id = $(this).data('id');
                let name = $(this).data('name');
                Swal.fire({
                    title: "Anda Yakin?",
                    text: `Apakah ingin menghapus mitra ${name}!`,
                    icon: "warning",
                    showCancelButton: true,
                    confirmButtonColor: "#3085d6",
                    cancelButtonColor: "#d33",
                    confirmButtonText: "Yes, delete it!"
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: "/mitra/" + id,
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
                            }
                        });

                    }
                });
            });


            // select2
            $('#selectArea').select2({
                'dropdownParent': '#formCreateModal',
                theme: 'bootstrap-5'


            });
            $('#selectPop').select2({
                'dropdownParent': '#formCreateModal',
                theme: 'bootstrap-5'


            });
            // $('#edit-member').select2({
            //     'dropdownParent': '#formEditModal',
            //     theme: 'bootstrap-5'

            // });
        });
    </script>

    <script>
        function formatRupiah(input) {
            let value = input.value.replace(/\D/g, ""); // Hanya angka
            let formatted = new Intl.NumberFormat("id-ID", {
                style: "currency",
                currency: "IDR",
                minimumFractionDigits: 0
            }).format(value);

            input.value = formatted.replace("Rp", "").trim(); // Hapus Rp untuk input
        }

        function calculateTotal() {
            let priceInput = document.getElementById("price").value.replace(/\D/g, ""); // Ambil angka
            let price = priceInput ? parseFloat(priceInput) : 0;

            let ppn = document.getElementById("ppn").checked ? price * 0.11 : 0;
            let bhpuso = document.getElementById("bhpuso").checked ? price * 0.0175 : 0;
            let kso = document.getElementById("kso").checked ? price * 0.0325 : 0;

            let total = price + ppn + bhpuso + kso;

            let formattedTotal = new Intl.NumberFormat("id-ID", {
                style: "currency",
                currency: "IDR",
                minimumFractionDigits: 0
            }).format(total).replace("Rp", "").trim();

            document.getElementById("total_price_text").innerHTML =
                `Total: <span class="text-success">Rp ${formattedTotal}</span>`;
        }

        // Jalankan calculateTotal() setiap kali harga diubah atau checkbox dicentang
        document.getElementById("price").addEventListener("input", function() {
            formatRupiah(this);
            calculateTotal();
        });
    </script>
@endsection
