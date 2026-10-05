import { Offcanvas } from '@tabler/core/dist/js/tabler.esm'
import { create as createDatepicker } from '@/js/services/ui/DatepickerService'
import { create as createSlider } from '@/js/services/ui/SliderService'

/**
 * The bottom bar of the phone's filters (location/agenda/_filters.html.twig): its button counts the events on show,
 * which closing the panel shows again, until a field of the form changes; it then says "Rechercher", and sends it.
 */
function initFiltersBar() {
    const button = document.querySelector('[data-filters-submit]')
    const form = button?.form
    if (!form) {
        return () => {}
    }

    let dirty = false
    const markDirty = () => {
        if (!dirty) {
            dirty = true
            button.textContent = button.dataset.dirtyLabel
        }
    }

    button.addEventListener('click', (event) => {
        if (!dirty) {
            event.preventDefault()
            const panel = document.getElementById('agenda-filters')
            Offcanvas.getOrCreateInstance(panel).hide()
        }
    })
    form.addEventListener('input', markDirty)
    form.addEventListener('change', markDirty)

    return markDirty
}

function initDatepickers(markDirty, container = document) {
    container.querySelectorAll('input.shorcuts_date').forEach((el) => {
        createDatepicker({
            element: el,
            fromInput: document.getElementById(el.dataset.from),
            toInput: document.getElementById(el.dataset.to),
            singleDate: el.dataset.singleDate === 'true',
            // The dates picked replace the shortcut of the chips (SearchType's "when"), which the form would send again
            onApply: () => {
                const when = el.form?.elements.namedItem('when')
                if (when) {
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

function initSliders(markDirty, container = document) {
    container.querySelectorAll('input[data-slider]').forEach((el) => {
        createSlider({
            element: el,
            unit: el.dataset.slider,
            onChange: markDirty,
            ...RADIUS_SCALE,
        })
    })
}

/** @type {Page} */
function initialize() {
    const markDirty = initFiltersBar()
    initDatepickers(markDirty)
    initSliders(markDirty)
}

window.App.registerPage('agenda', initialize)
