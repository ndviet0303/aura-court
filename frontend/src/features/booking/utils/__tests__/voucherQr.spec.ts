import { describe, expect, it } from 'vitest'
import { voucherCodeFromQr } from '../voucherQr'

describe('voucherCodeFromQr', () => {
  it('reads the code from the voucher booking link', () => {
    expect(voucherCodeFromQr('https://aura.example/courts/12/schedule?voucher=ABCDEFGHJK')).toBe(
      'ABCDEFGHJK',
    )
    expect(voucherCodeFromQr('/courts/3/schedule?voucher=abc')).toBe('abc')
  })

  it('refuses any other QR', () => {
    expect(voucherCodeFromQr('AURA_BOOKING_BK123')).toBeNull()
    expect(voucherCodeFromQr('https://aura.example/courts/12/schedule')).toBeNull()
    expect(voucherCodeFromQr('https://aura.example/venues/1?voucher=ABC')).toBeNull()
    expect(voucherCodeFromQr('00020101021238570010A000000727')).toBeNull()
  })
})
