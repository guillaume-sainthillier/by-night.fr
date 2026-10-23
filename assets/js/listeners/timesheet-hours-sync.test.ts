/**
 * Tests for the sync of the date rows with the event's default hours
 * Run: pnpm test
 */

import { beforeEach, describe, expect, test, vi } from 'vitest'
import initTimesheetHoursSync from '@/js/listeners/timesheet-hours-sync'
import { fake } from '@/js/test/fake'

// The Node environment has no DOM: the fields are plain objects the dom helpers look up by id or id suffix
const { page } = vi.hoisted(() => {
    interface Field {
        id: string
        value: string
        placeholder: string
        listeners: Record<string, ((event: { target: Field }) => void)[]>
    }
    const page: { fields: Record<string, Field>; rows: Field[] } = { fields: {}, rows: [] }
    return { page }
})

type Field = (typeof page.rows)[number]

vi.mock('@silarhi/app-js', () => ({
    findOne: (selector: string) => page.fields[selector] ?? null,
    findAll: (selector: string) => {
        const suffix = /\$="([^"]+)"/.exec(selector)?.[1]
        return suffix ? page.rows.filter((field) => field.id.endsWith(suffix)) : []
    },
    on: (element: Field, type: string, handler: (event: { target: Field }) => void) => {
        element.listeners[type] = [...(element.listeners[type] ?? []), handler]
    },
}))

function field(id: string, value = ''): Field {
    return { id, value, placeholder: '', listeners: {} }
}

const fire = (element: Field, type: string) =>
    element.listeners[type]?.forEach((handler) => handler({ target: element }))

const container = fake<HTMLElement>({})

describe('timesheet-hours-sync', () => {
    let start: Field, end: Field, collection: Field & { dataset: Record<string, string> }

    beforeEach(() => {
        start = field('app_event_startTime', '20:30')
        end = field('app_event_endTime')
        collection = { ...field('app_event_timesheets'), dataset: {} }
        page.fields = {
            '#app_event_hours': field('app_event_hours'),
            '#app_event_startTime': start,
            '#app_event_endTime': end,
            '#app_event_timesheets': collection,
        }
        page.rows = [field('app_event_timesheets_0_startTime'), field('app_event_timesheets_0_endTime')]
    })

    const placeholders = () => page.rows.map((row) => row.placeholder)

    test('shows the default times in the rows', () => {
        initTimesheetHoursSync(container)

        expect(placeholders()).toEqual(['20:30', 'hh:mm'])
    })

    test('follows the default times as they are typed, once complete', () => {
        initTimesheetHoursSync(container)

        end.value = '23:'
        fire(end, 'input')
        expect(placeholders()).toEqual(['20:30', 'hh:mm'])

        end.value = '23:45'
        fire(end, 'input')
        start.value = ''
        fire(start, 'change')
        expect(placeholders()).toEqual(['hh:mm', '23:45'])
    })

    test('gives the default times to the rows added', () => {
        initTimesheetHoursSync(container)

        page.rows.push(field('app_event_timesheets_1_startTime'), field('app_event_timesheets_1_endTime'))
        fire(collection, 'collection.added')

        expect(placeholders()).toEqual(['20:30', 'hh:mm', '20:30', 'hh:mm'])
    })
})
