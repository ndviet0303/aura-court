import type { Court } from '@/features/court/types/court'

export type LatLng = { lat: number; lng: number }
export type CourtArea = { key: string; label: string; count: number; distanceKm: number | null }

export const OTHER_AREA = '__other'

/** A court's area is its venue's district, falling back to the city. */
export function courtAreaKey(court: Court): string {
  return court.venue?.district?.trim() || court.venue?.city?.trim() || OTHER_AREA
}

/** Great-circle distance in km (haversine), same formula as the backend MatchService. */
export function distanceKm(a: LatLng, b: LatLng): number {
  const rad = (deg: number) => (deg * Math.PI) / 180
  const h =
    Math.sin(rad(b.lat - a.lat) / 2) ** 2 +
    Math.cos(rad(a.lat)) * Math.cos(rad(b.lat)) * Math.sin(rad(b.lng - a.lng) / 2) ** 2
  return 6371 * 2 * Math.atan2(Math.sqrt(h), Math.sqrt(1 - h))
}

export function courtDistanceKm(court: Court, origin: LatLng | null): number | null {
  const lat = court.venue?.latitude
  const lng = court.venue?.longitude
  if (!origin || lat == null || lng == null) return null
  return distanceKm(origin, { lat, lng })
}

// Unknown distances sort last; Infinity - Infinity is NaN, so fall back to 0 to keep order.
const nearestFirst = (a: number | null, b: number | null) =>
  (a ?? Number.POSITIVE_INFINITY) - (b ?? Number.POSITIVE_INFINITY) || 0

/** Areas in court order, or nearest first once the user's position is known. */
export function groupCourtAreas(
  courts: Court[],
  origin: LatLng | null,
  otherLabel: string,
): CourtArea[] {
  const areas = new Map<string, CourtArea>()
  for (const court of courts) {
    const key = courtAreaKey(court)
    const area = areas.get(key) ?? {
      key,
      label: key === OTHER_AREA ? otherLabel : key,
      count: 0,
      distanceKm: null,
    }
    const distance = courtDistanceKm(court, origin)
    if (distance !== null && (area.distanceKm === null || distance < area.distanceKm)) {
      area.distanceKm = distance
    }
    area.count++
    areas.set(key, area)
  }
  const list = [...areas.values()]
  return origin ? list.sort((a, b) => nearestFirst(a.distanceKm, b.distanceKm)) : list
}

export function filterCourts(
  courts: Court[],
  area: string | null,
  query: string,
  origin: LatLng | null,
): Court[] {
  const q = query.trim().toLowerCase()
  const matches = courts.filter((court) => {
    if (area !== null && courtAreaKey(court) !== area) return false
    if (!q) return true
    const fields = [
      court.name,
      court.venue?.name,
      court.venue?.district,
      court.venue?.address,
      court.sport_zone?.name,
    ]
    return fields.some((text) => (text || '').toLowerCase().includes(q))
  })
  if (!origin) return matches
  return matches.sort((a, b) =>
    nearestFirst(courtDistanceKm(a, origin), courtDistanceKm(b, origin)),
  )
}

/** Chip subtitle, e.g. "Cầu Giấy · 2.1 km". */
export function describeCourtPlace(court: Court, origin: LatLng | null): string {
  const key = courtAreaKey(court)
  const distance = courtDistanceKm(court, origin)
  return [key === OTHER_AREA ? '' : key, distance === null ? '' : `${distance.toFixed(1)} km`]
    .filter(Boolean)
    .join(' · ')
}
