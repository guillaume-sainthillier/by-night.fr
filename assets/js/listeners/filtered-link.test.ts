/**
 * Tests for the filtered-link listener
 * Run: pnpm test
 */

import { describe, expect, test } from 'vitest'
import filteredLink from '@/js/listeners/filtered-link'
import { fake } from '@/js/test/fake'

const SEO_HREF = '/toulouse/agenda/sortir/concert'
const FILTERED_HREF = '/toulouse/agenda/sortir-a/le-bikini?type=concert'

type Handler = () => void

const fakeLink = () => {
    const listeners: Record<string, Handler | undefined> = {}
    const attributes: Record<string, string> = { href: SEO_HREF }
    return {
        dataset: { filteredHref: FILTERED_HREF },
        getAttribute: (name: string) => attributes[name],
        setAttribute: (name: string, value: string) => {
            attributes[name] = value
        },
        addEventListener: (type: string, listener: Handler) => {
            listeners[type] = listener
        },
        removeEventListener: (type: string, listener: Handler) => {
            if (listeners[type] === listener) {
                delete listeners[type]
            }
        },
        dispatch: (type: string) => listeners[type]?.(),
    }
}

describe('filtered-link listener', () => {
    test('targets the links keeping the filters of the page', () => {
        expect(filteredLink.selector).toBe('a[data-filtered-href]')
    })

    test('leaves the SEO href to crawlers', () => {
        const link = fakeLink()
        filteredLink.connect(fake<HTMLAnchorElement>(link))

        expect(link.getAttribute('href')).toBe(SEO_HREF)
    })

    test.each(['pointerdown', 'focus'])('gives the filtered href to a visitor reaching for the link (%s)', (type) => {
        const link = fakeLink()
        filteredLink.connect(fake<HTMLAnchorElement>(link))

        link.dispatch(type)

        expect(link.getAttribute('href')).toBe(FILTERED_HREF)
    })

    test('stops listening once disconnected', () => {
        const link = fakeLink()
        const cleanup = filteredLink.connect(fake<HTMLAnchorElement>(link))

        cleanup()
        link.dispatch('pointerdown')
        link.dispatch('focus')

        expect(link.getAttribute('href')).toBe(SEO_HREF)
    })
})
