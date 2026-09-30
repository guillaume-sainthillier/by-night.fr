/**
 * Tests for the autocomplete service
 * Run: yarn test
 */

import { beforeEach, describe, expect, test, vi } from 'vitest'
import { create } from '@/js/services/ui/AutocompleteService'

// The library needs a DOM: its constructor is mocked to capture the configuration it gets
const { autoComplete } = vi.hoisted(() => ({
    autoComplete: vi.fn(function (config) {
        this.config = config
        this.input = { value: '' }
    }),
}))

vi.mock('@tarekraafat/autocomplete.js', () => ({ default: autoComplete }))

function select(value) {
    const { config } = autoComplete.mock.instances[0]
    config.events.input.selection({ detail: { selection: { index: 0, value } } })
}

describe('AutocompleteService', () => {
    const element = { setAttribute: vi.fn(), addEventListener: vi.fn() }
    const valueInput = { value: '' }
    let onSelection

    beforeEach(() => {
        autoComplete.mockClear()
        valueInput.value = ''
        onSelection = vi.fn()
        create({ element, valueInput, url: '/api/cities?q=__QUERY__', onSelection })
    })

    test('fills both inputs with the selected result', () => {
        select({ name: 'Toulouse', slug: 'toulouse' })

        expect(autoComplete.mock.instances[0].input.value).toBe('Toulouse')
        expect(valueInput.value).toBe('toulouse')
        expect(onSelection).toHaveBeenCalledExactlyOnceWith({ name: 'Toulouse', slug: 'toulouse' })
    })

    // The "no result" and error messages are list items too: the library selects them as an empty result
    test('ignores the selection of the no result message', () => {
        expect(() => select(undefined)).not.toThrow()

        expect(valueInput.value).toBe('')
        expect(onSelection).not.toHaveBeenCalled()
    })
})
