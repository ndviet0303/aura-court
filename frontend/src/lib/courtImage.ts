export interface CourtImageEntity {
  id?: number | string
  name?: string
  image_url?: string | null
  sport_zone?: {
    name?: string
    sport_type?: string
  } | null
}

export const SPORT_DEFAULT_IMAGES = {
  football: '/courts/football-mini-turf.jpg',
  football_5v5: '/courts/football-mini-turf.jpg',
  football_7v7: '/courts/football-wide.jpg',
  tennis: '/courts/tennis-1.jpg',
  pickleball: '/courts/pickleball-outdoor.jpg',
  basketball: '/courts/basketball-nba.jpg',
  table_tennis: '/courts/table-tennis-1.jpg',
  volleyball: '/courts/volleyball-indoor.jpg',
  badminton: '/courts/badminton-0.jpg',
} as const

export const SPORT_GALLERY_COLLECTIONS: Record<string, string[]> = {
  football: [
    '/courts/football-mini-turf.jpg',
    '/courts/football-saigon.jpg',
    '/courts/football-wide.jpg',
    '/courts/football-3.jpg',
    '/courts/football-1.jpg',
  ],
  tennis: [
    '/courts/tennis-1.jpg',
    '/courts/tennis-2.jpg',
    '/courts/tennis-3.jpg',
  ],
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
  table_tennis: [
    '/courts/table-tennis-1.jpg',
    '/courts/table-tennis-2.jpg',
  ],
  volleyball: [
    '/courts/volleyball-indoor.jpg',
    '/courts/volleyball-1.jpg',
    '/courts/volleyball-2.jpg',
  ],
  badminton: [
    '/courts/badminton-0.jpg',
    '/courts/badminton-court.jpg',
    '/courts/badminton-night.jpg',
    '/courts/badminton-wood.jpg',
  ],
}

/**
 * Detect sport category key from court attributes
 */
export function detectCourtSport(court?: CourtImageEntity | null): keyof typeof SPORT_DEFAULT_IMAGES {
  const sportType = (court?.sport_zone?.sport_type || '').toLowerCase()
  const name = (court?.name || '').toLowerCase()
  const zoneName = (court?.sport_zone?.name || '').toLowerCase()

  if (
    sportType.includes('football') ||
    sportType.includes('soccer') ||
    name.includes('bóng đá') ||
    name.includes('football') ||
    zoneName.includes('bóng đá')
  ) {
    if (sportType.includes('7v7') || name.includes('7v7') || zoneName.includes('7v7')) {
      return 'football_7v7'
    }
    return 'football_5v5'
  }

  if (
    sportType.includes('tennis') ||
    name.includes('tennis') ||
    name.includes('quần vợt') ||
    zoneName.includes('tennis')
  ) {
    return 'tennis'
  }

  if (
    sportType.includes('pickleball') ||
    name.includes('pickleball') ||
    zoneName.includes('pickleball')
  ) {
    return 'pickleball'
  }

  if (
    sportType.includes('basketball') ||
    name.includes('bóng rổ') ||
    name.includes('basketball') ||
    zoneName.includes('bóng rổ')
  ) {
    return 'basketball'
  }

  if (
    sportType.includes('table_tennis') ||
    name.includes('bóng bàn') ||
    name.includes('table tennis') ||
    zoneName.includes('bóng bàn')
  ) {
    return 'table_tennis'
  }

  if (
    sportType.includes('volleyball') ||
    name.includes('bóng chuyền') ||
    name.includes('volleyball') ||
    zoneName.includes('bóng chuyền')
  ) {
    return 'volleyball'
  }

  return 'badminton'
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
  return SPORT_DEFAULT_IMAGES[sport] || SPORT_DEFAULT_IMAGES.badminton
}

/**
 * Returns a gallery array of high quality photos matching the sport of the court.
 */
export function getCourtGalleryImages(court?: CourtImageEntity | null): string[] {
  const primary = resolveCourtImage(court)
  const sport = detectCourtSport(court)
  const baseKey = sport.startsWith('football') ? 'football' : sport
  const collection = SPORT_GALLERY_COLLECTIONS[baseKey] || SPORT_GALLERY_COLLECTIONS.badminton

  const set = new Set([primary, ...collection])
  return Array.from(set)
}
