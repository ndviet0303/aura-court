/**
 * BR-62, UC-3.15 A-1: a private voucher's QR holds its booking link, /courts/{id}/schedule?voucher=CODE, so the
 * code is read from that link. Any other QR gives null.
 */
export function voucherCodeFromQr(value: string): string | null {
  try {
    const url = new URL(value, 'https://qr.invalid')
    if (!/^\/courts\/\d+\/schedule$/.test(url.pathname)) return null
    return url.searchParams.get('voucher')?.trim() || null
  } catch {
    return null
  }
}
