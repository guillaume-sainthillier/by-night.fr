export default (di, container) => {
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
};
