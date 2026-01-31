import { h } from 'preact'
import { useState, useEffect, useMemo } from 'preact/hooks'
import TimesheetGenerator from '@/js/utils/TimesheetGenerator'
import { detectPattern } from '@/js/utils/timesheetPatternDetector'
import { dom, findAll } from '@/js/utils/dom'

const weekdayNames = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim']

// Hoisted outside component - never changes
const generator = new TimesheetGenerator()

export default function EventScheduler({ startDateFieldId, endDateFieldId, timesheetsCollectionId, collectionManager, detectExisting }) {
    const [pattern, setPattern] = useState('daily')
    const [selectedWeekdays, setSelectedWeekdays] = useState(() => new Set([1, 2, 3, 4, 5]))
    const [errors, setErrors] = useState({})
    const [dateRange, setDateRange] = useState({ startDate: null, endDate: null })

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

        const collection = dom(`#${timesheetsCollectionId}`)
        if (!collection) return

        const existingTimesheets = []
        for (const item of findAll('.form-group', collection)) {
            const startAtInput = item.querySelector('input[id*="startAt"]')
            const endAtInput = item.querySelector('input[id*="endAt"]')

            if (startAtInput?.value && endAtInput?.value) {
                existingTimesheets.push({
                    startAt: startAtInput.value,
                    endAt: endAtInput.value,
                })
            }
        }

        if (existingTimesheets.length === 0) return

        try {
            const detection = detectPattern(existingTimesheets)
            if (detection.mode === 'pattern') {
                setPattern(detection.config.pattern || 'daily')
                if (detection.config.weekdays) {
                    setSelectedWeekdays(new Set(detection.config.weekdays))
                }
            }
        } catch (error) {
            console.error('Failed to detect pattern:', error)
        }
    }, [detectExisting, timesheetsCollectionId])

    // Listen to main event startDate/endDate field changes
    useEffect(() => {
        const startDateField = dom(`#${startDateFieldId}`)
        const endDateField = dom(`#${endDateFieldId}`)

        if (!startDateField || !endDateField) return

        const syncDateRange = () => {
            setDateRange({
                startDate: startDateField.value || null,
                endDate: endDateField.value || null,
            })
        }

        // Initialize on mount
        syncDateRange()

        // Listen for changes (both 'change' for date picker and 'input' for manual typing)
        startDateField.addEventListener('change', syncDateRange)
        endDateField.addEventListener('change', syncDateRange)
        startDateField.addEventListener('input', syncDateRange)
        endDateField.addEventListener('input', syncDateRange)

        return () => {
            startDateField.removeEventListener('change', syncDateRange)
            endDateField.removeEventListener('change', syncDateRange)
            startDateField.removeEventListener('input', syncDateRange)
            endDateField.removeEventListener('input', syncDateRange)
        }
    }, [startDateFieldId, endDateFieldId])

    // Validate and generate (no useCallback needed - only used in onClick)
    const handleGenerate = (e) => {
        e.preventDefault()

        const { startDate, endDate } = dateRange
        const newErrors = {}

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
        if (Object.keys(newErrors).length > 0) return

        // Generate timesheets
        try {
            const timesheets =
                pattern === 'weekdays' && isMultiDay
                    ? generator.generateWeekdays(startDate, endDate, Array.from(selectedWeekdays))
                    : generator.generateDaily(startDate, endDate)

            const collection = dom(`#${timesheetsCollectionId}`)
            if (!collection) throw new Error('Collection not found')

            collectionManager.emptyCollection(collection)

            for (const timesheet of timesheets) {
                collectionManager.addElement(collection, {
                    startAt: timesheet.startAt,
                    endAt: timesheet.endAt,
                })
            }

            window.App.get('toastManager').createToast('success', `${timesheets.length} dates générées`)
        } catch (error) {
            console.error('Failed to generate timesheets:', error)
            window.App.get('toastManager').createToast('error', 'Erreur lors de la génération des dates')
        }
    }

    // Toggle weekday - functional setState (rule 5.5)
    const toggleWeekday = (day) => {
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
            {errors.dates ? <div className="alert alert-danger">{errors.dates}</div> : null}

            {isMultiDay ? (
                <div className="form-group">
                    <label className="form-label">Jours</label>
                    <div className="form-selectgroup form-selectgroup-full">
                        <label className="form-selectgroup-item">
                            <input
                                type="radio"
                                name="pattern"
                                value="daily"
                                className="form-selectgroup-input"
                                checked={pattern === 'daily'}
                                onChange={(e) => setPattern(e.target.value)}
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
                                onChange={(e) => setPattern(e.target.value)}
                            />
                            <span className="form-selectgroup-label">Jours spécifiques</span>
                        </label>
                    </div>
                </div>
            ) : null}

            {isMultiDay && pattern === 'weekdays' ? (
                <div className="form-group">
                    <label className="form-label">Jours de la semaine</label>
                    <div className="form-selectgroup form-selectgroup-boxes form-selectgroup-full">
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
                    {errors.weekdays ? <div className="alert alert-danger mt-2">{errors.weekdays}</div> : null}
                </div>
            ) : null}

            {previewCount > 0 ? (
                <div className="preview">
                    <span className="preview-count">{previewCount}</span> dates seront générées
                </div>
            ) : null}

            <button type="button" className="btn btn-primary btn-generate" onClick={handleGenerate}>
                Générer les dates
            </button>
        </div>
    )
}
