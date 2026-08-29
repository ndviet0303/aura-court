import { apiV2 } from '@/core/api/client'
import type { MatchPost, PlayerProfile } from '../types/social'

export const socialApi = {
  getProfile: () => apiV2.get<PlayerProfile>('/social/profile'),

  updateProfile: (payload: Partial<PlayerProfile>) =>
    apiV2.put<PlayerProfile>('/social/profile', payload),

  getMatches: () => apiV2.get<MatchPost[]>('/social/matches'),

  createMatch: (payload: any) =>
    apiV2.post<MatchPost>('/social/matches', payload),

  getMatch: (id: number) =>
    apiV2.get<MatchPost>(`/social/matches/${id}`),

  invite: (postId: number, payload: { user_id: number }) =>
    apiV2.post(`/social/matches/${postId}/invite`, payload),

  join: (postId: number) =>
    apiV2.post(`/social/matches/${postId}/join`),

  respondInvitation: (invitationId: number, payload: { status: 'accepted' | 'declined' }) =>
    apiV2.post(`/social/invitations/${invitationId}/respond`, payload),
}
