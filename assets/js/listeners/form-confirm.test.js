/**
 * Tests for the form-confirm listener
 * Run: yarn test
 */

import { beforeEach, describe, expect, test, vi } from 'vitest'
import formConfirm from '@/js/listeners/form-confirm'

// jQuery needs a DOM, which the Node environment lacks: the fake records the handlers bound by namespace
const { $, handlers } = vi.hoisted(() => {
    const handlers = {}
    const $ = () => ({
        on: (events, handler) => {
            handlers[events] = handler
        },
        off: (namespace) => {
            Object.keys(handlers)
                .filter((events) => events.endsWith(namespace))
                .forEach((events) => {
                    delete handlers[events]
                })
        },
    })
    return { $, handlers }
})

vi.mock('jquery', () => ({ default: $ }))

const connect = (dataset, confirmed) => {
    const form = { dataset, submit: vi.fn() }
    const modalManager = { createConfirm: vi.fn(() => Promise.resolve(confirmed)) }
    const cleanup = formConfirm.connect(form, { app: { get: () => modalManager } })

    return { form, modalManager, cleanup }
}

// The async handler returns a promise that settles once the dialog has been answered
const submit = () => {
    const event = { preventDefault: vi.fn() }
    const answered = handlers['submit.confirm'](event)
    return { event, answered }
}

describe('form-confirm listener', () => {
    beforeEach(() => {
        Object.keys(handlers).forEach((events) => {
            delete handlers[events]
        })
    })

    test('targets forms carrying a confirmation message', () => {
        expect(formConfirm.selector).toBe('form[data-confirm-message]')
    })

    test('asks with the texts of the form and submits it once confirmed', async () => {
        const { form, modalManager } = connect(
            { confirmTitle: 'Supprimer ?', confirmMessage: 'Tout sera perdu.', confirmButton: 'Supprimer' },
            true
        )

        const { event, answered } = submit()

        expect(event.preventDefault).toHaveBeenCalledOnce()
        expect(modalManager.createConfirm).toHaveBeenCalledExactlyOnceWith({
            title: 'Supprimer ?',
            text: 'Tout sera perdu.',
            confirmButtonText: 'Supprimer',
        })
        expect(form.submit).not.toHaveBeenCalled()

        await answered

        expect(form.submit).toHaveBeenCalledOnce()
    })

    test('keeps the form when the dialog is dismissed', async () => {
        const { form } = connect({ confirmMessage: 'Tout sera perdu.' }, undefined)

        await submit().answered

        expect(form.submit).not.toHaveBeenCalled()
    })

    test("leaves SweetAlert's title and button label alone when the form sets none", () => {
        const { modalManager } = connect({ confirmMessage: 'Tout sera perdu.' }, true)

        submit()

        // Strict: toHaveBeenCalledWith() would overlook a title or a label passed as undefined
        expect(modalManager.createConfirm).toHaveBeenCalledOnce()
        expect(modalManager.createConfirm.mock.calls[0][0]).toStrictEqual({ text: 'Tout sera perdu.' })
    })

    test('unbinds its submit handler on cleanup', () => {
        const { cleanup } = connect({ confirmMessage: 'Tout sera perdu.' }, true)

        cleanup()

        expect(handlers).toEqual({})
    })
})
