import 'fancybox/dist/js/jquery.fancybox';
import 'fancybox/dist/css/jquery.fancybox.css';

import '../../scss/components/_image-previews.scss';

export default function init(container = document) {
    $('.image-gallery', container).each(function () {
        $(this)
            .fancybox({
                helpers: {
                    title: {
                        type: 'inside',
                        position: 'top',
                    },
                    overlay: {
                        locked: false,
                    },
                },
            })
            .click(function () {
                return false;
            });
    });
}
