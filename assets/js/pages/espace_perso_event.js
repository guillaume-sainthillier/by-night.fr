import '../app';

import 'bootstrap-datepicker/dist/css/bootstrap-datepicker3.css';
import 'bootstrap-select/dist/css/bootstrap-select.css';

import 'typeahead.js/dist/bloodhound';
import 'typeahead.js/dist/typeahead.bundle';
import 'typeahead-addresspicker/dist/typeahead-addresspicker';
import 'bootstrap-datepicker/dist/js/bootstrap-datepicker.js';
import 'bootstrap-datepicker/dist/locales/bootstrap-datepicker.fr.min.js';
import 'bootstrap-select/dist/js/bootstrap-select.min.js';
import 'bootstrap-select/js/i18n/defaults-fr_FR.js';

import UserEventHandler from '../components/UserEventHandler';
new UserEventHandler().init();
