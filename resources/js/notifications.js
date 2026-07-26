// Header notification bell: polls unread count + recent list, renders dropdown.
// Polling pattern mirrors teacher/attempt-monitor.js (pause when tab hidden).

const POLL_INTERVAL = 20000;

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = value ?? '';
    return div.innerHTML;
}

function renderList(bell, recent) {
    const list = bell.querySelector('[data-notif-list]');
    if (!list) return;

    if (!recent || recent.length === 0) {
        list.innerHTML = '<p class="rail-bell__empty">No notifications yet.</p>';
        return;
    }

    const template = bell.dataset.readUrlTemplate || '';
    list.innerHTML = recent.map((n) => {
        const href = n.url || bell.dataset.indexUrl;
        const readUrl = template ? template.replace('__ID__', encodeURIComponent(n.id)) : '';
        const readAttr = !n.read && readUrl ? ` data-notif-link data-read-url="${escapeHtml(readUrl)}"` : '';
        return `<a href="${escapeHtml(href)}" class="rail-bell__item ${n.read ? '' : 'is-unread'}"${readAttr}>
            <span class="rail-bell__item-title">${escapeHtml(n.title)}</span>
            <span class="rail-bell__item-body">${escapeHtml(n.body)}</span>
            <span class="rail-bell__item-time">${escapeHtml(n.created_at)}</span>
        </a>`;
    }).join('');
}

function renderBadge(bell, unread) {
    const badge = bell.querySelector('[data-notif-badge]');
    if (!badge) return;

    if (unread > 0) {
        badge.textContent = unread > 99 ? '99+' : String(unread);
        badge.hidden = false;
    } else {
        badge.hidden = true;
    }
}

async function refresh(bell) {
    if (document.hidden) return;
    try {
        const response = await fetch(bell.dataset.summaryUrl, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (!response.ok) return;
        const payload = await response.json();
        renderBadge(bell, payload.unread || 0);
        renderList(bell, payload.recent || []);
    } catch (error) {
        // Silent: transient network errors shouldn't spam the console.
    }
}

// Fire a mark-read POST that survives page navigation (keepalive).
function markReadBeacon(url) {
    if (!url) return;
    try {
        fetch(url, {
            method: 'POST',
            keepalive: true,
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
        }).catch(() => {});
    } catch (error) {
        // ignore
    }
}

function decrementBadge() {
    const badge = document.querySelector('[data-notif-badge]');
    if (!badge || badge.hidden) return;
    const next = Math.max(0, Number.parseInt(badge.textContent, 10) - 1 || 0);
    if (next > 0) {
        badge.textContent = String(next);
    } else {
        badge.hidden = true;
    }
}

// Delegated click handler: mark a single notification read when its link is
// clicked, then let normal navigation proceed. Attached once per page load.
function handleNotifClick(event) {
    const link = event.target.closest('a[data-notif-link]');
    if (!link) return;

    const url = link.dataset.readUrl;
    if (!url || link.dataset.notifReadSent === 'true') return;

    link.dataset.notifReadSent = 'true';
    markReadBeacon(url);
    link.classList.remove('is-unread');
    link.querySelector('.notif-dot')?.remove();
    decrementBadge();
}

async function markAllRead(bell) {
    try {
        await fetch(bell.dataset.readAllUrl, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
    } catch (error) {
        // ignore
    }
    refresh(bell);
}

export function initNotificationBell() {
    // Delegated per-item mark-read: bind once to the document (covers both the
    // header bell dropdown and the notifications index page cards).
    if (!window.notifClickBound) {
        window.notifClickBound = true;
        document.addEventListener('click', handleNotifClick);
    }

    const bell = document.querySelector('[data-notif-bell]');
    if (!bell || bell.dataset.notifInitialized === 'true') return;
    bell.dataset.notifInitialized = 'true';

    bell.querySelector('[data-notif-mark-all]')?.addEventListener('click', (e) => {
        e.preventDefault();
        markAllRead(bell);
    });

    refresh(bell);
    const intervalId = window.setInterval(() => refresh(bell), POLL_INTERVAL);

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) refresh(bell);
    });

    window.addEventListener('beforeunload', () => window.clearInterval(intervalId));
}
