import { afterEach, describe, expect, it, vi } from 'vitest'
import { createApp, type App } from 'vue'
import { i18n } from '@/i18n'
import BookingQrCheckInModal from '../BookingQrCheckInModal.vue'
import type { Reservation } from '../../../types/reservation'

vi.mock('qrcode', () => ({ default: { toDataURL: vi.fn(async () => 'data:image/png;base64,') } }))

let app: App | null = null

afterEach(() => {
  app?.unmount()
  app = null
  document.body.innerHTML = ''
})

describe('BookingQrCheckInModal BR-188 check-in code', () => {
  it('prints the new check-in code of a transferred booking, not its order code', () => {
    const el = document.createElement('div')
    document.body.appendChild(el)
    const booking = {
      code: 'BK-OLD',
      check_in_code: 'CHKNEWCODE12345',
      slots: [],
    } as unknown as Reservation
    app = createApp(BookingQrCheckInModal, { show: true, booking })
    app.use(i18n).mount(el)

    expect(el.textContent).toContain('CHKNEWCODE12345')
  })
})
