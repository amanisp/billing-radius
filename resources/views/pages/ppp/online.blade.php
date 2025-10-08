@extends('layouts.admin')
@section('content')
    <div id="main">
        {{-- Navbar --}}
        @include('includes.v_navbar')

        <section class="row">
            <div class="card">
                <div class="card-header">
                    <h5>Session Online Users PPPoE</h5>
                    <hr>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table" id="dataTable">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Session ID</th>
                                    <th>Username</th>
                                    <th>Login Time</th>
                                    <th>Last Update</th>
                                    <th>IP / Mac</th>
                                    <th>Upload</th>
                                    <th>Download</th>
                                    <th>Uptime</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        @push('script-page')
            <script>
                $(document).ready(function() {
                    let table = $('#dataTable').DataTable({
                        processing: true,
                        serverSide: true,
                        responsive: true,
                        ajax: @json(route('online.getData')),
                        columns: [{
                                data: 'DT_RowIndex',
                                name: 'DT_RowIndex',
                                orderable: false,
                                searchable: false
                            },
                            {
                                data: 'session',
                            },
                            {
                                data: 'username',
                            },
                            {
                                data: 'login_time',
                            },
                            {
                                data: 'last_update',
                            },
                            {
                                data: 'ip_mac',
                            },
                            {
                                data: 'upload',
                            },
                            {
                                data: 'download',
                            },
                            {
                                data: 'uptime',
                            },
                        ]
                    });

                });
            </script>
        @endpush
    @endsection
