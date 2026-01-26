import $ from 'jquery'
import initDates from '@/js/lazy-listeners/dates'
import '@/scss/lazy-components/_selects.scss'
import TomSelect from 'tom-select'

$(document).ready(function () {
    initDates()

    $('.form-city-picker').each(function () {
        const form = $(this)
        const btn = form.find('.choose-city-action')
        const field = form.find('.city-picker')[0]
        const cityValue = form.find('.city-value')
        const apiUrl = window.AppConfig.apiCityURL

        function updateBtn() {
            btn.attr('disabled', cityValue.val().length === 0)
        }

        updateBtn()

        form.submit(function () {
            return !btn.attr('disabled')
        })

        console.log('o')
        const ts = new TomSelect(field, {
            valueField: 'slug',
            labelField: 'name',
            searchField: 'name',
            plugins: ['remove_button', 'restore_on_backspace'],
            maxItems: 1,
            create: false,
            loadThrottle: null,
            closeAfterSelect: true,
            load(query, callback) {
                const url = apiUrl.replace('%QUERY', encodeURIComponent(query))
                fetch(url, { headers: { Accept: 'application/ld+json' } })
                    .then((res) => res.json())
                    .then((data) => callback(data['hydra:member'] || data.member || data))
                    .catch(() => callback())
            },
            render: {
                no_results() {
                    return '<div class="no-results">Aucun r\u00e9sultat</div>'
                },
            },
            onChange(value) {
                cityValue.val(value || '')
                updateBtn()
                if (value) form.submit()
            },
        })

        // Search as you type
        $(ts.control_input).on('keydown', function(e) {
            if(
                e.key !== 'Enter'
                && e.key !== 'Tab'
                && e.key !== 'ArrowDown'
                && e.key !== 'ArrowUp'
                && e.key !== 'Escape'
                && e.key !== 'Shift'
                && e.key !== 'Control'
                && e.key !== 'Alt'
                && this.value.length > 0
            ) {
                ts.load(this.value)
            }
        })
    })
})
