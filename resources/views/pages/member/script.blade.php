@push('script-page')
    <script>
        $(document).ready(function() {
            let table = $('#dataTable').DataTable({
                processing: true,
                serverSide: false,
                responsive: true,
                autoWidth: false,
                ajax: @json(route('members.getData')),
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                columns: [{
                        data: 'DT_RowIndex',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'area_name',
                        name: 'connection.area.name'
                    },
                    {
                        data: 'fullname',
                    },
                    {
                        data: 'phone_number',
                    },
                    {
                        data: 'action',
                    },
                ]
            });

            // Edit
            $(document).on("click", ".btn-edit", function() {
                let id = $(this).data("id");

                // Isi nilai form dengan data dari tombol edit
                $("#edit-fullname").val($(this).data("fullname"));
                $("#edit-phone_number").val($(this).data("phone_number"));
                $("#edit-email").val($(this).data("email"));
                $("#edit-id_card").val($(this).data("id_card"));
                $("#edit-address").val($(this).data("address"));

                // Set action form dengan URL update
                $("#FormEdit").attr("action", `/ppp/members/${id}`);

                // Simpan ID ke form
                $("#FormEdit").data("id", id);

                // Tampilkan modal edit
                $("#formEditModal").modal("show");
            });

            $("#FormEdit").submit(function(e) {
                e.preventDefault(); // Hindari reload halaman

                let id = $(this).data("id");
                let formData = new FormData(this);

                $.ajax({
                    url: `/ppp/members/update/${id}`, // URL update
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
                                text: xhr.responseJSON?.message ||
                                    "Terjadi kesalahan!",
                                className: "error",
                                style: {
                                    background: "red"
                                },
                            }).showToast();
                        }
                    }
                });
            });

            // Payment Detail Handler
            $(document).on("click", ".btn-payment", function() {
                let id = $(this).data("id");
                let fullname = $(this).data("fullname");
                let paymentId = $(this).data("payment-id");

                // Set member name
                $("#member-name").text(fullname);

                // Isi form dengan data existing (jika ada)
                $("#payment_type").val($(this).data("payment-type") || "");
                $("#billing_period").val($(this).data("billing-period") || 1);
                $("#active_date").val($(this).data("active-date") || "");
                $("#amount").val($(this).data("amount") || 0);
                $("#discount").val($(this).data("discount") || 0);
                $("#ppn").val($(this).data("ppn") || 0);
                $("#last_invoice").val($(this).data("last-invoice") || "");

                // Set form action
                $("#FormPaymentDetail").attr("action", `/ppp/members/${id}/payment-detail`);
                $("#FormPaymentDetail").data("id", id);

                // Calculate initial total
                calculateTotal();

                // Show modal
                $("#paymentDetailModal").modal("show");
            });

            // Calculate Total Estimate
            function calculateTotal() {
                let amount = parseFloat($("#amount").val()) || 0;
                let period = parseFloat($("#billing_period").val()) || 1;
                let discount = parseFloat($("#discount").val()) || 0;
                let ppn = parseFloat($("#ppn").val()) || 0;

                let baseAmount = amount * period;
                let ppnAmount = (baseAmount * ppn) / 100;
                let discountAmount = (baseAmount * discount) / 100;
                let total = baseAmount + ppnAmount - discountAmount;

                $("#total-estimate").text("Rp " + new Intl.NumberFormat('id-ID').format(total));
            }

            // Update calculation on input change
            $("#amount, #billing_period, #discount, #ppn").on("input", function() {
                calculateTotal();
            });

            // Submit Payment Detail Form
            $("#FormPaymentDetail").submit(function(e) {
                e.preventDefault();

                let id = $(this).data("id");
                let formData = new FormData(this);

                $.ajax({
                    url: `/ppp/members/${id}/payment-detail`,
                    type: "POST",
                    headers: {
                        "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content")
                    },
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            $("#paymentDetailModal").modal("hide");

                            Toastify({
                                text: "Payment Detail Berhasil Disimpan!",
                                className: "info",
                                style: {
                                    background: "green"
                                },
                            }).showToast();

                            table.ajax.reload();
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

            // Delete
            $(document).on('click', '#btn-delete', function() {
                let id = $(this).data('id');
                let name = $(this).data('fullname');
                Swal.fire({
                    title: "Anda Yakin?",
                    text: `Apakah ingin menghapus member ${name}!`,
                    icon: "warning",
                    showCancelButton: true,
                    confirmButtonColor: "#3085d6",
                    cancelButtonColor: "#d33",
                    confirmButtonText: "Yes, delete it!"
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: "/ppp/members/" + id,
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
        });
    </script>
@endpush
