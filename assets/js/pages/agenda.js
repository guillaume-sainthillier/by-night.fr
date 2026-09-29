import { create as createDatepicker } from '@/js/services/ui/DatepickerService'
import { create as createSlider } from '@/js/services/ui/SliderService'

function initDatepickers(container = document) {
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

function initSliders(container = document) {
    container.querySelectorAll('input[data-slider]').forEach((el) => {
        createSlider({
            element: el,
            unit: el.dataset.slider,
            ...RADIUS_SCALE,
        })
    })
}

/** @type {Page} */
function initialize() {
    initDatepickers()
    initSliders()
}

window.App.registerPage('agenda', initialize)
