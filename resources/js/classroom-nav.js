/**
 * Phone off-canvas nav drawer for the classroom app-shell (icon-rail + list
 * column). CSS handles the slide via .rail-open on .app-shell — this just
 * toggles that class and closes on scrim click / Escape.
 */
export function initMobileClassroomNav() {
  const mql = window.matchMedia('(max-width: 640px)');

  document.querySelectorAll('[data-mobile-nav-toggle]').forEach((toggle) => {
    const shell = toggle.closest('.app-shell, .app-shell--no-list');
    if (!shell) return;

    const rail = shell.querySelector('.icon-rail');
    const sidebar = shell.querySelector('.sidebar-col, .class-col');
    const mainContent = shell.querySelector('.shell-content, .ledger-pane');
    const scrim = shell.querySelector('[data-mobile-nav-scrim]');
    const closeBtns = shell.querySelectorAll('[data-mobile-nav-close]');

    function syncState() {
      const isMobile = mql.matches;
      const isOpen = shell.classList.contains('rail-open');

      if (!isMobile) {
        if (rail) rail.inert = false;
        if (sidebar) sidebar.inert = false;
        if (mainContent) mainContent.inert = false;
        return;
      }

      if (isOpen) {
        if (rail) rail.inert = false;
        if (sidebar) sidebar.inert = false;
        if (mainContent) mainContent.inert = true;
      } else {
        if (rail) rail.inert = true;
        if (sidebar) sidebar.inert = true;
        if (mainContent) mainContent.inert = false;
      }
    }

    function open() {
      shell.classList.add('rail-open');
      toggle.setAttribute('aria-expanded', 'true');
      toggle.setAttribute('aria-label', 'Close menu');
      syncState();
      requestAnimationFrame(() => {
        const target = sidebar?.querySelector('[data-mobile-nav-close], input, a, button') || rail?.querySelector('a');
        target?.focus();
      });
    }

    function close(restoreFocus = true) {
      if (!shell.classList.contains('rail-open')) return;
      shell.classList.remove('rail-open');
      toggle.setAttribute('aria-expanded', 'false');
      toggle.setAttribute('aria-label', 'Open menu');
      syncState();
      if (restoreFocus && mql.matches) {
        toggle.focus();
      }
    }

    toggle.addEventListener('click', () => {
      if (shell.classList.contains('rail-open')) {
        close();
      } else {
        open();
      }
    });

    scrim?.addEventListener('click', () => close());
    closeBtns.forEach((btn) => btn.addEventListener('click', () => close()));

    document.addEventListener('keydown', (e) => {
      if (!shell.classList.contains('rail-open') || !mql.matches) return;
      if (e.key === 'Escape') {
        close();
        return;
      }
      if (e.key === 'Tab') {
        const container = [rail, sidebar].filter(Boolean);
        const focusable = container.flatMap((el) => Array.from(el.querySelectorAll('a[href], button:not([disabled]), input:not([disabled])')));
        if (focusable.length === 0) return;
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (e.shiftKey && document.activeElement === first) {
          e.preventDefault();
          last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
          e.preventDefault();
          first.focus();
        }
      }
    });

    mql.addEventListener('change', () => {
      if (!mql.matches) {
        shell.classList.remove('rail-open');
        toggle.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-label', 'Open menu');
      }
      syncState();
    });

    syncState();
  });
}
