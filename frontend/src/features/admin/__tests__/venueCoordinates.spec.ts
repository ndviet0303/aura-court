import { describe, it, expect } from 'vitest'
import { formatVenueCoordinates, parseVenueCoordinates } from '../utils/venueCoordinates'

describe('venueCoordinates', () => {
  it('clears coordinates for empty text', () => {
    expect(parseVenueCoordinates('  ')).toEqual({ latitude: null, longitude: null })
  })

  it('parses a Google Maps "lat, lng" pair', () => {
    expect(parseVenueCoordinates('21.0306, 105.783')).toEqual({
      latitude: 21.0306,
      longitude: 105.783,
    })
    expect(parseVenueCoordinates('-33.8,151')).toEqual({ latitude: -33.8, longitude: 151 })
  })

  it('rejects malformed or out-of-range pairs', () => {
    expect(parseVenueCoordinates('21.0306')).toBeNull()
    expect(parseVenueCoordinates('abc, 105')).toBeNull()
    expect(parseVenueCoordinates('91, 105')).toBeNull()
    expect(parseVenueCoordinates('21, 181')).toBeNull()
  })

  it('formats stored coordinates and round-trips them', () => {
    const text = formatVenueCoordinates({ latitude: 21.0306, longitude: 105.783 })
    expect(text).toBe('21.0306, 105.783')
    expect(parseVenueCoordinates(text)).toEqual({ latitude: 21.0306, longitude: 105.783 })
    expect(formatVenueCoordinates({ latitude: null, longitude: 105 })).toBe('')
    expect(formatVenueCoordinates(null)).toBe('')
  })
})
