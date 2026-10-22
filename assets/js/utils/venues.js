/**
 * @typedef {Object} MapEvent an event of the agenda's map (location/agenda/_map.html.twig)
 * @property {string} name
 * @property {string} url
 * @property {?string} date the day of its session on show (Y-m-d)
 * @property {?number} venue the id of its venue, when it has one
 * @property {?string} venueName
 * @property {?number} lat
 * @property {?number} lng
 */

/**
 * @typedef {Object} MapVenue a marker of the map: one venue, and its events
 * @property {string} key
 * @property {?string} name
 * @property {number} lat
 * @property {number} lng
 * @property {MapEvent[]} events
 */

/** Whether a coordinate can be placed on a map: (0, 0) is the default of a venue never geocoded, off the coast of Africa */
function isPlaced(lat, lng) {
    return Number.isFinite(lat) && Number.isFinite(lng) && Math.abs(lat) <= 90 && Math.abs(lng) <= 180 && (lat || lng)
}

/**
 * One marker per venue, its events in the order of the agenda. Events without a venue of their own share a marker when
 * they stand at the same spot. Events that cannot be placed are left out.
 *
 * @param {MapEvent[]} events
 * @returns {MapVenue[]}
 */
export function groupByVenue(events) {
    /** @type {Map<string, MapVenue>} */
    const venues = new Map()
    for (const event of events) {
        if (!isPlaced(event.lat, event.lng)) {
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
