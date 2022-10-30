export default (di, container) => {
    const options = {
        css_selecteur_like: '.btn-like-event',
        css_active_class: 'btn-primary',
    };

    $(options.css_selecteur_like, container).click(function () {
        const btn = $(this);

        if (btn.hasClass('connexion')) {
            return false;
        }

        btn.attr('disabled', true);
        $.post(btn.data('href'), { like: !btn.hasClass(options.css_active_class) }).done(function (msg) {
            btn.attr('disabled', !msg.success);
            if (msg.success) {
                btn.toggleClass(options.css_active_class, msg.like);
            }
        });
    });
};
