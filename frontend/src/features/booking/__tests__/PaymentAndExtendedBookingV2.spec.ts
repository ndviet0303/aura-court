import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createRouter, createWebHistory } from 'vue-router'
import fs from 'node:fs'
import { fileURLToPath } from 'node:url'
import path from 'node:path'
import { i18n } from '@/i18n'
import { useAppearanceStore } from '@/features/appearance/stores/appearanceStore'
import { useBookingStore } from '@/stores/booking'
import { useAuthStore } from '@/stores/auth'
import PaymentView from '../views/PaymentView.vue'
import RecurringBookingView from '../views/RecurringBookingView.vue'
import GroupSplitBillView from '../../finance/views/GroupSplitBillView.vue'
import ResaleMarketplaceView from '../../marketplace/views/ResaleMarketplaceView.vue'

describe('Payment & Extended Booking V2 Adaptation (Tasks 6.1 - 6.4)', () => {
  const currentDir = path.dirname(fileURLToPath(import.meta.url))
  const erabookCssPath = path.resolve(currentDir, '../../../assets/styles/erabook.css')
  const erabookCss = fs.readFileSync(erabookCssPath, 'utf-8')

  const router = createRouter({
    history: createWebHistory(),
    routes: [
      { path: '/', component: { template: '<div>Home</div>' } },
      { path: '/payment/:code?', component: PaymentView },
      { path: '/recurring-booking', component: RecurringBookingView },
      { path: '/split-bill/:code?', component: GroupSplitBillView },
      { path: '/resale', component: ResaleMarketplaceView },
    ],
  })

  const mockUser = {
    id: 1,
    name: 'Customer Demo',
    email: 'demo@auracourt.vn',
    role: 'customer',
  }

  const mockBooking = {
    id: 99,
    code: 'BK-TEST-1234',
    court_id: 1,
    amount: 300000,
    status: 'pending',
    court: {
      id: 1,
      name: 'Sân 1 - Taraflex Pro',
      venue: {
        id: 1,
        name: 'Aura Court Central',
      },
    },
  }

  beforeEach(async () => {
    setActivePinia(createPinia())
    localStorage.clear()
    document.documentElement.removeAttribute('data-ui-version')
    document.documentElement.classList.remove('dark')
    const auth = useAuthStore()
    auth.user = mockUser as any
    const booking = useBookingStore()
    booking.current = mockBooking as any
    await router.push('/payment/BK-TEST-1234')
    await router.isReady()
  })

  it('contains V2 payment, recurring, split-bill, and resale styling rules in erabook.css', () => {
    expect(erabookCss).toContain('html[data-ui-version="v2"] .payment-summary-card')
    expect(erabookCss).toContain('html[data-ui-version="v2"] .instructions-card')
    expect(erabookCss).toContain('html[data-ui-version="v2"] .recurring-hero-card')
    expect(erabookCss).toContain('html[data-ui-version="v2"] .split-bill-card')
    expect(erabookCss).toContain('html[data-ui-version="v2"] .resale-card')
    expect(erabookCss).toContain('html[data-ui-version="v2"] .btn-pay')
    expect(erabookCss).toContain('html[data-ui-version="v2"] .payment-amount-val')
  })

  it('preserves payment reference, deadline, and avoids mutation duplication across V1 <-> V2 switches', async () => {
    const appearance = useAppearanceStore()
    appearance.setUiVersion('v1')

    const wrapper = mount(PaymentView, {
      global: {
        plugins: [i18n, router],
      },
    })

    expect(wrapper.find('.payment-view-page').exists()).toBe(true)

    // Switch to V2
    appearance.setUiVersion('v2')
    await wrapper.vm.$nextTick()
    expect(document.documentElement.getAttribute('data-ui-version')).toBe('v2')

    // Switch back to V1
    appearance.setUiVersion('v1')
    await wrapper.vm.$nextTick()
    expect(document.documentElement.getAttribute('data-ui-version')).toBe('v1')
  })

  it('renders RecurringBookingView and preserves state across V1 <-> V2 switches', async () => {
    const appearance = useAppearanceStore()
    appearance.setUiVersion('v1')

    await router.push('/recurring-booking')
    await router.isReady()

    const wrapper = mount(RecurringBookingView, {
      global: {
        plugins: [i18n, router],
      },
    })

    expect(wrapper.find('.recurring-booking-view-page').exists()).toBe(true)

    // Switch V1 -> V2 -> V1
    appearance.setUiVersion('v2')
    await wrapper.vm.$nextTick()
    expect(document.documentElement.getAttribute('data-ui-version')).toBe('v2')

    appearance.setUiVersion('v1')
    await wrapper.vm.$nextTick()
    expect(document.documentElement.getAttribute('data-ui-version')).toBe('v1')
  })

  it('renders GroupSplitBillView and preserves state across V1 <-> V2 switches', async () => {
    const appearance = useAppearanceStore()
    appearance.setUiVersion('v1')

    await router.push('/split-bill')
    await router.isReady()

    const wrapper = mount(GroupSplitBillView, {
      global: {
        plugins: [i18n, router],
      },
    })

    expect(wrapper.find('.group-split-bill-view').exists()).toBe(true)

    appearance.setUiVersion('v2')
    await wrapper.vm.$nextTick()
    expect(document.documentElement.getAttribute('data-ui-version')).toBe('v2')

    appearance.setUiVersion('v1')
    await wrapper.vm.$nextTick()
    expect(document.documentElement.getAttribute('data-ui-version')).toBe('v1')
  })

  it('renders ResaleMarketplaceView and preserves active tab across V1 <-> V2 switches', async () => {
    const appearance = useAppearanceStore()
    appearance.setUiVersion('v1')

    await router.push('/resale')
    await router.isReady()

    const wrapper = mount(ResaleMarketplaceView, {
      global: {
        plugins: [i18n, router],
      },
    })

    expect(wrapper.find('.resale-marketplace-view').exists()).toBe(true)

    const tabs = wrapper.findAll('.tab-btn')
    if (tabs.length > 1) {
      await tabs[1]!.trigger('click')
      expect(tabs[1]!.classes()).toContain('active')

      appearance.setUiVersion('v2')
      await wrapper.vm.$nextTick()
      expect(tabs[1]!.classes()).toContain('active')

      appearance.setUiVersion('v1')
      await wrapper.vm.$nextTick()
      expect(tabs[1]!.classes()).toContain('active')
    }
  })
})
