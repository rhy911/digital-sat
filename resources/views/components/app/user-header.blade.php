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
                    {{ $user->username ?? 'Guest' }}
                    <div class="avatar">
                        <img src="{{ asset('images/default_avt.jpg') }}" alt="User">
                    </div>
                </div>
                <div class="dropdown-menu" id="dropdownMenu">
                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="dropdown-item">
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

