/**
 * Tests for the bulk-actions listener
 * Run: pnpm test
 */

import type { App, Listener } from '@silarhi/app-js'
import { connectListener } from '@silarhi/app-js/testing'
import { afterEach, beforeEach, describe, expect, test, vi } from 'vitest'
import bulkActions, { selectionLabel } from '@/js/listeners/bulk-actions'
import formConfirm from '@/js/listeners/form-confirm'
import { fake } from '@/js/test/fake'

type SubmitHandler = (event: { submitter?: object; preventDefault: () => void }) => Promise<void> | undefined

// The import report's bulk form, as _status.html.twig writes it: its events in hidden fields, no checkbox
const BULK_FORM_ATTRIBUTES = [
    'data-bulk-form',
    'data-bulk-confirm-title',
    'data-bulk-confirm-message',
    'data-bulk-confirm-button',
]

// Element.matches() for the selectors of the form listeners (`form[<attribute>]`), the tests running without a DOM
const matches = (selector: string): boolean => {
    const attribute = /^form\[([\w-]+)\]$/.exec(selector)?.[1]
    if (undefined === attribute) {
        throw new TypeError(`The test cannot match "${selector}"`)
    }

    return BULK_FORM_ATTRIBUTES.includes(attribute)
}

// What the page's mount does: every form listener whose selector the form matches is connected to it
const mount = (confirmed: true | undefined) => {
    const submitHandlers: SubmitHandler[] = []
    const form = {
        id: 'import-bulk-form',
        hidden: false,
        dataset: {
            bulkForm: '',
            bulkConfirmTitle: 'Supprimer les événements de cet import ?',
            bulkConfirmMessage: 'Les 3 événements importés seront supprimés.',
            bulkConfirmButton: 'Supprimer',
        },
        querySelector: () => null,
        addEventListener: (_type: 'submit', handler: SubmitHandler) => submitHandlers.push(handler),
        removeEventListener: () => {},
        append: vi.fn(),
        submit: vi.fn(),
    }
    const modalManager = { createConfirm: vi.fn((_params: object) => Promise.resolve(confirmed)) }
    const app = fake<App>({ get: () => modalManager })
    const listeners: Listener<HTMLFormElement>[] = [formConfirm, bulkActions]
    for (const listener of listeners.filter((listener) => matches(listener.selector))) {
        connectListener(listener, fake<HTMLFormElement>(form), app)
    }

    // A click on one of its buttons: every submit handler bound to the form runs, whichever listener bound it
    const submit = (action: string) => {
        const event = { submitter: Object.assign(new HTMLButtonElement(), { value: action }), preventDefault: vi.fn() }
        const answered = Promise.all(submitHandlers.map((handler) => handler(event)))

        return { event, answered }
    }

    return { form, modalManager, submit, submitHandlers }
}

describe('bulk actions', () => {
    test('targets the bulk forms', () => {
        expect(bulkActions.selector).toBe('form[data-bulk-form]')
    })

    test.each([
        [1, '1 événement sélectionné'],
        [3, '3 événements sélectionnés'],
        [1200, '1 200 événements sélectionnés'],
    ])('words %d picked events', (count, expected) => {
        expect(selectionLabel(count)).toBe(expected)
    })
})

describe('a bulk form, with the other form listeners', () => {
    beforeEach(() => {
        vi.stubGlobal('HTMLButtonElement', class {})
        vi.stubGlobal('HTMLInputElement', class {})
        vi.stubGlobal(
            'document',
            fake<Document>({
                querySelector: () => null,
                querySelectorAll: () => [],
                addEventListener: () => {},
                removeEventListener: () => {},
                createElement: () => ({}),
            })
        )
    })

    afterEach(() => {
        vi.unstubAllGlobals()
    })

    test('is left to bulk-actions: form-confirm does not ask on its buttons', () => {
        const { submitHandlers } = mount(true)

        expect(submitHandlers).toHaveLength(1)
    })

    test.each(['publish', 'draft'])('sends "%s" at once, with the button naming it', async (action) => {
        const { form, modalManager, submit } = mount(true)

        const { event, answered } = submit(action)
        await answered

        expect(modalManager.createConfirm).not.toHaveBeenCalled()
        expect(event.preventDefault).not.toHaveBeenCalled()
        expect(form.submit).not.toHaveBeenCalled()
    })

    test('asks once before deleting, in its own words, then sends the action', async () => {
        const { form, modalManager, submit } = mount(true)

        const { event, answered } = submit('delete')
        await answered

        expect(event.preventDefault).toHaveBeenCalledOnce()
        expect(modalManager.createConfirm).toHaveBeenCalledExactlyOnceWith({
            title: 'Supprimer les événements de cet import ?',
            text: 'Les 3 événements importés seront supprimés.',
            confirmButtonText: 'Supprimer',
        })
        expect(form.append).toHaveBeenCalledExactlyOnceWith(
            expect.objectContaining({ type: 'hidden', name: 'action', value: 'delete' })
        )
        expect(form.submit).toHaveBeenCalledOnce()
    })

    test('keeps the events when the dialog is dismissed', async () => {
        const { form, submit } = mount(undefined)

        await submit('delete').answered

        expect(form.append).not.toHaveBeenCalled()
        expect(form.submit).not.toHaveBeenCalled()
    })
})
