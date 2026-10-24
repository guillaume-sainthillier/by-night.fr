/**
 * Tests for the read-more listener
 * Run: pnpm test
 */

import { afterEach, describe, expect, test, vi } from 'vitest'
import readMore from '@/js/listeners/read-more'
import { fake } from '@/js/test/fake'
import { connectListener, connectListenerWithoutCleanup } from '@/js/test/listener'

type Handler = () => void

/** A text of that height, cut to that one, and the button the listener would create */
const setUp = (scrollHeight: number, clientHeight: number) => {
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

    return { text, button, classes, handlers }
}

describe('read-more listener', () => {
    afterEach(() => {
        vi.unstubAllGlobals()
    })

    test('offers the rest of a text cut short, and shows it', () => {
        const { text, button, classes, handlers } = setUp(240, 96)
        connectListener(readMore, fake<HTMLElement>(text))

        expect(text.after).toHaveBeenCalledWith(button)
        expect(button.textContent).toBe('Lire la suite')

        handlers.click?.()

        expect(classes.has('is-expanded')).toBe(true)
        expect(button.remove).toHaveBeenCalled()
    })

    test('adds nothing to a text that fits', () => {
        const { text } = setUp(96, 96)

        connectListenerWithoutCleanup(readMore, fake<HTMLElement>(text))

        expect(text.after).not.toHaveBeenCalled()
    })

    test('takes its button away once disconnected', () => {
        const { text, button } = setUp(240, 96)
        const cleanup = connectListener(readMore, fake<HTMLElement>(text))

        cleanup()

        expect(button.remove).toHaveBeenCalled()
    })
})
