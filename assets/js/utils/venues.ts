/**
 * An event of the agenda's map (location/agenda/_map.html.twig)
 */
export interface MapEvent {
    name: string
    url: string
    /** The day of its session on show (Y-m-d) */
    date: string | null
    /** The id of its venue, when it has one */
    venue: number | null
    venueName: string | null
    lat: number | null
    lng: number | null
}

/**
 * A marker of the map: one venue, and its events
 */
export interface MapVenue {
    key: string
    name: string | null
    lat: number
    lng: number
    events: MapEvent[]
}

/** An event whose coordinate can be placed on a map */
type PlacedEvent = MapEvent & { lat: number; lng: number }

/** Whether a coordinate can be placed on a map: (0, 0) is the default of a venue never geocoded, off the coast of Africa */
function isPlaced(event: MapEvent): event is PlacedEvent {
    const { lat, lng } = event

    return (
        Number.isFinite(lat) &&
        Number.isFinite(lng) &&
        Math.abs(lat as number) <= 90 &&
        Math.abs(lng as number) <= 180 &&
        (lat !== 0 || lng !== 0)
    )
}

/**
 * One marker per venue, its events in the order of the agenda. Events without a venue of their own share a marker when
 * they stand at the same spot. Events that cannot be placed are left out.
 */
export function groupByVenue(events: MapEvent[]): MapVenue[] {
    const venues = new Map<string, MapVenue>()
    for (const event of events) {
        if (!isPlaced(event)) {
            continue
        }

        const key = event.venue ? `venue-${event.venue}` : `spot-${event.lat.toFixed(5)},${event.lng.toFixed(5)}`
        let venue = venues.get(key)
        if (!venue) {
            venue = { key, name: event.venueName, lat: event.lat, lng: event.lng, events: [] }
            venues.set(key, venue)
        }
        venue.events.push(event)
    }

    return [...venues.values()]
}
