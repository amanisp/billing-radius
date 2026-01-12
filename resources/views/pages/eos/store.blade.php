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
            <div class="card">
                <div class="card-header">
                    <h5>Data Mitra</h5>
                    <hr>
                </div>
                <div class="card-body">

                    <form action="{{ route('eos.store') }}" method="post">
                        @csrf
                        <div class="row">
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label for="fullname">Nama Lengkap</label>
                                    <div class="position-relative">
                                        <input name="fullname" type="text"
                                            class="form-control @error('fullname')is-invalid @enderror"
                                            value="{{ old('fullname') }}" placeholder="Wicaksono" id="fullname">
                                        @error('fullname')
                                            <div class="invalid-feedback">
                                                {{ $message }}
                                            </div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label for="nik">NIK</label>
                                    <div class="position-relative">
                                        <input name="nik" type="number"
                                            class="form-control @error('nik')is-invalid @enderror"
                                            value="{{ old('nik') }}" placeholder="350xxxxxxxxxxxxx" id="niky">
                                        @error('nik')
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
                                    <label for="npwp">NPWP <small class="text-warning">Optional</small></label>
                                    <div class="position-relative">
                                        <input name="npwp" type="text"
                                            class="form-control @error('npwp')is-invalid @enderror"
                                            value="{{ old('npwp') }}" placeholder="Optional" id="npwp">
                                        @error('npwp')
                                            <div class="invalid-feedback">
                                                {{ $message }}
                                            </div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label for="customer_number">Nomor Pelanggan</label>
                                    <div class="position-relative">
                                        <input name="customer_number" type="text"
                                            class="form-control @error('customer_number')is-invalid @enderror"
                                            value="{{ old('customer_number') }}" placeholder="AMAN-xxxxx"
                                            id="customer_number">
                                        @error('customer_number')
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
                                    <label for="email">Email <small class="Optional"></small></label>
                                    <div class="position-relative">
                                        <input name="email" type="text"
                                            class="form-control @error('email')is-invalid @enderror"
                                            value="{{ old('email') }}" placeholder="email@mail.com" id="email">
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
                                            value="{{ old('phone_number') }}" placeholder="62xxxxxxxxxxx"
                                            id="phone_number">
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
                                <label for="area_id">Area</label>
                                <div class="form-group">
                                    <select name="area_id" class="form-select ">
                                        <option selected disabled>Pilih</option>
                                        @foreach ($area as $areas)
                                            <option value="{{ $areas->id }}">{{ $areas->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label for="register">Tanggal Registrasi</label>
                                    <div class="position-relative">
                                        <input name="register" type="date"
                                            class="form-control @error('register')is-invalid @enderror"
                                            value="{{ old('register') }}" placeholder="Example: 8" id="register">
                                        @error('register')
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
                                    <label for="name">Payment</label>
                                    <select name="payment" class="form-select ">
                                        <option value="Prabayar">Prabayar</option>
                                        <option value="Pascabayar">Pascabayar</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label for="nip">NIP</label>
                                    <div class="position-relative">
                                        <input name="nip" type="text"
                                            class="form-control @error('nip')is-invalid @enderror"
                                            value="{{ old('nip') }}" placeholder="xx.xx.xx.xx.xxx" id="nip">
                                        @error('nip')
                                            <div class="invalid-feedback">
                                                {{ $message }}
                                            </div>
                                        @enderror
                                    </div>
                                </div>
                            </div>


                        </div>
                        <div class="row">
                            <div class="form-group">
                                <label for="address">Address</label>
                                <div class="position-relative">
                                    <textarea name="address" type="number" class="form-control @error('address')is-invalid @enderror"
                                        value="{{ old('address') }}" placeholder="Malang" id="address"></textarea>
                                    @error('address')
                                        <div class="invalid-feedback">
                                            {{ $message }}
                                        </div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-outline-primary">Simpan</button>
                    </form>
                </div>
            </div>

            {{-- <div class="card">
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
            </div> --}}
        </section>

    </div>
@endsection
