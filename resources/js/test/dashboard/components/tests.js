import {
    BASE_URL,
    TEACHERS_SEARCH_URL,
    TEST_UPDATE_URL_TEMPLATE,
    dashboardJsonResponse,
    dashboardResourceUrl,
} from '../core/config.js';
import { showAlert, escapeHtml, humanizeUnderscores, showTableLoader, hideTableLoader, formatDateToShort } from '../utils/helpers.js';

let localAllTests = [];
let currentFilteredTests = [];
let currentTestsRenderId = 0;
let activeSharingTest = null;
let selectedShareTeacher = null;
let shareSearchTimeout = null;

function testTypeLabel(type) {
    const labels = {
        full_length: 'Normal Full Test',
        adaptive_full_length: 'Adaptive Full Test',
        short_test: 'Short Test',
        module_only: 'Single Module',
        section_only: 'Section Only',
        custom_test: 'Custom Test'
    };

    return labels[type] || humanizeUnderscores(type);
}

if (typeof window.__tdTestsPage === 'undefined') {
    window.__tdTestsPage = 1;
}
if (typeof window.__tdTestsPerPage === 'undefined') {
    window.__tdTestsPerPage = 30;
}

function testUpdateUrl(testId) {
    return dashboardResourceUrl(TEST_UPDATE_URL_TEMPLATE, 'tests', testId);
}

function renderTestRowHtml(t) {
    const isOwner = t.is_owner;
    const isShared = t.is_shared;
    const creatorName = t.created_by_name || 'Admin';
    const createdByHtml = `<span class="text-xs font-medium text-slate-600 truncate max-w-[110px] block" title="${escapeHtml(creatorName)}">${escapeHtml(creatorName)}</span>`;
    const sharedBadge = t.shares_count > 0 && isOwner
        ? `<span class="ml-2 rounded-full bg-indigo-50 px-2 py-0.5 text-[10px] font-bold text-indigo-700 ring-1 ring-inset ring-indigo-100">${escapeHtml(t.shares_count)} shared</span>`
        : '';
    const accessBadge = isShared
        ? `<span class="ml-2 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-600 ring-1 ring-inset ring-slate-200">Shared with you</span>`
        : '';

    // Title input/span
    const titleHtml = isOwner
        ? `<input type="text" class="test-title-input w-full bg-transparent border-0 hover:bg-slate-100 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:outline-none rounded-lg px-2 py-1 font-semibold text-slate-800 transition-all" value="${escapeHtml(t.title)}" data-id="${t.id}">`
        : `<span class="px-2 py-1 font-semibold text-slate-800">${escapeHtml(t.title)}${accessBadge}</span>`;

    // Public toggle checkbox
    const publicHtml = isOwner
        ? `<div class="flex items-center justify-center"><input type="checkbox" data-id="${t.id}" class="w-4 h-4 text-indigo-600 border-slate-300 bg-white rounded cursor-pointer test-public-checkbox" ${t.is_public ? 'checked' : ''} title="${t.is_public ? 'Public (Click to make Private)' : 'Private (Click to make Public)'}" aria-label="Toggle public visibility"></div>`
        : `<div class="flex items-center justify-center"><input type="checkbox" checked disabled class="w-4 h-4 text-slate-400 border-slate-200 bg-slate-100 rounded cursor-not-allowed opacity-60" title="Shared (View only)" aria-label="Shared resource"></div>`;

    let chipClass = 'status-chip status-chip-readonly';
    if (t.status === 'active') chipClass = 'status-chip status-chip-active';
    if (t.status === 'draft') chipClass = 'status-chip status-chip-draft';
    if (t.status === 'archived') chipClass = 'status-chip status-chip-archived';
    const statusHtml = `<div class="flex items-center justify-center w-full h-full"><span class="${chipClass}">${escapeHtml(humanizeUnderscores(t.status))}</span></div>`;

    // Actions dropdown
    const actionsHtml = isOwner
        ? `<div class="actions-dropdown">
            <button type="button" class="px-2.5 py-1.5 text-xs font-bold rounded-lg border border-slate-200 bg-white text-slate-700 cursor-pointer hover:bg-slate-50 flex items-center gap-1" data-dropdown-trigger="true" aria-expanded="false" aria-label="Toggle actions menu">
                Actions <i class="bi bi-chevron-down text-[10px]"></i>
            </button>
            <div class="dropdown-menu hidden">
                ${t.status !== 'active' ? `<button type="button" class="dropdown-item change-test-status-btn" data-id="${t.id}" data-status="active"><i class="bi bi-send-check mr-2"></i> Publish</button>` : ''}
                ${t.status !== 'draft' ? `<button type="button" class="dropdown-item change-test-status-btn" data-id="${t.id}" data-status="draft"><i class="bi bi-pencil mr-2"></i> Return to draft</button>` : ''}
                ${t.status !== 'archived' ? `<button type="button" class="dropdown-item change-test-status-btn" data-id="${t.id}" data-status="archived"><i class="bi bi-archive mr-2"></i> Archive</button>` : ''}
                ${t.raw_type === 'full_length' ? `<button type="button" class="dropdown-item score-conversion-btn" data-id="${t.id}" data-title="${escapeHtml(t.title)}"><i class="bi bi-graph-up-arrow mr-2"></i> Score conversion</button>` : ''}
                ${t.can_convert_to_normal ? `<button type="button" class="dropdown-item convert-to-normal-btn" data-id="${t.id}"><i class="bi bi-arrow-left-right mr-2"></i> Convert to Normal Full</button>` : ''}
                <button type="button" class="dropdown-item manage-test-sharing-btn" data-id="${t.id}"><i class="bi bi-people mr-2"></i> Manage sharing${sharedBadge}</button>
                <button type="button" class="dropdown-item clone-test-btn" data-id="${t.id}"><i class="bi bi-copy mr-2"></i> Clone</button>
                <button type="button" class="dropdown-item text-danger delete-test-btn" data-id="${t.id}"><i class="bi bi-trash mr-2"></i> Delete</button>
            </div>
          </div>`
        : isShared
            ? `<div class="actions-dropdown">
                <button type="button" class="px-2.5 py-1.5 text-xs font-bold rounded-lg border border-slate-200 bg-white text-slate-700 cursor-pointer hover:bg-slate-50 flex items-center gap-1" data-dropdown-trigger="true" aria-expanded="false" aria-label="Toggle actions menu">
                    Actions <i class="bi bi-chevron-down text-[10px]"></i>
                </button>
                <div class="dropdown-menu hidden">
                    <button type="button" class="dropdown-item clone-test-btn" data-id="${t.id}"><i class="bi bi-copy mr-2"></i> Clone</button>
                    ${t.status === 'active' ? `<a class="dropdown-item" href="/teacher/classes"><i class="bi bi-send mr-2"></i> Assign in class</a>` : ''}
                </div>
              </div>`
            : `<span class="status-chip status-chip-readonly">Read-Only</span>`;

    const rowClass = isOwner ? '' : 'row-shared';

    return `<tr class="${rowClass}">
        <td class="font-semibold text-slate-400 text-center">${escapeHtml(t.id)}</td>
        <td>${titleHtml}</td>
        <td>${escapeHtml(t.type)}</td>
        <td class="text-center font-semibold text-slate-500">${escapeHtml(t.created_at || 'N/A')}</td>
        <td>${createdByHtml}</td>
        <td class="text-center">${publicHtml}</td>
        <td class="text-center">${statusHtml}</td>
        <td class="font-bold text-slate-700 text-center">${escapeHtml(t.duration)}<span class="text-[10px] ml-0.5 opacity-50 uppercase tracking-tighter">min</span></td>
        <td class="text-center">${actionsHtml}</td>
    </tr>`;
}

function _renderTestsChunked(tbody, items, emptyHtml) {
    currentTestsRenderId++;
    const renderId = currentTestsRenderId;
    
    if (!items.length) {
        tbody.innerHTML = emptyHtml;
        return;
    }
    
    tbody.innerHTML = '';
    let index = 0;
    const chunkSize = 20;
    
    function renderChunk() {
        if (renderId !== currentTestsRenderId) return;
        
        const chunk = items.slice(index, index + chunkSize);
        if (!chunk.length) return;
        
        tbody.insertAdjacentHTML('beforeend', chunk.map(renderTestRowHtml).join(''));
        index += chunkSize;
        
        if (index < items.length) {
            requestAnimationFrame(() => setTimeout(renderChunk, 0));
        }
    }
    
    renderChunk();
}

function renderTestsPage() {
    const tbody = document.getElementById('testsTableBody');
    if (!tbody) return;

    const page = window.__tdTestsPage || 1;
    const perPage = window.__tdTestsPerPage || 30;
    
    const start = (page - 1) * perPage;
    const end = page * perPage;
    const sliced = currentFilteredTests.slice(start, end);

    const emptyHtml = '<tr>'
        + '<td colspan="9" class="px-6 py-20 text-center">'
        + '<div class="flex flex-col items-center justify-center">'
        + '<div class="w-20 h-20 rounded-full bg-slate-50 border border-slate-200 flex items-center justify-center mb-6">'
        + '<i class="bi bi-inbox text-4xl text-slate-400"></i>'
        + '</div>'
        + '<h4 class="text-lg font-bold text-slate-800">No tests found</h4>'
        + '<p class="text-sm text-slate-500 mt-1 max-w-xs mx-auto">Create one practice test to get started.</p>'
        + '</div>'
        + '</td>'
        + '</tr>';

    _renderTestsChunked(tbody, sliced, emptyHtml);
    renderTestsPagination(currentFilteredTests.length);
}

export function renderTestsPagination(total) {
    const wrap = document.getElementById('testsPoolPagination');
    if (!wrap) return;
    
    if (total === 0) {
        wrap.innerHTML = '';
        return;
    }
    
    const cur = window.__tdTestsPage || 1;
    const perPage = window.__tdTestsPerPage || 30;
    const last = Math.ceil(total / perPage) || 1;
    
    let html = '<div class="flex flex-wrap justify-between items-center w-full gap-3 px-2">';
    html += '<span class="text-xs font-semibold text-slate-600">Page ' + cur + ' of ' + last + ' <span class="mx-1 text-slate-300">•</span> ' + total + ' tests</span>';
    html += '<div class="flex gap-2">';
    html += '<button type="button" class="min-h-9 px-3 py-1.5 text-xs font-semibold rounded-lg border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 hover:border-slate-300 transition-colors duration-150 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer" data-t-page="prev"' + (cur <= 1 ? ' disabled' : '') + '>Previous</button>';
    html += '<button type="button" class="min-h-9 px-3 py-1.5 text-xs font-semibold rounded-lg border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 hover:border-slate-300 transition-colors duration-150 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer" data-t-page="next"' + (cur >= last ? ' disabled' : '') + '>Next</button>';
    html += '</div></div>';
    wrap.innerHTML = html;
    
    if (wrap.dataset.bound !== '1') {
        wrap.dataset.bound = '1';
        wrap.addEventListener('click', function (e) {
            const btn = e.target.closest('[data-t-page]');
            if (!btn || btn.disabled) return;
            
            const dir = btn.getAttribute('data-t-page');
            const curPage = window.__tdTestsPage || 1;
            
            showTableLoader('testsTableContainer');
            
            setTimeout(() => {
                if (dir === 'prev') {
                    window.__tdTestsPage = Math.max(1, curPage - 1);
                } else if (dir === 'next') {
                    window.__tdTestsPage = curPage + 1;
                }
                renderTestsPage();
                hideTableLoader('testsTableContainer');
            }, 400);
        });
    }
}

function applyTestsFilterAndSearch() {
    const searchInput = document.getElementById('testsTableSearch');
    const query = searchInput ? searchInput.value.toLowerCase().trim() : '';
    const showShared = document.getElementById('testsShowSharedToggle')?.checked;

    let filtered = localAllTests;
    if (window.__currentUserRole === 'teacher' && !showShared) {
        filtered = localAllTests.filter(t => t.is_owner);
    }

    if (query) {
        filtered = filtered.filter(t => {
            const title = (t.title || '').toLowerCase();
            const type = (t.type || '').toLowerCase();
            const id = String(t.id);
            return title.includes(query) || type.includes(query) || id.includes(query);
        });
    }

    currentFilteredTests = filtered;
}

export function renderTestsTable(tests) {
    localAllTests = tests.map(function(t) {
        const rawType = t.raw_type || t.test_type || t.type;
        const sections = Array.isArray(t.sections) ? t.sections : [];
        const hasOnePathPerSection = sections.length === 2 && sections.every(section => {
            const modules = Array.isArray(section.modules) ? section.modules : [];
            return modules.filter(module => Number(module.module_number) === 1).length === 1
                && modules.filter(module => Number(module.module_number) === 2).length === 1;
        });
        const canConvertToNormal = t.can_convert_to_normal !== undefined
            ? Boolean(t.can_convert_to_normal)
            : rawType === 'adaptive_full_length'
                && t.status === 'draft'
                && Number(t.user_tests_count || 0) === 0
                && hasOnePathPerSection;

        return {
            id: t.id,
            title: t.title,
            raw_type: rawType,
            type: testTypeLabel(rawType),
            status: t.status,
            duration: t.total_duration_minutes || t.duration || 0,
            created_by: t.created_by,
            created_by_name: t.created_by_name || (t.creator ? (t.creator.username || t.creator.email) : 'Admin'),
            created_at: formatDateToShort(t.created_at),
            is_public: t.is_public,
            is_shared: t.is_shared !== undefined
                ? Boolean(t.is_shared)
                : (window.__currentUserRole === 'teacher'
                    && t.created_by !== window.__currentUserId
                    && Array.isArray(t.shares)
                    && t.shares.some(share => Number(share.user_id) === Number(window.__currentUserId))),
            shares_count: t.shares_count !== undefined ? Number(t.shares_count) : (Array.isArray(t.shares) ? t.shares.length : 0),
            can_convert_to_normal: canConvertToNormal,
            is_owner: t.is_owner !== undefined ? t.is_owner : (t.created_by === window.__currentUserId || window.__currentUserRole === 'admin')
        };
    });
    
    const tableContainer = document.getElementById('testsTableContainer');
    const emptyState = document.getElementById('testsEmptyState');
    if (!tableContainer) return;

    applyTestsFilterAndSearch();

    if (!currentFilteredTests.length && !tests.length) {
        if (emptyState) emptyState.classList.remove('hidden');
        tableContainer.classList.add('hidden');
        return;
    }

    if (emptyState) emptyState.classList.add('hidden');
    tableContainer.classList.remove('hidden');

    window.__tdTestsPage = 1;
    renderTestsPage();
    initTestsEvents();
}

let eventsBound = false;
function initTestsEvents() {
    if (eventsBound) return;
    eventsBound = true;

    const searchInput = document.getElementById('testsTableSearch');
    if (searchInput) {
        let testsSearchTimeout = null;
        searchInput.addEventListener('input', function(e) {
            showTableLoader('testsTableContainer');
            if (testsSearchTimeout) clearTimeout(testsSearchTimeout);
            testsSearchTimeout = setTimeout(() => {
                applyTestsFilterAndSearch();
                window.__tdTestsPage = 1;
                renderTestsPage();
                setTimeout(() => {
                    hideTableLoader('testsTableContainer');
                }, 200);
            }, 400);
        });
    }

    document.getElementById('testsShowSharedToggle')?.addEventListener('change', function() {
        showTableLoader('testsTableContainer');
        setTimeout(() => {
            applyTestsFilterAndSearch();
            window.__tdTestsPage = 1;
            renderTestsPage();
            hideTableLoader('testsTableContainer');
        }, 300);
    });

    initTestSharingModal();

    const tbody = document.getElementById('testsTableBody');
    if (tbody) {
        tbody.addEventListener('click', function(e) {
            const button = e.target.closest('.score-conversion-btn');
            if (!button) return;
            const panel = document.getElementById('scoreConversionPanel');
            if (!panel) return;
            document.getElementById('scoreConversionTestId').value = button.dataset.id;
            document.getElementById('scoreConversionTitle').textContent = `Score conversion · ${button.dataset.title || `Test ${button.dataset.id}`}`;
            document.getElementById('scoreConversionApprove').disabled = true;
            document.getElementById('scoreConversionApprove').removeAttribute('data-set-id');
            document.getElementById('scoreConversionStatus').classList.add('hidden');
            panel.classList.remove('hidden');
            const behavior = window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth';
            panel.scrollIntoView({ behavior, block: 'nearest' });
            document.getElementById('scoreConversionSource').focus({ preventScroll: true });
        });

        tbody.addEventListener('click', async function(e) {
            const button = e.target.closest('.manage-test-sharing-btn');
            if (!button) return;
            const test = localAllTests.find(item => String(item.id) === String(button.dataset.id));
            if (!test) return;
            await openTestSharingModal(test);
        });

        // Toggle Public visibility
        tbody.addEventListener('change', function(e) {
            const checkbox = e.target.closest('.test-public-checkbox');
            if (!checkbox) return;
            
            const testId = checkbox.dataset.id;
            const checked = checkbox.checked;
            
            fetch(testUpdateUrl(testId), {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                body: JSON.stringify({ is_public: checked })
            }).then(res => dashboardJsonResponse(res, 'PUT')).then(res => {
                if (res.status === 'success') {
                    showAlert('success', 'Test visibility updated!');
                    const t = localAllTests.find(item => String(item.id) === String(testId));
                    if (t) t.is_public = checked;
                } else {
                    showAlert('danger', res.message || 'Failed to update visibility');
                    checkbox.checked = !checked;
                }
            }).catch(error => {
                showAlert('danger', error.message || 'Error updating test visibility');
                checkbox.checked = !checked;
            });
        });

        // Edit Title on change/blur
        tbody.addEventListener('change', function(e) {
            const input = e.target.closest('.test-title-input');
            if (!input) return;

            const testId = input.dataset.id;
            const newTitle = input.value.trim();
            if (!newTitle) {
                showAlert('danger', 'Title cannot be empty');
                input.value = input.defaultValue || '';
                return;
            }

            fetch(testUpdateUrl(testId), {
                method: 'PUT',
                headers: { 
                    'Content-Type': 'application/json', 
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                body: JSON.stringify({ title: newTitle })
            }).then(res => dashboardJsonResponse(res, 'PUT')).then(res => {
                if (res.status === 'success') {
                    showAlert('success', 'Test title updated successfully!');
                    input.defaultValue = newTitle;
                    const t = localAllTests.find(item => String(item.id) === String(testId));
                    if (t) t.title = newTitle;
                } else {
                    showAlert('danger', res.message || 'Failed to update title');
                    input.value = input.defaultValue || '';
                }
            }).catch(error => {
                showAlert('danger', error.message || 'Error updating test title');
                input.value = input.defaultValue || '';
            });
        });
    }

    initScoreConversionPanel();
}

function initTestSharingModal() {
    const modal = document.querySelector('[data-test-sharing-modal]');
    const search = document.getElementById('shareTeacherSearch');
    const addButton = document.getElementById('shareTeacherAdd');
    if (!modal || modal.dataset.bound === '1' || !search || !addButton) return;
    modal.dataset.bound = '1';

    search.addEventListener('input', () => {
        selectedShareTeacher = null;
        addButton.disabled = true;
        if (shareSearchTimeout) clearTimeout(shareSearchTimeout);
        shareSearchTimeout = setTimeout(() => searchShareTeachers(search.value), 250);
    });

    document.getElementById('shareTeacherResults')?.addEventListener('click', event => {
        const option = event.target.closest('[data-share-teacher-id]');
        if (!option) return;
        selectedShareTeacher = {
            id: option.dataset.shareTeacherId,
            name: option.dataset.shareTeacherName,
            email: option.dataset.shareTeacherEmail,
        };
        search.value = `${selectedShareTeacher.name} (${selectedShareTeacher.email})`;
        document.getElementById('shareTeacherResults').classList.add('hidden');
        addButton.disabled = false;
        search.focus();
    });

    addButton.addEventListener('click', addSharedTeacher);
}

async function openTestSharingModal(test) {
    activeSharingTest = test;
    selectedShareTeacher = null;
    document.getElementById('shareTestId').value = test.id;
    document.getElementById('testSharingSubtitle').textContent = `${test.title} can be shared with approved teachers for viewing, cloning, and assignment.`;
    document.getElementById('shareTeacherSearch').value = '';
    document.getElementById('shareTeacherAdd').disabled = true;
    document.getElementById('shareTeacherResults').classList.add('hidden');
    renderShareList([], true);
    window.dispatchEvent(new CustomEvent('open-modal', { detail: 'testSharingModal' }));
    await loadShares(test.id);
}

async function loadShares(testId) {
    try {
        const response = await fetch(`${BASE_URL}/tests/${testId}/shares`, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        });
        const data = await response.json();
        if (!response.ok) throw new Error(responseMessage(data));
        renderShareList(data.data || []);
        if (activeSharingTest) activeSharingTest.shares_count = (data.data || []).length;
    } catch (error) {
        renderShareList([]);
        showShareModalAlert('error', error.message || 'Could not load shared teachers.');
    }
}

async function searchShareTeachers(query) {
    const results = document.getElementById('shareTeacherResults');
    if (!results) return;
    if (!query || query.trim().length < 2) {
        results.classList.add('hidden');
        results.innerHTML = '';
        return;
    }

    try {
        const url = `${TEACHERS_SEARCH_URL}?q=${encodeURIComponent(query.trim())}`;
        const response = await fetch(url, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        });
        const data = await response.json();
        if (!response.ok) throw new Error(responseMessage(data));
        const teachers = data.data || [];
        results.innerHTML = teachers.length
            ? teachers.map(teacher => `<button type="button" class="test-sharing-result" data-share-teacher-id="${teacher.id}" data-share-teacher-name="${escapeAttr(teacher.name)}" data-share-teacher-email="${escapeAttr(teacher.email)}">
                <span>${escapeHtml(teacher.name)}</span>
                <small>${escapeHtml(teacher.email)}</small>
              </button>`).join('')
            : '<div class="px-3 py-3 text-sm font-semibold text-slate-600">No approved teachers found.</div>';
        results.classList.remove('hidden');
    } catch (error) {
        results.innerHTML = `<div class="px-3 py-3 text-sm font-semibold text-rose-700">${escapeHtml(error.message || 'Search failed.')}</div>`;
        results.classList.remove('hidden');
    }
}

async function addSharedTeacher() {
    if (!activeSharingTest || !selectedShareTeacher) return;
    const addButton = document.getElementById('shareTeacherAdd');
    addButton.disabled = true;

    try {
        const response = await fetch(`${BASE_URL}/tests/${activeSharingTest.id}/shares`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            credentials: 'same-origin',
            body: JSON.stringify({ teacher_id: selectedShareTeacher.id })
        });
        const data = await response.json();
        if (!response.ok) throw new Error(responseMessage(data));
        showShareModalAlert('success', data.message || 'Teacher shared.');
        document.getElementById('shareTeacherSearch').value = '';
        selectedShareTeacher = null;
        await loadShares(activeSharingTest.id);
    } catch (error) {
        showShareModalAlert('error', error.message || 'Could not share test.');
    } finally {
        addButton.disabled = true;
    }
}

function renderShareList(shares, loading = false) {
    const list = document.getElementById('shareList');
    const empty = document.getElementById('shareEmptyState');
    const badge = document.getElementById('shareCountBadge');
    if (!list || !empty || !badge) return;

    if (loading) {
        if (!list.children.length) {
            list.innerHTML = '<div class="p-4 text-sm font-semibold text-slate-600" id="shareLoadingState">Loading shared teachers...</div>';
            empty.classList.add('hidden');
        }
        badge.textContent = 'Loading';
        return;
    }

    const loadingEl = document.getElementById('shareLoadingState');
    if (loadingEl) loadingEl.remove();

    badge.textContent = `${shares.length} teacher${shares.length === 1 ? '' : 's'}`;
    
    // Animate empty state
    if (!shares.length) {
        if (empty.classList.contains('hidden')) {
            empty.classList.remove('hidden');
            empty.classList.add('opacity-0');
            empty.style.display = 'block';
            requestAnimationFrame(() => {
                empty.classList.remove('opacity-0');
                empty.classList.add('transition-opacity', 'duration-300', 'opacity-100');
            });
        }
    } else {
        if (!empty.classList.contains('hidden')) {
            empty.classList.remove('opacity-100');
            empty.classList.add('opacity-0');
            setTimeout(() => {
                empty.classList.add('hidden');
                empty.style.display = '';
            }, 300);
        }
    }

    // Diff the list
    const existingIds = new Set();
    Array.from(list.children).forEach(child => {
        const id = child.dataset.shareRowId;
        if (!id) return;
        if (!shares.find(s => String(s.user_id) === String(id))) {
            // Remove
            child.classList.remove('grid-rows-[1fr]');
            child.classList.add('grid-rows-[0fr]');
            const inner = child.firstElementChild;
            if (inner) inner.classList.add('opacity-0');
            setTimeout(() => child.remove(), 300);
        } else {
            existingIds.add(String(id));
        }
    });

    shares.forEach(share => {
        if (!existingIds.has(String(share.user_id))) {
            // Add
            const wrapper = document.createElement('div');
            wrapper.className = 'grid grid-rows-[0fr] transition-[grid-template-rows] duration-300 ease-[var(--ease-out-quart,cubic-bezier(0.25,1,0.5,1))]';
            wrapper.dataset.shareRowId = share.user_id;
            
            const inner = document.createElement('div');
            inner.className = 'overflow-hidden transition-opacity duration-300 opacity-0';
            
            inner.innerHTML = `
                <div class="test-sharing-row">
                    <div class="min-w-0">
                        <strong>${escapeHtml(share.name || share.email || 'Teacher')}</strong>
                        <span>${escapeHtml(share.email || '')}</span>
                    </div>
                    <button type="button" class="test-sharing-remove" data-share-remove-id="${share.user_id}">
                        <i class="bi bi-x-lg" aria-hidden="true"></i><span>Remove</span>
                    </button>
                </div>
            `;
            
            wrapper.appendChild(inner);
            list.appendChild(wrapper);
            
            // Setup remove handler
            const removeBtn = inner.querySelector('.test-sharing-remove');
            removeBtn.addEventListener('click', () => removeSharedTeacher(share.user_id));
            
            requestAnimationFrame(() => {
                wrapper.classList.remove('grid-rows-[0fr]');
                wrapper.classList.add('grid-rows-[1fr]');
                inner.classList.remove('opacity-0');
                inner.classList.add('opacity-100');
            });
        }
    });
}

async function removeSharedTeacher(teacherId) {
    if (!activeSharingTest || !teacherId) return;

    try {
        const response = await fetch(`${BASE_URL}/tests/${activeSharingTest.id}/shares/${teacherId}`, {
            method: 'DELETE',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            credentials: 'same-origin',
        });
        const data = await response.json();
        if (!response.ok) throw new Error(responseMessage(data));
        showShareModalAlert('success', data.message || 'Teacher removed from sharing.');
        await loadShares(activeSharingTest.id);
    } catch (error) {
        showShareModalAlert('error', error.message || 'Could not remove teacher.');
    }
}

function showShareModalAlert(type, message) {
    const alertWrapper = document.getElementById('shareAlertWrapper');
    const alertEl = document.getElementById('shareAlertContainer');
    if (!alertWrapper || !alertEl) return;
    
    alertEl.className = 'rounded-lg p-3 text-sm font-medium mb-3 opacity-0 transition-opacity duration-300';
    if (type === 'success') {
        alertEl.classList.add('bg-emerald-50', 'text-emerald-700', 'border', 'border-emerald-200');
        alertEl.innerHTML = `<i class="bi bi-check-circle-fill mr-2"></i>${escapeHtml(message)}`;
    } else {
        alertEl.classList.add('bg-rose-50', 'text-rose-700', 'border', 'border-rose-200');
        alertEl.innerHTML = `<i class="bi bi-exclamation-circle-fill mr-2"></i>${escapeHtml(message)}`;
    }
    
    // Expand height
    alertWrapper.classList.remove('grid-rows-[0fr]');
    alertWrapper.classList.add('grid-rows-[1fr]');
    
    // Fade in text slightly after height starts expanding
    requestAnimationFrame(() => {
        alertEl.classList.remove('opacity-0');
        alertEl.classList.add('opacity-100');
    });

    setTimeout(() => {
        // Fade out text
        alertEl.classList.remove('opacity-100');
        alertEl.classList.add('opacity-0');
        
        setTimeout(() => {
            // Collapse height
            alertWrapper.classList.remove('grid-rows-[1fr]');
            alertWrapper.classList.add('grid-rows-[0fr]');
        }, 300);
    }, 4000);
}

function responseMessage(data) {
    return data?.errors ? Object.values(data.errors).flat()[0] : (data?.message || 'Request failed.');
}

function escapeAttr(str) {
    return escapeHtml(str).replace(/"/g, '&quot;');
}

function initScoreConversionPanel() {
    const panel = document.getElementById('scoreConversionPanel');
    const form = document.getElementById('scoreConversionForm');
    if (!panel || !form || form.dataset.bound === '1') return;
    form.dataset.bound = '1';
    const status = document.getElementById('scoreConversionStatus');
    const importButton = document.getElementById('scoreConversionImport');
    const approveButton = document.getElementById('scoreConversionApprove');
    const setStatus = (message, error = false) => {
        status.textContent = message;
        status.classList.remove('hidden', 'bg-slate-100', 'text-slate-700', 'bg-red-50', 'text-red-800');
        status.classList.add(error ? 'bg-red-50' : 'bg-slate-100', error ? 'text-red-800' : 'text-slate-700');
    };
    const responseMessage = data => data.errors
        ? Object.values(data.errors).flat().join(' ')
        : (data.message || 'Request failed.');

    document.getElementById('scoreConversionClose')?.addEventListener('click', () => {
        panel.classList.add('hidden');
    });

    form.addEventListener('submit', async event => {
        event.preventDefault();
        let rows;
        try {
            const parsed = JSON.parse(document.getElementById('scoreConversionRows').value);
            rows = Array.isArray(parsed) ? parsed : parsed.rows;
            if (!Array.isArray(rows)) throw new Error('Expected a JSON array of conversion rows.');
        } catch (error) {
            setStatus(error.message || 'Conversion rows must be valid JSON.', true);
            return;
        }

        importButton.disabled = true;
        approveButton.disabled = true;
        setStatus('Importing and validating draft...');
        try {
            const testId = document.getElementById('scoreConversionTestId').value;
            const response = await fetch(`${BASE_URL}/tests/${testId}/score-conversions`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({
                    source_name: document.getElementById('scoreConversionSource').value,
                    source_url: document.getElementById('scoreConversionSourceUrl').value || null,
                    rows,
                }),
            });
            const data = await response.json();
            if (!response.ok) throw new Error(responseMessage(data));
            approveButton.dataset.setId = data.data.id;
            approveButton.disabled = false;
            setStatus(`Draft conversion v${data.data.version} imported. Approve to run full scoring audit.`);
        } catch (error) {
            setStatus(error.message, true);
        } finally {
            importButton.disabled = false;
        }
    });

    approveButton.addEventListener('click', async () => {
        if (!approveButton.dataset.setId) return;
        approveButton.disabled = true;
        setStatus('Running form and conversion audit...');
        try {
            const response = await fetch(`${BASE_URL}/score-conversions/${approveButton.dataset.setId}/approve`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
            });
            const data = await response.json();
            if (!response.ok) throw new Error(responseMessage(data));
            setStatus(`Conversion v${data.data.version} approved. Form is locked and eligible for estimated scores.`);
        } catch (error) {
            approveButton.disabled = false;
            setStatus(error.message, true);
        }
    });
}

export async function updateTestStatus(testId, status, refreshCallback) {
    try {
        const response = await fetch(testUpdateUrl(testId), {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            credentials: 'same-origin',
            body: JSON.stringify({ status: status })
        });

        await dashboardJsonResponse(response, 'PUT');
        showAlert('success', 'Status updated!');
        if (refreshCallback) await refreshCallback();
    } catch (error) {
        showAlert('danger', 'Error: ' + (error.message || 'Failed to update status'));
    }
}
