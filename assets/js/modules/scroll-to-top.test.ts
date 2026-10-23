/**
 * Tests for the scroll-to-top module
 * Run: pnpm test
 */

import type { App } from '@silarhi/app-js'
import { afterEach, beforeEach, describe, expect, type Mock, test, vi } from 'vitest'
import scrollToTop, { SHOW_AFTER } from '@/js/modules/scroll-to-top'
import { fake } from '@/js/test/fake'

type Handler = (event?: { preventDefault: () => void }) => void

// The Node environment has no DOM: the button only keeps its classes and listeners
const fakeButton = () => {
    const classes = new Set<string>()
    const listeners: Record<string, Handler> = {}

    return {
        classList: {
            toggle: (name: string, force?: boolean) => (force ? classes.add(name) : classes.delete(name)),
            contains: (name: string) => classes.has(name),
        },
        addEventListener: (type: string, listener: Handler) => {
            listeners[type] = listener
        },
        listeners,
    }
}

const run = () => scrollToTop({ app: fake<App>({}) })

describe('scroll-to-top', () => {
    let button: ReturnType<typeof fakeButton>
    let onScroll: Handler
    let page: { scrollY: number; scrollTo: Mock; addEventListener: (type: string, listener: Handler) => void }

    beforeEach(() => {
        vi.useFakeTimers()
        button = fakeButton()
        vi.stubGlobal('document', { getElementById: (id: string) => ('toTop' === id ? button : null) })
        page = {
            scrollY: 0,
            scrollTo: vi.fn(),
            addEventListener: (type, listener) => {
                if ('scroll' === type) {
                    onScroll = listener
                }
            },
        }
        vi.stubGlobal('window', page)
    })

    afterEach(() => {
        vi.unstubAllGlobals()
        vi.useRealTimers()
    })

    test('the button stays out of sight at the top of the page', () => {
        run()

        expect(button.classList.contains('is-visible')).toBe(false)
    })

    test('the button shows once the page is scrolled down, and hides back near the top', () => {
        run()

        page.scrollY = SHOW_AFTER + 1
        onScroll()
        expect(button.classList.contains('is-visible')).toBe(true)

        vi.advanceTimersByTime(300)
        page.scrollY = 0
        onScroll()
        expect(button.classList.contains('is-visible')).toBe(false)
    })

    test('a page reloaded halfway down shows the button at once', () => {
        page.scrollY = 2000

        run()

        expect(button.classList.contains('is-visible')).toBe(true)
    })

    test('a click brings the page back to the top', () => {
        run()
        const event = { preventDefault: vi.fn() }

        button.listeners.click?.(event)

        expect(event.preventDefault).toHaveBeenCalled()
        expect(page.scrollTo).toHaveBeenCalledWith({ top: 0, behavior: 'smooth' })
    })
})
