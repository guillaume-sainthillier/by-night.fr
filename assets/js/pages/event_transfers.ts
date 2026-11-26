import { definePage } from '@silarhi/app-js'
import { getHtml } from '@silarhi/app-js/http'
import { coalesce, followTransfers } from '@/js/utils/transfers'

/**
 * The import / export page and an import's report: their jobs' fragment (`[data-transfer-refresh]`) is read again
 * from `refreshUrl` when the member's channel says a job changed. Without `hub` (a job already done), nothing to
 * follow.
 */
export default definePage<{ hub?: string; refreshUrl?: string; iri?: string | null }>(
    ({ app, container, hub, refreshUrl, iri = null }) => {
        const target = container.querySelector('[data-transfer-refresh]')
        if (!hub || !refreshUrl || !target) {
            return undefined
        }

        const refresh = coalesce(async () => {
            let html: string
            try {
                html = await getHtml(refreshUrl)
            } catch {
                return
            }

            app.unmount(target)
            target.innerHTML = html
            app.mount(target)
        })

        return followTransfers({ hub, iri, onChange: refresh })
    }
)
