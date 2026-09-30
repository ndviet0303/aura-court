import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import fs from 'node:fs'
import { fileURLToPath } from 'node:url'
import path from 'node:path'
import { i18n } from '@/i18n'
import CourtsView from '@/features/court/views/CourtsView.vue'
import AboutView from '@/features/home/views/AboutView.vue'
import { useBookingStore } from '@/stores/booking'

describe('Courts and Discovery Views V2 Adaptation', () => {
  const currentDir = path.dirname(fileURLToPath(import.meta.url))
  const erabookCssPath = path.resolve(currentDir, '../../../assets/styles/erabook.css')
  const erabookCss = fs.readFileSync(erabookCssPath, 'utf-8')

  beforeEach(() => {
    setActivePinia(createPinia())
    document.documentElement.removeAttribute('data-ui-version')
    document.documentElement.classList.remove('dark')
  })

  it('contains V2 discovery rules in CSS for hero, search, cards and about', () => {
    expect(erabookCss).toContain('html[data-ui-version="v2"] .hero h1 em')
    expect(erabookCss).toContain('html[data-ui-version="v2"] .sport-category-btn.active')
    expect(erabookCss).toContain('html[data-ui-version="v2"] .court-card-item')
    expect(erabookCss).toContain('html[data-ui-version="v2"] .empty-state-card')
    expect(erabookCss).toContain('html[data-ui-version="v2"] .aura-glow-bg')
    expect(erabookCss).toContain('html[data-ui-version="v2"] .brand-badge')
  })

  it('renders CourtsView skeleton loading state when store is loading', async () => {
    const store = useBookingStore()
    vi.spyOn(store, 'loadCourts').mockResolvedValue()
    store.courts = []
    store.courtsLoading = true

    const wrapper = mount(CourtsView, {
      global: {
        plugins: [i18n],
        stubs: {
          CourtsHeroHeader: true,
          CourtsFilterBar: true,
          CourtItemCard: true,
          SkeletonLoader: {
            template: '<div class="skeleton-mock">Skeleton Loading...</div>',
          },
        },
      },
    })

    expect(wrapper.find('[data-testid="courts-skeleton"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Skeleton Loading...')
  })

  it('renders CourtsView empty state when no courts match filter', async () => {
    const store = useBookingStore()
    vi.spyOn(store, 'loadCourts').mockResolvedValue()
    store.courts = []
    store.courtsLoading = false

    const wrapper = mount(CourtsView, {
      global: {
        plugins: [i18n],
        stubs: {
          CourtsHeroHeader: true,
          CourtsFilterBar: true,
          CourtItemCard: true,
          SkeletonLoader: true,
        },
      },
    })

    expect(wrapper.find('[data-testid="courts-empty-state"]').exists()).toBe(true)
  })

  it('renders AboutView cleanly with badges, stats ribbon, and pillars', () => {
    const wrapper = mount(AboutView, {
      global: {
        plugins: [i18n],
        stubs: {
          RouterLink: {
            template: '<a :href="to"><slot /></a>',
            props: ['to'],
          },
        },
      },
    })

    expect(wrapper.find('.about-page-wrapper').exists()).toBe(true)
    expect(wrapper.find('.brand-badge').exists()).toBe(true)
    expect(wrapper.find('.stats-ribbon').exists()).toBe(true)
    expect(wrapper.find('.pillars-section').exists()).toBe(true)
  })
})
