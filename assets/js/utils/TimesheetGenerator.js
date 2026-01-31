import moment from 'moment'

/**
 * Client-side timesheet generator for event scheduling patterns
 */
export default class TimesheetGenerator {
    /**
     * Generate daily timesheets (every day in date range)
     *
     * @param {Date|string} startDate - Start date
     * @param {Date|string} endDate - End date
     * @returns {Array<{startAt: string, endAt: string}>} Array of timesheets
     */
    generateDaily(startDate, endDate) {
        const timesheets = []
        const start = moment(startDate).startOf('day')
        const end = moment(endDate).startOf('day')

        let current = start.clone()
        let count = 0
        const maxTimesheets = 500

        while (current.isSameOrBefore(end) && count < maxTimesheets) {
            const dateStr = current.format('YYYY-MM-DD')
            timesheets.push({
                startAt: dateStr,
                endAt: dateStr,
            })

            current.add(1, 'day')
            count++
        }

        return timesheets
    }

    /**
     * Generate timesheets for specific weekdays
     *
     * @param {Date|string} startDate - Start date
     * @param {Date|string} endDate - End date
     * @param {Array<number>} weekdays - Array of weekday numbers (1=Monday, 7=Sunday)
     * @returns {Array<{startAt: string, endAt: string}>} Array of timesheets
     */
    generateWeekdays(startDate, endDate, weekdays) {
        const timesheets = []
        const start = moment(startDate).startOf('day')
        const end = moment(endDate).startOf('day')

        let current = start.clone()
        let count = 0
        const maxTimesheets = 500

        while (current.isSameOrBefore(end) && count < maxTimesheets) {
            const dayOfWeek = current.isoWeekday() // 1=Monday, 7=Sunday

            if (weekdays.includes(dayOfWeek)) {
                const dateStr = current.format('YYYY-MM-DD')
                timesheets.push({
                    startAt: dateStr,
                    endAt: dateStr,
                })
                count++
            }

            current.add(1, 'day')
        }

        return timesheets
    }

    /**
     * Validate date range (max 365 days)
     *
     * @param {Date|string} startDate - Start date
     * @param {Date|string} endDate - End date
     * @returns {boolean} True if valid
     */
    validateDateRange(startDate, endDate) {
        if (!startDate || !endDate) return false

        const start = moment(startDate)
        const end = moment(endDate)

        if (!start.isValid() || !end.isValid()) return false
        if (end.isBefore(start)) return false

        const daysDiff = end.diff(start, 'days')
        return daysDiff <= 365
    }
}
