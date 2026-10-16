/**
 * Tests for the bulk-actions listener
 * Run: pnpm test
 */

import { describe, expect, test } from 'vitest'
import bulkActions, { selectionLabel } from '@/js/listeners/bulk-actions'

describe('bulk actions', () => {
    test('targets the bulk forms', () => {
        expect(bulkActions.selector).toBe('form[data-bulk-form]')
    })

    test.each([
        [1, '1 événement sélectionné'],
        [3, '3 événements sélectionnés'],
        [1200, '1 200 événements sélectionnés'],
    ])('words %d picked events', (count, expected) => {
        expect(selectionLabel(count)).toBe(expected)
    })
})
