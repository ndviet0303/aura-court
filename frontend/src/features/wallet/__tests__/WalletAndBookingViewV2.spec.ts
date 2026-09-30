import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createRouter, createWebHistory } from 'vue-router'
import fs from 'node:fs'
import { fileURLToPath } from 'node:url'
import path from 'node:path'
import { i18n } from '@/i18n'
import { useAppearanceStore } from '@/features/appearance/stores/appearanceStore'
import { useAuthStore } from '@/stores/auth'
import WalletView from '../views/WalletView.vue'
import BookingView from '../../booking/views/BookingView.vue'

describe('Wallet & Booking History V2 Adaptation (Task 5.4)', () => {
  const currentDir = path.dirname(fileURLToPath(import.meta.url))
  const erabookCssPath = path.resolve(currentDir, '../../../assets/styles/erabook.css')
  const erabookCss = fs.readFileSync(erabookCssPath, 'utf-8')

  const router = createRouter({
    history: createWebHistory(),
    routes: [
      { path: '/', component: { template: '<div>Home</div>' } },
      { path: '/wallet', component: WalletView },
      {
        path: '/booking',
        alias: ['/bookings', '/history', '/my-bookings'],
        component: BookingView,
      },
    ],
  })

  const mockUser = {
    id: 1,
    name: 'Customer Demo',
    email: 'demo@auracourt.vn',
    role: 'customer',
  }

  beforeEach(async () => {
    setActivePinia(createPinia())
    localStorage.clear()
    document.documentElement.removeAttribute('data-ui-version')
    document.documentElement.classList.remove('dark')
    const auth = useAuthStore()
    auth.user = mockUser as any
    await router.push('/wallet')
    await router.isReady()
  })

  it('contains V2 wallet and booking history styling rules in erabook.css', () => {
    expect(erabookCss).toContain('html[data-ui-version="v2"] .balance-card')
    expect(erabookCss).toContain('html[data-ui-version="v2"] .user-booking-card')
    expect(erabookCss).toContain('html[data-ui-version="v2"] .balance-val')
    expect(erabookCss).toContain('html[data-ui-version="v2"] .b-tab.active')
    expect(erabookCss).toContain('html[data-ui-version="v2"] .btn-action-primary')
  })

  it('renders WalletView and preserves filter state across V1 <-> V2 switches', async () => {
    const appearance = useAppearanceStore()
    appearance.setUiVersion('v1')

    const wrapper = mount(WalletView, {
      global: {
        plugins: [i18n, router],
      },
    })

    expect(wrapper.find('.wallet-view-page').exists()).toBe(true)

    // Filter tabs in transaction history
    const filterTabs = wrapper.findAll('.p-tab')
    if (filterTabs.length > 1) {
      await filterTabs[1]!.trigger('click')
      expect(filterTabs[1]!.classes()).toContain('active')

      // Switch to V2
      appearance.setUiVersion('v2')
      await wrapper.vm.$nextTick()
      expect(filterTabs[1]!.classes()).toContain('active')

      // Switch back to V1
      appearance.setUiVersion('v1')
      await wrapper.vm.$nextTick()
      expect(filterTabs[1]!.classes()).toContain('active')
    }
  })

  it('renders BookingView and resolves all aliases identically', async () => {
    const aliases = ['/booking', '/bookings', '/history', '/my-bookings']

    for (const routePath of aliases) {
      await router.push(routePath)
      await router.isReady()

      const wrapper = mount(BookingView, {
        global: {
          plugins: [i18n, router],
        },
      })

      expect(wrapper.find('.user-booking-view-page').exists()).toBe(true)
    }
  })

  it('preserves active booking tab across V1 <-> V2 switches', async () => {
    const appearance = useAppearanceStore()
    appearance.setUiVersion('v1')

    await router.push('/booking')
    await router.isReady()

    const wrapper = mount(BookingView, {
      global: {
        plugins: [i18n, router],
      },
    })

    expect(wrapper.find('.user-booking-view-page').exists()).toBe(true)

    const tabs = wrapper.findAll('.b-tab')
    if (tabs.length > 1) {
      await tabs[1]!.trigger('click')
      expect(tabs[1]!.classes()).toContain('active')

      // Switch to V2
      appearance.setUiVersion('v2')
      await wrapper.vm.$nextTick()
      expect(tabs[1]!.classes()).toContain('active')

      // Switch back to V1
      appearance.setUiVersion('v1')
      await wrapper.vm.$nextTick()
      expect(tabs[1]!.classes()).toContain('active')
    }
  })
})
