/**
 * Tests for the calendar leaf lines
 * Run: yarn test
 */

import { describe, expect, test } from 'vitest'
import { leafLines } from '@/js/utils/dateLeaf'

describe('leafLines', () => {
    test('gives the month, the day and the weekday of the date, as the Twig component does', () => {
        expect(leafLines('2026-03-27')).toEqual(['mars', '27', 'Vendredi'])
    })

    test('drops the dot of an abbreviated month, like the trim of the Twig component', () => {
        expect(leafLines('2026-09-26')).toEqual(['sept', '26', 'Samedi'])
    })

    test('gives a dash while the row has no date', () => {
        expect(leafLines('')).toEqual(['', '–', ''])
        expect(leafLines('not-a-date')).toEqual(['', '–', ''])
    })
})
