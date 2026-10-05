import { Calendar, time } from 'vanilla-calendar-pro'
import 'vanilla-calendar-pro/styles/layout/core.css'
import 'vanilla-calendar-pro/styles/layout/time.css'
import 'vanilla-calendar-pro/styles/themes/light/core.css'
import 'vanilla-calendar-pro/styles/themes/light/time.css'
import '@/scss/lazy-components/_datepicker.scss'

const TIME_PATTERN = /^([01]\d|2[0-3]):[0-5]\d$/

// What the picker shows on an empty field: most events start in the evening
const DEFAULT_TIME = '20:00'

/**
 * A time picker on a TimeType field ("HH:mm"): the hour and minute of the calendar's time control, without its dates.
 * The field stays a text input, so that it can still be typed in or emptied.
 *
 * @param {{element: HTMLInputElement, step?: number}} options
 */
export function create({ element, step = 5 } = {}) {
    // The browser's own picker would open over ours: <input type="time"> is only the fallback without JavaScript
    // A text field keeps the colon on phone keyboards, which inputmode="numeric" would leave out
    element.type = 'text'
    element.autocomplete = 'off'
    element.pattern = TIME_PATTERN.source.slice(1, -1)
    element.title ||= 'Heure au format hh:mm'
    element.placeholder ||= 'hh:mm'

    const calendar = new Calendar(element, {
        extensions: [time],
        inputMode: true,
        positionToInput: 'auto',
        selectedTheme: 'light',
        layouts: { default: '<#ControlTime />' },
        selectionTimeMode: 24,
        timeStepMinute: step,
        selectedTime: TIME_PATTERN.test(element.value) ? element.value : DEFAULT_TIME,
        // Starts from what was typed in the field since the last opening
        onShow(self) {
            if (TIME_PATTERN.test(element.value) && element.value !== self.context.selectedTime) {
                self.set({ selectedTime: element.value })
            }
        },
        onChangeToInput(self) {
            element.value = self.context.selectedTime
            // The listeners of the field (timesheet-hours-sync) read it as it is typed
            element.dispatchEvent(new Event('input', { bubbles: true }))
            element.dispatchEvent(new Event('change', { bubbles: true }))
        },
    })
    calendar.init()

    return {
        destroy: () => calendar.destroy(),
    }
}
