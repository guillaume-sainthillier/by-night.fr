/**
 * The search page of a query, from the URL the header gives (data-search-page-url), which may already carry the
 * city of the page: "/recherche/?city=toulouse" leads to "/recherche/?city=toulouse&q=jazz".
 *
 * @param {string} searchPageUrl
 * @param {string} query
 * @returns {string}
 */
export function searchPageHref(searchPageUrl, query) {
    const url = new URL(searchPageUrl, 'https://by-night.fr')
    url.searchParams.set('q', query)

    return `${url.pathname}${url.search}`
}
