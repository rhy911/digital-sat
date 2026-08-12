import { BASE_URL, dashboardJsonResponse } from '../core/config.js';

const renderedFieldCache = new Map();
const RENDERED_FIELD_CACHE_LIMIT = 200;

/**
 * Render question-content fields on the server, through the exact pipeline the
 * test engine uses (QuestionContentRenderer).
 *
 * Rendering in the browser used to mean a second, subtly different markdown
 * implementation: `marked` with `breaks: true` here, league/commonmark with
 * `breaks: false` in the engine, and no markdown at all in the import preview.
 * They disagreed on every LaTeX backslash escape, so a preview could look right
 * while the live test looked wrong. One request per preview update keeps a
 * single source of truth.
 *
 * @param {Record<string, string>} fields
 * @returns {Promise<Record<string, string>>} field name -> sanitized HTML
 */
export async function renderQuestionFields(fields) {
    const entries = Object.entries(fields || {})
        .filter(([, value]) => typeof value === 'string' && value.trim() !== '');

    if (!entries.length) return {};

    const payload = Object.fromEntries(entries);
    const cacheKey = JSON.stringify(payload);
    if (renderedFieldCache.has(cacheKey)) {
        return { ...renderedFieldCache.get(cacheKey) };
    }

    try {
        const response = await fetch(`${BASE_URL || '/admin'}/questions/render-preview`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            },
            body: JSON.stringify({ fields: payload }),
        });

        const data = await dashboardJsonResponse(response, 'POST');
        const rendered = data?.data?.fields || {};

        if (renderedFieldCache.size >= RENDERED_FIELD_CACHE_LIMIT) {
            renderedFieldCache.clear();
        }
        renderedFieldCache.set(cacheKey, rendered);

        return { ...rendered };
    } catch (error) {
        console.error('Question preview render failed', error);

        // Never leave the author staring at a blank preview: fall back to the
        // raw text, escaped, so the content is at least readable.
        return Object.fromEntries(entries.map(([key, value]) => [key, `<p>${escapeHtml(value)}</p>`]));
    }
}

export function normalizeQuestionMediaUrl(url) {
    if (!url || typeof url !== 'string') return url;

    const value = url.trim();
    const mediaPathPrefix = '/storage/media/';
    const targetPathPrefix = '/media/';

    if (value.startsWith(targetPathPrefix)) {
        return value;
    }

    const toMediaUrl = (pathname) => {
        if (!pathname.startsWith(mediaPathPrefix)) return null;

        const filename = pathname.slice(mediaPathPrefix.length).split(/[?#]/)[0];
        if (!/^[A-Za-z0-9]{20}\.(jpe?g|png|gif|webp|svg)$/i.test(filename)) {
            return null;
        }

        return `${targetPathPrefix}${filename}`;
    };

    const relativeMediaUrl = toMediaUrl(value);
    if (relativeMediaUrl) {
        return relativeMediaUrl;
    }

    try {
        const parsed = new URL(value, window.location.origin);
        const isAppMediaHost = parsed.origin === window.location.origin || parsed.hostname === 'dsat.bkse.vn';
        const absoluteMediaUrl = toMediaUrl(parsed.pathname);

        if (isAppMediaHost && absoluteMediaUrl) {
            return absoluteMediaUrl;
        }
    } catch (e) {
        return value;
    }

    return value;
}

export function escapeHtml(str) {
    if (str == null) {
        return '';
    }
    const d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
}

export function stripTags(html) {
    return String(html).replace(/<[^>]*>/g, '');
}

export function capitalizeFirstLetter(string) {
    if (!string) return '';
    return string.charAt(0).toUpperCase() + string.slice(1);
}

export function humanizeUnderscores(value) {
    if (!value) {
        return '';
    }
    return value.split('_').map(function (w) {
        return w.charAt(0).toUpperCase() + w.slice(1);
    }).join(' ');
}

/**
 * Format date string to DD/MM/YY (e.g. 29/05/26) safely and without timezone shifts.
 * @param {string} dateStr - Date string from database (Y-m-d H:i:s or ISO)
 * @returns {string} Formatted date
 */
export function formatDateToShort(dateStr) {
    if (!dateStr) return 'N/A';
    const parts = dateStr.split(/[\sT]/);
    if (parts[0]) {
        const dateParts = parts[0].split('-');
        if (dateParts.length === 3) {
            const year = dateParts[0].slice(-2);
            const month = dateParts[1];
            const day = dateParts[2];
            return `${day}/${month}/${year}`;
        }
    }
    const date = new Date(dateStr);
    if (isNaN(date.getTime())) return dateStr;
    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = String(date.getFullYear()).slice(-2);
    return `${day}/${month}/${year}`;
}
