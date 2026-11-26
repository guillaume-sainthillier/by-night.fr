import { definePage } from '@silarhi/app-js'
import { formView } from '@silarhi/app-js/form'
import type { AudienceQueryForm } from '@/js/forms/AudienceQueryForm'
import { create as createDatepicker } from '@/js/services/ui/DatepickerService'
import { formatDay } from '@/js/utils/days'

/**
 * The statistics of an event (personal-space/stats.html.twig): the dates picked show at once, in place of the shortcut
 * of the chips (AudienceQuery::getPreset())
 */
export default definePage(({ container }) => {
    const element = container.querySelector<HTMLFormElement>('form#event-stats-dates')
    if (!element) {
        return undefined
    }

    const form = formView<AudienceQueryForm>(element)
    const range = form.field('dateRange.range').element
    if (!range) {
        return undefined
    }

    createDatepicker({
        element: range,
        fromInput: form.field('dateRange.from').element,
        toInput: form.field('dateRange.to').element,
        // A period looking back: no day after today
        maxDate: formatDay(new Date()),
        onApply: () => {
            form.field('period').setValue('', { silent: true })
            element.requestSubmit()
        },
    })
    return undefined
})
