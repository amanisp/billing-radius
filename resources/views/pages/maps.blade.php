@extends('layouts.admin')
@section('content')
    <div id="main">
        {{-- Navbar --}}
        @include('includes.v_navbar')

        <section class="row">
            <div class="card">
                <div class="card-body">
                    <label for="typeFilter" class="form-label">Filter ODP/ODC:</label>
                    <select id="typeFilter" class="form-select w-25 mb-3" onchange="filterMarkers()">
                        <option value="">Semua</option>
                        <option value="ODP">ODP</option>
                        <option value="ODC">ODC</option>
                        <option value="POP">POP</option>
                    </select>

                    <div id="map" style="height: 500px; width: 100%;"></div>
                </div>
            </div>

        </section>

        @push('script-page')
            <script async src="https://maps.googleapis.com/maps/api/js?key=AIzaSyDDgUOvz-und-2YTKj5gVoQIarrUX7f6oM&callback=initMap"
                defer></script>

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
                    console.log(locations)

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
                            content: `<strong>${point.name}</strong><br>Perangkat: ${point.device_name}<br>Kapasitas: ${point.capacity}<br>Jenis: ${point.type}`
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
    </div>
@endsection
