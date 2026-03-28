import $ from 'jquery'
import { debounce } from 'lodash'

export default () => {
    const navbar = $('.navbar')
    const toggler = navbar.find('.navbar-toggler')
    const href = $(toggler).data('target')
    const elem = $(href)

    $(window).scroll(
        debounce(
            function () {
                if (!toggler.hasClass('collapsed')) {
                    $(elem).collapse('hide')
                }
            },
            200,
            { leading: true }
        )
    )
}
