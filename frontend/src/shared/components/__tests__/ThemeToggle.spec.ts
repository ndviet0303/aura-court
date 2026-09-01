import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import ThemeToggle from '../controls/ThemeToggle.vue'
import { useThemeStore } from '@/stores/theme'

describe('ThemeToggle', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    localStorage.clear()
  })

  it('renders correctly and toggles theme on click', async () => {
    const wrapper = mount(ThemeToggle, {
      global: {
        mocks: {
          $t: (key: string) => key,
        },
      },
    })
    const themeStore = useThemeStore()
    expect(wrapper.find('.theme-toggle-btn').exists()).toBe(true)

    const initialTheme = themeStore.theme
    await wrapper.find('.theme-toggle-btn').trigger('click')
    expect(themeStore.theme).toBe(initialTheme === 'dark' ? 'light' : 'dark')
  })
})
