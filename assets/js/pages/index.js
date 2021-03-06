import 'bootstrap-select/dist/css/bootstrap-select.css';
import 'daterangepicker/daterangepicker.css';

import 'typeahead.js/dist/bloodhound';
import 'typeahead.js/dist/typeahead.bundle';
import 'bootstrap-select/js/bootstrap-select.js';
import 'bootstrap-select/js/i18n/defaults-fr_FR.js';
import 'moment/locale/fr';
import 'daterangepicker';

$(document).ready(function () {
    $('.form-city-picker').each(function () {
        var form = $(this);
        var btn = form.find('.choose-city-action');
        var field = form.find('.city-picker');
        var cityValue = form.find('.city-value');

        function updateBtn() {
            btn.attr('disabled', cityValue.val().length === 0);
        }

        updateBtn();

        $(this).submit(function () {
            return !btn.attr('disabled');
        });

        // Saisie de la ville
        var cities = new Bloodhound({
            datumTokenizer: Bloodhound.tokenizers.obj.whitespace('value'),
            queryTokenizer: Bloodhound.tokenizers.whitespace,
            remote: {
                url: AppConfig.apiCityURL,
                wildcard: '%QUERY',
            },
        });
        cities.initialize();

        // Proxy inputs typeahead events to addressPicker
        field
            .typeahead(null, {
                name: 'cities',
                display: 'name',
                source: cities.ttAdapter(),
            })
            .on('typeahead:selected', function (e, data) {
                cityValue.val(data.slug || '');
                updateBtn();
                $(form).submit();
            })
            .on('keyup input', updateBtn);
    });
});
