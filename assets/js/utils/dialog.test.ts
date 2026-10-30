import { describe, expect, it, vi } from 'vitest'
import { fake } from '@/js/test/fake'
import { loadErrorMessage, loadIntoDialog } from './dialog'

const { http, modal, Modal } = vi.hoisted(() => {
    class HttpError extends Error {
        constructor(readonly status: number) {
            super(`HTTP ${status}`)
        }
    }

    // What the request answers: a page, or an HttpError it fails with
    const http: { HttpError: typeof HttpError; outcome: string | Error } = { HttpError, outcome: '' }
    // A class, which the dialog checks its instance against
    class Modal {
        setError = vi.fn()
        static getOrCreateInstance = () => modal
    }
    const modal = new Modal()

    return { http, modal, Modal }
})

vi.mock('@silarhi/app-js/http', () => ({
    getHtml: async () => {
        if (http.outcome instanceof Error) {
            throw http.outcome
        }
        return http.outcome
    },
    statusOf: (error: unknown) => (error instanceof http.HttpError ? error.status : 0),
}))

vi.mock('@tabler/core', () => ({ Modal }))

describe('loadIntoDialog', () => {
    it('shows the page, then runs the callback', async () => {
        http.outcome = '<div class="modal-dialog">Me déconnecter</div>'
        const dialog = { innerHTML: '' }
        const onLoaded = vi.fn()

        await loadIntoDialog(fake<HTMLElement>(dialog), '/login', onLoaded)

        expect(dialog.innerHTML).toBe('<div class="modal-dialog">Me déconnecter</div>')
        expect(onLoaded).toHaveBeenCalledOnce()
        expect(modal.setError).not.toHaveBeenCalled()
    })

    it.each([
        [new http.HttpError(500), 500],
        [new TypeError('Failed to fetch'), 0],
    ])('shows an error instead of the spinner when the content cannot be loaded (%s)', async (error, status) => {
        http.outcome = error
        modal.setError.mockClear()
        const dialog = { innerHTML: 'spinner' }
        const onLoaded = vi.fn()

        await loadIntoDialog(fake<HTMLElement>(dialog), '/login', onLoaded)

        expect(onLoaded).not.toHaveBeenCalled()
        expect(dialog.innerHTML).toBe('spinner')
        expect(modal.setError).toHaveBeenCalledWith(loadErrorMessage(status))
    })
})

describe('loadErrorMessage', () => {
    it('names the HTTP error', () => {
        expect(loadErrorMessage(404)).toBe("Le contenu n'a pas pu être chargé (erreur 404), veuillez réessayer.")
    })

    it('tells a lost connection apart', () => {
        expect(loadErrorMessage(0)).toBe('La connexion a échoué, veuillez réessayer.')
    })
})
