/**
 * Tests for the copy-url listener
 * Run: pnpm test
 */

import type { App } from '@silarhi/app-js'
import { connectListener } from '@silarhi/app-js/testing'
import { afterEach, describe, expect, test, vi } from 'vitest'
import copyUrl from '@/js/listeners/copy-url'
import { fake } from '@/js/test/fake'

type Handler = () => void

const connect = (url: string, writeText: (text: string) => Promise<void>) => {
    vi.stubGlobal('navigator', { clipboard: { writeText } })
    const handlers: Record<string, Handler | undefined> = {}
    const button = {
        dataset: { copyUrl: url },
        addEventListener: vi.fn((type: string, handler: Handler) => {
            handlers[type] = handler
        }),
        removeEventListener: vi.fn(),
    }
    const toastManager = { createToast: vi.fn() }
    const cleanup = connectListener(copyUrl, fake<HTMLButtonElement>(button), fake<App>({ get: () => toastManager }))

    return { button, handlers, toastManager, cleanup }
}

describe('copy-url listener', () => {
    afterEach(() => {
        vi.unstubAllGlobals()
    })

    test('copies the address of the button and says so', async () => {
        const writeText = vi.fn(() => Promise.resolve())
        const { handlers, toastManager } = connect('https://by-night.fr/calendrier/abc.ics', writeText)

        handlers.click?.()
        await new Promise((resolve) => setTimeout(resolve))

        expect(writeText).toHaveBeenCalledWith('https://by-night.fr/calendrier/abc.ics')
        expect(toastManager.createToast).toHaveBeenCalledWith('success', 'Lien copié !')
    })

    test('says when the browser refuses the copy', async () => {
        const writeText = vi.fn(() => Promise.reject(new Error('denied')))
        const { handlers, toastManager } = connect('https://by-night.fr/', writeText)

        handlers.click?.()
        await new Promise((resolve) => setTimeout(resolve))

        expect(toastManager.createToast).toHaveBeenCalledWith('error', 'Impossible de copier le lien')
    })

    test('stops listening once disconnected', () => {
        const { button, handlers, cleanup } = connect(
            'https://by-night.fr/',
            vi.fn(() => Promise.resolve())
        )

        cleanup()

        expect(button.removeEventListener).toHaveBeenCalledWith('click', handlers.click)
    })
})
