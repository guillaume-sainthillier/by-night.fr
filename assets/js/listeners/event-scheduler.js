import { render, h } from 'preact'
import EventScheduler from '@/js/components/EventScheduler'
import { findOne } from '@/js/utils/dom'

/**
 * Initialize Event Scheduler component
 *
 * @param {HTMLElement} container - DOM container
 * @param {App} di - Dependency injection container
 */
export default function initEventScheduler(container, di) {
    const schedulerRoot = findOne('#event-scheduler-root', container)
    if (!schedulerRoot) return

    const collectionManager = di.get('collectionManager')
    const startDateField = schedulerRoot.dataset.startDateField
    const endDateField = schedulerRoot.dataset.endDateField
    const collectionId = schedulerRoot.dataset.collectionId
    const detectExisting = schedulerRoot.dataset.detectExisting === 'true'

    schedulerRoot.innerHTML = ''
    render(
        h(EventScheduler, {
            startDateFieldId: startDateField,
            endDateFieldId: endDateField,
            timesheetsCollectionId: collectionId,
            collectionManager,
            detectExisting,
        }),
        schedulerRoot
    )
}
