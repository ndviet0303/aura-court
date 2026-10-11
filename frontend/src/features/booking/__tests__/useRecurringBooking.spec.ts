import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { useRecurringBooking } from '../composables/useRecurringBooking'
import { bookingApi } from '../api/bookingApi'
import type { RecurringSeries, RecurringSeriesPreview, RecurringStatus } from '../types/recurring'

vi.mock('@/core/api/client', () => ({
  idempotencyKey: () => 'test-key',
}))

vi.mock('@/core/utils/errorMessage', () => ({
  errorMessage: (err: unknown, fallback: string) =>
    err && typeof (err as { message?: unknown }).message === 'string'
      ? (err as { message: string }).message
      : fallback,
}))

vi.mock('../api/bookingApi', () => ({
  bookingApi: {
    getCourts: vi.fn(),
    getRecurringSeries: vi.fn(),
    previewRecurringSeries: vi.fn(),
    createRecurringSeries: vi.fn(),
  },
}))

function previewFixture(): RecurringSeriesPreview {
  return {
    frequency: 'weekly',
    weekdays: [1, 3],
    count: 2,
    currency: 'VND',
    total_amount: 300000,
    truncated_by: null,
    occurrences: [
      {
        index: 1,
        starts_at: '2026-10-05T11:00:00Z',
        ends_at: '2026-10-05T13:00:00Z',
        weekday: 1,
        price: 150000,
        conflict: false,
        conflict_reason: null,
      },
      {
        index: 2,
        starts_at: '2026-10-07T11:00:00Z',
        ends_at: '2026-10-07T13:00:00Z',
        weekday: 3,
        price: 150000,
        conflict: false,
        conflict_reason: null,
      },
    ],
  }
}

function seriesResult(status: RecurringStatus, total: number): { series: RecurringSeries } {
  return {
    series: {
      id: 1,
      court_id: 1,
      frequency: 'weekly',
      starts_at: '2026-10-05T11:00:00Z',
      ends_at: '2026-10-05T13:00:00Z',
      status,
      total_amount: total,
    },
  }
}

describe('useRecurringBooking submit', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    vi.clearAllMocks()
    vi.mocked(bookingApi.getCourts).mockResolvedValue([])
    vi.mocked(bookingApi.getRecurringSeries).mockResolvedValue({ data: [] })
    vi.mocked(bookingApi.previewRecurringSeries).mockResolvedValue(previewFixture())
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('submits the accepted preview snapshot and reports a confirmed series', async () => {
    vi.mocked(bookingApi.createRecurringSeries).mockResolvedValue(seriesResult('confirmed', 300000))
    const rb = useRecurringBooking()
    rb.preview.value = previewFixture()

    const outcome = await rb.submitSeries()

    expect(outcome).toEqual({ kind: 'confirmed' })
    expect(bookingApi.createRecurringSeries).toHaveBeenCalledWith(
      expect.objectContaining({
        accept_partial: false,
        accepted: {
          total: 300000,
          occurrences: [
            { starts_at: '2026-10-05T11:00:00Z', price: 150000 },
            { starts_at: '2026-10-07T11:00:00Z', price: 150000 },
          ],
        },
      }),
      'test-key',
    )
    expect(rb.activeTab.value).toBe('series')
  })

  it('reports a pending series when confirmation awaits per-period payment', async () => {
    vi.mocked(bookingApi.createRecurringSeries).mockResolvedValue(seriesResult('pending', 300000))
    const rb = useRecurringBooking()
    rb.preview.value = previewFixture()

    expect(await rb.submitSeries()).toEqual({ kind: 'pending' })
  })

  it('surfaces a partial conflict, then holds the remainder on explicit accept', async () => {
    const partial = {
      status: 'partial_conflict' as const,
      currency: 'VND',
      held_count: 1,
      revised_total: 150000,
      excluded: [
        {
          index: 2,
          starts_at: '2026-10-07T11:00:00Z',
          ends_at: '2026-10-07T13:00:00Z',
          conflict_reason: 'Đã có người đặt',
        },
      ],
    }
    vi.mocked(bookingApi.createRecurringSeries)
      .mockResolvedValueOnce(partial)
      .mockResolvedValueOnce(seriesResult('pending', 150000))

    const rb = useRecurringBooking()
    rb.preview.value = previewFixture()

    const first = await rb.submitSeries()
    expect(first).toEqual({ kind: 'partial', conflict: partial })
    expect(rb.partialConflict.value).toEqual(partial)
    expect(rb.activeTab.value).toBe('create')

    const second = await rb.submitSeries(true)
    expect(second).toEqual({ kind: 'pending' })
    expect(rb.partialConflict.value).toBeNull()
    expect(bookingApi.createRecurringSeries).toHaveBeenNthCalledWith(
      2,
      expect.objectContaining({ accept_partial: true }),
      'test-key',
    )
  })

  it('treats a 409 drift as a re-preview prompt', async () => {
    vi.mocked(bookingApi.createRecurringSeries).mockRejectedValue({
      status: 409,
      message: 'Giá đã thay đổi so với bản xem trước; vui lòng xem trước lại.',
    })
    const rb = useRecurringBooking()
    rb.preview.value = previewFixture()
    vi.mocked(bookingApi.previewRecurringSeries).mockClear()

    const outcome = await rb.submitSeries()

    expect(outcome).toEqual({ kind: 'drift' })
    expect(rb.partialConflict.value).toBeNull()
    expect(bookingApi.previewRecurringSeries).toHaveBeenCalled()
  })

  it('refuses to submit before a preview is available', async () => {
    const rb = useRecurringBooking()
    rb.preview.value = null

    expect(await rb.submitSeries()).toEqual({ kind: 'invalid', reason: 'preview' })
    expect(bookingApi.createRecurringSeries).not.toHaveBeenCalled()
  })
})
