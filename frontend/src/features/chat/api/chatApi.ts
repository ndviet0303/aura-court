import { api } from '@/core/api/client'
import type {
  ChatMessage,
  Conversation,
  GiftedVoucher,
  OpenConversationPayload,
} from '../types/chat'

// FR-118: chat crosses organizations, so these calls carry no organization header; the API checks the side.
export const chatApi = {
  list: () => api.get<{ data: Conversation[] }>('/conversations'),
  open: (payload: OpenConversationPayload) =>
    api.post<{ conversation: Conversation }>('/conversations', payload),
  messages: (id: number, after?: number) =>
    api.get<{ conversation: Conversation; data: ChatMessage[] }>(`/conversations/${id}/messages`, {
      params: { after },
    }),
  send: (id: number, body: string, image: File | null) => {
    const form = new FormData()
    if (body) form.append('body', body)
    if (image) form.append('image', image)
    return api.post<{ message: ChatMessage }>(`/conversations/${id}/messages`, form)
  },
  gift: (id: number, code: string, note: string) =>
    api.post<{ message: ChatMessage }>(`/conversations/${id}/vouchers`, {
      code,
      note: note || null,
    }),
  giftable: (venueId: number) =>
    api.get<{ data: GiftedVoucher[] }>(`/venues/${venueId}/gift-vouchers`),
}
