/**
 * Tests for the feedback listener
 * Run: pnpm test
 */

import type { App } from '@silarhi/app-js'
import { connectListener } from '@silarhi/app-js/testing'
import { afterEach, beforeEach, describe, expect, test, vi } from 'vitest'
import feedback from '@/js/listeners/feedback'
import { fake } from '@/js/test/fake'

// The Node environment has no DOM: the request and Tabler's widgets are faked. The widgets are classes, which the
// listener checks its instances against
const { request, modal, alert, Modal, Alert, HttpError } = vi.hoisted(() => {
    class Modal {
        hide = () => {}
        static getOrCreateInstance = () => modal
    }
    class Alert {
        close = () => {}
        static getOrCreateInstance = () => alert
    }
    const modal = new Modal()
    const alert = new Alert()

    class HttpError extends Error {
        constructor(readonly body: unknown) {
            super('HTTP 422')
        }
    }

    // What the request was sent with, and what it answers: a body, or an HttpError it fails with
    const request: { sent: { method: string; url: string; data: unknown } | null; outcome: unknown } = {
        sent: null,
        outcome: null,
    }

    return { request, modal, alert, Modal, Alert, HttpError }
})

vi.mock('@silarhi/app-js/http', () => ({
    sendJson: async (method: string, url: string, data: unknown) => {
        request.sent = { method, url, data }
        if (request.outcome instanceof HttpError) {
            throw request.outcome
        }
        return request.outcome
    },
    errorBody: (error: unknown) => (error instanceof HttpError ? error.body : undefined),
}))

vi.mock('@tabler/core', () => ({
    Modal,
    Alert,
}))

type Handler = (event?: { preventDefault: () => void }) => Promise<void> | void

const element = <P extends object>(props: P) => {
    const listeners: Record<string, Handler | undefined> = {}
    return {
        ...props,
        listeners,
        classList: { add: vi.fn(), remove: vi.fn() },
        addEventListener: (type: string, handler: Handler) => {
            listeners[type] = handler
        },
        removeEventListener: (type: string) => {
            delete listeners[type]
        },
    }
}

const connect = (message: string) => {
    const messageInput = element({ value: message })
    const errorEl = element({ textContent: '' })
    const formParts: Record<string, object> = { '#feedback-message': messageInput, '#feedback-error': errorEl }
    const form = element({
        dataset: { action: '/api/feedback' },
        reset: vi.fn(),
        querySelector: (selector: string) => formParts[selector],
    })
    const submitBtn = element({ disabled: false })
    const modalParts: Record<string, object> = { '#feedback-form': form, 'button[type="submit"]': submitBtn }
    const modalEl = element({
        querySelector: (selector: string) => modalParts[selector],
    })
    const toastManager = { createToast: vi.fn() }
    const cleanup = connectListener(feedback, fake<HTMLElement>(modalEl), fake<App>({ get: () => toastManager }))

    // Settles once the answer is handled
    const submit = async () => {
        const event = { preventDefault: vi.fn() }
        await form.listeners.submit?.(event)
        return event
    }

    return { modalEl, form, messageInput, errorEl, submitBtn, toastManager, cleanup, submit }
}

describe('feedback listener', () => {
    beforeEach(() => {
        request.sent = null
        request.outcome = { message: 'Merci !' }
        modal.hide = vi.fn()
        alert.close = vi.fn()
        vi.stubGlobal('document', { getElementById: () => ({}) })
    })

    afterEach(() => {
        vi.unstubAllGlobals()
    })

    test('targets the feedback modal, on every page of the layout', () => {
        expect(feedback.selector).toBe('#feedbackModal')
    })

    test('posts the trimmed message instead of submitting the form natively', async () => {
        const { submit, toastManager } = connect('  Très bonne nouvelle version  ')

        const event = await submit()

        expect(event.preventDefault).toHaveBeenCalled()
        expect(request.sent).toEqual({
            method: 'POST',
            url: '/api/feedback',
            data: { message: 'Très bonne nouvelle version' },
        })
        expect(toastManager.createToast).toHaveBeenCalledWith('success', 'Merci !')
        expect(modal.hide).toHaveBeenCalled()
        expect(alert.close).toHaveBeenCalled()
    })

    test('rejects a message shorter than 10 characters without posting it', async () => {
        const { submit, messageInput, errorEl } = connect('   court   ')

        await submit()

        expect(request.sent).toBeNull()
        expect(messageInput.classList.add).toHaveBeenCalledWith('is-invalid')
        expect(errorEl.textContent).toBe('Votre message doit faire au moins 10 caractères')
    })

    test('shows the violations of a rejected message and re-enables the button', async () => {
        request.outcome = new HttpError({ violations: [{ message: 'A' }, { message: 'B' }] })
        const { submit, errorEl, submitBtn } = connect('Un message assez long')

        await submit()

        expect(errorEl.textContent).toBe('A, B')
        expect(submitBtn.disabled).toBe(false)
        expect(modal.hide).not.toHaveBeenCalled()
    })

    test('works without the banner, which the member may have dismissed', async () => {
        vi.stubGlobal('document', { getElementById: () => null })
        const { submit } = connect('Un message assez long')

        await submit()

        expect(modal.hide).toHaveBeenCalled()
        expect(alert.close).not.toHaveBeenCalled()
    })

    test('resets the form once the modal is hidden, and unbinds on cleanup', () => {
        const { modalEl, form, errorEl, cleanup } = connect('Un message assez long')
        errorEl.textContent = 'Erreur'

        modalEl.listeners['hidden.bs.modal']?.()

        expect(form.reset).toHaveBeenCalled()
        expect(errorEl.textContent).toBe('')

        cleanup?.()

        expect(form.listeners).toEqual({})
        expect(modalEl.listeners).toEqual({})
    })
})
