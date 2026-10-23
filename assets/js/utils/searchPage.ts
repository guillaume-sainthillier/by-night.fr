/**
 * The search page of a query, from the URL the header gives (data-search-page-url), which may already carry the
 * city of the page: "/recherche/?city=toulouse" leads to "/recherche/?city=toulouse&q=jazz".
 */
export function searchPageHref(searchPageUrl: string, query: string): string {
    const url = new URL(searchPageUrl, window.location.href)
    url.searchParams.set('q', query)

    return `${url.pathname}${url.search}`
}
