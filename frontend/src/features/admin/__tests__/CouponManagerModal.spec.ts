import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import { i18n } from '@/i18n'
import type { Court, Venue } from '@/features/court/types/court'
import type { Coupon } from '@/features/booking/types/booking'
import CouponManagerModal from '../components/manage/CouponManagerModal.vue'

describe('CouponManagerModal', () => {
  it('creates a campaign coupon with its built-in customer group and limits', async () => {
    const venues: Venue[] = [{ id: 3, name: 'West Club', slug: 'west-club', address: null, google_maps_url: null, is_active: true }]
    const courts: Court[] = [{
      id: 8,
      name: 'Court 8',
      slug: 'court-8',
      venue_id: 3,
      venue: venues[0]!,
      is_active: true,
    }]
    const wrapper = mount(CouponManagerModal, {
      props: { show: true, coupons: [], venues, courts },
      global: { plugins: [i18n] },
    })

    await wrapper.get('[data-testid="promotion-campaign-name"]').setValue('First visit')
    await wrapper.get('[data-testid="promotion-coupon-code"]').setValue('first10')
    await wrapper.get('[data-testid="promotion-customer-group"]').setValue('first_time_customers')
    await wrapper.get('[data-testid="promotion-venue-scope"]').setValue('3')
    await wrapper.get('[data-testid="promotion-court-scope"]').setValue('8')
    await wrapper.get('[data-testid="promotion-usage-limit"]').setValue('3')
    await wrapper.get('[data-testid="promotion-save"]').trigger('click')

    expect(wrapper.emitted('createCoupon')?.[0]?.[0]).toMatchObject({
      code: 'FIRST10',
      campaign_name: 'First visit',
      eligibility_group: 'first_time_customers',
      campaign_usage_limit: 3,
      starts_at: null,
      ends_at: null,
      venue_id: 3,
      court_id: 8,
      campaign_venue_id: 3,
      campaign_court_id: 8,
    })
  })

  it('edits a coupon and emits lifecycle actions for its coupon and campaign', async () => {
    const coupon: Coupon = {
      id: 7,
      code: 'RETURN10',
      status: 'draft',
      discount_type: 'percent',
      discount_value: 10,
      usage_limit: 8,
      is_active: false,
      version: 1,
      campaign: {
        id: 5,
        organization_id: 2,
        name: 'Returning players',
        status: 'draft',
        eligibility_group: 'returning_customers',
        version: 1,
      },
    }
    const wrapper = mount(CouponManagerModal, {
      props: { show: true, coupons: [coupon] },
      global: { plugins: [i18n] },
    })

    await wrapper.get('[data-testid="promotion-edit-7"]').trigger('click')
    await wrapper.get('[data-testid="promotion-coupon-code"]').setValue('return15')
    await wrapper.get('[data-testid="promotion-save"]').trigger('click')
    expect(wrapper.emitted('updateCoupon')?.[0]).toMatchObject([7, { code: 'RETURN15' }])

    await wrapper.get('[data-testid="promotion-activate-coupon-7"]').trigger('click')
    await wrapper.get('[data-testid="promotion-activate-campaign-5"]').trigger('click')
    expect(wrapper.emitted('transitionCoupon')?.[0]).toEqual([coupon, 'activate'])
    expect(wrapper.emitted('transitionCampaign')?.[0]).toEqual([5, 'activate'])
  })
})
