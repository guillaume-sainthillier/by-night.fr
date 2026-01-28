import moment from 'moment'

/**
 * Detect pattern in existing timesheets
 *
 * @param {Array<{startAt: string, endAt: string}>} timesheets - Array of timesheets
 * @returns {{mode: string, config: Object}} Detection result
 */
export function detectPattern(timesheets) {
    if (!timesheets || timesheets.length === 0) {
        return {
            mode: 'simple',
            config: {
                startTime: '09:00',
                endTime: '17:00',
            },
        }
    }

    // Parse timesheets
    const parsed = timesheets.map((ts) => ({
        startAt: moment(ts.startAt, 'YYYY-MM-DD HH:mm'),
        endAt: moment(ts.endAt, 'YYYY-MM-DD HH:mm'),
    }))

    // Check if all have same time
    const firstStartTime = parsed[0].startAt.format('HH:mm')
    const firstEndTime = parsed[0].endAt.format('HH:mm')

    const allSameTime = parsed.every(
        (ts) => ts.startAt.format('HH:mm') === firstStartTime && ts.endAt.format('HH:mm') === firstEndTime
    )

    if (!allSameTime) {
        // Default to simple mode if no pattern detected
        return {
            mode: 'simple',
            config: {
                startTime: firstStartTime,
                endTime: firstEndTime,
            },
        }
    }

    // Extract dates
    const dates = parsed.map((ts) => ts.startAt.clone().startOf('day'))

    // Single date - simple mode
    if (dates.length === 1) {
        return {
            mode: 'simple',
            config: {
                startTime: firstStartTime,
                endTime: firstEndTime,
            },
        }
    }

    // Sort dates
    dates.sort((a, b) => a.diff(b))

    // Check if daily (consecutive dates)
    const isDaily = dates.every((date, index) => {
        if (index === 0) return true
        const prevDate = dates[index - 1]
        return date.diff(prevDate, 'days') === 1
    })

    if (isDaily) {
        return {
            mode: 'pattern',
            config: {
                pattern: 'daily',
                startTime: firstStartTime,
                endTime: firstEndTime,
            },
        }
    }

    // Check if specific weekdays
    const weekdays = [...new Set(dates.map((date) => date.isoWeekday()))].sort()

    if (weekdays.length > 0 && weekdays.length <= 7) {
        // Count occurrences of each weekday to ensure consistency
        const weekdayCounts = {}
        dates.forEach((date) => {
            const day = date.isoWeekday()
            weekdayCounts[day] = (weekdayCounts[day] || 0) + 1
        })

        // Check coverage - how many of the expected occurrences do we have?
        const minDate = dates[0]
        const maxDate = dates[dates.length - 1]

        let expectedCount = 0
        let current = minDate.clone()

        while (current.isSameOrBefore(maxDate)) {
            if (weekdays.includes(current.isoWeekday())) {
                expectedCount++
            }
            current.add(1, 'day')
        }

        // Check if pattern is consistent
        const isPerfectMatch = dates.length === expectedCount
        const coverageRatio = expectedCount > 0 ? dates.length / expectedCount : 0

        // Check consistency: if we have weekdays that appear only once, they're outliers
        const recurringDayCount = Object.values(weekdayCounts).filter((count) => count >= 2).length
        const singleOccurrenceDayCount = Object.values(weekdayCounts).filter((count) => count === 1).length

        // Calculate what percentage of timesheets are on recurring days
        const timesheetsOnRecurringDays = Object.entries(weekdayCounts)
            .filter(([_, count]) => count >= 2)
            .reduce((sum, [_, count]) => sum + count, 0)
        const recurringRatio = dates.length > 0 ? timesheetsOnRecurringDays / dates.length : 0

        // At least one weekday appears multiple times (showing recurrence)
        const hasRecurringDay = recurringDayCount > 0

        // Good coverage with minimum timesheets
        const hasGoodCoverage = coverageRatio >= 0.5 && dates.length >= 2

        // If we have single-occurrence days, they should be a small minority (< 20% of timesheets)
        const hasTooManyOutliers = singleOccurrenceDayCount > 0 && recurringRatio < 0.8

        // Pattern is valid if:
        // 1. Perfect match (all expected occurrences present), OR
        // 2. At least one weekday recurs AND we don't have too many outliers, OR
        // 3. Good coverage (50%+) with at least 2 timesheets and max 5 weekdays and 80%+ recurring ratio
        const isValidPattern =
            isPerfectMatch ||
            (hasRecurringDay && !hasTooManyOutliers) ||
            (hasGoodCoverage && weekdays.length <= 5 && recurringRatio >= 0.8)

        if (isValidPattern) {
            return {
                mode: 'pattern',
                config: {
                    pattern: 'weekdays',
                    weekdays: weekdays,
                    startTime: firstStartTime,
                    endTime: firstEndTime,
                },
            }
        }
    }

    // Default to simple mode
    return {
        mode: 'simple',
        config: {
            startTime: firstStartTime,
            endTime: firstEndTime,
        },
    }
}
