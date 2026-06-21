<x-layouts.student :user="$user" title="My Profile" header-type="default">
    @push('styles')
        @vite(['resources/css/student/analytics.css', 'resources/css/classroom.css'])
    @endpush
    <div class="student-workspace student-workspace--narrow">
        <a href="{{ route('dashboard') }}" class="back-link">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="inline mr-1" style="vertical-align: -2px;">
                <line x1="19" y1="12" x2="5" y2="12"></line>
                <polyline points="12 19 5 12 12 5"></polyline>
            </svg>
            Back to Dashboard
        </a>

        @if(session('success'))
            <div class="class-alert class-alert--success" role="status" style="margin-top: 12px; margin-bottom: 24px;">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="class-alert class-alert--error" role="alert" style="margin-top: 12px; margin-bottom: 24px;">
                <ul style="margin: 0; padding-left: 16px;">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="class-panel" style="display: flex; align-items: center; gap: 24px; padding: 24px;">
            <div style="width: 80px; height: 80px; border-radius: 50%; background: linear-gradient(135deg, #4361EE, #7209B7); display: flex; align-items: center; justify-content: center; color: white; font-size: 2rem; font-weight: 800; text-transform: uppercase; box-shadow: 0 4px 12px rgba(67, 97, 238, 0.25);">
                {{ substr($user->name ?: $user->username ?: 'U', 0, 2) }}
            </div>
            <div>
                <span class="status-chip status-chip--active" style="margin-bottom: 6px;">{{ ucfirst($user->role) }}</span>
                <h1 style="margin: 0 0 4px; font-size: 1.50rem; font-weight: 850; color: var(--cw-ink-strong);">{{ $user->name ?: $user->username }}</h1>
                <p style="margin: 0; color: var(--cw-muted); font-size: 0.9rem;">Member since {{ $user->created_at->format('M Y') }}</p>
            </div>
        </div>

        <section class="class-panel" aria-labelledby="profile-info-heading">
            <h2 id="profile-info-heading" style="margin: 0 0 6px; color: var(--cw-ink-strong); font-size: 1.25rem; font-weight: 800;">Profile Information</h2>
            <p style="margin: 0 0 20px; color: var(--cw-muted); font-size: 0.9rem;">Update your account's profile information.</p>
            
            <form method="POST" action="{{ route('profile.update') }}">
                @csrf
                <div style="display: grid; gap: 20px;">
                    <label>Name
                        <input type="text" name="name" value="{{ old('name', $user->name) }}" required autocomplete="name">
                    </label>
                    <label>Username
                        <input type="text" name="username" value="{{ old('username', $user->username) }}" required autocomplete="username">
                    </label>
                    <label style="opacity: 0.85;">Email address (Read-only)
                        <div style="position: relative;">
                            <input type="email" name="email" value="{{ $user->email }}" readonly style="background-color: var(--cw-surface-soft); cursor: not-allowed; padding-right: 36px;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); color: var(--cw-muted);">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                            </svg>
                        </div>
                    </label>
                    <div style="margin-top: 8px;">
                        <button type="submit" class="class-button class-button--primary">Save profile</button>
                    </div>
                </div>
            </form>
        </section>

        <section class="class-panel" aria-labelledby="password-heading">
            <h2 id="password-heading" style="margin: 0 0 6px; color: var(--cw-ink-strong); font-size: 1.25rem; font-weight: 800;">Update Password</h2>
            <p style="margin: 0 0 20px; color: var(--cw-muted); font-size: 0.9rem;">Ensure your account is using a long, random password to stay secure.</p>
            
            <form method="POST" action="{{ route('profile.update') }}">
                @csrf
                <!-- Pass standard fields unchanged to avoid validation errors if they update password only -->
                <input type="hidden" name="name" value="{{ $user->name }}">
                <input type="hidden" name="username" value="{{ $user->username }}">
                <input type="hidden" name="email" value="{{ $user->email }}">

                <div style="display: grid; gap: 20px;">
                    <label>Current Password
                        <input type="password" name="current_password" required autocomplete="current-password">
                    </label>
                    <label>New Password
                        <input type="password" name="password" required autocomplete="new-password">
                    </label>
                    <label>Confirm Password
                        <input type="password" name="password_confirmation" required autocomplete="new-password">
                    </label>
                    <div style="margin-top: 8px;">
                        <button type="submit" class="class-button class-button--primary">Update password</button>
                    </div>
                </div>
            </form>
        </section>
    </div>
</x-layouts.student>
