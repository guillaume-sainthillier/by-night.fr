import {debounce} from 'lodash';

export default () => {
    const navbar = $('.navbar');
    const toggler = navbar.find('.navbar-toggler');
    const href = $(toggler).data('target');
    const elem = $(href);

    $(window).scroll(debounce(function () {
        if (!toggler.hasClass('collapsed')) {
            $(elem).collapse('hide');
        }

        if ($(window).scrollTop() > 0) {
            $(navbar).addClass('navbar-shadow');
        } else {
            $(navbar).removeClass('navbar-shadow');
        }
    }, 200, {leading: true}));
}