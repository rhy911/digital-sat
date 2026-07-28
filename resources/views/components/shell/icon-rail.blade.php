@props([
    'items' => [],
    'logoHref' => '#',
    'logoLabel' => 'D',
    'avatarLabel' => '',
    'profileHref' => null,
    'logoutHref' => null,
])

@php
    $profileHref = $profileHref ?? route('profile');
    $logoutHref = $logoutHref ?? route('logout');
@endphp

<button type="button" class="mobile-nav-toggle" data-mobile-nav-toggle aria-label="Open menu" aria-expanded="false">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <line x1="3.5" y1="6" x2="20.5" y2="6" />
        <line x1="3.5" y1="12" x2="20.5" y2="12" />
        <line x1="3.5" y1="18" x2="20.5" y2="18" />
    </svg>
</button>
<div class="mobile-nav-scrim" data-mobile-nav-scrim></div>

<div class="icon-rail">
    <a href="{{ $logoHref }}" class="rail-logo" aria-label="{{ $logoLabel === 'D' ? 'DigiSAT home' : $logoLabel }}">
        <img src="/brand/icon-light.svg" alt="" aria-hidden="true" class="rail-logo__img">
    </a>
    <div class="rail-nav">
        @foreach ($items as $item)
            <a href="{{ $item['route'] }}" class="rail-item {{ !empty($item['active']) ? 'active' : '' }}"
                @if (!empty($item['target'])) target="{{ $item['target'] }}" @endif>
                <span class="rail-tip">{{ $item['label'] }}</span>
                {!! $item['icon'] !!}
            </a>
        @endforeach
    </div>

    <div class="rail-bottom">
    <div class="rail-bell" x-data="{ open: false }" @keydown.escape.window="open = false" @click.outside="open = false"
        data-notif-bell
        data-summary-url="{{ route('notifications.summary') }}"
        data-read-all-url="{{ route('notifications.read-all') }}"
        data-read-url-template="{{ route('notifications.read', ['id' => '__ID__']) }}"
        data-index-url="{{ route('notifications.index') }}">
        <button type="button" class="rail-item rail-bell__btn" @click="open = !open"
            :aria-expanded="open ? 'true' : 'false'" aria-haspopup="menu" aria-label="Notifications">
            <span class="rail-tip">Notifications</span>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
                <path d="M13.73 21a2 2 0 0 1-3.46 0" />
            </svg>
            <span class="rail-bell__badge" data-notif-badge hidden>0</span>
        </button>

        <div class="rail-account__menu rail-bell__menu" role="menu" x-show="open" x-cloak
            x-transition.opacity.duration.120ms>
            <div class="rail-bell__head">
                <span class="rail-bell__title">Notifications</span>
                <button type="button" class="rail-bell__mark" data-notif-mark-all>Mark all read</button>
            </div>
            <div class="rail-bell__list" data-notif-list>
                <p class="rail-bell__empty">No notifications yet.</p>
            </div>
            <a href="{{ route('notifications.index') }}" class="rail-bell__all" role="menuitem">See all</a>
        </div>
    </div>

    <div class="rail-account" x-data="{ open: false }" @keydown.escape.window="open = false" @click.outside="open = false">
        <button type="button" class="rail-avatar" @click="open = !open" :aria-expanded="open ? 'true' : 'false'"
            aria-haspopup="menu" aria-label="Account menu">
            {{ $avatarLabel }}
        </button>

        <div class="rail-account__menu" role="menu" x-show="open" x-cloak x-transition.opacity.duration.120ms
            @click="open = false">
            <a href="{{ $profileHref }}" class="rail-account__item" role="menuitem">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                    <circle cx="12" cy="7" r="4" />
                </svg>
                Profile
            </a>
            <form id="header-logout-form" action="{{ $logoutHref }}" method="POST" class="rail-account__form">
                @csrf
                <button type="submit" class="rail-account__item rail-account__item--danger" role="menuitem">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                        <polyline points="16 17 21 12 16 7" />
                        <line x1="21" y1="12" x2="9" y2="12" />
                    </svg>
                    Sign out
                </button>
            </form>
        </div>
    </div>
    </div>
</div>
