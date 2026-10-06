/**
 * Tests for the duplicate-check listener
 * Run: yarn test
 */

import { beforeEach, describe, expect, test, vi } from 'vitest'
import duplicateCheck from '@/js/listeners/duplicate-check'
import { confirmPublication } from '@/js/utils/duplicates'

vi.mock('@/js/utils/duplicates', () => ({
    confirmPublication: vi.fn(),
    duplicatesSearchBody: vi.fn(() => 'the form fields'),
}))

const URL = '/espace-perso/nouvelle-soiree/doublons'

const connect = () => {
    const listeners = {}
    const form = {
        dataset: { duplicatesUrl: URL },
        requestSubmit: vi.fn(),
        addEventListener: (type, listener) => {
            listeners[type] = listener
        },
        removeEventListener: (type, listener) => {
            if (listeners[type] === listener) {
                delete listeners[type]
            }
        },
    }
    const modalManager = {}
    const cleanup = duplicateCheck.connect(form, { app: { get: () => modalManager } })

    // The async handler returns a promise that settles once the check has been answered
    const submit = (submitter = null) => {
        const event = { submitter, preventDefault: vi.fn() }
        const answered = listeners.submit?.(event)
        return { event, answered }
    }

    return { form, modalManager, cleanup, submit }
}

const button = (attributes = []) => ({ hasAttribute: (name) => attributes.includes(name) })

describe('duplicate-check listener', () => {
    beforeEach(() => {
        vi.mocked(confirmPublication).mockReset()
    })

    test('targets the event forms that publish', () => {
        expect(duplicateCheck.selector).toBe('form[data-duplicates-url]')
    })

    test('asks before publishing, then sends the form with the button clicked', async () => {
        vi.mocked(confirmPublication).mockResolvedValue(true)
        const { form, modalManager, submit } = connect()
        const publish = button()

        const { event, answered } = submit(publish)
        await answered

        expect(event.preventDefault).toHaveBeenCalled()
        expect(confirmPublication).toHaveBeenCalledWith(modalManager, URL, { method: 'POST', body: 'the form fields' })
        expect(form.requestSubmit).toHaveBeenCalledWith(publish)

        // The submission sent again goes through
        const again = submit(publish)
        await again.answered
        expect(again.event.preventDefault).not.toHaveBeenCalled()
        expect(confirmPublication).toHaveBeenCalledOnce()
    })

    test('keeps the form as filled in when the member cancels', async () => {
        vi.mocked(confirmPublication).mockResolvedValue(false)
        const { form, submit } = connect()

        const { event, answered } = submit(button())
        await answered

        expect(event.preventDefault).toHaveBeenCalled()
        expect(form.requestSubmit).not.toHaveBeenCalled()
    })

    test('saves a draft without asking: it is not online', async () => {
        const { submit } = connect()

        const { event, answered } = submit(button(['data-skip-duplicates']))
        await answered

        expect(event.preventDefault).not.toHaveBeenCalled()
        expect(confirmPublication).not.toHaveBeenCalled()
    })

    test('asks once when the button is clicked again while checking', async () => {
        let answer
        vi.mocked(confirmPublication).mockReturnValue(
            new Promise((resolve) => {
                answer = resolve
            })
        )
        const { form, submit } = connect()

        const first = submit(button())
        const second = submit(button())
        answer(true)
        await Promise.all([first.answered, second.answered])

        expect(second.event.preventDefault).toHaveBeenCalled()
        expect(confirmPublication).toHaveBeenCalledOnce()
        expect(form.requestSubmit).toHaveBeenCalledOnce()
    })

    test('stops listening once disconnected', async () => {
        const { cleanup, submit } = connect()

        cleanup()
        submit(button())

        expect(confirmPublication).not.toHaveBeenCalled()
    })
})
