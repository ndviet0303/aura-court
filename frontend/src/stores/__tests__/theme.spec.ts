import { describe, it, expect, beforeEach, vi } from 'vitest'
import { nextTick } from 'vue'
import { setActivePinia, createPinia } from 'pinia'
import { useThemeStore } from '../theme'

describe('useThemeStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    localStorage.clear()
    document.documentElement.classList.remove('dark')
    document.documentElement.removeAttribute('data-theme')
    vi.restoreAllMocks()
  })

  it('initializes to light theme when storage is empty', () => {
    const store = useThemeStore()
    expect(store.theme).toBe('light')
    expect(store.isDark()).toBe(false)
    expect(document.documentElement.getAttribute('data-theme')).toBe('light')
    expect(document.documentElement.classList.contains('dark')).toBe(false)
  })

  it('initializes to dark theme when stored', () => {
    localStorage.setItem('aura_theme', 'dark')
    const store = useThemeStore()
    expect(store.theme).toBe('dark')
    expect(store.isDark()).toBe(true)
    expect(document.documentElement.getAttribute('data-theme')).toBe('dark')
    expect(document.documentElement.classList.contains('dark')).toBe(true)
  })

  it('safely falls back to light theme if storage read throws SecurityError', () => {
    vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
      throw new Error('SecurityError: The operation is insecure.')
    })

    const store = useThemeStore()
    expect(store.theme).toBe('light')
    expect(document.documentElement.getAttribute('data-theme')).toBe('light')
  })

  it('toggles theme between light and dark', async () => {
    const store = useThemeStore()
    expect(store.theme).toBe('light')

    store.toggleTheme()
    await nextTick()
    expect(store.theme).toBe('dark')
    expect(store.isDark()).toBe(true)
    expect(document.documentElement.getAttribute('data-theme')).toBe('dark')
    expect(document.documentElement.classList.contains('dark')).toBe(true)
    expect(localStorage.getItem('aura_theme')).toBe('dark')

    store.toggleTheme()
    await nextTick()
    expect(store.theme).toBe('light')
    expect(store.isDark()).toBe(false)
    expect(document.documentElement.getAttribute('data-theme')).toBe('light')
    expect(document.documentElement.classList.contains('dark')).toBe(false)
    expect(localStorage.getItem('aura_theme')).toBe('light')
  })

  it('does not throw when storage write fails', async () => {
    const store = useThemeStore()
    vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
      throw new Error('QuotaExceededError')
    })

    expect(() => store.toggleTheme()).not.toThrow()
    await nextTick()
    expect(store.theme).toBe('dark')
    expect(document.documentElement.getAttribute('data-theme')).toBe('dark')
  })
})
