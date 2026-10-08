import debounce from 'lodash/debounce'

/** How far down the page the button shows, in pixels */
export const SHOW_AFTER = 200

/**
 * Show the scroll-to-top button once the page is scrolled down, hide it back near the top. Runs once at App.start().
 * The stylesheet hides it until `.is-visible`: shown from the start, it covered the bottom of the first screen.
 *
 * @type {Module}
 */
export default () => {
    const toTop = document.getElementById('toTop')

    if (!toTop) {
        return
    }

    toTop.addEventListener('click', (e) => {
        e.preventDefault()
        window.scrollTo({ top: 0, behavior: 'smooth' })
    })

    const update = () => toTop.classList.toggle('is-visible', window.scrollY > SHOW_AFTER)
    window.addEventListener('scroll', debounce(update, 100, { leading: true, maxWait: 200 }), { passive: true })
    // A page reloaded halfway down starts scrolled
    update()
}
