import { describe, it, expect } from 'vitest'
import type { Court } from '@/features/court/types/court'
import {
  OTHER_AREA,
  describeCourtPlace,
  distanceKm,
  filterCourts,
  groupCourtAreas,
} from '../utils/courtArea'

const court = (id: number, venue: Partial<NonNullable<Court['venue']>> | null): Court =>
  ({ id, name: `Sân ${id}`, slug: `san-${id}`, venue: venue ?? undefined }) as Court

// Cầu Giấy and Tây Hồ in Hà Nội; Quận 7 in Sài Gòn; one city-only venue; one without location data.
const cauGiay = court(1, {
  name: 'Aura Court Cầu Giấy',
  district: 'Cầu Giấy',
  city: 'Hà Nội',
  latitude: 21.0306,
  longitude: 105.783,
})
const tayHo = court(2, {
  name: 'Aura Tây Hồ',
  district: 'Tây Hồ',
  city: 'Hà Nội',
  latitude: 21.0655,
  longitude: 105.827,
})
const quan7 = court(3, {
  name: 'Hoàng Gia',
  district: 'Quận 7',
  city: 'TP. Hồ Chí Minh',
  latitude: 10.729,
  longitude: 106.721,
})
const cityOnly = court(4, { name: 'Sân Đà Nẵng', district: null, city: 'Đà Nẵng' })
const unknown = court(5, null)
const courts = [quan7, cauGiay, tayHo, cityOnly, unknown]
const westLake = { lat: 21.06, lng: 105.82 }

describe('courtArea', () => {
  it('measures great-circle distance in km', () => {
    // Hà Nội to Sài Gòn is about 1,140 km as the crow flies.
    const hanoiToSaigon = distanceKm(
      { lat: 21.0285, lng: 105.8542 },
      { lat: 10.7769, lng: 106.7009 },
    )
    expect(Math.abs(hanoiToSaigon - 1140)).toBeLessThan(10)
    expect(distanceKm(westLake, westLake)).toBe(0)
  })

  it('groups courts by district, falling back to city, in court order without a position', () => {
    expect(groupCourtAreas(courts, null, 'Khác').map((a) => [a.key, a.label, a.count])).toEqual([
      ['Quận 7', 'Quận 7', 1],
      ['Cầu Giấy', 'Cầu Giấy', 1],
      ['Tây Hồ', 'Tây Hồ', 1],
      ['Đà Nẵng', 'Đà Nẵng', 1],
      [OTHER_AREA, 'Khác', 1],
    ])
  })

  it('sorts areas and courts nearest first, unknown distances last', () => {
    expect(groupCourtAreas(courts, westLake, 'Khác').map((a) => a.key)).toEqual([
      'Tây Hồ',
      'Cầu Giấy',
      'Quận 7',
      'Đà Nẵng',
      OTHER_AREA,
    ])
    expect(filterCourts(courts, null, '', westLake).map((c) => c.id)).toEqual([2, 1, 3, 4, 5])
  })

  it('filters by area and searches name, venue, district and address', () => {
    expect(filterCourts(courts, 'Cầu Giấy', '', null)).toEqual([cauGiay])
    expect(filterCourts(courts, OTHER_AREA, '', null)).toEqual([unknown])
    expect(filterCourts(courts, null, 'tây hồ', null)).toEqual([tayHo])
    expect(filterCourts(courts, null, 'hoàng gia', null)).toEqual([quan7])
  })

  it('describes where a court is and how far away', () => {
    expect(describeCourtPlace(tayHo, null)).toBe('Tây Hồ')
    expect(describeCourtPlace(tayHo, westLake)).toMatch(/^Tây Hồ · \d+\.\d km$/)
    expect(describeCourtPlace(unknown, westLake)).toBe('')
  })
})
