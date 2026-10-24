/**
 * Tests for the like listener
 * Run: pnpm test
 */

import type { App } from '@silarhi/app-js'
import { afterEach, beforeEach, describe, expect, test, vi } from 'vitest'
import like from '@/js/listeners/like'
import { fake } from '@/js/test/fake'
import { connectListener } from '@/js/test/listener'

// jQuery needs a DOM, which the Node environment lacks: the fake wraps the button's classes, records the handlers
// bound by namespace and answers $.ajax with the response the test sets
const { $, state } = vi.hoisted(() => {
    interface FakeButton {
        dataset: Record<string, string>
        classes: Set<string>
        attributes: Record<string, string | null>
    }
    interface Response {
        success: boolean
        like: boolean
    }
    const state: { handlers: Record<string, () => void>; requests: { data: string }[]; response: Response } = {
        handlers: {},
        requests: [],
        response: { success: true, like: true },
    }
    const $ = (element: FakeButton) => ({
        on: (events: string, handler: (this: FakeButton) => void) => {
            state.handlers[events] = handler.bind(element)
        },
        off: () => {
            state.handlers = {}
        },
        attr: (name: string, value: string | null) => {
            element.attributes[name] = value
        },
        data: (name: string) => element.dataset[name],
        hasClass: (name: string) => element.classes.has(name),
        toggleClass: (name: string, on: boolean) => (on ? element.classes.add(name) : element.classes.delete(name)),
    })
    $.ajax = (request: { data: string }) => {
        state.requests.push(request)
        return { done: (callback: (response: Response) => void) => callback(state.response) }
    }
    return { $, state }
})

vi.mock('jquery', () => ({ default: $ }))

const connect = ({ intent, active = false }: { intent?: string; active?: boolean } = {}) => {
    const button = {
        dataset: { href: '/api/events/42/participer', ...(intent ? { intent } : {}) },
        classes: new Set(active ? ['btn-primary'] : []),
        attributes: {},
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
    state.handlers = {}
    state.requests = []
    state.response = { success: true, like: true }
})

afterEach(() => {
    vi.unstubAllGlobals()
})

describe('like listener', () => {
    test('adds the event to the outings on a click, and says so', () => {
        visit('')
        const { button, toastManager } = connect()

        state.handlers['click.like']?.()

        expect(state.requests).toHaveLength(1)
        expect(JSON.parse(state.requests[0].data)).toEqual({ like: true })
        expect(button.classes.has('btn-primary')).toBe(true)
        expect(toastManager.createToast).toHaveBeenCalledWith('success', 'Ajouté à vos sorties')
    })

    test('keeps the other buttons of the event in step', () => {
        visit('')
        const other = { dataset: { href: '/api/events/42/participer' }, classList: { toggle: vi.fn() } }
        const elsewhere = { dataset: { href: '/api/events/7/participer' }, classList: { toggle: vi.fn() } }
        vi.stubGlobal('document', { querySelectorAll: () => [other, elsewhere] })
        connect()

        state.handlers['click.like']?.()

        expect(other.classList.toggle).toHaveBeenCalledWith('btn-primary', true)
        expect(elsewhere.classList.toggle).not.toHaveBeenCalled()
    })

    test('records the click made before the login, once, and forgets the fragment', () => {
        const replaceState = visit('#participer')
        const { button } = connect({ intent: 'participer' })

        expect(state.requests).toHaveLength(1)
        expect(JSON.parse(state.requests[0].data)).toEqual({ like: true })
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
