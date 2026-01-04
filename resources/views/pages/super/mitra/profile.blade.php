@extends('layouts.admin')
@section('content')
    <div class="page-heading">
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
                    <h3>Data Mitra {{ $data->name }}</h3>
                </div>
                <div class="col-12 col-md-6 order-md-2 order-first">
                    <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard.index') }}">Dashboard</a></li>
                            <li class="breadcrumb-item" aria-current="page"><a href="{{ route('mitra.index') }}">Mitra</a>
                            </li>
                            <li class="breadcrumb-item active" aria-current="page">Profile</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>

        <section class="section mt-3">
            <div class="row">

                <div class="col-6 col-lg-3 col-md-6">
                    <div class="card">
                        <div class="card-body px-4 py-4-5">
                            <div class="row">
                                <div class="col-md-4 col-lg-12 col-xl-12 col-xxl-5 d-flex justify-content-start ">
                                    <div class="stats-icon purple mb-2">
                                        <i class="text-white fa-solid fa-map-location-dot"></i>
                                    </div>
                                </div>
                                <div class="col-md-8 col-lg-12 col-xl-12 col-xxl-7">
                                    <h6 class="text-muted font-semibold">Coverage Area</h6>
                                    <h6 class="font-extrabold mb-0"></h6>
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
                                    <div class="stats-icon blue mb-2">
                                        <i class="text-white fa-solid fa-network-wired"></i>
                                    </div>
                                </div>
                                <div class="col-md-8 col-lg-12 col-xl-12 col-xxl-7">
                                    <h6 class="text-muted font-semibold">POP</h6>
                                    <h6 class="font-extrabold mb-0"></h6>
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
                                    <div class="stats-icon blue mb-2">
                                        <i class="text-white fa-solid fa-network-wired"></i>
                                    </div>
                                </div>
                                <div class="col-md-8 col-lg-12 col-xl-12 col-xxl-7">
                                    <h6 class="text-muted font-semibold">POP</h6>
                                    <h6 class="font-extrabold mb-0"></h6>
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
                                    <div class="stats-icon blue mb-2">
                                        <i class="text-white fa-solid fa-network-wired"></i>
                                    </div>
                                </div>
                                <div class="col-md-8 col-lg-12 col-xl-12 col-xxl-7">
                                    <h6 class="text-muted font-semibold">POP</h6>
                                    <h6 class="font-extrabold mb-0"></h6>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table" id="dataTables">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Nama</th>
                                    <th>Nomor HP</th>
                                    <th>Email</th>
                                    <th>Area</th>
                                    <th>POP</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
            <h6>Titik Koordinat ODP/ODC</h6>
            <div class="card">
                <div class="card-body">
                    <div id="map" style="height: 600px; width: 100%;"></div>
                </div>
            </div>
        </section>
    </div>

    @push('script-page')
        <script>
            let map;
            let markers = [];

            function initMap() {
                map = new google.maps.Map(document.getElementById("map"), {
                    center: {
                        lat: -8.087128,
                        lng: 111.930738
                    },
                    zoom: 12,
                });

                let locations = @json($optical); // Ambil data dari database

                locations.forEach(point => {
                    let icon = getIcon(point); // Panggil fungsi untuk menentukan icon

                    let marker = new google.maps.Marker({
                        position: {
                            lat: parseFloat(point.lat),
                            lng: parseFloat(point.lng)
                        },
                        map: map,
                        icon: icon,
                        title: point.name
                    });

                    let infoWindow = new google.maps.InfoWindow({
                        content: `<strong>${point.name}</strong><br>Perangkat: ${point.device_name}<br>Kapasitas: ${point.used}/${point.capacity}<br>Jenis: ${point.type}`
                    });

                    marker.addListener("click", () => {
                        infoWindow.open(map, marker);
                    });

                    marker.type = point.type;
                    markers.push(marker);
                });
            }

            function getIcon(point) {
                if (point.type === 'ODC') {
                    return '{{ asset('png/maps-odc.svg') }}'; // Gunakan icon ODC default
                }

                let usage = (point.used / point.capacity) * 100; // Hitung persentase kapasitas terpakai

                if (usage < 50) {
                    return '{{ asset('png/maps-odp.svg') }}'; // Hijau jika di bawah 50%
                } else if (usage >= 50 && usage <= 80) {
                    return '{{ asset('png/maps-odp.svg') }}'; // Kuning jika 50% - 80%
                } else {
                    return '{{ asset('png/maps-odp.svg') }}'; // Merah jika di atas 80%
                }
            }

            function filterMarkers() {
                let selectedType = document.getElementById("typeFilter").value;

                markers.forEach(marker => {
                    if (selectedType === "" || marker.type === selectedType) {
                        marker.setMap(map);
                    } else {
                        marker.setMap(null);
                    }
                });
            }

            window.onload = initMap;
        </script>
    @endpush
@endsection
