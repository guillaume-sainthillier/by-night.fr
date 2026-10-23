import { Offcanvas } from '@tabler/core'
import { create as createDatepicker } from '@/js/services/ui/DatepickerService'
import { create as createSlider } from '@/js/services/ui/SliderService'
import type { Page } from '@/js/types'

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

const inputById = (id: string | undefined): HTMLInputElement | null => {
    const input = id ? document.getElementById(id) : null

    return input instanceof HTMLInputElement ? input : null
}

function initDatepickers(markDirty: () => void, container: ParentNode = document) {
    container.querySelectorAll<HTMLInputElement>('input.shorcuts_date').forEach((el) => {
        createDatepicker({
            element: el,
            fromInput: inputById(el.dataset.from),
            toInput: inputById(el.dataset.to),
            singleDate: el.dataset.singleDate === 'true',
            // The dates picked replace the shortcut of the chips (SearchType's "when"), which the form would send again
            onApply: () => {
                const when = el.form?.elements.namedItem('when')
                if (when instanceof HTMLInputElement) {
                    when.value = ''
                }
                markDirty()
            },
        })
    })
}

/**
 * The radius slider: evenly spaced stops the handle snaps to, 25 km being SearchEvent's default and 100 km
 * SearchEvent::MAX_RANGE. See https://refreshless.com/nouislider/slider-values/
 */
const RADIUS_SCALE = {
    range: { min: 5, '25%': 10, '50%': 25, '75%': 50, max: 100 },
    snap: true,
}

function initSliders(markDirty: () => void, container: ParentNode = document) {
    container.querySelectorAll<HTMLInputElement>('input[data-slider]').forEach((el) => {
        createSlider({
            element: el,
            unit: el.dataset.slider,
            onChange: markDirty,
            ...RADIUS_SCALE,
        })
    })
}

const initialize: Page = () => {
    const markDirty = initFiltersBar()
    initDatepickers(markDirty)
    initSliders(markDirty)
}

window.App.registerPage('agenda', initialize)
