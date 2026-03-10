<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xác minh Email - BlueBook</title>
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}">
    <style>
        .verify-container {
            max-width: 500px;
            margin: 50px auto;
            padding: 30px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background-color: #f9f9f9;
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .alert-warning {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .btn {
            background-color: #007bff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
            margin-top: 10px;
        }
        .btn:hover {
            background-color: #0056b3;
        }
        .text-center {
            text-align: center;
        }
        .small-text {
            font-size: 14px;
            color: #666;
            margin-top: 15px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="verify-container">
        <h2 class="text-center">Xác minh Email</h2>

        @if (session('warning'))
            <div class="alert alert-warning">
                {{ session('warning') }}
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-error">
                {{ session('error') }}
            </div>
        @endif

        @if (session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif

        <div style="margin: 30px 0; text-align: center;">
            <p>Vui lòng xác minh địa chỉ email của bạn trước khi tiếp tục.</p>
            <p style="margin: 20px 0; font-size: 18px;"><strong>{{ auth()->user()->email }}</strong></p>
            <p>Chúng tôi đã gửi một liên kết xác minh đến email của bạn.</p>
        </div>

        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button type="submit" class="btn">
                Gửi lại Email Xác minh
            </button>
        </form>

        <form method="POST" action="{{ route('logout') }}" style="margin-top: 10px;">
            @csrf
            <button type="submit" class="btn" style="background-color: #6c757d;">
                Đăng xuất
            </button>
        </form>

        <div class="small-text">
            <p>Nếu bạn không nhận được email, vui lòng kiểm tra thư rác hoặc gửi lại.</p>
        </div>
    </div>
</body>
</html>
