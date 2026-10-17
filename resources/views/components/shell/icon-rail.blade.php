@props([
    'items' => [],
    'logoHref' => '#',
    'logoLabel' => 'DFET',
    'avatarLabel' => '',
    'profileHref' => null,
    'logoutHref' => null,
])

@php
    $profileHref = $profileHref ?? route('profile');
    $logoutHref = $logoutHref ?? route('logout');
@endphp

<button type="button" class="mobile-nav-toggle" data-mobile-nav-toggle aria-label="Open menu" aria-expanded="false">
    <svg class="mobile-nav-toggle__hamburger" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <line x1="3.5" y1="6" x2="20.5" y2="6" />
        <line x1="3.5" y1="12" x2="20.5" y2="12" />
        <line x1="3.5" y1="18" x2="20.5" y2="18" />
    </svg>
    <svg class="mobile-nav-toggle__close" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <line x1="18" y1="6" x2="6" y2="18" />
        <line x1="6" y1="6" x2="18" y2="18" />
    </svg>
</button>
<div class="mobile-nav-scrim" data-mobile-nav-scrim></div>

<aside class="icon-rail" aria-label="Sidebar navigation">
    <a href="{{ $logoHref }}" class="rail-logo" aria-label="{{ in_array($logoLabel, ['D', 'DFET'], true) ? 'DigiSAT home' : $logoLabel }}">
        <img src="/brand/icon-light.svg" alt="" aria-hidden="true" class="rail-logo__img">
    </a>

    <nav class="rail-nav" aria-label="Primary navigation">
        @foreach ($items as $item)
            @if (!empty($item['divider']) || (!empty($item['target']) && $item['target'] === '_blank' && !$loop->first))
                <div class="rail-divider" role="separator" aria-hidden="true"></div>
            @endif

            @php
                $key = $item['key'] ?? '';
                $rawLabel = $item['label'] ?? '';
                $shortLabel = match (true) {
                    $key === 'practice' || $rawLabel === 'Test Library' => 'Library',
                    $key === 'exam-sessions' || $rawLabel === 'Exam Sessions' => 'Sessions',
                    $key === 'test-builder' || $rawLabel === 'Test Builder' => 'Builder',
                    $key === 'assignments' || $rawLabel === 'Assignments' => 'Tasks',
                    $key === 'scores' || $rawLabel === 'My Scores' || $rawLabel === 'Scores' => 'Scores',
                    $key === 'classes' || $rawLabel === 'My Classes' || $rawLabel === 'Classes' => 'Classes',
                    $key === 'reports' || $rawLabel === 'Reports' => 'Reports',
                    $key === 'home' || $rawLabel === 'Home' || $rawLabel === 'Dashboard' => 'Home',
                    $key === 'progress' || $rawLabel === 'Progress' => 'Progress',
                    $key === 'applications' || str_contains(strtolower($rawLabel), 'application') => 'Review',
                    default => $rawLabel,
                };
            @endphp

            <a href="{{ $item['route'] }}" class="rail-item {{ !empty($item['active']) ? 'active' : '' }}"
                aria-label="{{ $item['label'] }}"
                title="{{ $item['label'] }}"
                @if (!empty($item['active'])) aria-current="page" @endif
                @if (!empty($item['target'])) target="{{ $item['target'] }}" rel="noopener noreferrer" @endif>
                <span class="rail-item__icon">{!! $item['icon'] !!}</span>
                <span class="rail-item__label">{{ $shortLabel }}</span>
                @if (!empty($item['target']) && $item['target'] === '_blank')
                    <span class="sr-only"> (opens in new tab)</span>
                @endif
            </a>
        @endforeach
    </nav>

    <div class="rail-bottom">
        <div class="rail-divider" role="separator" aria-hidden="true"></div>

        <div class="rail-bell" x-data="{ open: false }" @keydown.escape.window="open = false" @click.outside="open = false"
            data-notif-bell
            data-summary-url="{{ route('notifications.summary') }}"
            data-read-all-url="{{ route('notifications.read-all') }}"
            data-read-url-template="{{ route('notifications.read', ['id' => '__ID__']) }}"
            data-index-url="{{ route('notifications.index') }}">
            <button type="button" class="rail-item rail-bell__btn" @click="open = !open"
                :aria-expanded="open ? 'true' : 'false'" aria-haspopup="dialog" aria-label="Notifications">
                <span class="rail-item__icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
                        <path d="M13.73 21a2 2 0 0 1-3.46 0" />
                    </svg>
                    <span class="rail-bell__badge" data-notif-badge aria-live="polite" hidden>0</span>
                </span>
                <span class="rail-item__label">Alerts</span>
            </button>

            <div class="rail-account__menu rail-bell__menu" role="dialog" aria-label="Notifications" x-show="open" x-cloak
                x-transition.opacity.duration.150ms>
                <div class="rail-bell__head">
                    <div class="rail-bell__title-wrap">
                        <span class="rail-bell__title">Notifications</span>
                        <span class="rail-bell__count-tag" data-notif-badge-pill hidden>0 new</span>
                    </div>
                    <button type="button" class="rail-bell__mark" data-notif-mark-all>Mark all read</button>
                </div>
                <div class="rail-bell__list" data-notif-list>
                    <p class="rail-bell__empty">No notifications yet.</p>
                </div>
                <a href="{{ route('notifications.index') }}" class="rail-bell__all">
                    <span>See all notifications</span>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="rail-bell__arrow" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                </a>
            </div>
        </div>

        <div class="rail-account" x-data="{ open: false }" @keydown.escape.window="open = false" @click.outside="open = false">
            <button type="button" class="rail-item rail-avatar__btn" @click="open = !open"
                :aria-expanded="open ? 'true' : 'false'" aria-haspopup="dialog" aria-label="Account menu">
                <span class="rail-avatar">
                    {{ $avatarLabel }}
                    <span class="rail-avatar__status" aria-hidden="true"></span>
                </span>
                <span class="rail-item__label">Account</span>
            </button>

            <div class="rail-account__menu" role="dialog" aria-label="Account menu" x-show="open" x-cloak
                x-transition.opacity.duration.150ms @click="open = false">
                @if (auth()->check())
                    <div class="rail-account__user">
                        <div class="rail-account__avatar-lg">{{ $avatarLabel }}</div>
                        <div class="rail-account__meta">
                            <div class="rail-account__name">{{ auth()->user()->name }}</div>
                            <div class="rail-account__email">{{ auth()->user()->email }}</div>
                            <span class="rail-account__role-tag">{{ ucfirst(auth()->user()->role ?? 'User') }}</span>
                        </div>
                    </div>
                    <div class="rail-account__divider"></div>
                @endif
                <a href="{{ $profileHref }}" class="rail-account__item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                        <circle cx="12" cy="7" r="4" />
                    </svg>
                    Profile & Settings
                </a>
                <form id="header-logout-form" action="{{ $logoutHref }}" method="POST" class="rail-account__form">
                    @csrf
                    <button type="submit" class="rail-account__item rail-account__item--danger">
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
</aside>
