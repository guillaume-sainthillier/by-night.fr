import { LngLatBounds, MapLibreMap, Marker, NavigationControl, Popup, setWorkerUrl } from 'maplibre-gl'
import 'maplibre-gl/dist/maplibre-gl.css'
import workerUrl from 'maplibre-gl/dist/maplibre-gl-worker.mjs?url'
import { plural } from '@/js/utils/plural'
import type { MapVenue } from '@/js/utils/venues'

/**
 * OpenFreeMap's vector styles: free for any use, without a key or a quota (https://openfreemap.org), the attribution
 * coming with the style. Positron's greys leave the markers in sight; Dark is for a map inside a dark theme.
 */
const STYLES = {
    light: 'https://tiles.openfreemap.org/styles/positron',
    dark: 'https://tiles.openfreemap.org/styles/dark',
}

/** Close enough to see the streets around a venue (MapLibre's 512 px tiles: Leaflet's 15) */
const VENUE_ZOOM = 14

/** Narrow enough for the map of a phone, whose popups the map pans into sight */
const POPUP_WIDTH = '260px'

const dayFormat = new Intl.DateTimeFormat('fr', { weekday: 'short', day: 'numeric', month: 'short', timeZone: 'UTC' })

// MapLibre looks for its worker next to its own module, which the bundle does not keep: the build ships it as a file
setWorkerUrl(workerUrl)

export interface MapServiceOptions {
    element: HTMLElement
    venues: MapVenue[]
}

/**
 * The marker of a venue: its number of events in a disc.
 */
function createMarker(venue: MapVenue): HTMLElement {
    const marker = document.createElement('span')
    marker.className = 'avatar avatar-sm rounded-circle bg-primary text-white shadow fw-bold'
    marker.style.cursor = 'pointer'
    marker.textContent = String(venue.events.length)
    if (venue.name) {
        marker.title = venue.name
    }

    return marker
}

/**
 * The popup of a venue: its name, and a link to each of its events. Built as nodes, so that no name is read as HTML.
 */
function createPopup(venue: MapVenue): HTMLElement {
    const popup = document.createElement('div')
    // The page's text rather than the map's own (MapLibre sets a 12 px Helvetica on the whole map), in the colours of a
    // light theme whatever the page's: MapLibre's popup stays white on the dark map too
    popup.dataset.bsTheme = 'light'
    popup.style.font = 'var(--bs-body-font-size) / var(--bs-body-line-height) var(--bs-body-font-family)'
    popup.style.color = 'var(--bs-body-color)'

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
 * The style of the theme the map sits in: the page's, or a dark card's.
 */
function styleOf(element: HTMLElement): string {
    return element.closest('[data-bs-theme]')?.getAttribute('data-bs-theme') === 'dark' ? STYLES.dark : STYLES.light
}

/**
 * A map of the venues, framed on all of them.
 */
export function create({ element, venues }: MapServiceOptions): {
    refresh: () => void
    destroy: () => void
} {
    const map = new MapLibreMap({
        container: element,
        style: styleOf(element),
        // The wheel scrolls the page: the map zooms with its buttons, a pinch or a double click
        scrollZoom: false,
        // A flat map, north up: nothing to tilt or turn
        dragRotate: false,
        pitchWithRotate: false,
        touchPitch: false,
        attributionControl: { compact: true },
    })
    map.touchZoomRotate.disableRotation()
    map.addControl(new NavigationControl({ showCompass: false }), 'top-left')

    const bounds = new LngLatBounds()
    for (const venue of venues) {
        // Opened by a click on a marker, which is no keyboard stop: no focus to move into it
        const popup = new Popup({ maxWidth: POPUP_WIDTH, offset: 18, focusAfterOpen: false }).setDOMContent(
            createPopup(venue)
        )
        new Marker({ element: createMarker(venue) }).setLngLat([venue.lng, venue.lat]).setPopup(popup).addTo(map)
        bounds.extend([venue.lng, venue.lat])
    }

    if (venues.length === 1) {
        map.jumpTo({ center: bounds.getCenter(), zoom: VENUE_ZOOM })
    } else {
        // Room for a marker's disc and the zoom buttons around the venues at the edges
        map.fitBounds(bounds, { padding: 56, maxZoom: VENUE_ZOOM, animate: false })
    }

    return {
        // The map was laid out while hidden, or its box changed size
        refresh: () => map.resize(),
        destroy: () => map.remove(),
    }
}
