/**
 * Jest tests for timesheetPatternDetector
 * Run: yarn test
 */

import { detectPattern } from './timesheetPatternDetector'

describe('timesheetPatternDetector', () => {
    describe('detectPattern', () => {
        test('Empty timesheets should return simple mode', () => {
            const result = detectPattern([])
            expect(result).toEqual({
                mode: 'simple',
                config: {
                    startTime: '09:00',
                    endTime: '17:00',
                },
            })
        })

        test('Single timesheet should return simple mode', () => {
            const result = detectPattern([{ startAt: '2024-01-15 14:00:00', endAt: '2024-01-15 18:00:00' }])
            expect(result).toEqual({
                mode: 'simple',
                config: {
                    startTime: '14:00',
                    endTime: '18:00',
                },
            })
        })

        test('Consecutive days with same time should be daily pattern', () => {
            const result = detectPattern([
                { startAt: '2024-01-15 14:00:00', endAt: '2024-01-15 18:00:00' },
                { startAt: '2024-01-16 14:00:00', endAt: '2024-01-16 18:00:00' },
                { startAt: '2024-01-17 14:00:00', endAt: '2024-01-17 18:00:00' },
                { startAt: '2024-01-18 14:00:00', endAt: '2024-01-18 18:00:00' },
            ])
            expect(result).toEqual({
                mode: 'pattern',
                config: {
                    pattern: 'daily',
                    startTime: '14:00',
                    endTime: '18:00',
                },
            })
        })

        test('Mon/Wed/Fri pattern should be detected as weekdays', () => {
            const result = detectPattern([
                { startAt: '2024-01-15 14:00:00', endAt: '2024-01-15 18:00:00' }, // Mon
                { startAt: '2024-01-17 14:00:00', endAt: '2024-01-17 18:00:00' }, // Wed
                { startAt: '2024-01-19 14:00:00', endAt: '2024-01-19 18:00:00' }, // Fri
                { startAt: '2024-01-22 14:00:00', endAt: '2024-01-22 18:00:00' }, // Mon
                { startAt: '2024-01-24 14:00:00', endAt: '2024-01-24 18:00:00' }, // Wed
                { startAt: '2024-01-26 14:00:00', endAt: '2024-01-26 18:00:00' }, // Fri
            ])
            expect(result.mode).toBe('pattern')
            expect(result.config.pattern).toBe('weekdays')
            expect(result.config.startTime).toBe('14:00')
            expect(result.config.endTime).toBe('18:00')
            // Weekdays: 1=Mon, 3=Wed, 5=Fri
            expect(result.config.weekdays.sort()).toEqual([1, 3, 5])
        })

        test('Mon-Fri pattern should be detected as weekdays', () => {
            const result = detectPattern([
                { startAt: '2024-01-15 09:00:00', endAt: '2024-01-15 17:00:00' }, // Mon
                { startAt: '2024-01-16 09:00:00', endAt: '2024-01-16 17:00:00' }, // Tue
                { startAt: '2024-01-17 09:00:00', endAt: '2024-01-17 17:00:00' }, // Wed
                { startAt: '2024-01-18 09:00:00', endAt: '2024-01-18 17:00:00' }, // Thu
                { startAt: '2024-01-19 09:00:00', endAt: '2024-01-19 17:00:00' }, // Fri
                { startAt: '2024-01-22 09:00:00', endAt: '2024-01-22 17:00:00' }, // Mon
                { startAt: '2024-01-23 09:00:00', endAt: '2024-01-23 17:00:00' }, // Tue
                { startAt: '2024-01-24 09:00:00', endAt: '2024-01-24 17:00:00' }, // Wed
                { startAt: '2024-01-25 09:00:00', endAt: '2024-01-25 17:00:00' }, // Thu
                { startAt: '2024-01-26 09:00:00', endAt: '2024-01-26 17:00:00' }, // Fri
            ])
            expect(result.mode).toBe('pattern')
            expect(result.config.pattern).toBe('weekdays')
            expect(result.config.weekdays.sort()).toEqual([1, 2, 3, 4, 5])
        })

        test('Different times should fall back to simple mode', () => {
            const result = detectPattern([
                { startAt: '2024-01-15 14:00:00', endAt: '2024-01-15 18:00:00' },
                { startAt: '2024-01-16 10:00:00', endAt: '2024-01-16 12:00:00' }, // Different time
                { startAt: '2024-01-17 14:00:00', endAt: '2024-01-17 18:00:00' },
            ])
            expect(result.mode).toBe('simple')
        })

        test('Non-consecutive days with weekday pattern', () => {
            const result = detectPattern([
                { startAt: '2024-01-15 14:00:00', endAt: '2024-01-15 18:00:00' }, // Mon
                { startAt: '2024-01-17 14:00:00', endAt: '2024-01-17 18:00:00' }, // Wed
                { startAt: '2024-01-22 14:00:00', endAt: '2024-01-22 18:00:00' }, // Mon (next week)
                { startAt: '2024-01-24 14:00:00', endAt: '2024-01-24 18:00:00' }, // Wed (next week)
            ])
            expect(result.mode).toBe('pattern')
            expect(result.config.pattern).toBe('weekdays')
            expect(result.config.weekdays.sort()).toEqual([1, 3])
        })

        test('Weekend pattern should be detected', () => {
            const result = detectPattern([
                { startAt: '2024-01-20 10:00:00', endAt: '2024-01-20 22:00:00' }, // Sat
                { startAt: '2024-01-21 10:00:00', endAt: '2024-01-21 22:00:00' }, // Sun
                { startAt: '2024-01-27 10:00:00', endAt: '2024-01-27 22:00:00' }, // Sat
                { startAt: '2024-01-28 10:00:00', endAt: '2024-01-28 22:00:00' }, // Sun
            ])
            expect(result.mode).toBe('pattern')
            expect(result.config.pattern).toBe('weekdays')
            expect(result.config.weekdays.sort()).toEqual([6, 7])
        })

        test('Single weekday repeated should be weekdays pattern', () => {
            const result = detectPattern([
                { startAt: '2024-01-17 19:00:00', endAt: '2024-01-17 23:00:00' }, // Wed
                { startAt: '2024-01-24 19:00:00', endAt: '2024-01-24 23:00:00' }, // Wed
                { startAt: '2024-01-31 19:00:00', endAt: '2024-01-31 23:00:00' }, // Wed
            ])
            expect(result.mode).toBe('pattern')
            expect(result.config.pattern).toBe('weekdays')
            expect(result.config.weekdays).toEqual([3])
        })

        test('Irregular dates should fall back to simple mode', () => {
            const result = detectPattern([
                { startAt: '2024-01-15 14:00:00', endAt: '2024-01-15 18:00:00' },
                { startAt: '2024-01-18 14:00:00', endAt: '2024-01-18 18:00:00' },
                { startAt: '2024-01-22 14:00:00', endAt: '2024-01-22 18:00:00' },
                { startAt: '2024-01-29 14:00:00', endAt: '2024-01-29 18:00:00' },
            ])
            // This doesn't form a clear weekday pattern, should fall back to simple
            expect(result.mode).toBe('simple')
        })

        test('Every Monday pattern with gaps should be detected', () => {
            const result = detectPattern([
                { startAt: '2024-01-01 19:00:00', endAt: '2024-01-01 22:00:00' }, // Mon
                { startAt: '2024-01-08 19:00:00', endAt: '2024-01-08 22:00:00' }, // Mon
                { startAt: '2024-01-15 19:00:00', endAt: '2024-01-15 22:00:00' }, // Mon (missing 22nd)
                { startAt: '2024-01-29 19:00:00', endAt: '2024-01-29 22:00:00' }, // Mon
            ])
            expect(result.mode).toBe('pattern')
            expect(result.config.pattern).toBe('weekdays')
            expect(result.config.weekdays).toEqual([1]) // Monday only
            expect(result.config.startTime).toBe('19:00')
            expect(result.config.endTime).toBe('22:00')
        })

        test('Two Mondays should detect Monday pattern', () => {
            const result = detectPattern([
                { startAt: '2024-02-05 18:00:00', endAt: '2024-02-05 21:00:00' }, // Mon
                { startAt: '2024-02-12 18:00:00', endAt: '2024-02-12 21:00:00' }, // Mon
            ])
            expect(result.mode).toBe('pattern')
            expect(result.config.pattern).toBe('weekdays')
            expect(result.config.weekdays).toEqual([1])
        })

        test('Tue/Thu pattern with 2 occurrences each should be detected', () => {
            const result = detectPattern([
                { startAt: '2024-01-02 10:00:00', endAt: '2024-01-02 12:00:00' }, // Tue
                { startAt: '2024-01-04 10:00:00', endAt: '2024-01-04 12:00:00' }, // Thu
                { startAt: '2024-01-09 10:00:00', endAt: '2024-01-09 12:00:00' }, // Tue
                { startAt: '2024-01-11 10:00:00', endAt: '2024-01-11 12:00:00' }, // Thu
            ])
            expect(result.mode).toBe('pattern')
            expect(result.config.pattern).toBe('weekdays')
            expect(result.config.weekdays.sort()).toEqual([2, 4]) // Tue, Thu
        })

        test('Mon/Wed pattern with perfect coverage should be detected', () => {
            const result = detectPattern([
                { startAt: '2024-01-15 09:00:00', endAt: '2024-01-15 17:00:00' }, // Mon
                { startAt: '2024-01-17 09:00:00', endAt: '2024-01-17 17:00:00' }, // Wed
            ])
            expect(result.mode).toBe('pattern')
            expect(result.config.pattern).toBe('weekdays')
            expect(result.config.weekdays.sort()).toEqual([1, 3]) // Mon, Wed
        })

        test('Mon/Wed/Fri with 2 Mon, 1 Wed, 2 Fri should be detected', () => {
            const result = detectPattern([
                { startAt: '2024-01-15 14:00:00', endAt: '2024-01-15 18:00:00' }, // Mon
                { startAt: '2024-01-17 14:00:00', endAt: '2024-01-17 18:00:00' }, // Wed
                { startAt: '2024-01-19 14:00:00', endAt: '2024-01-19 18:00:00' }, // Fri
                { startAt: '2024-01-22 14:00:00', endAt: '2024-01-22 18:00:00' }, // Mon
                { startAt: '2024-01-26 14:00:00', endAt: '2024-01-26 18:00:00' }, // Fri
            ])
            expect(result.mode).toBe('pattern')
            expect(result.config.pattern).toBe('weekdays')
            expect(result.config.weekdays.sort()).toEqual([1, 3, 5]) // Mon, Wed, Fri
        })
    })
})
