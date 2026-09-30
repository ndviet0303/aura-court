import { ref, computed } from 'vue'
import { defineStore } from 'pinia'

export type UiVersion = 'v1' | 'v2'

export const UI_VERSION_STORAGE_KEY = 'aura_ui_version'
export const DEFAULT_UI_VERSION: UiVersion = 'v1'

export function isValidUiVersion(value: unknown): value is UiVersion {
  return value === 'v1' || value === 'v2'
}

export function readStoredUiVersion(): UiVersion {
  if (typeof window === 'undefined') return DEFAULT_UI_VERSION
  try {
    const raw = localStorage.getItem(UI_VERSION_STORAGE_KEY)
    if (isValidUiVersion(raw)) return raw
    return DEFAULT_UI_VERSION
  } catch {
    // Storage access denied (private mode / SecurityError)
    return DEFAULT_UI_VERSION
  }
}

export function writeStoredUiVersion(version: UiVersion): boolean {
  if (typeof window === 'undefined') return false
  try {
    localStorage.setItem(UI_VERSION_STORAGE_KEY, version)
    return true
  } catch {
    // Storage access denied / quota exceeded
    return false
  }
}

export function applyUiVersionToDom(version: UiVersion): void {
  if (typeof document === 'undefined') return
  const root = document.documentElement
  root.setAttribute('data-ui-version', version)
}

export const useAppearanceStore = defineStore('appearance', () => {
  const currentVersion = ref<UiVersion>(readStoredUiVersion())

  // Apply to DOM immediately on store initialization
  applyUiVersionToDom(currentVersion.value)

  const isV2 = computed(() => currentVersion.value === 'v2')

  function setUiVersion(version: unknown): void {
    const normalized: UiVersion = isValidUiVersion(version) ? version : DEFAULT_UI_VERSION
    currentVersion.value = normalized
    applyUiVersionToDom(normalized)
    writeStoredUiVersion(normalized)
  }

  function toggleUiVersion(): void {
    setUiVersion(currentVersion.value === 'v2' ? 'v1' : 'v2')
  }

  return {
    uiVersion: currentVersion,
    isV2,
    setUiVersion,
    toggleUiVersion,
  }
})
