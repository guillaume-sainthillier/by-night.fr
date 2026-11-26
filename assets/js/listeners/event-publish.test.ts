/**
 * Tests for the event-publish listener
 * Run: pnpm test
 */

import type { App } from '@silarhi/app-js'
import { connectListener } from '@silarhi/app-js/testing'
import { afterEach, describe, expect, test, vi } from 'vitest'
import eventPublish from '@/js/listeners/event-publish'
import { fake } from '@/js/test/fake'
import { confirmPublication } from '@/js/utils/duplicates'

const { sendJson } = vi.hoisted(() => ({ sendJson: vi.fn() }))

vi.mock('@silarhi/app-js/http', () => ({ sendJson }))
vi.mock('@/js/utils/duplicates', () => ({ confirmPublication: vi.fn() }))

const URL = '/api/events/42'

const DUPLICATES_URL = '/espace-perso/organisateurs/le-bikini/evenements/42/doublons'

type Handler = () => Promise<void>

const connect = (dataset: Record<string, string> = {}) => {
    const listeners: Record<string, Handler | undefined> = {}
    const notice = { remove: vi.fn() }
    const button = {
        dataset: { publishEvent: '42', ...dataset },
        disabled: false,
        closest: (selector: string) => (selector === '.alert' ? notice : null),
        addEventListener: (type: string, listener: Handler) => {
            listeners[type] = listener
        },
        removeEventListener: (type: string, listener: Handler) => {
            if (listeners[type] === listener) {
                delete listeners[type]
            }
        },
    }
    const toastManager = { createToast: vi.fn() }
    const modalManager = {}
    const services: Record<string, object> = { toastManager, modalManager }
    const cleanup = connectListener(
        eventPublish,
        fake<HTMLButtonElement>(button),
        fake<App>({ get: (name: string) => services[name] })
    )

    return { button, notice, toastManager, modalManager, cleanup, click: () => listeners.click?.() }
}

afterEach(() => {
    sendJson.mockReset()
    vi.mocked(confirmPublication).mockReset()
})

describe('event-publish listener', () => {
    test('targets the publish buttons', () => {
        expect(eventPublish.selector).toBe('button[data-publish-event]')
    })

    test('takes the event out of draft, then drops the preview notice', async () => {
        sendJson.mockResolvedValue({ draft: false, cancelled: false })
        const { button, notice, toastManager, click } = connect()

        await click()

        expect(sendJson).toHaveBeenCalledWith('PATCH', URL, { draft: false })
        expect(toastManager.createToast).toHaveBeenCalledWith('success', expect.any(String))
        expect(notice.remove).toHaveBeenCalled()
        expect(button.disabled).toBe(true)
    })

    test.each([
        ['a refusal', () => Promise.reject(new Error('Forbidden'))],
        // The login page's HTML is no JSON answer
        ['a lapsed session, redirected to the login page', () => Promise.resolve(null)],
        ['a network failure', () => Promise.reject(new TypeError('Failed to fetch'))],
    ])('keeps the notice and lets the author retry after %s', async (_, response) => {
        sendJson.mockImplementation(response)
        const { button, notice, toastManager, click } = connect()

        await click()

        expect(toastManager.createToast).toHaveBeenCalledWith('error', expect.any(String))
        expect(notice.remove).not.toHaveBeenCalled()
        expect(button.disabled).toBe(false)
    })

    test('asks first for the events already online the draft likely repeats', async () => {
        vi.mocked(confirmPublication).mockResolvedValue(true)
        sendJson.mockResolvedValue({ draft: false, cancelled: false })
        const { modalManager, notice, click } = connect({ publisher: 'le-bikini' })

        await click()

        expect(confirmPublication).toHaveBeenCalledWith(modalManager, DUPLICATES_URL)
        expect(sendJson).toHaveBeenCalledWith('PATCH', URL, { draft: false })
        expect(notice.remove).toHaveBeenCalled()
    })

    test('keeps the draft when the author cancels', async () => {
        vi.mocked(confirmPublication).mockResolvedValue(false)
        const { button, notice, toastManager, click } = connect({ publisher: 'le-bikini' })

        await click()

        expect(sendJson).not.toHaveBeenCalled()
        expect(toastManager.createToast).not.toHaveBeenCalled()
        expect(notice.remove).not.toHaveBeenCalled()
        expect(button.disabled).toBe(false)
    })

    test('stops listening once disconnected', async () => {
        const { cleanup, click } = connect()

        cleanup()
        await click()

        expect(sendJson).not.toHaveBeenCalled()
    })
})
