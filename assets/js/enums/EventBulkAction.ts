export const PUBLISH_NAME = 'Publish'
export const DRAFT_NAME = 'Draft'
export const DELETE_NAME = 'Delete'

const EventBulkAction = Object.freeze({
    Publish: 'publish',
    Draft: 'draft',
    Delete: 'delete',
})
export default EventBulkAction

export type EventBulkActionValue = (typeof EventBulkAction)[keyof typeof EventBulkAction]
