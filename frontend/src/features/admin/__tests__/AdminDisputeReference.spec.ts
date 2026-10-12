import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import DisputeDetailWorkbench from '../components/disputes/DisputeDetailWorkbench.vue'
import { disputeApi } from '../api/disputeApi'
import { i18n } from '@/i18n'
import { apiV2 } from '@/core/api/client'

vi.mock('@/core/api/client', () => ({
  api: { get: vi.fn(), post: vi.fn(), patch: vi.fn(), delete: vi.fn() },
  apiV2: { get: vi.fn(), post: vi.fn(), patch: vi.fn(), delete: vi.fn() },
  errorMessage: (_err: unknown, fallback: string) => fallback,
}))

async function referenceOf(raw: Record<string, unknown>) {
  vi.mocked(apiV2.get).mockResolvedValue({ data: { id: 5, dispute_code: 'DSP-5', ...raw } })
  const item = await disputeApi.getDisputeDetail(5)
  const wrapper = mount(DisputeDetailWorkbench, {
    props: { item: item!, activeEvidenceTab: 'logs' },
    global: { plugins: [i18n] },
  })
  return { item: item!, ref: wrapper.find('.reference-tag').text() }
}

describe('Admin dispute reference', () => {
  beforeEach(() => vi.clearAllMocks())

  it('maps and shows the reservation reference when present', async () => {
    const { item, ref } = await referenceOf({
      reservation_id: 9,
      reservation_code: 'BK-OLD9',
      booking_id: 42,
    })

    expect(item.reservationId).toBe(9)
    expect(item.reservationCode).toBe('BK-OLD9')
    expect(ref).toContain('BK-OLD9')
    expect(ref).not.toContain('BK-42')
  })

  // The server applies the display rule (legacy_code ?? code) to reservation_code; the UI never rewrites it.
  it('shows reservation_code verbatim as sent by the server', async () => {
    const { item, ref } = await referenceOf({
      reservation_id: 11,
      reservation_code: 'RSV-20261011-7XK2',
      booking_id: null,
    })

    expect(item.reservationCode).toBe('RSV-20261011-7XK2')
    expect(ref).toContain('RSV-20261011-7XK2')
  })

  it('falls back to the historical booking reference', async () => {
    const { item, ref } = await referenceOf({
      reservation_id: null,
      reservation_code: null,
      booking_id: 42,
    })

    expect(item.reservationCode).toBeUndefined()
    expect(ref).toContain('BK-42')
  })
})
