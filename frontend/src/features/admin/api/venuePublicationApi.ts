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
    venue_metadata: boolean
    active_courts: boolean
    court_pricing: boolean
    policies_configured: boolean
    operating_calendar_configured?: boolean
    receiving_connection_selected?: boolean
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
      price_table: { id: number; name: string } | null
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

export interface ReceivingConnectionEvidenceItem {
  id: number
  event_type: string
  credential_version: number
  event_fingerprint: string | null
  created_at: string | null
}

export interface OwnerReceivingConnection {
  id: number
  routing_id: string
  provider: 'sepay'
  bank_code: string
  account_name: string
  account_number_masked: string
  status: 'draft' | 'pending_verification' | 'verified' | 'revoked' | 'superseded'
  is_selected: boolean
  credential_version: number
  credential_configured: boolean
  query_api_configured: boolean
  query_api_version: number
  provider_account_id?: string | null
  verified_at?: string | null
  webhook_url: string
  onboarding: {
    instructions_vi: string[]
    instructions_en: string[]
    optional_transaction_query?: {
      base_url: string
      instructions_vi: string[]
      instructions_en: string[]
    }
  }
}

export const venuePublicationApi = {
  getReadiness: async (
    organizationId: number,
    venueId: number,
  ): Promise<{ data: VenueReadinessChecklist }> => {
    return api.get<{ data: VenueReadinessChecklist }>(
      `/partner/organizations/${organizationId}/venues/${venueId}/readiness`,
    )
  },

  getReceivingConnection: async (organizationId: number, venueId: number) =>
    api.get<{ data: OwnerReceivingConnection | null }>(
      `/partner/organizations/${organizationId}/venues/${venueId}/receiving-connection`,
    ),

  createReceivingConnection: async (
    organizationId: number,
    venueId: number,
    payload: {
      bank_code: string
      account_name: string
      account_number: string
      legal_owner_confirmed: true
    },
  ) =>
    api.post<{ data: OwnerReceivingConnection; message: string }>(
      `/partner/organizations/${organizationId}/venues/${venueId}/receiving-connection`,
      payload,
    ),

  rotateReceivingCredential: async (
    organizationId: number,
    venueId: number,
    connectionId: number,
    webhookApiKey: string,
  ) =>
    api.post<{ data: OwnerReceivingConnection; message: string }>(
      `/partner/organizations/${organizationId}/venues/${venueId}/receiving-connection/${connectionId}/credential`,
      { webhook_api_key: webhookApiKey },
    ),

  selectReceivingConnection: async (
    organizationId: number,
    venueId: number,
    connectionId: number,
  ) =>
    api.post<{ data: OwnerReceivingConnection; message: string }>(
      `/partner/organizations/${organizationId}/venues/${venueId}/receiving-connection/${connectionId}/select`,
    ),

  rotateReceivingApiToken: async (
    organizationId: number,
    venueId: number,
    connectionId: number,
    apiToken: string,
    providerAccountId: string,
  ) =>
    api.post<{ data: OwnerReceivingConnection; message: string }>(
      `/partner/organizations/${organizationId}/venues/${venueId}/receiving-connection/${connectionId}/query-credential`,
      { api_token: apiToken, provider_account_id: providerAccountId },
    ),

  getReceivingConnectionEvidence: async (
    organizationId: number,
    venueId: number,
    connectionId: number,
  ) =>
    api.get<{ data: ReceivingConnectionEvidenceItem[] }>(
      `/partner/organizations/${organizationId}/venues/${venueId}/receiving-connection/${connectionId}/evidence`,
    ),

  syncReceivingTransactions: async (
    organizationId: number,
    venueId: number,
    connectionId: number,
  ) =>
    api.post<{
      data: { transactions_received: number; last_queried_transaction_id: string | null }
    }>(
      `/partner/organizations/${organizationId}/venues/${venueId}/receiving-connection/${connectionId}/transactions/sync`,
    ),

  submitPublication: async (
    organizationId: number,
    venueId: number,
  ): Promise<{ data: VenuePublicationRevision; message: string }> => {
    return api.post<{ data: VenuePublicationRevision; message: string }>(
      `/partner/organizations/${organizationId}/venues/${venueId}/submit-publication`,
    )
  },

  setOperationalStatus: async (
    organizationId: number,
    venueId: number,
    payload: { status: 'published' | 'temporarily_closed' | 'closed'; reason?: string },
  ): Promise<{ data: any; message: string }> => {
    return api.post<{ data: any; message: string }>(
      `/partner/organizations/${organizationId}/venues/${venueId}/operational-status`,
      payload,
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
    revisionId: number,
  ): Promise<{ data: VenuePublicationRevision }> => {
    return api.get<{ data: VenuePublicationRevision }>(`/admin/venues/revisions/${revisionId}`)
  },

  reviewPublicationRevision: async (
    revisionId: number,
    payload: {
      decision: 'approve' | 'revision_required' | 'reject'
      reason?: string
      feedback_fields?: Record<string, string>
    },
  ): Promise<{ data: VenuePublicationRevision; message: string }> => {
    return api.post<{ data: VenuePublicationRevision; message: string }>(
      `/admin/venues/revisions/${revisionId}/review`,
      payload,
    )
  },
}
