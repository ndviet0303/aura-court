import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useAppearanceStore } from '../stores/appearanceStore'
import { useThemeStore } from '@/stores/theme'

// Bootstrap script logic identical to index.html inline script
function runPrePaintBootstrap(): void {
  try {
    const root = document.documentElement
    const theme = localStorage.getItem('aura_theme')
    if (theme === 'dark' || (!theme && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
      root.classList.add('dark')
      root.setAttribute('data-theme', 'dark')
    } else {
      root.classList.remove('dark')
      root.setAttribute('data-theme', 'light')
    }
    const version = localStorage.getItem('aura_ui_version')
    if (version === 'v2') {
      root.setAttribute('data-ui-version', 'v2')
    } else {
      root.setAttribute('data-ui-version', 'v1')
    }
  } catch {
    document.documentElement.setAttribute('data-theme', 'light')
    document.documentElement.setAttribute('data-ui-version', 'v1')
  }
}

describe('Bootstrap restoration and store alignment', () => {
  beforeEach(() => {
    localStorage.clear()
    document.documentElement.classList.remove('dark')
    document.documentElement.removeAttribute('data-theme')
    document.documentElement.removeAttribute('data-ui-version')
    vi.restoreAllMocks()
  })

  it('restores v2-dark before first paint when saved in storage', () => {
    localStorage.setItem('aura_theme', 'dark')
    localStorage.setItem('aura_ui_version', 'v2')

    // Simulate pre-paint script run in <head>
    runPrePaintBootstrap()

    expect(document.documentElement.getAttribute('data-ui-version')).toBe('v2')
    expect(document.documentElement.getAttribute('data-theme')).toBe('dark')
    expect(document.documentElement.classList.contains('dark')).toBe(true)

    // Now application mounts Pinia stores
    setActivePinia(createPinia())
    const appearance = useAppearanceStore()
    const theme = useThemeStore()

    // Confirms NO flash to v1: stores align with pre-paint attributes
    expect(appearance.uiVersion).toBe('v2')
    expect(appearance.isV2).toBe(true)
    expect(theme.theme).toBe('dark')
    expect(theme.isDark()).toBe(true)
    expect(document.documentElement.getAttribute('data-ui-version')).toBe('v2')
    expect(document.documentElement.getAttribute('data-theme')).toBe('dark')
  })

  it('safely defaults to v1-light without crashing when storage is denied', () => {
    vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
      throw new Error('SecurityError: The operation is insecure.')
    })

    expect(() => runPrePaintBootstrap()).not.toThrow()
    expect(document.documentElement.getAttribute('data-ui-version')).toBe('v1')
    expect(document.documentElement.getAttribute('data-theme')).toBe('light')

    setActivePinia(createPinia())
    expect(() => useAppearanceStore()).not.toThrow()
    expect(() => useThemeStore()).not.toThrow()

    const appearance = useAppearanceStore()
    expect(appearance.uiVersion).toBe('v1')
  })
})
