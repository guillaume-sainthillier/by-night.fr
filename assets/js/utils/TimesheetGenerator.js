import { addDays, diffDays, formatDay, isoWeekday, parseDay } from '@/js/utils/days'

const MAX_TIMESHEETS = 500

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
        return this.generate(startDate, endDate, () => true)
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
        return this.generate(startDate, endDate, (day) => weekdays.includes(isoWeekday(day)))
    }

    /**
     * Validate date range (max 365 days)
     *
     * @param {Date|string} startDate - Start date
     * @param {Date|string} endDate - End date
     * @returns {boolean} True if valid
     */
    validateDateRange(startDate, endDate) {
        const start = parseDay(startDate)
        const end = parseDay(endDate)
        if (!start || !end || end < start) return false

        return diffDays(end, start) <= 365
    }

    /**
     * One-day timesheets for the days of the range the filter keeps, MAX_TIMESHEETS at most
     *
     * @param {Date|string} startDate
     * @param {Date|string} endDate
     * @param {(day: Date) => boolean} filter
     * @returns {Array<{startAt: string, endAt: string}>}
     */
    generate(startDate, endDate, filter) {
        const timesheets = []
        const end = parseDay(endDate)

        for (let day = parseDay(startDate); day && end && day <= end; day = addDays(day, 1)) {
            if (filter(day)) {
                const dateStr = formatDay(day)
                timesheets.push({ startAt: dateStr, endAt: dateStr })
                if (timesheets.length === MAX_TIMESHEETS) break
            }
        }

        return timesheets
    }
}
