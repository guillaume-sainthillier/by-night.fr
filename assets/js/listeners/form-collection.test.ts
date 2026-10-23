/**
 * Tests for the form-collection listener
 * Run: pnpm test
 */

import type { App } from '@silarhi/app-js'
import type { CollectionManager } from '@silarhi/app-js/form'
import { describe, expect, test, vi } from 'vitest'
import formCollection from '@/js/listeners/form-collection'
import { fake } from '@/js/test/fake'

describe('form-collection', () => {
    test('matches the add and remove buttons of the collection manager', () => {
        expect(formCollection.selector).toBe('.add-collection, .remove-collection')
    })

    test("hands each button to the collection manager's listener, with its cleanup", () => {
        const cleanup = vi.fn()
        const connect = vi.fn(() => cleanup)
        const get = vi.fn(() => fake<CollectionManager>({ listener: { connect } }))
        const app = fake<App>({ get })
        const button = fake<HTMLElement>({})

        const disconnect = formCollection.connect(button, { app })

        expect(get).toHaveBeenCalledWith('collectionManager')
        expect(connect).toHaveBeenCalledWith(button, { app })
        expect(disconnect).toBe(cleanup)
    })
})
