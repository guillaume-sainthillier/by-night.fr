/**
 * Tests for the sync of the date rows with the event's default hours
 * Run: pnpm test
 */

import type { App } from '@silarhi/app-js'
import type { FormView } from '@silarhi/app-js/form'
import { beforeEach, describe, expect, test, vi } from 'vitest'
import type { EventForm } from '@/js/forms/EventForm'
import initTimesheetHoursSync from '@/js/listeners/timesheet-hours-sync'
import { fake } from '@/js/test/fake'

// The Node environment has no DOM: the form view and its fields are plain objects
interface FakeField {
    value: string
    placeholder: string
    listeners: Record<string, (() => void)[]>
    on: (type: string, handler: () => void) => () => void
    setPlaceholder: (placeholder: string) => void
}

const { collectionListeners } = vi.hoisted(() => ({ collectionListeners: {} as Record<string, (() => void)[]> }))

vi.mock('@silarhi/app-js', () => ({
    on: (_element: unknown, type: string, handler: () => void) => {
        collectionListeners[type] = [...(collectionListeners[type] ?? []), handler]
    },
}))

function field(value = ''): FakeField {
    const self: FakeField = {
        value,
        placeholder: '',
        listeners: {},
        on: (type, handler) => {
            self.listeners[type] = [...(self.listeners[type] ?? []), handler]
            return () => {}
        },
        setPlaceholder: (placeholder) => {
            self.placeholder = placeholder
        },
    }
    return self
}

const fire = (target: FakeField, type: string) => target.listeners[type]?.forEach((handler) => handler())

function row() {
    const fields: Record<string, FakeField> = { startTime: field(), endTime: field(), hours: field() }
    return { fields, field: (path: string) => fields[path] }
}

describe('timesheet-hours-sync', () => {
    let start: FakeField, end: FakeField, rows: ReturnType<typeof row>[], form: FormView<EventForm>
    const app = fake<App>({ get: () => ({ each: () => () => {} }) })

    beforeEach(() => {
        for (const type of Object.keys(collectionListeners)) delete collectionListeners[type]
        start = field('20:30')
        end = field()
        rows = [row()]
        const fields: Record<string, FakeField> = { hours: field(), startTime: start, endTime: end }
        form = fake<FormView<EventForm>>({
            field: (path: string) => fields[path],
            get: () => ({ element: { dataset: {} }, children: rows }),
        })
    })

    const placeholders = () => rows.flatMap((r) => [r.fields.startTime.placeholder, r.fields.endTime.placeholder])

    test('shows the default times in the rows', () => {
        initTimesheetHoursSync(form, app)

        expect(placeholders()).toEqual(['20:30', 'hh:mm'])
    })

    test('follows the default times as they are typed, once complete', () => {
        initTimesheetHoursSync(form, app)

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
        initTimesheetHoursSync(form, app)

        rows.push(row())
        for (const handler of collectionListeners['collection.add'] ?? []) handler()

        expect(placeholders()).toEqual(['20:30', 'hh:mm', '20:30', 'hh:mm'])
    })
})
