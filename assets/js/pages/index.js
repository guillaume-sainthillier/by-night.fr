import $ from 'jquery'
import initDates from '@/js/lazy-listeners/dates'
import initTypeAHead from '@/js/lazy-listeners/typeahead'

$(document).ready(function () {
    initDates()
    initTypeAHead()

    $('.form-city-picker').each(function () {
        const form = $(this)
        const btn = form.find('.choose-city-action')
        const field = form.find('.city-picker')
        const cityValue = form.find('.city-value')

        function updateBtn() {
            btn.attr('disabled', cityValue.val().length === 0)
        }

        updateBtn()

        $(this).submit(function () {
            return !btn.attr('disabled')
        })

        // Saisie de la ville
        const cities = new window.Bloodhound({
            datumTokenizer: window.Bloodhound.tokenizers.obj.whitespace('value'),
            queryTokenizer: window.Bloodhound.tokenizers.whitespace,
            remote: {
                url: window.AppConfig.apiCityURL,
                wildcard: '%QUERY',
            },
        })
        cities.initialize()

        // Proxy inputs typeahead events to addressPicker
        field
            .typeahead(null, {
                name: 'cities',
                display: 'name',
                source: cities.ttAdapter(),
            })
            .on('typeahead:selected', function (e, data) {
                cityValue.val(data.slug || '')
                updateBtn()
                $(form).submit()
            })
            .on('keyup input', updateBtn)
    })
})
