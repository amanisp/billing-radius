@extends('layouts.admin')
@section('content')
    <div id="main">
        {{-- Navbar --}}
        @include('includes.v_navbar')

        {{-- Modal Create --}}
        <x-modal-form id="formCreateModal" title="Tambah Profile PPPoE" action="">
            <div class="col-12 mb-3">
                <div class="row">
                    <div class="col-lg-6">
                        <div class="form-group">
                            <label for="name">Nama</label>
                            <div class="position-relative">
                                <input required name="name" type="text" class="form-control"
                                    placeholder="Phoenix Silver" id="name">
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="form-group">
                            <label for="price">Price <small class="text-warning">Optional</small></label>
                            <div class="position-relative">
                                <input name="price" type="text" class="form-control" id="price"
                                    oninput="formatRupiah(this);">
                            </div>
                        </div>
                    </div>
                </div>
                <hr />
                <div class="row mb-2">
                    <div class="col-lg-4">
                        <label class="col-form-label" for="rate_rx">Rate Limit</label>
                    </div>
                    <div class="col-lg-4">
                        <div class="row">
                            <div class="col-2">
                                <label class="col-form-label" for="rate_rx">RX</label>
                            </div>
                            <div class="col-10">
                                <input type="text" id="rate_rx" class="form-control" name="rate_rx"
                                    placeholder="Example: 2M">
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 mt-lg-0 mt-1">
                        <div class="row">
                            <div class="col-2">
                                <label class="col-form-label" for="rate_tx">TX</label>
                            </div>
                            <div class="col-10">
                                <input type="text" id="rate_tx" class="form-control" name="rate_tx"
                                    placeholder="Example: 2M">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row mb-2">
                    <div class="col-lg-4">
                        <label class="col-form-label" for="burst_rx">Burst Limit <small
                                class="text-warning">Optional</small></label>
                    </div>
                    <div class="col-lg-4">
                        <div class="row">
                            <div class="col-2">
                                <label class="col-form-label" for="burst_rx">RX</label>
                            </div>
                            <div class="col-10">
                                <input type="text" id="burst_rx" class="form-control" name="burst_rx"
                                    placeholder="Example: 3M">
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 mt-lg-0 mt-1">
                        <div class="row">
                            <div class="col-2">
                                <label class="col-form-label" for="burst_tx">TX</label>
                            </div>
                            <div class="col-10">
                                <input type="text" id="burst_tx" class="form-control" name="burst_tx"
                                    placeholder="Example: 3M">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row mb-2">
                    <div class="col-lg-4">
                        <label class="col-form-label" for="threshold_rx">Burst Threshold <small
                                class="text-warning">Optional</small></label>
                    </div>
                    <div class="col-lg-4">
                        <div class="row">
                            <div class="col-2">
                                <label class="col-form-label" for="threshold_rx">RX</label>
                            </div>
                            <div class="col-10">
                                <input type="text" id="threshold_rx" class="form-control" name="threshold_rx"
                                    placeholder="Example: 1536k">
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 mt-lg-0 mt-1">
                        <div class="row">
                            <div class="col-2">
                                <label class="col-form-label" for="threshold_tx">TX</label>
                            </div>
                            <div class="col-10">
                                <input type="text" id="threshold_tx" class="form-control" name="threshold_tx"
                                    placeholder="Example: 1536k">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row mb-2">
                    <div class="col-lg-4">
                        <label class="col-form-label" for="time_rx">Burst Time <small
                                class="text-warning">Optional</small></label>
                    </div>
                    <div class="col-lg-4">
                        <div class="row">
                            <div class="col-2">
                                <label class="col-form-label" for="time_rx">RX</label>
                            </div>
                            <div class="col-10">
                                <input type="text" id="time_rx" class="form-control" name="time_rx"
                                    placeholder="Example: 12">
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 mt-lg-0 mt-1">
                        <div class="row">
                            <div class="col-2">
                                <label class="col-form-label" for="time_tx">TX</label>
                            </div>
                            <div class="col-10">
                                <input type="text" id="time_tx" class="form-control" name="time_tx"
                                    placeholder="Example: 12">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row mb-2">
                    <div class="col-lg-4">
                        <label class="col-form-label" for="priority">Priority <small
                                class="text-warning">Optional</small></label>
                    </div>
                    <div class="col-lg-8">
                        <div class="row">
                            <select name="priority" id="priority" class="form-select">
                                <option value="1">1</option>
                                <option value="2">2</option>
                                <option value="3">3</option>
                                <option value="4">4</option>
                                <option value="5">5</option>
                                <option value="6">6</option>
                                <option value="7">7</option>
                                <option selected value="8">8</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </x-modal-form>

        {{-- Modal Edit --}}
        <x-modal-form id="formEditModal" title="Edit Data Profile" action="">
            <div class="col-12 mb-3">
                <div class="row">
                    <div class="col-lg-6">
                        <div class="form-group">
                            <label for="name">Nama</label>
                            <div class="position-relative">
                                <input required name="name" type="text" class="form-control"
                                    placeholder="Phoenix Silver" id="edit-name">
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="form-group">
                            <label for="price">Price <small class="text-warning">Optional</small></label>
                            <div class="position-relative">
                                <input name="price" type="text" class="form-control" id="edit-price"
                                    oninput="formatRupiah(this);">
                            </div>
                        </div>
                    </div>
                </div>
                <hr />
                <div class="row mb-2">
                    <div class="col-lg-4">
                        <label class="col-form-label" for="rate_rx">Rate Limit</label>
                    </div>
                    <div class="col-lg-4">
                        <div class="row">
                            <div class="col-2">
                                <label class="col-form-label" for="rate_rx">RX</label>
                            </div>
                            <div class="col-10">
                                <input type="text" id="edit-raterx" class="form-control" name="rate_rx"
                                    placeholder="Example: 2M">
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 mt-lg-0 mt-1">
                        <div class="row">
                            <div class="col-2">
                                <label class="col-form-label" for="rate_tx">TX</label>
                            </div>
                            <div class="col-10">
                                <input type="text" id="edit-ratetx" class="form-control" name="rate_tx"
                                    placeholder="Example: 2M">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row mb-2">
                    <div class="col-lg-4">
                        <label class="col-form-label" for="burst_rx">Burst Limit <small
                                class="text-warning">Optional</small></label>
                    </div>
                    <div class="col-lg-4">
                        <div class="row">
                            <div class="col-2">
                                <label class="col-form-label" for="burst_rx">RX</label>
                            </div>
                            <div class="col-10">
                                <input type="text" id="edit-burstrx" class="form-control" name="burst_rx"
                                    placeholder="Example: 3M">
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 mt-lg-0 mt-1">
                        <div class="row">
                            <div class="col-2">
                                <label class="col-form-label" for="burst_tx">TX</label>
                            </div>
                            <div class="col-10">
                                <input type="text" id="edit-bursttx" class="form-control" name="burst_tx"
                                    placeholder="Example: 3M">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row mb-2">
                    <div class="col-lg-4">
                        <label class="col-form-label" for="threshold_rx">Burst Threshold <small
                                class="text-warning">Optional</small></label>
                    </div>
                    <div class="col-lg-4">
                        <div class="row">
                            <div class="col-2">
                                <label class="col-form-label" for="threshold_rx">RX</label>
                            </div>
                            <div class="col-10">
                                <input type="text" id="edit-thresholdrx" class="form-control" name="threshold_rx"
                                    placeholder="Example: 1536k">
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 mt-lg-0 mt-1">
                        <div class="row">
                            <div class="col-2">
                                <label class="col-form-label" for="threshold_tx">TX</label>
                            </div>
                            <div class="col-10">
                                <input type="text" id="edit-thresholdtx" class="form-control" name="threshold_tx"
                                    placeholder="Example: 1536k">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row mb-2">
                    <div class="col-lg-4">
                        <label class="col-form-label" for="time_rx">Burst Time <small
                                class="text-warning">Optional</small></label>
                    </div>
                    <div class="col-lg-4">
                        <div class="row">
                            <div class="col-2">
                                <label class="col-form-label" for="time_rx">RX</label>
                            </div>
                            <div class="col-10">
                                <input type="text" id="edit-timerx" class="form-control" name="time_rx"
                                    placeholder="Example: 12">
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 mt-lg-0 mt-1">
                        <div class="row">
                            <div class="col-2">
                                <label class="col-form-label" for="time_tx">TX</label>
                            </div>
                            <div class="col-10">
                                <input type="text" id="edit-timetx" class="form-control" name="time_tx"
                                    placeholder="Example: 12">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row mb-2">
                    <div class="col-lg-4">
                        <label class="col-form-label" for="priority">Priority <small
                                class="text-warning">Optional</small></label>
                    </div>
                    <div class="col-lg-8">
                        <div class="row">
                            <select name="priority" id="edit-priority" class="form-select">
                                <option value="1">1</option>
                                <option value="2">2</option>
                                <option value="3">3</option>
                                <option value="4">4</option>
                                <option value="5">5</option>
                                <option value="6">6</option>
                                <option value="7">7</option>
                                <option value="8">8</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </x-modal-form>

        <section class="row">
            <div class="card">
                <div class="card-header">
                    <button class="btn btn-outline-primary btn-sm px-5 py-2" data-bs-toggle="modal"
                        data-bs-target="#formCreateModal"><i class="fa-solid fa-plus"></i>
                        Add Profile
                    </button>
                    <hr>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        {{-- Pure DataTables implementation - no server-side rendered data --}}
                        <table class="table" id="dataTable">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Nama</th>
                                    <th>Price</th>
                                    <th>Rate Limit</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                {{-- DataTables will populate this via AJAX --}}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        {{-- Scripts --}}
        @push('script-page')
            <script>
                $(document).ready(function() {
                    // Initialize DataTable with proper server-side processing
                    let table = $('#dataTable').DataTable({
                        processing: true,
                        serverSide: false,
                        responsive: true,
                        autoWidth: false,
                        language: {
                            processing: "Memuat data...",
                            search: "Cari:",
                            lengthMenu: "Tampilkan _MENU_ data per halaman",
                            info: "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
                            infoEmpty: "Menampilkan 0 sampai 0 dari 0 data",
                            infoFiltered: "(difilter dari _MAX_ total data)",
                            paginate: {
                                first: "Pertama",
                                last: "Terakhir",
                                next: "Selanjutnya",
                                previous: "Sebelumnya"
                            },
                            emptyTable: "Tidak ada data yang tersedia"
                        },
                        ajax: {
                            url: @json(route('profiles.getData')),
                            type: 'GET',
                            headers: {
                                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                            },
                            error: function(xhr, error, code) {
                                console.log('DataTables error:', error);
                                console.log('XHR:', xhr);
                            }
                        },
                        columns: [{
                                data: 'DT_RowIndex',
                                name: 'DT_RowIndex',
                                orderable: false,
                                searchable: false,
                                width: '5%'
                            },
                            {
                                data: 'name',
                                name: 'name',
                                orderable: true,
                                searchable: true
                            },
                            {
                                data: 'price',
                                name: 'price',
                                orderable: true,
                                searchable: false,
                                width: '15%'
                            },
                            {
                                data: 'rate_limit',
                                name: 'rate_limit',
                                orderable: false,
                                searchable: false,
                                width: '30%'
                            },
                            {
                                data: 'action',
                                name: 'action',
                                orderable: false,
                                searchable: false,
                                width: '15%'
                            }
                        ],
                        order: [
                            [1, 'asc']
                        ], // Default sort by name
                        pageLength: 10,
                        lengthMenu: [
                            [10, 25, 50, 100],
                            [10, 25, 50, 100]
                        ]
                    });

                    // Create Profile
                    $('#formCreateModal form').submit(function(e) {
                        e.preventDefault();

                        let formData = new FormData(this);
                        let submitBtn = $(this).find('button[type="submit"]');

                        // Disable submit button during request
                        submitBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Menyimpan...');

                        $.ajax({
                            url: @json(route('profiles.store')),
                            type: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                            },
                            data: formData,
                            processData: false,
                            contentType: false,
                            success: function(response) {
                                if (response.success) {
                                    $('#formCreateModal').modal('hide');
                                    $('#formCreateModal form')[0].reset();

                                    Toastify({
                                        text: response.message ||
                                            "Profile Berhasil Ditambahkan!",
                                        className: "info",
                                        duration: 3000,
                                        style: {
                                            background: "linear-gradient(to right, #00b09b, #96c93d)"
                                        }
                                    }).showToast();

                                    // Reload DataTable
                                    table.ajax.reload(null, false);
                                }
                            },
                            error: function(xhr) {
                                if (xhr.status === 422) {
                                    let errors = xhr.responseJSON.errors;
                                    $.each(errors, function(key, messages) {
                                        messages.forEach((message, index) => {
                                            setTimeout(() => {
                                                Toastify({
                                                    text: message,
                                                    className: "error",
                                                    duration: 3000,
                                                    style: {
                                                        background: "linear-gradient(to right, #ff5f6d, #ffc371)"
                                                    },
                                                }).showToast();
                                            }, index * 500);
                                        });
                                    });
                                } else {
                                    Toastify({
                                        text: xhr.responseJSON?.message || "Terjadi kesalahan!",
                                        className: "error",
                                        duration: 3000,
                                        style: {
                                            background: "linear-gradient(to right, #ff5f6d, #ffc371)"
                                        },
                                    }).showToast();
                                }
                            },
                            complete: function() {
                                // Re-enable submit button
                                submitBtn.prop('disabled', false).html('Simpan');
                            }
                        });
                    });

                    // Edit Profile - Handle click on edit button (delegated event)
                    $(document).on('click', '#btn-edit', function(e) {
                        e.preventDefault();

                        let id = $(this).data('id');
                        let name = $(this).data('name');
                        let price = $(this).data('price');
                        let priority = $(this).data('priority');
                        let raterx = $(this).data('raterx');
                        let ratetx = $(this).data('ratetx');
                        let burstrx = $(this).data('burstrx');
                        let bursttx = $(this).data('bursttx');
                        let thresholdrx = $(this).data('thresholdrx');
                        let thresholdtx = $(this).data('thresholdtx');
                        let timerx = $(this).data('timerx');
                        let timetx = $(this).data('timetx');

                        // Populate form fields
                        $('#edit-name').val(name);
                        $('#edit-price').val(price);
                        $('#edit-raterx').val(raterx);
                        $('#edit-ratetx').val(ratetx);
                        $('#edit-burstrx').val(burstrx);
                        $('#edit-bursttx').val(bursttx);
                        $('#edit-thresholdrx').val(thresholdrx);
                        $('#edit-thresholdtx').val(thresholdtx);
                        $('#edit-timerx').val(timerx);
                        $('#edit-timetx').val(timetx);
                        $('#edit-priority').val(priority);

                        // Store ID for update
                        $('#formEditModal form').data('id', id);
                        $('#formEditModal').modal('show');
                    });

                    // Update Profile
                    $('#formEditModal form').submit(function(e) {
                        e.preventDefault();

                        let id = $(this).data('id');
                        if (!id) {
                            Toastify({
                                text: "ID Profile tidak ditemukan!",
                                className: "error",
                                duration: 3000,
                                style: {
                                    background: "linear-gradient(to right, #ff5f6d, #ffc371)"
                                },
                            }).showToast();
                            return;
                        }

                        let formData = new FormData(this);
                        formData.append('_method', 'PUT');

                        let submitBtn = $(this).find('button[type="submit"]');
                        submitBtn.prop('disabled', true).html(
                            '<i class="fa fa-spinner fa-spin"></i> Memperbarui...');

                        $.ajax({
                            url: `/ppp/profiles/${id}`,
                            type: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                            },
                            data: formData,
                            processData: false,
                            contentType: false,
                            success: function(response) {
                                if (response.success) {
                                    $('#formEditModal').modal('hide');

                                    Toastify({
                                        text: response.message ||
                                            "Profile Berhasil Diperbarui!",
                                        className: "info",
                                        duration: 3000,
                                        style: {
                                            background: "linear-gradient(to right, #00b09b, #96c93d)"
                                        }
                                    }).showToast();

                                    // Reload DataTable
                                    table.ajax.reload(null, false);
                                }
                            },
                            error: function(xhr) {
                                if (xhr.status === 422) {
                                    let errors = xhr.responseJSON.errors;
                                    $.each(errors, function(key, messages) {
                                        messages.forEach((message, index) => {
                                            setTimeout(() => {
                                                Toastify({
                                                    text: message,
                                                    className: "error",
                                                    duration: 3000,
                                                    style: {
                                                        background: "linear-gradient(to right, #ff5f6d, #ffc371)"
                                                    },
                                                }).showToast();
                                            }, index * 500);
                                        });
                                    });
                                } else {
                                    Toastify({
                                        text: xhr.responseJSON?.message || "Terjadi kesalahan!",
                                        className: "error",
                                        duration: 3000,
                                        style: {
                                            background: "linear-gradient(to right, #ff5f6d, #ffc371)"
                                        },
                                    }).showToast();
                                }
                            },
                            complete: function() {
                                submitBtn.prop('disabled', false).html('Update');
                            }
                        });
                    });

                    // Delete Profile - Handle click on delete button (delegated event)
                    $(document).on('click', '#btn-delete', function(e) {
                        e.preventDefault();

                        let id = $(this).data('id');
                        let name = $(this).data('name');

                        Swal.fire({
                            title: "Konfirmasi Hapus",
                            text: `Apakah Anda yakin ingin menghapus profile "${name}"?`,
                            icon: "warning",
                            showCancelButton: true,
                            confirmButtonColor: "#d33",
                            cancelButtonColor: "#3085d6",
                            confirmButtonText: "Ya, Hapus!",
                            cancelButtonText: "Batal",
                            reverseButtons: true
                        }).then((result) => {
                            if (result.isConfirmed) {
                                // Show loading
                                Swal.fire({
                                    title: 'Menghapus...',
                                    text: 'Mohon tunggu sebentar',
                                    allowOutsideClick: false,
                                    allowEscapeKey: false,
                                    showConfirmButton: false,
                                    didOpen: () => {
                                        Swal.showLoading()
                                    }
                                });

                                $.ajax({
                                    url: `/ppp/profiles/${id}`,
                                    type: 'DELETE',
                                    headers: {
                                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                                    },
                                    success: function(response) {
                                        Swal.close();

                                        Toastify({
                                            text: response.message ||
                                                "Profile Berhasil Dihapus!",
                                            className: "info",
                                            duration: 3000,
                                            style: {
                                                background: "linear-gradient(to right, #00b09b, #96c93d)"
                                            }
                                        }).showToast();

                                        // Reload DataTable
                                        table.ajax.reload(null, false);
                                    },
                                    error: function(xhr) {
                                        Swal.close();

                                        Toastify({
                                            text: xhr.responseJSON?.message ||
                                                "Gagal menghapus profile!",
                                            className: "error",
                                            duration: 3000,
                                            style: {
                                                background: "linear-gradient(to right, #ff5f6d, #ffc371)"
                                            },
                                        }).showToast();
                                    }
                                });
                            }
                        });
                    });

                    // Reset form when modal is closed
                    $('#formCreateModal').on('hidden.bs.modal', function() {
                        $(this).find('form')[0].reset();
                    });

                    $('#formEditModal').on('hidden.bs.modal', function() {
                        $(this).find('form')[0].reset();
                        $(this).find('form').removeData('id');
                    });
                });

                // Format Rupiah function
                function formatRupiah(input) {
                    let value = input.value.replace(/\D/g, "");

                    if (value) {
                        let formatted = new Intl.NumberFormat("id-ID").format(value);
                        input.value = formatted;
                    } else {
                        input.value = "";
                    }
                }

                // Remove formatting before form submission
                function cleanRupiahFormat(value) {
                    return value.replace(/\./g, '');
                }
            </script>
        @endpush
    </div>
@endsection
