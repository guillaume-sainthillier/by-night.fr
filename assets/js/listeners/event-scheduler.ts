import { h, render } from 'preact'
import type { App } from '@/js/app'
import EventScheduler from '@/js/components/EventScheduler'
import { findOne } from '@/js/utils/dom'

/**
 * Initialize Event Scheduler component
 *
 * @param container - DOM container
 * @param di - Dependency injection container
 */
export default function initEventScheduler(container: ParentNode, di: App) {
    const schedulerRoot = findOne('#event-scheduler-root', container)
    if (!schedulerRoot) return

    const collectionManager = di.get('collectionManager')
    const startDateField = schedulerRoot.dataset.startDateField
    const endDateField = schedulerRoot.dataset.endDateField
    const collectionId = schedulerRoot.dataset.collectionId
    const generateButtonId = schedulerRoot.dataset.generateButton
    const detectExisting = schedulerRoot.dataset.detectExisting === 'true'
    // All set by templates/personal-space/new.html.twig
    if (!startDateField || !endDateField || !collectionId || !generateButtonId) return

    schedulerRoot.innerHTML = ''
    render(
        h(EventScheduler, {
            startDateFieldId: startDateField,
            endDateFieldId: endDateField,
            timesheetsCollectionId: collectionId,
            generateButtonId,
            collectionManager,
            detectExisting,
        }),
        schedulerRoot
    )
}
