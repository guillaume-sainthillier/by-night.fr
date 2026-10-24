/**
 * Tests for the read-more listener
 * Run: pnpm test
 */

import { afterEach, describe, expect, test, vi } from 'vitest'
import readMore from '@/js/listeners/read-more'
import { fake } from '@/js/test/fake'

type Handler = () => void

const connect = (scrollHeight: number, clientHeight: number) => {
    const handlers: Record<string, Handler | undefined> = {}
    const button = {
        type: '',
        className: '',
        textContent: '',
        remove: vi.fn(),
        addEventListener: (type: string, handler: Handler) => {
            handlers[type] = handler
        },
    }
    vi.stubGlobal('document', { createElement: () => button })
    const classes = new Set<string>()
    const text = { scrollHeight, clientHeight, classList: { add: (name: string) => classes.add(name) }, after: vi.fn() }
    const cleanup = readMore.connect(fake<HTMLElement>(text))

    return { text, button, classes, handlers, cleanup }
}

describe('read-more listener', () => {
    afterEach(() => {
        vi.unstubAllGlobals()
    })

    test('offers the rest of a text cut short, and shows it', () => {
        const { text, button, classes, handlers } = connect(240, 96)

        expect(text.after).toHaveBeenCalledWith(button)
        expect(button.textContent).toBe('Lire la suite')

        handlers.click?.()

        expect(classes.has('is-expanded')).toBe(true)
        expect(button.remove).toHaveBeenCalled()
    })

    test('adds nothing to a text that fits', () => {
        const { text, cleanup } = connect(96, 96)

        expect(text.after).not.toHaveBeenCalled()
        expect(cleanup).toBeUndefined()
    })
})
