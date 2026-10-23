import { leafLines } from '@/js/utils/dateLeaf'
import { findAll, findOne, on } from '@/js/utils/dom'
import { plural } from '@/js/utils/plural'

/**
 * Fills the calendar leaf of a date row from its date field.
 *
 * @param item - A .collection-item of the timesheets collection
 */
const fillLeaf = (item: HTMLElement) => {
    const leaf = findOne('[data-timesheet-leaf]', item)
    const from = findOne<HTMLInputElement>('input[id$="_from"]', item)
    if (!leaf || !from) return

    leafLines(from.value).forEach((text, i) => {
        leaf.children[i].textContent = text
    })
}

/**
 * Keeps the date rows of the event form in step with their dates: the calendar leaf of each row, and the number of
 * dates in the header of the list.
 *
 * @param container - DOM container
 */
export default function initTimesheetRows(container: HTMLElement) {
    const collection = findOne('#app_event_timesheets', container)
    if (!collection) return

    const count = findOne('[data-timesheets-count]', container)
    const updateCount = () => {
        if (count) {
            count.textContent = plural(findAll('.collection-item', collection).length, {
                one: '# date',
                other: '# dates',
            })
        }
    }

    on(collection, 'collection.added', (e) => {
        fillLeaf(e.detail.item)
        updateCount()
    })
    on(collection, 'collection.deleted', updateCount)

    // The date picker of a row dispatches "change" on its hidden date field
    on(collection, 'change', (e) => {
        if (e.target instanceof Element && e.target.matches('input[id$="_from"]')) {
            const item = e.target.closest<HTMLElement>('.collection-item')
            if (item) fillLeaf(item)
        }
    })
}
