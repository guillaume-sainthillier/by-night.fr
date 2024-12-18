import $ from 'jquery'
import Widgets from '@/js/components/Widgets'
import CommentApp from '@/js/components/CommentApp'

$(document).ready(function () {
    new Widgets().init()

    new CommentApp().init()

    const gMap = $('#googleMap').attr('data-bs-toggled', '0')
    $('#loadMap')
        .off('click')
        .click(function (e) {
            e.preventDefault()
            if (!gMap.find('iframe').length) {
                $('<iframe>')
                    .attr({
                        class: 'component',
                        width: gMap.width(),
                        height: 450,
                        frameborder: 0,
                        src: $(this).data('map'),
                        allowfullscreen: true,
                    })
                    .css({ width: '100%', border: '0' })
                    .appendTo(gMap)
            }

            if (gMap.attr('data-bs-toggled') === '1') {
                // Masquer
                gMap.attr('data-bs-toggled', '0').hide('fast')
            } // Afficher
            else {
                gMap.attr('data-bs-toggled', '1').show('fast')
            }
        })
})
