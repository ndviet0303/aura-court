import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { i18n } from '@/i18n'
import ReconciliationHealthAnalytics from '../components/reconciliation/ReconciliationHealthAnalytics.vue'
import ReconciliationKpiCards from '../components/reconciliation/ReconciliationKpiCards.vue'
import type { HealthTrendPoint, ReconciliationStats } from '../types/reconciliation'

const point = (date: string, status: string | null): HealthTrendPoint => ({
  date,
  status,
  provider_total: 0,
  journal_total: 0,
  settlement_total: 0,
  mismatch_amount: 0,
})

describe('Reconciliation analytics', () => {
  it('leaves days without a run empty and hides the match rate when there are no runs', () => {
    const wrapper = mount(ReconciliationHealthAnalytics, {
      props: {
        healthTrend: [point('2026-10-11', 'matched'), point('2026-10-12', null)],
        matchRate: null,
      },
      global: { plugins: [i18n] },
    })

    expect(wrapper.findAll('.bar-fill')).toHaveLength(1)
    expect(wrapper.find('.rate-badge').exists()).toBe(false)
    expect(wrapper.text()).toContain('Chưa có phiên đối soát')
  })

  it('shows the settlement variance without a made-up held-transaction count', () => {
    const stats: ReconciliationStats = {
      provider_total: 10000000,
      journal_total: 10000000,
      settlement_total: 8600000,
      payout_total: 0,
      mismatch_total: 1400000,
      open_alerts_count: 0,
      match_rate: null,
      last_reconciled_at: null,
      sync_status: 'synced',
      health_trend: [],
    }
    const wrapper = mount(ReconciliationKpiCards, {
      props: { stats },
      global: { plugins: [i18n] },
    })

    expect(wrapper.text()).toContain('Chênh lệch: 1.400.000')
    expect(wrapper.text()).not.toContain('Treo')
    expect(wrapper.findAll('.card-footer')).toHaveLength(1)
  })
})
