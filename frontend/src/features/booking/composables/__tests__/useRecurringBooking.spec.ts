import { nextTick } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { RecurringBookingPayload } from '../../types/recurring'

const bookingApi = vi.hoisted(() => ({
  getCourts: vi.fn(),
  checkRecurringBookings: vi.fn(),
  submitRecurringBookings: vi.fn(),
}))
vi.mock('../../api/bookingApi', () => ({ bookingApi }))

import { useRecurringBooking } from '../useRecurringBooking'

const okRow = (o: RecurringBookingPayload['occurrences'][number]) => ({
  starts_at: o.starts_at,
  ends_at: o.ends_at,
  ok: true,
  refusal: null,
  rental: 100000,
  vouchers: [],
  code_voucher: null,
  code_refusal: null,
  reservation: null,
})

async function planned(dates: string[]) {
  const booking = useRecurringBooking()
  booking.courtId.value = 7
  booking.mode.value = 'dates'
  booking.dates.value = dates
  await nextTick()
  return booking
}

const sentDays = (call: unknown[]) =>
  (call[0] as RecurringBookingPayload).occurrences.map((o) => o.starts_at.slice(0, 10))

describe('useRecurringBooking (UC-8.4..UC-8.7)', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    bookingApi.checkRecurringBookings.mockImplementation(async (p: RecurringBookingPayload) => ({
      data: p.occurrences.map(okRow),
    }))
    bookingApi.submitRecurringBookings.mockImplementation(async (p: RecurringBookingPayload) => ({
      data: p.occurrences.map(okRow),
    }))
  })

  it('checks every occurrence once the common time is applied (UC-8.4 steps 6-7)', async () => {
    const booking = await planned(['2099-01-05', '2099-01-06'])

    booking.requestApply()
    await vi.waitFor(() => expect(booking.ready.value).toHaveLength(2))

    expect(bookingApi.checkRecurringBookings).toHaveBeenCalledTimes(1)
  })

  it('asks before the common time overwrites an occurrence with its own time (UC-8.4 A-2)', async () => {
    const booking = await planned(['2099-01-05', '2099-01-06'])
    const [first] = booking.rows.value
    booking.setTime(first!, '06:00', '07:00')

    booking.requestApply()

    expect(booking.confirmingApply.value).toBe(true)
    expect(first!.start).toBe('06:00')
    booking.applyTime()
    expect([first!.start, first!.custom, booking.confirmingApply.value]).toEqual([
      booking.start.value,
      false,
      false,
    ])
  })

  it('submits only the picked occurrences (UC-8.7 A-1)', async () => {
    const booking = await planned(['2099-01-05', '2099-01-06', '2099-01-07'])
    booking.requestApply()
    await vi.waitFor(() => expect(booking.ready.value).toHaveLength(3))

    booking.setSelected(booking.rows.value[1]!, false)
    await booking.submit()

    expect(sentDays(bookingApi.submitRecurringBookings.mock.calls[0]!)).toEqual([
      '2099-01-05',
      '2099-01-07',
    ])
    expect(booking.rows.value[1]!.booked).toBeNull()
  })

  it('checks the occurrences naming one code together (UC-8.6 E-3)', async () => {
    const booking = await planned(['2099-01-05', '2099-01-06', '2099-01-07'])
    booking.requestApply()
    await vi.waitFor(() => expect(booking.ready.value).toHaveLength(3))
    const [first, second] = booking.rows.value

    await booking.setCode(first!, 'RIENG1')
    await booking.setCode(second!, ' rieng1')

    expect(sentDays(bookingApi.checkRecurringBookings.mock.lastCall!)).toEqual([
      '2099-01-05',
      '2099-01-06',
    ])
  })
})
