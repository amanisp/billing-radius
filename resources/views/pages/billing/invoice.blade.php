<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice {{ $invoice->inv_number }}</title>
    <style>
        @page {
            margin: 0;
        }

        * {
            font-size: 12px;
            font-family: 'Times New Roman', Times, serif;
        }

        body {
            font-family: 'Times New Roman', Times, serif;
            margin: 0;
            padding: 0;
        }

        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
        }

        .invoice-desc {
            font-size: 18px;
            text-align: center;
            font-family: Arial, sans-serif;
        }

        .container {
            font-size: 12px !important;
            margin: 0 1.5cm;
            box-sizing: border-box;
        }

        .invoice-info h3 {
            font-size: 12px !important;
        }

        .body-info {
            width: 100%;
            display: flex !important;
            justify-content: space-between;
        }

        .info-left,
        .info-right {
            width: 48%;
        }

        ul {
            list-style-type: none;
            padding: 0;
            margin: 0;
        }

        li {
            display: flex;
            justify-content: space-between;
        }

        .label {
            width: 50%;
            font-weight: bold;
        }

        .value {
            width: 50%;
            text-align: left;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border: none;
            padding: 2px;
            text-align: left;
            font-size: 12px !important;
            vertical-align: top;
        }

        th {
            background-color: #f2f2f2;
        }
    </style>
</head>

<body>
    <!-- Header -->
    <div class="header">
        <img src="{{ public_path('png/header-inv.png') }}" style="width: 100%;" alt="Header Invoice">
    </div>

    <div class="container">
        <p class="invoice-desc"><strong>INVOICE</strong></p>

        <!-- Informasi Invoice -->
        <div class="invoice-info">
            <h3>PELANGGAN</h3>
            <table width="100%" style="border-collapse: collapse;">
                <tr>
                    <td style="text-align: left;">Nama</td>
                    <td style="text-align: left;">: {{ $invoice->name }}</td>

                    <td style="text-align: left;">No. Invoice</td>
                    <td style="text-align: left;">: {{ $invoice->inv_number }}</td>
                </tr>
                <tr>
                    <td>Alamat</td>
                    <td>: {{ $invoice->address }}</td>

                    <td>No. Pelanggan</td>
                    <td>: {{ $invoice->nomor_pelanggan }}</td>
                </tr>
                <tr>
                    <td>Email</td>
                    <td>: {{ $invoice->email }}</td>

                    <td>Tanggal Invoice</td>
                    <td>: {{ $invoice->invoice_date }}</td>
                </tr>
                <tr>
                    <td>NIK</td>
                    <td>: {{ $invoice->nik }}</td>

                    <td>Jatuh Tempo</td>
                    <td>: {{ $invoice->due_date }}</td>
                </tr>
                <tr>
                    <td>No. Telepon</td>
                    <td>: {{ $invoice->phone_number }}</td>

                    <td>Sistem Bayar</td>
                    <td>: <strong>PRABAYAR</strong></td>
                </tr>
            </table>
        </div>

        <table width="100%" style="border-collapse: collapse; border: 1px solid black; margin-top:15px">
            <tr style="background-color: #dbe5f1; text-align: center;">
                <th style="border: 1px solid black; padding: 5px;">No.</th>
                <th style="border: 1px solid black; padding: 5px;">Jenis Tagihan</th>
                <th style="border: 1px solid black; padding: 5px;">Deskripsi</th>
                <th style="border: 1px solid black; padding: 5px;">Jumlah</th>
            </tr>
            <tr>
                <td style="border: 1px solid black; padding: 5px; text-align: center;">1.</td>
                <td style="border: 1px solid black; padding: 5px;">Phoenix Diamond: Dedicated –
                    <strong>{{ $invoice->capacity }} Mbps</strong>
                </td>
                <td style="border: 1px solid black; padding: 5px; text-align: center;">
                    <strong>{{ $moon }}</strong>
                </td>
                <td style="border: 1px solid black; padding: 5px;">{{ $priceBandwith }}</td>
            </tr>
            <tr>
                <td style="border: 1px solid black; padding: 5px; text-align: center;">2.</td>
                <td style="border: 1px solid black; padding: 5px;"><em>OTC (One Time Charge)</em></td>
                <td style="border: 1px solid black; padding: 5px;"></td>
                <td style="border: 1px solid black; padding: 5px;">Rp. 0,00</td>
            </tr>
            <tr>
                <td style="border: 1px solid black; padding: 5px; text-align: center;">3.</td>
                <td style="border: 1px solid black; padding: 5px;">PPN (11%)</td>
                <td style="border: 1px solid black; padding: 5px;"></td>
                <td style="border: 1px solid black; padding: 5px;">Rp.</td>
            </tr>
            <tr>
                <td style="border: 1px solid black; padding: 5px; text-align: center;">4.</td>
                <td style="border: 1px solid black; padding: 5px;">BHP, USO, KSO (5%)</td>
                <td style="border: 1px solid black; padding: 5px;"></td>
                <td style="border: 1px solid black; padding: 5px;">Rp.</td>
            </tr>
            <tr>
                <td colspan="4"
                    style="border: 1px solid black; text-align: center; font-weight: bold; padding: 5px;">SUBTOTAL</td>
            </tr>
            <tr>
                <td colspan="2" style="border: 1px solid black; padding: 5px; color: red;">Tagihan Bulan Desember
                    2024</td>
                <td style="border: 1px solid black; padding: 5px;"></td>
                <td style="border: 1px solid black; padding: 5px;">Rp.</td>
            </tr>
            <tr>
                <td colspan="2" style="border: 1px solid black; padding: 5px; color: red;">Pembayaran Bulan Desember
                    2024 (20/12/2024)</td>
                <td style="border: 1px solid black; padding: 5px;"></td>
                <td style="border: 1px solid black; padding: 5px;">(Rp. __ )</td>
            </tr>
            <tr>
                <td style="border: 1px solid black; padding: 5px; text-align: center;">5.</td>
                <td style="border: 1px solid black; padding: 5px;">Kurang (Lebih) Bayar Bulan Desember 2024</td>
                <td style="border: 1px solid black; padding: 5px;"></td>
                <td style="border: 1px solid black; padding: 5px;">Rp.</td>
            </tr>
            <tr>
                <td style="border: 1px solid black; padding: 5px; text-align: center;">6.</td>
                <td style="border: 1px solid black; padding: 5px;">BPJS Ketenagakerjaan</td>
                <td style="border: 1px solid black; padding: 5px; text-align: center;">
                    <strong>{{ $moon }}</strong>
                </td>
                <td style="border: 1px solid black; padding: 5px;">Rp.</td>
            </tr>
            <tr style="background-color: #dbe5f1;">
                <td colspan="3"
                    style="border: 1px solid black; text-align: center; font-weight: bold; padding: 5px;">TOTAL TAGIHAN
                </td>
                <td style="border: 1px solid black; padding: 5px; font-weight: bold;">Rp.</td>
            </tr>
        </table>

        <table style="width: 100%; margin-top: 15px;">
            <tr>
                <td style="width: 60%;"></td> <!-- Kosong untuk isi tetap di tengah -->
                <td style="width: 40%; text-align: center; vertical-align: middle;">
                    <p style="margin: 0; padding: 0;"><strong>PT. ANUGERAH MEDIA DATA NUSANTARA</strong></p>
                    <img src="{{ public_path('png/ttd.png') }}" alt="TTD"
                        style="width: 150px; height: auto; display: block; margin: 2px auto;">
                    <p style="margin: 0; padding: 0;"><strong>STAFF FINANCE</strong></p>
                </td>
            </tr>
        </table>

        <div style="margin-top: 18px; font-size: 12px; z-index:1">
            <p style="margin: 0; padding: 0;"><strong>Pembayaran Otomatis:</strong></p>
            <p style="margin: 0; padding: 0;">{{ $invoice->payment_url }}</p>
            <p style="margin: 0; padding: 0;"><strong>Pembayaran Melalui Transfer Bank:</strong></p>
            <p style="margin: 0; padding: 0;">BCA KCP MALANG</p>
            <p style="margin: 0; padding: 0;"><strong>385-088-8204</strong></p>
            <p style="margin: 0; padding: 0;"><strong>PT. ANUGERAH MEDIA DATA NUSANTARA</strong></p>
            <p style="margin: 0; padding: 0;">Setelah melakukan pembayaran, mohon segera melakukan konfirmasi dengan
                menyantumkan nomor invoice melalui
                nomor berikut ini:</p>
            <p style="margin: 0; padding: 0;"><strong>0858-1670-0731 (Fifi – Staff Finance)</strong></p>
        </div>



    </div>

    <!-- Footer -->
    <div class="footer">
        <img src="{{ public_path('png/footer-inv.png') }}" style="width: 100%;" alt="Footer Invoice">
    </div>
</body>

</html>
