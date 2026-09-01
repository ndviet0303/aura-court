export interface SplitMember {
  id: number
  name: string
  phone: string
  role: 'host' | 'member'
  amount: number
  paid: boolean
  method?: string
  paidAt?: string
  avatar?: string
  timeMinutes: number
  customPercent?: number
  customAmount?: number
  assignedItemIds?: string[]
}

export interface SplitItemDetail {
  id: string
  name: string
  category: 'court' | 'drink' | 'gear' | 'service'
  qty: number
  unitPrice: number
  total: number
  assignedMemberIds: number[] // empty means split evenly across all
}

export type SplitMode = 'equal' | 'itemized' | 'time' | 'custom'

export interface SplitBillBookingInfo {
  bookingCode: string
  courtName: string
  venueName: string
  address: string
  bookingDate: string
  bookingTime: string
  durationHours: number
  bankName: string
  bankBin: string
  accountNumber: string
  accountName: string
  hostName: string
  hostPhone: string
  status: 'pending' | 'partially_paid' | 'completed'
}
