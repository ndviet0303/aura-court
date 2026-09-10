import { api } from '@/core/api/client'

export interface VenueReadinessChecklist {
  is_ready: boolean
  missing_items: Array<{
    code: string
    message: string
    field?: string
  }>
  checklist: {
    partner_active: boolean
    subscription_entitled: boolean
    venue_metadata: boolean
    active_courts: boolean
    court_pricing: boolean
    policies_configured: boolean
    staff_members?: boolean
    pos_products?: boolean
  }
}

export interface VenuePublicationRevision {
  id: number
  venue_id: number
  revision_number: number
  status: 'pending' | 'approved' | 'revision_required' | 'rejected'
  configuration_snapshot: {
    venue: {
      id: number
      name: string
      address: string
      google_maps_url?: string
    }
    courts: Array<{
      id: number
      name: string
      price_slots: Array<{
        weekday: number
        starts_at: string
        ends_at: string
        price: number
      }>
    }>
    organization?: {
      id: number
      name: string
      cancellation_grace_hours?: number
    }
    submitted_at?: string
  }
  submitted_by: number
  submitted_at: string
  reviewed_by?: number
  reviewed_at?: string
  decision_reason?: string
  feedback_fields?: Record<string, string>
}

export const venuePublicationApi = {
  getReadiness: async (
    organizationId: number,
    venueId: number
  ): Promise<{ data: VenueReadinessChecklist }> => {
    return api.get<{ data: VenueReadinessChecklist }>(
      `/partner/organizations/${organizationId}/venues/${venueId}/readiness`
    )
  },

  submitPublication: async (
    organizationId: number,
    venueId: number
  ): Promise<{ data: VenuePublicationRevision; message: string }> => {
    return api.post<{ data: VenuePublicationRevision; message: string }>(
      `/partner/organizations/${organizationId}/venues/${venueId}/submit-publication`
    )
  },

  setOperationalStatus: async (
    organizationId: number,
    venueId: number,
    payload: { status: 'published' | 'temporarily_closed' | 'closed'; reason?: string }
  ): Promise<{ data: any; message: string }> => {
    return api.post<{ data: any; message: string }>(
      `/partner/organizations/${organizationId}/venues/${venueId}/operational-status`,
      payload
    )
  },

  getPublicationQueue: async (): Promise<{
    data: VenuePublicationRevision[]
    meta: { current_page: number; total: number; last_page: number }
  }> => {
    return api.get<{
      data: VenuePublicationRevision[]
      meta: { current_page: number; total: number; last_page: number }
    }>('/admin/venues/publication-queue')
  },

  getPublicationRevision: async (
    revisionId: number
  ): Promise<{ data: VenuePublicationRevision }> => {
    return api.get<{ data: VenuePublicationRevision }>(
      `/admin/venues/revisions/${revisionId}`
    )
  },

  reviewPublicationRevision: async (
    revisionId: number,
    payload: {
      decision: 'approve' | 'revision_required' | 'reject'
      reason?: string
      feedback_fields?: Record<string, string>
    }
  ): Promise<{ data: VenuePublicationRevision; message: string }> => {
    return api.post<{ data: VenuePublicationRevision; message: string }>(
      `/admin/venues/revisions/${revisionId}/review`,
      payload
    )
  },
}
