/**
 * Phone off-canvas nav drawer for the classroom app-shell (icon-rail + list
 * column). CSS handles the slide via .rail-open on .app-shell — this just
 * toggles that class and closes on scrim click / Escape.
 */
export function initMobileClassroomNav() {
  document.querySelectorAll('[data-mobile-nav-toggle]').forEach((toggle) => {
    const shell = toggle.closest('.app-shell, .app-shell--no-list');
    if (!shell) return;

    const scrim = shell.querySelector('[data-mobile-nav-scrim]');
    const closeBtns = shell.querySelectorAll('[data-mobile-nav-close]');

    function close() {
      shell.classList.remove('rail-open');
      toggle.setAttribute('aria-expanded', 'false');
      toggle.setAttribute('aria-label', 'Open menu');
    }

    toggle.addEventListener('click', () => {
      const open = shell.classList.toggle('rail-open');
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      toggle.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
    });

    scrim?.addEventListener('click', close);
    closeBtns.forEach((btn) => btn.addEventListener('click', close));

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') close();
    });
  });
}
