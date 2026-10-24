/**
 * Tests for the share listener
 * Run: pnpm test
 */

import type { App } from '@silarhi/app-js'
import { afterEach, describe, expect, test, vi } from 'vitest'
import share from '@/js/listeners/share'
import { fake } from '@/js/test/fake'

type Handler = () => void

const connect = ({ sheet, nativeOnly = false }: { sheet: boolean; nativeOnly?: boolean }) => {
    const shareSheet = vi.fn(() => Promise.resolve())
    const writeText = vi.fn(() => Promise.resolve())
    vi.stubGlobal('navigator', { clipboard: { writeText }, ...(sheet ? { share: shareSheet } : {}) })
    vi.stubGlobal('window', { matchMedia: () => ({ matches: true }) })

    const handlers: Record<string, Handler | undefined> = {}
    const fallback = { hidden: false }
    const button = {
        hidden: nativeOnly,
        dataset: {
            shareUrl: 'https://by-night.fr/toulouse/soiree/concert--42',
            shareTitle: 'Concert',
            ...(nativeOnly ? { shareNativeOnly: '' } : {}),
        },
        closest: () => ({ querySelectorAll: () => [fallback] }),
        addEventListener: vi.fn((type: string, handler: Handler) => {
            handlers[type] = handler
        }),
        removeEventListener: vi.fn(),
    }
    const toastManager = { createToast: vi.fn() }
    share.connect(fake<HTMLButtonElement>(button), { app: fake<App>({ get: () => toastManager }) })

    return { button, fallback, handlers, shareSheet, writeText, toastManager }
}

describe('share listener', () => {
    afterEach(() => {
        vi.unstubAllGlobals()
    })

    test('opens the share sheet of the phone', () => {
        const { handlers, shareSheet, writeText } = connect({ sheet: true })

        handlers.click?.()

        expect(shareSheet).toHaveBeenCalledWith({
            title: 'Concert',
            url: 'https://by-night.fr/toulouse/soiree/concert--42',
        })
        expect(writeText).not.toHaveBeenCalled()
    })

    test('copies the address without a share sheet', async () => {
        const { handlers, writeText, toastManager } = connect({ sheet: false })

        handlers.click?.()
        await new Promise((resolve) => setTimeout(resolve))

        expect(writeText).toHaveBeenCalledWith('https://by-night.fr/toulouse/soiree/concert--42')
        expect(toastManager.createToast).toHaveBeenCalledWith('success', 'Lien copié !')
    })

    test('replaces the share menu with the share sheet', () => {
        const { button, fallback } = connect({ sheet: true, nativeOnly: true })

        expect(button.hidden).toBe(false)
        expect(fallback.hidden).toBe(true)
    })

    test('leaves the share menu to a computer', () => {
        const { button, fallback, handlers } = connect({ sheet: false, nativeOnly: true })

        expect(button.hidden).toBe(true)
        expect(fallback.hidden).toBe(false)
        expect(handlers.click).toBeUndefined()
    })
})
