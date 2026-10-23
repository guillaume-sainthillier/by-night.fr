import type { Page } from '@silarhi/app-js'
import $ from 'jquery'
import CommentApp from '@/js/components/CommentApp'
import Widgets from '@/js/components/Widgets'

const initialize: Page = () => {
    new Widgets().init()

    new CommentApp().init()

    // Google Maps loads on demand: the frame replaces the placeholder of the map card
    $('#loadMap')
        .off('click')
        .on('click', (event) => {
            $('<iframe>')
                .attr({
                    src: $(event.currentTarget).data('map'),
                    title: 'Plan',
                    loading: 'lazy',
                    referrerpolicy: 'no-referrer-when-downgrade',
                    allowfullscreen: true,
                })
                .css('border', 0)
                .appendTo($('#googleMap').empty())
        })
}

window.App.registerPage('event_details', initialize)
