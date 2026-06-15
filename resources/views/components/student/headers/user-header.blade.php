@props([
    'user' => null,
    'headerClass' => '',
    'logoClass' => '',
    'userClass' => '',
])

<header class="sticky top-0 left-0 p-5 z-10 {{ $headerClass }}">
    <div class="md:container md:mx-auto px-4">
        <div class="flex justify-between items-center">
            <x-brand.wordmark href="/" size="lg" tone="brand" class="{{ $logoClass }}" />
            <div class="user-dropdown relative">
                <div class="user text-[1.125rem] font-bold flex items-center cursor-pointer py-2 px-4 rounded-lg gap-2 {{ $userClass }}" id="userDropdown">
                    <span class="me-2">{{ $user->username ?? 'Guest' }}</span>
                    <div class="avatar w-10 h-10 ml-0">
                        <img class="w-full h-full object-cover rounded-full" src="{{ asset('images/default_avt.jpg') }}" alt="User">
                    </div>
                </div>
                <div class="dropdown-menu absolute top-full right-0 mt-3 min-w-[220px] z-1000 p-0" id="dropdownMenu">
                    @if($user && in_array($user->role, ['admin', 'teacher']))
                        <a href="{{ route('home-dashboard.index') }}" class="dropdown-item flex items-center gap-3 w-full py-3 px-4 border-none bg-none text-[0.95rem] text-[#333] cursor-pointer">
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
                        <button type="submit" class="dropdown-item logout-btn flex items-center gap-3 w-full py-3 px-4 border-none bg-none text-[0.95rem] text-[#333] cursor-pointer">
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
