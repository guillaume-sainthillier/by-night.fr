/**
 * Tests for the filtered-link listener
 * Run: yarn test
 */

import { describe, expect, test } from 'vitest'
import filteredLink from '@/js/listeners/filtered-link'

const SEO_HREF = '/toulouse/agenda/sortir/concert'
const FILTERED_HREF = '/toulouse/agenda/sortir-a/le-bikini?type=concert'

const fakeLink = () => {
    const listeners = {}
    const attributes = { href: SEO_HREF }
    return {
        dataset: { filteredHref: FILTERED_HREF },
        getAttribute: (name) => attributes[name],
        setAttribute: (name, value) => {
            attributes[name] = value
        },
        addEventListener: (type, listener) => {
            listeners[type] = listener
        },
        removeEventListener: (type, listener) => {
            if (listeners[type] === listener) {
                delete listeners[type]
            }
        },
        dispatch: (type) => listeners[type]?.(),
    }
}

describe('filtered-link listener', () => {
    test('targets the links keeping the filters of the page', () => {
        expect(filteredLink.selector).toBe('a[data-filtered-href]')
    })

    test('leaves the SEO href to crawlers', () => {
        const link = fakeLink()
        filteredLink.connect(link)

        expect(link.getAttribute('href')).toBe(SEO_HREF)
    })

    test.each(['pointerdown', 'focus'])('gives the filtered href to a visitor reaching for the link (%s)', (type) => {
        const link = fakeLink()
        filteredLink.connect(link)

        link.dispatch(type)

        expect(link.getAttribute('href')).toBe(FILTERED_HREF)
    })

    test('stops listening once disconnected', () => {
        const link = fakeLink()
        const cleanup = filteredLink.connect(link)

        cleanup()
        link.dispatch('pointerdown')
        link.dispatch('focus')

        expect(link.getAttribute('href')).toBe(SEO_HREF)
    })
})
