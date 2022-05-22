import 'select2';
import 'select2/src/scss/core.scss';
import 'select2-bootstrap-5-theme/src/select2-bootstrap-5-theme.scss';
import $ from 'jquery';

export default (container = document) => {
    $('select.form-select:not(.hidden)', container).select2({
        theme: 'bootstrap-5',
        minimumResultsForSearch: 10,
        width: $(this).data('width') ? $(this).data('width') : $(this).hasClass('w-100') ? '100%' : 'style',
    });
};
