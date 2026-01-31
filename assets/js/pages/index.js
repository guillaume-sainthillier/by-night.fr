import $ from 'jquery'
import initDates from '@/js/lazy-listeners/dates'

$(document).ready(function () {
    initDates()

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

        window.App.get('autocompleteService').create({
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
