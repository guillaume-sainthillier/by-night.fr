/**
 * Whole-day arithmetic on local midnights, for the date fields ("YYYY-MM-DD") and the timesheets.
 *
 * Days are built from their parts: new Date('2026-03-27') is midnight UTC, the day before west of Greenwich. Adding a
 * day goes through the day of the month rather than 24 hours, which the daylight saving changes would break.
 */

const DAY_PATTERN = /^(\d{4})-(\d{2})-(\d{2})/

/**
 * The day of a date field or a timesheet: "2026-10-03", "2026-10-03 20:00:00" or a Date.
 *
 * @returns Its local midnight, null when the value is not a date
 */
export function parseDay(value: string | Date | null | undefined): Date | null {
    if (value instanceof Date) {
        return Number.isNaN(value.getTime()) ? null : new Date(value.getFullYear(), value.getMonth(), value.getDate())
    }

    const match = DAY_PATTERN.exec(value ?? '')
    if (!match) {
        return null
    }

    const year = Number(match[1])
    const month = Number(match[2])
    const day = Number(match[3])
    const date = new Date(year, month - 1, day)

    // new Date() rolls 2026-02-30 over to March: a day that does not exist is not a date
    return date.getMonth() === month - 1 && date.getDate() === day ? date : null
}

export function addDays(date: Date, days: number): Date {
    return new Date(date.getFullYear(), date.getMonth(), date.getDate() + days)
}

/**
 * The days from one midnight to another: 23 or 25 hours count as one across a daylight saving change.
 */
export function diffDays(to: Date, from: Date): number {
    return Math.round((to.getTime() - from.getTime()) / 86_400_000)
}

/**
 * @returns 1 for Monday to 7 for Sunday
 */
export function isoWeekday(date: Date): number {
    return date.getDay() || 7
}

/**
 * @returns "YYYY-MM-DD"
 */
export function formatDay(date: Date): string {
    const month = String(date.getMonth() + 1).padStart(2, '0')
    const day = String(date.getDate()).padStart(2, '0')

    return `${date.getFullYear()}-${month}-${day}`
}
