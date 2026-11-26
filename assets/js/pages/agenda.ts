import { definePage } from '@silarhi/app-js'
import { type FormView, formView } from '@silarhi/app-js/form'
import { Offcanvas } from '@tabler/core'
import { DEFAULT_RANGE, MAX_RANGE } from '@/js/constants/SearchEvent'
import type { SearchForm } from '@/js/forms/SearchForm'
import { create as createDatepicker } from '@/js/services/ui/DatepickerService'
import { create as createSlider } from '@/js/services/ui/SliderService'
import { groupByVenue, type MapEvent } from '@/js/utils/venues'

/**
 * The bottom bar of the phone's filters (location/agenda/_filters.html.twig): its button counts the events on show,
 * which closing the panel shows again, until a field of the form changes; it then says "Rechercher", and sends it.
 */
function initFiltersBar(): () => void {
    const button = document.querySelector<HTMLButtonElement>('[data-filters-submit]')
    const form = button?.form
    if (!button || !form) {
        return () => {}
    }

    let dirty = false
    const markDirty = () => {
        if (!dirty) {
            dirty = true
            button.textContent = button.dataset.dirtyLabel ?? ''
        }
    }

    button.addEventListener('click', (event) => {
        if (!dirty) {
            event.preventDefault()
            const panel = document.getElementById('agenda-filters')
            const offcanvas = panel && Offcanvas.getOrCreateInstance(panel)
            if (offcanvas instanceof Offcanvas) {
                offcanvas.hide()
            }
        }
    })
    form.addEventListener('input', markDirty)
    form.addEventListener('change', markDirty)

    return markDirty
}

/** The dates of the filters (SearchType's dateRange, a DateRangeType), when the page shows them */
function initDatepicker(form: FormView<SearchForm>, markDirty: () => void) {
    const dateRange = form.find('dateRange')
    const element = dateRange?.field('range').element
    if (!dateRange || !element) {
        return
    }

    createDatepicker({
        element,
        fromInput: dateRange.field('from').element,
        toInput: dateRange.field('to').element,
        singleDate: element.dataset.singleDate === 'true',
        // The dates picked replace the shortcut of the chips (SearchType's "when"), which the form would send again
        onApply: () => {
            form.find('when')?.field().setValue('', { silent: true })
            markDirty()
        },
    })
}

/**
 * The radius slider: evenly spaced stops the handle snaps to, SearchEvent's default in the middle and its widest radius
 * last. See https://refreshless.com/nouislider/slider-values/
 */
const RADIUS_SCALE = {
    range: { min: 5, '25%': 10, '50%': DEFAULT_RANGE, '75%': 50, max: MAX_RANGE },
    snap: true,
}

/** The radius of the filters (SearchType's "range"), when the page shows it */
function initSlider(form: FormView<SearchForm>, markDirty: () => void) {
    const element = form.find('range')?.field().element
    if (!element) {
        return
    }

    createSlider({
        element,
        unit: element.dataset.slider,
        onChange: markDirty,
        ...RADIUS_SCALE,
    })
}

export default definePage(({ container }) => {
    const markDirty = initFiltersBar()
    // The filters of the agenda (location/agenda/_filters.html.twig)
    const element = container.querySelector<HTMLFormElement>('form#search-form')
    if (!element) {
        return
    }

    const form = formView<SearchForm>(element)
    initDatepicker(form, markDirty)
    initSlider(form, markDirty)
})

/** The hash of a page whose map is open: a link to it opens the map too, and no search engine reads it as a page */
const MAP_HASH = '#carte'

/**
 * The map of the events the page lists (location/agenda/_map.html.twig), shown above them on demand. MapLibre and the
 * tiles are only fetched when the visitor opens it.
 */
export const agendaMap = definePage<{ events?: MapEvent[] }>(({ container, events = [] }) => {
    const button = container.querySelector<HTMLButtonElement>('[data-map-toggle]')
    const panel = container.querySelector<HTMLElement>('[data-map]')
    const canvas = panel?.querySelector<HTMLElement>('[data-map-canvas]')
    const label = button?.querySelector<HTMLElement>('[data-map-label]')
    if (!button || !panel || !canvas || !label) {
        return
    }
    const venues = groupByVenue(events)
    // The page places the same events (location/agenda/_map.html.twig): this only guards against a coordinate it reads otherwise
    if (venues.length === 0) {
        button.hidden = true
        return
    }

    let map: { refresh: () => void; destroy: () => void } | null = null
    let disconnected = false

    const setOpen = (open: boolean) => {
        panel.hidden = !open
        button.setAttribute('aria-expanded', String(open))
        label.textContent = (open ? button.dataset.closeLabel : button.dataset.openLabel) ?? ''
        const url = new URL(window.location.href)
        url.hash = open ? MAP_HASH : ''
        window.history.replaceState(window.history.state, '', url)
    }

    const open = async () => {
        setOpen(true)
        if (map) {
            map.refresh()
            return
        }

        button.disabled = true
        try {
            const { create } = await import('@/js/services/ui/MapService')
            if (!disconnected) {
                map = create({ element: canvas, venues })
            }
        } catch {
            // The chunk could not be fetched (flaky network, blocker): the list is still there
            setOpen(false)
            button.hidden = true
        } finally {
            button.disabled = false
        }
    }

    const toggle = () => (panel.hidden ? open() : setOpen(false))
    button.addEventListener('click', toggle)

    if (window.location.hash === MAP_HASH) {
        open()
    }

    return () => {
        disconnected = true
        button.removeEventListener('click', toggle)
        map?.destroy()
    }
})
