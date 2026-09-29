const monthFormat = new Intl.DateTimeFormat('fr-FR', { month: 'short' })
const weekdayFormat = new Intl.DateTimeFormat('fr-FR', { weekday: 'long' })

/**
 * The three lines of a calendar leaf (the Event:DateBlock component) for a date field's value, with the formats of the
 * server: leafLines('2026-09-26') → ['sept', '26', 'Samedi']. A missing or malformed date gives a dash.
 *
 * @param {string} value - A date as "YYYY-MM-DD"
 * @returns {[string, string, string]} The month, the day and the weekday
 */
export const leafLines = (value) => {
    const [year, month, day] = value.split('-').map(Number)
    if (!year || !month || !day) {
        return ['', '–', '']
    }

    // Built from its parts: new Date('2026-03-27') is midnight UTC, the day before west of Greenwich
    const date = new Date(year, month - 1, day)
    const weekday = weekdayFormat.format(date)

    return [monthFormat.format(date).replace('.', ''), String(day), weekday.charAt(0).toUpperCase() + weekday.slice(1)]
}
