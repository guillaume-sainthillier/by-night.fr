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
     * @param {string} startTime - Start time (HH:mm format)
     * @param {string} endTime - End time (HH:mm format)
     * @returns {Array<{startAt: string, endAt: string}>} Array of timesheets
     */
    generateDaily(startDate, endDate, startTime, endTime) {
        const timesheets = []
        const start = moment(startDate).startOf('day')
        const end = moment(endDate).startOf('day')

        let current = start.clone()
        let count = 0
        const maxTimesheets = 500

        while (current.isSameOrBefore(end) && count < maxTimesheets) {
            timesheets.push({
                startAt: this._formatDateTime(current, startTime),
                endAt: this._formatDateTime(current, endTime),
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
     * @param {string} startTime - Start time (HH:mm format)
     * @param {string} endTime - End time (HH:mm format)
     * @returns {Array<{startAt: string, endAt: string}>} Array of timesheets
     */
    generateWeekdays(startDate, endDate, weekdays, startTime, endTime) {
        const timesheets = []
        const start = moment(startDate).startOf('day')
        const end = moment(endDate).startOf('day')

        let current = start.clone()
        let count = 0
        const maxTimesheets = 500

        while (current.isSameOrBefore(end) && count < maxTimesheets) {
            const dayOfWeek = current.isoWeekday() // 1=Monday, 7=Sunday

            if (weekdays.includes(dayOfWeek)) {
                timesheets.push({
                    startAt: this._formatDateTime(current, startTime),
                    endAt: this._formatDateTime(current, endTime),
                })
                count++
            }

            current.add(1, 'day')
        }

        return timesheets
    }

    /**
     * Format date and time to datetime string
     *
     * @private
     * @param {moment.Moment} date - Moment date object
     * @param {string} time - Time string (HH:mm format)
     * @returns {string} ISO datetime string (YYYY-MM-DD HH:mm)
     */
    _formatDateTime(date, time) {
        return `${date.format('YYYY-MM-DD')} ${time}`
    }

    /**
     * Validate time range
     *
     * @param {string} startTime - Start time (HH:mm format)
     * @param {string} endTime - End time (HH:mm format)
     * @returns {boolean} True if valid
     */
    validateTimeRange(startTime, endTime) {
        if (!startTime || !endTime) return false

        const start = moment(startTime, 'HH:mm')
        const end = moment(endTime, 'HH:mm')

        return start.isValid() && end.isValid() && start.isBefore(end)
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
