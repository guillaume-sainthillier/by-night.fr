import 'select2';
import 'select2/src/scss/core.scss';
import 'select2-bootstrap-5-theme/src/select2-bootstrap-5-theme.scss';

export default (di, container) => {
    $('select', container).each(function () {
        $(this).select2({
            theme: 'bootstrap-5',
        });
    });
};
