/**
 * Tests for the dropzone listener
 * Run: pnpm test
 */

import { describe, expect, test } from 'vitest'
import { accepts, formatSize } from '@/js/listeners/dropzone'

describe('accepts', () => {
    test.each([
        [{ name: 'saison.csv', type: 'text/csv' }, true],
        [{ name: 'SAISON.XLSX', type: '' }, true],
        // A CSV saved by Excel on Windows: the browser gives it Excel's type
        [{ name: 'saison.csv', type: 'application/vnd.ms-excel' }, true],
        [{ name: 'affiche.pdf', type: 'application/pdf' }, false],
    ])('takes %j for a spreadsheet field: %s', (file, expected) => {
        expect(accepts(file, ['.csv', '.xlsx'])).toBe(expected)
    })

    test('takes the types of a family', () => {
        expect(accepts({ name: 'affiche.webp', type: 'image/webp' }, ['image/*'])).toBe(true)
        expect(accepts({ name: 'saison.csv', type: 'text/csv' }, ['image/*'])).toBe(false)
    })
})

describe('formatSize', () => {
    test.each([
        [120, '1 Ko'],
        [850_000, '850 Ko'],
        [1_400_000, '1,4 Mo'],
    ])('writes %d bytes as %s', (size, expected) => {
        expect(formatSize(size)).toBe(expected)
    })
})
