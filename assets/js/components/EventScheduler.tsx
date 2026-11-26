import { findOne } from '@silarhi/app-js'
import type { CollectionManager, Field, FormView } from '@silarhi/app-js/form'
import { useEffect, useMemo, useState } from 'preact/hooks'
import type { EventForm } from '@/js/forms/EventForm'
import { plural } from '@/js/utils/plural'
import TimesheetGenerator, { type Timesheet } from '@/js/utils/TimesheetGenerator'
import { detectPattern } from '@/js/utils/timesheetPatternDetector'

const weekdayNames = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim']

// Hoisted outside component - never changes
const generator = new TimesheetGenerator()

type Pattern = 'daily' | 'weekdays'

interface Errors {
    dates?: string
    weekdays?: string
}

interface DateRange {
    startDate: string | null
    endDate: string | null
}

export interface EventSchedulerProps {
    /** The event's start and end date fields, which the period follows */
    startDateField: Field<HTMLInputElement, string>
    endDateField: Field<HTMLInputElement, string>
    /** The timesheets collection the dates are generated into */
    timesheets: FormView<EventForm['timesheets']>
    /** The id of the "Générer les dates" button, outside this component */
    generateButtonId: string
    collectionManager: CollectionManager
    /** Whether to preselect the recurrence of the timesheets already there */
    detectExisting: boolean
}

export default function EventScheduler({
    startDateField,
    endDateField,
    timesheets,
    generateButtonId,
    collectionManager,
    detectExisting,
}: EventSchedulerProps) {
    const [pattern, setPattern] = useState<Pattern>('daily')
    const [selectedWeekdays, setSelectedWeekdays] = useState(() => new Set([1, 2, 3, 4, 5]))
    const [errors, setErrors] = useState<Errors>({})
    const [dateRange, setDateRange] = useState<DateRange>({ startDate: null, endDate: null })

    // Derived state - no need to store in useState (rule 5.4)
    const isMultiDay = dateRange.startDate && dateRange.endDate && dateRange.startDate !== dateRange.endDate

    // Compute preview count from current state (derived, not stored)
    const previewCount = useMemo(() => {
        const { startDate, endDate } = dateRange
        if (!startDate || !endDate) return 0
        if (!isMultiDay) return 1

        try {
            if (pattern === 'daily') {
                return generator.generateDaily(startDate, endDate).length
            }
            if (pattern === 'weekdays') {
                return generator.generateWeekdays(startDate, endDate, Array.from(selectedWeekdays)).length
            }
        } catch {
            return 0
        }
        return 0
    }, [dateRange, isMultiDay, pattern, selectedWeekdays])

    // Detect pattern from existing timesheets (runs once on mount if detectExisting)
    useEffect(() => {
        if (!detectExisting) return

        const existingTimesheets: Timesheet[] = []
        for (const item of timesheets.children) {
            const startAt = item.field('dateRange.from').value
            const endAt = item.field('dateRange.to').value
            if (startAt && endAt) {
                existingTimesheets.push({ startAt, endAt })
            }
        }

        if (existingTimesheets.length === 0) return

        try {
            const detection = detectPattern(existingTimesheets)
            if (detection.mode === 'pattern') {
                setPattern(detection.config.pattern || 'daily')
                if (detection.config.pattern === 'weekdays') {
                    setSelectedWeekdays(new Set(detection.config.weekdays))
                }
            }
        } catch (error) {
            console.error('Failed to detect pattern:', error)
        }
    }, [detectExisting, timesheets])

    // Listen to main event startDate/endDate field changes
    useEffect(() => {
        const syncDateRange = () => {
            setDateRange({
                startDate: startDateField.value || null,
                endDate: endDateField.value || null,
            })
        }

        // Initialize on mount
        syncDateRange()

        // Listen for changes (both 'change' for date picker and 'input' for manual typing)
        const stops = [startDateField, endDateField].flatMap((field) => [
            field.on('change', syncDateRange),
            field.on('input', syncDateRange),
        ])

        return () => {
            for (const stop of stops) stop()
        }
    }, [startDateField, endDateField])

    // Validate and generate
    const handleGenerate = async (e: Event) => {
        e.preventDefault()

        const { startDate, endDate } = dateRange
        const newErrors: Errors = {}

        // Validate
        if (!startDate || !endDate) {
            newErrors.dates = 'Veuillez sélectionner les dates de début et de fin'
        } else if (!generator.validateDateRange(startDate, endDate)) {
            newErrors.dates = 'La plage de dates ne peut pas dépasser 365 jours'
        }

        if (isMultiDay && pattern === 'weekdays' && selectedWeekdays.size === 0) {
            newErrors.weekdays = 'Veuillez sélectionner au moins un jour de la semaine'
        }

        setErrors(newErrors)
        if (Object.keys(newErrors).length > 0 || !startDate || !endDate) return

        // Generate timesheets
        try {
            const generated =
                pattern === 'weekdays' && isMultiDay
                    ? generator.generateWeekdays(startDate, endDate, Array.from(selectedWeekdays))
                    : generator.generateDaily(startDate, endDate)

            await collectionManager.clear(timesheets)

            for (const timesheet of generated) {
                // Keyed by the item's field paths: the dates sit in its dateRange (timesheets_0_dateRange_from)
                collectionManager.add(timesheets, {
                    dateRange: { from: timesheet.startAt, to: timesheet.endAt },
                })
            }

            window.App.get('toastManager').createToast(
                'success',
                plural(generated.length, { one: '# date générée', other: '# dates générées' })
            )
        } catch (error) {
            console.error('Failed to generate timesheets:', error)
            window.App.get('toastManager').createToast('error', 'Erreur lors de la génération des dates')
        }
    }

    // The "Générer les dates" button sits next to the period field, outside this component: re-bound on every render
    // so that it always generates from the current state
    useEffect(() => {
        const button = findOne(`#${generateButtonId}`)
        if (!button) return

        button.addEventListener('click', handleGenerate)
        return () => button.removeEventListener('click', handleGenerate)
    })

    // Toggle weekday - functional setState (rule 5.5)
    const toggleWeekday = (day: number) => {
        setSelectedWeekdays((prev) => {
            const next = new Set(prev)
            if (next.has(day)) {
                next.delete(day)
            } else {
                next.add(day)
            }
            return next
        })
    }

    return (
        <div className="event-scheduler">
            {errors.dates ? <div className="alert alert-danger mt-3 mb-0">{errors.dates}</div> : null}

            {isMultiDay ? (
                <div className="mt-3">
                    <div className="form-selectgroup form-selectgroup-pills">
                        <label className="form-selectgroup-item">
                            <input
                                type="radio"
                                name="pattern"
                                value="daily"
                                className="form-selectgroup-input"
                                checked={pattern === 'daily'}
                                onChange={() => setPattern('daily')}
                            />
                            <span className="form-selectgroup-label">Tous les jours</span>
                        </label>
                        <label className="form-selectgroup-item">
                            <input
                                type="radio"
                                name="pattern"
                                value="weekdays"
                                className="form-selectgroup-input"
                                checked={pattern === 'weekdays'}
                                onChange={() => setPattern('weekdays')}
                            />
                            <span className="form-selectgroup-label">Certains jours</span>
                        </label>
                    </div>

                    {pattern === 'weekdays' ? (
                        <div className="form-selectgroup form-selectgroup-boxes form-selectgroup-full mt-2">
                            {weekdayNames.map((name, index) => {
                                const day = index + 1
                                return (
                                    <label key={day} className="form-selectgroup-item">
                                        <input
                                            type="checkbox"
                                            name="weekdays"
                                            value={day}
                                            className="form-selectgroup-input"
                                            checked={selectedWeekdays.has(day)}
                                            onChange={() => toggleWeekday(day)}
                                        />
                                        <div className="form-selectgroup-label text-center">{name}</div>
                                    </label>
                                )
                            })}
                        </div>
                    ) : null}
                    {errors.weekdays ? <div className="alert alert-danger mt-2 mb-0">{errors.weekdays}</div> : null}
                </div>
            ) : null}

            {previewCount > 0 ? (
                <p className="text-secondary small mt-2 mb-0">
                    <strong className="text-body">{previewCount}</strong>{' '}
                    {plural(previewCount, { one: 'date sera générée', other: 'dates seront générées' })}
                </p>
            ) : null}
        </div>
    )
}
