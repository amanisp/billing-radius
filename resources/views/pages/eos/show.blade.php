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


        <section class="row">
            <div class="card shadow-sm">
                <div class="card-body">

                    {{-- HEADER PROFILE --}}
                    <div class="d-flex align-items-center mb-4">
                        <div class="me-3">
                            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center"
                                style="width:70px;height:70px;font-size:24px;">
                                {{ strtoupper(substr($eos->fullname, 0, 1)) }}
                            </div>
                        </div>
                        <div>
                            <h4 class="mb-0">{{ $eos->fullname }}</h4>
                            <small class="text-muted">NIP: {{ $eos->nip }}</small>
                        </div>
                    </div>

                    <hr>

                    {{-- DATA UTAMA --}}
                    <h6 class="text-muted mb-3">Informasi Pribadi</h6>
                    <div class="row">
                        <div class="col-md-6 mb-2">
                            <strong>NIK</strong>
                            <div>{{ $eos->nik }}</div>
                        </div>
                        <div class="col-md-6 mb-2">
                            <strong>NPWP</strong>
                            <div>{{ $eos->npwp ?? '-' }}</div>
                        </div>
                        <div class="col-md-6 mb-2">
                            <strong>Email</strong>
                            <div>{{ $eos->email ?? '-' }}</div>
                        </div>
                        <div class="col-md-6 mb-2">
                            <strong>Nomor HP</strong>
                            <div>{{ $eos->phone_number }}</div>
                        </div>
                    </div>

                    <hr>

                    {{-- INFO BISNIS --}}
                    <h6 class="text-muted mb-3">Informasi Mitra</h6>
                    <div class="row">
                        <div class="col-md-6 mb-2">
                            <strong>Nomor Pelanggan</strong>
                            <div>{{ $eos->customer_number }}</div>
                        </div>
                        <div class="col-md-6 mb-2">
                            <strong>Area</strong>
                            <div>{{ optional($eos->area)->name ?? '-' }}</div>
                        </div>
                        <div class="col-md-6 mb-2">
                            <strong>Tanggal Registrasi</strong>
                            <div>{{ \Carbon\Carbon::parse($eos->register)->format('d M Y') }}</div>
                        </div>
                        <div class="col-md-6 mb-2">
                            <strong>Metode Pembayaran</strong>
                            <span class="badge bg-info">
                                {{ ucfirst($eos->payment) }}
                            </span>
                        </div>
                    </div>

                    <hr>

                    {{-- ALAMAT --}}
                    <h6 class="text-muted mb-2">Alamat</h6>
                    <p class="mb-4">{{ $eos->address }}</p>

                    {{-- ACTION --}}
                    <div class="d-flex justify-content-end">
                        {{-- <a href="{{ route('eos.edit', $eos->id) }}" class="btn btn-outline-primary">
                            <i class="fa-solid fa-pen"></i> Edit Profile
                        </a> --}}
                    </div>

                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5>Data Layanan</h5>
                    <hr>
                </div>
                <div class="card-body">

                    <form action="" method="post">
                        @csrf
                        <div class="row">
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label for="name">Nama Layanan</label>
                                    <div class="position-relative">
                                        <input name="name" type="text"
                                            class="form-control @error('name')is-invalid @enderror"
                                            value="{{ old('name') }}" placeholder="Phoenix Diamond: Dedicated"
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
                                    <label for="capasity">Kapasitas <small>Mbps</small></label>
                                    <div class="position-relative">
                                        <input name="capasity" type="number"
                                            class="form-control @error('capasity')is-invalid @enderror"
                                            value="{{ old('capasity') }}" placeholder="1200" id="capasity">
                                        @error('capasity')
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
                                    <label for="otc">OTC (One Time Charge)</label>
                                    <div class="position-relative">
                                        <input name="otc" type="text"
                                            class="form-control @error('otc')is-invalid @enderror"
                                            value="{{ old('otc') }}" placeholder="5000000" id="otc">
                                        @error('otc')
                                            <div class="invalid-feedback">
                                                {{ $message }}
                                            </div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label for="price">Harga</label>
                                    <div class="position-relative">
                                        <input name="price" type="number"
                                            class="form-control @error('price')is-invalid @enderror"
                                            value="{{ old('price') }}" placeholder="16100000" id="price">
                                        @error('price')
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
                                <label for="ppn">PPn 11%</label>
                                <div class="form-group">
                                    <select name="ppn" class="form-select ">
                                        <option value="true">Iya</option>
                                        <option value="false">Tidak</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <label for="bhp">BHP, USO, KSO (4%)</label>
                                <div class="form-group">
                                    <select name="bhp" class="form-select ">
                                        <option value="true">Iya</option>
                                        <option value="false">Tidak</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                    </form>
                </div>
            </div>
        </section>

    </div>
@endsection
