@extends('layouts.admin')
@section('content')
    <div id="main">
        {{-- Navbar --}}
        @include('includes.v_navbar')


        {{-- Modal Script --}}
        <div class="modal fade" id="vpnModal" tabindex="-1" aria-labelledby="vpnModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="vpnModalLabel">RADIUS Configuration Scripts</h5>
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
                                <strong>3.</strong> Buka menu RADIUS, jika langkah diatas sudah berhasil, maka akan tampil
                                Radius Server yang baru dibuat.
                            </li>
                            <li class="list-group-item">
                                <strong>4.</strong> Ubah pengaturan Hotspot Server Profile di Tab RADIUS centang Use RADIUS.
                            </li>
                            <li class="list-group-item">
                                <strong>5.</strong> Untuk PPP centang Use Radius dan Accounting pada menu PPP > Secret (ppp
                                Auth&Acct).
                            </li>
                        </ul>
                        <hr>
                        <!-- SSTP Script -->
                        <div class="mb-3">
                            <h6>Radius Configuration</h6>
                            <div class="position-relative">
                                <button class="btn btn-sm btn-outline-primary copy-btn"
                                    data-clipboard-target="#nasScript">Copy</button>
                                <pre class="code-container"><code id="nasScript">
                        </code></pre>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>

        <section class="row">

            <div class="alert alert-info">
                <strong>Catatan:</strong> Pastikan anda sudah menggunakan IP Public anda atau membuat account VPN terlebih
                dulu pada link berikut <a href="{{ route('vpn.index') }}" class="btn btn-danger btn-sm">Create VPN</a> Dan
                pastikan juga Account VPN sudah di setting pada router MikroTik yang ingin di gunakan sebagai router NAS.
                Jika ada kendala untuk setting VPN silahkan hubungi team Teknis kami.


            </div>
            <!-- Card untuk Form (col-4) -->
            <div class="row">
                <div class="col-md-4">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title mb-3">Tambah NAS</h5>
                            <span><b><i class="fa-solid fa-circle-info"></i> IP Address</b> isi IP Public atau dari VPN
                                yang sudah di buat <a href="{{ route('vpn.index') }}"
                                    class="link text-danger">Disini</a>.</span>
                            <form class="mt-2" action="{{ route('radius.store') }}" method="POST">
                                @csrf
                                <div class="mb-3">
                                    <label class="form-label">NAS Name</label>
                                    <input required type="text" class="form-control" name="name"
                                        value="{{ old('name') }}" placeholder="Mikrotik 1">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">IP Address</label>
                                    <input type="text" class="form-control" name="ip_router"
                                        value="{{ old('ip_router') }}" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Secret</label>
                                    <input type="text" class="form-control" name="secret" required>
                                </div>
                                <button type="submit" class="btn btn-outline-primary w-100">Add Radius</button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Card untuk Tabel (col-8) -->
                <div class="col-md-8">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title mb-3">Daftar NAS</h5>
                            <div class="table-responsive">
                                <table class="table" id="table1">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Nama</th>
                                            <th>IP Radius</th>
                                            <th>IP Router</th>
                                            <th>Secret</th>
                                            <th>Script</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($data as $index => $nas)
                                            <tr>
                                                <td>{{ $index + 1 }}</td>
                                                <td>{{ $nas->name }}</td>
                                                <td id="ip_radius">{{ $nas->ip_radius }}</td>
                                                <td>{{ $nas->ip_router }}</td>
                                                <td id="secret">{{ $nas->secret }}</td>
                                                <td><button data-bs-toggle="modal" data-bs-target="#vpnModal"
                                                        class="btn btn-outline-info btn-sm">
                                                        <i class="fa-solid fa-info"></i>
                                                    </button></td>
                                                <td class="d-flex gap-2">

                                                    <form action="{{ route('radius.destroy', $nas->id) }}" method="POST">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button class="btn btn-outline-danger btn-sm">
                                                            <i class="fa-solid fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        @empty

                                            <tr>
                                                <td colspan="7" class="text-center">Data Belum Tersedia</td>
                                                <td class="d-none"></td>
                                                <td class="d-none"></td>
                                                <td class="d-none"></td>
                                                <td class="d-none"></td>
                                                <td class="d-none"></td>
                                                <td class="d-none"></td>
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
                /* Agar tidak melebar */
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
                    let table1 = $('#table1').DataTable({
                        responsive: true,
                        pageLength: 10,
                        lengthMenu: [
                            [10, 25, 50, 100, -1],
                            [10, 25, 50, 100, "All"]
                        ],
                        order: [
                            [1, 'asc']
                        ], // Sort by name column by default
                        columnDefs: [{
                                targets: [0],
                                orderable: false
                            },
                            {
                                targets: [5, 6],
                                orderable: false,
                                searchable: false
                            } // Script & Action columns
                        ],
                        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
                            '<"row"<"col-sm-12"tr>>' +
                            '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>'
                    });

                    window.table1 = table1;

                    // --- ClipboardJS Init ---
                    var clipboard = new ClipboardJS('.copy-btn');
                    clipboard.on('success', function(e) {
                        alert('Skrip berhasil disalin!');
                        e.clearSelection();
                    });
                    clipboard.on('error', function(e) {
                        alert('Gagal menyalin. Silakan salin manual.');
                    });

                    // --- Event Delegation untuk tombol modal ---
                    document.addEventListener('click', function(e) {
                        let btn = e.target.closest('[data-bs-target="#vpnModal"]');
                        if (!btn) return;

                        let row = btn.closest("tr");
                        let secretEl = row.querySelector("#secret");
                        let ipRadiusEl = row.querySelector("#ip_radius");

                        if (!secretEl || !ipRadiusEl) return;

                        let secret = secretEl.innerText.trim();
                        let ip_radius = ipRadiusEl.innerText.trim();

                        document.getElementById("nasScript").innerText = `
/radius
add address=${ip_radius} secret="${secret}" service=ppp,hotspot,dhcp timeout=2000ms
/radius incoming
set accept=yes
        `.trim();
                    });

                });
            </script>
        @endpush
    @endsection
