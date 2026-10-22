import type { Court } from '@/features/court/types/court'

/**
 * Gets the current or provided date string (YYYY-MM-DD) in the venue's local timezone (default: Asia/Ho_Chi_Minh).
 * Correctly avoids UTC date slicing boundary bugs.
 */
export function getVenueLocalDate(
  dateOrTimezone?: Date | string | number,
  timeZone?: string,
): string {
  let tz = timeZone || 'Asia/Ho_Chi_Minh'
  let dInput: Date | string | number = new Date()

  if (typeof dateOrTimezone === 'string') {
    const intlWithSupported = Intl as unknown as { supportedValuesOf?: (type: string) => string[] }
    const isSupportedIana =
      typeof intlWithSupported.supportedValuesOf === 'function' &&
      intlWithSupported.supportedValuesOf('timeZone').includes(dateOrTimezone)

    if (
      !timeZone &&
      (dateOrTimezone.includes('/') ||
        ['UTC', 'GMT'].includes(dateOrTimezone.toUpperCase()) ||
        isSupportedIana)
    ) {
      // Called as getVenueLocalDate('Asia/Tokyo') or getVenueLocalDate('UTC')
      tz = dateOrTimezone
      dInput = new Date()
    } else if (/^\d{4}-\d{2}-\d{2}$/.test(dateOrTimezone)) {
      return dateOrTimezone
    } else {
      dInput = dateOrTimezone
    }
  } else if (dateOrTimezone instanceof Date || typeof dateOrTimezone === 'number') {
    dInput = dateOrTimezone
  }

  const d = typeof dInput === 'string' || typeof dInput === 'number' ? new Date(dInput) : dInput
  return new Intl.DateTimeFormat('en-CA', {
    timeZone: tz,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).format(d)
}

/**
 * Resolves the dynamic availability heading in the specified locale.
 * Replaces fixed "today" headings with format:
 * - vi: "Lịch trống — {court} — {date}"
 * - en: "Availability — {court} — {date}"
 */
export function resolveAvailabilityHeading(
  courtName?: string | null,
  dateStr?: string | null,
  locale: string = 'vi',
  timeZone: string = 'Asia/Ho_Chi_Minh',
): string {
  if (!courtName) {
    return locale === 'en' ? 'Live Availability' : 'Lịch sân trống trực quan'
  }
  const effectiveDate = dateStr || getVenueLocalDate(new Date(), timeZone)
  const prefix = locale === 'en' ? 'Availability' : 'Lịch trống'
  return `${prefix} — ${courtName} — ${effectiveDate}`
}

/** "Thứ Năm, 15/10/2026" for a venue-local YYYY-MM-DD, capitalised for a heading. */
export function formatAvailabilityDate(date: string, locale: string): string {
  // Format it as a UTC midnight so the calendar day never shifts with the browser's timezone.
  const label = new Intl.DateTimeFormat(locale === 'en' ? 'en-US' : 'vi-VN', {
    weekday: 'long',
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    timeZone: 'UTC',
  }).format(new Date(`${date}T00:00:00Z`))
  return label.charAt(0).toUpperCase() + label.slice(1)
}

/** Material icon for a court chip, from the court's sport. */
export function getCourtIcon(court: Court): string {
  const sport = court.sport_type
  if (sport === 'football_5v5' || sport === 'football_7v7') return 'sports_soccer'
  if (sport === 'basketball') return 'sports_basketball'
  if (sport === 'volleyball') return 'sports_volleyball'
  if (sport === 'badminton') return 'badminton'
  if (sport === 'pickleball') return 'pickleball'
  return 'sports_tennis'
}
