import { ref, watchEffect } from 'vue'
import { defineStore } from 'pinia'

export type ThemeMode = 'light' | 'dark'

const STORAGE_KEY = 'aura_theme'

export const useThemeStore = defineStore('theme', () => {
  const getInitialTheme = (): ThemeMode => {
    if (typeof window === 'undefined') return 'light'
    const saved = localStorage.getItem(STORAGE_KEY) as ThemeMode | null
    if (saved === 'dark' || saved === 'light') return saved
    return 'light'
  }

  const theme = ref<ThemeMode>(getInitialTheme())

  function applyTheme(newTheme: ThemeMode) {
    if (typeof document === 'undefined') return
    const root = document.documentElement
    if (newTheme === 'dark') {
      root.classList.add('dark')
      root.setAttribute('data-theme', 'dark')
      root.style.colorScheme = 'dark'
    } else {
      root.classList.remove('dark')
      root.setAttribute('data-theme', 'light')
      root.style.colorScheme = 'light'
    }
    if (typeof localStorage !== 'undefined') {
      localStorage.setItem(STORAGE_KEY, newTheme)
    }
  }

  function toggleTheme() {
    theme.value = theme.value === 'dark' ? 'light' : 'dark'
  }

  function setTheme(newTheme: ThemeMode) {
    theme.value = newTheme
  }

  // Initialize and watch
  watchEffect(() => {
    applyTheme(theme.value)
  })

  // Listen to OS preference changes if not manually set
  if (typeof window !== 'undefined' && typeof window.matchMedia === 'function') {
    const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)')
    if (mediaQuery && typeof mediaQuery.addEventListener === 'function') {
      mediaQuery.addEventListener('change', (e) => {
        if (!localStorage.getItem(STORAGE_KEY)) {
          theme.value = e.matches ? 'dark' : 'light'
        }
      })
    }
  }

  return {
    theme,
    isDark: () => theme.value === 'dark',
    toggleTheme,
    setTheme,
  }
})
