import { api } from '@/core/api/client'
import type { Court, Venue, AvailabilitySlot } from '../types/court'

export interface CourtQueryParams {
  sport_type?: string
  venue_id?: number
  [key: string]: string | number | boolean | undefined | null
}

export const courtApi = {
  list: (params?: CourtQueryParams) => {
    const cleanParams: Record<string, string | number> = {}
    if (params?.sport_type && params.sport_type !== 'all') {
      cleanParams.sport_type = params.sport_type
    }
    if (params?.venue_id) {
      cleanParams.venue_id = params.venue_id
    }
    return api.get<Court[]>('/courts', { params: cleanParams })
  },

  getById: (id: number | string) => api.get<Court>(`/courts/${id}`),

  getVenueBySlug: (slug: string) => api.get<Venue>(`/venues/${slug}`),

  getAvailability: (courtId: number, date: string) =>
    api.get<AvailabilitySlot[]>(`/availability?court_id=${courtId}&date=${date}`),
}
