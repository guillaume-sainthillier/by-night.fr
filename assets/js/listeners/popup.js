export default (di, container) => {
    $('a.popup', container).click(function () {
        var width = 520,
            height = 350,
            leftPosition = window.screen.width / 2 - (width / 2 + 10),
            topPosition = window.screen.height / 2 - (height / 2 + 50),
            windowFeatures =
                'status=no,height=' +
                height +
                ',width=' +
                width +
                ',left=' +
                leftPosition +
                ',top=' +
                topPosition +
                ',screenX=' +
                leftPosition +
                ',screenY=' +
                topPosition +
                ',toolbar=0,status=0';

        window.open($(this).attr('href'), 'sharer', windowFeatures);
        return false;
    });
}