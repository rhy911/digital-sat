<x-layouts.student :user="$user" title="Notifications" header-type="none">
    @push('styles')
        @vite(['resources/css/classroom-workspace.css', 'resources/css/student/progress.css'])
    @endpush

    @php
        $unreadCount = $user->unreadNotifications()->count();
    @endphp

    <div class="app-shell app-shell--no-list" x-data="{}">
        <x-shell.icon-rail :logo-href="\App\Support\NavRail::logoHrefForUser($user)" :avatar-label="$user->initials"
            :items="\App\Support\NavRail::forUser($user)" />

        <div class="ledger-pane">
            <div class="binder-panel">
                <div class="ledger-header">
                    <div class="dh-left">
                        <h2>Notifications <span class="handwriting status-quote">"Stay in the loop"</span></h2>
                        <div class="dh-desc">Assignments, class updates, and account activity.</div>
                    </div>
                    <div>
                        @if ($notifications->isNotEmpty())
                            <form method="POST" action="{{ route('notifications.read-all') }}" style="display: inline;">
                                @csrf
                                <button type="submit" class="btn-outline">Mark all read</button>
                            </form>
                        @endif
                        <a href="{{ route('home') }}" class="btn-outline">Back to Home</a>
                    </div>
                </div>

                @if ($notifications->isNotEmpty())
                    <div style="margin-top: 20px;">
                        @foreach ($notifications as $notification)
                            @php
                                $data = $notification->data;
                                $url = $data['url'] ?? null;
                                $isUnread = $notification->read_at === null;
                            @endphp
                            <a href="{{ $url ?? route('notifications.index') }}"
                                class="index-card {{ $isUnread ? 'is-unread' : '' }}"
                                @if ($isUnread) data-notif-link data-read-url="{{ route('notifications.read', $notification->id) }}" @endif
                                style="display: flex; align-items: center; justify-content: space-between; gap: 16px; text-decoration: none; color: inherit; margin-bottom: 12px;">
                                <div style="min-width: 0;">
                                    <div class="card-title" style="margin-top: 0; min-height: 0;">
                                        @if ($isUnread)
                                            <span class="status-pill pending text-3xs font-bold mr-6">New</span>
                                        @endif
                                        {{ $data['title'] ?? 'Notification' }}
                                    </div>
                                    <div class="card-sub">{{ $data['body'] ?? '' }}</div>
                                </div>
                                <div style="flex-shrink: 0; font-size: 11px; color: var(--ink-faint); white-space: nowrap;">
                                    {{ $notification->created_at->diffForHumans() }}
                                </div>
                            </a>
                        @endforeach
                    </div>
                    <div style="margin-top: 8px;">{{ $notifications->links() }}</div>
                @else
                    <div class="empty-grid-cell" style="margin-top: 24px;">
                        <strong>No notifications yet</strong>
                        <p style="margin-top: 4px;">You'll see assignment and class updates here.</p>
                    </div>
                @endif
            </div>

            <!-- CORKBOARD -->
            <x-shell.corkboard header="Notification Center">
                <div class="cork-note">
                    <h3>Summary</h3>
                    <p class="digest-line"><strong>Unread:</strong> <span class="mono">{{ $unreadCount }}</span></p>
                    <p class="digest-line" style="margin-top: 4px;"><strong>Total:</strong> <span class="mono">{{ $notifications->total() }}</span></p>
                </div>

                @if ($unreadCount > 0)
                    <div class="cork-note">
                        <h3>Quick Actions</h3>
                        <p class="digest-line">Clear unread indicators from your feed.</p>
                        <form method="POST" action="{{ route('notifications.read-all') }}" class="mt-8">
                            @csrf
                            <button type="submit" class="btn-sm-primary btn-pin btn-full text-center">Mark all read</button>
                        </form>
                    </div>
                @endif
            </x-shell.corkboard>
        </div>
    </div>
</x-layouts.student>

