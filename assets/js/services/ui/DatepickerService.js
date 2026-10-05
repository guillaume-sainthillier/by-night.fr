import { Calendar, months } from 'vanilla-calendar-pro'
// Only the modules the pickers use: no time picker, week numbers, range tooltip or motion
import 'vanilla-calendar-pro/styles/layout/core.css'
import 'vanilla-calendar-pro/styles/layout/months.css'
import 'vanilla-calendar-pro/styles/themes/light/core.css'
import 'vanilla-calendar-pro/styles/themes/light/months.css'
import { isTouchDevice } from '@/js/utils/utils'
import '@/scss/lazy-components/_datepicker.scss'

// ICU's medium style, as DateRange::label() formats it server side: "3 oct. 2026"
const dateFormatter = new Intl.DateTimeFormat('fr', { dateStyle: 'medium' })

// Two months side by side need about 37rem: narrower screens get one
const WIDE_SCREEN = '(min-width: 576px)'

function resolveElement(element) {
    if (typeof element === 'string') {
        return document.querySelector(element)
    }
    return element
}

/**
 * Whether the field sits in a panel fixed to the screen, as the agenda filters' offcanvas on a phone: the calendar's
 * "auto" placement opens it above the field there, its header out of the screen.
 *
 * @param {HTMLElement} input
 * @returns {boolean}
 */
function inFixedPanel(input) {
    const panel = input.closest(
        '.offcanvas, .offcanvas-sm, .offcanvas-md, .offcanvas-lg, .offcanvas-xl, .offcanvas-xxl'
    )
    return panel !== null && getComputedStyle(panel).position === 'fixed'
}

/**
 * @param {string} date - YYYY-MM-DD
 * @returns {string}
 */
function formatDate(date) {
    const [year, month, day] = date.split('-').map(Number)
    return dateFormatter.format(new Date(year, month - 1, day))
}

/**
 * The label DateRange::label() renders: "Le 3 oct. 2026", "Du 3 oct. 2026 au 5 oct. 2026", "À partir du 3 oct. 2026".
 *
 * @param {string} start - YYYY-MM-DD
 * @param {string|null} end - YYYY-MM-DD
 * @returns {string}
 */
export function formatRangeLabel(start, end) {
    if (!end) {
        return `À partir du ${formatDate(start)}`
    }
    if (start === end) {
        return `Le ${formatDate(start)}`
    }
    return `Du ${formatDate(start)} au ${formatDate(end)}`
}

export function create({ element, fromInput, toInput, singleDate = false, onApply = null } = {}) {
    const input = resolveElement(element)
    const from = resolveElement(fromInput)
    const to = resolveElement(toInput)

    // Remove name attribute to prevent double form submission
    input.removeAttribute('name')

    // Make readonly on touch devices
    if (isTouchDevice()) {
        input.readOnly = true
        input.classList.add('form-control-readonly')
    }

    const apply = (start, end) => {
        input.value = singleDate ? formatDate(start) : formatRangeLabel(start, end)
        if (from) {
            from.value = start
        }
        if (to) {
            to.value = end
        }

        // Native change events for the listeners of the hidden inputs (Preact, etc.)
        from?.dispatchEvent(new Event('change', { bubbles: true }))
        to?.dispatchEvent(new Event('change', { bubbles: true }))

        onApply?.({ start, end })
    }

    // Initialize with existing values
    const selectedDates = []
    if (from?.value) {
        const start = from.value
        // No end: a range open from its start ("À partir du 3 oct. 2026"), not that day alone
        const end = to?.value || null
        if (singleDate) {
            selectedDates.push(start)
            input.value = formatDate(start)
        } else {
            selectedDates.push(...(end ? [start, end] : [start]))
            input.value = formatRangeLabel(start, end)
        }
    }

    const wide = !singleDate && window.matchMedia(WIDE_SCREEN).matches
    const calendar = new Calendar(input, {
        // Side by side months (type: 'multiple')
        extensions: [months],
        inputMode: true,
        positionToInput: inFixedPanel(input) ? ['bottom', 'left'] : 'auto',
        locale: 'fr-FR',
        firstWeekday: 1,
        selectedTheme: 'light',
        selectedWeekends: [],
        type: wide ? 'multiple' : 'default',
        displayMonthsCount: wide ? 2 : undefined,
        monthsToSwitch: 1,
        displayDatesOutside: !wide,
        selectionDatesMode: singleDate ? 'single' : 'multiple-ranged',
        // A second click on the start day picks that day alone instead of clearing the selection
        enableDateToggle: false,
        enableJumpToSelectedDate: true,
        selectedDates,
        // Applies once the range is complete: the first click only sets its start
        onChangeToInput(self) {
            const dates = self.context.selectedDates
            if (dates.length === (singleDate ? 1 : 2)) {
                apply(dates[0], dates[dates.length - 1])
                self.hide()
            }
        },
    })
    calendar.init()

    return {
        destroy: () => calendar.destroy(),
    }
}
