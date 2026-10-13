import { api } from '@/core/api/client'
import type {
  CreateMatchPayload,
  MatchResponse,
  InvitePartnerPayload,
  DropInSessionBill,
  SocialMatch,
} from '../types/social'

export const socialApi = {
  createMatch: (payload: CreateMatchPayload) => api.post<MatchResponse>('/social/matches', payload),

  invitePartner: (matchId: number | string, payload: InvitePartnerPayload) =>
    api.post(`/social/matches/${matchId}/invite`, payload),

  getMatches: <T = { matches?: SocialMatch[]; data?: SocialMatch[] }>(
    params?: Record<string, string | number | boolean | null | undefined>,
  ) => api.get<T>('/social/matches', params ? { params } : undefined),
  updateMatch: (matchId: number | string, payload: Record<string, unknown>) =>
    api.put<MatchResponse>(`/social/matches/${matchId}`, payload),

  endSession: (matchId: number | string) =>
    api.post<MatchResponse>(`/social/matches/${matchId}/end`),

  getSessionBill: (matchId: number | string) =>
    api.get<{ bill: DropInSessionBill }>(`/social/matches/${matchId}/bill`),

  saveDraftBill: (matchId: number | string, payload: Record<string, unknown>) =>
    api.post<{ bill: DropInSessionBill }>(`/social/matches/${matchId}/bill/draft`, payload),

  finalizeSessionBill: (matchId: number | string, payload: Record<string, unknown>) =>
    api.post<{ bill: DropInSessionBill }>(`/social/matches/${matchId}/bill/finalize`, payload),

  getDefaultRates: (matchId: number | string) =>
    api.get<{
      rates: { court_hourly_rate: number | null; shuttlecock_unit_price: number | null }
    }>(`/social/matches/${matchId}/rates`),

  getMatch: (id: number | string) => api.get<MatchResponse>(`/social/matches/${id}`),

  joinMatch: (matchId: number | string) => api.post(`/social/matches/${matchId}/join`),

  getProfile: <T = unknown>() => api.get<T>('/social/profile'),

  updateProfile: (payload: unknown) => api.put('/social/profile', payload),

  getFriends: <T = { friends: any[]; total: number }>(query?: string) =>
    api.get<T>(query ? `/social/friends?q=${encodeURIComponent(query)}` : '/social/friends'),

  getFriendRequests: <T = { incoming: any[]; outgoing: any[] }>() =>
    api.get<T>('/social/friends/requests'),

  sendFriendRequest: (addresseeId: number) =>
    api.post('/social/friends/requests', { addressee_id: addresseeId }),

  acceptFriendRequest: (friendshipId: number) =>
    api.post(`/social/friends/requests/${friendshipId}/accept`),

  declineFriendRequest: (friendshipId: number) =>
    api.post(`/social/friends/requests/${friendshipId}/decline`),

  removeFriend: (friendshipId: number) => api.delete(`/social/friends/${friendshipId}`),

  invitePartners: (matchId: number | string, inviteeIds: number[]) =>
    api.post<{
      invitations: any[]
      results: Array<{ invitee_id: number; status: 'invited' | 'failed'; reason?: string }>
      summary: { total: number; invited: number; failed: number }
    }>(`/social/matches/${matchId}/invite`, { invitee_ids: inviteeIds }),

  respondInvitation: (invitationId: number | string, status: 'accepted' | 'declined') =>
    api.post(`/social/invitations/${invitationId}/respond`, { status }),
  // The list returns reaction rows; the feed badges read per-type counts, the shape reactFeedItem returns.
  getFeed: async (page = 1) => {
    const res = await api.get<{ data: any[]; current_page: number; last_page: number }>(
      `/social/feed?page=${page}`,
    )
    const data = (res?.data || []).map((post) => ({
      ...post,
      reactions_counts:
        post.reactions_counts ??
        (post.reactions || []).reduce(
          (counts: Record<string, number>, r: { reaction_type: string }) => {
            counts[r.reaction_type] = (counts[r.reaction_type] || 0) + 1
            return counts
          },
          {},
        ),
    }))
    return { ...res, data }
  },

  createFeedPost: (payload: { content: string; audience?: 'organization' | 'friends' }) =>
    api.post<{ post: any; message: string }>('/social/feed', payload),

  deleteFeedPost: (postId: number) => api.delete(`/social/feed/${postId}`),

  addFeedComment: (postId: number, content: string) =>
    api.post<{ comment: any; message: string }>(`/social/feed/${postId}/comments`, { content }),

  deleteFeedComment: (commentId: number) => api.delete(`/social/feed/comments/${commentId}`),

  reactFeedItem: (type: 'post' | 'comment', id: number, reactionType: string) =>
    type === 'post'
      ? api.post<{ action: string; reaction_type: string; counts: Record<string, number> }>(
          `/social/feed/${id}/react`,
          { type: reactionType },
        )
      : api.post<{ action: string; reaction_type: string; counts: Record<string, number> }>(
          `/social/feed/comments/${id}/react`,
          { type: reactionType },
        ),

  reportFeedItem: (type: 'post' | 'comment', id: number, reason: string) =>
    type === 'post'
      ? api.post<{ report: any; message: string }>(`/social/feed/${id}/report`, { reason })
      : api.post<{ report: any; message: string }>(`/social/feed/comments/${id}/report`, {
          reason,
        }),

  getFeedReports: (status = 'pending') =>
    api.get<{ data: any[] }>(`/social/feed/reports?status=${status}`),

  hideFeedItem: (type: 'post' | 'comment', id: number, reason: string) =>
    type === 'post'
      ? api.post(`/social/feed/${id}/hide`, { reason })
      : api.post(`/social/feed/comments/${id}/hide`, { reason }),

  restoreFeedItem: (type: 'post' | 'comment', id: number, reason: string) =>
    type === 'post'
      ? api.post(`/social/feed/${id}/restore`, { reason })
      : api.post(`/social/feed/comments/${id}/restore`, { reason }),
}
