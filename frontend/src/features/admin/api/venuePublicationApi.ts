import { api } from '@/core/api/client'

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
}
