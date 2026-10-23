import type { Page } from '@/js/types'
import { coalesce, followTransfers } from '@/js/utils/transfers'

/**
 * The import / export page and an import's report: their jobs' fragment (`[data-transfer-refresh]`) is read again
 * from `refreshUrl` when the member's channel says a job changed. Without `hub` (a job already done), nothing to
 * follow.
 */
const initialize: Page<{ hub?: string; refreshUrl?: string; iri?: string | null }> = ({
    app,
    container,
    hub,
    refreshUrl,
    iri = null,
}) => {
    const target = container.querySelector('[data-transfer-refresh]')
    if (!hub || !refreshUrl || !target) {
        return undefined
    }

    const refresh = coalesce(async () => {
        const response = await fetch(refreshUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        if (!response.ok) {
            return
        }

        const html = await response.text()
        app.unmount(target)
        target.innerHTML = html
        app.mount(target)
    })

    return followTransfers({ hub, iri, onChange: refresh })
}

window.App.registerPage('event_transfers', initialize)
