/**
 * The live progress of a member's imports and exports. Their worker publishes each step on the member's Mercure
 * channel (TransferNotifier): the whole state of the job, under the job's IRI ("@id"). The pages render that
 * state on the server, so an update only tells them to read their fragment again.
 */

interface TransferStateBase {
    /** The job's IRI */
    '@id': string
    id: number
    status: string
    statusLabel: string
    running: boolean
    percent: number
}

/** An import's state, as TransferNotifier::importState() publishes it */
interface ImportState extends TransferStateBase {
    events: number
    processed: number
    valid: number
    invalid: number
    warnings: number
    created: number
    updated: number
    unchanged: number
    failed: number
    skipped: number
}

/** An export's state, as TransferNotifier::exportState() publishes it */
interface ExportState extends TransferStateBase {
    total: number
    exported: number
}

export type TransferState = ImportState | ExportState

/** What followTransfers() reads the updates from: an EventSource, or a double of it */
interface TransferSource {
    addEventListener(type: string, listener: (event: MessageEvent<string>) => void): void
    close(): void
}

export interface FollowTransfersOptions {
    /**
     * the hub's URL, matching the member's channel (the Twig mercure() function, which sets the cookie the browser
     * sends along)
     */
    hub: string
    /** only the updates of this job; every job of the member when null */
    iri?: string | null
    /** the job's new state, null when the stream (re)opens */
    onChange: (state: TransferState | null) => void
    EventSourceImpl?: new (url: string) => TransferSource
}

/** The SSE event types of the channel's updates */
const TRANSFER_EVENTS = ['event-import', 'event-export']

/**
 * Calls `onChange` when a job changes, and each time the stream (re)opens: the hub keeps no history, so what
 * happened before the subscription or while it was down is caught up by reading the page's state again.
 *
 * @returns closes the stream
 */
export function followTransfers({
    hub,
    iri = null,
    onChange,
    EventSourceImpl = EventSource,
}: FollowTransfersOptions): () => void {
    const source = new EventSourceImpl(hub)
    const onUpdate = (event: MessageEvent<string>) => {
        let state: TransferState
        try {
            state = JSON.parse(event.data) as TransferState
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
 */
export function coalesce(load: () => Promise<void>): () => Promise<void> {
    let running: Promise<void> | null = null
    let again = false

    const run = (): Promise<void> => {
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
