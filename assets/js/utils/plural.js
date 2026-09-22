const pluralRules = new Intl.PluralRules('fr-FR')
const numberFormat = new Intl.NumberFormat('fr-FR')

/**
 * Picks the form agreeing with `count` under French plural rules (0 and 1 are singular),
 * replacing "#" with the count grouped by thousands: plural(3, { one: '# date', other: '# dates' }) → "3 dates".
 *
 * @param {number} count
 * @param {{one: string, other: string}} forms
 * @returns {string}
 */
export const plural = (count, { one, other }) =>
    (pluralRules.select(count) === 'one' ? one : other).replace('#', numberFormat.format(count))
