import { describe, expect, it } from 'vitest'
import { splitHighlights } from './highlight'

describe('splitHighlights', () => {
    it('returns a plain value as one segment', () => {
        expect(splitHighlights('Le Bikini')).toEqual([{ text: 'Le Bikini', highlighted: false }])
    })

    it('marks the highlighted parts', () => {
        expect(
            splitHighlights('Le __aa-highlight__Bikini__/aa-highlight__ à __aa-highlight__Toulouse__/aa-highlight__')
        ).toEqual([
            { text: 'Le ', highlighted: false },
            { text: 'Bikini', highlighted: true },
            { text: ' à ', highlighted: false },
            { text: 'Toulouse', highlighted: true },
        ])
    })

    it('keeps markup as text', () => {
        expect(splitHighlights('__aa-highlight__Concert__/aa-highlight__ <img src=x onerror=alert(1)>')).toEqual([
            { text: 'Concert', highlighted: true },
            { text: ' <img src=x onerror=alert(1)>', highlighted: false },
        ])
    })

    it('returns nothing for an empty value', () => {
        expect(splitHighlights('')).toEqual([])
    })
})
