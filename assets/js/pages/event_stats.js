import { create as createDatepicker } from '@/js/services/ui/DatepickerService'
import { formatDay } from '@/js/utils/days'

/**
 * The statistics of an event (personal-space/stats.html.twig): the dates picked show at once, in place of the shortcut
 * of the chips (AudienceQuery::getPreset())
 *
 * @type {Page}
 */
function initialize({ container }) {
    container.querySelectorAll('input.shorcuts_date').forEach((el) => {
        createDatepicker({
            element: el,
            fromInput: document.getElementById(el.dataset.from),
            toInput: document.getElementById(el.dataset.to),
            // A period looking back: no day after today
            maxDate: formatDay(new Date()),
            onApply: () => {
                const period = el.form?.elements.namedItem('period')
                if (period) {
                    period.value = ''
                }
                el.form?.requestSubmit()
            },
        })
    })
}

window.App.registerPage('event_stats', initialize)
