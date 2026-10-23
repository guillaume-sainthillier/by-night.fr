import { Calendar, months } from 'vanilla-calendar-pro'
// Only the modules the pickers use: no time picker, week numbers, range tooltip or motion
import 'vanilla-calendar-pro/styles/layout/core.css'
import 'vanilla-calendar-pro/styles/layout/months.css'
import 'vanilla-calendar-pro/styles/themes/light/core.css'
import 'vanilla-calendar-pro/styles/themes/light/months.css'
import { formatDay, parseDay } from '@/js/utils/days'
import { isTouchDevice } from '@/js/utils/utils'
import '@/scss/lazy-components/_datepicker.scss'

// ICU's medium style, as DateRange::label() formats it server side: "3 oct. 2026"
const dateFormatter = new Intl.DateTimeFormat('fr', { dateStyle: 'medium' })

// Two months side by side need about 37rem: narrower screens get one
const WIDE_SCREEN = '(min-width: 576px)'

interface DateRangeSelection {
    /** YYYY-MM-DD */
    start: string
    /** YYYY-MM-DD */
    end: string
}

export interface DatepickerOptions {
    element: string | HTMLInputElement
    fromInput?: string | HTMLInputElement | null
    toInput?: string | HTMLInputElement | null
    singleDate?: boolean
    onApply?: ((selection: DateRangeSelection) => void) | null
    /** YYYY-MM-DD: no day after it can be picked (a period looking back), and its month is shown last */
    maxDate?: string | null
}

function resolveElement(element: string | HTMLInputElement | null | undefined): HTMLInputElement | null {
    if (typeof element === 'string') {
        return document.querySelector<HTMLInputElement>(element)
    }
    return element ?? null
}

/**
 * Whether the field sits in a panel fixed to the screen, as the agenda filters' offcanvas on a phone: the calendar's
 * "auto" placement opens it above the field there, its header out of the screen.
 */
function inFixedPanel(input: HTMLElement): boolean {
    const panel = input.closest(
        '.offcanvas, .offcanvas-sm, .offcanvas-md, .offcanvas-lg, .offcanvas-xl, .offcanvas-xxl'
    )
    return panel !== null && getComputedStyle(panel).position === 'fixed'
}

/**
 * @param date - YYYY-MM-DD
 */
function formatDate(date: string): string {
    const [year, month, day] = date.split('-').map(Number)
    return dateFormatter.format(new Date(year, month - 1, day))
}

/**
 * The label DateRange::label() renders: "Le 3 oct. 2026", "Du 3 oct. 2026 au 5 oct. 2026", "À partir du 3 oct. 2026".
 *
 * @param start - YYYY-MM-DD
 * @param end - YYYY-MM-DD
 */
export function formatRangeLabel(start: string, end: string | null): string {
    if (!end) {
        return `À partir du ${formatDate(start)}`
    }
    if (start === end) {
        return `Le ${formatDate(start)}`
    }
    return `Du ${formatDate(start)} au ${formatDate(end)}`
}

/**
 * The day of a hidden input. The server sends back a value it could not read as a date as it came
 * ("?dateRange[from]=undefined"), and searches without it: no day then.
 *
 * @returns YYYY-MM-DD
 */
function dayOf(input: HTMLInputElement | null): string | null {
    const day = parseDay(input?.value)
    return day ? formatDay(day) : null
}

/**
 * The months the calendar opens on when it looks back to maxDate: the one before maxDate's and maxDate's own side by side,
 * or the dates picked's when they are older. Undefined without maxDate (the library's default: today's, or the dates
 * picked's).
 *
 * @param maxDate - YYYY-MM-DD
 * @param start - YYYY-MM-DD, the first day picked
 * @param wide - two months side by side
 */
function firstMonthShown(
    maxDate: string | null,
    start: string | null,
    wide: boolean
): { selectedMonth: number; selectedYear: number } | undefined {
    const max = parseDay(maxDate)
    if (!max) {
        return undefined
    }

    let first = new Date(max.getFullYear(), max.getMonth() - (wide ? 1 : 0), 1)
    const picked = parseDay(start)
    if (picked && picked < first) {
        first = new Date(picked.getFullYear(), picked.getMonth(), 1)
    }

    return { selectedMonth: first.getMonth(), selectedYear: first.getFullYear() }
}

export function create({
    element,
    fromInput,
    toInput,
    singleDate = false,
    onApply = null,
    maxDate = null,
}: DatepickerOptions): {
    destroy: () => void
} {
    const input = resolveElement(element)
    if (!input) {
        throw new Error(`Datepicker: no input matches ${String(element)}`)
    }
    const from = resolveElement(fromInput)
    const to = resolveElement(toInput)

    // Remove name attribute to prevent double form submission
    input.removeAttribute('name')

    // Make readonly on touch devices
    if (isTouchDevice()) {
        input.readOnly = true
        input.classList.add('form-control-readonly')
    }

    const apply = (start: string, end: string) => {
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
    const selectedDates: string[] = []
    const start = dayOf(from)
    if (start) {
        // No end: a range open from its start ("À partir du 3 oct. 2026"), not that day alone
        const end = dayOf(to)
        if (singleDate) {
            selectedDates.push(start)
            input.value = formatDate(start)
        } else {
            selectedDates.push(...(end ? [start, end] : [start]))
            input.value = formatRangeLabel(start, end)
        }
    }

    const wide = !singleDate && window.matchMedia(WIDE_SCREEN).matches
    // dateMax alone: with displayDateMax, the library opens on maxDate's month first and ignores selectedMonth
    const lookingBack = maxDate ? { dateMax: maxDate, ...firstMonthShown(maxDate, start, wide) } : {}
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
        // Looking back, firstMonthShown() already shows the dates picked
        enableJumpToSelectedDate: !maxDate,
        selectedDates,
        ...lookingBack,
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
