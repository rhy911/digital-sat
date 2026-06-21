const COMPACT_KEY = 'testDashboardSidebarCompact';

document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.getElementById('testDashboardSidebar');
    const toggle = sidebar?.querySelector('[data-sidebar-toggle]');
    const desktop = window.matchMedia('(min-width: 1024px)');
    if (!sidebar || !toggle) return;

    const saved = localStorage.getItem(COMPACT_KEY);
    let compact = saved === null ? window.innerWidth < 1280 : saved === 'true';

    function render() {
        sidebar.classList.toggle('is-compact', desktop.matches && compact);
        const action = compact ? 'Expand sidebar' : 'Compact sidebar';
        toggle.setAttribute('aria-label', action);
        toggle.setAttribute('title', action);
        toggle.setAttribute('aria-expanded', String(!compact));
    }

    toggle.addEventListener('click', () => {
        if (!desktop.matches) return;
        compact = !compact;
        localStorage.setItem(COMPACT_KEY, String(compact));
        render();
    });
    desktop.addEventListener('change', render);
    render();
});
