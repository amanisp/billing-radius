@extends('layouts.admin')
@section('content')
    <div id="main">
        {{-- Navbar --}}
        @include('includes.v_navbar')

        <div class="row">
            <div class="col-md">
                <div class="card">
                    <div class="status-card total">
                        <i class="fa-solid fa-users status-icon"></i>
                        <div class="d-flex flex-column">
                            <spam class="status-number fs-4">{{ $total }}</spam>
                            <spam class="status-text">Total</spam>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md">
                <div class="card">
                    <div class="status-card active">
                        <i class="fa-solid fa-check status-icon"></i>
                        <div class="d-flex flex-column">
                            <spam class="status-number fs-4">{{ $active }}</spam>
                            <spam class="status-text">Active</spam>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md">
                <div class="card">
                    <div class="status-card suspend">
                        <i class="fa-solid fa-ban status-icon"></i>
                        <div class="d-flex flex-column">
                            <spam class="status-number fs-4">{{ $isolir }}</spam>
                            <spam class="status-text">Suspend</spam>
                        </div>
                    </div>
                </div>
            </div>
        </div>



        {{-- Create/Import/Update --}}
        @include('pages.ppp.pppoe.create')
        @include('pages.ppp.pppoe.edit')
        @include('pages.ppp.pppoe.session')
        @include('pages.ppp.pppoe.import')

        <section class="row">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-primary btn-sm px-5 py-2" data-bs-toggle="modal"
                            data-bs-target="#formCreateModal"><i class="fa-solid fa-user-plus"></i>
                            Add User
                        </button>
                        <button class="btn btn-outline-success btn-sm px-5 py-2" data-bs-toggle="modal"
                            data-bs-target="#modalExcel"><i class="fa-solid fa-upload"></i>
                            Import
                        </button>

                    </div>
                    <hr />
                    <div class="filters d-flex gap-2 ">
                        <select id="operationFilter" class="form-select px-5 py-2">
                            <option value="">All Status</option>
                            <option value="CREATE">Active</option>
                            <option value="READ">Suspend</option>
                        </select>
                        <select id="roleFilter" class="form-select px-5 py-2">

                            <option value=" " selected>All Profile</option>
                            @foreach ($profile as $profiles)
                                <option value="{{ $profiles->id }}">{{ $profiles->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <hr>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table" id="dataTable">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Nomor Layanan</th>
                                    <th>Username</th>
                                    <th>Type</th>
                                    <th>Nas</th>
                                    <th>Profile</th>
                                    <th>Isolir</th>
                                    <th>Status</th>
                                    <th>Internet</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </section>


        @push('script-page')
            @include('pages.ppp.pppoe.script')
        @endpush

    </div>
@endsection
