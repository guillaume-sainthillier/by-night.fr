import { defineListener } from '@silarhi/app-js'

/** An event's properties, shown in the event breakdown of the tool */
export type EventData = Record<string, string>

declare global {
    interface Window {
        // The Umami tracker (templates/base.html.twig), when UMAMI_WEBSITE_ID is set: read by this file alone
        umami?: {
            track(name: string, data?: EventData): void
        }
    }
}

const PREFIX = 'data-analytics-'

/**
 * Sends an analytics event: the one place that calls the tool. Without the tracker (deferred, UMAMI_WEBSITE_ID unset,
 * an ad blocker) the event is dropped.
 */
export const track = (name: string, data?: EventData): void => {
    window.umami?.track(name, data)
}

/**
 * The properties of an element's event, its data-analytics-<prop> attributes but the name: data-analytics-seller="Fnac"
 * gives { seller: 'Fnac' }. The kebab-case of the attribute is kept, as Umami's own data-umami-event-* did.
 */
export const readEventData = (element: Element): EventData => {
    const data: EventData = {}
    for (const { name, value } of Array.from(element.attributes)) {
        if (name.startsWith(PREFIX) && name !== `${PREFIX}event`) {
            data[name.slice(PREFIX.length)] = value
        }
    }

    return data
}

/**
 * Count a click as an analytics event: its name in `data-analytics-event`, its properties in `data-analytics-<prop>`
 * (the ticketing links of an event page: "ticket-click" and its seller, read back by EventVisitSync). The templates
 * never name the tool, so it can change here alone. The tracker posts with `keepalive`: a link leaving the page is
 * still counted, without holding its navigation.
 */
export default defineListener<HTMLElement>({
    selector: '[data-analytics-event]',
    connect(element) {
        const onClick = () => track(element.getAttribute(`${PREFIX}event`) ?? '', readEventData(element))

        element.addEventListener('click', onClick)

        return () => element.removeEventListener('click', onClick)
    },
})
