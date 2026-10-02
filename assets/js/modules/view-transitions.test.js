/**
 * Tests for the view-transitions module
 * Run: yarn test
 */

import { afterEach, beforeEach, describe, expect, test, vi } from 'vitest'
import viewTransitions from '@/js/modules/view-transitions'

const ORIGIN = 'https://by-night.fr'
const EVENT = '/toulouse/soiree/concert--1'
const OTHER_EVENT = '/toulouse/soiree/expo--2'

// The Node environment has no DOM: elements only carry the inline style the module sets
const fakeElement = () => ({
    style: {
        viewTransitionName: '',
        removeProperty(property) {
            if ('view-transition-name' === property) {
                this.viewTransitionName = ''
            }
        },
    },
})

// A card of the page: its title link leads to pathname, its picture is what the module names
const fakeCard = (pathname) => {
    const picture = fakeElement()
    const card = { querySelector: (selector) => ('.image-container' === selector ? picture : null) }
    const link = { pathname, closest: (selector) => ('.card-event' === selector ? card : null) }

    return { link, picture }
}

// A transition whose end the test decides
const fakeTransition = () => {
    let finish
    let abort
    const finished = new Promise((resolve, reject) => {
        finish = resolve
        abort = reject
    })

    return { finished, finish, abort }
}

let handlers
const load = ({ cards = [], poster = null, from = null } = {}) => {
    handlers = {}
    vi.stubGlobal('window', {
        addEventListener: (type, handler) => {
            handlers[type] = handler
        },
        navigation: from ? { activation: { from: { url: ORIGIN + from } } } : undefined,
    })
    vi.stubGlobal('document', {
        getElementById: (id) => ('event-poster' === id ? poster : null),
        querySelectorAll: () => cards.map((card) => card.link),
    })
    viewTransitions()
}

const leave = (to, transition = fakeTransition()) => {
    handlers.pageswap({ viewTransition: transition, activation: { entry: { url: ORIGIN + to } } })

    return transition
}

describe('view-transitions module', () => {
    beforeEach(() => {
        handlers = {}
    })

    afterEach(() => {
        vi.unstubAllGlobals()
    })

    test('leaving a listing names the picture of the card leading to the event, and only it', () => {
        const other = fakeCard(OTHER_EVENT)
        const clicked = fakeCard(EVENT)
        load({ cards: [other, clicked] })

        leave(EVENT)

        expect(clicked.picture.style.viewTransitionName).toBe('event-picture')
        expect(other.picture.style.viewTransitionName).toBe('')
    })

    test('the name is cleared once the transition has finished', async () => {
        const clicked = fakeCard(EVENT)
        load({ cards: [clicked] })

        const transition = leave(EVENT)
        transition.finish()
        await transition.finished
        await Promise.resolve()

        expect(clicked.picture.style.viewTransitionName).toBe('')
    })

    test('the name is cleared when the destination does not opt in, without an unhandled rejection', async () => {
        const clicked = fakeCard(EVENT)
        load({ cards: [clicked] })

        const transition = leave(EVENT)
        transition.abort(new Error('Transition was aborted because of invalid state. ViewTransition opt-in disabled'))
        await transition.finished.catch(() => {})
        await Promise.resolve()

        expect(clicked.picture.style.viewTransitionName).toBe('')
    })

    test('leaving an event page for a similar event takes the name from the poster, so the page holds only one', () => {
        const poster = fakeElement()
        const similar = fakeCard(OTHER_EVENT)
        load({ cards: [similar], poster })

        leave(OTHER_EVENT)

        expect(poster.style.viewTransitionName).toBe('none')
        expect(similar.picture.style.viewTransitionName).toBe('event-picture')
    })

    test('leaving for a page no card leads to names nothing: an event page keeps its poster named by CSS', () => {
        const poster = fakeElement()
        load({ cards: [fakeCard(OTHER_EVENT)], poster })

        leave('/toulouse/agenda')

        expect(poster.style.viewTransitionName).toBe('')
    })

    test('a page left without the Navigation API names nothing', () => {
        const clicked = fakeCard(EVENT)
        load({ cards: [clicked] })

        handlers.pageswap({ viewTransition: fakeTransition(), activation: null })

        expect(clicked.picture.style.viewTransitionName).toBe('')
    })

    test('back on a listing, the card of the event left is named', () => {
        const card = fakeCard(EVENT)
        load({ cards: [card], from: EVENT })

        handlers.pagereveal({ viewTransition: fakeTransition() })

        expect(card.picture.style.viewTransitionName).toBe('event-picture')
    })

    test('back on a listing, the name its own departure left is cleared first', () => {
        const left = fakeCard(OTHER_EVENT)
        const back = fakeCard(EVENT)
        load({ cards: [left, back], from: EVENT })

        // Restored from the back/forward cache before the departure's transition had finished
        leave(OTHER_EVENT)
        handlers.pagereveal({ viewTransition: fakeTransition() })

        expect(left.picture.style.viewTransitionName).toBe('')
        expect(back.picture.style.viewTransitionName).toBe('event-picture')
    })

    test('arriving on an event page names no card: its poster is named by CSS', () => {
        const similar = fakeCard(OTHER_EVENT)
        load({ cards: [similar], poster: fakeElement(), from: OTHER_EVENT })

        handlers.pagereveal({ viewTransition: fakeTransition() })

        expect(similar.picture.style.viewTransitionName).toBe('')
    })

    test('a page loaded without a transition names nothing', () => {
        const card = fakeCard(EVENT)
        load({ cards: [card], from: EVENT })

        handlers.pagereveal({ viewTransition: null })

        expect(card.picture.style.viewTransitionName).toBe('')
    })
})
