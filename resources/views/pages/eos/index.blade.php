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
                    <a href={{ route('eos.add') }} class="btn btn-outline-primary btn-sm px-5 py-2"><i
                            class="fa-solid fa-users"></i>
                        Tambah Mitra
                    </a>
                    <hr>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table" id="dataTable">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Nama Lengkap</th>
                                    <th>NIP</th>
                                    <th>NO. Pelanggan</th>
                                    <th>Area</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        {{-- Get AJAX --}}
        @include('pages.eos.script')
    </div>
@endsection
