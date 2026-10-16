/**
 * The live progress of a member's imports and exports. Their worker publishes each step on the member's Mercure
 * channel (TransferNotifier): the whole state of the job, under the job's IRI ("@id"). The pages render that
 * state on the server, so an update only tells them to read their fragment again.
 */

/** The SSE event types of the channel's updates */
const TRANSFER_EVENTS = ['event-import', 'event-export']

/**
 * Calls `onChange` when a job changes, and each time the stream (re)opens: the hub keeps no history, so what
 * happened before the subscription or while it was down is caught up by reading the page's state again.
 *
 * @param {Object} options
 * @param {string} options.hub the hub's URL, matching the member's channel (the Twig mercure() function, which
 *   sets the cookie the browser sends along)
 * @param {string|null} [options.iri] only the updates of this job; every job of the member when null
 * @param {() => void} options.onChange
 * @param {typeof EventSource} [options.EventSourceImpl]
 * @returns {() => void} closes the stream
 */
export function followTransfers({ hub, iri = null, onChange, EventSourceImpl = EventSource }) {
    const source = new EventSourceImpl(hub)
    const onUpdate = (event) => {
        let state
        try {
            state = JSON.parse(event.data)
        } catch {
            return
        }

        if (null === iri || state['@id'] === iri) {
            onChange(state)
        }
    }

    for (const type of TRANSFER_EVENTS) {
        source.addEventListener(type, onUpdate)
    }
    source.addEventListener('open', () => onChange(null))

    return () => source.close()
}

/**
 * Coalesces reloads: one request at a time, and one more after it if changes came in meanwhile, so a burst of
 * progress updates costs two requests, not one each.
 *
 * @param {() => Promise<void>} load
 * @returns {() => Promise<void>}
 */
export function coalesce(load) {
    let running = null
    let again = false

    const run = () => {
        if (running) {
            again = true

            return running
        }

        running = load()
            .catch(() => {})
            .finally(() => {
                running = null
                if (again) {
                    again = false
                    run()
                }
            })

        return running
    }

    return run
}
