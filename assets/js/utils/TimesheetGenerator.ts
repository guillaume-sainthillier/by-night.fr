import { addDays, diffDays, formatDay, isoWeekday, parseDay } from '@/js/utils/days'

const MAX_TIMESHEETS = 500

/**
 * A session of an event, as the timesheet fields hold it: "YYYY-MM-DD", or "YYYY-MM-DD HH:MM:SS" for older ones
 */
export interface Timesheet {
    startAt: string
    endAt: string
}

/**
 * Client-side timesheet generator for event scheduling patterns
 */
export default class TimesheetGenerator {
    /**
     * Generate daily timesheets (every day in date range)
     */
    generateDaily(startDate: Date | string, endDate: Date | string): Timesheet[] {
        return this.generate(startDate, endDate, () => true)
    }

    /**
     * Generate timesheets for specific weekdays
     *
     * @param weekdays - Array of weekday numbers (1=Monday, 7=Sunday)
     */
    generateWeekdays(startDate: Date | string, endDate: Date | string, weekdays: number[]): Timesheet[] {
        return this.generate(startDate, endDate, (day) => weekdays.includes(isoWeekday(day)))
    }

    /**
     * Validate date range (max 365 days)
     *
     * @returns True if valid
     */
    validateDateRange(startDate: Date | string, endDate: Date | string): boolean {
        const start = parseDay(startDate)
        const end = parseDay(endDate)
        if (!start || !end || end < start) return false

        return diffDays(end, start) <= 365
    }

    /**
     * One-day timesheets for the days of the range the filter keeps, MAX_TIMESHEETS at most
     */
    generate(startDate: Date | string, endDate: Date | string, filter: (day: Date) => boolean): Timesheet[] {
        const timesheets: Timesheet[] = []
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
