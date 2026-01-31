import { dom, findAll, on } from '@/js/utils/dom'

/**
 * Sync timesheet hours placeholders with parent hours field
 *
 * @param {HTMLElement} container - DOM container
 */
export default function initTimesheetHoursSync(container) {
    const hoursField = dom('#app_event_hours', container)
    const timesheetsCollection = dom('#app_event_timesheets', container)

    if (!hoursField || !timesheetsCollection) return

    /**
     * Update all timesheet hours placeholders
     */
    const updateTimesheetPlaceholders = () => {
        const hoursValue = hoursField.value.trim()
        const placeholder = hoursValue || 'A 20h, de 21h à minuit'

        // Update existing timesheet hours fields
        const timesheetHoursFields = findAll('.timesheet-hours', timesheetsCollection)
        timesheetHoursFields.forEach((field) => {
            field.placeholder = placeholder
        })

        // Update prototype for new entries
        const prototype = timesheetsCollection.dataset.prototype
        if (prototype) {
            // Replace placeholder in prototype HTML
            // Find the timesheet-hours input and update its placeholder
            const updatedPrototype = prototype.replace(
                /(<input[^>]*class="[^"]*timesheet-hours[^"]*"[^>]*placeholder=")[^"]*(")/g,
                `$1${placeholder}$2`
            )
            timesheetsCollection.dataset.prototype = updatedPrototype
        }
    }

    // Update on hours field change
    on(hoursField, 'input', updateTimesheetPlaceholders)
    on(hoursField, 'change', updateTimesheetPlaceholders)

    // Update when new timesheet is added
    on(timesheetsCollection, 'collection.added', updateTimesheetPlaceholders)

    // Initial update
    updateTimesheetPlaceholders()
}
