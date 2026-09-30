import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createRouter, createWebHistory } from 'vue-router'
import fs from 'node:fs'
import { fileURLToPath } from 'node:url'
import path from 'node:path'
import { i18n } from '@/i18n'
import { useAppearanceStore } from '@/features/appearance/stores/appearanceStore'
import LoginView from '@/features/auth/views/LoginView.vue'
import ForgotPasswordView from '@/features/auth/views/ForgotPasswordView.vue'
import OnboardingView from '@/features/auth/views/OnboardingView.vue'

describe('Auth & Account V2 Adaptation (Task 5.1)', () => {
  const currentDir = path.dirname(fileURLToPath(import.meta.url))
  const erabookCssPath = path.resolve(currentDir, '../../../assets/styles/erabook.css')
  const erabookCss = fs.readFileSync(erabookCssPath, 'utf-8')

  const router = createRouter({
    history: createWebHistory(),
    routes: [
      { path: '/', component: { template: '<div>Home</div>' } },
      { path: '/login', component: LoginView },
      { path: '/register', component: LoginView },
      { path: '/forgot-password', component: ForgotPasswordView },
      { path: '/onboarding', component: OnboardingView },
    ],
  })

  beforeEach(async () => {
    setActivePinia(createPinia())
    localStorage.clear()
    document.documentElement.removeAttribute('data-ui-version')
    document.documentElement.classList.remove('dark')
    await router.push('/login')
    await router.isReady()
  })

  it('contains V2 auth styling rules in erabook.css', () => {
    expect(erabookCss).toContain('html[data-ui-version="v2"] .auth-split-card')
    expect(erabookCss).toContain('html[data-ui-version="v2"] .auth-card-single')
    expect(erabookCss).toContain('html[data-ui-version="v2"] .onboarding-card')
    expect(erabookCss).toContain('html[data-ui-version="v2"] .auth-submit-btn')
    expect(erabookCss).toContain('html[data-ui-version="v2"] .welcome-chip')
  })

  it('preserves typed form state in LoginView across V1 <-> V2 switches', async () => {
    const appearance = useAppearanceStore()
    appearance.setUiVersion('v1')

    const wrapper = mount(LoginView, {
      global: {
        plugins: [i18n, router],
      },
    })

    // Find email and password inputs
    const emailInput = wrapper.find('input[type="email"]')
    const passwordInput = wrapper.find('input[type="password"]')
    expect(emailInput.exists()).toBe(true)
    expect(passwordInput.exists()).toBe(true)

    // Type credentials
    await emailInput.setValue('testuser@auracourt.vn')
    await passwordInput.setValue('SecurePassword123!')

    expect((emailInput.element as HTMLInputElement).value).toBe('testuser@auracourt.vn')
    expect((passwordInput.element as HTMLInputElement).value).toBe('SecurePassword123!')

    // Switch to V2
    appearance.setUiVersion('v2')
    await wrapper.vm.$nextTick()

    // Verify values remain intact
    expect((emailInput.element as HTMLInputElement).value).toBe('testuser@auracourt.vn')
    expect((passwordInput.element as HTMLInputElement).value).toBe('SecurePassword123!')

    // Switch back to V1
    appearance.setUiVersion('v1')
    await wrapper.vm.$nextTick()

    // Verify values still intact
    expect((emailInput.element as HTMLInputElement).value).toBe('testuser@auracourt.vn')
    expect((passwordInput.element as HTMLInputElement).value).toBe('SecurePassword123!')
  })

  it('preserves entered email in ForgotPasswordView across V1 <-> V2 switches', async () => {
    const appearance = useAppearanceStore()
    appearance.setUiVersion('v1')

    const wrapper = mount(ForgotPasswordView, {
      global: {
        plugins: [i18n, router],
      },
    })

    const emailInput = wrapper.find('input[type="email"]')
    expect(emailInput.exists()).toBe(true)

    await emailInput.setValue('forgot@auracourt.vn')
    expect((emailInput.element as HTMLInputElement).value).toBe('forgot@auracourt.vn')

    // Switch V1 -> V2 -> V1
    appearance.setUiVersion('v2')
    await wrapper.vm.$nextTick()
    expect((emailInput.element as HTMLInputElement).value).toBe('forgot@auracourt.vn')

    appearance.setUiVersion('v1')
    await wrapper.vm.$nextTick()
    expect((emailInput.element as HTMLInputElement).value).toBe('forgot@auracourt.vn')
  })

  it('preserves selected role in OnboardingView across V1 <-> V2 switches', async () => {
    const appearance = useAppearanceStore()
    appearance.setUiVersion('v1')

    const wrapper = mount(OnboardingView, {
      global: {
        plugins: [i18n, router],
      },
    })

    expect(wrapper.find('.onboarding-card').exists()).toBe(true)

    // Select second role (e.g. venue owner)
    const roleCards = wrapper.findAll('.role-card')
    if (roleCards.length > 1) {
      await roleCards[1]!.trigger('click')
      expect(roleCards[1]!.classes()).toContain('active')

      // Switch to V2
      appearance.setUiVersion('v2')
      await wrapper.vm.$nextTick()
      expect(roleCards[1]!.classes()).toContain('active')

      // Switch back to V1
      appearance.setUiVersion('v1')
      await wrapper.vm.$nextTick()
      expect(roleCards[1]!.classes()).toContain('active')
    }
  })
})
