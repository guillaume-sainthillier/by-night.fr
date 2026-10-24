/**
 * Tests for the otp-autosubmit listener
 * Run: pnpm test
 */

import { describe, expect, test, vi } from 'vitest'
import otpAutosubmit from '@/js/listeners/otp-autosubmit'
import { fake } from '@/js/test/fake'
import { connectListener } from '@/js/test/listener'

// No DOM in the Node environment: the fake element records its listeners and finds its form
const connect = (form: { requestSubmit: () => void } | null) => {
    const listeners: Record<string, () => void> = {}
    const element = {
        addEventListener: vi.fn((type: string, listener: () => void) => {
            listeners[type] = listener
        }),
        removeEventListener: vi.fn((type: string) => {
            delete listeners[type]
        }),
        closest: () => form,
    }
    const cleanup = connectListener(otpAutosubmit, fake<HTMLElement>(element))

    return { listeners, element, cleanup }
}

describe('otp-autosubmit', () => {
    test('submits the form once Tabler says the code is complete', () => {
        const form = { requestSubmit: vi.fn() }
        const { listeners } = connect(form)

        listeners['complete.bs.otpInput']()

        expect(form.requestSubmit).toHaveBeenCalledOnce()
    })

    test('does nothing outside a form', () => {
        const { listeners } = connect(null)

        expect(() => listeners['complete.bs.otpInput']()).not.toThrow()
    })

    test('stops listening on cleanup', () => {
        const form = { requestSubmit: vi.fn() }
        const { listeners, cleanup } = connect(form)

        cleanup?.()

        expect(listeners['complete.bs.otpInput']).toBeUndefined()
    })
})
