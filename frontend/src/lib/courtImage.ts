export interface CourtImageEntity {
  image_url?: string | null
  sport_type?: string
}

export const BADMINTON_DEFAULT_IMAGE = '/courts/badminton-0.jpg'
export const BADMINTON_GALLERY: string[] = [
  '/courts/badminton-0.jpg',
  '/courts/badminton-court.jpg',
  '/courts/badminton-night.jpg',
  '/courts/badminton-wood.jpg',
]

export const SPORT_DEFAULT_IMAGES: Record<string, string> = {
  football: '/courts/football-mini-turf.jpg',
  football_5v5: '/courts/football-mini-turf.jpg',
  football_7v7: '/courts/football-wide.jpg',
  tennis: '/courts/tennis-1.jpg',
  pickleball: '/courts/pickleball-outdoor.jpg',
  basketball: '/courts/basketball-nba.jpg',
  table_tennis: '/courts/table-tennis-1.jpg',
  volleyball: '/courts/volleyball-indoor.jpg',
  badminton: BADMINTON_DEFAULT_IMAGE,
}

export const SPORT_GALLERY_COLLECTIONS: Record<string, string[]> = {
  football: [
    '/courts/football-mini-turf.jpg',
    '/courts/football-saigon.jpg',
    '/courts/football-wide.jpg',
    '/courts/football-3.jpg',
    '/courts/football-1.jpg',
  ],
  tennis: ['/courts/tennis-1.jpg', '/courts/tennis-2.jpg', '/courts/tennis-3.jpg'],
  pickleball: [
    '/courts/pickleball-outdoor.jpg',
    '/courts/pickleball-neon.jpg',
    '/courts/pickleball-1.jpg',
  ],
  basketball: [
    '/courts/basketball-nba.jpg',
    '/courts/basketball-2.jpg',
    '/courts/basketball-1.jpg',
  ],
  table_tennis: ['/courts/table-tennis-1.jpg', '/courts/table-tennis-2.jpg'],
  volleyball: [
    '/courts/volleyball-indoor.jpg',
    '/courts/volleyball-1.jpg',
    '/courts/volleyball-2.jpg',
  ],
  badminton: BADMINTON_GALLERY,
}

/** The court's own sport when it has a default image, else badminton. */
export function detectCourtSport(
  court?: CourtImageEntity | null,
): keyof typeof SPORT_DEFAULT_IMAGES {
  const sport = court?.sport_type ?? ''
  return sport in SPORT_DEFAULT_IMAGES ? sport : 'badminton'
}

/**
 * Resolves the primary display image for any court entity, falling back
 * safely to sport-accurate imagery if empty.
 */
export function resolveCourtImage(court?: CourtImageEntity | null): string {
  if (court?.image_url && court.image_url.trim()) {
    return court.image_url.trim()
  }
  const sport = detectCourtSport(court)
  return SPORT_DEFAULT_IMAGES[sport] ?? BADMINTON_DEFAULT_IMAGE
}

/**
 * Returns a gallery array of high quality photos matching the sport of the court.
 */
export function getCourtGalleryImages(court?: CourtImageEntity | null): string[] {
  const primary = resolveCourtImage(court)
  const sport = detectCourtSport(court)
  const baseKey = sport.startsWith('football') ? 'football' : sport
  const collection: string[] = SPORT_GALLERY_COLLECTIONS[baseKey] ?? BADMINTON_GALLERY

  const set = new Set<string>([primary, ...collection])
  return Array.from(set)
}
