import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import {
  useAppearanceStore,
  UI_VERSION_STORAGE_KEY,
  isValidUiVersion,
  readStoredUiVersion,
  writeStoredUiVersion,
  applyUiVersionToDom,
} from '../stores/appearanceStore'

describe('appearanceStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    localStorage.clear()
    document.documentElement.removeAttribute('data-ui-version')
    vi.restoreAllMocks()
  })

  it('defaults to v1 when storage is empty', () => {
    const store = useAppearanceStore()
    expect(store.uiVersion).toBe('v1')
    expect(store.isV2).toBe(false)
    expect(document.documentElement.getAttribute('data-ui-version')).toBe('v1')
  })

  it('falls back to v1 when stored value is invalid', () => {
    localStorage.setItem(UI_VERSION_STORAGE_KEY, 'invalid-version')
    expect(readStoredUiVersion()).toBe('v1')

    const store = useAppearanceStore()
    expect(store.uiVersion).toBe('v1')
    expect(store.isV2).toBe(false)
    expect(document.documentElement.getAttribute('data-ui-version')).toBe('v1')
  })

  it('safely falls back to v1 when localStorage read throws SecurityError', () => {
    vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
      throw new Error('SecurityError: The operation is insecure.')
    })

    expect(readStoredUiVersion()).toBe('v1')
    const store = useAppearanceStore()
    expect(store.uiVersion).toBe('v1')
  })

  it('updates state, DOM attribute and storage on setUiVersion', () => {
    const store = useAppearanceStore()
    store.setUiVersion('v2')

    expect(store.uiVersion).toBe('v2')
    expect(store.isV2).toBe(true)
    expect(document.documentElement.getAttribute('data-ui-version')).toBe('v2')
    expect(localStorage.getItem(UI_VERSION_STORAGE_KEY)).toBe('v2')

    store.setUiVersion('v1')
    expect(store.uiVersion).toBe('v1')
    expect(store.isV2).toBe(false)
    expect(document.documentElement.getAttribute('data-ui-version')).toBe('v1')
    expect(localStorage.getItem(UI_VERSION_STORAGE_KEY)).toBe('v1')
  })

  it('normalizes invalid values passed to setUiVersion to default v1', () => {
    const store = useAppearanceStore()
    store.setUiVersion('v2')
    expect(store.uiVersion).toBe('v2')

    store.setUiVersion('v999')
    expect(store.uiVersion).toBe('v1')
    expect(document.documentElement.getAttribute('data-ui-version')).toBe('v1')
    expect(localStorage.getItem(UI_VERSION_STORAGE_KEY)).toBe('v1')
  })

  it('toggles bi-directionally between v1 and v2', () => {
    const store = useAppearanceStore()
    expect(store.uiVersion).toBe('v1')

    store.toggleUiVersion()
    expect(store.uiVersion).toBe('v2')
    expect(store.isV2).toBe(true)
    expect(document.documentElement.getAttribute('data-ui-version')).toBe('v2')

    store.toggleUiVersion()
    expect(store.uiVersion).toBe('v1')
    expect(store.isV2).toBe(false)
    expect(document.documentElement.getAttribute('data-ui-version')).toBe('v1')
  })

  it('updates memory state and DOM even when storage write fails', () => {
    const store = useAppearanceStore()
    vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
      throw new Error('QuotaExceededError')
    })

    expect(writeStoredUiVersion('v2')).toBe(false)
    store.setUiVersion('v2')

    expect(store.uiVersion).toBe('v2')
    expect(store.isV2).toBe(true)
    expect(document.documentElement.getAttribute('data-ui-version')).toBe('v2')
  })

  it('persists and restores v2 upon subsequent initialization', () => {
    localStorage.setItem(UI_VERSION_STORAGE_KEY, 'v2')
    const store = useAppearanceStore()
    expect(store.uiVersion).toBe('v2')
    expect(store.isV2).toBe(true)
    expect(document.documentElement.getAttribute('data-ui-version')).toBe('v2')
  })

  it('validates helper functions independently', () => {
    expect(isValidUiVersion('v1')).toBe(true)
    expect(isValidUiVersion('v2')).toBe(true)
    expect(isValidUiVersion('v3')).toBe(false)
    expect(isValidUiVersion(null)).toBe(false)
    expect(isValidUiVersion(undefined)).toBe(false)

    applyUiVersionToDom('v2')
    expect(document.documentElement.getAttribute('data-ui-version')).toBe('v2')
  })
})
