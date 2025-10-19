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

        {{-- Modal Create --}}
        <x-modal-form id="formCreateModal" title="Tambah Data Admin" action="{{ route('admin.store') }}">
            <div class="col-12 mb-3">
                <div class="row">
                    <div class="col-lg-6">
                        <div class="form-group">
                            <label for="name">Nama</label>
                            <div class="position-relative">
                                <input required name="name" type="text"
                                    class="form-control @error('name')is-invalid @enderror" value="{{ old('name') }}"
                                    id="name">
                                @error('name')
                                    <div class="invalid-feedback">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="form-group">
                            <label for="username">Username</label>
                            <div class="position-relative">
                                <input name="username" type="text"
                                    class="form-control @error('username')is-invalid @enderror"
                                    value="{{ old('username') }}" id="username">
                                @error('username')
                                    <div class="invalid-feedback">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-lg-6">
                        <div class="form-group">
                            <label for="email">Email</label>
                            <div class="position-relative">
                                <input required name="email" type="email"
                                    class="form-control @error('email')is-invalid @enderror" value="{{ old('email') }}"
                                    id="email">
                                @error('email')
                                    <div class="invalid-feedback">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="form-group">
                            <label for="phone_number">Nomor HP</label>
                            <div class="position-relative">
                                <input name="phone_number" type="number"
                                    class="form-control @error('phone_number')is-invalid @enderror"
                                    value="{{ old('phone_number') }}" id="phone_number">
                                @error('phone_number')
                                    <div class="invalid-feedback">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-lg-6">
                        <div class="form-group">
                            <label for="password">Password</label>
                            <div class="position-relative">
                                <input required name="password" type="text"
                                    class="form-control @error('password')is-invalid @enderror"
                                    value="{{ old('password') }}" id="password">
                                @error('password')
                                    <div class="invalid-feedback">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="form-group">
                            <label for="role">Role</label>
                            @if (Auth::user()->role === 'superadmin')
                                <select class="form-control" name="role" id="role">
                                    <option value="admin">Admin</option>
                                </select>
                            @else
                                <select class="form-control" name="role" id="role">
                                    <option value="teknisi">Teknisi</option>
                                    {{-- <option value="kasir">Kasir</option> --}}
                                </select>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </x-modal-form>

        <section class="row">
            <div class="col-lg-5">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex align-items-center gap-3">
                            <i class="fa-solid fa-user"></i>
                            <h5 class="p-0 m-0">
                                My Profile
                            </h5>
                        </div>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('admin.updateProfile') }}" method="post">
                            @csrf
                            @method('PUT')

                            <div class="form-group mb-3">
                                <label for="profile_name">Nama</label>
                                <input required name="name" type="text"
                                    class="form-control @error('name')is-invalid @enderror"
                                    value="{{ old('name', Auth::user()->name) }}" id="profile_name">
                                @error('name')
                                    <div class="invalid-feedback">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>

                            <div class="form-group mb-3">
                                <label for="profile_username">Username</label>
                                <input required name="username" type="text"
                                    class="form-control @error('username')is-invalid @enderror"
                                    value="{{ old('username', Auth::user()->username) }}" id="profile_username">
                                @error('username')
                                    <div class="invalid-feedback">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>

                            <div class="form-group mb-3">
                                <label for="profile_email">Email</label>
                                <input required name="email" type="email"
                                    class="form-control @error('email')is-invalid @enderror"
                                    value="{{ old('email', Auth::user()->email) }}" id="profile_email">
                                @error('email')
                                    <div class="invalid-feedback">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>

                            <div class="form-group mb-3">
                                <label for="profile_phone_number">Nomor HP</label>
                                <input name="phone_number" type="text"
                                    class="form-control @error('phone_number')is-invalid @enderror"
                                    value="{{ old('phone_number', Auth::user()->phone_number) }}"
                                    id="profile_phone_number">
                                @error('phone_number')
                                    <div class="invalid-feedback">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>

                            <div class="form-group mb-3">
                                <label for="profile_password">Password (Kosongkan jika tidak ingin mengubah)</label>
                                <input name="password" type="password"
                                    class="form-control @error('password')is-invalid @enderror"
                                    placeholder="Masukkan password baru" id="profile_password">
                                @error('password')
                                    <div class="invalid-feedback">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>

                            <div class="form-group mb-4">
                                <label for="profile_role">Role</label>
                                <input type="text" class="form-control" value="{{ ucfirst(Auth::user()->role) }}"
                                    readonly disabled>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-outline-primary">
                                    Update
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-lg-7">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center gap-3">
                                <i class="fa-solid fa-user-group"></i>
                                <h5 class="p-0 m-0">
                                    User Admin
                                </h5>
                            </div>
                            <button class="btn btn-outline-primary btn-sm px-4" data-bs-toggle="modal"
                                data-bs-target="#formCreateModal"><i class="fa-solid fa-plus"></i>
                                Add User
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table" id="table1">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Username</th>
                                        <th>Nama</th>
                                        <th>Email</th>
                                        <th>Nomor HP</th>
                                        <th>Role</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($data as $index => $datas)
                                        <tr>
                                            <td>{{ $index + 1 }}</td>
                                            <td class="text-nowrap">
                                                {{ $datas->username }}
                                            </td>
                                            <td class="text-nowrap">{{ $datas->name }}</td>
                                            <td>{{ $datas->email }}</td>
                                            <td>{{ $datas->phone_number }}</td>
                                            <td>{{ $datas->role }}</td>
                                            <td>
                                                <form action="{{ route('admin.destroy', $datas->id) }}" method="POST">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button @if ($datas->role === 'mitra' || $datas->role === 'superadmin') disabled @endif
                                                        class="btn btn-outline-danger btn-sm ">
                                                        <i class="fa-solid fa-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="text-center">Tidak ada data</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </section>


        @push('script-page')
            <script>
                document.addEventListener("DOMContentLoaded", function() {
                    $('#table1').DataTable({
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
                                targets: [6], // Script and Action columns
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
                    document.querySelectorAll(".edit-btn").forEach(button => {
                        button.addEventListener("click", function() {
                            document.getElementById("edit-username").value = this.dataset.username;

                            document.querySelector("#formEditModal form").action =
                                `/settings/admin/${this.dataset.id}`;
                        });
                    });
                });
            </script>
        @endpush
    @endsection
