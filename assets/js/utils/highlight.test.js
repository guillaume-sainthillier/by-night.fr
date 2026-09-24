import { describe, expect, it } from 'vitest'
import { highlightMatch, splitHighlights } from './highlight'

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

describe('highlightMatch', () => {
    it('marks the matched part whatever its case', () => {
        expect(highlightMatch('toul', 'Toulouse')).toBe('<mark>Toul</mark>ouse')
        expect(highlightMatch('LOUSE', 'Toulouse')).toBe('Tou<mark>louse</mark>')
    })

    it('escapes the record around and inside the match', () => {
        expect(highlightMatch('rock', 'Rock & <b>Roll</b>')).toBe('<mark>Rock</mark> &amp; &lt;b&gt;Roll&lt;/b&gt;')
        expect(highlightMatch('<img', 'x<img src=x onerror=alert(1)>')).toBe(
            'x<mark>&lt;img</mark> src=x onerror=alert(1)&gt;'
        )
    })

    it('returns undefined when the record does not match', () => {
        expect(highlightMatch('paris', 'Toulouse')).toBeUndefined()
        expect(highlightMatch('paris', null)).toBeUndefined()
    })

    it('escapes without marking when highlighting is off', () => {
        expect(highlightMatch('l', "L'été", false)).toBe('L&#39;été')
    })
})
