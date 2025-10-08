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
            <button class="btn btn-outline-primary btn-sm px-4 mt-2 mb-4" data-bs-toggle="modal"
                data-bs-target="#formCreateModal"><i class="fa-solid fa-plus"></i>
                Create
            </button>
        </div>

        <x-modal-form id="formCreateModal" title="Tambah Data Area" action="{{ route('area.store') }}">
            <div class="col-12 mb-3">
                <div class="row">
                    <div class="col-lg-6">
                        <div class="form-group">
                            <label for="area-name">Nama Area</label>
                            <input name="name" type="text" class="form-control @error('name')is-invalid @enderror"
                                value="{{ old('name') }}" placeholder="Lowokwaru" id="area-name">
                            @error('name')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="form-group">
                            <label for="area-name">Kode Area</label>
                            <input name="area_code" type="number"
                                class="form-control @error('area_code')is-invalid @enderror" value="{{ old('area_code') }}"
                                id="area_code">
                            @error('area_code')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>
        </x-modal-form>

        <section class="row">
            <div class="card">
                <div class="card-header">
                    <button class="btn btn-outline-primary btn-sm px-5 py-2" data-bs-toggle="modal"
                        data-bs-target="#formCreateModal"><i class="fa-solid fa-map-location"></i>
                        New Area
                    </button>
                    <hr>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped" id="areaTable">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Nama</th>
                                    <th>Kode Area</th>
                                    <th>{{ $user->role === 'mitra' ? 'Total ODP/ODC' : 'Total POP' }}</th>
                                    <th>{{ $user->role === 'mitra' ? 'Total Customer' : 'Total Mitra' }}</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($data as $index => $area)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>{{ $area->name }}</td>
                                        <td>{{ $area->area_code }}</td>
                                        <td>{{ $area->opticals()->count() }}</td>
                                        <td>{{ Auth::user()->role === 'mitra' ? $area->connection()->count() : $area->mitras()->count() }}
                                        </td>
                                        <td>
                                            <button class="btn btn-outline-danger btn-sm btn-delete"
                                                data-id="{{ $area->id }}" data-name="{{ $area->name }}">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        @push('script-page')
            <script>
                document.addEventListener("DOMContentLoaded", function() {
                    // Initialize DataTable
                    $('#areaTable').DataTable({
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
                            initializeDeleteButtons();
                        }
                    });

                    // Initial delete button setup
                    initializeDeleteButtons();

                    function initializeDeleteButtons() {
                        const deleteButtons = document.querySelectorAll(".btn-delete");

                        deleteButtons.forEach(button => {
                            // Remove existing listeners to prevent duplicates
                            button.removeEventListener("click", handleDeleteClick);
                            button.addEventListener("click", handleDeleteClick);
                        });
                    }

                    function handleDeleteClick() {
                        const areaId = this.getAttribute("data-id");
                        const areaName = this.getAttribute("data-name");

                        Swal.fire({
                            title: "Konfirmasi",
                            text: `Apakah Anda yakin ingin menghapus area ${areaName}?`,
                            icon: "warning",
                            showCancelButton: true,
                            confirmButtonColor: "#d33",
                            cancelButtonColor: "#3085d6",
                            confirmButtonText: "Ya, hapus!",
                            cancelButtonText: "Batal",
                            reverseButtons: true
                        }).then((result) => {
                            if (result.isConfirmed) {
                                // Create and submit form
                                const form = document.createElement("form");
                                form.method = "POST";
                                form.action = `{{ route('area.destroy', '') }}/${areaId}`;

                                // Add CSRF token
                                const csrfInput = document.createElement("input");
                                csrfInput.type = "hidden";
                                csrfInput.name = "_token";
                                csrfInput.value = document.querySelector('meta[name="csrf-token"]').getAttribute(
                                    'content');

                                // Add DELETE method
                                const methodInput = document.createElement("input");
                                methodInput.type = "hidden";
                                methodInput.name = "_method";
                                methodInput.value = "DELETE";

                                form.appendChild(csrfInput);
                                form.appendChild(methodInput);
                                document.body.appendChild(form);

                                form.submit();
                            }
                        });
                    }
                });
            </script>
        @endpush
    @endsection
