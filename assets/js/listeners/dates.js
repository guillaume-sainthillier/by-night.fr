export default (di, container) => {
    $('input.shorcuts_date', container).each(function () {
        $(this).removeAttr('name');
        var input = this;
        var fromInput = $('#' + $(this).data('from'));
        var toInput = $('#' + $(this).data('to'));

        var moment = require('moment');

        var ranges = {};
        $.each($(input).data('ranges'), function (label, values) {
            ranges[label] = [moment(values[0]), values[1] === null ? null : moment(values[1])];
        });

        $(input).daterangepicker(
            {
                startDate: fromInput.val() ? moment(fromInput.val()) : moment(),
                endDate: toInput.val() ? moment(toInput.val()) : null,
                autoUpdateInput: false,
                ranges: ranges,
                alwaysShowCalendars: Object.keys(ranges).length === 0,
                //showCustomRangeLabel: Object.keys(ranges).length > 0,
                locale: {
                    applyLabel: 'OK',
                    cancelLabel: 'Annuler',
                    fromLabel: 'Du',
                    toLabel: 'Au',
                    customRangeLabel: 'Personnalisé',
                },
            },
            cb
        );

        function cb(start, end, label) {
            var datas = $(input).data('daterangepicker');
            if (typeof datas.ranges[label] !== 'undefined') {
                $(input).val(label);
            } else {
                if (!end.isValid()) {
                    $(input).val('À partir du ' + start.format('ll'));
                } else if (start.format('YYYY-MM-DD') === end.format('YYYY-MM-DD')) {
                    $(input).val('Le ' + start.format('ll'));
                } else {
                    $(input).val('Du ' + start.format('ll') + ' au ' + end.format('ll'));
                }
            }

            fromInput.val(start.isValid() ? start.format('YYYY-MM-DD') : '');
            toInput.val(end.isValid() ? end.format('YYYY-MM-DD') : '');
        }
    });
}