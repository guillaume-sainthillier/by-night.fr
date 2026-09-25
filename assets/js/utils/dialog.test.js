import { describe, expect, it, vi } from 'vitest'
import { loadErrorMessage, loadIntoDialog } from './dialog'

/**
 * A stand-in for the jQuery dialog: load() answers at once with the given outcome.
 */
function fakeDialog(status, httpStatus) {
    return {
        load: vi.fn((_url, complete) => complete('', status, { status: httpStatus })),
        modal: vi.fn(),
    }
}

describe('loadIntoDialog', () => {
    it('runs the callback once the content is loaded', () => {
        const $dialog = fakeDialog('success', 200)
        const onLoaded = vi.fn()

        loadIntoDialog($dialog, '/login', onLoaded)

        expect($dialog.load).toHaveBeenCalledWith('/login', expect.any(Function))
        expect(onLoaded).toHaveBeenCalledOnce()
        expect($dialog.modal).not.toHaveBeenCalled()
    })

    it('shows an error instead of the spinner when the content cannot be loaded', () => {
        const $dialog = fakeDialog('error', 500)
        const onLoaded = vi.fn()

        loadIntoDialog($dialog, '/login', onLoaded)

        expect(onLoaded).not.toHaveBeenCalled()
        expect($dialog.modal).toHaveBeenCalledWith('setError', loadErrorMessage(500))
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
