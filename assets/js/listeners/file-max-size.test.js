/**
 * Tests for the file-max-size listener
 * Run: pnpm test
 */

import { describe, expect, test } from 'vitest'
import fileMaxSize, { oversizeMessage } from '@/js/listeners/file-max-size'

// ImageFormats::MAX_SIZE
const MAX_SIZE = 6_000_000

describe('oversizeMessage', () => {
    test('targets the file fields given a maximum', () => {
        expect(fileMaxSize.selector).toBe('input[type="file"][data-max-size]')
    })

    test.each([[undefined], [{ size: 0 }], [{ size: MAX_SIZE }]])('lets %j through', (file) => {
        expect(oversizeMessage(file, MAX_SIZE)).toBeNull()
    })

    test('refuses a heavier file, with its weight and the maximum in decimal megabytes', () => {
        // The 33 MB poster of the feedback of 13/10/2026
        expect(oversizeMessage({ size: 33_265_572 }, MAX_SIZE)).toBe(
            'Cette image pèse 33,3 Mo, le maximum est de 6 Mo. Réduisez-la ou choisissez-en une autre.'
        )
    })

    test('refuses a file one byte over the maximum', () => {
        expect(oversizeMessage({ size: MAX_SIZE + 1 }, MAX_SIZE)).not.toBeNull()
    })

    test.each([[0], [Number.NaN]])('lets everything through without a usable maximum (%s)', (maxSize) => {
        expect(oversizeMessage({ size: 33_265_572 }, maxSize)).toBeNull()
    })
})
