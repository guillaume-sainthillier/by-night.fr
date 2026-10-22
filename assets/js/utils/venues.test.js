import { describe, expect, it } from 'vitest'
import { groupByVenue } from './venues'

const event = (overrides) => ({
    name: 'Concert',
    url: '/toulouse/soiree/concert--1',
    date: '2026-10-24',
    venue: 1,
    venueName: 'Le Bikini',
    lat: 43.55,
    lng: 1.47,
    ...overrides,
})

describe('groupByVenue', () => {
    it('puts the events of a venue under one marker, in the order of the agenda', () => {
        const venues = groupByVenue([
            event({ name: 'A' }),
            event({ name: 'B', venue: 2, venueName: 'Le Zénith', lat: 43.59, lng: 1.41 }),
            event({ name: 'C' }),
        ])

        expect(venues).toHaveLength(2)
        expect(venues[0]).toMatchObject({ key: 'venue-1', name: 'Le Bikini', lat: 43.55, lng: 1.47 })
        expect(venues[0].events.map((e) => e.name)).toEqual(['A', 'C'])
        expect(venues[1].events.map((e) => e.name)).toEqual(['B'])
    })

    it('groups the events without a venue that stand at the same spot', () => {
        const venues = groupByVenue([
            event({ name: 'A', venue: null }),
            event({ name: 'B', venue: null, lat: 43.550001 }),
            event({ name: 'C', venue: null, lat: 43.6 }),
        ])

        expect(venues.map((v) => v.events.map((e) => e.name))).toEqual([['A', 'B'], ['C']])
    })

    it('leaves out the events that cannot be placed', () => {
        const venues = groupByVenue([
            event({ lat: null, lng: null }),
            event({ lat: 0, lng: 0 }),
            event({ lat: 120, lng: 1 }),
            event({ name: 'Placed' }),
        ])

        expect(venues.flatMap((v) => v.events.map((e) => e.name))).toEqual(['Placed'])
    })

    it('has no marker for no event', () => {
        expect(groupByVenue([])).toEqual([])
    })
})
