import { describe, expect, it } from 'vitest'
import type { TransferCandidate } from '../../types/transfer'
import { itemRefusals, priceProblem, remainingInGroup } from '../transfer'

const candidate = (id: number, extra: Partial<TransferCandidate> = {}): TransferCandidate => ({
  reservation_id: id,
  code: `R${id}`,
  venue: { id: 1, name: 'Aura', address: null },
  court: { id: 1, name: 'Sân 1', image_url: null },
  starts_at: '2026-11-01T18:00:00+07:00',
  ends_at: '2026-11-01T19:00:00+07:00',
  paid_amount: 200_000,
  max_price: 200_000,
  recurring_group: null,
  eligible: true,
  refusal: null,
  ...extra,
})

describe('priceProblem (BR-181)', () => {
  it('accepts a whole price up to the paid amount, free included', () => {
    expect(priceProblem(200_000, 200_000)).toBeNull()
    expect(priceProblem(0, 200_000)).toBeNull()
  })

  it('refuses a missing, fractional, negative or over-cap price', () => {
    expect(priceProblem(null, 200_000)).toBe('required')
    expect(priceProblem(Number.NaN, 200_000)).toBe('required')
    expect(priceProblem(1.5, 200_000)).toBe('invalid')
    expect(priceProblem(-1, 200_000)).toBe('invalid')
    expect(priceProblem(200_001, 200_000)).toBe('overCap')
  })
})

describe('remainingInGroup (BR-179)', () => {
  it('selects only the eligible bookings of the same recurring submission', () => {
    const list = [
      candidate(1, { recurring_group: 'g1' }),
      candidate(2, { recurring_group: 'g1', eligible: false }),
      candidate(3, { recurring_group: 'g2' }),
      candidate(4, { recurring_group: 'g1' }),
      candidate(5),
    ]
    expect(remainingInGroup(list, 'g1')).toEqual([1, 4])
  })
})

describe('itemRefusals', () => {
  it('maps the 422 items by reservation id', () => {
    const error = {
      code: 'TRANSFER_REFUSED',
      items: [{ reservation_id: 7, code: 'TRANSFER_CUTOFF', message: 'Đã đến mốc' }],
    }
    expect(itemRefusals(error)).toEqual({ 7: 'Đã đến mốc' })
  })

  it('is empty for a failure without items', () => {
    expect(itemRefusals({ message: 'x' })).toEqual({})
    expect(itemRefusals(null)).toEqual({})
  })
})
