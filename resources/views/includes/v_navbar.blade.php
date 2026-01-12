<header class="mb-3">
    <div class="d-flex align-items-center justify-content-xl-end justify-content-between">
        <!-- Burger button responsive -->
        <a href="#" class="burger-btn d-block d-xl-none">
            <i class="bi bi-justify fs-3"></i>
        </a>
        <div class=" d-flex gap-3 align-items-center">
            <div class="theme-dropdown dropdown d-flex gap-2 align-items-center">
                <button class="btn btn-outline-success btn-sm" type="button" id="themeDropdown" data-bs-toggle="dropdown"
                    aria-expanded="false">
                    <i class="fa-solid fa-palette"></i>
                </button>
                <ul class="dropdown-menu" aria-labelledby="themeDropdown">
                    <li><a class="dropdown-item theme-option" href="#" data-theme="light">Light</a></li>
                    <li><a class="dropdown-item theme-option" href="#" data-theme="dark">Dark</a></li>
                </ul>
            </div>
            <div style="border-left:1px solid #ccc; height:30px;"></div>

            <div class="dropdown">
                <a href="#" id="topbarUserDropdown"
                    class="user-dropdown d-flex align-items-center dropend dropdown-toggle " data-bs-toggle="dropdown"
                    aria-expanded="false">
                    <div class="avatar avatar-md">
                        <img src="https://api.dicebear.com/9.x/pixel-art/svg?seed=<?= Auth::user()->name ?>"
                            alt="Avatar">
                    </div>
                    <div class="text d-none d-md-block">
                        <h6 class="user-dropdown-name">{{ Auth::user()->name }}</h6>
                        <p class="user-dropdown-status text-sm text-muted">{{ Auth::user()->role }}</p>
                    </div>
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow-lg" aria-labelledby="topbarUserDropdown">
                    <li><a class="dropdown-item" href="{{ route('admin.index') }}">My Account</a></li>
                    <li><a class="dropdown-item" href="{{ route('admin.index') }}">Settings</a></li>
                    <li>
                        <hr class="dropdown-divider">
                    </li>
                    <li>
                        <form action="{{ route('logout') }}" method="post">
                            @csrf
                            <button class="dropdown-item btn text-danger" type="submit">Logout</button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </div>
    <hr>
</header>
