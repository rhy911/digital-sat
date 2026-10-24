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
    const switcher = shell.querySelector('[data-drawer-switcher]');
    const hasSwitcher = Boolean(switcher && sidebar);
    let activePanel = 'nav';

    function setDrawerPanel(panel) {
      if (!hasSwitcher) return;
      activePanel = panel;
      if (switcher) {
        switcher.querySelectorAll('[data-drawer-tab]').forEach((tab) => {
          const isActive = tab.getAttribute('data-drawer-tab') === activePanel;
          tab.classList.toggle('active', isActive);
          tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });
      }
      syncState();
      requestAnimationFrame(() => {
        const activeContainer = activePanel === 'list' && sidebar ? sidebar : rail;
        const target = activeContainer?.querySelector('[data-mobile-nav-close], input, a, button');
        target?.focus();
      });
    }

    if (hasSwitcher) {
      switcher.querySelectorAll('[data-drawer-tab]').forEach((tab) => {
        tab.addEventListener('click', () => {
          setDrawerPanel(tab.getAttribute('data-drawer-tab') || 'nav');
        });
      });
    }

    function syncState() {
      const isMobile = mql.matches;
      const isOpen = shell.classList.contains('rail-open');

      if (!isMobile) {
        if (rail) {
          rail.inert = false;
          rail.style.transform = '';
        }
        if (sidebar) {
          sidebar.inert = false;
          sidebar.style.transform = '';
        }
        if (mainContent) mainContent.inert = false;
        if (switcher) switcher.hidden = true;
        return;
      }

      if (switcher) {
        switcher.hidden = !hasSwitcher || !isOpen;
      }

      if (isOpen) {
        if (hasSwitcher) {
          if (activePanel === 'nav') {
            if (rail) {
              rail.inert = false;
              rail.style.transform = 'translateX(0)';
            }
            if (sidebar) {
              sidebar.inert = true;
              sidebar.style.transform = 'translateX(-100%)';
            }
          } else {
            if (rail) {
              rail.inert = true;
              rail.style.transform = 'translateX(-100%)';
            }
            if (sidebar) {
              sidebar.inert = false;
              sidebar.style.transform = 'translateX(0)';
            }
          }
        } else {
          if (rail) {
            rail.inert = false;
            rail.style.transform = '';
          }
          if (sidebar) {
            sidebar.inert = false;
            sidebar.style.transform = '';
          }
        }
        if (mainContent) mainContent.inert = true;
      } else {
        if (rail) {
          rail.inert = true;
          rail.style.transform = '';
        }
        if (sidebar) {
          sidebar.inert = true;
          sidebar.style.transform = '';
        }
        if (mainContent) mainContent.inert = false;
      }
    }

    function open() {
      shell.classList.add('rail-open');
      toggle.setAttribute('aria-expanded', 'true');
      toggle.setAttribute('aria-label', 'Close menu');
      if (hasSwitcher) {
        setDrawerPanel('nav');
        requestAnimationFrame(() => {
          switcher.querySelector('[data-drawer-tab].active')?.focus();
        });
      } else {
        activePanel = 'nav';
        syncState();
        requestAnimationFrame(() => {
          rail?.querySelector('a, button')?.focus();
        });
      }
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
        const focusable = [];
        if (toggle && toggle.offsetParent !== null) {
          focusable.push(toggle);
        }
        if (hasSwitcher && switcher && !switcher.hidden) {
          focusable.push(...switcher.querySelectorAll('button:not([disabled])'));
        }
        const activeContainer = hasSwitcher
          ? (activePanel === 'list' && sidebar ? sidebar : rail)
          : null;

        if (activeContainer) {
          focusable.push(...activeContainer.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'));
        } else {
          if (rail) {
            focusable.push(...rail.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'));
          }
          if (sidebar) {
            focusable.push(...sidebar.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'));
          }
        }

        const visibleFocusable = focusable.filter((el, i, self) => self.indexOf(el) === i && !el.disabled && el.offsetParent !== null);
        if (visibleFocusable.length === 0) return;

        const first = visibleFocusable[0];
        const last = visibleFocusable[visibleFocusable.length - 1];

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
