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
                config: {},
            })
        })

        test('Single timesheet should return simple mode', () => {
            const result = detectPattern([{ startAt: '2024-01-15', endAt: '2024-01-15' }])
            expect(result).toEqual({
                mode: 'simple',
                config: {},
            })
        })

        test('Consecutive days should be daily pattern', () => {
            const result = detectPattern([
                { startAt: '2024-01-15', endAt: '2024-01-15' },
                { startAt: '2024-01-16', endAt: '2024-01-16' },
                { startAt: '2024-01-17', endAt: '2024-01-17' },
                { startAt: '2024-01-18', endAt: '2024-01-18' },
            ])
            expect(result).toEqual({
                mode: 'pattern',
                config: {
                    pattern: 'daily',
                },
            })
        })

        test('Mon/Wed/Fri pattern should be detected as weekdays', () => {
            const result = detectPattern([
                { startAt: '2024-01-15', endAt: '2024-01-15' }, // Mon
                { startAt: '2024-01-17', endAt: '2024-01-17' }, // Wed
                { startAt: '2024-01-19', endAt: '2024-01-19' }, // Fri
                { startAt: '2024-01-22', endAt: '2024-01-22' }, // Mon
                { startAt: '2024-01-24', endAt: '2024-01-24' }, // Wed
                { startAt: '2024-01-26', endAt: '2024-01-26' }, // Fri
            ])
            expect(result.mode).toBe('pattern')
            expect(result.config.pattern).toBe('weekdays')
            // Weekdays: 1=Mon, 3=Wed, 5=Fri
            expect(result.config.weekdays.sort()).toEqual([1, 3, 5])
        })

        test('Mon-Fri pattern should be detected as weekdays', () => {
            const result = detectPattern([
                { startAt: '2024-01-15', endAt: '2024-01-15' }, // Mon
                { startAt: '2024-01-16', endAt: '2024-01-16' }, // Tue
                { startAt: '2024-01-17', endAt: '2024-01-17' }, // Wed
                { startAt: '2024-01-18', endAt: '2024-01-18' }, // Thu
                { startAt: '2024-01-19', endAt: '2024-01-19' }, // Fri
                { startAt: '2024-01-22', endAt: '2024-01-22' }, // Mon
                { startAt: '2024-01-23', endAt: '2024-01-23' }, // Tue
                { startAt: '2024-01-24', endAt: '2024-01-24' }, // Wed
                { startAt: '2024-01-25', endAt: '2024-01-25' }, // Thu
                { startAt: '2024-01-26', endAt: '2024-01-26' }, // Fri
            ])
            expect(result.mode).toBe('pattern')
            expect(result.config.pattern).toBe('weekdays')
            expect(result.config.weekdays.sort()).toEqual([1, 2, 3, 4, 5])
        })

        test('Non-consecutive days with weekday pattern', () => {
            const result = detectPattern([
                { startAt: '2024-01-15', endAt: '2024-01-15' }, // Mon
                { startAt: '2024-01-17', endAt: '2024-01-17' }, // Wed
                { startAt: '2024-01-22', endAt: '2024-01-22' }, // Mon (next week)
                { startAt: '2024-01-24', endAt: '2024-01-24' }, // Wed (next week)
            ])
            expect(result.mode).toBe('pattern')
            expect(result.config.pattern).toBe('weekdays')
            expect(result.config.weekdays.sort()).toEqual([1, 3])
        })

        test('Weekend pattern should be detected', () => {
            const result = detectPattern([
                { startAt: '2024-01-20', endAt: '2024-01-20' }, // Sat
                { startAt: '2024-01-21', endAt: '2024-01-21' }, // Sun
                { startAt: '2024-01-27', endAt: '2024-01-27' }, // Sat
                { startAt: '2024-01-28', endAt: '2024-01-28' }, // Sun
            ])
            expect(result.mode).toBe('pattern')
            expect(result.config.pattern).toBe('weekdays')
            expect(result.config.weekdays.sort()).toEqual([6, 7])
        })

        test('Single weekday repeated should be weekdays pattern', () => {
            const result = detectPattern([
                { startAt: '2024-01-17', endAt: '2024-01-17' }, // Wed
                { startAt: '2024-01-24', endAt: '2024-01-24' }, // Wed
                { startAt: '2024-01-31', endAt: '2024-01-31' }, // Wed
            ])
            expect(result.mode).toBe('pattern')
            expect(result.config.pattern).toBe('weekdays')
            expect(result.config.weekdays).toEqual([3])
        })

        test('Irregular dates should fall back to simple mode', () => {
            const result = detectPattern([
                { startAt: '2024-01-15', endAt: '2024-01-15' },
                { startAt: '2024-01-18', endAt: '2024-01-18' },
                { startAt: '2024-01-22', endAt: '2024-01-22' },
                { startAt: '2024-01-29', endAt: '2024-01-29' },
            ])
            // This doesn't form a clear weekday pattern, should fall back to simple
            expect(result.mode).toBe('simple')
        })

        test('Every Monday pattern with gaps should be detected', () => {
            const result = detectPattern([
                { startAt: '2024-01-01', endAt: '2024-01-01' }, // Mon
                { startAt: '2024-01-08', endAt: '2024-01-08' }, // Mon
                { startAt: '2024-01-15', endAt: '2024-01-15' }, // Mon (missing 22nd)
                { startAt: '2024-01-29', endAt: '2024-01-29' }, // Mon
            ])
            expect(result.mode).toBe('pattern')
            expect(result.config.pattern).toBe('weekdays')
            expect(result.config.weekdays).toEqual([1]) // Monday only
        })

        test('Two Mondays should detect Monday pattern', () => {
            const result = detectPattern([
                { startAt: '2024-02-05', endAt: '2024-02-05' }, // Mon
                { startAt: '2024-02-12', endAt: '2024-02-12' }, // Mon
            ])
            expect(result.mode).toBe('pattern')
            expect(result.config.pattern).toBe('weekdays')
            expect(result.config.weekdays).toEqual([1])
        })

        test('Tue/Thu pattern with 2 occurrences each should be detected', () => {
            const result = detectPattern([
                { startAt: '2024-01-02', endAt: '2024-01-02' }, // Tue
                { startAt: '2024-01-04', endAt: '2024-01-04' }, // Thu
                { startAt: '2024-01-09', endAt: '2024-01-09' }, // Tue
                { startAt: '2024-01-11', endAt: '2024-01-11' }, // Thu
            ])
            expect(result.mode).toBe('pattern')
            expect(result.config.pattern).toBe('weekdays')
            expect(result.config.weekdays.sort()).toEqual([2, 4]) // Tue, Thu
        })

        test('Mon/Wed pattern with perfect coverage should be detected', () => {
            const result = detectPattern([
                { startAt: '2024-01-15', endAt: '2024-01-15' }, // Mon
                { startAt: '2024-01-17', endAt: '2024-01-17' }, // Wed
            ])
            expect(result.mode).toBe('pattern')
            expect(result.config.pattern).toBe('weekdays')
            expect(result.config.weekdays.sort()).toEqual([1, 3]) // Mon, Wed
        })

        test('Mon/Wed/Fri with 2 Mon, 1 Wed, 2 Fri should be detected', () => {
            const result = detectPattern([
                { startAt: '2024-01-15', endAt: '2024-01-15' }, // Mon
                { startAt: '2024-01-17', endAt: '2024-01-17' }, // Wed
                { startAt: '2024-01-19', endAt: '2024-01-19' }, // Fri
                { startAt: '2024-01-22', endAt: '2024-01-22' }, // Mon
                { startAt: '2024-01-26', endAt: '2024-01-26' }, // Fri
            ])
            expect(result.mode).toBe('pattern')
            expect(result.config.pattern).toBe('weekdays')
            expect(result.config.weekdays.sort()).toEqual([1, 3, 5]) // Mon, Wed, Fri
        })

        test('Datetime format should still work (backwards compatible)', () => {
            const result = detectPattern([
                { startAt: '2024-01-15 14:00:00', endAt: '2024-01-15 18:00:00' },
                { startAt: '2024-01-16 14:00:00', endAt: '2024-01-16 18:00:00' },
                { startAt: '2024-01-17 14:00:00', endAt: '2024-01-17 18:00:00' },
            ])
            expect(result.mode).toBe('pattern')
            expect(result.config.pattern).toBe('daily')
        })
    })
})
