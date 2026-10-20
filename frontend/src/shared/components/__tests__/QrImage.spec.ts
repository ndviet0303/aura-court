import { describe, expect, it, vi } from 'vitest'
import fs from 'node:fs'
import path from 'node:path'
import { flushPromises, mount } from '@vue/test-utils'
import QRCode from 'qrcode'
import QrImage from '../media/QrImage.vue'
import BookingQrCheckInModal from '@/features/booking/components/history/BookingQrCheckInModal.vue'
import ReservationQrModal from '@/features/booking/components/reservation/ReservationQrModal.vue'
import type { Reservation } from '@/features/booking/types/reservation'
import { i18n } from '@/i18n'

vi.mock('qrcode', () => ({
  default: { toDataURL: vi.fn(async (value: string) => `data:image/png;base64,${btoa(value)}`) },
}))

const reservation = {
  code: 'RSV123',
  status: 'confirmed',
  slots: [],
  legacy_mapping: [{ legacy_code: 'BK-OLD' }],
} as unknown as Reservation

describe('QrImage', () => {
  it('draws the payload on the device as a data image', async () => {
    const wrapper = mount(QrImage, { props: { value: 'AURA_BOOKING_RSV123', alt: 'QR' } })
    await flushPromises()

    expect(QRCode.toDataURL).toHaveBeenCalledWith('AURA_BOOKING_RSV123', { width: 220, margin: 2 })
    expect(wrapper.get('img').attributes('src')).toBe(
      `data:image/png;base64,${btoa('AURA_BOOKING_RSV123')}`,
    )
  })

  it('gives both booking QR modals the one AURA_BOOKING_<code> payload', async () => {
    const checkIn = mount(BookingQrCheckInModal, { props: { show: true, booking: reservation } })
    const detail = mount(ReservationQrModal, {
      props: { show: true, reservation, formatDate: String, formatTimeSlot: String },
      global: { plugins: [i18n] },
    })

    expect(checkIn.getComponent(QrImage).props('value')).toBe('AURA_BOOKING_RSV123')
    expect(detail.getComponent(QrImage).props('value')).toBe('AURA_BOOKING_RSV123')
  })

  it('sends nothing to a third-party QR service', () => {
    const srcDir = path.resolve(__dirname, '../../..')
    const offenders = (fs.readdirSync(srcDir, { recursive: true }) as string[]).filter(
      (file) =>
        /\.(vue|ts)$/.test(file) &&
        !file.includes('__tests__') &&
        fs.readFileSync(path.join(srcDir, file), 'utf8').includes('qrserver'),
    )
    expect(offenders).toEqual([])
  })
})
