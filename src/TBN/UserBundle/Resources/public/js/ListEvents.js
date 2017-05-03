var ListEvents = {
    init: function () {
        $(function () {
            $('.brouillon').click(function () {
                var that = $(this);

                that.attr('disabled', true);
                $.post(that.data('href'), {
                    brouillon: !that.prop('checked')
                }).done(function () {
                    that.attr('disabled', false);
                });
            });

            $('.annuler').click(function () {
                var that = $(this);

                that.attr('disabled', true);
                $.post(that.data('href'), {
                    annuler: that.prop('checked')
                }).done(function () {
                    that.attr('disabled', false);
                });
            });

            $("#connect-fb").click(function () {
                App.popup($(this).attr('href'), $(this));
                return false;
            });

            $("body").on("hasConnected", function () {
                window.location = $("#connect-fb").data("href");
            });
        });
    }
};

ListEvents.init();
