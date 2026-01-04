@extends('layouts.admin')
@section('content')
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
        }

        .card-custom {
            border-radius: 1rem;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.2);
        }
    </style>
    <div id="main">
        {{-- Navbar --}}
        @include('includes.v_navbar')


        <div class="page-content">
            <section class="row">
                <div class="col-12">

                    <div class="row">
                        <div class="col-md">
                            <div class="card">
                                <div class="status-card total">
                                    <div class="stats-icon green mb-1 rounded-circle">
                                        <i class="text-white fa-solid fa-money-bill-trend-up"></i>
                                    </div>
                                    <div class="d-flex flex-column">
                                        <small class="status-number">Monthly Earning</small>
                                        <p class="fs-4 status-number">Rp {{ number_format($paidTotalNow, 0, ',', '.') }}
                                        </p>
                                        <small class="text-muted">Rp
                                            {{ number_format($paidTotalLast, 0, ',', '.') }}
                                            Last Month</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md">
                            <div class="card">
                                <div class="status-card active">
                                    <div class="stats-icon bg-danger mb-1 rounded-circle">
                                        <i class="text-white fa-solid fa-cash-register"></i>
                                    </div>
                                    <div class="d-flex flex-column">
                                        <small class="status-number">Invoice Overdue</small>
                                        <p class="fs-4 status-number">
                                            {{ $invOverCount }}</p>
                                        <small class="text-muted">Rp {{ number_format($invOverTotal, 0, ',', '.') }}</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md">
                            <div class="card">
                                <div class="status-card suspend">
                                    <div class="stats-icon bg-warning mb-1 rounded-circle">
                                        <i class="text-white fa-solid fa-map-location-dot"></i>
                                    </div>
                                    <div class="d-flex flex-column">
                                        <small class="status-number">Total Unpaid</small>
                                        <p class="fs-4 status-number">{{ $unpaidCount }}</p>
                                        <small class="text-muted">Rp
                                            {{ number_format($unpaidTotal, 0, ',', '.') }}</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-6 col-lg-3 col-md-6">
                            <div class="card">
                                <div class="card-body px-4 py-4-5">
                                    <div class="row">
                                        <div class="col-md-4 col-lg-12 col-xl-12 col-xxl-5 d-flex justify-content-start ">
                                            <div class="stats-icon purple mb-2 rounded-circle">
                                                <i class="text-white fa-solid fa-map-location-dot"></i>
                                            </div>
                                        </div>
                                        <div class="col-md-8 col-lg-12 col-xl-12 col-xxl-7">
                                            <h6 class="text-muted font-semibold">Area</h6>
                                            <h6 class="font-extrabold mb-0">{{ $areaCount }}</h6>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 col-lg-3 col-md-6">
                            <div class="card">
                                <div class="card-body px-4 py-4-5">
                                    <div class="row">
                                        <div class="col-md-4 col-lg-12 col-xl-12 col-xxl-5 d-flex justify-content-start ">
                                            <div class="stats-icon rounded-circle blue mb-2">
                                                <i class="text-white fa-solid fa-network-wired"></i>
                                            </div>
                                        </div>
                                        <div class="col-md-8 col-lg-12 col-xl-12 col-xxl-7">
                                            <h6 class="text-muted font-semibold">ODP/ODC</h6>
                                            <h6 class="font-extrabold mb-0">{{ $opticalCount }}</h6>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 col-lg-3 col-md-6">
                            <div class="card">
                                <div class="card-body px-4 py-4-5">
                                    <div class="row">
                                        <div class="col-md-4 col-lg-12 col-xl-12 col-xxl-5 d-flex justify-content-start ">
                                            <div class="stats-icon rounded-circle green mb-2">
                                                <i class="text-white fa-solid fa-house-signal"></i>
                                            </div>
                                        </div>
                                        <div class="col-md-8 col-lg-12 col-xl-12 col-xxl-7">
                                            <h6 class="text-muted font-semibold">Homepass</h6>
                                            <h6 class="font-extrabold mb-0">{{ $homepass }}</h6>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 col-lg-3 col-md-6">
                            <div class="card">
                                <div class="card-body px-4 py-4-5">
                                    <div class="row">
                                        <div class="col-md-4 col-lg-12 col-xl-12 col-xxl-5 d-flex justify-content-start ">
                                            <div class="stats-icon rounded-circle red mb-2">
                                                <i class="text-white fa-solid fa-link-slash"></i>
                                            </div>
                                        </div>
                                        <div class="col-md-8 col-lg-12 col-xl-12 col-xxl-7">
                                            <h6 class="text-muted font-semibold">Suspend</h6>
                                            <h6 class="font-extrabold mb-0">{{ $suspend }}</h6>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @if (auth()->user()->role === 'superadmin')
                            <div class="col-6 col-lg-3 col-md-6">
                                <div class="card">
                                    <div class="card-body px-4 py-4-5">
                                        <div class="row">
                                            <div
                                                class="col-md-4 col-lg-12 col-xl-12 col-xxl-5 d-flex justify-content-start ">
                                                <div class="stats-icon green mb-2">
                                                    <i class="text-white fa-solid fa-users"></i>
                                                </div>
                                            </div>
                                            <div class="col-md-8 col-lg-12 col-xl-12 col-xxl-7">
                                                <h6 class="text-muted font-semibold">Mitra Aktif</h6>
                                                <h6 class="font-extrabold mb-0">{{ $mitraCount }}</h6>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                    <div class="row">
                        {{-- <div class="col-12 col-lg-4">

                            <div class="card">
                                <div class="card-header">
                                    <h4>Session Online</h4>
                                </div>
                                <div class="card-body">
                                    <div id="session-chart"></div>
                                </div>
                            </div>
                        </div> --}}
                        <div class="col-12 col-lg-12">
                            <div class="card">
                                <div class="card-header">
                                    <h4>Histori Transaksi</h4>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover table-lg">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>Payment Method</th>
                                                    <th>Admin</th>
                                                    <th>Total</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($invoices as $invoice)
                                                    <tr>
                                                        <td>
                                                            <small>{{ $invoice->inv_number }}</small><br>
                                                            <small>{{ $invoice->paid_at ? $invoice->paid_at : '' }}</small>
                                                        </td>
                                                        <td>
                                                            <small>
                                                                @php
                                                                    $paymentMethods = [
                                                                        'payment_gateway' => 'Payment Gateway',
                                                                        'cash' => 'Tunai',
                                                                        'bank_transfer' => 'Transfer Bank',
                                                                    ];
                                                                @endphp
                                                                {{ $paymentMethods[$invoice->payment_method] ?? 'Tidak Diketahui' }}
                                                            </small>
                                                        </td>
                                                        <td><small>{{ $invoice->payer->name ?? 'System' }}</small>
                                                        </td>
                                                        <td><small>Rp
                                                                {{ number_format($invoice->amount, 0, ',', '.') }}</small>
                                                        </td>
                                                        <td>
                                                            @if ($invoice->status == 'paid')
                                                                <span class="badge bg-success">Lunas</span>
                                                            @elseif ($invoice->status == 'unpaid')
                                                                <span class="badge bg-danger">Belum Bayar</span>
                                                            @else
                                                                <span class="badge bg-warning">Pending</span>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </section>
        </div>
    @endsection

    {{-- @push('script-page')
        <script>
            optionSession = {
                series: [<?= $session['online'] ?>, <?= $session['offline'] ?>],
                labels: ["Online", "Offline"],
                colors: ["#5ddab4", "#ff7976"],
                chart: {
                    type: "donut",
                    width: "100%",
                    height: "350px",
                },
                legend: {
                    position: "bottom",
                },
                plotOptions: {
                    pie: {
                        donut: {
                            size: "30%",
                        },
                    },
                },
            }

            var chartSession = new ApexCharts(
                document.getElementById("session-chart"),
                optionSession
            )

            chartSession.render()
        </script>
    @endpush --}}
