<script>
    document.addEventListener("DOMContentLoaded", function() {
        // --- Variabel Billing ---
        const checkbox = document.getElementById("billing-active");
        const billingElements = document.querySelector("#bill");
        const profileSelect = document.querySelector("select[name='profile_id']");
        const amountInput = document.getElementById("amount");
        const ppnInput = document.getElementById("ppn");
        const discountInput = document.getElementById("discount");
        const paymentTotalInput = document.getElementById("payment_total");

        // --- Variabel Filter ODP ---
        const areaSelect = document.getElementById("area");
        const odpSelect = document.getElementById("odp");
        const allOdpOptions = Array.from(odpSelect.options).slice(
            1); // Simpan semua opsi ODP kecuali "Pilih Area Dulu"

        // --- Fungsi Toggle Form Billing ---
        function toggleBillingElements() {
            billingElements.style.display = checkbox.checked ? "block" : "none";
        }

        // --- Format ke Rupiah ---
        function formatCurrency(value) {
            return new Intl.NumberFormat("id-ID", {
                style: "currency",
                currency: "IDR",
                minimumFractionDigits: 0
            }).format(value);
        }

        // --- Update Harga dari Profile ---
        function updateAmount() {
            const selectedProfile = profileSelect.value;
            const prices = JSON.parse(profileSelect.getAttribute("data-prices") || "{}");
            let price = parseFloat(prices[selectedProfile]) || 0;

            amountInput.value = formatCurrency(price);
            calculateTotal();
        }

        // --- Hitung Total Pembayaran ---
        function calculateTotal() {
            let amount = parseFloat(amountInput.value.replace(/[^0-9]/g, "")) || 0;
            let ppn = parseFloat(ppnInput.value) || 0;
            let discount = parseFloat(discountInput.value.replace(/[^0-9]/g, "")) || 0;

            let totalPpn = (amount * ppn) / 100;
            let total = amount + totalPpn - discount;

            paymentTotalInput.value = formatCurrency(total);
        }

        // --- Filter ODP Berdasarkan Area ---
        function filterOdpByArea() {
            let selectedArea = areaSelect.value;

            // Reset dropdown ODP
            odpSelect.innerHTML = '<option disabled selected>Pilih Salah Satu</option>';

            // Filter opsi ODP sesuai dengan area yang dipilih
            allOdpOptions.forEach(option => {
                if (option.getAttribute("data-area") == selectedArea) {
                    odpSelect.appendChild(option.cloneNode(true)); // Tambahkan opsi yang cocok
                }
            });
        }

        // --- Inisialisasi ---
        toggleBillingElements();

        // --- Event Listener ---
        checkbox.addEventListener("change", toggleBillingElements);
        profileSelect.addEventListener("change", updateAmount);
        ppnInput.addEventListener("input", calculateTotal);
        discountInput.addEventListener("input", calculateTotal);
        areaSelect.addEventListener("change", filterOdpByArea);
    });
</script>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        const billingTypeSelect = document.getElementById("billing_type");
        const billingPeriodSelect = document.getElementById("billing_period");

        // Simpan opsi berdasarkan Billing Type
        const options = {
            prabayar: [{
                    value: "fixed_date",
                    text: "Fixed Date"
                },
                {
                    value: "renewal",
                    text: "Renewal"
                }
            ],
            pascabayar: [{
                    value: "fixed_date",
                    text: "Fixed Date"
                },
                {
                    value: "billing_cycle",
                    text: "Billing Cycle"
                }
            ]
        };

        function updateBillingPeriodOptions() {
            const selectedType = billingTypeSelect.value;

            // Hapus semua opsi lama
            billingPeriodSelect.innerHTML = "";

            // Tambahkan opsi yang sesuai dengan Billing Type yang dipilih
            options[selectedType].forEach(option => {
                const newOption = document.createElement("option");
                newOption.value = option.value;
                newOption.textContent = option.text;
                billingPeriodSelect.appendChild(newOption);
            });
        }

        // Panggil fungsi saat halaman dimuat & ketika Billing Type berubah
        updateBillingPeriodOptions();
        billingTypeSelect.addEventListener("change", updateBillingPeriodOptions);
    });
</script>

{{-- Ajax Get Data --}}
<script>
    $(document).ready(function() {
        let table = $('#dataTable').DataTable({
            processing: true,
            serverSide: true,
            responsive: true,
            autoWidth: false,
            ajax: {
                url: @json(route('pppoe.getData')),
                data: function(d) {
                    // Menggunakan ID yang sudah ada di HTML
                    d.status_filter = $('#operationFilter').val();
                    d.profile_filter = $('#roleFilter').val();
                }
            },
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            columns: [{
                    data: 'DT_RowIndex',
                    orderable: false,
                    searchable: false
                },
                {
                    data: 'internet_number'
                },
                {
                    data: 'username'
                },
                {
                    data: 'type'
                },
                {
                    data: 'nas'
                },
                {
                    data: 'profile'
                },
                {
                    data: 'togle'
                },
                {
                    data: 'status'
                },
                {
                    data: 'internet'
                },
                {
                    data: 'action',
                    orderable: false,
                    searchable: false
                }
            ]
        });

        // Make table available globally
        window.table = table;

        // Event listener untuk filter dengan ID yang sudah ada
        $('#operationFilter, #roleFilter').on('change', function() {

            table.draw();
        });

        // REMOVED: Duplicate Create Data form handler - now handled in pppoe-page-create.js

        // Import Excel
        $(document).ready(function() {
            $('#importExcel').submit(function(e) {
                e.preventDefault(); // Mencegah reload halaman

                let formData = new FormData(this); // Ambil data form (termasuk file)
                let fileInput = $('input[name="file"]')[0].files;

                // Kosongkan error log setiap kali form disubmit
                $('#errorLog').html('');

                // Cek apakah file kosong sebelum submit
                if (fileInput.length === 0) {
                    Toastify({
                        text: "Harap pilih file sebelum mengimpor!",
                        className: "error",
                        style: {
                            background: "red"
                        },
                    }).showToast();
                    return;
                }

                $.ajax({
                    url: "/ppp/pppoe/import", // Ganti dengan route upload kamu
                    type: "POST",
                    data: formData,
                    processData: false, // Jangan diproses oleh jQuery
                    contentType: false, // Jangan set contentType otomatis
                    headers: {
                        "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr(
                            "content") // CSRF Token
                    },
                    beforeSend: function() {
                        $('#inputGroupFileAddon04').prop('disabled', true).text(
                            'Uploading...');
                    },
                    success: function(response) {
                        $('#modalExcel').modal('hide');
                        Toastify({
                            text: "Data Berhasil Diimport!",
                            className: "info",
                        }).showToast();
                        table.ajax.reload();
                        $('#importExcel')[0].reset(); // Reset form setelah sukses
                    },
                    error: function(xhr) {
                        if (xhr.status === 422) {
                            try {
                                let response = JSON.parse(xhr.responseText);
                                if (response.errors) {
                                    let errorHtml =
                                        '<div class="alert alert-danger"><ul>';
                                    response.errors.forEach(error => {
                                        errorHtml +=
                                            `<li>Baris ${error.row}: ${error.error} (${error.username})</li>`;
                                    });
                                    errorHtml += '</ul></div>';
                                    $('#errorLog').html(errorHtml);
                                } else {
                                    $('#errorLog').html(
                                        '<div class="alert alert-danger">Terjadi kesalahan validasi.</div>'
                                    );
                                }
                            } catch (e) {
                                $('#errorLog').html(
                                    '<div class="alert alert-danger">Gagal membaca respon error.</div>'
                                );
                            }
                        } else {
                            Toastify({
                                text: 'Sepertinya ada yang salah!',
                                className: "error",
                                style: {
                                    background: "red"
                                },
                            }).showToast();
                        }

                        table.ajax.reload();
                    },
                    complete: function() {
                        $('#inputGroupFileAddon04').prop('disabled', false).text(
                            'Upload');
                    }
                });
            });
        });

        // Edit
        $(document).on("click", "#btn-edit", function() {
            let id = $(this).data("id");
            let type = ($(this).data("type") || 'pppoe').toLowerCase();

            // Temporarily enable the select to set value
            const typeSelect = $("#edit-typeUserSelect");
            typeSelect.prop('disabled', false);
            typeSelect.val(type);
            typeSelect.prop('disabled', true);
            $("#edit-typeUserHidden").val(type);

            // Isi nilai form dengan data dari tombol edit
            $("#edit-username").val($(this).data("username"));
            $("#edit-password").val($(this).data("password"));
            $("#edit-macAddress").val($(this).data("mac_address"));

            let selectedProfile = $(this).data("profile");
            $("#edit-profileSelect").val(selectedProfile).trigger("change");

            let selectedArea = $(this).data("area_id");
            let selectedMember = $(this).data("member_id");
            let selectedOptical = $(this).data("optical_id");
            let isolir = $(this).data("isolir");

            $("#edit-member").val(selectedMember).trigger("change");
            $("#edit-area").val(selectedArea);
            $("#dropdown-isolir").val(isolir)
            $("#edit-odp").val(selectedOptical);

            // Set action form dengan URL update
            $("#FormEdit").attr("action", `/ppp/pppoe/update/${id}`);

            // Simpan ID ke dalam form untuk keperluan update
            $("#FormEdit").data("id", id);

            // Tampilkan modal edit
            $("#formEditModal").modal("show");
        });

        // Isolir
        $(document).on('change', '.toggle-isolir', function() {
            let isChecked = $(this).is(':checked');
            let statusText = isChecked ? 'Mengaktifkan' : 'Mengisolir';
            let statusConfirm = isChecked ? 'Ya, Aktifkan' : 'Ya, Isolir';
            let accountId = $(this).data('id');
            let name = $(this).data('name');

            Swal.fire({
                title: "Konfirmasi",
                text: `Apakah Anda ingin ${statusText} akun ${name}?`,
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: statusConfirm,
                cancelButtonText: "Batal",
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    // Kirim AJAX untuk update isolir
                    $.ajax({
                        url: `/ppp/pppoe/update/isolir/${accountId}`,
                        type: "PUT",
                        data: {
                            _token: $('meta[name="csrf-token"]').attr('content'),
                            isolir: isChecked ? 0 : 1
                        },
                        success: function(response) {
                            Swal.fire({
                                title: "Berhasil!",
                                text: response.message,
                                icon: "success"
                            });
                            table.ajax.reload(); // Reload tabel tanpa refresh
                        },
                        error: function() {
                            Swal.fire({
                                title: "Error!",
                                text: "Terjadi kesalahan, silakan coba lagi.",
                                icon: "error"
                            });
                            $(this).prop('checked', !
                                isChecked); // Balikkan toggle jika error
                        }
                    });
                } else {
                    $(this).prop('checked', !isChecked); // Balikkan toggle jika batal
                }
            });
        });

        $("#FormEdit").submit(function(e) {
            e.preventDefault(); // Hindari reload halaman

            let id = $(this).data("id");
            let formData = new FormData(this);

            $.ajax({
                url: `/ppp/pppoe/update/${id}`, // URL update
                type: "POST",
                headers: {
                    "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content")
                },
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        // Tutup modal setelah update sukses
                        $("#formEditModal").modal("hide");

                        // Tampilkan notifikasi sukses
                        Toastify({
                            text: "Data Berhasil Diperbarui!",
                            className: "info",
                        }).showToast();

                        // Reload DataTable
                        table.ajax.reload();
                    }
                },
                error: function(xhr) {
                    if (xhr.status === 422) { // Validasi error dari Laravel
                        let errors = xhr.responseJSON.errors;
                        $.each(errors, function(key, messages) {
                            messages.forEach((message, index) => {
                                setTimeout(() => {
                                    Toastify({
                                        text: message,
                                        className: "error",
                                        style: {
                                            background: "red"
                                        },
                                    }).showToast();
                                }, index * 500);
                            });
                        });
                    } else {
                        Toastify({
                            text: xhr.responseJSON?.message || "Terjadi kesalahan!",
                            className: "error",
                            style: {
                                background: "red"
                            },
                        }).showToast();
                    }
                }
            });
        });

        // Show Session
        $(document).on("click", "#btn-session", function() {
            let username = $(this).data("username");
            $(".modal-title-session").text("Session for: " + username);
            $("#formSession").modal("show");

            $("#statistik").text("Menghitung statistik...");
            let totalUpload = 0;
            let totalDownload = 0;

            // Hapus instance DataTables jika sudah ada
            if ($.fn.DataTable.isDataTable('#dataSession')) {
                $('#dataSession').DataTable().clear().destroy();
            }

            // Inisialisasi ulang DataTables dengan limit 5 dan tanpa sorting
            $('#dataSession').DataTable({
                processing: true,
                serverSide: true,
                responsive: true,
                autoWidth: false,
                pageLength: 5, // Default 5 data per halaman
                ordering: false, // Matikan sorting
                ajax: {
                    url: `/ppp/pppoe/session/${username}`,
                    dataSrc: function(json) {
                        $("#statistik").text(
                            `Statistik Pemakaian Bulan Ini: Upload ${json.total_upload}, Download ${json.total_download}`
                        );
                        return json.data; // Pastikan mengembalikan array data
                    }
                },
                columns: [{
                        data: 'DT_RowIndex',
                        name: 'DT_RowIndex',
                        searchable: false,
                        orderable: false
                    },
                    {
                        data: 'login_time',
                        orderable: false
                    },
                    {
                        data: 'last_update',
                        orderable: false
                    },
                    {
                        data: 'ip_mac',
                        orderable: false
                    },
                    {
                        data: 'upload',
                        orderable: false
                    },
                    {
                        data: 'download',
                        orderable: false
                    },
                    {
                        data: 'uptime',
                        orderable: false
                    },
                ]
            });
        });

        // Delete
        $(document).on('click', '#btn-delete', function() {
            let id = $(this).data('id');
            let name = $(this).data('username');
            Swal.fire({
                title: "Anda Yakin?",
                text: `Apakah ingin menghapus account ${name}!`,
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#3085d6",
                cancelButtonColor: "#d33",
                confirmButtonText: "Yes, delete it!"
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: "/ppp/pppoe/" + id,
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

        $('#customerSelect').select2({
            'dropdownParent': '#formCreateModal',
            theme: 'bootstrap-5'
        });

        $('#edit-member').select2({
            'dropdownParent': '#formEditModal',
            theme: 'bootstrap-5'
        });
    });
</script>
