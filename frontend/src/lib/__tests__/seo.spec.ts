import { describe, it, expect, beforeEach } from 'vitest'
import { applySeo } from '../seo'
import { i18n, setAppLocale } from '@/i18n'
import router from '@/router'
import vi from '@/i18n/locales/vi'
import en from '@/i18n/locales/en'

describe('SEO & Bilingual Routes', () => {
  beforeEach(() => {
    setAppLocale('vi')
    document.title = ''
  })

  it('verifies that all non-admin routes have titleKey and corresponding translations in both vi and en', () => {
    const routes = router.getRoutes()
    const nonAdminRoutes = routes.filter((r) => !r.path.startsWith('/admin'))

    expect(nonAdminRoutes.length).toBeGreaterThan(15)

    for (const route of nonAdminRoutes) {
      const titleKey = route.meta?.titleKey as string | undefined
      expect(titleKey, `Route ${route.path} missing meta.titleKey`).toBeDefined()

      // Verify that the title key exists in both locales
      const viTitle = i18n.global.t(titleKey!)
      expect(viTitle, `Missing VI translation for ${titleKey} on route ${route.path}`).not.toBe(
        titleKey,
      )
      expect(viTitle.length).toBeGreaterThan(0)

      setAppLocale('en')
      const enTitle = i18n.global.t(titleKey!)
      expect(enTitle, `Missing EN translation for ${titleKey} on route ${route.path}`).not.toBe(
        titleKey,
      )
      expect(enTitle.length).toBeGreaterThan(0)

      setAppLocale('vi')
    }
  })

  it('updates document.title and meta description dynamically based on active locale', () => {
    const mockRoute = {
      path: '/tournaments',
      meta: {
        titleKey: 'seo.tournamentsTitle',
        descriptionKey: 'seo.tournamentsDesc',
      },
    } as any

    // Test in Vietnamese
    setAppLocale('vi')
    applySeo(mockRoute)
    expect(document.title).toBe(vi.seo.tournamentsTitle)
    const metaDescVi = document.head.querySelector<HTMLMetaElement>(
      'meta[name="description"]',
    )?.content
    expect(metaDescVi).toBe(vi.seo.tournamentsDesc)

    // Test in English
    setAppLocale('en')
    applySeo(mockRoute)
    expect(document.title).toBe(en.seo.tournamentsTitle)
    const metaDescEn = document.head.querySelector<HTMLMetaElement>(
      'meta[name="description"]',
    )?.content
    expect(metaDescEn).toBe(en.seo.tournamentsDesc)
  })

  it('applies fallback home title when no titleKey is specified', () => {
    setAppLocale('vi')
    applySeo(undefined)
    expect(document.title).toBe(vi.seo.homeTitle)

    setAppLocale('en')
    applySeo(undefined)
    expect(document.title).toBe(en.seo.homeTitle)
  })
})
