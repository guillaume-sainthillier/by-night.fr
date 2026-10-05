/**
 * Tests for the like listener
 * Run: yarn test
 */

import { afterEach, beforeEach, describe, expect, test, vi } from 'vitest'
import like from '@/js/listeners/like'

// jQuery needs a DOM, which the Node environment lacks: the fake wraps the button's classes, records the handlers
// bound by namespace and answers $.ajax with the response the test sets
const { $, state } = vi.hoisted(() => {
    const state = { handlers: {}, requests: [], response: { success: true, like: true } }
    const $ = (element) => ({
        on: (events, handler) => {
            state.handlers[events] = handler.bind(element)
        },
        off: () => {
            state.handlers = {}
        },
        attr: (name, value) => {
            element.attributes[name] = value
        },
        data: (name) => element.dataset[name],
        hasClass: (name) => element.classes.has(name),
        toggleClass: (name, on) => (on ? element.classes.add(name) : element.classes.delete(name)),
    })
    $.ajax = (request) => {
        state.requests.push(request)
        return { done: (callback) => callback(state.response) }
    }
    return { $, state }
})

vi.mock('jquery', () => ({ default: $ }))

const connect = ({ intent, active = false } = {}) => {
    const button = {
        dataset: { href: '/api/events/42/participer', ...(intent ? { intent } : {}) },
        classes: new Set(active ? ['btn-primary'] : []),
        attributes: {},
    }
    const toastManager = { createToast: vi.fn() }
    like.connect(button, { app: { get: () => toastManager } })

    return { button, toastManager }
}

const visit = (hash) => {
    const replaceState = vi.fn()
    vi.stubGlobal('window', {
        location: { hash, pathname: '/toulouse/soiree/concert--42', search: '' },
        history: { replaceState },
    })

    return replaceState
}

beforeEach(() => {
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

        state.handlers['click.like']()

        expect(state.requests).toHaveLength(1)
        expect(JSON.parse(state.requests[0].data)).toEqual({ like: true })
        expect(button.classes.has('btn-primary')).toBe(true)
        expect(toastManager.createToast).toHaveBeenCalledWith('success', 'Ajouté à vos sorties')
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
