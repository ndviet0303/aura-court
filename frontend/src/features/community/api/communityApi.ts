import { api } from '@/core/api/client'
import type { Venue } from '@/features/court/types/court'
import type {
  Comment,
  PageMeta,
  Post,
  PostPayload,
  PostType,
  ReportTarget,
} from '../types/community'

// FR-112..FR-114: one platform-wide feed, so these calls carry no organization header.
export const communityApi = {
  feed: (type: PostType | '', page: number) =>
    api.get<{ data: Post[]; meta: PageMeta }>('/social/feed', {
      params: { type: type || undefined, page },
    }),
  post: (id: number) => api.get<{ post: Post & { comments: Comment[] } }>(`/social/feed/${id}`),
  create: (payload: PostPayload) => api.post<{ post: Post }>('/social/feed', payload),
  update: (id: number, payload: PostPayload) =>
    api.put<{ post: Post }>(`/social/feed/${id}`, payload),
  remove: (id: number) => api.delete<void>(`/social/feed/${id}`),
  addComment: (postId: number, content: string) =>
    api.post<{ comment: Comment }>(`/social/feed/${postId}/comments`, { content }),
  updateComment: (id: number, content: string) =>
    api.put<{ comment: Comment }>(`/social/feed/comments/${id}`, { content }),
  removeComment: (id: number) => api.delete<void>(`/social/feed/comments/${id}`),
  // D9: images are uploaded one by one first; posts and reviews then carry the returned URLs.
  uploadImage: (image: File) => {
    const form = new FormData()
    form.append('image', image)
    return api.post<{ url: string }>('/uploads/community-images', form)
  },
  // The public venue list, for the venue of a matchmaking post.
  venues: () => api.get<Venue[]>('/venues'),
  report: (target: ReportTarget, reason: string) =>
    api.post<{ report: unknown }>('/content-reports', { ...target, reason }),
}
