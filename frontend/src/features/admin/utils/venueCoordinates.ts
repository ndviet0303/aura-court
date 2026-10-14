export type VenueCoordinates = { latitude: number | null; longitude: number | null }

/**
 * Parses "21.0306, 105.783" as Google Maps copies it on right-click.
 * Empty text clears the coordinates; null means the text is not a valid pair.
 */
export function parseVenueCoordinates(text: string): VenueCoordinates | null {
  const value = text.trim()
  if (!value) return { latitude: null, longitude: null }
  const match = value.match(/^(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)$/)
  if (!match) return null
  const latitude = Number(match[1])
  const longitude = Number(match[2])
  return Math.abs(latitude) <= 90 && Math.abs(longitude) <= 180 ? { latitude, longitude } : null
}

export function formatVenueCoordinates(venue?: Partial<VenueCoordinates> | null): string {
  return venue?.latitude != null && venue.longitude != null
    ? `${venue.latitude}, ${venue.longitude}`
    : ''
}
