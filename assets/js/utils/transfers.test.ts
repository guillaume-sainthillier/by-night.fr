import { describe, expect, it, vi } from 'vitest'
import { fake } from '@/js/test/fake'
import { coalesce, followTransfers } from './transfers'

type Listener = (event: MessageEvent<string>) => void

class FakeEventSource {
    static last: FakeEventSource | null = null

    url: string
    listeners: Record<string, Listener[]> = {}
    closed = false

    constructor(url: string) {
        this.url = url
        FakeEventSource.last = this
    }

    addEventListener(type: string, listener: Listener) {
        ;(this.listeners[type] ??= []).push(listener)
    }

    emit(type: string, data?: object) {
        for (const listener of this.listeners[type] ?? []) {
            listener(fake<MessageEvent<string>>({ data: undefined === data ? undefined : JSON.stringify(data) }))
        }
    }

    close() {
        this.closed = true
    }
}

// The stream the last followTransfers() opened
const source = () => {
    if (!FakeEventSource.last) {
        throw new Error('No stream opened')
    }

    return FakeEventSource.last
}

describe('followTransfers', () => {
    it('reports the updates of the job it follows only', () => {
        const onChange = vi.fn()
        followTransfers({
            hub: '/hub',
            iri: 'https://by-night.test/imports/1',
            onChange,
            EventSourceImpl: FakeEventSource,
        })

        source().emit('event-import', { '@id': 'https://by-night.test/imports/2', status: 'checking' })
        source().emit('event-import', { '@id': 'https://by-night.test/imports/1', status: 'checked' })

        expect(onChange).toHaveBeenCalledTimes(1)
        expect(onChange).toHaveBeenCalledWith({ '@id': 'https://by-night.test/imports/1', status: 'checked' })
    })

    it('reports every job of the member without an IRI, imports and exports', () => {
        const onChange = vi.fn()
        followTransfers({ hub: '/hub', onChange, EventSourceImpl: FakeEventSource })

        source().emit('event-import', { '@id': 'a' })
        source().emit('event-export', { '@id': 'b' })

        expect(onChange).toHaveBeenCalledTimes(2)
    })

    it('catches up each time the stream opens', () => {
        const onChange = vi.fn()
        followTransfers({ hub: '/hub', onChange, EventSourceImpl: FakeEventSource })

        source().emit('open')
        source().emit('open')

        expect(onChange).toHaveBeenCalledTimes(2)
    })

    it('closes the stream on cleanup', () => {
        const close = followTransfers({ hub: '/hub', onChange: () => {}, EventSourceImpl: FakeEventSource })

        close()

        expect(source().closed).toBe(true)
    })
})

describe('coalesce', () => {
    it('runs one load at a time, and one more for the changes that came meanwhile', async () => {
        let resolve = () => {}
        const load = vi.fn(
            () =>
                new Promise<void>((r) => {
                    resolve = r
                })
        )
        const reload = coalesce(load)

        const first = reload()
        reload()
        reload()
        expect(load).toHaveBeenCalledTimes(1)

        resolve()
        await first
        expect(load).toHaveBeenCalledTimes(2)
    })
})
