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
        {{-- Modal Assign Technician --}}
        <x-modal-form id="assignTechnicianModal" title="Assign Teknisi ke Area"
            action="{{ route('area.assignTechnician') }}">
            <input type="hidden" name="area_id" id="assign_area_id">

            <div class="col-12 mb-3">
                <label class="form-label">Pilih Teknisi</label>
                <div class="border rounded p-3" style="max-height: 300px; overflow-y: auto;">
                    @forelse($technicians as $tech)
                        <div class="form-check mb-2">
                            <input class="form-check-input technician-checkbox" type="checkbox" name="technician_ids[]"
                                value="{{ $tech->id }}" id="tech_{{ $tech->id }}">
                            <label class="form-check-label" for="tech_{{ $tech->id }}">
                                {{ $tech->name }}
                                <small class="text-muted">({{ $tech->username }})</small>
                            </label>
                        </div>
                    @empty
                        <p class="text-muted">Tidak ada teknisi tersedia</p>
                    @endforelse
                </div>
                @error('technician_ids')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
            </div>
        </x-modal-form>

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
                        <table class="table" id="areaTable">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Nama</th>
                                    <th>Kode Area</th>
                                    @if ($user->role === 'mitra')
                                        <th>Teknisi di Area</th>
                                    @endif
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

                                        @if ($user->role === 'mitra')
                                            <td>
                                                @if ($area->assignedTechnicians->count() > 0)
                                                    @foreach ($area->assignedTechnicians as $tech)
                                                        <span class="badge bg-primary me-1 mb-1">{{ $tech->name }}</span>
                                                    @endforeach
                                                @else
                                                    <span class="text-muted small">Belum ada teknisi</span>
                                                @endif
                                            </td>
                                        @endif

                                        <td>{{ $area->opticals()->count() }}</td>
                                        <td>
                                            {{ Auth::user()->role === 'mitra' ? $area->connection()->count() : $area->mitras()->count() }}
                                        </td>
                                        <td>
                                            @if ($user->role === 'mitra')
                                                <button class="btn btn-outline-primary btn-sm btn-assign"
                                                    data-id="{{ $area->id }}" data-name="{{ $area->name }}"
                                                    data-technicians="{{ $area->assignedTechnicians->pluck('id')->toJson() }}">
                                                    <i class="fa-solid fa-user-plus"></i>
                                                </button>
                                            @endif
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
                        ],
                        columnDefs: [{
                                targets: [0],
                                orderable: false
                            },
                            {
                                targets: [-1],
                                orderable: false,
                                searchable: false
                            }
                        ],
                        drawCallback: function() {
                            initializeButtons();
                        }
                    });

                    initializeButtons();

                    function initializeButtons() {
                        initializeDeleteButtons();
                        initializeAssignButtons();
                    }

                    // Handle Assign Technician
                    function initializeAssignButtons() {
                        const assignButtons = document.querySelectorAll(".btn-assign");
                        assignButtons.forEach(button => {
                            button.removeEventListener("click", handleAssignClick);
                            button.addEventListener("click", handleAssignClick);
                        });
                    }

                    function handleAssignClick() {
                        const areaId = this.getAttribute("data-id");
                        const areaName = this.getAttribute("data-name");
                        const assignedTechs = JSON.parse(this.getAttribute("data-technicians") || "[]");

                        // Set area ID
                        document.getElementById('assign_area_id').value = areaId;

                        // Update modal title
                        document.querySelector('#assignTechnicianModal .modal-title').textContent =
                            `Assign Teknisi ke Area: ${areaName}`;

                        // Check assigned technicians
                        document.querySelectorAll('.technician-checkbox').forEach(checkbox => {
                            checkbox.checked = assignedTechs.includes(parseInt(checkbox.value));
                        });

                        // Show modal
                        const modal = new bootstrap.Modal(document.getElementById('assignTechnicianModal'));
                        modal.show();
                    }

                    // Handle Delete
                    function initializeDeleteButtons() {
                        const deleteButtons = document.querySelectorAll(".btn-delete");
                        deleteButtons.forEach(button => {
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
                                const form = document.createElement("form");
                                form.method = "POST";
                                form.action = `{{ route('area.destroy', '') }}/${areaId}`;

                                const csrfInput = document.createElement("input");
                                csrfInput.type = "hidden";
                                csrfInput.name = "_token";
                                csrfInput.value = document.querySelector('meta[name="csrf-token"]').getAttribute(
                                    'content');

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
