/**
 * Tests for the video-facade listener
 * Run: pnpm test
 */

import { afterEach, describe, expect, test, vi } from 'vitest'
import videoFacade from '@/js/listeners/video-facade'

const fakeButton = () => {
    const listeners = {}
    return {
        addEventListener: (type, listener) => {
            listeners[type] = listener
        },
        removeEventListener: (type, listener) => {
            if (listeners[type] === listener) {
                delete listeners[type]
            }
        },
        click: () => listeners.click?.(),
        listeners,
    }
}

const fakePlaceholder = (src) => {
    const button = fakeButton()
    return {
        button,
        dataset: { videoFacade: src },
        querySelector: () => button,
        replaceChildren: vi.fn(),
    }
}

afterEach(() => {
    vi.unstubAllGlobals()
})

describe('video-facade listener', () => {
    test('targets the placeholders of the video players', () => {
        expect(videoFacade.selector).toBe('[data-video-facade]')
    })

    test('loads nothing until the click, then swaps in the playing player', () => {
        const createElement = vi.fn(() => ({ style: {} }))
        vi.stubGlobal('document', { createElement })
        const placeholder = fakePlaceholder('https://www.youtube-nocookie.com/embed/abc?start=10')

        videoFacade.connect(placeholder)
        expect(createElement).not.toHaveBeenCalled()

        placeholder.button.click()

        expect(createElement).toHaveBeenCalledWith('iframe')
        const [frame] = placeholder.replaceChildren.mock.calls[0]
        expect(frame.src).toBe('https://www.youtube-nocookie.com/embed/abc?start=10&autoplay=1')
        expect(frame.allowFullscreen).toBe(true)
    })

    test('stops listening once disconnected', () => {
        const placeholder = fakePlaceholder('https://www.youtube-nocookie.com/embed/abc')
        const cleanup = videoFacade.connect(placeholder)

        cleanup()

        expect(placeholder.button.listeners.click).toBeUndefined()
    })
})
