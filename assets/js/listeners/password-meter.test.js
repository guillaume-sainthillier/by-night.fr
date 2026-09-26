/**
 * Tests for the password-meter listener
 * Run: yarn test
 */

import { describe, expect, test } from 'vitest'
import { checkRules, strengthTone } from '@/js/listeners/password-meter'

// PasswordRequirements::RULES
const PATTERNS = ['.{8,}', '\\d', '\\p{Lu}']

describe('checkRules', () => {
    test.each([
        ['', [false, false, false]],
        ['motdepasse', [true, false, false]],
        ['motdepasse1', [true, true, false]],
        ['Motdepasse1', [true, true, true]],
        ['Court1', [false, true, true]],
    ])('"%s" meets %j', (password, expected) => {
        expect(checkRules(password, PATTERNS)).toEqual(expected)
    })

    test('reads an accented capital as a capital, as the server does', () => {
        expect(checkRules('Été', PATTERNS)[2]).toBe(true)
    })

    test('counts characters, not UTF-16 units, as the server does', () => {
        // 7 emojis are 14 UTF-16 units: without the "u" flag they would pass the 8-character rule
        expect(checkRules('🎉🎉🎉🎉🎉🎉🎉', PATTERNS)[0]).toBe(false)
    })
})

describe('strengthTone', () => {
    test.each([
        [0, null],
        [1, 'bg-danger'],
        [2, 'bg-warning'],
        [3, 'bg-success'],
    ])('%i rules met out of 3 give %s', (met, tone) => {
        expect(strengthTone(met, 3)).toBe(tone)
    })
})
