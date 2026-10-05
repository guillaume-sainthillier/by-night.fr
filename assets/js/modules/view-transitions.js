/**
 * Names the card picture the cross-document view transition morphs into the event's poster, and back
 * (scss/components/_view-transitions.scss). A name must be unique on a page and a listing shows dozens of cards, so
 * only the picture of the navigation is named, for the transition's duration. Runs once at App.start().
 *
 * The poster names itself in CSS: `pagereveal` fires on the event page's first render, before App.start(). Here, it
 * only fires on a page restored from the back/forward cache, whose listeners are still there: back to a listing.
 *
 * @type {Module}
 */
export default () => {
    /** @type {HTMLElement[]} */
    let styled = []

    const style = (element, name) => {
        element.style.viewTransitionName = name
        styled.push(element)
    }

    const clear = () => {
        styled.forEach((element) => element.style.removeProperty('view-transition-name'))
        styled = []
    }

    // The picture of a card of this page leading to url
    const card = (url) => {
        const { pathname } = new URL(url)
        const link = [...document.querySelectorAll('.card-event h3 a[href]')].find((a) => a.pathname === pathname)

        return link?.closest('.card-event').querySelector('.image-container')
    }

    // Clears the names of a previous transition first: a page restored from the back/forward cache keeps those its
    // `pageswap` set
    const name = (transition, picture) => {
        clear()
        if (!picture) {
            return
        }
        // Leaving an event page for a similar event: its poster gives up its name to the card, or the page would
        // hold two and the browser would drop the transition
        const poster = document.getElementById('event-poster')
        if (poster) {
            style(poster, 'none')
        }
        style(picture, 'event-picture')
        // Chrome rejects `finished` when the destination does not opt in (a page without app.css, like robots.txt or
        // the back office): handled here, or the page gets an "Uncaught (in promise)" once restored from the cache
        transition.finished.then(clear, clear)
    }

    // Leaving: the card leading to the destination, if any; an event page's poster keeps its own name otherwise.
    // `activation` needs the Navigation API: without it, no card
    window.addEventListener('pageswap', (e) => {
        if (e.viewTransition && e.activation?.entry) {
            name(e.viewTransition, card(e.activation.entry.url))
        }
    })

    // Arriving back on a listing: the card leading to the event left. An event page is left alone, its poster is
    // already named
    window.addEventListener('pagereveal', (e) => {
        const from = window.navigation?.activation?.from
        if (e.viewTransition && from?.url && !document.getElementById('event-poster')) {
            name(e.viewTransition, card(from.url))
        }
    })
}
