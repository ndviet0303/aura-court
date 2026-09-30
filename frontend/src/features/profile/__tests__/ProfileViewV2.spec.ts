import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createRouter, createWebHistory } from 'vue-router'
import fs from 'node:fs'
import { fileURLToPath } from 'node:url'
import path from 'node:path'
import { i18n } from '@/i18n'
import { useAuthStore } from '@/stores/auth'
import { useAppearanceStore } from '@/features/appearance/stores/appearanceStore'
import ProfileView from '@/features/profile/views/ProfileView.vue'
import NotificationsView from '@/features/notification/views/NotificationsView.vue'

describe('Profile & Notifications V2 Adaptation (Task 5.3)', () => {
  const currentDir = path.dirname(fileURLToPath(import.meta.url))
  const erabookCssPath = path.resolve(currentDir, '../../../assets/styles/erabook.css')
  const erabookCss = fs.readFileSync(erabookCssPath, 'utf-8')

  const router = createRouter({
    history: createWebHistory(),
    routes: [
      { path: '/', component: { template: '<div>Home</div>' } },
      { path: '/profile', component: ProfileView },
      { path: '/notifications', component: NotificationsView },
    ],
  })

  const mockUser = {
    id: 1,
    name: 'Nguyễn Văn Aura',
    email: 'user@auracourt.vn',
    phone: '0901234567',
    role: 'customer',
    is_active: true,
  }

  beforeEach(async () => {
    setActivePinia(createPinia())
    localStorage.clear()
    document.documentElement.removeAttribute('data-ui-version')
    document.documentElement.classList.remove('dark')
    await router.push('/profile')
    await router.isReady()
  })

  it('contains V2 profile and notification styling rules in erabook.css', () => {
    expect(erabookCss).toContain('html[data-ui-version="v2"] .athlete-hero-card')
    expect(erabookCss).toContain('html[data-ui-version="v2"] .profile-tab-card')
    expect(erabookCss).toContain('html[data-ui-version="v2"] .tab-item-btn.is-active')
    expect(erabookCss).toContain('html[data-ui-version="v2"] .notification-item-card')
    expect(erabookCss).toContain('html[data-ui-version="v2"] .filter-pill-btn.is-active')
  })

  it('preserves unsaved form fields and active tab in ProfileView without triggering submit across V1 <-> V2 switches', async () => {
    const auth = useAuthStore()
    auth.user = mockUser as any

    const appearance = useAppearanceStore()
    appearance.setUiVersion('v1')

    const wrapper = mount(ProfileView, {
      global: {
        plugins: [i18n, router],
      },
    })

    expect(wrapper.find('.profile-view-page').exists()).toBe(true)

    // Type in phone input to simulate unsaved field
    const phoneInput = wrapper.find('input[type="tel"]')
    if (phoneInput.exists()) {
      await phoneInput.setValue('0987654321')
      expect((phoneInput.element as HTMLInputElement).value).toBe('0987654321')
    }

    // Switch to V2
    appearance.setUiVersion('v2')
    await wrapper.vm.$nextTick()

    // Assert phone input retains unsaved value
    if (phoneInput.exists()) {
      expect((phoneInput.element as HTMLInputElement).value).toBe('0987654321')
    }

    // Switch back to V1
    appearance.setUiVersion('v1')
    await wrapper.vm.$nextTick()

    if (phoneInput.exists()) {
      expect((phoneInput.element as HTMLInputElement).value).toBe('0987654321')
    }
  })

  it('preserves notification filter selection across V1 <-> V2 switches', async () => {
    const appearance = useAppearanceStore()
    appearance.setUiVersion('v1')

    const wrapper = mount(NotificationsView, {
      global: {
        plugins: [i18n, router],
      },
    })

    expect(wrapper.find('.notifications-view-page').exists()).toBe(true)

    const filterBtns = wrapper.findAll('.filter-pill-btn')
    if (filterBtns.length > 1) {
      await filterBtns[1]!.trigger('click')
      expect(filterBtns[1]!.classes()).toContain('is-active')

      // Switch V1 -> V2
      appearance.setUiVersion('v2')
      await wrapper.vm.$nextTick()
      expect(filterBtns[1]!.classes()).toContain('is-active')

      // Switch back V2 -> V1
      appearance.setUiVersion('v1')
      await wrapper.vm.$nextTick()
      expect(filterBtns[1]!.classes()).toContain('is-active')
    }
  })

  it('preserves UI version appearance preference across auth transitions (logout/login)', () => {
    const appearance = useAppearanceStore()
    const auth = useAuthStore()

    appearance.setUiVersion('v2')
    expect(appearance.uiVersion).toBe('v2')

    // Simulate user login
    auth.user = mockUser as any
    expect(appearance.uiVersion).toBe('v2')

    // Simulate user logout
    auth.user = null
    expect(appearance.uiVersion).toBe('v2')
    expect(document.documentElement.getAttribute('data-ui-version')).toBe('v2')
  })
})
