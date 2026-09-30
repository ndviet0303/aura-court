import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import { setActivePinia, createPinia } from 'pinia'
import NavbarUserProfile from '@/shared/layouts/header/components/NavbarUserProfile.vue'
import NavbarMobileDrawer from '@/shared/layouts/header/components/NavbarMobileDrawer.vue'
import type { User } from '@/core/types/common'
import { useAuthStore } from '@/stores/auth'
import { useBookingStore } from '@/stores/booking'
import { useAppearanceStore } from '@/features/appearance'
import { i18n } from '@/i18n'

vi.mock('vue-router', () => ({
  useRoute: () => ({ path: '/', query: {} }),
  RouterLink: { template: '<a><slot /></a>' },
}))

describe('Navbar UI Version Switch Integration', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    localStorage.clear()
    document.documentElement.removeAttribute('data-ui-version')
    vi.restoreAllMocks()
  })

  function mountUserProfile() {
    return mount(NavbarUserProfile, {
      global: {
        plugins: [i18n],
        stubs: {
          RouterLink: true,
        },
      },
      attachTo: document.body,
    })
  }

  it('renders version switch in guest dropdown and toggles without calling fetchWallet', async () => {
    const auth = useAuthStore()
    const booking = useBookingStore()
    const appearance = useAppearanceStore()
    auth.user = null

    const fetchWalletSpy = vi.spyOn(booking, 'fetchWalletBalance')
    const wrapper = mountUserProfile()

    // Open guest dropdown
    const trigger = wrapper.find('.guest-trigger')
    await trigger.trigger('click')
    await nextTick()

    expect(wrapper.find('.guest-menu').exists()).toBe(true)

    // Verify switch is present and initial state is v1 (false)
    const versionSwitch = wrapper.findComponent({ name: 'AuraUiVersionSwitch' })
    expect(versionSwitch.exists()).toBe(true)
    expect(versionSwitch.props('modelValue')).toBe(false)
    expect(appearance.isV2).toBe(false)

    // Click switch
    await versionSwitch.find('button').trigger('click')
    await nextTick()

    expect(appearance.isV2).toBe(true)
    expect(appearance.uiVersion).toBe('v2')
    expect(document.documentElement.getAttribute('data-ui-version')).toBe('v2')

    // Dropdown remains open
    expect(wrapper.find('.guest-menu').exists()).toBe(true)

    // Verify switch didn't trigger wallet fetch
    expect(fetchWalletSpy).not.toHaveBeenCalled()

    wrapper.unmount()
  })

  it('renders version switch in auth dropdown and toggles state', async () => {
    const auth = useAuthStore()
    const appearance = useAppearanceStore()
    const authUser: User = {
      id: 1,
      name: 'Nguyen Van A',
      email: 'a@example.com',
      role: 'customer',
      avatar_url: null,
    }
    auth.user = authUser

    const wrapper = mountUserProfile()

    // Open auth dropdown
    const trigger = wrapper.find('.user-dropdown-trigger')
    await trigger.trigger('click')
    await nextTick()

    expect(wrapper.find('.user-dropdown-menu').exists()).toBe(true)

    const versionSwitch = wrapper.findComponent({ name: 'AuraUiVersionSwitch' })
    expect(versionSwitch.exists()).toBe(true)

    await versionSwitch.find('button').trigger('click')
    await nextTick()

    expect(appearance.isV2).toBe(true)
    expect(document.documentElement.getAttribute('data-ui-version')).toBe('v2')

    wrapper.unmount()
  })

  it('closes dropdown and returns focus to trigger button on Escape key', async () => {
    const auth = useAuthStore()
    auth.user = null
    const wrapper = mountUserProfile()

    const trigger = wrapper.find<HTMLButtonElement>('.guest-trigger')
    await trigger.trigger('click')
    await nextTick()
    expect(wrapper.find('.guest-menu').exists()).toBe(true)

    // Focus inside menu (e.g. switch button)
    const switchBtn = wrapper.findComponent({ name: 'AuraUiVersionSwitch' }).find('button')
    switchBtn.element.focus()
    expect(document.activeElement).toBe(switchBtn.element)

    // Dispatch Escape on document
    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))
    await nextTick()

    // Menu closed
    expect(wrapper.find('.guest-menu').exists()).toBe(false)

    // Focus returned to trigger
    expect(document.activeElement).toBe(trigger.element)

    wrapper.unmount()
  })

  it('preserves uiVersion across auth login and logout actions', () => {
    const auth = useAuthStore()
    const appearance = useAppearanceStore()

    appearance.setUiVersion('v2')
    expect(appearance.uiVersion).toBe('v2')

    // Simulate login
    const loggedInUser: User = {
      id: 2,
      name: 'User Two',
      email: 'two@test.vn',
      role: 'customer',
    }
    auth.user = loggedInUser
    expect(appearance.uiVersion).toBe('v2')

    // Simulate logout
    auth.logout()
    expect(appearance.uiVersion).toBe('v2')
    expect(localStorage.getItem('aura_ui_version')).toBe('v2')
  })

  it('renders synchronized version switch in mobile drawer', async () => {
    const appearance = useAppearanceStore()
    appearance.setUiVersion('v1')

    const drawerWrapper = mount(NavbarMobileDrawer, {
      props: {
        open: true,
      },
      global: {
        plugins: [i18n],
        stubs: {
          Teleport: true,
          NavbarDrawerUserCard: true,
          NavbarDrawerNavList: true,
          ThemeToggle: true,
          LanguageSwitcher: true,
        },
      },
    })

    const versionSwitch = drawerWrapper.findComponent({ name: 'AuraUiVersionSwitch' })
    expect(versionSwitch.exists()).toBe(true)
    expect(versionSwitch.props('modelValue')).toBe(false)

    await versionSwitch.find('button').trigger('click')
    await nextTick()

    expect(appearance.isV2).toBe(true)
    expect(document.documentElement.getAttribute('data-ui-version')).toBe('v2')
  })
})
