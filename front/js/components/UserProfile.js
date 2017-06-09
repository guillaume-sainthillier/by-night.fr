var UserProfile;
UserProfile = {
    settings: {
        offsetTop: 75,
        scrollSpeed: 400,
        easingType: 'linear'
    },
    init: function () {
        $(function () {
            $("#shorcuts li a").click(function () {
                var elem = $($(this).attr('href'));
                $.scrollTo(elem.offset().top - UserProfile.settings.offsetTop, UserProfile.settings.scrollSpeed, {easing: UserProfile.settings.easingType});
                return false;
            });
            $("#btnDelete").click(function () {
                $("#modalDelete").modal();
            });
        });
    }
};

UserProfile.init();