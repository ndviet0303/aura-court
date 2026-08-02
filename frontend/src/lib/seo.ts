import type { RouteLocationNormalizedLoaded } from 'vue-router'

const defaultTitle = 'Aura Court | Đặt sân cầu lông trực tuyến'
const defaultDescription = 'Xem sân còn trống, chọn khung giờ yêu thích và đặt sân cầu lông trong vài giây.'

function setMeta(attribute: 'name' | 'property', key: string, content: string): void {
  let element = document.head.querySelector<HTMLMetaElement>(`meta[${attribute}="${key}"]`)
  if (!element) {
    element = document.createElement('meta')
    element.setAttribute(attribute, key)
    document.head.appendChild(element)
  }
  element.content = content
}

function setCanonical(url: string): void {
  let element = document.head.querySelector<HTMLLinkElement>('link[rel="canonical"]')
  if (!element) {
    element = document.createElement('link')
    element.rel = 'canonical'
    document.head.appendChild(element)
  }
  element.href = url
}

export function applySeo(route: RouteLocationNormalizedLoaded): void {
  const title = typeof route.meta.title === 'string' ? route.meta.title : defaultTitle
  const description = typeof route.meta.description === 'string' ? route.meta.description : defaultDescription
  const indexable = route.meta.indexable !== false
  const canonical = new URL(route.path, window.location.origin).toString()

  document.title = title
  setMeta('name', 'description', description)
  setMeta('name', 'robots', indexable ? 'index, follow, max-image-preview:large' : 'noindex, nofollow')
  setMeta('property', 'og:title', title)
  setMeta('property', 'og:description', description)
  setMeta('property', 'og:url', canonical)
  setMeta('name', 'twitter:title', title)
  setMeta('name', 'twitter:description', description)
  setCanonical(canonical)
}
