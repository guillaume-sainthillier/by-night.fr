import { byId, definePage } from '@silarhi/app-js'
import { create as createDatepicker } from '@/js/services/ui/DatepickerService'
import { formatDay } from '@/js/utils/days'

/**
 * The statistics of an event (personal-space/stats.html.twig): the dates picked show at once, in place of the shortcut
 * of the chips (AudienceQuery::getPreset())
 */
export default definePage(({ container }) => {
    container.querySelectorAll<HTMLInputElement>('input.shorcuts_date').forEach((el) => {
        createDatepicker({
            element: el,
            fromInput: byId(el.dataset.from ?? '', HTMLInputElement),
            toInput: byId(el.dataset.to ?? '', HTMLInputElement),
            // A period looking back: no day after today
            maxDate: formatDay(new Date()),
            onApply: () => {
                const period = el.form?.elements.namedItem('period')
                if (period instanceof HTMLInputElement) {
                    period.value = ''
                }
                el.form?.requestSubmit()
            },
        })
    })
    return undefined
})
