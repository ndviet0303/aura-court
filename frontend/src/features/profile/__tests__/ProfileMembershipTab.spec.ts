import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import ProfileMembershipTab from '../components/ProfileMembershipTab.vue'
import { i18n } from '@/i18n'
import type { Membership } from '@/core/types/common'

const activeMembership: Membership = {
  id: 7,
  starts_at: '2026-10-01T00:00:00+07:00',
  ends_at: '2026-11-30T00:00:00+07:00',
  status: 'active',
  plan: { name: 'Gold Elite All-Access', discount_percent: 15 },
}

function mountTab(membership: Membership | null) {
  return mount(ProfileMembershipTab, {
    props: { membership },
    global: { plugins: [i18n], stubs: { RouterLink: true } },
  })
}

describe('ProfileMembershipTab', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('shows the active plan name, its entitled discount and expiry', () => {
    const card = mountTab(activeMembership).find('.tier-highlight-card')

    expect(card.find('.tier-title').text()).toBe('Gold Elite All-Access')
    expect(card.find('.discount-value').text()).toBe('-15%')
    expect(card.find('.discount-label').text()).toBe(i18n.global.t('profile.vipDiscount'))
    expect(card.find('.status-active-pill').exists()).toBe(true)
    expect(card.find('.expiry-text').text()).toContain(i18n.global.t('profile.vipExpires'))
    expect(card.text()).not.toContain('1,250')
  })

  it('hides the discount callout when the plan grants no discount', () => {
    const card = mountTab({
      ...activeMembership,
      plan: { name: 'Basic Pass', discount_percent: 0 },
    }).find('.tier-highlight-card')

    expect(card.find('.tier-title').text()).toBe('Basic Pass')
    expect(card.find('.discount-callout').exists()).toBe(false)
  })

  it('shows the no-membership state instead of a placeholder tier', () => {
    const card = mountTab(null).find('.tier-highlight-card')

    expect(card.text()).toBe(i18n.global.t('profile.vipNone'))
    expect(card.find('.tier-title').exists()).toBe(false)
    expect(card.find('.discount-callout').exists()).toBe(false)
    expect(card.find('.status-active-pill').exists()).toBe(false)
  })
})
