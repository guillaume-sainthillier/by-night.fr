/**
 * Tests for the active-chip listener
 * Run: pnpm test
 */

import { connectListenerWithoutCleanup } from '@silarhi/app-js/testing'
import { describe, expect, test } from 'vitest'
import activeChip from '@/js/listeners/active-chip'
import { fake } from '@/js/test/fake'

const connect = ({
    scrollWidth,
    active,
}: {
    scrollWidth: number
    active: { offsetLeft: number; offsetWidth: number } | null
}) => {
    const row = {
        scrollWidth,
        clientWidth: 358,
        offsetLeft: 16,
        scrollLeft: 0,
        querySelector: () => active,
    }
    // Scrolled once: nothing to undo
    connectListenerWithoutCleanup(activeChip, fake<HTMLElement>(row))

    return row
}

describe('active-chip listener', () => {
    test('scrolls the row to its active chip', () => {
        const row = connect({ scrollWidth: 1024, active: { offsetLeft: 900, offsetWidth: 100 } })

        expect(row.scrollLeft).toBe(834)
    })

    test('leaves a row that fits as it is', () => {
        const row = connect({ scrollWidth: 358, active: { offsetLeft: 200, offsetWidth: 100 } })

        expect(row.scrollLeft).toBe(0)
    })

    test('leaves the start of a row whose first chip is active', () => {
        const row = connect({ scrollWidth: 1024, active: { offsetLeft: 20, offsetWidth: 120 } })

        expect(row.scrollLeft).toBe(0)
    })
})
