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
