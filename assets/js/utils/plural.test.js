import { describe, expect, it } from 'vitest'
import { plural } from './plural'

const forms = { one: '# date générée', other: '# dates générées' }

describe('plural', () => {
    it('uses the singular for 0 and 1', () => {
        expect(plural(0, forms)).toBe('0 date générée')
        expect(plural(1, forms)).toBe('1 date générée')
    })

    it('uses the plural from 2', () => {
        expect(plural(2, forms)).toBe('2 dates générées')
    })

    it('groups thousands the French way', () => {
        expect(plural(12345, forms)).toBe('12 345 dates générées')
    })

    it('leaves forms without a placeholder as they are', () => {
        expect(plural(3, { one: 'date sera générée', other: 'dates seront générées' })).toBe('dates seront générées')
    })
})
