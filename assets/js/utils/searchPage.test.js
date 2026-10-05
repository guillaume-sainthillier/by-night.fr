import { describe, expect, it } from 'vitest'
import { searchPageHref } from './searchPage'

describe('searchPageHref', () => {
    it('adds the query to the search page', () => {
        expect(searchPageHref('/recherche/', 'jazz')).toBe('/recherche/?q=jazz')
    })

    it('keeps the city the page gives', () => {
        expect(searchPageHref('/recherche/?city=toulouse', 'marché de noël')).toBe(
            '/recherche/?city=toulouse&q=march%C3%A9+de+no%C3%ABl'
        )
    })
})
