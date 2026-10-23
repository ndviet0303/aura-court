import { api } from '@/core/api/client'
import type { Venue } from '@/features/court/types/court'
import type { Review, ReviewPage, ReviewPayload } from '../types/review'

export const reviewApi = {
  venueReviews: (venueId: number, page = 1) =>
    api.get<ReviewPage>(`/venues/${venueId}/reviews`, { params: { page } }),
  create: (reservationCode: string, payload: ReviewPayload) =>
    api.post<{ review: Review }>('/reviews', { reservation_code: reservationCode, ...payload }),
  update: (id: number, payload: ReviewPayload) =>
    api.put<{ review: Review }>(`/reviews/${id}`, payload),
  remove: (id: number) => api.delete<void>(`/reviews/${id}`),
  // The venues the signed-in owner or staff member works with.
  ownerVenues: () => api.get<Venue[]>('/admin/venues'),
  // Calling it again edits the reply; owners never edit or delete the review itself.
  reply: (id: number, body: string) =>
    api.put<{ review: Review }>(`/reviews/${id}/reply`, { body }),
}
