import 'font-awesome/scss/font-awesome.scss';
import '../scss/app.scss';

import './vendors';
import './overrides';
import './utils/css';
import './utils/dom';

import * as Sentry from '@sentry/browser';
import Container from './services/Container';
import registerServices from './services';

//Global listeners
import breadcrumb from './page-listeners/breadcrumb';
import headerSearch from './page-listeners/header-search';
import navbarScroll from './page-listeners/navbar-scroll';
import scrollToTop from './page-listeners/scroll-to-top';

//Listeners
import dates from './listeners/dates';
import formCollection from './listeners/form-collection';
import formErrors from './listeners/form-errors';
import imageGallery from './listeners/image-gallery';
import like from './listeners/like';
import loadMore from './listeners/load-more';
import login from './listeners/login';
import popup from './listeners/popup';
import register from './listeners/register';
import selects from './listeners/selects';
import tooltip from './listeners/tooltip';

class App {
    constructor() {
        this._di = null;
        this._listeners = [
            breadcrumb,
            headerSearch,
            //navbarScroll,
            scrollToTop,
        ];

        this._pageListeners = [
            dates,
            formCollection,
            formErrors,
            imageGallery,
            like,
            loadMore,
            login,
            popup,
            register,
            selects,
            tooltip,
        ];
    }

    handleError(error) {
        Sentry.captureException(error);
        throw error;
    }

    run(parameters) {
        this._di = new Container(parameters);

        if (parameters.dsn) {
            Sentry.init({
                dsn: parameters.dsn,
                release: parameters.release,
                environment: parameters.environment,
                autoSessionTracking: false,
            });

            Sentry.configureScope((scope) => {
                scope.setUser(parameters.user);
            });
        }

        registerServices(this._di);

        // Execute the page load listeners
        this._listeners.forEach((listener) => {
            listener(this._di);
        });

        this.dispatchPageLoadedEvent();
    }

    dispatchPageLoadedEvent(container) {
        this._pageListeners.forEach((listener) => {
            listener(this._di, container || document);
        });

        if (typeof window.onPageLoaded === 'function') {
            window.onPageLoaded(this, container || document);
            window.onPageLoaded = null;
        }
    }

    get(key) {
        return this._di.get(key);
    }

    loadingButtons(container) {
        $('.btn-submit', container)
            .attr('disabled', true)
            .prepend('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>');
    }

    resetButtons(container) {
        $('.btn-submit', container).attr('disabled', false).find('.spinner-border').remove();
    }
}

window.App = new App();
