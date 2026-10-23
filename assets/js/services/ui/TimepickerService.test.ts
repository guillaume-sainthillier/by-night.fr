/**
 * Tests for the time picker service
 * Run: pnpm test
 */

import type { MaskitoTimeParams } from '@maskito/kit'
import type { Calendar as CalendarType, Options } from 'vanilla-calendar-pro'
import { beforeEach, describe, expect, type Mock, test, vi } from 'vitest'
import { create } from '@/js/services/ui/TimepickerService'
import { fake } from '@/js/test/fake'

interface CalendarDouble {
    options: Options
    context: { isShowInInputMode: boolean; selectedTime: string | undefined }
    set: Mock<(options: Options) => void>
    init: () => void
    destroy: () => void
}

// The library needs a DOM: its constructor is mocked to capture the options it gets
const { Calendar, Maskito } = vi.hoisted(() => ({
    Calendar: vi.fn(function (this: CalendarDouble, _input: HTMLElement, options: Options) {
        this.options = options
        this.context = { isShowInInputMode: false, selectedTime: options.selectedTime }
        this.set = vi.fn()
        this.init = vi.fn()
        this.destroy = vi.fn()
    }),
    Maskito: vi.fn(function (this: { destroy: () => void }) {
        this.destroy = vi.fn()
    }),
}))

vi.mock('vanilla-calendar-pro', () => ({ Calendar, time: {} }))
vi.mock('@maskito/core', () => ({ Maskito }))
vi.mock('@maskito/kit', () => ({ maskitoTime: (params: MaskitoTimeParams) => ({ params }) }))

const options = () => Calendar.mock.instances[0].options

// What the picker hands its callbacks: itself
const calendarOf = (self: { context: { selectedTime: string }; set?: Mock<(options: Options) => void> }) =>
    fake<CalendarType>(self)

interface Field {
    type: string
    value: string
    placeholder: string
    title: string
    pattern?: string
    dispatchEvent: Mock<(event: { type: string }) => boolean>
    addEventListener: Mock<(type: string, listener: () => void) => void>
    removeEventListener: Mock<(type: string, listener: () => void) => void>
}

describe('TimepickerService', () => {
    let element: Field

    beforeEach(() => {
        Calendar.mockClear()
        Maskito.mockClear()
        vi.stubGlobal(
            'Event',
            class {
                type: string

                constructor(type: string) {
                    this.type = type
                }
            }
        )
        element = {
            type: 'time',
            value: '',
            placeholder: '',
            title: '',
            dispatchEvent: vi.fn(),
            addEventListener: vi.fn(),
            removeEventListener: vi.fn(),
        }
    })

    test('turns the time field into a text field the picker fills', () => {
        create({ element: fake<HTMLInputElement>(element) })

        expect(element.type).toBe('text')
        expect(new RegExp(`^${element.pattern}$`).test('20:30')).toBe(true)
        expect(new RegExp(`^${element.pattern}$`).test('24:00')).toBe(false)
        expect(options().layouts?.default).toBe('<#ControlTime />')
        expect(Maskito).toHaveBeenCalledWith(element, { params: { mode: 'HH:MM', step: 1 } })
    })

    test('starts from the time of the field, else from the evening', () => {
        element.value = '14:15'
        create({ element: fake<HTMLInputElement>(element) })
        expect(options().selectedTime).toBe('14:15')

        Calendar.mockClear()
        create({ element: fake<HTMLInputElement>({ ...element, value: '' }) })
        expect(options().selectedTime).toBe('20:00')
    })

    test('writes the time picked and tells the listeners of the field', () => {
        create({ element: fake<HTMLInputElement>(element) })
        options().onChangeToInput?.(calendarOf({ context: { selectedTime: '21:45' } }), fake<Event>({}))

        expect(element.value).toBe('21:45')
        expect(element.dispatchEvent.mock.calls.map(([event]) => event.type)).toEqual(['input', 'change'])

        // The sliders following a typed time call it back: the field already holds it
        element.dispatchEvent.mockClear()
        options().onChangeToInput?.(calendarOf({ context: { selectedTime: '21:45' } }), fake<Event>({}))
        expect(element.dispatchEvent).not.toHaveBeenCalled()
    })

    test('moves the sliders as a complete time is typed, while the picker is open', () => {
        create({ element: fake<HTMLInputElement>(element) })
        const calendar = Calendar.mock.instances[0]
        const [[type, onInput]] = element.addEventListener.mock.calls
        expect(type).toBe('input')

        element.value = '19:30'
        onInput()
        expect(calendar.set).not.toHaveBeenCalled()

        calendar.context.isShowInInputMode = true
        element.value = '19:3'
        onInput()
        expect(calendar.set).not.toHaveBeenCalled()

        element.value = '19:30'
        onInput()
        expect(calendar.set).toHaveBeenCalledWith({ selectedTime: '19:30' })
    })

    test('destroys the mask with the picker', () => {
        const picker = create({ element: fake<HTMLInputElement>(element) })
        picker.destroy()

        expect(Maskito.mock.instances[0].destroy).toHaveBeenCalled()
        expect(Calendar.mock.instances[0].destroy).toHaveBeenCalled()
        expect(element.removeEventListener).toHaveBeenCalledWith('input', element.addEventListener.mock.calls[0][1])
    })

    test('reopens on the time typed in the field', () => {
        create({ element: fake<HTMLInputElement>(element) })
        const self = { context: { selectedTime: '20:00' }, set: vi.fn() }

        element.value = '23:30'
        options().onShow?.(calendarOf(self))
        expect(self.set).toHaveBeenCalledWith({ selectedTime: '23:30' })

        self.set.mockClear()
        element.value = '23h30'
        options().onShow?.(calendarOf(self))
        expect(self.set).not.toHaveBeenCalled()
    })
})
