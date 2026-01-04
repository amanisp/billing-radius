@extends('layouts.admin')
@section('content')
    <div id="main">
        {{-- Navbar --}}
        @include('includes.v_navbar')

        <section class="section">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h4 class="card-title mb-3">Panduan Penggunaan Fitur Isolir PPPoE</h4>
                    <form class=" mb-3" action="/ppp/settings/{{ $isolir_mode->id }}" method="post">
                        @csrf
                        @method('PUT')
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="isolir_mode" id="flexSwitchCheckChecked"
                                {{ optional($isolir_mode)->isolir_mode ? 'checked' : '' }}>
                            <label class="form-check-label" for="flexSwitchCheckChecked">Aktifkan Mode Isolir untuk Type
                                PPPoE</label>
                        </div>
                        <button type="submit" class="btn btn-sm btn-outline-primary mt-2 px-4 ">Apply</button>
                    </form>
                    <p>
                        Fitur isolir ini dirancang untuk membantu mitra ISP dalam mengelola pelanggan yang belum
                        menyelesaikan pembayaran.
                        Silakan salin skrip di bawah ini dan tempelkan ke terminal MikroTik melalui Winbox.
                    </p>


                    <div class=" position-relative">
                        <button class="btn btn-sm btn-outline-primary copy-btn"
                            data-clipboard-target="#script1">Copy</button>
                        <pre class="code-container"><code id="script1">
/ip proxy 
set enabled=yes parent-proxy=0.0.0.0" 
/ip proxy access 
add action=redirect action-data=isolir.amanisp.net.id src-address=172.30.0.0/16
                            </code></pre>
                    </div>

                    <div class=" position-relative">
                        <button class="btn btn-sm btn-outline-primary copy-btn"
                            data-clipboard-target="#script2">Copy</button>
                        <pre class="code-container"><code id="script2">
/ip firewall filter
add action=drop chain=forward comment="Generate AMANISP - Isolir WebProxy" dst-address=!157.15.63.105/28 protocol=tcp src-address=172.30.0.0/16
add action=drop chain=forward comment="Generate AMANISP - Isolir WebProxy" dst-address=!157.15.63.100 dst-port=!53,5353 protocol=udp src-address=172.30.0.0/16
                            </code></pre>
                    </div>

                    <div class="position-relative">
                        <button class="btn btn-sm btn-outline-primary copy-btn"
                            data-clipboard-target="#script3">Copy</button>
                        <pre class="code-container"><code id="script3">
/ip firewall nat
add action=redirect chain=dstnat comment="Generate AMANISP - Isolir WebProxy" dst-address=!157.15.63.105/28 dst-port=80,443 protocol=tcp src-address=172.30.0.0/16 to-ports=8080
</code></pre>
                    </div>
                    <div class="alert alert-info">
                        <strong>Catatan:</strong> Pastikan konfigurasi sesuai dengan kebutuhan jaringan Anda.
                    </div>

                    <ul class="list-group list-group-flush">
                        <li class="list-group-item">
                            <strong>1.</strong> IP Address isolir akan dibuat secara otomatis oleh sistem dengan
                            Network: <code>172.30.0.0/16</code>.
                        </li>
                        <li class="list-group-item">
                            <strong>2.</strong> Tidak perlu menambahkan IP Pool secara manual.
                        </li>
                        <li class="list-group-item">
                            <strong>3.</strong> Jika ingin mengganti halaman isolir, edit pengaturan akses di
                            Web Proxy dan ubah
                            <code>dst-address !157.15.63.100</code> ke IP isolir Anda.
                        </li>
                        <li class="list-group-item">
                            <strong>4.</strong> Setelah mode isolir aktif, sistem akan otomatis mengirim
                            perintah <code>sendDisconnect</code> ke pelanggan yang sedang online.
                            Saat mereka reconnect, IP isolir akan diterapkan.
                        </li>
                        <li class="list-group-item">
                            <strong>5.</strong> Jika ada pertanyaan mengenai konfigurasi isolir, hubungi tim
                            support melalui kontak yang tersedia.
                        </li>
                    </ul>

                </div>
            </div>

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
                var clipboard = new ClipboardJS('.copy-btn');
                clipboard.on('success', function(e) {
                    alert('Skrip berhasil disalin!');
                    e.clearSelection();
                });
                clipboard.on('error', function(e) {
                    alert('Gagal menyalin. Silakan salin manual.');
                });
            </script>
        @endpush
    @endsection
