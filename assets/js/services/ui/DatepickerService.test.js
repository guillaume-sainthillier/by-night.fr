/**
 * Tests for the date picker service
 * Run: yarn test
 */

import { beforeEach, describe, expect, test, vi } from 'vitest'
import { create, formatRangeLabel } from '@/js/services/ui/DatepickerService'

// The library needs a DOM: its constructor is mocked to capture the options it gets
const { Calendar } = vi.hoisted(() => ({
    Calendar: vi.fn(function (_input, options) {
        this.options = options
        this.init = vi.fn()
        this.destroy = vi.fn()
    }),
}))

vi.mock('vanilla-calendar-pro', () => ({ Calendar, months: {} }))
vi.mock('@/js/utils/utils', () => ({ isTouchDevice: () => false }))

function field(value = '', panel = null) {
    return {
        value,
        removeAttribute: vi.fn(),
        classList: { add: vi.fn() },
        dispatchEvent: vi.fn(),
        closest: () => panel,
    }
}

// Clicks in the picker: the library has updated its selection when it calls onChangeToInput
function pick(...selectedDates) {
    const self = { context: { selectedDates }, hide: vi.fn() }
    Calendar.mock.instances[0].options.onChangeToInput(self)
    return self
}

describe('formatRangeLabel', () => {
    test('writes the labels of DateRange::label()', () => {
        expect(formatRangeLabel('2026-10-03', '2026-10-03')).toBe('Le 3 oct. 2026')
        expect(formatRangeLabel('2026-10-03', '2026-10-05')).toBe('Du 3 oct. 2026 au 5 oct. 2026')
        expect(formatRangeLabel('2026-10-03', null)).toBe('À partir du 3 oct. 2026')
    })
})

describe('DatepickerService', () => {
    let input, from, to, onApply

    beforeEach(() => {
        Calendar.mockClear()
        vi.stubGlobal('window', { matchMedia: () => ({ matches: true }) })
        vi.stubGlobal('Event', class {})
        input = field()
        from = field('2026-10-09')
        to = field('2026-10-11')
        onApply = vi.fn()
    })

    test('starts from the dates of the hidden inputs', () => {
        create({ element: input, fromInput: from, toInput: to })

        expect(input.value).toBe('Du 9 oct. 2026 au 11 oct. 2026')
        expect(Calendar.mock.instances[0].options.selectedDates).toEqual(['2026-10-09', '2026-10-11'])
    })

    test('keeps a range open from its start as the server labels it', () => {
        create({ element: input, fromInput: from, toInput: field() })

        expect(input.value).toBe('À partir du 9 oct. 2026')
        expect(Calendar.mock.instances[0].options.selectedDates).toEqual(['2026-10-09'])
    })

    test('opens below a field of a panel fixed to the screen, wherever there is room otherwise', () => {
        vi.stubGlobal('getComputedStyle', (element) => ({ position: element.position }))

        create({ element: input, fromInput: from, toInput: to })
        // The agenda filters' offcanvas on a phone
        create({ element: field('', { position: 'fixed' }), fromInput: from, toInput: to })
        // The same panel from the lg breakpoint on: a sidebar
        create({ element: field('', { position: 'static' }), fromInput: from, toInput: to })

        expect(Calendar.mock.instances.map((calendar) => calendar.options.positionToInput)).toEqual([
            'auto',
            ['bottom', 'left'],
            'auto',
        ])
    })

    test('applies a range on its second click only', () => {
        create({ element: input, fromInput: from, toInput: to, onApply })

        const first = pick('2026-10-16')
        expect(first.hide).not.toHaveBeenCalled()
        expect(onApply).not.toHaveBeenCalled()

        const second = pick('2026-10-16', '2026-10-25')
        expect(second.hide).toHaveBeenCalled()
        expect([from.value, to.value, input.value]).toEqual([
            '2026-10-16',
            '2026-10-25',
            'Du 16 oct. 2026 au 25 oct. 2026',
        ])
        expect(onApply).toHaveBeenCalledWith({ start: '2026-10-16', end: '2026-10-25' })
    })

    test('applies a single date on its first click', () => {
        create({ element: input, fromInput: from, toInput: to, singleDate: true })

        expect(pick('2026-10-15').hide).toHaveBeenCalled()
        expect([from.value, to.value, input.value]).toEqual(['2026-10-15', '2026-10-15', '15 oct. 2026'])
    })
})
