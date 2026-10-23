export type ConversationType = 'customer_customer' | 'customer_venue' | 'owner_admin'
export type ChatSide = 'customer' | 'venue' | 'owner' | 'admin'

export interface Conversation {
  id: number
  type: ConversationType
  /** The viewer's side; the venue, owner and admin sides are shared (BR-158). */
  side: ChatSide
  title: string
  venue: { id: number; name: string; organization_id: number } | null
  customer_id: number | null
  last_message: { preview: string; mine: boolean } | null
  last_message_at: string | null
}

/** FR-111: what the gifted code offered when it was sent. */
export interface GiftedVoucher {
  code: string
  program: string
  discount_kind: 'percent' | 'fixed'
  percent_bp: number | null
  amount: number | null
  max_discount: number | null
  min_rental: number | null
  courts: string[]
  starts_at?: string | null
  ends_at: string | null
}

export interface ChatMessage {
  id: number
  side: ChatSide
  /** The customer's name, or the venue or organization name, or "Quản trị viên". */
  sender_name: string
  /** Who actually sent it; only the same shared side sees it (BR-158). */
  sent_by: string | null
  mine: boolean
  body: string | null
  image_url: string | null
  voucher: GiftedVoucher | null
  created_at: string
}

export type OpenConversationPayload =
  | { type: 'customer_customer'; user_id: number }
  | { type: 'customer_venue'; venue_id: number; user_id?: number }
  | { type: 'owner_admin'; organization_id: number }
