import { apiV2 } from '@/core/api/client'
import type {
  CreateMatchPayload,
  MatchResponse,
  InvitePartnerPayload,
} from '../types/social'

export const socialApi = {
  createMatch: (payload: CreateMatchPayload) =>
    apiV2.post<MatchResponse>('/social/matches', payload),

  invitePartner: (matchId: number | string, payload: InvitePartnerPayload) =>
    apiV2.post(`/social/matches/${matchId}/invite`, payload),

  getMatches: <T = { matches?: any[]; data?: any[] }>() =>
    apiV2.get<T>('/social/matches'),

  getMatch: (id: number | string) =>
    apiV2.get<MatchResponse>(`/social/matches/${id}`),

  joinMatch: (matchId: number | string) =>
    apiV2.post(`/social/matches/${matchId}/join`),

  getProfile: <T = unknown>() =>
    apiV2.get<T>('/social/profile'),

  updateProfile: (payload: unknown) =>
    apiV2.put('/social/profile', payload),
}
