<x-layouts.student :user="$user" title="My Profile" header-type="none">
    @push('styles')
        @vite(['resources/css/classroom-workspace.css', 'resources/css/student/profile.css'])
    @endpush

    @php
        $hasPrivacyTab = $user->role === 'student';
    @endphp

    <div class="app-shell app-shell--no-list">
        <x-shell.icon-rail :logo-href="\App\Support\NavRail::logoHrefForUser($user)" :avatar-label="$user->initials"
            :items="\App\Support\NavRail::forUser($user)" />

        <div class="shell-content">
    <div class="profile-settings" x-data="{ tab: sessionStorage.getItem('profileActiveTab') || 'account' }"
        x-effect="sessionStorage.setItem('profileActiveTab', tab)">

        <div class="profile-settings__grid">
            {{-- Sidebar: identity card + tab nav --}}
            <div class="profile-settings__sidebar">
                <div class="ps-id-card">
                    <div class="ps-id-card__avatar">{{ substr($user->name ?: $user->username ?: 'U', 0, 2) }}</div>
                    <h2 class="ps-id-card__name">{{ $user->name ?: $user->username }}</h2>
                    <div class="ps-id-card__handle">{{ '@' . $user->username }}</div>
                    <span class="ps-role-pill">{{ ucfirst($user->role) }}</span>
                </div>

                <nav class="ps-tab-nav">
                    <button type="button" class="ps-tab" :class="{ 'is-active': tab === 'account' }"
                        @click="tab = 'account'">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z"
                                stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        Profile details
                    </button>
                    <button type="button" class="ps-tab" :class="{ 'is-active': tab === 'security' }"
                        @click="tab = 'security'">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                            <path d="M7 11V7a5 5 0 0 1 10 0v4" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        Sign-in &amp; password
                    </button>
                    @if ($hasPrivacyTab)
                        <button type="button" class="ps-tab" :class="{ 'is-active': tab === 'privacy' }"
                            @click="tab = 'privacy'">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" stroke-linecap="round"
                                    stroke-linejoin="round" />
                            </svg>
                            Privacy
                        </button>
                    @endif
                </nav>
            </div>

            {{-- Content --}}
            <div>
                @if (session('success'))
                    <div class="ps-alert ps-alert--success" role="status">{{ session('success') }}</div>
                @endif

                @if ($errors->any())
                    <div class="ps-alert ps-alert--error" role="alert">
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{-- Profile details --}}
                <div class="ps-panel" x-show="tab === 'account'" x-cloak>
                    <h3 class="ps-panel__title">Profile details</h3>
                    <p class="ps-panel__desc">Update the name and username other people on the platform see.</p>

                    <form method="POST" action="{{ route('profile.update') }}">
                        @csrf
                        <div class="ps-grid-2">
                            <div class="ps-field">
                                <label for="name">Name</label>
                                <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}"
                                    required autocomplete="name">
                                <span class="ps-field__hint">Visible to classmates and teachers.</span>
                            </div>
                            <div class="ps-field">
                                <label for="username">Username</label>
                                <input type="text" id="username" name="username"
                                    value="{{ old('username', $user->username) }}" required autocomplete="username">
                                <span class="ps-field__hint">Your unique handle on the platform.</span>
                            </div>
                            <div class="ps-field ps-field--full">
                                <label for="email">Email address <span class="ps-readonly-tag">Read-only</span></label>
                                <div class="ps-lock">
                                    <input type="email" id="email" name="email" value="{{ $user->email }}" readonly>
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                        stroke="currentColor" stroke-width="2">
                                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                    </svg>
                                </div>
                                <span class="ps-field__hint">Contact support to change your email address.</span>
                            </div>
                        </div>
                        <div class="ps-footer">
                            <button type="submit" class="ps-btn ps-btn--primary">Save profile</button>
                        </div>
                    </form>
                </div>

                {{-- Sign-in & password --}}
                <div class="ps-panel" x-show="tab === 'security'" x-cloak>
                    <h3 class="ps-panel__title">Sign-in &amp; password</h3>
                    <p class="ps-panel__desc">Use a long, random password to keep your account secure.</p>

                    <form method="POST" action="{{ route('profile.update') }}">
                        @csrf
                        {{-- Carry current profile fields so a password-only update passes validation --}}
                        <input type="hidden" name="name" value="{{ $user->name }}">
                        <input type="hidden" name="username" value="{{ $user->username }}">
                        <input type="hidden" name="email" value="{{ $user->email }}">

                        <div class="ps-grid-2">
                            <div class="ps-field ps-field--full" x-data="{ show: false }">
                                <label for="current_password">Current password</label>
                                <div class="ps-password">
                                    <input :type="show ? 'text' : 'password'" id="current_password"
                                        name="current_password" required autocomplete="current-password">
                                    <button type="button" class="ps-password-toggle" @click="show = !show"
                                        :aria-label="show ? 'Hide password' : 'Show password'">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path
                                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                                                stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            <div class="ps-field" x-data="{ show: false }">
                                <label for="password">New password</label>
                                <div class="ps-password">
                                    <input :type="show ? 'text' : 'password'" id="password" name="password" required
                                        autocomplete="new-password">
                                    <button type="button" class="ps-password-toggle" @click="show = !show"
                                        :aria-label="show ? 'Hide password' : 'Show password'">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path
                                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                                                stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            <div class="ps-field" x-data="{ show: false }">
                                <label for="password_confirmation">Confirm password</label>
                                <div class="ps-password">
                                    <input :type="show ? 'text' : 'password'" id="password_confirmation"
                                        name="password_confirmation" required autocomplete="new-password">
                                    <button type="button" class="ps-password-toggle" @click="show = !show"
                                        :aria-label="show ? 'Hide password' : 'Show password'">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path
                                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                                                stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="ps-footer">
                            <button type="submit" class="ps-btn ps-btn--primary">Update password</button>
                        </div>
                    </form>
                </div>

                {{-- Privacy (students only) --}}
                @if ($hasPrivacyTab)
                    <div class="ps-panel" x-show="tab === 'privacy'" x-cloak>
                        <h3 class="ps-panel__title">Privacy</h3>
                        <p class="ps-panel__desc">Control what your teachers can see about your independent practice.</p>

                        <form method="POST" action="{{ route('profile.sharing.update') }}">
                            @csrf
                            @method('PUT')
                            <label class="ps-toggle">
                                <span class="ps-switch">
                                    <input type="checkbox" name="share_independent_practice" value="1"
                                        {{ old('share_independent_practice', $user->share_independent_practice) ? 'checked' : '' }}>
                                    <span class="ps-switch__track" aria-hidden="true"></span>
                                </span>
                                <span class="ps-toggle__text">
                                    Share my independent practice test results with my teachers
                                    <small>Off by default. When on, teachers you share a class with can see your
                                        independent (non-assignment) practice results alongside assignment results on
                                        your progress page, clearly labeled as shared practice.</small>
                                </span>
                            </label>
                            <div class="ps-footer">
                                <button type="submit" class="ps-btn ps-btn--primary">Save privacy setting</button>
                            </div>
                        </form>
                    </div>
                @endif
            </div>
        </div>
    </div>
        </div>
    </div>
</x-layouts.student>
