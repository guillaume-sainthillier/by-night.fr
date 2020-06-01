import '../../scss/pages/espace_perso_event.scss';

import 'typeahead.js/dist/bloodhound';
import 'typeahead.js/dist/typeahead.bundle';
import 'typeahead-addresspicker/dist/typeahead-addresspicker';
import 'moment/locale/fr';
import 'daterangepicker';
import 'bootstrap-select/dist/js/bootstrap-select.min.js';
import 'bootstrap-select/js/i18n/defaults-fr_FR.js';

import UserEventHandler from '../components/UserEventHandler';

window.onPageLoaded = function () {
    new UserEventHandler().init();
};
