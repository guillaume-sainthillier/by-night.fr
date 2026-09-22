/**
 * Tests for the cookie-settings listener
 * Run: yarn test
 */

import { afterEach, describe, expect, test, vi } from 'vitest'
import cookieSettings from '@/js/listeners/cookie-settings'

const fakeLink = () => {
    const listeners = {}
    return {
        addEventListener: (type, listener) => {
            listeners[type] = listener
        },
        removeEventListener: (type, listener) => {
            if (listeners[type] === listener) {
                delete listeners[type]
            }
        },
        click: () => {
            const event = { preventDefault: vi.fn() }
            listeners.click?.(event)
            return event
        },
    }
}

afterEach(() => {
    vi.unstubAllGlobals()
})

describe('cookie-settings listener', () => {
    test('targets the "Gérer mes cookies" links', () => {
        expect(cookieSettings.selector).toBe('[data-cookie-consent]')
    })

    test("reopens Google's consent message once its API is ready", () => {
        vi.stubGlobal('window', {})
        const link = fakeLink()
        cookieSettings.connect(link)

        const event = link.click()

        expect(event.preventDefault).toHaveBeenCalledOnce()
        const [callbacks] = window.googlefc.callbackQueue
        window.googlefc.showRevocationMessage = vi.fn()
        callbacks.CONSENT_API_READY()
        expect(window.googlefc.showRevocationMessage).toHaveBeenCalledOnce()
    })

    test('keeps the callbacks the page already queued', () => {
        const pageCallback = { CONSENT_MODE_DATA_READY: () => {} }
        vi.stubGlobal('window', { googlefc: { callbackQueue: [pageCallback] } })
        const link = fakeLink()
        cookieSettings.connect(link)

        link.click()

        expect(window.googlefc.callbackQueue).toHaveLength(2)
        expect(window.googlefc.callbackQueue[0]).toBe(pageCallback)
    })

    test('stops listening on cleanup', () => {
        vi.stubGlobal('window', {})
        const link = fakeLink()
        const cleanup = cookieSettings.connect(link)

        cleanup()
        const event = link.click()

        expect(event.preventDefault).not.toHaveBeenCalled()
        expect(window.googlefc).toBeUndefined()
    })
})
