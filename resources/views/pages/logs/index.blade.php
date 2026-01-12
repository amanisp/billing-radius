@extends('layouts.admin')
@section('content')
    <div id="main">
        {{-- Navbar --}}
        @include('includes.v_navbar')

        <section class="section">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title">Activity Log</h5>
                    <div class="filters">
                        <select id="operationFilter" class="form-select form-select-sm d-inline-block" style="width: 150px;">
                            <option value="">All Operations</option>
                            <option value="CREATE">Create</option>
                            <option value="READ">Read</option>
                            <option value="UPDATE">Update</option>
                            <option value="DELETE">Delete</option>
                            <option value="LOGIN">Login</option>
                            <option value="LOGOUT">Logout</option>
                            <option value="SESSION_TIMEOUT">Session Timeout</option>
                            <option value="LOGIN_FAILED">Login Failed</option>
                            <option value="PASSWORD_RESET">Password Reset</option>
                            <option value="ACCOUNT_LOCKED">Account Locked</option>
                        </select>
                        <select id="roleFilter" class="form-select form-select-sm d-inline-block ms-2"
                            style="width: 150px;">
                            <option value="">All Roles</option>
                            {{-- <option value="superadmin">Super Admin</option> --}}
                            <option value="mitra">Administrator</option>
                            <option value="kasir">Kasir</option>
                            <option value="teknisi">Teknisi</option>
                        </select>
                    </div>
                </div>
                <hr>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="auditTable">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Username</th>
                                    <th>Operation</th>
                                    <th>Role</th>
                                    <th>Session ID</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Detail Modal -->
            <div class="modal fade" id="detailModal" tabindex="-1" aria-labelledby="detailModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="detailModalLabel">Activity Details</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>Basic Information</h6>
                                    <table class="table table-sm">
                                        <tr>
                                            <td>Operation</td>
                                            <td id="modal-operation"></td>
                                        </tr>
                                        <tr>
                                            <td>Table</td>
                                            <td id="modal-table"></td>
                                        </tr>
                                        <tr>
                                            <td>Time</td>
                                            <td id="modal-time"></td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <h6>User Information</h6>
                                    <table class="table table-sm">
                                        <tr>
                                            <td>Username</td>
                                            <td id="modal-username"></td>
                                        </tr>
                                        <tr>
                                            <td>Role</td>
                                            <td id="modal-role"></td>
                                        </tr>
                                        <tr>
                                            <td>IP Address</td>
                                            <td id="modal-ip"></td>
                                        </tr>
                                        <tr>
                                            <td>Session ID</td>
                                            <td id="modal-session" class="text-break"></td>
                                        </tr>
                                    </table>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    @push('script-page')
        <!-- Add these BEFORE your DataTables script -->
        <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>
        <script>
            $(document).ready(function() {
                let table = $('#auditTable').DataTable({
                    processing: true,
                    serverSide: false,
                    // scrollY: '200px',
                    scrollCollapse: true,
                    paging: true,
                    responsive: true,
                    ajax: {
                        url: '{{ route('logs.getData') }}',
                        data: function(d) {
                            d.operation_type = $('#operationFilter').val();
                            d.user_role = $('#roleFilter').val();
                        }
                    },
                    order: [
                        [0, 'desc']
                    ], // sort by time desc
                    columns: [{
                            data: 'time',
                            name: 'time',
                            render: function(data) {
                                return data ? moment(data).format('YYYY-MM-DD HH:mm:ss') : '-';
                            }
                        },
                        {
                            data: 'username',
                            name: 'username'
                        },
                        {
                            data: 'operation',
                            name: 'operation',
                            render: function(data) {
                                const badges = {
                                    'CREATE': 'text-success',
                                    'READ': 'text-info',
                                    'UPDATE': 'text-warning',
                                    'DELETE': 'text-danger',
                                    'LOGIN': 'text-primary',
                                    'LOGOUT': 'text-secondary',
                                    'SESSION_TIMEOUT': 'text-warning',
                                    'LOGIN_FAILED': 'text-danger',
                                    'PASSWORD_RESET': 'text-info',
                                    'ACCOUNT_LOCKED': 'text-danger'
                                };
                                return `<span class="text-sm ${badges[data] || 'text-secondary'}">${data}</span>`;
                            }
                        },
                        // {
                        //     data: 'table_name',
                        //     name: 'table_name'
                        // },

                        {
                            data: 'role',
                            name: 'role',
                            render: function(data) {
                                // normalize, safe string
                                const roleStr = data ? String(data).trim().toLowerCase() : '';

                                const roleClasses = {
                                    'mitra': 'text-primary',
                                    'teknisi': 'text-warning text-dark', // warning default text is dark -> keep readable
                                    'kasir': 'text-success',
                                    'superadmin': 'text-danger'
                                };

                                const cls = roleClasses[roleStr] || 'text-secondary';
                                // escapeHtml is already defined lower in the script (hoisted function), so safe to use
                                const label = data ? escapeHtml(String(data)) : '-';

                                return `<span class=" ${cls}">${label}</span>`;
                            }
                        },

                        // {
                        //     data: 'ip_address',
                        //     name: 'ip_address'
                        // },
                        {


                            data: 'session_id',
                            name: 'session_id'
                        },
                        {
                            data: null,
                            orderable: false,
                            searchable: false,
                            render: function() {
                                return '<button class="btn btn-sm btn-outline-primary" onclick="showDetails(this)">Details</button>';
                            }
                        }
                    ]
                });

                // Apply filters
                $('#operationFilter, #roleFilter').change(function() {
                    table.ajax.reload();
                });
            });

            // helper: escape html (XSS-safe)
            function escapeHtml(s) {
                return String(s)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#39;');
            }

            // try parse string -> object safely, handle double-encoded JSON
            function tryParseRawDetails(raw) {
                if (raw == null || raw === '') return null;

                // already an object/array
                if (typeof raw === 'object') return raw;

                // if it's not a string, coerce then try
                let s = String(raw);

                try {
                    let parsed = JSON.parse(s);
                    // parsed could still be string (double encoded), try again
                    if (typeof parsed === 'string') {
                        try {
                            return JSON.parse(parsed);
                        } catch (e) {
                            return parsed;
                        }
                    }
                    return parsed;
                } catch (e) {
                    // try strip slashes (in case PHP saved escaped JSON)
                    try {
                        let stripped = s.replace(/\\+/g, '');
                        return JSON.parse(stripped);
                    } catch (e2) {
                        return null;
                    }
                }
            }

            // render object/array as pretty HTML
            function renderPretty(obj) {
                function formatJSON(data) {
                    let json = JSON.stringify(
                        data,
                        (key, value) => (typeof value === "string" ? value : value),
                        2
                    );
                    json = json.replace(/\\n/g, '\n');
                    return escapeHtml(json);
                }

                let html = '<div class="mt-3" id="modal-details">';
                if (obj && (obj.before || obj.after)) {
                    if (obj.before) {
                        html += '<h6 class="mb-1">Before</h6>';
                        html +=
                            `<pre class="p-2 bg-light rounded" style="white-space:pre-wrap;word-break:break-word;">${formatJSON(obj.before)}</pre>`;
                    }
                    if (obj.after) {
                        html += '<h6 class="mb-1 mt-2">After</h6>';
                        html +=
                            `<pre class="p-2 bg-light rounded" style="white-space:pre-wrap;word-break:break-word;">${formatJSON(obj.after)}</pre>`;
                    }
                } else {
                    html +=
                        `<pre class="p-2 bg-light rounded" style="white-space:pre-wrap;word-break:break-word;">${formatJSON(obj)}</pre>`;
                }
                html += '</div>';
                return html;
            }




            // MAIN: replace your old showDetails with this
            function showDetails(btn) {
                // remove any previous details block to avoid duplicates
                $('#modal-details').remove();

                const data = $('#auditTable').DataTable().row($(btn).parents('tr')).data();

                // fill basic fields (unchanged)
                $('#modal-operation').text(data.operation || '-');
                $('#modal-table').text(data.table_name || '-');
                $('#modal-time').text(data.time || '-');
                $('#modal-username').text(data.username || '-');
                $('#modal-role').text(data.role || '-');
                $('#modal-ip').text(data.ip_address || '-');
                $('#modal-session').text(data.session_id || '-');

                // get raw_details or details fallback
                const raw = data.raw_details ?? data.details ?? '';

                // if raw is object already, use it
                let parsed = null;
                if (typeof raw === 'object') {
                    parsed = raw;
                } else {
                    parsed = tryParseRawDetails(raw);
                }

                let html = '';
                if (parsed) {
                    html = renderPretty(parsed);
                } else {
                    // not JSON: show as escaped plain text
                    const text = raw ? escapeHtml(String(raw)) : '<em>No details</em>';
                    html =
                        `<div class="mt-3" id="modal-details"><pre class="p-2 bg-light rounded" style="white-space:pre-wrap;word-break:break-word;">${text}</pre></div>`;
                }

                // append details after existing modal content
                $('#detailModal .modal-body').append(html);

                $('#detailModal').modal('show');
            }
        </script>
    @endpush
@endsection
