/**
 * Tests for the time picker service
 * Run: yarn test
 */

import { beforeEach, describe, expect, test, vi } from 'vitest'
import { create } from '@/js/services/ui/TimepickerService'

// The library needs a DOM: its constructor is mocked to capture the options it gets
const { Calendar, Maskito } = vi.hoisted(() => ({
    Calendar: vi.fn(function (_input, options) {
        this.options = options
        this.context = { isShowInInputMode: false, selectedTime: options.selectedTime }
        this.set = vi.fn()
        this.init = vi.fn()
        this.destroy = vi.fn()
    }),
    Maskito: vi.fn(function () {
        this.destroy = vi.fn()
    }),
}))

vi.mock('vanilla-calendar-pro', () => ({ Calendar, time: {} }))
vi.mock('@maskito/core', () => ({ Maskito }))
vi.mock('@maskito/kit', () => ({ maskitoTime: (params) => ({ params }) }))

const options = () => Calendar.mock.instances[0].options

describe('TimepickerService', () => {
    let element

    beforeEach(() => {
        Calendar.mockClear()
        Maskito.mockClear()
        vi.stubGlobal(
            'Event',
            class {
                constructor(type) {
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
        create({ element })

        expect(element.type).toBe('text')
        expect(new RegExp(`^${element.pattern}$`).test('20:30')).toBe(true)
        expect(new RegExp(`^${element.pattern}$`).test('24:00')).toBe(false)
        expect(options().layouts.default).toBe('<#ControlTime />')
        expect(Maskito).toHaveBeenCalledWith(element, { params: { mode: 'HH:MM', step: 1 } })
    })

    test('starts from the time of the field, else from the evening', () => {
        element.value = '14:15'
        create({ element })
        expect(options().selectedTime).toBe('14:15')

        Calendar.mockClear()
        create({ element: { ...element, value: '' } })
        expect(options().selectedTime).toBe('20:00')
    })

    test('writes the time picked and tells the listeners of the field', () => {
        create({ element })
        options().onChangeToInput({ context: { selectedTime: '21:45' } })

        expect(element.value).toBe('21:45')
        expect(element.dispatchEvent.mock.calls.map(([event]) => event.type)).toEqual(['input', 'change'])

        // The sliders following a typed time call it back: the field already holds it
        element.dispatchEvent.mockClear()
        options().onChangeToInput({ context: { selectedTime: '21:45' } })
        expect(element.dispatchEvent).not.toHaveBeenCalled()
    })

    test('moves the sliders as a complete time is typed, while the picker is open', () => {
        create({ element })
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
        const picker = create({ element })
        picker.destroy()

        expect(Maskito.mock.instances[0].destroy).toHaveBeenCalled()
        expect(Calendar.mock.instances[0].destroy).toHaveBeenCalled()
        expect(element.removeEventListener).toHaveBeenCalledWith('input', element.addEventListener.mock.calls[0][1])
    })

    test('reopens on the time typed in the field', () => {
        create({ element })
        const self = { context: { selectedTime: '20:00' }, set: vi.fn() }

        element.value = '23:30'
        options().onShow(self)
        expect(self.set).toHaveBeenCalledWith({ selectedTime: '23:30' })

        self.set.mockClear()
        element.value = '23h30'
        options().onShow(self)
        expect(self.set).not.toHaveBeenCalled()
    })
})
