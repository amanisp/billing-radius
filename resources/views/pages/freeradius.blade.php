@extends('layouts.admin')
@section('content')
    <div id="main">
        <header class="mb-3">
            <a href="#" class="burger-btn d-block d-xl-none">
                <i class="bi bi-justify fs-3"></i>
            </a>
        </header>

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
                    <h3>Akses Radius</h3>
                </div>
                <div class="col-12 col-md-6 order-md-2 order-first">
                    <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard.index') }}">Dashboard</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Akses Freeradius</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>

        <div class="d-flex">
            <button class="btn btn-primary btn-sm px-4 rounded-pill mt-2 mb-4" data-bs-toggle="modal"
                data-bs-target="#formCreateModal"><i class="fa-solid fa-plus"></i>
                Create
            </button>

        </div>


        {{-- Modal Create --}}
        <x-modal-form id="formCreateModal" title="Tambah Akses Radius" action="{{ route('freeradius.store') }}">
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
                    <div class="col-lg-12">
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
                </div>
            </div>
        </x-modal-form>



        <section class="row">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table" id="table1">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Nama</th>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Nomor HP</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($data as $index => $datas)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>{{ $datas->name }}</td>
                                        <td>
                                            {{ $datas->username }}
                                        </td>
                                        <td>{{ $datas->email }}</td>
                                        <td>{{ $datas->phone_number }}</td>
                                        <td class="d-flex gap-2">
                                            <form action="{{ route('freeradius.destroy', $datas->id) }}" method="POST">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-danger btn-sm rounded-circle">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            </form>

                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="d-none"></td>
                                        <td colspan="6" class="text-center">Akses Belum Tersedia</td>
                                        <td class="d-none"></td>
                                        <td class="d-none"></td>
                                        <td class="d-none"></td>
                                        <td class="d-none"></td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        <script>
            document.addEventListener("DOMContentLoaded", function() {
                document.querySelectorAll(".edit-btn").forEach(button => {
                    button.addEventListener("click", function() {
                        document.getElementById("edit-username").value = this.dataset.username;

                        document.querySelector("#formEditModal form").action =
                            `/settings/admin/${this.dataset.id}`;
                    });
                });
            });
        </script>
    @endsection
