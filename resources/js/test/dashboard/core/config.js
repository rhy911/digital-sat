export const config = window.TestDashboardConfig || {};

/**
 * Skill taxonomy, served from config/sat_taxonomy.php via the Blade layout.
 *
 * It used to be duplicated here by hand, which is how the importer, the builder
 * and the teacher guide ended up disagreeing about which skills exist. The
 * columns feed report grouping directly, so an unknown value does not error —
 * it becomes a one-question "skill" and ruins weak-area reporting.
 *
 * @type {Record<string, Record<string, string[]>>}
 */
export const SKILL_TAXONOMY = config.SKILL_TAXONOMY || {};

function toLabel(value) {
    return String(value)
        .split('_')
        .map(word => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
}

/** @type {Record<string, Array<{value: string, label: string}>>} */
export const SKILL_DOMAINS = Object.fromEntries(
    Object.entries(SKILL_TAXONOMY).map(([sectionType, domains]) => [
        sectionType,
        Object.keys(domains).map(domain => ({ value: domain, label: toLabel(domain) })),
    ])
);

/**
 * Subdomains allowed for a domain, in taxonomy order.
 *
 * @param {string} domain
 * @returns {Array<{value: string, label: string}>}
 */
export function skillSubdomainsFor(domain) {
    for (const domains of Object.values(SKILL_TAXONOMY)) {
        if (domains[domain]) {
            return domains[domain].map(subdomain => ({ value: subdomain, label: toLabel(subdomain) }));
        }
    }

    return [];
}
export const {
    SNAPSHOT_URL,
    QUESTIONS_LIST_URL,
    QUESTIONS_SEARCH_URL,
    CSV_BULK_URL,
    BULK_PREVIEW_URL,
    CSV_BULK_PREVIEW_URL,
    QUESTIONS_PER_PAGE = 30,
    BULK_STORE_URL,
    MEDIA_UPLOAD_URL,
    TESTS_STORE_URL,
    SECTIONS_STORE_URL,
    MODULES_STORE_URL,
    QUESTIONS_ATTACH_URL,
    TEACHERS_SEARCH_URL,
    TEST_UPDATE_URL_TEMPLATE,
    SECTION_UPDATE_URL_TEMPLATE,
    MODULE_UPDATE_URL_TEMPLATE,
    QUESTION_UPDATE_URL_TEMPLATE,
    BASE_URL,
} = config;

export const TEST_DASHBOARD_TAB_KEY = 'testDashboardActiveTab';

export function dashboardResourceUrl(template, resource, id) {
    const encodedId = encodeURIComponent(String(id));
    if (template && template.includes('__ID__')) {
        return template.replace('__ID__', encodedId);
    }

    const baseUrl = BASE_URL || '/admin';
    return `${baseUrl}/${resource}/${encodedId}`;
}

export async function dashboardRequestErrorMessage(response, method = 'GET') {
    const contentType = response.headers.get('content-type') || '';
    if (contentType.includes('application/json')) {
        try {
            const data = await response.json();
            if (data?.errors) return Object.values(data.errors).flat().join(' ');
            if (data?.message) return data.message;
        } catch (error) {
            // Fall through to generic message when the response advertises JSON but is malformed.
        }
    }

    const url = response.url
        ? new URL(response.url, window.location.origin).pathname
        : 'request';
    return `Request failed (${response.status}): ${method} ${url}`;
}

export async function dashboardJsonResponse(response, method = 'GET') {
    if (!response.ok) {
        throw new Error(await dashboardRequestErrorMessage(response, method));
    }

    const contentType = response.headers.get('content-type') || '';
    if (contentType.includes('application/json')) {
        return response.json();
    }

    return {};
}

// Shared state that was previously on window
if (typeof window.__tdQuestionsPage === 'undefined') {
    window.__tdQuestionsPage = 1;
}
if (typeof window.__tdQuestionsPerPage === 'undefined') {
    window.__tdQuestionsPerPage = QUESTIONS_PER_PAGE;
}
if (typeof window.__tdQuestionsQuery === 'undefined') {
    window.__tdQuestionsQuery = '';
}
if (typeof window.__tdLatestTests === 'undefined') {
    window.__tdLatestTests = window.__tdTestsData || [];
}
