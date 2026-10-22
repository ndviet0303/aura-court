import { api } from '@/core/api/client'
import type {
  CreatePromotionProgramPayload,
  PromotionProgram,
  PromotionProgramCodes,
  PromotionProgramTerms,
} from '../types/promotionProgram'

export const promotionProgramApi = {
  list: (venueId: number) =>
    api.get<{ data: PromotionProgram[] }>(`/admin/venues/${venueId}/promotion-programs`),
  create: (venueId: number, payload: CreatePromotionProgramPayload) =>
    api.post<{ data: PromotionProgram }>(`/admin/venues/${venueId}/promotion-programs`, payload),
  show: (programId: number) =>
    api.get<{ data: PromotionProgram }>(`/admin/promotion-programs/${programId}`),
  update: (programId: number, payload: Partial<PromotionProgramTerms>) =>
    api.patch<{ data: PromotionProgram }>(`/admin/promotion-programs/${programId}`, payload),
  stop: (programId: number) =>
    api.post<{ data: PromotionProgram }>(`/admin/promotion-programs/${programId}/stop`),
  codes: (programId: number) =>
    api.get<{ data: PromotionProgramCodes }>(`/admin/promotion-programs/${programId}/codes`),
}
