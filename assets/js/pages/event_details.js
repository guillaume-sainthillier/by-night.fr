import $ from 'jquery'
import CommentApp from '@/js/components/CommentApp'
import Widgets from '@/js/components/Widgets'

/** @type {Page} */
function initialize() {
    new Widgets().init()

    new CommentApp().init()

    // Google Maps loads on demand: the frame replaces the placeholder of the map card
    $('#loadMap')
        .off('click')
        .on('click', function () {
            $('<iframe>')
                .attr({
                    src: $(this).data('map'),
                    title: 'Plan',
                    loading: 'lazy',
                    referrerpolicy: 'no-referrer-when-downgrade',
                    allowfullscreen: true,
                })
                .css('border', 0)
                .appendTo($('#googleMap').empty())
        })

    $('[data-copy-url]')
        .off('click')
        .on('click', function () {
            const toastManager = window.App.get('toastManager')
            navigator.clipboard.writeText($(this).data('copy-url')).then(
                () => toastManager.createToast('success', 'Lien copié !'),
                () => toastManager.createToast('error', 'Impossible de copier le lien')
            )
        })
}

window.App.registerPage('event_details', initialize)
