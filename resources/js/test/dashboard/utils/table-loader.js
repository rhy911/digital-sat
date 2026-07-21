/**
 * Display a hardware-accelerated translucent loading overlay native to the specific table container.
 * @param {string} containerId - The DOM ID of the table container element.
 */
export function showTableLoader(containerId) {
    const container = document.getElementById(containerId);
    if (!container) return;

    // Target the inner table wrapper to only cover the table content and remain under the title row
    const tableWrapper = container.querySelector('.overflow-x-auto') || container;

    let overlay = tableWrapper.querySelector('.table-loading-overlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.className = 'table-loading-overlay';
        overlay.innerHTML = `
            <div class="table-loading-spinner-wrapper">
                <div class="table-loading-spinner"></div>
                <div class="table-loading-text">Loading Data</div>
            </div>
        `;
        tableWrapper.appendChild(overlay);
    }

    // Ensure the tableWrapper is relatively positioned to anchor absolute overlay
    tableWrapper.classList.add('relative');

    // Force a browser reflow to trigger CSS transition correctly
    overlay.getBoundingClientRect();

    overlay.classList.add('show');
}

/**
 * Hide the translucent loading overlay from the specific table container with a smooth fade-out.
 * @param {string} containerId - The DOM ID of the table container element.
 */
export function hideTableLoader(containerId) {
    const container = document.getElementById(containerId);
    if (!container) return;

    const tableWrapper = container.querySelector('.overflow-x-auto') || container;
    const overlay = tableWrapper.querySelector('.table-loading-overlay');
    if (overlay) {
        overlay.classList.remove('show');
        // Remove from DOM after CSS transition completes to free layout memory
        setTimeout(() => {
            if (!overlay.classList.contains('show')) {
                overlay.remove();
            }
        }, 300);
    }
}
