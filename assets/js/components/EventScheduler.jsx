import { h } from 'preact'
import { useState, useEffect, useCallback } from 'preact/hooks'
import TimesheetGenerator from '@/js/utils/TimesheetGenerator'
import { detectPattern } from '@/js/utils/timesheetPatternDetector'
import { dom, findAll } from '@/js/utils/dom'
import moment from 'moment'

const weekdayNames = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim']

const Icon = ({ name, className = '' }) => (
    <svg
        className={`icon ${className}`}
        xmlns="http://www.w3.org/2000/svg"
        width="24"
        height="24"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        strokeWidth="2"
        strokeLinecap="round"
        strokeLinejoin="round"
        dangerouslySetInnerHTML={{ __html: getIconPath(name) }}
    />
)

const getIconPath = (name) => {
    const icons = {
        clock: '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
        'calendar-days': '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><path d="M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01M16 18h.01"/>',
        'calendar-repeat': '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><path d="m17 14-3 3 3 3"/><path d="m7 14 3 3-3 3"/>',
        'pencil': '<path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/>',
    }
    return icons[name] || ''
}

export default function EventScheduler({ dateRangeFieldIds, timesheetsCollectionId, collectionManager, detectExisting }) {
    const [generator] = useState(() => new TimesheetGenerator())
    const [pattern, setPattern] = useState('daily')
    const [startTime, setStartTime] = useState('09:00')
    const [endTime, setEndTime] = useState('17:00')
    const [selectedWeekdays, setSelectedWeekdays] = useState(new Set([1, 2, 3, 4, 5]))
    const [errors, setErrors] = useState({})
    const [previewCount, setPreviewCount] = useState(0)
    const [isMultiDay, setIsMultiDay] = useState(false)

    /**
     * Get date range from form fields
     */
    const getDateRange = useCallback(() => {
        const startDateField = dom(`#${dateRangeFieldIds.start}`)
        const endDateField = dom(`#${dateRangeFieldIds.end}`)

        if (!startDateField || !endDateField) {
            return { startDate: null, endDate: null, isMultiDay: false }
        }

        const startDate = startDateField.value
        const endDate = endDateField.value
        const isMultiDay = startDate && endDate && startDate !== endDate

        return {
            startDate,
            endDate,
            isMultiDay,
        }
    }, [dateRangeFieldIds])

    /**
     * Detect pattern from existing timesheets in the form
     */
    const detectAndLoadPattern = useCallback(() => {
        try {
            const collection = dom(`#${timesheetsCollectionId}`)
            if (!collection) return

            const existingTimesheets = []
            const items = findAll('.form-group', collection)

            items.forEach((item) => {
                const startAtInput = item.querySelector('input[id*="startAt"]')
                const endAtInput = item.querySelector('input[id*="endAt"]')

                if (startAtInput && endAtInput && startAtInput.value && endAtInput.value) {
                    existingTimesheets.push({
                        startAt: startAtInput.value,
                        endAt: endAtInput.value,
                    })
                }
            })

            if (existingTimesheets.length === 0) return

            const detection = detectPattern(existingTimesheets)

            // If pattern mode is detected, use the detected pattern
            if (detection.mode === 'pattern') {
                setPattern(detection.config.pattern || 'daily')
                if (detection.config.weekdays) {
                    setSelectedWeekdays(new Set(detection.config.weekdays))
                }
            }
            setStartTime(detection.config.startTime || '09:00')
            setEndTime(detection.config.endTime || '17:00')
        } catch (error) {
            console.error('Failed to detect pattern:', error)
        }
    }, [timesheetsCollectionId])

    /**
     * Update preview count
     */
    const updatePreview = useCallback(() => {
        const { startDate, endDate, isMultiDay } = getDateRange()

        if (!startDate || !endDate || !startTime || !endTime) {
            setPreviewCount(0)
            setIsMultiDay(false)
            return
        }

        setIsMultiDay(isMultiDay)

        try {
            let timesheets = []

            if (!isMultiDay) {
                // Single day event
                setPreviewCount(1)
                return
            }

            // Multi-day event
            if (pattern === 'daily') {
                timesheets = generator.generateDaily(startDate, endDate, startTime, endTime)
            } else if (pattern === 'weekdays') {
                timesheets = generator.generateWeekdays(
                    startDate,
                    endDate,
                    Array.from(selectedWeekdays),
                    startTime,
                    endTime
                )
            }

            setPreviewCount(timesheets.length)
        } catch (error) {
            console.error('Failed to update preview:', error)
            setPreviewCount(0)
        }
    }, [pattern, startTime, endTime, selectedWeekdays, generator, getDateRange])

    /**
     * Validate form inputs
     */
    const validate = useCallback(() => {
        const newErrors = {}
        const { startDate, endDate, isMultiDay } = getDateRange()

        // Validate dates
        if (!startDate || !endDate) {
            newErrors.dates = 'Veuillez sélectionner les dates de début et de fin'
        } else if (!generator.validateDateRange(startDate, endDate)) {
            newErrors.dates = 'La plage de dates ne peut pas dépasser 365 jours'
        }

        // Validate times
        if (!generator.validateTimeRange(startTime, endTime)) {
            newErrors.times = "L'heure de fin doit être après l'heure de début"
        }

        // Validate weekdays selection for multi-day events with specific days
        if (isMultiDay && pattern === 'weekdays' && selectedWeekdays.size === 0) {
            newErrors.weekdays = 'Veuillez sélectionner au moins un jour de la semaine'
        }

        setErrors(newErrors)
        return Object.keys(newErrors).length === 0
    }, [pattern, startTime, endTime, selectedWeekdays, generator, getDateRange])

    /**
     * Generate timesheets and add to collection
     */
    const handleGenerate = useCallback((e) => {
        e.preventDefault()

        if (!validate()) {
            return
        }

        const { startDate, endDate, isMultiDay } = getDateRange()

        let timesheets = []

        try {
            if (!isMultiDay) {
                // Single day event
                timesheets = generator.generateDaily(startDate, endDate, startTime, endTime)
            } else {
                // Multi-day event
                if (pattern === 'daily') {
                    timesheets = generator.generateDaily(startDate, endDate, startTime, endTime)
                } else if (pattern === 'weekdays') {
                    timesheets = generator.generateWeekdays(
                        startDate,
                        endDate,
                        Array.from(selectedWeekdays),
                        startTime,
                        endTime
                    )
                }
            }

            // Clear existing timesheets in collection
            const collection = dom(`#${timesheetsCollectionId}`)
            if (!collection) {
                throw new Error('Collection not found')
            }

            collectionManager.emptyCollection(collection)

            // Add generated timesheets to collection
            timesheets.forEach((timesheet) => {
                collectionManager.addElement(collection, {
                    startAt: timesheet.startAt,
                    endAt: timesheet.endAt,
                })
            })

            // Show success message
            window.App.get('toastManager').createToast('success', `${timesheets.length} créneaux horaires générés`)
        } catch (error) {
            console.error('Failed to generate timesheets:', error)
            window.App.get('toastManager').createToast('error', 'Erreur lors de la génération des créneaux horaires')
        }
    }, [pattern, startTime, endTime, selectedWeekdays, generator, validate, getDateRange, timesheetsCollectionId, collectionManager])

    /**
     * Handle pattern change
     */
    const handlePatternChange = useCallback((e) => {
        setPattern(e.target.value)
    }, [])

    /**
     * Handle time change
     */
    const handleTimeChange = useCallback((field, value) => {
        if (field === 'startTime') {
            setStartTime(value)
        } else {
            setEndTime(value)
        }
    }, [])

    /**
     * Toggle weekday selection
     */
    const toggleWeekday = useCallback((day) => {
        setSelectedWeekdays((prev) => {
            const newWeekdays = new Set(prev)
            if (newWeekdays.has(day)) {
                newWeekdays.delete(day)
            } else {
                newWeekdays.add(day)
            }
            return newWeekdays
        })
    }, [])

    // Effect for initial detection
    useEffect(() => {
        if (detectExisting) {
            detectAndLoadPattern()
        }
    }, [detectExisting, detectAndLoadPattern])

    // Effect for updating preview
    useEffect(() => {
        updatePreview()
    }, [updatePreview])

    // Effect for listening to date field changes
    useEffect(() => {
        const startDateField = dom(`#${dateRangeFieldIds.start}`)
        const endDateField = dom(`#${dateRangeFieldIds.end}`)

        if (!startDateField || !endDateField) return

        const handleDateChange = () => {
            const { isMultiDay } = getDateRange()
            setIsMultiDay(isMultiDay)
            updatePreview()
        }

        startDateField.addEventListener('change', handleDateChange)
        endDateField.addEventListener('change', handleDateChange)

        // Also listen to input events for real-time updates
        startDateField.addEventListener('input', handleDateChange)
        endDateField.addEventListener('input', handleDateChange)

        // Initialize state on mount
        handleDateChange()

        return () => {
            startDateField.removeEventListener('change', handleDateChange)
            endDateField.removeEventListener('change', handleDateChange)
            startDateField.removeEventListener('input', handleDateChange)
            endDateField.removeEventListener('input', handleDateChange)
        }
    }, [dateRangeFieldIds, getDateRange, updatePreview])

    return (
        <div className="event-scheduler">
            {/* Error messages */}
            {errors.dates && <div className="alert alert-danger">{errors.dates}</div>}

            {/* Horaires section - always shown */}
            <div className="time-inputs">
                <div className="form-group mb-0">
                    <label className="form-label">Heure de début</label>
                    <input
                        type="time"
                        className="form-control"
                        value={startTime}
                        onChange={(e) => handleTimeChange('startTime', e.target.value)}
                    />
                </div>
                <div className="form-group mb-0">
                    <label className="form-label">Heure de fin</label>
                    <input
                        type="time"
                        className="form-control"
                        value={endTime}
                        onChange={(e) => handleTimeChange('endTime', e.target.value)}
                    />
                </div>
            </div>
            {errors.times && <div className="alert alert-danger mt-2">{errors.times}</div>}

            {/* Pattern selection - only shown for multi-day events */}
            {isMultiDay && (
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
                                onChange={handlePatternChange}
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
                                onChange={handlePatternChange}
                            />
                            <span className="form-selectgroup-label">Jours spécifiques</span>
                        </label>
                    </div>
                </div>
            )}

            {/* Weekday selection - only shown for specific days pattern */}
            {isMultiDay && pattern === 'weekdays' && (
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
                    {errors.weekdays && <div className="alert alert-danger mt-2">{errors.weekdays}</div>}
                </div>
            )}

            {/* Preview and generate button */}
            {previewCount > 0 && (
                <div className="preview">
                    <span className="preview-count">{previewCount}</span> créneaux horaires seront générés
                </div>
            )}

            <button type="button" className="btn btn-primary btn-generate" onClick={handleGenerate}>
                Générer les créneaux horaires
            </button>
        </div>
    )
}
