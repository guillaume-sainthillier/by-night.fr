import { describe, expect, it } from 'vitest'
import { searchPageHref } from './searchPage'

describe('searchPageHref', () => {
    it('adds the query to the search page', () => {
        expect(searchPageHref(null, 'jazz')).toBe('/recherche/?q=jazz')
    })

    it('keeps the city of the page', () => {
        expect(searchPageHref('toulouse', 'marché de noël')).toBe(
            '/recherche/?city=toulouse&q=march%C3%A9%20de%20no%C3%ABl'
        )
    })
})
