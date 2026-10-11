import { beforeEach, describe, expect, it, vi } from 'vitest'
import { api } from '@/core/api/client'
import { platformSubscriptionApi } from '../api/platformSubscriptionApi'

vi.mock('@/core/api/client', () => ({ api: { get: vi.fn(), post: vi.fn() } }))

describe('platformSubscriptionApi', () => {
  beforeEach(() => vi.clearAllMocks())

  it('uses backend routes and plan/version payload names', async () => {
    await platformSubscriptionApi.getPlans()
    await platformSubscriptionApi.getCurrentSubscription(42)
    await platformSubscriptionApi.selectPlan(42, { plan_id: 7, accepted_terms_version: '2026.3' })
    await platformSubscriptionApi.activateTrial(42, {
      plan_id: 7,
      accepted_terms_version: '2026.3',
    })
    await platformSubscriptionApi.initiatePayment(42, {
      plan_id: 7,
      accepted_terms_version: '2026.3',
      return_url: 'https://app.example/subscription?payment_return=1',
      cancel_url: 'https://app.example/subscription?payment_cancelled=1',
    })
    await platformSubscriptionApi.renew(42, {
      return_url: 'https://app.example/subscription?payment_return=1',
      cancel_url: 'https://app.example/subscription?payment_cancelled=1',
    })

    expect(api.get).toHaveBeenNthCalledWith(1, '/platform/plans')
    expect(api.get).toHaveBeenNthCalledWith(2, '/partner/organizations/42/subscription/current')
    expect(api.post).toHaveBeenNthCalledWith(
      1,
      '/partner/organizations/42/subscription/select-plan',
      {
        plan_id: 7,
        accepted_terms_version: '2026.3',
      },
    )
    expect(api.post).toHaveBeenNthCalledWith(
      2,
      '/partner/organizations/42/subscription/activate-trial',
      {
        plan_id: 7,
        accepted_terms_version: '2026.3',
      },
    )
    expect(api.post).toHaveBeenNthCalledWith(
      3,
      '/partner/organizations/42/subscription/initiate-payment',
      {
        plan_id: 7,
        accepted_terms_version: '2026.3',
        return_url: 'https://app.example/subscription?payment_return=1',
        cancel_url: 'https://app.example/subscription?payment_cancelled=1',
      },
    )
    expect(api.post).toHaveBeenNthCalledWith(4, '/partner/organizations/42/subscription/renew', {
      return_url: 'https://app.example/subscription?payment_return=1',
      cancel_url: 'https://app.example/subscription?payment_cancelled=1',
    })
  })
})
