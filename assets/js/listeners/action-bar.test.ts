/**
 * Tests for the action-bar listener
 * Run: pnpm test
 */

import { afterEach, describe, expect, test, vi } from 'vitest'
import actionBar from '@/js/listeners/action-bar'
import { fake } from '@/js/test/fake'

type Observed = (entries: { isIntersecting: boolean; boundingClientRect: { bottom: number } }[]) => void

const connect = () => {
    const observed: { callback?: Observed } = {}
    const disconnect = vi.fn()
    vi.stubGlobal(
        'IntersectionObserver',
        class {
            constructor(callback: Observed) {
                observed.callback = callback
            }
            observe() {}
            disconnect = disconnect
        }
    )
    vi.stubGlobal('document', { querySelector: () => ({}) })
    const classes = new Set<string>()
    const bar = {
        dataset: { actionBar: '#event-tickets' },
        classList: { toggle: (name: string, on: boolean) => (on ? classes.add(name) : classes.delete(name)) },
    }
    const cleanup = actionBar.connect(fake<HTMLElement>(bar))

    return {
        classes,
        see: (isIntersecting: boolean, bottom: number) =>
            observed.callback?.([{ isIntersecting, boundingClientRect: { bottom } }]),
        disconnect,
        cleanup,
    }
}

describe('action-bar listener', () => {
    afterEach(() => {
        vi.unstubAllGlobals()
    })

    test('shows once its button has scrolled away above the screen, hides back at it', () => {
        const { classes, see } = connect()

        see(false, -10)
        expect(classes.has('is-visible')).toBe(true)

        see(true, 300)
        expect(classes.has('is-visible')).toBe(false)
    })

    test('stays hidden while its button is still below the screen', () => {
        const { classes, see } = connect()

        see(false, 1200)

        expect(classes.has('is-visible')).toBe(false)
    })

    test('stops watching once disconnected', () => {
        const { disconnect, cleanup } = connect()

        cleanup?.()

        expect(disconnect).toHaveBeenCalled()
    })
})
