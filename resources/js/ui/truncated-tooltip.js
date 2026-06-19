const TRUNCATED_TEXT_SELECTOR = [
    '.truncate',
    '.text-truncate',
    '.sd-tabs-bar-title',
    '.sd-domain-tag',
    '.test-title',
    '.section-name-sm',
    '.stem-column',
    '.tabulator-col-content',
    '.tabulator-cell',
].join(',');

const TOOLTIP_ID = 'truncated-text-tooltip';
const VIEWPORT_GAP = 8;
const TOOLTIP_GAP = 7;

let tooltip;
let activeTarget;

function getTooltip() {
    if (tooltip) return tooltip;

    tooltip = document.createElement('div');
    tooltip.id = TOOLTIP_ID;
    tooltip.className = 'truncated-text-tooltip';
    tooltip.setAttribute('role', 'tooltip');
    tooltip.hidden = true;
    document.body.appendChild(tooltip);

    return tooltip;
}

function getTooltipText(target) {
    const title = target.getAttribute('title')?.trim();
    if (title) {
        target.dataset.truncatedTooltipText = title;
        target.removeAttribute('title');
    }

    return target.dataset.truncatedTooltipText?.trim() || target.textContent?.trim() || '';
}

function isTruncated(target, fullText) {
    if (target.matches('.tabulator-col-content')) return true;

    const visibleText = target.textContent?.trim() || '';
    const contentDiffers = fullText !== visibleText;
    const contentOverflows = target.scrollWidth > target.clientWidth + 1
        || target.scrollHeight > target.clientHeight + 1;

    return contentDiffers || contentOverflows;
}

function positionTooltip(target, tooltipElement) {
    const targetRect = target.getBoundingClientRect();
    const tooltipRect = tooltipElement.getBoundingClientRect();
    const maxLeft = window.innerWidth - tooltipRect.width - VIEWPORT_GAP;
    const centeredLeft = targetRect.left + ((targetRect.width - tooltipRect.width) / 2);
    const left = Math.min(Math.max(centeredLeft, VIEWPORT_GAP), Math.max(maxLeft, VIEWPORT_GAP));
    const top = targetRect.top >= tooltipRect.height + TOOLTIP_GAP + VIEWPORT_GAP
        ? targetRect.top - tooltipRect.height - TOOLTIP_GAP
        : targetRect.bottom + TOOLTIP_GAP;

    tooltipElement.style.left = `${Math.round(left)}px`;
    tooltipElement.style.top = `${Math.round(top)}px`;
}

function showTooltip(target) {
    const fullText = getTooltipText(target);
    if (!fullText || !isTruncated(target, fullText)) {
        hideTooltip();
        return;
    }

    const tooltipElement = getTooltip();
    activeTarget = target;
    tooltipElement.textContent = fullText;
    tooltipElement.hidden = false;
    const describedBy = new Set((target.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean));
    describedBy.add(TOOLTIP_ID);
    target.setAttribute('aria-describedby', Array.from(describedBy).join(' '));
    positionTooltip(target, tooltipElement);

    requestAnimationFrame(() => tooltipElement.classList.add('is-visible'));
}

function hideTooltip() {
    if (!tooltip) return;

    if (activeTarget) {
        const describedBy = (activeTarget.getAttribute('aria-describedby') || '')
            .split(/\s+/)
            .filter(id => id && id !== TOOLTIP_ID);

        if (describedBy.length) {
            activeTarget.setAttribute('aria-describedby', describedBy.join(' '));
        } else {
            activeTarget.removeAttribute('aria-describedby');
        }
    }
    activeTarget = null;
    tooltip.classList.remove('is-visible');
    tooltip.hidden = true;
}

function findTarget(eventTarget) {
    return eventTarget instanceof Element
        ? eventTarget.closest(TRUNCATED_TEXT_SELECTOR)
        : null;
}

export function initTruncatedTooltips() {
    document.addEventListener('mouseover', event => {
        const target = findTarget(event.target);
        if (!target || target === activeTarget) return;
        showTooltip(target);
    }, true);

    document.addEventListener('mouseout', event => {
        if (!activeTarget || activeTarget.contains(event.relatedTarget)) return;
        hideTooltip();
    }, true);

    document.addEventListener('focusin', event => {
        const target = findTarget(event.target);
        if (target) showTooltip(target);
    });

    document.addEventListener('focusout', event => {
        if (activeTarget?.contains(event.target)) hideTooltip();
    });

    window.addEventListener('resize', hideTooltip);
    window.addEventListener('scroll', hideTooltip, true);
}
