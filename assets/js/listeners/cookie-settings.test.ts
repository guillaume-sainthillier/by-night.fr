/**
 * Tests for the cookie-settings listener
 * Run: pnpm test
 */

import { afterEach, describe, expect, test, vi } from 'vitest'
import cookieSettings from '@/js/listeners/cookie-settings'
import { fake } from '@/js/test/fake'
import { connectListener } from '@/js/test/listener'

type Handler = (event: { preventDefault: () => void }) => void

const fakeLink = () => {
    const listeners: Record<string, Handler | undefined> = {}
    return {
        addEventListener: (type: string, listener: Handler) => {
            listeners[type] = listener
        },
        removeEventListener: (type: string, listener: Handler) => {
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
        connectListener(cookieSettings, fake<HTMLElement>(link))

        const event = link.click()

        expect(event.preventDefault).toHaveBeenCalledOnce()
        const [callbacks] = window.googlefc?.callbackQueue ?? []
        const showRevocationMessage = vi.fn()
        Object.assign(window.googlefc ?? {}, { showRevocationMessage })
        callbacks?.CONSENT_API_READY?.()
        expect(showRevocationMessage).toHaveBeenCalledOnce()
    })

    test('keeps the callbacks the page already queued', () => {
        const pageCallback = { CONSENT_MODE_DATA_READY: () => {} }
        vi.stubGlobal('window', { googlefc: { callbackQueue: [pageCallback] } })
        const link = fakeLink()
        connectListener(cookieSettings, fake<HTMLElement>(link))

        link.click()

        expect(window.googlefc?.callbackQueue).toHaveLength(2)
        expect(window.googlefc?.callbackQueue?.[0]).toBe(pageCallback)
    })

    test('stops listening on cleanup', () => {
        vi.stubGlobal('window', {})
        const link = fakeLink()
        const cleanup = connectListener(cookieSettings, fake<HTMLElement>(link))

        cleanup()
        const event = link.click()

        expect(event.preventDefault).not.toHaveBeenCalled()
        expect(window.googlefc).toBeUndefined()
    })
})
