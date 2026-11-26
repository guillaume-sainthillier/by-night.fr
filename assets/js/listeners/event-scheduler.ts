import type { App } from '@silarhi/app-js'
import { findOne } from '@silarhi/app-js'
import type { FormView } from '@silarhi/app-js/form'
import { h, render } from 'preact'
import EventScheduler from '@/js/components/EventScheduler'
import type { EventForm } from '@/js/forms/EventForm'

/**
 * The generator of the event's dates (templates/personal-space/new.html.twig), which reads the event's period and
 * fills its timesheets
 */
export default function initEventScheduler(form: FormView<EventForm>, container: ParentNode, app: App) {
    const schedulerRoot = findOne('#event-scheduler-root', container)
    const generateButtonId = schedulerRoot?.dataset.generateButton
    if (!schedulerRoot || !generateButtonId) return

    schedulerRoot.innerHTML = ''
    render(
        h(EventScheduler, {
            startDateField: form.field('dateRange.from'),
            endDateField: form.field('dateRange.to'),
            timesheets: form.get('timesheets'),
            generateButtonId,
            collectionManager: app.get('collectionManager'),
            detectExisting: schedulerRoot.dataset.detectExisting === 'true',
        }),
        schedulerRoot
    )
}
