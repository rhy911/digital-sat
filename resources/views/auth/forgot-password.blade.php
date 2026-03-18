<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quên mật khẩu | Premium Auth</title>
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}">
</head>

<body>
    <div class="auth-container">
        <div class="auth-header">
            <h1>Quên mật khẩu?</h1>
            <p>Nhập email của bạn và chúng tôi sẽ gửi link đặt lại mật khẩu.</p>
        </div>

        @if (session('status'))
        <div class="alert alert-success" style="background: rgba(34, 197, 94, 0.1); border: 1px solid rgba(34, 197, 94, 0.2); color: #86efac; padding: 0.75rem 1rem; border-radius: 0.75rem; margin-bottom: 1rem;">
            {{ session('status') }}
        </div>
        @endif

        <form action="{{ route('password.email') }}" method="POST">
            @csrf
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" placeholder="name@example.com" value="{{ old('email') }}" required autofocus>
                @error('email')
                <span style="color: #fca5a5; font-size: 0.75rem; margin-top: 0.25rem; display: block;">{{ $message }}</span>
                @enderror
            </div>

            <button type="submit" class="btn">Gửi link đặt lại mật khẩu</button>
        </form>

        <div class="auth-footer">
            <a href="{{ route('login') }}">Quay lại đăng nhập</a>
        </div>
    </div>
</body>

</html>