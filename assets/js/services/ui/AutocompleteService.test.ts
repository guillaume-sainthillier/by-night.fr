/**
 * Tests for the autocomplete service
 * Run: pnpm test
 */

import type AutoComplete from '@tarekraafat/autocomplete.js'
import { beforeEach, describe, expect, type Mock, test, vi } from 'vitest'
import { create } from '@/js/services/ui/AutocompleteService'
import { fake } from '@/js/test/fake'

type Config = ConstructorParameters<typeof AutoComplete>[0]

// The library needs a DOM: its constructor is mocked to capture the configuration it gets
const { autoComplete } = vi.hoisted(() => ({
    autoComplete: vi.fn(function (this: { config: Config; input: { value: string } }, config: Config) {
        this.config = config
        this.input = { value: '' }
    }),
}))

vi.mock('@tarekraafat/autocomplete.js', () => ({ default: autoComplete }))

function select(value: unknown) {
    const { config } = autoComplete.mock.instances[0]
    config.events?.input?.selection?.(
        fake<CustomEvent<{ selection: { index: number; value: unknown } }>>({
            detail: { selection: { index: 0, value } },
        })
    )
}

describe('AutocompleteService', () => {
    const element = { setAttribute: vi.fn(), addEventListener: vi.fn() }
    const valueInput = { value: '' }
    let onSelection: Mock<(selection: object) => void>

    beforeEach(() => {
        autoComplete.mockClear()
        valueInput.value = ''
        onSelection = vi.fn()
        create({
            element: fake<HTMLInputElement>(element),
            valueInput: fake<HTMLInputElement>(valueInput),
            url: '/api/cities?q=__QUERY__',
            onSelection,
        })
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
