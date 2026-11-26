import { path } from '@/js/router'

/**
 * The search page of a query, with the city of the page when it has one: "/recherche/?city=toulouse&q=jazz".
 */
export function searchPageHref(city: string | null, query: string): string {
    return path('app_search_index', { _query: { city, q: query } })
}
