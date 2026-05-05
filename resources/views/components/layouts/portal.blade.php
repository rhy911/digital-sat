@props(['title' => 'Digital SAT', 'nextUrl' => '#', 'backUrl' => '/home'])

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    @vite(['resources/css/app.css', 'resources/sass/app.scss', 'resources/js/app.js'])
    @stack('styles')
    <style>
        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        main {
            flex: 1;
            overflow: auto;
            scroll-behavior: smooth;
            padding-bottom: 20px;
        }

        main h1 {
            font-size: 2.25rem;
            text-align: center;
            font-weight: 400;
            margin: 2rem 0
        }

        main .container {
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.2);
            padding: 50px 40px;
            border-radius: 12px;
            max-width: 32.5rem;
        }

        footer {
            background-color: #fff;
        }

        .buttons {
            display: flex;
            flex-direction: row-reverse;
            padding: 20px 40px;
            border-top: 1px solid #cccccc;
            gap: 20px;
        }

        .btn {
            background-color: #2c53da;
            color: #fff;
            border: none;
            padding: 10px 24px;
            border-radius: 30px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
        }

        .btn:hover {
            background-color: #1a3bb8;
            color: #fff;
        }
    </style>
</head>

<body>
    <header></header>
    <main>
        <h1>{{ $title ?? '' }}</h1>
        <div class="container">
            {{ $slot }}
        </div>
    </main>
    <footer>
        <div class="buttons">
            <a href="{{ $nextUrl }}">
                <div class="btn">Next</div>
            </a>
            <a href="{{ $backUrl }}">
                <div class="btn">Back</div>
            </a>
        </div>
    </footer>
    @stack('scripts')
</body>

</html>
