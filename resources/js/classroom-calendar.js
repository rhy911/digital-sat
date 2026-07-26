// Client-side month grid for the class calendar. Consumes a JSON blob of items
// ({date, type, title, time, location, url}) embedded by the blade component and
// merges class events with assignment open/due dates. No server round-trips for
// month navigation.

const TYPE_LABEL = { event: 'Event', due: 'Due', opens: 'Opens' };
const WEEK_DAYS = 7;

function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = value ?? '';
    return div.innerHTML;
}

function ymd(year, month, day) {
    return `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
}

function groupByDate(items) {
    const map = new Map();
    items.forEach((item) => {
        if (!map.has(item.date)) map.set(item.date, []);
        map.get(item.date).push(item);
    });
    return map;
}

function renderDayDetail(detailEl, dateKey, dayItems) {
    if (!dayItems || dayItems.length === 0) {
        detailEl.innerHTML = '<p class="cal-day-empty">Nothing scheduled for this day.</p>';
        return;
    }

    const heading = new Date(`${dateKey}T00:00:00`).toLocaleDateString(undefined, {
        weekday: 'long', month: 'long', day: 'numeric',
    });

    const rows = dayItems.map((item) => {
        const label = TYPE_LABEL[item.type] || 'Event';
        const time = item.time ? `<span class="cal-item-time">${escapeHtml(item.time)}</span>` : '';
        const loc = item.location ? ` · ${escapeHtml(item.location)}` : '';
        const title = item.url
            ? `<a href="${escapeHtml(item.url)}" class="cal-item-title">${escapeHtml(item.title)}</a>`
            : `<span class="cal-item-title">${escapeHtml(item.title)}</span>`;
        return `<div class="cal-item cal-item--${escapeHtml(item.type)}">
            <span class="cal-item-tag">${escapeHtml(label)}</span>
            <span class="cal-item-main">${title}${loc}</span>
            ${time}
        </div>`;
    }).join('');

    detailEl.innerHTML = `<div class="cal-day-head">${escapeHtml(heading)}</div>${rows}`;
}

function initCalendar(root) {
    if (root.dataset.calInit === 'true') return;
    root.dataset.calInit = 'true';

    let items = [];
    try {
        items = JSON.parse(root.dataset.items || '[]');
    } catch (error) {
        items = [];
    }

    const byDate = groupByDate(items);
    const grid = root.querySelector('[data-cal-grid]');
    const titleEl = root.querySelector('[data-cal-title]');
    const detailEl = root.querySelector('[data-cal-day]');
    if (!grid || !titleEl || !detailEl) return;

    const today = new Date();
    const todayKey = ymd(today.getFullYear(), today.getMonth(), today.getDate());
    let viewYear = today.getFullYear();
    let viewMonth = today.getMonth();
    let selectedKey = null;

    function selectDay(dateKey) {
        selectedKey = dateKey;
        grid.querySelectorAll('.cal-cell.is-selected').forEach((c) => c.classList.remove('is-selected'));
        grid.querySelector(`[data-date="${dateKey}"]`)?.classList.add('is-selected');
        renderDayDetail(detailEl, dateKey, byDate.get(dateKey));
    }

    function render() {
        titleEl.textContent = new Date(viewYear, viewMonth, 1)
            .toLocaleDateString(undefined, { month: 'long', year: 'numeric' });

        const firstWeekday = new Date(viewYear, viewMonth, 1).getDay();
        const daysInMonth = new Date(viewYear, viewMonth + 1, 0).getDate();

        let cells = '';
        for (let i = 0; i < firstWeekday; i += 1) {
            cells += '<div class="cal-cell is-empty"></div>';
        }

        for (let day = 1; day <= daysInMonth; day += 1) {
            const key = ymd(viewYear, viewMonth, day);
            const dayItems = byDate.get(key) || [];
            const types = [...new Set(dayItems.map((it) => it.type))];
            const dots = types.map((t) => `<span class="cal-dot cal-dot--${t}"></span>`).join('');
            const classes = ['cal-cell'];
            if (key === todayKey) classes.push('is-today');
            if (dayItems.length) classes.push('has-items');
            if (key === selectedKey) classes.push('is-selected');
            cells += `<div class="${classes.join(' ')}" data-date="${key}" role="button" tabindex="0">
                <span class="cal-daynum">${day}</span>
                <span class="cal-dots">${dots}</span>
            </div>`;
        }

        const trailing = (firstWeekday + daysInMonth) % WEEK_DAYS;
        if (trailing) {
            for (let i = trailing; i < WEEK_DAYS; i += 1) {
                cells += '<div class="cal-cell is-empty"></div>';
            }
        }

        grid.innerHTML = cells;
    }

    grid.addEventListener('click', (e) => {
        const cell = e.target.closest('.cal-cell[data-date]');
        if (cell) selectDay(cell.dataset.date);
    });
    grid.addEventListener('keydown', (e) => {
        if (e.key !== 'Enter' && e.key !== ' ') return;
        const cell = e.target.closest('.cal-cell[data-date]');
        if (cell) {
            e.preventDefault();
            selectDay(cell.dataset.date);
        }
    });

    root.querySelector('[data-cal-prev]')?.addEventListener('click', () => {
        viewMonth -= 1;
        if (viewMonth < 0) { viewMonth = 11; viewYear -= 1; }
        render();
    });
    root.querySelector('[data-cal-next]')?.addEventListener('click', () => {
        viewMonth += 1;
        if (viewMonth > 11) { viewMonth = 0; viewYear += 1; }
        render();
    });
    root.querySelector('[data-cal-today]')?.addEventListener('click', () => {
        viewYear = today.getFullYear();
        viewMonth = today.getMonth();
        render();
        selectDay(todayKey);
    });

    render();
    // Preselect today so the detail panel isn't empty on open.
    if (byDate.has(todayKey)) selectDay(todayKey);
}

export function initClassroomCalendars() {
    document.querySelectorAll('[data-class-calendar]').forEach(initCalendar);
}
