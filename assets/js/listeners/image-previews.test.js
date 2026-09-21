/**
 * Tests for the image-previews listener
 * Run: yarn test
 */

import { beforeEach, describe, expect, test, vi } from 'vitest'
import imagePreviews from '@/js/listeners/image-previews'

// Fancybox is a jQuery plugin with CSS: it cannot load in Node, so the lazy import is mocked
const { create, lightbox } = vi.hoisted(() => {
    const lightbox = { open: vi.fn(), destroy: vi.fn() }
    return { lightbox, create: vi.fn(() => lightbox) }
})

vi.mock('@/js/services/ui/FancyboxService', () => ({ create }))

describe('image-previews listener', () => {
    beforeEach(() => {
        create.mockClear()
        lightbox.destroy.mockClear()
    })

    test('targets gallery links', () => {
        expect(imagePreviews.selector).toBe('.image-gallery')
    })

    test('opens the element in a lightbox once Fancybox has loaded', async () => {
        const element = { className: 'image-gallery' }

        imagePreviews.connect(element)
        await vi.dynamicImportSettled()

        expect(create).toHaveBeenCalledExactlyOnceWith({ element })
    })

    test('destroys the lightbox on cleanup', async () => {
        const cleanup = imagePreviews.connect({})
        await vi.dynamicImportSettled()

        cleanup()

        expect(lightbox.destroy).toHaveBeenCalledOnce()
    })

    test('creates no lightbox when unmounted before Fancybox has loaded', async () => {
        const cleanup = imagePreviews.connect({})
        cleanup()
        await vi.dynamicImportSettled()

        expect(create).not.toHaveBeenCalled()
        expect(lightbox.destroy).not.toHaveBeenCalled()
    })
})
