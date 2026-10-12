import { afterAll, beforeAll, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import CycleOccurrencesTable from '../components/cycles/CycleOccurrencesTable.vue'
import type { OccurrenceItem } from '../types/finance'

const occurrence = (id: number, starts_at: string, ends_at: string): OccurrenceItem => ({
  id,
  recurring_series_id: 1,
  sequence: id,
  starts_at,
  ends_at,
  amount: 200000,
  status: 'confirmed',
})

describe('CycleOccurrencesTable', () => {
  beforeAll(() => {
    vi.stubEnv('TZ', 'UTC')
  })

  afterAll(() => {
    vi.unstubAllEnvs()
  })

  it('shows session times and dates on the venue clock', () => {
    const wrapper = mount(CycleOccurrencesTable, {
      props: {
        occurrences: [
          // The API sends UTC instants: 23:00Z on Nov 1 is 06:00 on Nov 2 in Vietnam.
          occurrence(1, '2026-11-01T23:00:00.000000Z', '2026-11-02T01:00:00.000000Z'),
          // A missing end falls back to a 2h session.
          occurrence(2, '2026-11-08T23:00:00.000000Z', ''),
        ],
      },
    })

    const ranges = wrapper.findAll('.time-range').map((range) => range.text())
    expect(ranges[0]).toContain('06:00 ➔ 08:00')
    expect(ranges[1]).toContain('06:00 ➔ 08:00')
    expect(wrapper.findAll('.date-text')[0]!.text()).toContain('02/11/2026')
  })
})
