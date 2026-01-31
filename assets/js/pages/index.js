import $ from 'jquery'
import { create as createDatepicker } from '@/js/services/ui/DatepickerService'
import { create as createAutocomplete } from '@/js/services/ui/AutocompleteService'

$(document).ready(function () {
    document.querySelectorAll('input.shorcuts_date').forEach((el) => {
        createDatepicker({
            element: el,
            fromInput: document.getElementById(el.dataset.from),
            toInput: document.getElementById(el.dataset.to),
            singleDate: el.dataset.singleDate === 'true',
            ranges: el.dataset.ranges ? JSON.parse(el.dataset.ranges) : {},
        })
    })

    $('.form-city-picker').each(function () {
        const form = $(this)
        const btn = form.find('.choose-city-action')
        const field = form.find('.city-picker')[0]
        const cityValue = form.find('.city-value')[0]

        function updateBtn() {
            btn.attr('disabled', cityValue.value.length === 0)
        }

        updateBtn()

        form.submit(function () {
            return !btn.attr('disabled')
        })

        createAutocomplete({
            element: field,
            url: window.AppConfig.apiCityURL,
            valueInput: cityValue,
            throttle: 0,
            onSelection: () => {
                updateBtn()
                form.submit()
            },
            onInput: () => {
                updateBtn()
            },
        })
    })
})
