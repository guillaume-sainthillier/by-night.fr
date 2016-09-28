var Profile = {
    init: function () {
        $(function () {
            $("#shorcuts li a").click(function() {
                console.log('OK');
                return true;
            })
            $("#btnDelete").click(function() {
               $("#modalDelete").modal();
            });
        });
    }
};

Profile.init();