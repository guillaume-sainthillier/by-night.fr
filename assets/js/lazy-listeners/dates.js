// JS
import $ from 'jquery'
import 'moment/locale/fr'
import 'daterangepicker'
import moment from 'moment'
import { isTouchDevice } from '@/js/utils/utils'

// CSS
import '@/scss/lazy-components/_datepicker.scss'

export default function init(container = document) {
    $('input.shorcuts_date', container).each(function () {
        const $input = $(this)
        $input.removeAttr('name')

        const $fromInput = $(`#${$input.data('from')}`)
        const $toInput = $(`#${$input.data('to')}`)
        const isSingleDate = $input.data('single-date') === true || $input.data('single-date') === 'true'
        const ranges = parseRanges($input.data('ranges'))

        if (isTouchDevice()) {
            $input.attr('readonly', true).addClass('form-control-readonly')
        }

        $input.daterangepicker(
            {
                singleDatePicker: isSingleDate,
                autoApply: isSingleDate,
                autoUpdateInput: false,
                ranges: isSingleDate ? undefined : ranges,
                alwaysShowCalendars: !isSingleDate && Object.keys(ranges).length === 0,
                locale: {
                    applyLabel: 'OK',
                    cancelLabel: 'Annuler',
                    fromLabel: 'Du',
                    toLabel: 'Au',
                    customRangeLabel: 'Personnalisé',
                },
            },
            function (start, end, label) {
                if (isSingleDate) {
                    $input.val(start.format('ll'))
                    $fromInput.val(start.format('YYYY-MM-DD'))
                    $toInput.val(start.format('YYYY-MM-DD'))
                } else {
                    $input.val(formatRangeLabel(start, end, label, ranges))
                    $fromInput.val(start.isValid() ? start.format('YYYY-MM-DD') : '')
                    $toInput.val(end.isValid() ? end.format('YYYY-MM-DD') : '')
                }

                // Dispatch native change events for non-jQuery listeners (Preact, etc.)
                $fromInput[0]?.dispatchEvent(new Event('change', { bubbles: true }))
                $toInput[0]?.dispatchEvent(new Event('change', { bubbles: true }))
            }
        )

        // Initialize with existing values
        const fromVal = $fromInput.val()
        if (fromVal) {
            const picker = $input.data('daterangepicker')
            const start = moment(fromVal)
            const end = $toInput.val() ? moment($toInput.val()) : start

            picker.setStartDate(start)
            picker.setEndDate(end)

            if (isSingleDate) {
                $input.val(start.format('ll'))
            } else {
                $input.val(formatRangeLabel(start, end, null, ranges))
            }
        }
    })
}

function parseRanges(rangesData) {
    const ranges = {}
    if (rangesData) {
        $.each(rangesData, (label, values) => {
            ranges[label] = [moment(values[0]), values[1] === null ? null : moment(values[1])]
        })
    }
    return ranges
}

function formatRangeLabel(start, end, label, ranges) {
    // Check if it's a predefined range
    if (label && ranges[label]) {
        return label
    }

    // Custom format
    if (!end || !end.isValid()) {
        return `À partir du ${start.format('ll')}`
    }
    if (start.format('YYYY-MM-DD') === end.format('YYYY-MM-DD')) {
        return `Le ${start.format('ll')}`
    }
    return `Du ${start.format('ll')} au ${end.format('ll')}`
}
