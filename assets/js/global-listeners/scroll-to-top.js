import { debounce } from 'lodash'

export default () => {
    const settings = {
        min: 200,
        inDelay: 300,
        outDelay: 200,
        containerID: 'toTop',
        scrollSpeed: 400,
        easingType: 'linear',
    }

    const toTop = $(`#${settings.containerID}`)

    if (!toTop.length) {
        return
    }

    toTop.click(function (e) {
        e.preventDefault()
        $('html, body').animate({ scrollTop: 0 }, settings.scrollSpeed, settings.easingType)
    })

    let toTopHidden = true
    $(window).scroll(
        debounce(
            function () {
                const sd = $(this).scrollTop()
                if (sd > settings.min && toTopHidden) {
                    toTop.fadeIn(settings.inDelay)
                    toTopHidden = false
                } else if (sd <= settings.min && !toTopHidden) {
                    toTop.fadeOut(settings.outDelay)
                    toTopHidden = true
                }
            },
            200,
            { leading: true }
        )
    )
}
