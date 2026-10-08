/**
 * Tests for the scroll-to-top module
 * Run: yarn test
 */

import { afterEach, beforeEach, describe, expect, test, vi } from 'vitest'
import scrollToTop, { SHOW_AFTER } from '@/js/modules/scroll-to-top'

// The Node environment has no DOM: the button only keeps its classes and listeners
const fakeButton = () => {
    const classes = new Set()
    const listeners = {}

    return {
        classList: {
            toggle: (name, force) => (force ? classes.add(name) : classes.delete(name)),
            contains: (name) => classes.has(name),
        },
        addEventListener: (type, listener) => {
            listeners[type] = listener
        },
        listeners,
    }
}

describe('scroll-to-top', () => {
    let button
    let onScroll

    beforeEach(() => {
        vi.useFakeTimers()
        button = fakeButton()
        vi.stubGlobal('document', { getElementById: (id) => ('toTop' === id ? button : null) })
        vi.stubGlobal('window', {
            scrollY: 0,
            scrollTo: vi.fn(),
            addEventListener: (type, listener) => {
                if ('scroll' === type) {
                    onScroll = listener
                }
            },
        })
    })

    afterEach(() => {
        vi.unstubAllGlobals()
        vi.useRealTimers()
    })

    test('the button stays out of sight at the top of the page', () => {
        scrollToTop()

        expect(button.classList.contains('is-visible')).toBe(false)
    })

    test('the button shows once the page is scrolled down, and hides back near the top', () => {
        scrollToTop()

        window.scrollY = SHOW_AFTER + 1
        onScroll()
        expect(button.classList.contains('is-visible')).toBe(true)

        vi.advanceTimersByTime(300)
        window.scrollY = 0
        onScroll()
        expect(button.classList.contains('is-visible')).toBe(false)
    })

    test('a page reloaded halfway down shows the button at once', () => {
        window.scrollY = 2000

        scrollToTop()

        expect(button.classList.contains('is-visible')).toBe(true)
    })

    test('a click brings the page back to the top', () => {
        scrollToTop()
        const event = { preventDefault: vi.fn() }

        button.listeners.click(event)

        expect(event.preventDefault).toHaveBeenCalled()
        expect(window.scrollTo).toHaveBeenCalledWith({ top: 0, behavior: 'smooth' })
    })
})
