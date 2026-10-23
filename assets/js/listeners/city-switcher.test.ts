/**
 * Tests for the city-switcher listener
 * Run: pnpm test
 */

import { afterEach, describe, expect, test, vi } from 'vitest'
import citySwitcher from '@/js/listeners/city-switcher'
import { fake } from '@/js/test/fake'

const { createAutocomplete } = vi.hoisted(() => ({ createAutocomplete: vi.fn() }))

vi.mock('@/js/services/ui/AutocompleteService', () => ({ create: createAutocomplete }))

// An element recording the listeners added to it
type Handler = () => unknown

const element = <P extends object>(properties: P) => {
    const listeners: Record<string, Handler | undefined> = {}
    return {
        ...properties,
        listeners,
        addEventListener: (type: string, listener: Handler) => {
            listeners[type] = listener
        },
        removeEventListener: (type: string, listener: Handler) => {
            if (listeners[type] === listener) {
                delete listeners[type]
            }
        },
    }
}

const connect = () => {
    const dropdown = element({})
    const input = element({
        dataset: { citiesUrl: '/api/cities?q=__QUERY__', locationUrl: '/__LOCATION__' },
        closest: (selector: string) => (selector === '.dropdown' ? dropdown : null),
        focus: vi.fn(),
    })
    const cleanup = citySwitcher.connect(fake<HTMLInputElement>(input))

    return { input, dropdown, cleanup }
}

afterEach(() => {
    vi.unstubAllGlobals()
    createAutocomplete.mockReset()
})

describe('city-switcher listener', () => {
    test('targets the field of the switcher', () => {
        expect(citySwitcher.selector).toBe('input[data-city-switcher]')
    })

    test('loads the autocomplete on the first focus only', async () => {
        const { input } = connect()

        expect(createAutocomplete).not.toHaveBeenCalled()
        await input.listeners.focus?.()
        await input.listeners.focus?.()

        expect(createAutocomplete).toHaveBeenCalledOnce()
        expect(createAutocomplete).toHaveBeenCalledWith(
            expect.objectContaining({ element: input, url: '/api/cities?q=__QUERY__' })
        )
    })

    test.each([
        ['a city', 'toulouse', '/toulouse'],
        ['a city under its country, keeping its slash', 'suisse/geneve', '/suisse/geneve'],
        ['a slug to encode', 'saint-étienne', '/saint-%C3%A9tienne'],
    ])('opens the page of %s picked', async (_label, slug, url) => {
        const assign = vi.fn()
        vi.stubGlobal('window', { location: { assign } })
        const { input } = connect()
        await input.listeners.focus?.()

        createAutocomplete.mock.calls[0][0].onSelection({ slug, name: 'Ville' })

        expect(assign).toHaveBeenCalledWith(url)
    })

    test('focuses the field as the dropdown opens, until disconnected', () => {
        const { input, dropdown, cleanup } = connect()

        dropdown.listeners['shown.bs.dropdown']?.()
        expect(input.focus).toHaveBeenCalledOnce()

        cleanup()
        expect(dropdown.listeners['shown.bs.dropdown']).toBeUndefined()
        expect(input.listeners.focus).toBeUndefined()
    })
})
