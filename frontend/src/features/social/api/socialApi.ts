import { apiV2 } from '@/core/api/client'
import type {
  CreateMatchPayload,
  MatchResponse,
  InvitePartnerPayload,
  DropInSessionBill,
  SocialMatch,
} from '../types/social'

export const socialApi = {
  createMatch: (payload: CreateMatchPayload) =>
    apiV2.post<MatchResponse>('/social/matches', payload),

  invitePartner: (matchId: number | string, payload: InvitePartnerPayload) =>
    apiV2.post(`/social/matches/${matchId}/invite`, payload),

  getMatches: <T = { matches?: SocialMatch[]; data?: SocialMatch[] }>(
    params?: Record<string, string | number | boolean | null | undefined>,
  ) => apiV2.get<T>('/social/matches', params ? { params } : undefined),
  updateMatch: (matchId: number | string, payload: Record<string, unknown>) =>
    apiV2.put<MatchResponse>(`/social/matches/${matchId}`, payload),

  endSession: (matchId: number | string) =>
    apiV2.post<MatchResponse>(`/social/matches/${matchId}/end`),

  getSessionBill: (matchId: number | string) =>
    apiV2.get<{ bill: DropInSessionBill }>(`/social/matches/${matchId}/bill`),

  saveDraftBill: (matchId: number | string, payload: Record<string, unknown>) =>
    apiV2.post<{ bill: DropInSessionBill }>(`/social/matches/${matchId}/bill/draft`, payload),

  finalizeSessionBill: (matchId: number | string, payload: Record<string, unknown>) =>
    apiV2.post<{ bill: DropInSessionBill }>(`/social/matches/${matchId}/bill/finalize`, payload),

  getDefaultRates: (matchId: number | string) =>
    apiV2.get<{
      rates: { court_hourly_rate: number | null; shuttlecock_unit_price: number | null }
    }>(`/social/matches/${matchId}/rates`),

  getMatch: (id: number | string) => apiV2.get<MatchResponse>(`/social/matches/${id}`),

  joinMatch: (matchId: number | string) => apiV2.post(`/social/matches/${matchId}/join`),

  getProfile: <T = unknown>() => apiV2.get<T>('/social/profile'),

  updateProfile: (payload: unknown) => apiV2.put('/social/profile', payload),

  getFriends: <T = { friends: any[]; total: number }>(query?: string) =>
    apiV2.get<T>(query ? `/social/friends?q=${encodeURIComponent(query)}` : '/social/friends'),

  getFriendRequests: <T = { incoming: any[]; outgoing: any[] }>() =>
    apiV2.get<T>('/social/friends/requests'),

  sendFriendRequest: (addresseeId: number) =>
    apiV2.post('/social/friends/requests', { addressee_id: addresseeId }),

  acceptFriendRequest: (friendshipId: number) =>
    apiV2.post(`/social/friends/requests/${friendshipId}/accept`),

  declineFriendRequest: (friendshipId: number) =>
    apiV2.post(`/social/friends/requests/${friendshipId}/decline`),

  removeFriend: (friendshipId: number) => apiV2.delete(`/social/friends/${friendshipId}`),

  invitePartners: (matchId: number | string, inviteeIds: number[]) =>
    apiV2.post<{
      invitations: any[]
      results: Array<{ invitee_id: number; status: 'invited' | 'failed'; reason?: string }>
      summary: { total: number; invited: number; failed: number }
    }>(`/social/matches/${matchId}/invite`, { invitee_ids: inviteeIds }),

  respondInvitation: (invitationId: number | string, status: 'accepted' | 'declined') =>
    apiV2.post(`/social/invitations/${invitationId}/respond`, { status }),
  getFeed: (page = 1) =>
    apiV2.get<{ data: any[]; current_page: number; last_page: number }>(
      `/social/feed?page=${page}`,
    ),

  createFeedPost: (payload: { content: string; audience?: 'organization' | 'friends' }) =>
    apiV2.post<{ post: any; message: string }>('/social/feed', payload),

  deleteFeedPost: (postId: number) => apiV2.delete(`/social/feed/${postId}`),

  addFeedComment: (postId: number, content: string) =>
    apiV2.post<{ comment: any; message: string }>(`/social/feed/${postId}/comments`, { content }),

  deleteFeedComment: (commentId: number) => apiV2.delete(`/social/feed/comments/${commentId}`),

  reactFeedItem: (type: 'post' | 'comment', id: number, reactionType: string) =>
    type === 'post'
      ? apiV2.post<{ action: string; reaction_type: string; counts: Record<string, number> }>(
          `/social/feed/${id}/react`,
          { type: reactionType },
        )
      : apiV2.post<{ action: string; reaction_type: string; counts: Record<string, number> }>(
          `/social/feed/comments/${id}/react`,
          { type: reactionType },
        ),

  reportFeedItem: (type: 'post' | 'comment', id: number, reason: string) =>
    type === 'post'
      ? apiV2.post<{ report: any; message: string }>(`/social/feed/${id}/report`, { reason })
      : apiV2.post<{ report: any; message: string }>(`/social/feed/comments/${id}/report`, {
          reason,
        }),

  getFeedReports: (status = 'pending') =>
    apiV2.get<{ data: any[] }>(`/social/feed/reports?status=${status}`),

  hideFeedItem: (type: 'post' | 'comment', id: number, reason: string) =>
    type === 'post'
      ? apiV2.post(`/social/feed/${id}/hide`, { reason })
      : apiV2.post(`/social/feed/comments/${id}/hide`, { reason }),

  restoreFeedItem: (type: 'post' | 'comment', id: number, reason: string) =>
    type === 'post'
      ? apiV2.post(`/social/feed/${id}/restore`, { reason })
      : apiV2.post(`/social/feed/comments/${id}/restore`, { reason }),
}
