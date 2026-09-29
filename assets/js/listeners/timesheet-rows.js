import { leafLines } from '@/js/utils/dateLeaf'
import { findAll, findOne, on } from '@/js/utils/dom'
import { plural } from '@/js/utils/plural'

/**
 * Fills the calendar leaf of a date row from its date field.
 *
 * @param {HTMLElement} item - A .collection-item of the timesheets collection
 */
const fillLeaf = (item) => {
    const leaf = findOne('[data-timesheet-leaf]', item)
    const from = findOne('input[id$="_from"]', item)
    if (!leaf || !from) return

    leafLines(from.value).forEach((text, i) => {
        leaf.children[i].textContent = text
    })
}

/**
 * Keeps the date rows of the event form in step with their dates: the calendar leaf of each row, and the number of
 * dates in the header of the list.
 *
 * @param {HTMLElement} container - DOM container
 */
export default function initTimesheetRows(container) {
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
        if (e.target.matches('input[id$="_from"]')) {
            fillLeaf(e.target.closest('.collection-item'))
        }
    })
}
