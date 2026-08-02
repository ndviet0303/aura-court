export type User = { id: number; name: string; email: string; role: 'customer' | 'admin'; email_verified_at?: string | null }
export type CourtBlock = { id: number; court_id: number; starts_at: string; ends_at: string; reason?: string }
export type Court = { id: number; name: string; description?: string; image_url?: string; is_active?: boolean; price_slots?: PriceSlot[]; blocks?: CourtBlock[] }
export type PriceSlot = { id?: number; court_id?: number; weekday: number; starts_at: string; ends_at: string; price: number; is_active?: boolean }
export type AvailabilitySlot = { startsAt: string; endsAt: string; price: number; available: boolean }
export type Booking = { id: number; code: string; status: string; total_amount: number; payment_expires_at?: string; court: Court; slots: { id?: number; starts_at: string; ends_at: string; unit_price: number }[] }
export type PaymentInfo = { code: string; amount: number; bank: string; accountNumber: string; qrUrl: string; expiresAt: string }
export type Refund = { id: number; amount: number; status: string; note?: string; booking: Booking & { user?: User } }
