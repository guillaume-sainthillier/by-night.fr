/**
 * Splits a value highlighted by the search API ("Le __aa-highlight__Bikini__/aa-highlight__") into
 * segments, so the matches can be rendered as <mark> nodes without parsing the value as HTML: names
 * come from members and feeds and may contain markup.
 *
 * @param {string} value
 * @returns {{ text: string, highlighted: boolean }[]}
 */
export function splitHighlights(value) {
    return value
        .split(/__aa-highlight__(.*?)__\/aa-highlight__/s)
        .map((text, index) => ({ text, highlighted: index % 2 === 1 }))
        .filter(({ text }) => text !== '')
}

const HTML_ESCAPES = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }

/**
 * @param {*} text
 * @returns {string}
 */
function escapeHtml(text) {
    return String(text).replace(/[&<>"']/g, (char) => HTML_ESCAPES[char])
}

/**
 * Search engine for autoComplete.js: the same case-insensitive substring match as its default one,
 * but the record is escaped before the match gets wrapped in <mark>, the library rendering the
 * result as HTML while tags, cities and usernames are typed by members.
 *
 * @param {string} query
 * @param {*} record
 * @param {boolean} highlight
 * @returns {string|undefined} undefined when the record does not match
 */
export function highlightMatch(query, record, highlight = true) {
    const text = String(record ?? '')
    const needle = String(query).toLowerCase()
    const start = text.toLowerCase().indexOf(needle)
    if (-1 === start) {
        return undefined
    }

    if (!highlight) {
        return escapeHtml(text)
    }

    const end = start + needle.length

    return `${escapeHtml(text.slice(0, start))}<mark>${escapeHtml(text.slice(start, end))}</mark>${escapeHtml(text.slice(end))}`
}
