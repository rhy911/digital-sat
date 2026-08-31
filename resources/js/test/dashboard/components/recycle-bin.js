import { RECYCLE_BIN_CLEAR_URL, RECYCLE_BIN_DELETE_URL_TEMPLATE, RECYCLE_BIN_RESTORE_URL_TEMPLATE, RECYCLE_BIN_URL } from '../core/config.js';
import { showAlert, showCustomConfirm } from '../utils/custom-alert.js';
import { escapeHtml } from '../utils/text.js';
import { icon } from '../../../shared/icons.js';

const typeLabels = {
    test: 'Practice test',
    section: 'Section',
    module: 'Module',
    question: 'Question bank item',
};

const typeIcons = {
    test: 'journal-text',
    section: 'folder2-open',
    module: 'box-seam',
    question: 'database',
};

function restoreUrl(type, id) {
    return (RECYCLE_BIN_RESTORE_URL_TEMPLATE || `/admin/recycle-bin/${encodeURIComponent(type)}/${encodeURIComponent(id)}/restore`)
        .replace('__TYPE__', encodeURIComponent(type))
        .replace('__ID__', encodeURIComponent(id));
}

function deleteUrl(type, id) {
    return (RECYCLE_BIN_DELETE_URL_TEMPLATE || `/admin/recycle-bin/${encodeURIComponent(type)}/${encodeURIComponent(id)}`)
        .replace('__TYPE__', encodeURIComponent(type))
        .replace('__ID__', encodeURIComponent(id));
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content || '';
}

async function responseData(response, fallbackMessage) {
    const data = await response.json().catch(() => ({}));
    if (!response.ok) {
        const error = new Error(data.message || fallbackMessage);
        error.payload = data;
        throw error;
    }
    return data;
}

function referenceDetails(references = []) {
    return references
        .map(reference => {
            const items = Array.isArray(reference.items) && reference.items.length
                ? ` [${reference.items.join(', ')}]`
                : '';
            return `${reference.type} (${reference.count})${items}: ${reference.action}`;
        })
        .join('\n');
}

function showBlockerDetails(items = []) {
    const notice = document.getElementById('recycleBinBlockers');
    const details = document.getElementById('recycleBinBlockerDetails');
    if (!notice || !details) return;
    if (!items.length) {
        notice.classList.add('hidden');
        details.textContent = '';
        return;
    }

    const visibleItems = items.slice(0, 6).map(item => {
        const references = referenceDetails(item.references || []);
        return `${item.title || `${item.type} #${item.id}`}: ${references || item.message}`;
    });
    if (items.length > visibleItems.length) {
        visibleItems.push(`…and ${items.length - visibleItems.length} more item(s).`);
    }
    details.textContent = visibleItems.join('\n');
    notice.classList.remove('hidden');
}

function formatDate(value) {
    if (!value) return 'Unknown date';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return 'Unknown date';
    return new Intl.DateTimeFormat(undefined, { day: 'numeric', month: 'short', year: 'numeric' }).format(date);
}

function retentionLabel(daysLeft) {
    if (daysLeft <= 0) return 'Expires soon';
    if (daysLeft === 1) return '1 day left';
    return `${daysLeft} days left`;
}

function renderItem(item) {
    const blocked = Boolean(item.parent_deleted);
    const type = typeLabels[item.type] || 'Deleted item';
    const daysClass = item.days_left <= 1 ? 'is-urgent' : '';
    const restoreAction = blocked
        ? `<button type="button" class="recycle-bin-action is-disabled" disabled title="Restore the parent test first">Restore parent first</button>`
        : `<button type="button" class="recycle-bin-action recycle-bin-restore" data-type="${escapeHtml(item.type)}" data-id="${escapeHtml(item.id)}">${icon('arrow-clockwise', 'h-3.5 w-3.5')} Restore</button>`;
    const deleteAction = `<button type="button" class="recycle-bin-action recycle-bin-delete" data-type="${escapeHtml(item.type)}" data-id="${escapeHtml(item.id)}" title="Delete permanently">${icon('trash', 'h-3.5 w-3.5')} Delete forever</button>`;

    return `<article class="recycle-bin-row">
        <div class="recycle-bin-item-icon recycle-bin-item-icon-${escapeHtml(item.type)}">${icon(typeIcons[item.type] || 'trash', 'h-5 w-5')}</div>
        <div class="recycle-bin-item-main">
            <div class="recycle-bin-item-heading">
                <h4 title="${escapeHtml(item.title)}">${escapeHtml(item.title)}</h4>
                <span class="recycle-bin-type">${escapeHtml(type)}</span>
            </div>
            <p>${escapeHtml(item.subtitle || type)} · Deleted ${escapeHtml(formatDate(item.deleted_at))}</p>
        </div>
        <div class="recycle-bin-expiry ${daysClass}">
            <span>${icon('clock', 'h-3.5 w-3.5')} ${escapeHtml(retentionLabel(item.days_left))}</span>
            <small>Until ${escapeHtml(formatDate(item.expires_at))}</small>
        </div>
        <div class="recycle-bin-row-action"><div class="recycle-bin-row-actions">${restoreAction}${deleteAction}</div></div>
    </article>`;
}

function setVisibility({ loading = false, empty = false, error = false, list = false }) {
    document.getElementById('recycleBinLoading')?.classList.toggle('hidden', !loading);
    document.getElementById('recycleBinEmpty')?.classList.toggle('hidden', !empty);
    document.getElementById('recycleBinError')?.classList.toggle('hidden', !error);
    document.getElementById('recycleBinList')?.classList.toggle('hidden', !list);
}

function renderItems(items) {
    const list = document.getElementById('recycleBinList');
    const count = document.getElementById('recycleBinCount');
    const clearAll = document.getElementById('recycleBinClearAll');
    if (!list || !count) return;

    count.textContent = `${items.length} item${items.length === 1 ? '' : 's'} in recovery`;
    if (clearAll) clearAll.disabled = items.length === 0;
    if (!items.length) {
        list.innerHTML = '';
        setVisibility({ empty: true });
        return;
    }

    list.innerHTML = items.map(renderItem).join('');
    setVisibility({ list: true });
}

export async function refreshRecycleBin() {
    const count = document.getElementById('recycleBinCount');
    const clearAll = document.getElementById('recycleBinClearAll');
    if (!count) return;
    count.textContent = 'Loading…';
    if (clearAll) clearAll.disabled = true;
    setVisibility({ loading: true });

    try {
        const response = await fetch(RECYCLE_BIN_URL, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        const data = await responseData(response, 'Recycle bin request failed.');
        renderItems(Array.isArray(data.data) ? data.data : []);
    } catch (error) {
        count.textContent = 'Could not load items';
        setVisibility({ error: true });
    }
}

export function initRecycleBin() {
    const refreshButton = document.getElementById('recycleBinRefresh');
    const clearAllButton = document.getElementById('recycleBinClearAll');
    if (!refreshButton || refreshButton.dataset.bound === '1') return;
    refreshButton.dataset.bound = '1';
    refreshButton.addEventListener('click', refreshRecycleBin);

    clearAllButton?.addEventListener('click', async () => {
        if (clearAllButton.disabled) return;
        const confirmed = await showCustomConfirm(
            'Permanently delete every item currently in the recycle bin? This cannot be undone. Shared questions and student history will be kept.',
            'error',
            'Empty recycle bin',
        );
        if (!confirmed) return;

        clearAllButton.disabled = true;
        clearAllButton.classList.add('is-loading');
        try {
            const response = await fetch(RECYCLE_BIN_CLEAR_URL || '/admin/recycle-bin', {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                credentials: 'same-origin',
            });
            const data = await responseData(response, 'Could not empty the recycle bin.');
            const skipped = Number(data.data?.skipped || 0);
            showBlockerDetails(skipped ? (data.data?.skipped_items || []) : []);
            showAlert(skipped ? 'warning' : 'success', data.message || 'Recycle bin emptied.');
            await refreshRecycleBin();
        } catch (error) {
            clearAllButton.disabled = false;
            showAlert('danger', error.message || 'Could not empty the recycle bin.');
        } finally {
            clearAllButton.classList.remove('is-loading');
        }
    });

    document.getElementById('recycleBinList')?.addEventListener('click', async event => {
        const button = event.target.closest('.recycle-bin-restore');
        const deleteButton = event.target.closest('.recycle-bin-delete');
        if (deleteButton && !deleteButton.disabled) {
            await permanentlyDelete(deleteButton);
            return;
        }
        if (!button || button.disabled) return;

        const type = button.dataset.type;
        const id = button.dataset.id;
        if (!await showCustomConfirm('Restore this item and return it to the active library?', 'info', 'Restore item')) return;

        button.disabled = true;
        button.classList.add('is-loading');
        try {
            const response = await fetch(restoreUrl(type, id), {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                credentials: 'same-origin',
            });
            const data = await responseData(response, 'Restore failed.');
            showAlert('success', data.message || 'Item restored successfully.');
            if (window.refreshTestDashboardData) {
                await window.refreshTestDashboardData();
            } else {
                await refreshRecycleBin();
            }
        } catch (error) {
            button.disabled = false;
            button.classList.remove('is-loading');
            showAlert('danger', error.message || 'Restore failed.');
        }
    });

}

async function permanentlyDelete(button) {
    const type = button.dataset.type;
    const id = button.dataset.id;
    const itemLabel = type === 'test' ? 'this test and its deleted child content' : 'this item';
    if (!await showCustomConfirm(`Permanently delete ${itemLabel}? This cannot be undone. Shared questions and student history will be kept.`, 'error', 'Delete forever')) return;

    button.disabled = true;
    button.classList.add('is-loading');
    try {
        const response = await fetch(deleteUrl(type, id), {
            method: 'DELETE',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
            },
            credentials: 'same-origin',
        });
        const data = await responseData(response, 'Permanent deletion failed.');
        showAlert('success', data.message || 'Item permanently deleted.');
        await refreshRecycleBin();
    } catch (error) {
        button.disabled = false;
        button.classList.remove('is-loading');
        const references = referenceDetails(error.payload?.data?.references || []);
        showAlert('warning', [error.message || 'Permanent deletion failed.', references].filter(Boolean).join('\n'));
    }
}
