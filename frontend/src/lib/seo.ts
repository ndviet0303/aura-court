import type { RouteLocationNormalizedLoaded } from 'vue-router'
import { i18n } from '@/i18n'

function setMeta(attribute: 'name' | 'property', key: string, content: string): void {
  if (typeof document === 'undefined') return
  let element = document.head.querySelector<HTMLMetaElement>(`meta[${attribute}="${key}"]`)
  if (!element) {
    element = document.createElement('meta')
    element.setAttribute(attribute, key)
    document.head.appendChild(element)
  }
  element.content = content
}

function setCanonical(url: string): void {
  if (typeof document === 'undefined') return
  let element = document.head.querySelector<HTMLLinkElement>('link[rel="canonical"]')
  if (!element) {
    element = document.createElement('link')
    element.rel = 'canonical'
    document.head.appendChild(element)
  }
  element.href = url
}

export function applySeo(route?: RouteLocationNormalizedLoaded): void {
  const t = i18n.global.t
  const origin = typeof window !== 'undefined' ? window.location.origin : 'https://auracourt.vn'

  let title = t('seo.homeTitle')
  let description = t('seo.homeDesc')
  const defaultImage = `${origin}/og-image.jpg`

  if (typeof route?.meta?.titleKey === 'string') {
    title = t(route.meta.titleKey)
  } else if (typeof route?.meta?.title === 'string') {
    title = route.meta.title
  }

  if (typeof route?.meta?.descriptionKey === 'string') {
    description = t(route.meta.descriptionKey)
  } else if (typeof route?.meta?.description === 'string') {
    description = route.meta.description
  }

  const indexable = route?.meta?.indexable !== false
  const canonical = new URL(route?.path || '/', origin).toString()

  if (typeof document !== 'undefined') {
    document.title = title
    setMeta('name', 'description', description)
    setMeta(
      'name',
      'robots',
      indexable ? 'index, follow, max-image-preview:large' : 'noindex, nofollow',
    )
    setMeta('property', 'og:title', title)
    setMeta('property', 'og:description', description)
    setMeta('property', 'og:url', canonical)
    setMeta('property', 'og:image', defaultImage)
    setMeta('name', 'twitter:title', title)
    setMeta('name', 'twitter:description', description)
    setMeta('name', 'twitter:image', defaultImage)
    setCanonical(canonical)
  }
}
