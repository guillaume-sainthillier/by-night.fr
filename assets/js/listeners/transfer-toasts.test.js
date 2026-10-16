/**
 * Tests for the transfer-toasts listener
 * Run: pnpm test
 */

import { describe, expect, test } from 'vitest'
import { transferToast } from '@/js/listeners/transfer-toasts'

const EXPORTS = '/espace-perso/exports'

describe('transferToast', () => {
    test('points a checked import to its report', () => {
        expect(
            transferToast({ '@id': 'https://by-night.test/espace-perso/imports/4', status: 'checked' }, EXPORTS)
        ).toEqual({
            icon: 'success',
            message: 'Votre fichier est vérifié : le bilan vous attend.',
            url: '/espace-perso/imports/4',
        })
    })

    test('points a written export to the export page, where it is downloaded', () => {
        expect(
            transferToast({ '@id': 'https://by-night.test/espace-perso/exports/7', status: 'completed' }, EXPORTS)?.url
        ).toBe(EXPORTS)
    })

    test('tells an export by its path, whatever host the worker wrote it with', () => {
        expect(
            transferToast({ '@id': 'https://by-night.fr/espace-perso/exports/7', status: 'failed' }, EXPORTS)?.message
        ).toBe('Votre export n’a pas pu être écrit.')
    })

    test.each([['checking'], ['importing'], ['cancelled']])('says nothing of an import %s', (status) => {
        expect(transferToast({ '@id': 'https://by-night.test/espace-perso/imports/4', status }, EXPORTS)).toBeNull()
    })
})
