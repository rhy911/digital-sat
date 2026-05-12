<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $pageTitle ?? 'Dashboard' }}</title>
    @vite(['resources/css/app.css', 'resources/sass/app.scss', 'resources/js/app.js'])
    @stack('styles')
</head>

<body class="bg-light">
    <header class="bg-white border-bottom py-3 mb-4">
        <div class="container-fluid px-4">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <a href="{{ route('home') }}" class="text-decoration-none text-dark d-flex align-items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                            fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                            stroke-linejoin="round">
                            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                            <polyline points="9 22 9 12 15 12 15 22"></polyline>
                        </svg>
                        <span class="ms-2 fw-bold">Home</span>
                    </a>
                    {{-- <span class="ms-3 fw-semibold">{{ $pageTitle ?? 'Dashboard' }}</span> --}}
                </div>
                @auth
                    <div class="d-flex align-items-center gap-3">
                        <span class="text-muted">{{ auth()->user()->username ?? auth()->user()->email }}</span>
                        <form action="{{ route('logout') }}" method="POST" class="m-0">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-danger">Logout</button>
                        </form>
                    </div>
                @endauth
            </div>
        </div>
    </header>
    <main class="container-fluid px-4">
        {{ $slot }}
    </main>
    @stack('scripts')
</body>

</html>
