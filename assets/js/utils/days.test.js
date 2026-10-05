/**
 * Tests for the whole-day helpers and the timesheet generator built on them
 * Run: yarn test
 */

import { afterAll, beforeAll, describe, expect, test } from 'vitest'
import { addDays, diffDays, formatDay, isoWeekday, parseDay } from '@/js/utils/days'
import TimesheetGenerator from '@/js/utils/TimesheetGenerator'

// France's daylight saving changes: 2026-03-29 has 23 hours, 2026-10-25 has 25
const timezone = process.env.TZ
beforeAll(() => {
    process.env.TZ = 'Europe/Paris'
})
afterAll(() => {
    process.env.TZ = timezone
})

describe('days', () => {
    test('parses the day of a date field or a timesheet', () => {
        expect(formatDay(parseDay('2026-10-03'))).toBe('2026-10-03')
        expect(formatDay(parseDay('2026-10-03 20:00:00'))).toBe('2026-10-03')
        expect(formatDay(parseDay(new Date(2026, 9, 3, 20, 30)))).toBe('2026-10-03')
    })

    test('rejects what is not a day', () => {
        expect(parseDay('')).toBeNull()
        expect(parseDay(null)).toBeNull()
        expect(parseDay('3 oct. 2026')).toBeNull()
        expect(parseDay('2026-02-30')).toBeNull()
        expect(parseDay(new Date('nope'))).toBeNull()
    })

    test('counts whole days across the daylight saving changes', () => {
        expect(formatDay(addDays(parseDay('2026-10-24'), 2))).toBe('2026-10-26')
        expect(formatDay(addDays(parseDay('2026-03-28'), 2))).toBe('2026-03-30')
        expect(diffDays(parseDay('2026-10-26'), parseDay('2026-10-24'))).toBe(2)
        expect(diffDays(parseDay('2026-03-30'), parseDay('2026-03-28'))).toBe(2)
    })

    test('numbers the weekdays from Monday', () => {
        expect(isoWeekday(parseDay('2026-10-05'))).toBe(1)
        expect(isoWeekday(parseDay('2026-10-11'))).toBe(7)
    })
})

describe('TimesheetGenerator', () => {
    const generator = new TimesheetGenerator()
    const days = (timesheets) => timesheets.map(({ startAt }) => startAt)

    test('generates every day of the range, once across a daylight saving change', () => {
        expect(days(generator.generateDaily('2026-10-24', '2026-10-26'))).toEqual([
            '2026-10-24',
            '2026-10-25',
            '2026-10-26',
        ])
        expect(generator.generateDaily('2026-10-26', '2026-10-24')).toEqual([])
        expect(generator.generateDaily('', '2026-10-24')).toEqual([])
    })

    test('keeps the weekdays picked', () => {
        expect(days(generator.generateWeekdays('2026-10-01', '2026-10-14', [1, 3]))).toEqual([
            '2026-10-05',
            '2026-10-07',
            '2026-10-12',
            '2026-10-14',
        ])
    })

    test('stops at 500 timesheets', () => {
        expect(generator.generateDaily('2026-01-01', '2028-12-31')).toHaveLength(500)
    })

    test('accepts ranges of a year at most', () => {
        expect(generator.validateDateRange('2026-01-01', '2027-01-01')).toBe(true)
        expect(generator.validateDateRange('2026-01-01', '2027-01-02')).toBe(false)
        expect(generator.validateDateRange('2026-01-02', '2026-01-01')).toBe(false)
        expect(generator.validateDateRange('', '2026-01-01')).toBe(false)
    })
})
