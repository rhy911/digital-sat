/**
 * Live screen-size guard. The initial load-time check lives inline in
 * layouts/test.blade.php (must run before paint); this handles the case where
 * the viewport shrinks below the same threshold mid-session — e.g. a laptop
 * window resized down or a tablet rotated to a narrower orientation. Blocks
 * interaction visually only; does not touch timer/attempt state.
 */
export function initializeScreenSizeGuard() {
  const guard = document.getElementById('screenSizeGuard');
  if (!guard) return;

  const threshold = window.SCREEN_SIZE_THRESHOLD ?? 768;
  let resizeTimeout = null;

  function evaluate() {
    const minDimension = Math.min(window.innerWidth, window.innerHeight);
    guard.classList.toggle('hidden', minDimension >= threshold);
  }

  function onResize() {
    clearTimeout(resizeTimeout);
    resizeTimeout = setTimeout(evaluate, 150);
  }

  window.addEventListener('resize', onResize);
  window.addEventListener('orientationchange', onResize);
  evaluate();
}
