import { describe, expect, it, vi } from 'vitest'
import { coalesce, followTransfers } from './transfers'

class FakeEventSource {
    constructor(url) {
        this.url = url
        this.listeners = {}
        this.closed = false
        FakeEventSource.last = this
    }

    addEventListener(type, listener) {
        ;(this.listeners[type] ??= []).push(listener)
    }

    emit(type, data) {
        for (const listener of this.listeners[type] ?? []) {
            listener({ data: undefined === data ? undefined : JSON.stringify(data) })
        }
    }

    close() {
        this.closed = true
    }
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

        FakeEventSource.last.emit('event-import', { '@id': 'https://by-night.test/imports/2', status: 'checking' })
        FakeEventSource.last.emit('event-import', { '@id': 'https://by-night.test/imports/1', status: 'checked' })

        expect(onChange).toHaveBeenCalledTimes(1)
        expect(onChange).toHaveBeenCalledWith({ '@id': 'https://by-night.test/imports/1', status: 'checked' })
    })

    it('reports every job of the member without an IRI, imports and exports', () => {
        const onChange = vi.fn()
        followTransfers({ hub: '/hub', onChange, EventSourceImpl: FakeEventSource })

        FakeEventSource.last.emit('event-import', { '@id': 'a' })
        FakeEventSource.last.emit('event-export', { '@id': 'b' })

        expect(onChange).toHaveBeenCalledTimes(2)
    })

    it('catches up each time the stream opens', () => {
        const onChange = vi.fn()
        followTransfers({ hub: '/hub', onChange, EventSourceImpl: FakeEventSource })

        FakeEventSource.last.emit('open')
        FakeEventSource.last.emit('open')

        expect(onChange).toHaveBeenCalledTimes(2)
    })

    it('closes the stream on cleanup', () => {
        const close = followTransfers({ hub: '/hub', onChange: () => {}, EventSourceImpl: FakeEventSource })

        close()

        expect(FakeEventSource.last.closed).toBe(true)
    })
})

describe('coalesce', () => {
    it('runs one load at a time, and one more for the changes that came meanwhile', async () => {
        let resolve
        const load = vi.fn(
            () =>
                new Promise((r) => {
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
