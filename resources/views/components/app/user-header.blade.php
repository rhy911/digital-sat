@props([
    'user' => null,
    'headerClass' => '',
    'logoClass' => '',
    'userClass' => '',
])

<header class="{{ $headerClass }}">
    <div class="container">
        <div class="d-flex justify-content-between">
            <div class="bluebook-logo {{ $logoClass }}">
                <span>Bluebook™</span>
            </div>
            <div class="user-dropdown">
                <div class="user {{ $userClass }}" id="userDropdown">
                    <span class="me-2">{{ $user->username ?? 'Guest' }}</span>
                    <div class="avatar">
                        <img src="{{ asset('images/default_avt.jpg') }}" alt="User">
                    </div>
                </div>
                <div class="dropdown-menu" id="dropdownMenu">
                    <div class="px-4 py-2 border-bottom d-md-none">
                        <div class="fw-bold">{{ $user->username ?? 'Guest' }}</div>
                        <div class="small text-muted text-truncate">{{ $user->email ?? '' }}</div>
                    </div>

                    @if($user && in_array($user->role, ['admin', 'teacher']))
                        <a href="{{ route('test-dashboard.index') }}" class="dropdown-item">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="3" width="7" height="7"></rect>
                                <rect x="14" y="3" width="7" height="7"></rect>
                                <rect x="14" y="14" width="7" height="7"></rect>
                                <rect x="3" y="14" width="7" height="7"></rect>
                            </svg>
                            Test Dashboard
                        </a>
                        <div class="border-top my-1"></div>
                    @endif

                    <form action="{{ route('logout') }}" method="POST" class="m-0">
                        @csrf
                        <button type="submit" class="dropdown-item logout-btn">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                                <polyline points="16 17 21 12 16 7"></polyline>
                                <line x1="21" y1="12" x2="9" y2="12"></line>
                            </svg>
                            Sign Out
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</header>

