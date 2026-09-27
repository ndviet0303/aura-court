import { api } from '@/core/api/client'
import type {
  PartnerApplication,
  PartnerDocument,
  PartnerReviewHistory,
  SaveDraftPayload,
  SubmitApplicationPayload,
} from '../types'

export const partnerRegistrationApi = {
  getCurrentApplication: async (): Promise<{ data: PartnerApplication | null }> => {
    return api.get<{ data: PartnerApplication | null }>('/partner/application/current')
  },

  startApplication: async (): Promise<{ data: PartnerApplication; message: string }> => {
    return api.post<{ data: PartnerApplication; message: string }>('/partner/application/start', {})
  },

  saveDraft: async (id: number, payload: SaveDraftPayload): Promise<{ data: PartnerApplication; message: string }> => {
    return api.put<{ data: PartnerApplication; message: string }>(`/partner/application/${id}`, payload)
  },

  uploadDocument: async (
    id: number,
    formData: FormData
  ): Promise<{ data: PartnerDocument; message: string }> => {
    return api.post<{ data: PartnerDocument; message: string }>(
      `/partner/application/${id}/documents`,
      formData
    )
  },

  submitApplication: async (
    id: number,
    payload: SubmitApplicationPayload
  ): Promise<{ data: PartnerApplication; message: string }> => {
    return api.post<{ data: PartnerApplication; message: string }>(
      `/partner/application/${id}/submit`,
      payload
    )
  },

  getApplicationHistory: async (id: number): Promise<{ data: PartnerReviewHistory[] }> => {
    return api.get<{ data: PartnerReviewHistory[] }>(`/partner/application/${id}/history`)
  },
}
