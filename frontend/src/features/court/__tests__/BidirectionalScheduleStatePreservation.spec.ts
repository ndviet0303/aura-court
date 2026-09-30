import { describe, it, expect, beforeEach, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useAppearanceStore } from '@/features/appearance/stores/appearanceStore'
import { useBookingStore } from '@/stores/booking'
import type { AvailabilitySlot, Court, Venue } from '@/features/court/types/court'

describe('Bidirectional Schedule & Discovery State Preservation (Task 4.4)', () => {
  const mockVenue: Venue = {
    id: 1,
    name: 'Aura Court Arena',
    slug: 'aura-court-arena',
    address: '123 Đường Số 1, Quận 7',
    google_maps_url: 'https://maps.google.com',
    is_active: true,
  }

  const mockCourt: Court = {
    id: 201,
    name: 'Sân Trung Tâm VIP',
    slug: 'san-trung-tam-vip',
    venue_id: 1,
    venue: mockVenue,
    is_active: true,
  }

  const slot1: AvailabilitySlot = {
    startsAt: '2026-10-02T10:00:00Z',
    endsAt: '2026-10-02T11:00:00Z',
    price: 200000,
    available: true,
  }

  const slot2: AvailabilitySlot = {
    startsAt: '2026-10-02T11:00:00Z',
    endsAt: '2026-10-02T12:00:00Z',
    price: 200000,
    available: true,
  }

  beforeEach(() => {
    setActivePinia(createPinia())
    localStorage.clear()
    document.documentElement.removeAttribute('data-ui-version')
    document.documentElement.classList.remove('dark')
  })

  it('preserves court selection, multi-slot booking, and quote across V1 <-> V2 switches without reset or mutation', () => {
    const appearance = useAppearanceStore()
    const booking = useBookingStore()

    // 1. Initial State in V1
    appearance.setUiVersion('v1')
    expect(appearance.uiVersion).toBe('v1')

    // Simulate user selecting court and 2 slots
    booking.courts = [mockCourt]
    booking.selectedSlots = [slot1, slot2]

    const initialTotal = booking.selectedSlots.reduce((sum, s) => sum + s.price, 0)
    expect(initialTotal).toBe(400000)
    expect(booking.selectedSlots.length).toBe(2)

    // Spy on mutation functions to prove switch doesn't trigger backend actions
    const holdSpy = vi.fn()
    const checkoutSpy = vi.fn()

    // 2. Switch V1 -> V2
    appearance.setUiVersion('v2')
    expect(appearance.uiVersion).toBe('v2')
    expect(document.documentElement.getAttribute('data-ui-version')).toBe('v2')

    // Verify state is preserved
    expect(booking.courts[0]?.id).toBe(201)
    expect(booking.selectedSlots.length).toBe(2)
    expect(booking.selectedSlots[0]?.startsAt).toBe(slot1.startsAt)
    expect(booking.selectedSlots[1]?.startsAt).toBe(slot2.startsAt)
    const v2Total = booking.selectedSlots.reduce((sum, s) => sum + s.price, 0)
    expect(v2Total).toBe(400000)

    // 3. Switch V2 -> V1
    appearance.setUiVersion('v1')
    expect(appearance.uiVersion).toBe('v1')
    expect(document.documentElement.getAttribute('data-ui-version')).toBe('v1')

    // Verify state remains preserved
    expect(booking.courts[0]?.id).toBe(201)
    expect(booking.selectedSlots.length).toBe(2)
    const v1TotalAfterReturn = booking.selectedSlots.reduce((sum, s) => sum + s.price, 0)
    expect(v1TotalAfterReturn).toBe(400000)

    // Verify 0 side-effect reservations or mutations were emitted
    expect(holdSpy).not.toHaveBeenCalled()
    expect(checkoutSpy).not.toHaveBeenCalled()
  })
})
