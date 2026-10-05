import { dom, findAll, on } from '@/js/utils/dom'

const TIME_PATTERN = /^([01]\d|2[0-3]):[0-5]\d$/

/**
 * Whether a date row has hours of its own: a start, an end or precisions. Without, it shows the event's default hours.
 *
 * @param {HTMLElement} item - A .timesheet-item of the timesheets collection
 */
const markOwnHours = (item) => {
    const own = [...findAll('.timesheet-time, .timesheet-hours', item)].some((field) => field.value.trim() !== '')
    item.toggleAttribute('data-hours-own', own)
}

/**
 * Keeps the date rows in step with the event's default hours: the start, end and precisions of each row show the
 * default ones as their placeholder, and each row's badge says whether it follows the default or has hours of its own.
 *
 * @param {HTMLElement} container - DOM container
 */
export default function initTimesheetHoursSync(container) {
    const hoursField = dom('#app_event_hours', container)
    const startField = dom('#app_event_startTime', container)
    const endField = dom('#app_event_endTime', container)
    const timesheetsCollection = dom('#app_event_timesheets', container)

    if (!hoursField || !timesheetsCollection) return

    /**
     * The start or end of the rows show the default one once it is a complete time (TimepickerService's fields)
     *
     * @param {HTMLInputElement|null} defaultField
     * @param {string} selector - The start or end fields of the rows
     */
    const updateTimePlaceholders = (defaultField, selector) => {
        const value = defaultField?.value ?? ''
        const placeholder = TIME_PATTERN.test(value) ? value : 'hh:mm'

        findAll(selector, timesheetsCollection).forEach((field) => {
            field.placeholder = placeholder
        })
    }
    const updateStartPlaceholders = () => updateTimePlaceholders(startField, 'input[id$="_startTime"]')
    const updateEndPlaceholders = () => updateTimePlaceholders(endField, 'input[id$="_endTime"]')

    /**
     * Update all timesheet precisions placeholders
     */
    const updateTimesheetPlaceholders = () => {
        const placeholder = hoursField.value.trim() || 'Précisions (facultatif)'

        // Update existing timesheet hours fields
        findAll('.timesheet-hours', timesheetsCollection).forEach((field) => {
            field.placeholder = placeholder
        })

        // Update prototype for new entries
        const prototype = timesheetsCollection.dataset.prototype
        if (prototype) {
            // Replace placeholder in prototype HTML
            // Find the timesheet-hours input and update its placeholder
            timesheetsCollection.dataset.prototype = prototype.replace(
                /(<input[^>]*class="[^"]*timesheet-hours[^"]*"[^>]*placeholder=")[^"]*(")/g,
                `$1${placeholder}$2`
            )
        }
    }

    // Update on hours field change
    on(hoursField, 'input', updateTimesheetPlaceholders)
    on(hoursField, 'change', updateTimesheetPlaceholders)

    // The default start and end, as they are typed or picked
    for (const type of ['input', 'change']) {
        if (startField) on(startField, type, updateStartPlaceholders)
        if (endField) on(endField, type, updateEndPlaceholders)
    }

    // Update when new timesheet is added: before its time picker, which only fills an empty placeholder
    on(timesheetsCollection, 'collection.added', () => {
        updateTimesheetPlaceholders()
        updateStartPlaceholders()
        updateEndPlaceholders()
    })

    // A row's own hours, as they are typed
    const markRow = (e) => {
        if (e.target.matches('.timesheet-time, .timesheet-hours')) {
            markOwnHours(e.target.closest('.timesheet-item'))
        }
    }
    on(timesheetsCollection, 'input', markRow)
    on(timesheetsCollection, 'change', markRow)

    // Initial update
    updateTimesheetPlaceholders()
    updateStartPlaceholders()
    updateEndPlaceholders()
}
