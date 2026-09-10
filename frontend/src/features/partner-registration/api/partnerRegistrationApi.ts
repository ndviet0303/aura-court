import { api } from '@/core/api/client'
import type {
  PartnerApplication,
  PartnerDocument,
  PartnerReviewHistory,
  StartApplicationPayload,
  SaveDraftPayload,
  SubmitApplicationPayload,
  SupplementApplicationPayload,
} from '../types'

export const partnerRegistrationApi = {
  getCurrentApplication: async (): Promise<{ data: PartnerApplication | null }> => {
    return api.get<{ data: PartnerApplication | null }>('/partner/applications/current')
  },

  startApplication: async (payload: StartApplicationPayload): Promise<{ data: PartnerApplication; message: string }> => {
    return api.post<{ data: PartnerApplication; message: string }>('/partner/applications/start', payload)
  },

  saveDraft: async (id: number, payload: SaveDraftPayload): Promise<{ data: PartnerApplication; message: string }> => {
    return api.put<{ data: PartnerApplication; message: string }>(`/partner/applications/${id}/draft`, payload)
  },

  uploadDocument: async (
    id: number,
    formData: FormData
  ): Promise<{ data: PartnerDocument; message: string }> => {
    return api.post<{ data: PartnerDocument; message: string }>(
      `/partner/applications/${id}/documents`,
      formData
    )
  },

  submitApplication: async (
    id: number,
    payload: SubmitApplicationPayload
  ): Promise<{ data: PartnerApplication; message: string }> => {
    return api.post<{ data: PartnerApplication; message: string }>(
      `/partner/applications/${id}/submit`,
      payload
    )
  },

  supplementApplication: async (
    id: number,
    payload: SupplementApplicationPayload
  ): Promise<{ data: PartnerApplication; message: string }> => {
    return api.post<{ data: PartnerApplication; message: string }>(
      `/partner/applications/${id}/supplement`,
      payload
    )
  },

  getApplicationHistory: async (id: number): Promise<{ data: PartnerReviewHistory[] }> => {
    return api.get<{ data: PartnerReviewHistory[] }>(`/partner/applications/${id}/history`)
  },
}
