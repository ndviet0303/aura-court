import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import LoginForm from '../components/LoginForm.vue'
import { i18n } from '@/i18n'

describe('LoginForm.vue', () => {
  it('renders localized button text in Vietnamese when idle and loading', () => {
    i18n.global.locale.value = 'vi'

    // Idle
    const idleWrapper = mount(LoginForm, {
      props: {
        email: 'test@example.com',
        password: 'password123',
        rememberMe: true,
        loading: false,
      },
      global: {
        plugins: [i18n],
        stubs: { RouterLink: true },
      },
    })
    const idleBtn = idleWrapper.find('.auth-submit-btn')
    expect(idleBtn.text()).toContain('Đăng nhập')
    expect(idleBtn.text()).not.toContain('auth.')

    // Loading
    const loadingWrapper = mount(LoginForm, {
      props: {
        email: 'test@example.com',
        password: 'password123',
        rememberMe: true,
        loading: true,
      },
      global: {
        plugins: [i18n],
        stubs: { RouterLink: true },
      },
    })
    const loadingBtn = loadingWrapper.find('.auth-submit-btn')
    expect(loadingBtn.text()).toContain('Đang đăng nhập...')
    expect(loadingBtn.text()).not.toContain('auth.loggingIn')
    expect(loadingBtn.text()).not.toContain('auth.siginiing')
    expect(loadingBtn.text()).not.toContain('auth.')
  })

  it('renders localized button text in English when idle and loading', () => {
    i18n.global.locale.value = 'en'

    // Idle
    const idleWrapper = mount(LoginForm, {
      props: {
        email: 'test@example.com',
        password: 'password123',
        rememberMe: true,
        loading: false,
      },
      global: {
        plugins: [i18n],
        stubs: { RouterLink: true },
      },
    })
    const idleBtn = idleWrapper.find('.auth-submit-btn')
    expect(idleBtn.text()).toContain('Sign In')
    expect(idleBtn.text()).not.toContain('auth.')

    // Loading
    const loadingWrapper = mount(LoginForm, {
      props: {
        email: 'test@example.com',
        password: 'password123',
        rememberMe: true,
        loading: true,
      },
      global: {
        plugins: [i18n],
        stubs: { RouterLink: true },
      },
    })
    const loadingBtn = loadingWrapper.find('.auth-submit-btn')
    expect(loadingBtn.text()).toContain('Signing in...')
    expect(loadingBtn.text()).not.toContain('auth.loggingIn')
    expect(loadingBtn.text()).not.toContain('auth.siginiing')
    expect(loadingBtn.text()).not.toContain('auth.')

    // Reset locale
    i18n.global.locale.value = 'vi'
  })
})
