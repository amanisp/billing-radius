<script src="{{ asset('assets/static/js/initTheme.js') }}"></script>
<div id="app">
    <div id="sidebar">
        <div class="sidebar-wrapper active">
            <div class="sidebar-header position-relative">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="logo">
                        <a href="{{ route('dashboard.index') }}"><img height="100"
                                src="{{ asset('images/logo-sidebar.png') }}" alt="Logo" srcset=""></a>
                    </div>

                    <div class="sidebar-toggler  x">
                        <a href="#" class="sidebar-hide d-xl-none d-block"><i class="bi bi-x bi-middle"></i></a>
                    </div>
                </div>
            </div>
            <hr>
            <div class="sidebar-menu">
                <ul class="menu">
                    <li class="sidebar-title">Menu</li>
                    <li class="sidebar-item {{ Route::is('dashboard.index') ? 'active' : '' }}">
                        <a href="{{ route('dashboard.index') }}" class='sidebar-link'>
                            <i class="bi bi-grid-fill"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    @if (Auth::user()->role === 'teknisi' || Auth::user()->role === 'mitra')
                        @include('includes.sidebar.mitra')
                    @endif
                    @if (Auth::user()->role === 'kasir')
                        @include('includes.sidebar.kasir')
                    @endif
                    @if (Auth::user()->role === 'superadmin' || Auth::user()->role === 'admin')
                        @include('includes.sidebar.superadmin')
                    @endif
                </ul>
            </div>
        </div>
    </div>
