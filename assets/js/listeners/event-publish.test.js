/**
 * Tests for the event-publish listener
 * Run: yarn test
 */

import { afterEach, describe, expect, test, vi } from 'vitest'
import eventPublish from '@/js/listeners/event-publish'
import { confirmPublication } from '@/js/utils/duplicates'

vi.mock('@/js/utils/duplicates', () => ({ confirmPublication: vi.fn() }))

const URL = '/api/events/42/draft'

const DUPLICATES_URL = '/espace-perso/42/doublons'

const connect = (dataset = {}) => {
    const listeners = {}
    const notice = { remove: vi.fn() }
    const button = {
        dataset: { publishHref: URL, ...dataset },
        disabled: false,
        closest: (selector) => (selector === '.alert' ? notice : null),
        addEventListener: (type, listener) => {
            listeners[type] = listener
        },
        removeEventListener: (type, listener) => {
            if (listeners[type] === listener) {
                delete listeners[type]
            }
        },
    }
    const toastManager = { createToast: vi.fn() }
    const modalManager = {}
    const services = { toastManager, modalManager }
    const cleanup = eventPublish.connect(button, { app: { get: (name) => services[name] } })

    return { button, notice, toastManager, modalManager, cleanup, click: () => listeners.click?.() }
}

const json = (body, ok = true) => ({ ok, json: () => Promise.resolve(body) })

afterEach(() => {
    vi.unstubAllGlobals()
    vi.mocked(confirmPublication).mockReset()
})

describe('event-publish listener', () => {
    test('targets the publish buttons', () => {
        expect(eventPublish.selector).toBe('button[data-publish-href]')
    })

    test('takes the event out of draft, then drops the preview notice', async () => {
        const fetch = vi.fn(() => Promise.resolve(json({ success: true })))
        vi.stubGlobal('fetch', fetch)
        const { button, notice, toastManager, click } = connect()

        await click()

        expect(fetch).toHaveBeenCalledWith(URL, expect.objectContaining({ method: 'PUT', body: '{"draft":false}' }))
        expect(toastManager.createToast).toHaveBeenCalledWith('success', expect.any(String))
        expect(notice.remove).toHaveBeenCalled()
        expect(button.disabled).toBe(true)
    })

    test.each([
        ['a refusal', () => Promise.resolve(json({ title: 'Forbidden' }, false))],
        [
            'a lapsed session, redirected to the login page',
            () => Promise.resolve({ ok: true, json: () => Promise.reject(new SyntaxError()) }),
        ],
        ['a network failure', () => Promise.reject(new TypeError('Failed to fetch'))],
    ])('keeps the notice and lets the author retry after %s', async (_, response) => {
        vi.stubGlobal('fetch', vi.fn(response))
        const { button, notice, toastManager, click } = connect()

        await click()

        expect(toastManager.createToast).toHaveBeenCalledWith('error', expect.any(String))
        expect(notice.remove).not.toHaveBeenCalled()
        expect(button.disabled).toBe(false)
    })

    test('asks first for the events already online the draft likely repeats', async () => {
        vi.mocked(confirmPublication).mockResolvedValue(true)
        const fetch = vi.fn(() => Promise.resolve(json({ success: true })))
        vi.stubGlobal('fetch', fetch)
        const { modalManager, notice, click } = connect({ duplicatesHref: DUPLICATES_URL })

        await click()

        expect(confirmPublication).toHaveBeenCalledWith(modalManager, DUPLICATES_URL)
        expect(fetch).toHaveBeenCalledWith(URL, expect.objectContaining({ method: 'PUT' }))
        expect(notice.remove).toHaveBeenCalled()
    })

    test('keeps the draft when the author cancels', async () => {
        vi.mocked(confirmPublication).mockResolvedValue(false)
        const fetch = vi.fn()
        vi.stubGlobal('fetch', fetch)
        const { button, notice, toastManager, click } = connect({ duplicatesHref: DUPLICATES_URL })

        await click()

        expect(fetch).not.toHaveBeenCalled()
        expect(toastManager.createToast).not.toHaveBeenCalled()
        expect(notice.remove).not.toHaveBeenCalled()
        expect(button.disabled).toBe(false)
    })

    test('stops listening once disconnected', async () => {
        const fetch = vi.fn()
        vi.stubGlobal('fetch', fetch)
        const { cleanup, click } = connect()

        cleanup()
        await click()

        expect(fetch).not.toHaveBeenCalled()
    })
})
