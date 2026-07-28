/**
 * Live screen-size guard. The initial load-time check lives inline in
 * layouts/test.blade.php (must run before paint); this handles the case where
 * the viewport shrinks below the same thresholds mid-session — e.g. a laptop
 * window resized down or a tablet rotated to a narrower orientation. Blocks
 * interaction visually only; does not touch timer/attempt state.
 */
export function initializeScreenSizeGuard() {
  const guard = document.getElementById('screenSizeGuard');
  if (!guard) return;

  const minWidth = window.SCREEN_MIN_WIDTH ?? 900;
  const minHeight = window.SCREEN_MIN_HEIGHT ?? 500;
  let resizeTimeout = null;

  function evaluate() {
    const tooSmall = window.innerWidth < minWidth || window.innerHeight < minHeight;
    guard.classList.toggle('hidden', !tooSmall);
  }

  function onResize() {
    clearTimeout(resizeTimeout);
    resizeTimeout = setTimeout(evaluate, 150);
  }

  window.addEventListener('resize', onResize);
  window.addEventListener('orientationchange', onResize);
  evaluate();
}
