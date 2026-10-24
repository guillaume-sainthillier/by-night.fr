import { defineListener } from '@silarhi/app-js'

/**
 * A row of chips scrolled sideways on a phone (`.chips-scroll-mobile`, the agenda's dates) opens on its active chip:
 * "Ce mois", the last of the row, would be out of sight on the page it leads to.
 */
export default defineListener<HTMLElement>({
    selector: '.chips-scroll-mobile',
    connect(row) {
        const active = row.querySelector<HTMLElement>('.active')
        if (!active || row.scrollWidth <= row.clientWidth) {
            return
        }

        // The chip before it stays in sight, showing where the row comes from
        row.scrollLeft = Math.max(0, active.offsetLeft - row.offsetLeft - active.offsetWidth / 2)
    },
})
