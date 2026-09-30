import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import { useAppearanceStore } from '../stores/appearanceStore'
import { useThemeStore } from '@/stores/theme'
import { setAppLocale, getCurrentLocale } from '@/i18n'
import App from '@/App.vue'

vi.mock('vue-router', () => ({
  useRoute: () => ({
    path: '/courts',
    fullPath: '/courts?sport=badminton#results',
    query: { sport: 'badminton' },
    hash: '#results',
  }),
  useRouter: () => ({
    push: vi.fn(),
    replace: vi.fn(),
  }),
  RouterView: {
    name: 'RouterView',
    template: '<div class="mock-router-view" data-testid="router-view-instance">Content</div>',
  },
}))

describe('Four combinations and state preservation', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    localStorage.clear()
    document.documentElement.classList.remove('dark')
    document.documentElement.removeAttribute('data-theme')
    document.documentElement.removeAttribute('data-ui-version')
    vi.restoreAllMocks()
  })

  it('supports all four combinations of V1/V2 x light/dark independently', async () => {
    const appearance = useAppearanceStore()
    const theme = useThemeStore()

    // 1. V1 - Light
    appearance.setUiVersion('v1')
    theme.setTheme('light')
    await nextTick()
    expect(document.documentElement.getAttribute('data-ui-version')).toBe('v1')
    expect(document.documentElement.getAttribute('data-theme')).toBe('light')

    // 2. V1 - Dark
    theme.setTheme('dark')
    await nextTick()
    expect(document.documentElement.getAttribute('data-ui-version')).toBe('v1')
    expect(document.documentElement.getAttribute('data-theme')).toBe('dark')

    // 3. V2 - Dark (Switching presentation does NOT change color mode)
    appearance.setUiVersion('v2')
    await nextTick()
    expect(document.documentElement.getAttribute('data-ui-version')).toBe('v2')
    expect(document.documentElement.getAttribute('data-theme')).toBe('dark')

    // 4. V2 - Light (Switching color mode does NOT change presentation)
    theme.setTheme('light')
    await nextTick()
    expect(document.documentElement.getAttribute('data-ui-version')).toBe('v2')
    expect(document.documentElement.getAttribute('data-theme')).toBe('light')

    // Back to V1 - Light
    appearance.setUiVersion('v1')
    await nextTick()
    expect(document.documentElement.getAttribute('data-ui-version')).toBe('v1')
    expect(document.documentElement.getAttribute('data-theme')).toBe('light')
  })

  it('preserves active locale independently of presentation version', () => {
    const appearance = useAppearanceStore()

    setAppLocale('vi')
    expect(getCurrentLocale()).toBe('vi')

    appearance.setUiVersion('v2')
    expect(getCurrentLocale()).toBe('vi')

    setAppLocale('en')
    expect(getCurrentLocale()).toBe('en')

    appearance.setUiVersion('v1')
    expect(getCurrentLocale()).toBe('en')
    expect(appearance.uiVersion).toBe('v1')
  })

  it('renders a single RouterView in App.vue without version-key or tree duplication', () => {
    const appearance = useAppearanceStore()
    appearance.setUiVersion('v1')

    const appWrapper = mount(App, {
      global: {
        stubs: {
          Navbar: true,
          SiteFooter: true,
          DemoWarningModal: true,
        },
      },
    })

    // Exactly one RouterView instance rendered
    const views = appWrapper.findAllComponents({ name: 'RouterView' })
    expect(views).toHaveLength(1)

    // Verify RouterView does not have a key attribute bound to version
    const firstView = views[0]
    expect(firstView?.attributes('key')).toBeFalsy()

    // Switch to v2
    appearance.setUiVersion('v2')
    expect(appWrapper.findAllComponents({ name: 'RouterView' })).toHaveLength(1)
  })
})
