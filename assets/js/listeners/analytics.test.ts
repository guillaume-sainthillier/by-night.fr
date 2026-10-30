/**
 * Tests for the analytics listener
 * Run: pnpm test
 */

import { connectListener } from '@silarhi/app-js/testing'
import { afterEach, describe, expect, test, vi } from 'vitest'
import analytics, { readEventData, track } from '@/js/listeners/analytics'
import { fake } from '@/js/test/fake'

type Handler = () => void

/** A ticketing link of an event page: its attributes, and the listeners it is given */
const link = (attributes: Record<string, string>) => {
    const handlers: Record<string, Handler | undefined> = {}
    const element = {
        attributes: Object.entries(attributes).map(([name, value]) => ({ name, value })),
        getAttribute: (name: string) => attributes[name] ?? null,
        addEventListener: vi.fn((type: string, handler: Handler) => {
            handlers[type] = handler
        }),
        removeEventListener: vi.fn(),
    }

    return { element, handlers }
}

describe('analytics listener', () => {
    afterEach(() => {
        vi.unstubAllGlobals()
    })

    test('sends the event of a clicked link with its properties', () => {
        const umami = { track: vi.fn() }
        vi.stubGlobal('window', { umami })
        const { element, handlers } = link({
            href: 'https://www.fnac.com/',
            'data-analytics-event': 'ticket-click',
            'data-analytics-seller': 'Fnac',
        })
        connectListener(analytics, fake<HTMLElement>(element))

        handlers.click?.()

        // The name and the property EventVisitSync reads back from Umami
        expect(umami.track).toHaveBeenCalledWith('ticket-click', { seller: 'Fnac' })
    })

    test('stops listening once disconnected', () => {
        const { element, handlers } = link({ 'data-analytics-event': 'ticket-click' })
        const cleanup = connectListener(analytics, fake<HTMLElement>(element))

        cleanup()

        expect(element.removeEventListener).toHaveBeenCalledWith('click', handlers.click)
    })
})

describe('track', () => {
    afterEach(() => {
        vi.unstubAllGlobals()
    })

    test('drops the event without the tracker (ad blocker, no website id)', () => {
        vi.stubGlobal('window', {})

        expect(() => track('ticket-click', { seller: 'Fnac' })).not.toThrow()
    })
})

describe('readEventData', () => {
    test('keeps the kebab-case of the properties and leaves out the name and the other attributes', () => {
        const { element } = link({
            class: 'btn',
            'data-analytics-event': 'ticket-click',
            'data-analytics-seller': 'Ticketmaster',
            'data-analytics-from-bar': 'yes',
        })

        expect(readEventData(fake<Element>(element))).toEqual({ seller: 'Ticketmaster', 'from-bar': 'yes' })
    })
})
