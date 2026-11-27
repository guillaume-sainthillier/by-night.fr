/**
 * Tests for the follow listener
 * Run: pnpm test
 */

import type { App } from '@silarhi/app-js'
import { connectListener } from '@silarhi/app-js/testing'
import { afterEach, beforeEach, describe, expect, test, vi } from 'vitest'
import follow from '@/js/listeners/follow'
import { fake } from '@/js/test/fake'

// The Node environment has no DOM: the button is a stand-in recording its click handler, and the request answers
// with the response the test sets
const { state, sendJson } = vi.hoisted(() => {
    interface Response {
        following: boolean
        followers: number
    }
    const state: { urls: string[]; requests: unknown[]; response: Response | Error | null } = {
        urls: [],
        requests: [],
        response: { following: true, followers: 1 },
    }
    const sendJson = async (_method: string, url: string, data: unknown) => {
        state.urls.push(url)
        state.requests.push(data)
        if (state.response instanceof Error) {
            throw state.response
        }
        return state.response
    }
    return { state, sendJson }
})

vi.mock('@silarhi/app-js/http', () => ({ sendJson }))

const connect = ({ active = false }: { active?: boolean } = {}) => {
    const classes = new Set(active ? ['btn-primary'] : [])
    const label = { textContent: active ? 'Suivi' : 'Suivre' }
    const followers = { textContent: '' }
    const button = {
        dataset: { follow: 'publisher', followId: '7', intent: 'suivre' },
        classes,
        label,
        followers,
        disabled: false,
        classList: {
            contains: (name: string) => classes.has(name),
            toggle: (name: string, on: boolean) => (on ? classes.add(name) : classes.delete(name)),
        },
        setAttribute: vi.fn(),
        querySelector: () => label,
        parentElement: { querySelector: () => followers },
        click: undefined as (() => Promise<void>) | undefined,
        addEventListener(_type: 'click', handler: () => Promise<void>) {
            this.click = handler
        },
        removeEventListener() {
            this.click = undefined
        },
    }
    const toastManager = { createToast: vi.fn() }
    const cleanup = connectListener(follow, fake<HTMLButtonElement>(button), fake<App>({ get: () => toastManager }))

    return { button, toastManager, cleanup }
}

const visit = (hash: string) => {
    const replaceState = vi.fn()
    vi.stubGlobal('window', {
        location: { hash, pathname: '/organisateurs/le-bikini', search: '' },
        history: { replaceState },
    })

    return replaceState
}

beforeEach(() => {
    state.urls = []
    state.requests = []
    state.response = { following: true, followers: 1 }
})

afterEach(() => {
    vi.unstubAllGlobals()
})

describe('follow listener', () => {
    test('follows on a click, and says so with the count', async () => {
        visit('')
        state.response = { following: true, followers: 12 }
        const { button, toastManager } = connect()

        await button.click?.()

        expect(state.urls).toEqual(['/api/publishers/7/follow'])
        expect(state.requests).toEqual([{ following: true }])
        expect(button.classes.has('btn-primary')).toBe(true)
        expect(button.label.textContent).toBe('Suivi')
        expect(button.followers.textContent).toBe('12 abonnés')
        expect(toastManager.createToast).toHaveBeenCalledWith('success', expect.any(String))
    })

    test('stops following on a click when already followed', async () => {
        visit('')
        state.response = { following: false, followers: 0 }
        const { button, toastManager } = connect({ active: true })

        await button.click?.()

        expect(state.requests).toEqual([{ following: false }])
        expect(button.classes.has('btn-primary')).toBe(false)
        expect(button.label.textContent).toBe('Suivre')
        expect(button.followers.textContent).toBe('')
        expect(toastManager.createToast).not.toHaveBeenCalled()
    })

    test('gives the button back and says so when the request fails', async () => {
        visit('')
        state.response = new TypeError('Failed to fetch')
        const { button, toastManager } = connect()

        await button.click?.()

        expect(button.disabled).toBe(false)
        expect(toastManager.createToast).toHaveBeenCalledWith('error', expect.any(String))
    })

    test('follows once for a visitor back from the login', () => {
        const replaceState = visit('#suivre')
        connect()

        expect(state.requests).toEqual([{ following: true }])
        expect(replaceState).toHaveBeenCalledWith(null, '', '/organisateurs/le-bikini')
    })

    test('does not stop following a publisher followed already, back from the login', () => {
        visit('#suivre')
        connect({ active: true })

        expect(state.requests).toEqual([])
    })
})
