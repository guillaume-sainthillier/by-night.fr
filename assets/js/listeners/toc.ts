import type { Listener } from '@/js/types'

// A section counts as current once its top has gone above this line: below the sticky header, and far enough down
// the screen that the section filling it is the one marked rather than the end of the previous one
const currentLine = () => Math.max(120, window.innerHeight * 0.3)

/**
 * Marks the entry of "Sur cette page" ([data-toc] links, components/Legal/Toc) whose section is being read, with
 * aria-current: the last section whose top has gone above a line near the top of the screen. A sidebar too long for
 * the screen scrolls on its own to keep that entry in view.
 */
export default {
    selector: '[data-toc]',
    connect(toc) {
        const links = [...toc.querySelectorAll<HTMLAnchorElement>('a[href^="#"]')]
        const sections = links.map((link) => document.getElementById(decodeURIComponent(link.hash.slice(1))))
        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)')
        let previous: number | null = null

        const update = () => {
            const line = currentLine()
            let current = 0
            sections.forEach((section, index) => {
                if (section && section.getBoundingClientRect().top <= line) {
                    current = index
                }
            })
            if (current === previous) {
                return
            }

            links.forEach((link, index) => {
                if (index === current) {
                    link.setAttribute('aria-current', 'true')
                } else {
                    link.removeAttribute('aria-current')
                }
            })

            // Only when the entry changes, so the page's scroll events don't restart the animation; instant on load
            const link = links[current]
            if (
                link.offsetTop < toc.scrollTop ||
                link.offsetTop + link.offsetHeight > toc.scrollTop + toc.clientHeight
            ) {
                toc.scrollTo({
                    top: link.offsetTop - toc.clientHeight / 2,
                    behavior: previous === null || reducedMotion.matches ? 'instant' : 'smooth',
                })
            }
            previous = current
        }

        document.addEventListener('scroll', update, { passive: true })
        update()

        return () => document.removeEventListener('scroll', update)
    },
} satisfies Listener
