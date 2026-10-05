import { dom, findAll, on } from '@/js/utils/dom'

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
 * Keeps the date rows in step with the event's default hours: the precisions of each row show the default ones as
 * their placeholder, and each row's badge says whether it follows the default or has hours of its own.
 *
 * @param {HTMLElement} container - DOM container
 */
export default function initTimesheetHoursSync(container) {
    const hoursField = dom('#app_event_hours', container)
    const timesheetsCollection = dom('#app_event_timesheets', container)

    if (!hoursField || !timesheetsCollection) return

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

    // Update when new timesheet is added
    on(timesheetsCollection, 'collection.added', updateTimesheetPlaceholders)

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
}
