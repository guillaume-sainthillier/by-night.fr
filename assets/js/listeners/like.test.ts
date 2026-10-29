/**
 * Tests for the like listener
 * Run: pnpm test
 */

import type { App } from '@silarhi/app-js'
import { afterEach, beforeEach, describe, expect, test, vi } from 'vitest'
import like from '@/js/listeners/like'
import { fake } from '@/js/test/fake'
import { connectListener } from '@/js/test/listener'

// The Node environment has no DOM: the button is a stand-in recording its click handler, and the request answers
// with the response the test sets
const { state, sendJson } = vi.hoisted(() => {
    interface Response {
        success: boolean
        like: boolean
    }
    const state: { requests: unknown[]; response: Response | Error } = {
        requests: [],
        response: { success: true, like: true },
    }
    const sendJson = async (_method: string, _url: string, data: unknown) => {
        state.requests.push(data)
        if (state.response instanceof Error) {
            throw state.response
        }
        return state.response
    }
    return { state, sendJson }
})

vi.mock('@/js/utils/http', () => ({ sendJson }))

const connect = ({ intent, active = false }: { intent?: string; active?: boolean } = {}) => {
    const classes = new Set(active ? ['btn-primary'] : [])
    const button = {
        dataset: { href: '/api/events/42/participer', ...(intent ? { intent } : {}) },
        classes,
        classList: {
            contains: (name: string) => classes.has(name),
            toggle: (name: string, on: boolean) => (on ? classes.add(name) : classes.delete(name)),
        },
        setAttribute: vi.fn(),
        removeAttribute: vi.fn(),
        click: undefined as (() => Promise<void>) | undefined,
        addEventListener(_type: 'click', handler: () => Promise<void>) {
            this.click = handler
        },
        removeEventListener() {
            this.click = undefined
        },
    }
    const toastManager = { createToast: vi.fn() }
    connectListener(like, fake<HTMLElement>(button), fake<App>({ get: () => toastManager }))

    return { button, toastManager }
}

const visit = (hash: string) => {
    const replaceState = vi.fn()
    vi.stubGlobal('window', {
        location: { hash, pathname: '/toulouse/soiree/concert--42', search: '' },
        history: { replaceState },
    })

    return replaceState
}

beforeEach(() => {
    vi.stubGlobal('document', { querySelectorAll: () => [] })
    state.requests = []
    state.response = { success: true, like: true }
})

afterEach(() => {
    vi.unstubAllGlobals()
})

describe('like listener', () => {
    test('adds the event to the outings on a click, and says so', async () => {
        visit('')
        const { button, toastManager } = connect()

        await button.click?.()

        expect(state.requests).toEqual([{ like: true }])
        expect(button.classes.has('btn-primary')).toBe(true)
        expect(toastManager.createToast).toHaveBeenCalledWith('success', 'Ajouté à vos sorties')
    })

    test('keeps the other buttons of the event in step', async () => {
        visit('')
        const other = { dataset: { href: '/api/events/42/participer' }, classList: { toggle: vi.fn() } }
        const elsewhere = { dataset: { href: '/api/events/7/participer' }, classList: { toggle: vi.fn() } }
        vi.stubGlobal('document', { querySelectorAll: () => [other, elsewhere] })
        const { button } = connect()

        await button.click?.()

        expect(other.classList.toggle).toHaveBeenCalledWith('btn-primary', true)
        expect(elsewhere.classList.toggle).not.toHaveBeenCalled()
    })

    test('gives the button back and says so when the request fails', async () => {
        visit('')
        state.response = new TypeError('Failed to fetch')
        const { button, toastManager } = connect()

        await button.click?.()

        expect(button.removeAttribute).toHaveBeenCalledWith('disabled')
        expect(button.classes.has('btn-primary')).toBe(false)
        expect(toastManager.createToast).toHaveBeenCalledWith('error', 'Une erreur est survenue, veuillez réessayer.')
    })

    test('records the click made before the login, once, and forgets the fragment', async () => {
        const replaceState = visit('#participer')
        const { button } = connect({ intent: 'participer' })
        await vi.waitFor(() => expect(button.classes.size).toBe(1))

        expect(state.requests).toEqual([{ like: true }])
        expect(button.classes.has('btn-primary')).toBe(true)
        expect(replaceState).toHaveBeenCalledWith(null, '', '/toulouse/soiree/concert--42')
    })

    test('does not take back an event already in the outings', () => {
        visit('#participer')
        connect({ intent: 'participer', active: true })

        expect(state.requests).toHaveLength(0)
    })

    test.each([
        ['another fragment', '#comments', 'participer'],
        ['a button without intent', '#participer', undefined],
    ])('does nothing by itself on %s', (_label, hash, intent) => {
        visit(hash)
        connect({ intent })

        expect(state.requests).toHaveLength(0)
    })
})
