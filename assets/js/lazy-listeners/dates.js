// JS
import 'moment/locale/fr'
import 'daterangepicker'
import moment from 'moment'
import { isTouchDevice } from '../utils/utils'

// CSS
import '../../scss/lazy-components/_datepicker.scss'

export default function init(container = document) {
    $('input.shorcuts_date', container).each(function () {
        $(this).removeAttr('name')
        const input = this
        const fromInput = $(`#${$(this).data('from')}`)
        const toInput = $(`#${$(this).data('to')}`)
        const ranges = {}
        $.each($(input).data('ranges'), function (label, values) {
            ranges[label] = [moment(values[0]), values[1] === null ? null : moment(values[1])]
        })

        if (isTouchDevice()) {
            $(input).attr('readonly', true).addClass('form-control-readonly')
        }

        $(input).daterangepicker(
            {
                startDate: fromInput.val() ? moment(fromInput.val()) : moment(),
                endDate: toInput.val() ? moment(toInput.val()) : null,
                autoUpdateInput: false,
                ranges,
                alwaysShowCalendars: Object.keys(ranges).length === 0,
                locale: {
                    applyLabel: 'OK',
                    cancelLabel: 'Annuler',
                    fromLabel: 'Du',
                    toLabel: 'Au',
                    customRangeLabel: 'Personnalisé',
                },
            },
            callback
        )

        function callback(start, end, label) {
            const datas = $(input).data('daterangepicker')
            if (typeof datas.ranges[label] !== 'undefined') {
                $(input).val(label)
            } else if (!end.isValid()) {
                $(input).val(`À partir du ${start.format('ll')}`)
            } else if (start.format('YYYY-MM-DD') === end.format('YYYY-MM-DD')) {
                $(input).val(`Le ${start.format('ll')}`)
            } else {
                $(input).val(`Du ${start.format('ll')} au ${end.format('ll')}`)
            }

            fromInput.val(start.isValid() ? start.format('YYYY-MM-DD') : '')
            toInput.val(end.isValid() ? end.format('YYYY-MM-DD') : '')
        }
    })
}
