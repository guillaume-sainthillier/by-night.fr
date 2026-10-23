const pluralRules = new Intl.PluralRules('fr-FR')
const numberFormat = new Intl.NumberFormat('fr-FR')

/**
 * The singular and plural forms of a phrase, "#" standing for the count
 */
export interface PluralForms {
    one: string
    other: string
}

/**
 * Picks the form agreeing with `count` under French plural rules (0 and 1 are singular),
 * replacing "#" with the count grouped by thousands: plural(3, { one: '# date', other: '# dates' }) → "3 dates".
 */
export const plural = (count: number, { one, other }: PluralForms): string =>
    (pluralRules.select(count) === 'one' ? one : other).replace('#', numberFormat.format(count))
