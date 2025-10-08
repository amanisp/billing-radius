@extends('layouts.admin')
@section('content')
    <div id="main">
        @include('includes.v_navbar')

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
                    <h3>VPN</h3>
                </div>
                <div class="col-12 col-md-6 order-md-2 order-first">
                    <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard.index') }}">Dashboard</a></li>
                            <li class="breadcrumb-item active" aria-current="page">VPN</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>

        {{-- Modal Script --}}
        <div class="modal fade" id="vpnModal" tabindex="-1" aria-labelledby="vpnModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="vpnModalLabel">VPN Configuration Scripts</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <h6><i class="fa-solid fa-circle-info"></i> Tips Penggunaan</h6>
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item">
                                <strong>1.</strong> Pilih salah satu mode yang akan digunakan.
                            </li>
                            <li class="list-group-item">
                                <strong>2.</strong> Salin / Copy seluruh isi script pada kolom mode yang dipilih.
                            </li>
                            <li class="list-group-item">
                                <strong>3.</strong> Login mikrotik melalui Winbox, buka menu New Terminal kemudian Tempel /
                                Paste script yang sudah di salin / copy sebelumnya, lanjut tekan tombol Enter di keyboard.
                            </li>
                            <li class="list-group-item">
                                <strong>4.</strong> Buka menu PPP > Interface jika langkah diatas sudah berhasil, maka akan
                                tampil interface vpn baru sesuai mode yang dipilih.
                            </li>
                            <li class="list-group-item">
                                <strong>5.</strong> Lihat status interface VPN, jika belum terhubung / Connected silahkan
                                coba menggunakan mode yang lain. Jika sudah terhubung / connected (cirinya ada icon huruf R
                                di samping interface VPN).
                            </li>
                        </ul>
                        <hr>
                        <!-- SSTP Script -->
                        <div class="mb-3">
                            <h6>SSTP Configuration</h6>
                            <div class="position-relative">
                                <button class="btn btn-sm btn-outline-primary copy-btn"
                                    data-clipboard-target="#sstpScript">Copy</button>
                                <pre class="code-container"><code id="sstpScript"></code></pre>
                            </div>
                        </div>
                        <div class="mb-3">
                            <h6>L2TP Configuration</h6>
                            <div class="position-relative">
                                <button class="btn btn-sm btn-outline-primary copy-btn"
                                    data-clipboard-target="#l2tpScript">Copy</button>
                                <pre class="code-container"><code id="l2tpScript"></code></pre>
                            </div>
                        </div>
                        <div class="mb-3">
                            <h6>PPTP Configuration</h6>
                            <div class="position-relative">
                                <button class="btn btn-sm btn-outline-primary copy-btn"
                                    data-clipboard-target="#pptpScript">Copy</button>
                                <pre class="code-container"><code id="pptpScript"></code></pre>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <section class="row">
            <div class="alert alert-info">
                <strong>Catatan:</strong> VPN digunakan untuk menghubungkan Router MikroTik anda dengan Router kami
                melalui jaringan internet/public. Radius server kami tidak dapat meneruskan paket request dari router anda
                jika
                router anda tidak mempunyai IP Public atau tidak dalam satu jaringan. Setelah router MikroTik anda
                terhubung dengan router kami, otomatis radius server akan merespond paket request anda melalui IP Private
                dari
                VPN.
            </div>

            <!-- Card untuk Form (col-4) -->
            <div class="row">
                <div class="col-md-4">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title mb-3">Tambah VPN</h5>
                            <form action="{{ route('vpn.store') }}" method="POST">
                                @csrf
                                <div class="mb-3">
                                    <label class="form-label">VPN Name</label>
                                    <input required type="text" class="form-control" name="name"
                                        value="{{ old('name') }}" placeholder="Mikrotik 1">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Username</label>
                                    <input type="text" class="form-control" name="username" value="{{ old('username') }}"
                                        required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Password</label>
                                    <input type="text" class="form-control" name="password" required>
                                </div>
                                <button type="submit" class="btn btn-outline-primary w-100">Add VPN</button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Card untuk Tabel (col-8) -->
                <div class="col-md-8">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title mb-3">Daftar VPN</h5>
                            <div class="table-responsive">
                                <table class="table table-striped" id="vpnTable">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Nama</th>
                                            <th>Username</th>
                                            <th>Password</th>
                                            <th>IP</th>
                                            <th>Script</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($data as $index => $vpn)
                                            <tr>
                                                <td>{{ $index + 1 }}</td>
                                                <td>{{ $vpn->name }}</td>
                                                <td class="vpn-username">{{ $vpn->username }}</td>
                                                <td class="vpn-password">{{ $vpn->password }}</td>
                                                <td><span class="badge bg-primary">{{ $vpn->ip_address }}</span></td>
                                                <td>
                                                    <button data-bs-toggle="modal" data-bs-target="#vpnModal"
                                                        class="btn btn-outline-info btn-sm vpn-script-btn"
                                                        data-username="{{ $vpn->username }}"
                                                        data-password="{{ $vpn->password }}">
                                                        <i class="fa-solid fa-info"></i>
                                                    </button>
                                                </td>
                                                <td>
                                                    <button class="btn btn-outline-danger btn-sm btn-delete-vpn"
                                                        data-id="{{ $vpn->id }}" data-name="{{ $vpn->name }}">
                                                        <i class="fa-solid fa-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="7" class="text-center">Data Belum Tersedia</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <hr>
        </section>

        <style>
            .code-container {
                background-color: #eaeaea;
                color: #1e1e1e;
                padding: 15px;
                border-radius: 8px;
                font-family: monospace;
                white-space: pre-wrap;
                overflow: auto;
                position: relative;
                padding-top: 50px
            }

            .copy-btn {
                position: absolute;
                top: 10px;
                right: 10px;
                z-index: 10;
            }
        </style>

        @push('script-page')
            <script>
                document.addEventListener("DOMContentLoaded", function() {
                    // --- Initialize DataTable ---
                    let vpnTable = $('#vpnTable').DataTable({
                        responsive: true,
                        pageLength: 10,
                        lengthMenu: [
                            [10, 25, 50, 100, -1],
                            [10, 25, 50, 100, "All"]
                        ],
                        order: [
                            [1, 'asc']
                        ], // Sort by Name
                        columnDefs: [{
                                targets: [0],
                                orderable: false
                            }, // # column
                            {
                                targets: [5, 6],
                                orderable: false,
                                searchable: false
                            } // Script & Action
                        ],
                        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
                            '<"row"<"col-sm-12"tr>>' +
                            '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>'
                    });

                    window.vpnTable = vpnTable;

                    // --- ClipboardJS Init ---
                    var clipboard = new ClipboardJS('.copy-btn');
                    clipboard.on('success', function(e) {
                        alert('Skrip berhasil disalin!');
                        e.clearSelection();
                    });
                    clipboard.on('error', function(e) {
                        alert('Gagal menyalin. Silakan salin manual.');
                    });

                    // --- Event Delegation untuk tombol Script ---
                    document.addEventListener('click', function(e) {
                        let btn = e.target.closest('.vpn-script-btn');
                        if (!btn) return;

                        const username = btn.getAttribute('data-username');
                        const password = btn.getAttribute('data-password');

                        document.getElementById("sstpScript").innerText = `
/interface sstp-client
add connect-to=157.15.63.97:4433 name=sstp-AmanBill password="${password}" user=${username} disabled=no
/ip route
add disabled=no distance=1 dst-address=10.137.24.15 gateway=sstp-AmanBill`.trim();

                        document.getElementById("l2tpScript").innerText = `
/interface l2tp-client
add connect-to=157.15.63.97 name=l2tp-AmanBill password="${password}" user=${username} disabled=no
/ip route
add disabled=no distance=1 dst-address=10.137.24.15 gateway=l2tp-AmanBill`.trim();

                        document.getElementById("pptpScript").innerText = `
/interface pptp-client
add connect-to=157.15.63.97 name=pptp-AmanBill password="${password}" user=${username} disabled=no
/ip route
add disabled=no distance=1 dst-address=10.137.24.15 gateway=pptp-AmanBill`.trim();
                    });

                    // --- Event Delegation untuk Delete VPN ---
                    document.addEventListener('click', function(e) {
                        let btn = e.target.closest('.btn-delete-vpn');
                        if (!btn) return;

                        let id = btn.getAttribute('data-id');
                        let name = btn.getAttribute('data-name');

                        Swal.fire({
                            title: "Anda Yakin?",
                            text: `Apakah ingin menghapus account ${name}?`,
                            icon: "warning",
                            showCancelButton: true,
                            confirmButtonColor: "#3085d6",
                            cancelButtonColor: "#d33",
                            confirmButtonText: "Yes, delete it!"
                        }).then((result) => {
                            if (result.isConfirmed) {
                                $.ajax({
                                    url: `/vpn/${id}`,
                                    type: "DELETE",
                                    data: {
                                        _token: "{{ csrf_token() }}"
                                    },
                                    success: function(response) {
                                        Toastify({
                                            text: "Data Berhasil Dihapus!",
                                            className: "info",
                                        }).showToast();
                                        vpnTable.ajax
                                            .reload(); // Reload DataTable jika pakai serverSide
                                        location
                                            .reload(); // Kalau pakai blade rendering, reload halaman
                                    },
                                    error: function() {
                                        Swal.fire({
                                            title: "Error!",
                                            text: "Gagal menghapus data.",
                                            icon: "error"
                                        });
                                    }
                                });
                            }
                        });
                    });
                });
            </script>
        @endpush
    @endsection
