/**
 * Tests for the transfer-toasts listener
 * Run: pnpm test
 */

import { describe, expect, test } from 'vitest'
import { transferToast } from '@/js/listeners/transfer-toasts'

const PAGES = '/espace-perso/organisateurs/le-bikini'

describe('transferToast', () => {
    test('points a checked import to its report', () => {
        expect(transferToast({ '@id': `https://by-night.test${PAGES}/imports/4`, status: 'checked' })).toEqual({
            icon: 'success',
            message: 'Votre fichier est vérifié : le bilan vous attend.',
            url: `${PAGES}/imports/4`,
        })
    })

    test('points a written export to the export page of its publisher, where it is downloaded', () => {
        expect(transferToast({ '@id': `https://by-night.test${PAGES}/exports/7`, status: 'completed' })?.url).toBe(
            `${PAGES}/exports`
        )
    })

    test('tells an export by its path, whatever host the worker wrote it with', () => {
        expect(transferToast({ '@id': `https://by-night.fr${PAGES}/exports/7`, status: 'failed' })?.message).toBe(
            'Votre export n’a pas pu être écrit.'
        )
    })

    test.each([['checking'], ['importing'], ['cancelled']])('says nothing of an import %s', (status) => {
        expect(transferToast({ '@id': `https://by-night.test${PAGES}/imports/4`, status })).toBeNull()
    })
})
