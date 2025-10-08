@extends('layouts.admin')
@section('content')
    <div id="main">
        {{-- Navbar --}}
        @include('includes.v_navbar')

        @if (session('success'))
            <div class="alert alert-light-success color-danger"><i class="bi bi-exclamation-circle"></i>
                {{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-light-danger color-danger"><i class="bi bi-exclamation-circle"></i>
                {{ session('error') }}</div>
        @endif

        <div class="d-flex">
            <button class="btn btn-outline-primary btn-sm  px-4  mt-2 mb-4" data-bs-toggle="modal"
                data-bs-target="#formCreateModal"><i class="fa-solid fa-plus"></i>
                Create
            </button>
        </div>

        {{-- Modal Create --}}
        @if (Auth::user()->role === 'superadmin')
            @include('pages.optical.store-superadmin')
        @else
            @include('pages.optical.store-mitra')
        @endif

        {{-- Modal Edit --}}
        @if (Auth::user()->role === 'superadmin')
            @include('pages.optical.edit-superadmin')
        @else
            @include('pages.optical.edit-mitra')
        @endif

        <section class="row">
            <div class="card">
                <div class="card-header">
                    <button class="btn btn-outline-primary btn-sm px-5 py-2" data-bs-toggle="modal"
                        data-bs-target="#formCreateModal"><i class="fa-solid fa-network-wired"></i>
                        New ODP/ODC
                    </button>
                    <hr>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped" id="opticalTable">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Nama</th>
                                    <th>Type</th>
                                    <th>Capacity</th>
                                    <th>{{ Auth::user()->role === 'superadmin' ? 'Total Mitra' : 'Total Customer' }}</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($data as $index => $optical)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>{{ $optical->name }}</td>
                                        <td>
                                            <span class="badge {{ $optical->type == 'ODP' ? 'bg-primary' : 'bg-success' }}">
                                                {{ $optical->type }}
                                            </span>
                                        </td>
                                        <td>{{ $optical->capacity }}</td>
                                        @if (Auth::user()->role === 'superadmin')
                                            <td>0</td>
                                            {{-- <td>{{ $optical->mitras()->count() }}</td> --}}
                                        @else
                                            <td>{{ $optical->connection()->count() }}</td>
                                        @endif
                                        <td>
                                            <div class="btn-group gap-2">
                                                <button data-id="{{ $optical->id }}" data-name="{{ $optical->name }}"
                                                    data-capacity="{{ $optical->capacity }}"
                                                    data-area_id="{{ $optical->area_id }}" data-lat="{{ $optical->lat }}"
                                                    data-lng="{{ $optical->lng }}" data-type="{{ $optical->type }}"
                                                    data-devices="{{ $optical->device_name }}"
                                                    data-ip="{{ $optical->ip_public }}"
                                                    class="btn btn-outline-warning btn-sm edit-btn" data-bs-toggle="modal"
                                                    data-bs-target="#formEditModal">
                                                    <i class="fa-solid fa-pencil"></i>
                                                </button>
                                                <button class="btn btn-outline-danger btn-sm btn-delete"
                                                    data-id="{{ $optical->id }}" data-name="{{ $optical->name }}">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center">ODP/ODC Belum Tersedia</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        @push('script-page')
            <script async src="https://maps.googleapis.com/maps/api/js?key=AIzaSyDDgUOvz-und-2YTKj5gVoQIarrUX7f6oM&callback=initMap"
                defer></script>
            <script>
                document.addEventListener("DOMContentLoaded", function() {
                    // Initialize DataTable
                    $('#opticalTable').DataTable({
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
                                targets: [0], // # column
                                orderable: false
                            },
                            {
                                targets: [-1], // Action column
                                orderable: false,
                                searchable: false
                            }
                        ],
                        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
                            '<"row"<"col-sm-12"tr>>' +
                            '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
                        drawCallback: function() {
                            // Re-initialize event listeners after table redraw
                            initializeEventListeners();
                        }
                    });

                    // Initial event listeners
                    initializeEventListeners();

                    function initializeEventListeners() {
                        // Delete button event listeners
                        const deleteButtons = document.querySelectorAll(".btn-delete");
                        deleteButtons.forEach(button => {
                            button.removeEventListener("click", handleDeleteClick); // Remove existing listeners
                            button.addEventListener("click", handleDeleteClick);
                        });

                        // Edit button event listeners
                        const editButtons = document.querySelectorAll(".edit-btn");
                        editButtons.forEach(button => {
                            button.removeEventListener("click", handleEditClick); // Remove existing listeners
                            button.addEventListener("click", handleEditClick);
                        });
                    }

                    function handleDeleteClick() {
                        let name = this.getAttribute("data-name");
                        let profileId = this.getAttribute("data-id");

                        Swal.fire({
                            title: "Konfirmasi",
                            text: `Apakah Anda ingin menghapus ODP/ODC ${name}?`,
                            icon: "warning",
                            showCancelButton: true,
                            confirmButtonText: "Ya, hapus!",
                            cancelButtonText: "Batal",
                            reverseButtons: true
                        }).then((result) => {
                            if (result.isConfirmed) {
                                let form = document.createElement("form");
                                form.method = "POST";
                                form.action = `/master-data/optical/${profileId}`;

                                let csrf = document.createElement("input");
                                csrf.type = "hidden";
                                csrf.name = "_token";
                                csrf.value = document.querySelector('meta[name="csrf-token"]').content;

                                let method = document.createElement("input");
                                method.type = "hidden";
                                method.name = "_method";
                                method.value = "DELETE";

                                form.appendChild(csrf);
                                form.appendChild(method);
                                document.body.appendChild(form);

                                form.submit();
                            }
                        });
                    }

                    function handleEditClick() {
                        let areaData = @json($area->pluck('name', 'id'));

                        document.getElementById("edit-name").value = this.dataset.name;
                        document.getElementById("edit-capacity").value = this.dataset.capacity;
                        document.getElementById("edit-area").value = areaData[this.dataset.area_id];
                        document.getElementById("edit-lat").value = this.dataset.lat;
                        document.getElementById("edit-lng").value = this.dataset.lng;

                        let formAction = `/master-data/optical/${this.dataset.id}`;
                        document.querySelector("#formEditModal form").action = formAction;

                        let ipPublic = document.getElementById("ip-public");
                        let editDevice = document.getElementById("edit_device");
                        let editType = document.getElementById("edit-type");

                        if (this.dataset.hasOwnProperty('ip') && this.dataset.ip) {
                            // Jika Superadmin
                            ipPublic.value = this.dataset.ip;
                            editDevice.value = this.dataset.devices;
                            ipPublic.closest(".form-group").style.display = "block";
                            editDevice.closest(".form-group").style.display = "block";
                        } else {
                            // Jika Mitra
                            editType.value = this.dataset.type;
                            editType.closest(".form-group").style.display = "block";
                        }

                        // Pastikan lat & lng ada, jika tidak, gunakan default lokasi
                        let lat = parseFloat(this.dataset.lat) || -6.200000; // Default Jakarta
                        let lng = parseFloat(this.dataset.lng) || 106.816666;

                        loadMap({
                            lat,
                            lng
                        }, true);
                    }
                });

                let map, marker, editMap, editMarker;

                function initMap() {
                    if (navigator.geolocation) {
                        navigator.geolocation.getCurrentPosition(
                            position => loadMap({
                                lat: position.coords.latitude,
                                lng: position.coords.longitude
                            }),
                            () => loadMap({
                                lat: -8.087128,
                                lng: 111.930738
                            }) // Default location
                        );
                    } else {
                        loadMap({
                            lat: -8.087128,
                            lng: 111.930738
                        });
                    }
                }

                function loadMap(location, isEdit = false) {
                    let mapElement = isEdit ? "edit-maps" : "map";
                    let latInput = isEdit ? "edit-lat" : "lat";
                    let lngInput = isEdit ? "edit-lng" : "lng";
                    let selectedMap = isEdit ? editMap : map;
                    let selectedMarker = isEdit ? editMarker : marker;

                    selectedMap = new google.maps.Map(document.getElementById(mapElement), {
                        center: location,
                        zoom: 15
                    });

                    selectedMarker = new google.maps.Marker({
                        position: location,
                        map: selectedMap,
                        draggable: true
                    });

                    document.getElementById(latInput).value = location.lat;
                    document.getElementById(lngInput).value = location.lng;

                    google.maps.event.addListener(selectedMarker, "dragend", event => {
                        document.getElementById(latInput).value = event.latLng.lat();
                        document.getElementById(lngInput).value = event.latLng.lng();
                    });

                    google.maps.event.addListener(selectedMap, "click", event => {
                        selectedMarker.setPosition(event.latLng);
                        document.getElementById(latInput).value = event.latLng.lat();
                        document.getElementById(lngInput).value = event.latLng.lng();
                    });

                    if (isEdit) {
                        editMap = selectedMap;
                        editMarker = selectedMarker;
                    } else {
                        map = selectedMap;
                        marker = selectedMarker;
                    }
                }
            </script>
        @endpush
    @endsection
