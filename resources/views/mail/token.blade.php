<!DOCTYPE html>
<html lang="en" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <title>Reset Kata Sandi Anda</title>
    <!--[if mso]>
    <xml>
        <o:OfficeDocumentSettings>
            <o:AllowPNG/>
            <o:PixelsPerInch>96</o:PixelsPerInch>
        </o:OfficeDocumentSettings>
    </xml>
    <![endif]-->
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap');

        body {
            margin: 0;
            padding: 0;
            width: 100%;
            background-color: #f8fafc;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        table {
            border-collapse: collapse;
            border-spacing: 0;
        }

        img {
            border: 0;
            line-height: 100%;
            vertical-align: middle;
        }

        .container {
            width: 100%;
            max-width: 600px;
            margin: 0 auto;
        }

        .email-wrapper {
            padding: 40px 20px;
        }

        .content-card {
            background-color: #ffffff;
            border-radius: 12px;
            padding: 48px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }

        .logo {
            margin-bottom: 32px;
            text-align: center;
        }

        .heading {
            color: #1e293b;
            font-size: 24px;
            font-weight: 700;
            line-height: 1.3;
            margin: 0 0 16px;
            text-align: left;
        }

        .text {
            color: #475569;
            font-size: 16px;
            line-height: 1.6;
            margin: 0 0 24px;
            text-align: left;
        }

        .email-info {
            background-color: #f1f5f9;
            padding: 12px 16px;
            border-radius: 8px;
            font-weight: 600;
            color: #334155;
            display: inline-block;
            margin-bottom: 24px;
        }

        .btn-wrapper {
            text-align: left;
            margin-top: 8px;
            margin-bottom: 32px;
        }

        .button {
            background-color: #0034F2;
            border-radius: 8px;
            color: #ffffff !important;
            display: inline-block;
            font-size: 16px;
            font-weight: 600;
            padding: 16px 32px;
            text-decoration: none;
            transition: background-color 0.3s;
        }

        .footer {
            margin-top: 32px;
            text-align: center;
            color: #94a3b8;
            font-size: 14px;
        }

        .divider {
            height: 1px;
            background-color: #e2e8f0;
            margin: 32px 0;
        }

        @media (max-width: 600px) {
            .content-card {
                padding: 32px 24px;
            }
        }
    </style>
</head>

<body>
    <div class="container email-wrapper">
        <!-- Logo Section -->
        <div class="logo">
            <img src="https://06fdf7bd-ff1a-4d18-9d91-15db89578ef6.b-cdn.net/e/9470a655-dd5b-4c65-899d-0ec67c0b5db2/e0854f40-9235-4976-8b6a-902cae520a03.png"
                alt="AMAN ISP" width="48" style="margin-bottom: 12px;">
            <div style="font-weight: 700; font-size: 18px; color: #1e293b; letter-spacing: -0.5px;">AMAN ISP</div>
        </div>

        <div class="content-card">
            <h1 class="heading">Permintaan Reset Password</h1>

            <p class="text">
                Halo,<br><br>
                Kami menerima permintaan untuk mengatur ulang kata sandi akun Anda yang terdaftar dengan email:
            </p>

            <div class="email-info">
                {{ $email }}
            </div>

            <p class="text">
                Silakan klik tombol di bawah ini untuk membuat kata sandi baru. Link ini hanya berlaku selama <strong>5
                    menit</strong> dan hanya dapat digunakan satu kali.
            </p>

            <div class="btn-wrapper">
                <!--[if mso]>
                <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="{{ $link }}" style="height:52px;v-text-anchor:middle;width:200px;" arcsize="16%" stroke="f" fillcolor="#0034F2">
                    <w:anchorlock/>
                    <center style="color:#ffffff;font-family:sans-serif;font-size:16px;font-weight:bold;">Reset Password</center>
                </v:roundrect>
                <![endif]-->
                <a href="{{ $link }}" class="button">Reset Password</a>
            </div>

            <p class="text" style="font-size: 14px; margin-bottom: 0;">
                Jika Anda tidak merasa melakukan permintaan ini, abaikan saja email ini. Akun Anda tetap aman.
            </p>

            <div class="divider"></div>

            <p class="text" style="font-size: 13px; color: #94a3b8; margin-bottom: 0;">
                Jika tombol di atas tidak berfungsi, salin dan tempel URL berikut ke browser Anda:<br>
                <span style="color: #0034F2; word-break: break-all;">{{ $link }}</span>
            </p>
        </div>

        <div class="footer">
            <p style="margin-bottom: 4px; font-weight: 600; color: #64748b;">AMAN ISP</p>
            <p style="margin: 0;">PT Anugerah Media Data Nusantara</p>
            <p style="margin-top: 16px; font-size: 12px;">&copy; 2024 AMAN ISP. All rights reserved.</p>
        </div>
    </div>
</body>

</html>
