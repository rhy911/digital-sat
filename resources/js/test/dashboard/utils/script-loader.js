const loadedStyles = new Set();
const loadingScripts = new Map();

export function loadStyle(href) {
    if (loadedStyles.has(href) || document.querySelector(`link[href="${href}"]`)) return;
    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = href;
    document.head.appendChild(link);
    loadedStyles.add(href);
}

export function loadScript(src) {
    if (loadingScripts.has(src)) return loadingScripts.get(src);
    if (document.querySelector(`script[src="${src}"]`)) {
        return Promise.resolve();
    }

    const promise = new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = src;
        script.onload = () => resolve();
        script.onerror = () => reject(new Error(`Failed to load script ${src}`));
        document.body.appendChild(script);
    });

    loadingScripts.set(src, promise);
    return promise;
}

export async function loadHeavyDependencies() {
    // Load CSS
    loadStyle('https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css');
    loadStyle('https://cdn.jsdelivr.net/npm/easymde/dist/easymde.min.css');
    loadStyle('https://cdn.jsdelivr.net/npm/tabulator-tables@5.5.2/dist/css/tabulator_bootstrap5.min.css');
    loadStyle('https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/katex.min.css');

    // Load independent JS
    const tomSelectP = loadScript('https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js');
    const tabulatorP = loadScript('https://cdn.jsdelivr.net/npm/tabulator-tables@5.5.2/dist/js/tabulator.min.js');
    const markedP = loadScript('https://cdn.jsdelivr.net/npm/marked@12.0.0/marked.min.js');

    // Load dependent JS
    const katexP = loadScript('https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/katex.min.js')
        .then(() => loadScript('https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/contrib/auto-render.min.js'));

    const easymdeP = markedP.then(() => loadScript('https://cdn.jsdelivr.net/npm/easymde/dist/easymde.min.js'));

    await Promise.all([tomSelectP, tabulatorP, katexP, easymdeP]);
}
