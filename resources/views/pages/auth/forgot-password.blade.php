<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name', 'Laravel') }}</title>

    <link rel="shortcut icon" href="{{ asset('images/logo.png') }}" type="image/x-icon">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app-dark.css') }}">
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}">
    <script src="https://kit.fontawesome.com/6a37edf554.js" crossorigin="anonymous"></script>
</head>

<body>
    {{-- <script src={{ asset('static/js/initTheme.js') }}></script> --}}
    <div id="auth">

        <div class="row h-100">
            <div class="col-lg-3 d-none d-lg-block"></div>
            <div class="col-lg-6 col-12 my-auto">
                <div class="card">
                    <div class="card-body">
                        <div class="auth-logo d-flex justify-content-center">
                            <img style="height: 120px !important; width: auto !important"
                                src={{ asset('images/logo.png') }} alt="Logo">
                        </div>
                        @if (session('status'))
                            <div class="alert alert-light-success color-danger"><i class="bi bi-exclamation-circle"></i>
                                {{ session('status') }}</div>
                        @endif

                        <h3 class="auth-title mt-2">Forgot Password</h3>
                        <p>Enter your Email Address</p>

                        <form method="POST" action="{{ route('password.email') }}">
                            @csrf

                            <div class="mb-4">
                                <div class="form-group position-relative has-icon-left">
                                    <input type="email" name="email"
                                        class="form-control form-control-xl @error('email') is-invalid @enderror"
                                        placeholder="email@mail.com" value="{{ old('email') }}">
                                    <div class="form-control-icon">
                                        <i class="fas fa-user"></i>
                                    </div>
                                </div>

                                @error('email')
                                    <div class="invalid-feedback d-block">
                                        <i class="bx bx-radio-circle"></i>
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>

                            <button type="submit" class="btn btn-primary btn-block btn-lg shadow-lg mt-3">Send Link
                                Reset
                                Password</button>
                        </form>
                        <div class="text-right ">
                            <p><a class="font-light" href="/login">Ingat Password?</a>.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 d-none d-lg-block"></div>
        </div>

    </div>
</body>

</html>
