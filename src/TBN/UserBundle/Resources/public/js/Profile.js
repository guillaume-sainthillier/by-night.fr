var Profile;
Profile = {
    settings: {
        offsetTop: 75,
        scrollSpeed: 400,
        easingType: 'linear'
    },
    init: function () {
        $(function () {
            $("#shorcuts li a").click(function () {
                var elem = $($(this).attr('href'));
                $.scrollTo(elem.offset().top - Profile.settings.offsetTop, Profile.settings.scrollSpeed, {easing: Profile.settings.easingType});
                return false;
            });
            $("#btnDelete").click(function () {
                $("#modalDelete").modal();
            });
        });
    }
};

Profile.init();