/**
 * Tests for the feedback listener
 * Run: pnpm test
 */

import type { App } from '@silarhi/app-js'
import { afterEach, beforeEach, describe, expect, test, vi } from 'vitest'
import feedback from '@/js/listeners/feedback'
import { fake } from '@/js/test/fake'
import { connectListener } from '@/js/test/listener'

interface AjaxOptions {
    url: string
    type: string
    data: string
}

// A jqXHR stand-in that settles synchronously with the given outcome
interface Request {
    done: (callback: (payload: unknown) => void) => Request
    fail: (callback: (payload: unknown) => void) => Request
    always: (callback: () => void) => Request
}

// The Node environment has no DOM: jQuery's ajax and Tabler's widgets are faked. The widgets are classes, which the
// listener checks its instances against
const { ajax, modal, alert, Modal, Alert } = vi.hoisted(() => {
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

    const ajax: { options: AjaxOptions | null; request: Request | null } = { options: null, request: null }

    return { ajax, modal, alert, Modal, Alert }
})

vi.mock('jquery', () => ({
    default: {
        ajax: (options: AjaxOptions) => {
            ajax.options = options
            return ajax.request
        },
    },
}))

vi.mock('@tabler/core', () => ({
    Modal,
    Alert,
}))

const request = (outcome: 'done' | 'fail', payload: unknown) => {
    const jqXHR: Request = {
        done: (callback) => {
            if ('done' === outcome) callback(payload)
            return jqXHR
        },
        fail: (callback) => {
            if ('fail' === outcome) callback(payload)
            return jqXHR
        },
        always: (callback) => {
            callback()
            return jqXHR
        },
    }
    return jqXHR
}

type Handler = (event?: { preventDefault: () => void }) => void

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

    const submit = () => {
        const event = { preventDefault: vi.fn() }
        form.listeners.submit?.(event)
        return event
    }

    return { modalEl, form, messageInput, errorEl, submitBtn, toastManager, cleanup, submit }
}

describe('feedback listener', () => {
    beforeEach(() => {
        ajax.options = null
        ajax.request = request('done', { message: 'Merci !' })
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

    test('posts the trimmed message instead of submitting the form natively', () => {
        const { submit, toastManager } = connect('  Très bonne nouvelle version  ')

        const event = submit()

        expect(event.preventDefault).toHaveBeenCalled()
        expect(ajax.options).toMatchObject({ url: '/api/feedback', type: 'POST' })
        expect(JSON.parse(ajax.options?.data ?? '')).toEqual({ message: 'Très bonne nouvelle version' })
        expect(toastManager.createToast).toHaveBeenCalledWith('success', 'Merci !')
        expect(modal.hide).toHaveBeenCalled()
        expect(alert.close).toHaveBeenCalled()
    })

    test('rejects a message shorter than 10 characters without posting it', () => {
        const { submit, messageInput, errorEl } = connect('   court   ')

        submit()

        expect(ajax.options).toBeNull()
        expect(messageInput.classList.add).toHaveBeenCalledWith('is-invalid')
        expect(errorEl.textContent).toBe('Votre message doit faire au moins 10 caractères')
    })

    test('shows the violations of a rejected message and re-enables the button', () => {
        ajax.request = request('fail', { responseJSON: { violations: [{ message: 'A' }, { message: 'B' }] } })
        const { submit, errorEl, submitBtn } = connect('Un message assez long')

        submit()

        expect(errorEl.textContent).toBe('A, B')
        expect(submitBtn.disabled).toBe(false)
        expect(modal.hide).not.toHaveBeenCalled()
    })

    test('works without the banner, which the member may have dismissed', () => {
        vi.stubGlobal('document', { getElementById: () => null })
        const { submit } = connect('Un message assez long')

        submit()

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
