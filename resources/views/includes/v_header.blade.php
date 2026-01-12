<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Laravel') }}</title>
    <link rel="icon" href="{{ asset('images/logo.png') }}">
    <link rel="shortcut icon" href="{{ asset('images/logo.png') }}">

    <style>
        #connectionStatus.whatsapp-wait,
        #phoneNumber.whatsapp-wait,
        #statusAction.whatsapp-wait {
            visibility: hidden;
        }

        .sidebar-wrapper .sidebar-header img {
            height: 2rem !important;
        }

        /* Custom Sidebar */
        .sidebar-wrapper .menu .sidebar-item.active>.sidebar-link {
            background-color: #20c997 !important;
        }

        .form-check-input:checked {
            background-color: #0d6efd !important;
            border-color: #0d6efd !important;
        }

        /* PPPoE Index*/
        .status-card {
            /* background: #1a2742; */
            border-radius: 12px;
            padding: 20px;
            color: #fff;
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .status-card::after {
            content: "";
            position: absolute;
            right: 0;
            bottom: 0;
            width: 120px;
            height: 120px;
            border-radius: 100%;
            transform: translate(40%, 40%);
        }

        .row>.col-md:nth-of-type(1) .status-card::after {
            background: rgba(33, 255, 110, 0.215);
            /* hijau */
        }

        /* card kedua */
        .row>.col-md:nth-of-type(2) .status-card::after {
            background: rgba(243, 33, 33, 0.215);
            /* biru */
        }

        /* card ketiga */
        .row>.col-md:nth-of-type(3) .status-card::after {
            background: rgba(255, 193, 7, 0.215);
            /* kuning */
        }

        .row>.col-md:nth-of-type(4) .status-card::after {
            background: rgba(73, 7, 255, 0.215);
            /* kuning */
        }

        .status-icon {
            font-size: 28px;
        }

        .status-number {
            font-weight: bold;
            margin: 0;
            color: #25396f
        }

        html[data-bs-theme="dark"] .status-number,
        html[data-bs-theme="dark"] .status-text {
            color: #fff
        }

        .status-text {
            font-size: 14px;
            opacity: 0.8;
            margin: 0;
            color: #25396f
        }

        /* warna per status */
        .total .status-icon {
            color: #0d6efd;
        }

        .active .status-icon {
            color: #20c997;
        }

        .suspend .status-icon {
            color: #f1c40f;
        }

        /* Maps */
        #map,
        #edit-maps {
            height: 400px;
            width: 100%;
        }
    </style>
    <link rel="stylesheet" href="{{ asset('assets/compiled/css/app.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/compiled/css/app-dark.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/compiled/css/iconly.css') }}" />
    <link rel="stylesheet" href="{{ asset('extensions/datatables.net-bs5/css/dataTables.bootstrap5.min.css') }}">
    <!-- Add DataTables Responsive CSS -->
    <link rel="stylesheet"
        href="{{ asset('extensions/datatables.net-responsive-bs5/css/responsive.bootstrap5.min.css') }}">
    <script src="https://kit.fontawesome.com/6a37edf554.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/clipboard.js/2.0.11/clipboard.min.js"></script>

    <script src="{{ asset('extensions/jquery/jquery.min.js') }}"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/css/select2.min.css" />
    <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />

    <script src="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.full.min.js"></script>

</head>

<body>
