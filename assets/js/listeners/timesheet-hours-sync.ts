import type { App } from '@silarhi/app-js'
import { on } from '@silarhi/app-js'
import type { FormView } from '@silarhi/app-js/form'
import type { EventForm } from '@/js/forms/EventForm'

const TIME_PATTERN = /^([01]\d|2[0-3]):[0-5]\d$/

/**
 * Keeps the date rows in step with the event's default hours: the start, end and precisions of each row show the
 * default ones as their placeholder, and each row's badge says whether it follows the default or has hours of its own.
 */
export default function initTimesheetHoursSync(form: FormView<EventForm>, app: App) {
    const hoursField = form.field('hours')
    const startField = form.field('startTime')
    const endField = form.field('endTime')
    const timesheets = form.get('timesheets')
    const collection = timesheets.element
    if (!collection) return

    /** A complete time (TimepickerService's fields) is the placeholder of the rows' start or end */
    const timePlaceholder = (value: string) => (TIME_PATTERN.test(value) ? value : 'hh:mm')
    const updateStartPlaceholders = () => {
        for (const row of timesheets.children) row.field('startTime').setPlaceholder(timePlaceholder(startField.value))
    }
    const updateEndPlaceholders = () => {
        for (const row of timesheets.children) row.field('endTime').setPlaceholder(timePlaceholder(endField.value))
    }

    /** The rows' precisions, and the prototype of the rows to come */
    const updateTimesheetPlaceholders = () => {
        const placeholder = hoursField.value.trim() || 'Précisions (facultatif)'
        for (const row of timesheets.children) row.field('hours').setPlaceholder(placeholder)

        const prototype = collection.dataset.prototype
        if (prototype) {
            collection.dataset.prototype = prototype.replace(
                /(<input[^>]*class="[^"]*timesheet-hours[^"]*"[^>]*placeholder=")[^"]*(")/g,
                `$1${placeholder}$2`
            )
        }
    }

    for (const type of ['input', 'change'] as const) {
        hoursField.on(type, updateTimesheetPlaceholders)
        startField.on(type, updateStartPlaceholders)
        endField.on(type, updateEndPlaceholders)
    }

    // A row added: its placeholders first, before its time picker, which only fills an empty one
    on(collection, 'collection.add', () => {
        updateTimesheetPlaceholders()
        updateStartPlaceholders()
        updateEndPlaceholders()
    })

    // Whether a row has hours of its own (a start, an end or precisions): without, it shows the event's default hours
    app.get('collectionManager').each(timesheets, ({ item, form: row }) => {
        const own = [row.field('startTime'), row.field('endTime'), row.field('hours')]
        const mark = () => {
            item.toggleAttribute(
                'data-hours-own',
                own.some((field) => field.value.trim() !== '')
            )
        }
        for (const field of own) {
            field.on('input', mark)
            field.on('change', mark)
        }
    })

    updateTimesheetPlaceholders()
    updateStartPlaceholders()
    updateEndPlaceholders()
}
