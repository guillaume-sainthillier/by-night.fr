import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import { plural } from '@/js/utils/plural'

/**
 * OpenStreetMap's own tiles: free with the attribution on the map and the site's Referer (no Referrer-Policy hides it),
 * for a map a visitor opens by hand, which is what https://operations.osmfoundation.org/policies/tiles/ allows. Move
 * to a tile provider of our own if the agenda's map ever loads on its own.
 */
const TILES = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'
const ATTRIBUTION = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'

/** Close enough to see the streets around a venue, and no closer than the tiles go */
const VENUE_ZOOM = 15

const dayFormat = new Intl.DateTimeFormat('fr', { weekday: 'short', day: 'numeric', month: 'short', timeZone: 'UTC' })

/**
 * The marker of a venue: its number of events in a disc.
 *
 * @param {import('@/js/utils/venues').MapVenue} venue
 */
function createIcon(venue) {
    return L.divIcon({
        className: '',
        html: `<span class="avatar avatar-sm rounded-circle bg-primary text-white shadow fw-bold">${venue.events.length}</span>`,
        iconSize: [32, 32],
        iconAnchor: [16, 16],
        popupAnchor: [0, -16],
    })
}

/**
 * The popup of a venue: its name, and a link to each of its events. Built as nodes, so that no name is read as HTML.
 *
 * @param {import('@/js/utils/venues').MapVenue} venue
 * @returns {HTMLElement}
 */
function createPopup(venue) {
    const popup = document.createElement('div')

    const title = document.createElement('div')
    title.className = 'fw-bold mb-1'
    title.textContent = venue.name || 'Lieu'
    const count = document.createElement('div')
    count.className = 'text-secondary small mb-2'
    count.textContent = plural(venue.events.length, { one: '# sortie', other: '# sorties' })
    popup.append(title, count)

    const list = document.createElement('ul')
    list.className = 'list-unstyled mb-0'
    for (const event of venue.events) {
        const item = document.createElement('li')
        item.className = 'mb-1'
        if (event.date) {
            const day = document.createElement('span')
            day.className = 'text-secondary me-1'
            day.textContent = dayFormat.format(new Date(`${event.date}T00:00:00Z`))
            item.append(day)
        }
        const link = document.createElement('a')
        link.href = event.url
        link.textContent = event.name
        item.append(link)
        list.append(item)
    }
    popup.append(list)

    return popup
}

/**
 * A map of the venues, framed on all of them.
 *
 * @param {{element: HTMLElement, venues: import('@/js/utils/venues').MapVenue[]}} options
 */
export function create({ element, venues }) {
    // The wheel scrolls the page: the map zooms with its buttons, a pinch or a double click
    const map = L.map(element, { scrollWheelZoom: false })
    L.tileLayer(TILES, { attribution: ATTRIBUTION, maxZoom: 19 }).addTo(map)

    const markers = venues.map((venue) =>
        L.marker([venue.lat, venue.lng], { icon: createIcon(venue), title: venue.name || '' })
            .bindPopup(() => createPopup(venue))
            .addTo(map)
    )

    if (markers.length === 1) {
        map.setView(markers[0].getLatLng(), VENUE_ZOOM)
    } else {
        map.fitBounds(L.featureGroup(markers).getBounds(), { padding: [24, 24], maxZoom: VENUE_ZOOM })
    }

    return {
        // The map was laid out while hidden, or its box changed size
        refresh: () => map.invalidateSize(),
        destroy: () => map.remove(),
    }
}
