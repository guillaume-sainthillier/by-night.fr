/**
 * A link whose href is the SEO page crawlers follow ("Concerts à Toulouse"), and whose data-filtered-href is the page a
 * visitor gets instead, keeping the filters of the page they are on ("Concerts" on a venue page stays on the venue).
 * The href is swapped as the visitor reaches for the link, before the browser reads it: pointerdown comes before a
 * click, a middle click or a context menu ("Open in a new tab"), focus before the Enter key.
 *
 * @type {Listener}
 */
export default {
    selector: 'a[data-filtered-href]',
    connect(element) {
        const swap = () => element.setAttribute('href', element.dataset.filteredHref)

        element.addEventListener('pointerdown', swap)
        element.addEventListener('focus', swap)

        return () => {
            element.removeEventListener('pointerdown', swap)
            element.removeEventListener('focus', swap)
        }
    },
}
