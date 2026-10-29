import { afterEach, beforeEach, describe, expect, it } from 'vitest'
import { createApp, type App } from 'vue'
import { createPinia, setActivePinia, type Pinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'
import SplitBillLink from '../SplitBillLink.vue'
import { useAuthStore } from '@/features/auth/stores/authStore'
import { i18n } from '@/i18n'
import type { Reservation } from '../../../types/reservation'

let app: App | null = null
let pinia: Pinia

function splitLink(booking: Partial<Reservation>, splitEnabled: boolean) {
  useAuthStore().user = {
    id: 1,
    capabilities: { split_payments_enabled: splitEnabled },
  } as unknown as ReturnType<typeof useAuthStore>['user']
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/split-bill/:code', name: 'split-bill', component: { template: '<div />' } }],
  })
  const el = document.createElement('div')
  document.body.appendChild(el)
  app = createApp(SplitBillLink, { booking: { code: 'RSV-SPLIT-1', status: 'held', ...booking } })
  app.use(i18n).use(pinia).use(router).mount(el)
  return el.querySelector('[data-testid="booking-split-btn"]')
}

describe('SplitBillLink', () => {
  beforeEach(() => {
    pinia = createPinia()
    setActivePinia(pinia)
  })

  afterEach(() => {
    app?.unmount()
    app = null
    document.body.innerHTML = ''
  })

  it('links a held booking to its split page by booking code when split payments are on', () => {
    expect(splitLink({}, true)?.getAttribute('href')).toBe('/split-bill/RSV-SPLIT-1')
  })

  it('keeps the link for a booking that already has a split group', () => {
    expect(splitLink({ status: 'confirmed', has_split_group: true }, true)).not.toBeNull()
  })

  it('hides the link when split payments are off', () => {
    expect(splitLink({}, false)).toBeNull()
  })

  it('hides the link for a booking that can no longer be split', () => {
    expect(splitLink({ status: 'confirmed' }, true)).toBeNull()
  })
})
