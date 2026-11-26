import type { App } from '@silarhi/app-js'
import { findOne, on } from '@silarhi/app-js'
import type { FormView } from '@silarhi/app-js/form'
import type { EventForm } from '@/js/forms/EventForm'
import { leafLines } from '@/js/utils/dateLeaf'
import { plural } from '@/js/utils/plural'

/**
 * Keeps the date rows of the event form in step with their dates: the calendar leaf of each row, and the number of
 * dates in the header of the list.
 */
export default function initTimesheetRows(form: FormView<EventForm>, container: ParentNode, app: App) {
    const timesheets = form.get('timesheets')
    const collection = timesheets.element
    if (!collection) return

    const count = findOne('[data-timesheets-count]', container)
    const updateCount = () => {
        if (count) {
            count.textContent = plural(timesheets.children.length, { one: '# date', other: '# dates' })
        }
    }

    // Each row's leaf follows its date, which its date picker sets on the hidden "from" field
    app.get('collectionManager').each(timesheets, ({ item, form: row }) => {
        const from = row.field('dateRange.from')
        const fillLeaf = () => {
            const leaf = findOne('[data-timesheet-leaf]', item)
            if (!leaf) return

            leafLines(from.value).forEach((text, i) => {
                leaf.children[i].textContent = text
            })
        }

        fillLeaf()
        from.onChange(fillLeaf)
        updateCount()
    })
    on(collection, 'collection.removed', updateCount)
}
