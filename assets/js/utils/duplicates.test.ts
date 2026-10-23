/**
 * Tests for the likely duplicates check before publishing
 * Run: pnpm test
 */

import { afterEach, describe, expect, test, vi } from 'vitest'
import type ModalManager from '@/js/services/modals/ModalManager'
import { fake } from '@/js/test/fake'
import { confirmPublication, duplicatesSearchBody } from '@/js/utils/duplicates'

const URL = '/espace-perso/nouvelle-soiree/doublons'

const modal = (answer: boolean | undefined) => ({ createConfirm: vi.fn(() => Promise.resolve(answer)) })

// The modal manager answers what the member clicked, which the type narrows to true
const asManager = (modalManager: ReturnType<typeof modal>) => fake<Pick<ModalManager, 'createConfirm'>>(modalManager)

const response = (status: number, body = '', redirected = false) => ({
    status,
    redirected,
    text: () => Promise.resolve(body),
})

afterEach(() => {
    vi.unstubAllGlobals()
})

describe('confirmPublication', () => {
    test('publishes at once when nothing alike is online', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(() => Promise.resolve(response(204)))
        )
        const modalManager = modal(false)

        expect(await confirmPublication(asManager(modalManager), URL)).toBe(true)
        expect(modalManager.createConfirm).not.toHaveBeenCalled()
    })

    test.each([
        [true, true],
        [false, false],
        // Closed with its cross or Escape
        [undefined, false],
    ])('shows the likely duplicates and publishes on the answer %s: %s', async (answer, published) => {
        const fetch = vi.fn(() => Promise.resolve(response(200, '<a class="list-group-item">Soirée swing</a>')))
        vi.stubGlobal('fetch', fetch)
        const modalManager = modal(answer)
        const body = fake<FormData>({ form: 'data' })

        expect(await confirmPublication(asManager(modalManager), URL, { method: 'POST', body })).toBe(published)
        expect(fetch).toHaveBeenCalledWith(URL, expect.objectContaining({ method: 'POST', body }))
        expect(modalManager.createConfirm).toHaveBeenCalledWith(
            expect.objectContaining({
                html: '<a class="list-group-item">Soirée swing</a>',
                confirmButtonText: 'Publier quand même',
            })
        )
    })

    test.each([
        ['a network failure', () => Promise.reject(new TypeError('Failed to fetch'))],
        ['an error', () => Promise.resolve(response(500, 'Erreur'))],
        ['a lapsed session, redirected to the login page', () => Promise.resolve(response(200, '<form>', true))],
    ])('publishes as before after %s: the check only advises', async (_, fetch) => {
        vi.stubGlobal('fetch', vi.fn(fetch))
        const modalManager = modal(false)

        expect(await confirmPublication(asManager(modalManager), URL)).toBe(true)
        expect(modalManager.createConfirm).not.toHaveBeenCalled()
    })
})

describe('duplicatesSearchBody', () => {
    test('leaves the picture out', () => {
        // The Node environment has no form: the fake reads its fields from the object it is given
        vi.stubGlobal(
            'FormData',
            class {
                fields: Map<string, unknown>

                constructor(form: { fields: [string, unknown][] }) {
                    this.fields = new Map(form.fields)
                }

                entries() {
                    return this.fields.entries()
                }

                delete(name: string) {
                    this.fields.delete(name)
                }
            }
        )
        const picture = new File(['…'], 'affiche.jpg')

        const body = duplicatesSearchBody(
            fake<HTMLFormElement>({
                fields: [
                    ['app_event[name]', 'Soirée swing'],
                    ['app_event[imageFile][file]', picture],
                ],
            })
        )

        expect([...body.entries()]).toEqual([['app_event[name]', 'Soirée swing']])
    })
})
