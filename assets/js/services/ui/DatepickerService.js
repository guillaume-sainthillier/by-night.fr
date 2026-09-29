import $ from 'jquery'
import moment from 'moment'
import 'moment/locale/fr'
import 'daterangepicker'
import { isTouchDevice } from '@/js/utils/utils'
import '@/scss/lazy-components/_datepicker.scss'

moment.locale('fr')

function resolveElement(element) {
    if (typeof element === 'string') {
        return document.querySelector(element)
    }
    return element
}

function formatRangeLabel(start, end) {
    if (!end?.isValid()) {
        return `À partir du ${start.format('ll')}`
    }
    if (start.format('YYYY-MM-DD') === end.format('YYYY-MM-DD')) {
        return `Le ${start.format('ll')}`
    }
    return `Du ${start.format('ll')} au ${end.format('ll')}`
}

export function create({
    element,
    fromInput,
    toInput,
    singleDate = false,
    locale = {
        applyLabel: 'OK',
        cancelLabel: 'Annuler',
        fromLabel: 'Du',
        toLabel: 'Au',
        customRangeLabel: 'Personnalisé',
    },
    onApply = null,
} = {}) {
    const inputElement = resolveElement(element)
    const $input = $(inputElement)
    const $fromInput = $(resolveElement(fromInput))
    const $toInput = $(resolveElement(toInput))

    // Remove name attribute to prevent double form submission
    $input.removeAttr('name')

    // Make readonly on touch devices
    if (isTouchDevice()) {
        $input.attr('readonly', true).addClass('form-control-readonly')
    }

    $input.daterangepicker(
        {
            singleDatePicker: singleDate,
            autoApply: singleDate,
            autoUpdateInput: false,
            locale,
        },
        (start, end, label) => {
            if (singleDate) {
                $input.val(start.format('ll'))
                $fromInput.val(start.format('YYYY-MM-DD'))
                $toInput.val(start.format('YYYY-MM-DD'))
            } else {
                $input.val(formatRangeLabel(start, end))
                $fromInput.val(start.isValid() ? start.format('YYYY-MM-DD') : '')
                $toInput.val(end.isValid() ? end.format('YYYY-MM-DD') : '')
            }

            // Dispatch native change events for non-jQuery listeners (Preact, etc.)
            $fromInput[0]?.dispatchEvent(new Event('change', { bubbles: true }))
            $toInput[0]?.dispatchEvent(new Event('change', { bubbles: true }))

            if (onApply) {
                onApply({ start, end, label })
            }
        }
    )

    const picker = $input.data('daterangepicker')

    // Initialize with existing values
    const fromVal = $fromInput.val()
    if (fromVal) {
        const start = moment(fromVal)
        const end = $toInput.val() ? moment($toInput.val()) : start

        picker.setStartDate(start)
        picker.setEndDate(end)

        if (singleDate) {
            $input.val(start.format('ll'))
        } else {
            $input.val(formatRangeLabel(start, end))
        }
    }

    return {
        setStartDate: (date) => picker.setStartDate(date),
        setEndDate: (date) => picker.setEndDate(date),
        destroy: () => picker.remove(),
    }
}
