/**
 * Tests for the toc listener ("Sur cette page" of the legal pages)
 * Run: pnpm test
 */

import { afterEach, beforeEach, describe, expect, test, vi } from 'vitest'
import toc from '@/js/listeners/toc'

const fakeLink = (id, offsetTop) => {
    const attributes = {}
    return {
        hash: `#${id}`,
        offsetTop,
        offsetHeight: 20,
        setAttribute: (name, value) => {
            attributes[name] = value
        },
        removeAttribute: (name) => {
            delete attributes[name]
        },
        attributes,
    }
}

// Three sections whose tops sit at the given distances from the top of the screen
const setUp = (tops) => {
    const ids = tops.map((_, index) => `section-${index}`)
    const links = ids.map((id, index) => fakeLink(id, index * 30))
    const sections = Object.fromEntries(
        ids.map((id, index) => [id, { getBoundingClientRect: () => ({ top: tops[index] }) }])
    )
    const listeners = {}

    vi.stubGlobal('window', {
        innerHeight: 1000,
        matchMedia: () => ({ matches: false }),
    })
    vi.stubGlobal('document', {
        getElementById: (id) => sections[id] ?? null,
        addEventListener: (type, listener) => {
            listeners[type] = listener
        },
        removeEventListener: (type, listener) => {
            if (listeners[type] === listener) {
                delete listeners[type]
            }
        },
    })

    const element = {
        scrollTop: 0,
        clientHeight: 500,
        scrollTo: vi.fn(),
        querySelectorAll: () => links,
    }

    return { element, links, sections, listeners, tops }
}

const current = (links) => links.findIndex((link) => link.attributes['aria-current'] === 'true')

describe('toc listener', () => {
    beforeEach(() => {
        vi.unstubAllGlobals()
    })

    afterEach(() => {
        vi.unstubAllGlobals()
    })

    test('marks the first entry before any section reached the line', () => {
        const { element, links } = setUp([400, 900, 1400])

        toc.connect(element)

        expect(current(links)).toBe(0)
    })

    test('marks the last section whose top went above the line, and follows the scroll', () => {
        const { element, links, listeners, tops } = setUp([-600, 100, 900])

        toc.connect(element)
        expect(current(links)).toBe(1)

        tops.splice(0, 3, -1400, -700, 200)
        listeners.scroll()

        expect(current(links)).toBe(2)
        expect(links.filter((link) => link.attributes['aria-current'])).toHaveLength(1)
    })

    test('stops listening once disconnected', () => {
        const { element, listeners } = setUp([0, 900])

        const cleanup = toc.connect(element)
        cleanup()

        expect(listeners.scroll).toBeUndefined()
    })
})
