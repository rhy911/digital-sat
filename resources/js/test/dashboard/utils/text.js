export function compileMarkdownToHtml(text) {
    if (!text) return '';
    try {
        // Fix loose formatting like "** bold **" which standard marked.js ignores
        text = text.replace(/\*\*\s*([^*]+?)\s*\*\*/g, '**$1**');

        if (window.marked) {
            const markedOptions = { breaks: true, gfm: true };
            if (typeof window.marked.parse === 'function') {
                return window.marked.parse(text, markedOptions);
            } else if (typeof window.marked === 'function') {
                if (window.marked.setOptions) window.marked.setOptions(markedOptions);
                return window.marked(text);
            }
        }
        if (window.EasyMDE && typeof window.EasyMDE.prototype.markdown === 'function') {
            return window.EasyMDE.prototype.markdown(text);
        }
    } catch (e) {
        console.error('Markdown compile failed', e);
    }
    return text.replace(/\n/g, '<br>');
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

export function processMedia(text) {
    if (!text || typeof text !== 'string') return text;
    // Convert backslash delimiters to $$ for consistent preview rendering
    text = text.replace(/\\\(/g, '$$').replace(/\\\)/g, '$$');
    text = text.replace(/!\[([^\]]*)\]\(([^)]+)\)/g, (match, alt, url) => {
        return `![${alt}](${normalizeQuestionMediaUrl(url)})`;
    });
    return text.replace(/(?<!\!)\[Media:([^\]]+)\]/gi, (match, filename) => {
        const safeFilename = filename.trim();
        if (!/^[A-Za-z0-9]{20}\.(jpe?g|png|gif|webp|svg)$/i.test(safeFilename)) {
            return match;
        }

        return `<img src="/media/${safeFilename}" alt="${safeFilename}" class="question-media img-fluid mb-2 d-block mx-auto" style="max-height: 300px;">`;
    });
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
