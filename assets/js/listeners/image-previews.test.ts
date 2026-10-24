/**
 * Tests for the image-previews listener
 * Run: pnpm test
 */

import { beforeEach, describe, expect, test, vi } from 'vitest'
import imagePreviews from '@/js/listeners/image-previews'
import { fake } from '@/js/test/fake'
import { connectListener } from '@/js/test/listener'

// Fancybox is a jQuery plugin with CSS: it cannot load in Node, so the lazy import is mocked
const { create, lightbox } = vi.hoisted(() => {
    const lightbox = { open: vi.fn(), destroy: vi.fn() }
    return { lightbox, create: vi.fn((_options: object) => lightbox) }
})

vi.mock('@/js/services/ui/FancyboxService', () => ({ create }))

// The listener reads nothing of the element: it only hands it to Fancybox
const connect = (listener: typeof imagePreviews, element: object = {}) =>
    connectListener(listener, fake<HTMLElement>(element))

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

        connect(imagePreviews, element)
        await vi.dynamicImportSettled()

        expect(create).toHaveBeenCalledExactlyOnceWith({ element })
    })

    test('destroys the lightbox on cleanup', async () => {
        const cleanup = connect(imagePreviews)
        await vi.dynamicImportSettled()

        cleanup?.()

        expect(lightbox.destroy).toHaveBeenCalledOnce()
    })

    test('creates no lightbox when unmounted before Fancybox has loaded', async () => {
        const cleanup = connect(imagePreviews)
        cleanup?.()
        await vi.dynamicImportSettled()

        expect(create).not.toHaveBeenCalled()
        expect(lightbox.destroy).not.toHaveBeenCalled()
    })

    // Vitest fails the run on an unhandled rejection, as Sentry reported it (BY-NIGHTFR-6C4)
    test('leaves the plain link when Fancybox cannot be fetched', async () => {
        vi.resetModules()
        vi.doMock('@/js/services/ui/FancyboxService', () => {
            throw new Error('Unable to preload CSS for /build/FancyboxService.css')
        })
        const { default: listener } = await import('@/js/listeners/image-previews')

        const cleanup = connect(listener)
        await vi.dynamicImportSettled()
        cleanup?.()

        expect(create).not.toHaveBeenCalled()
        vi.doUnmock('@/js/services/ui/FancyboxService')
    })
})
