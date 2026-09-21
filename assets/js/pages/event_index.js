import { create as createDatepicker } from '@/js/services/ui/DatepickerService'

/** @type {Page} */
function initialize() {
    document.querySelectorAll('input.shorcuts_date').forEach((el) => {
        createDatepicker({
            element: el,
            fromInput: document.getElementById(el.dataset.from),
            toInput: document.getElementById(el.dataset.to),
            singleDate: el.dataset.singleDate === 'true',
            ranges: el.dataset.ranges ? JSON.parse(el.dataset.ranges) : {},
        })
    })
}

window.App.registerPage('event_index', initialize)
