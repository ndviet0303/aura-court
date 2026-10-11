import { describe, it, expect } from 'vitest'
import { i18n, setAppLocale } from '../index'
import vi from '../locales/vi'
import en from '../locales/en'

describe('Bilingual Localization (Song ngữ vi / en)', () => {
  it('contains matching keys for POS VietQR and shift balancing in both vi and en', () => {
    expect(vi.admin.pos.qrModalTitle).toBe('Thanh toán VietQR')
    expect(en.admin.pos.qrModalTitle).toBe('VietQR Payment')

    expect(vi.admin.pos.qrWaitingTransfer).toBe('Đang chờ chuyển khoản')
    expect(en.admin.pos.qrWaitingTransfer).toBe('Waiting for transfer')

    expect(vi.admin.pos.tabCurrentShift).toBe('Ca hiện tại')
    expect(en.admin.pos.tabCurrentShift).toBe('Current Shift')

    expect(vi.admin.pos.tabShiftHistory).toBe('Lịch sử ca & Đối soát')
    expect(en.admin.pos.tabShiftHistory).toBe('Shift History & Reconciliation')

    expect(vi.admin.pos.shiftVarianceMatched).toBe(
      'Khớp 100% so với dữ liệu đơn hàng và tiền đầu ca.',
    )
    expect(en.admin.pos.shiftVarianceMatched).toBe('100% matched with orders and opening float.')

    expect(vi.admin.pos.statusApproved).toBe('Đã duyệt')
    expect(en.admin.pos.statusApproved).toBe('Approved')
  })

  it('contains matching keys for Wallet Deposit modal in both vi and en', () => {
    expect(vi.wallet.depositModal.titleSepay).toBe('Chuyển Khoản Nạp Tiền VietQR SePay 24/7')
    expect(en.wallet.depositModal.titleSepay).toBe('VietQR SePay 24/7 Bank Transfer Top-Up')

    expect(vi.wallet.depositModal.titleStripe).toBe('Thanh Toán Thẻ Quốc Tế Qua Stripe')
    expect(en.wallet.depositModal.titleStripe).toBe('Stripe International Card Payment')

    expect(vi.wallet.depositModal.selectWalletLabel).toBe('Chọn thẻ / ví muốn nạp:')
    expect(en.wallet.depositModal.selectWalletLabel).toBe('Select card / wallet to top up:')

    expect(vi.wallet.depositModal.methodSepayTitle).toBe('Chuyển khoản VietQR SePay (Khuyên dùng)')
    expect(en.wallet.depositModal.methodSepayTitle).toBe('VietQR SePay Bank Transfer (Recommended)')

    expect(vi.wallet.depositModal.vqrListening).toBe(
      'Hệ thống SePay đang tự động lắng nghe giao dịch chuyển khoản...',
    )
    expect(en.wallet.depositModal.vqrListening).toBe(
      'SePay system is automatically listening for incoming bank transfer...',
    )
  })

  it('translates dynamic keys accurately when switching locales via setAppLocale', () => {
    setAppLocale('vi')
    expect(i18n.global.t('admin.pos.qrModalTitle')).toBe('Thanh toán VietQR')
    expect(i18n.global.t('wallet.depositModal.titleSepay')).toBe(
      'Chuyển Khoản Nạp Tiền VietQR SePay 24/7',
    )
    expect(i18n.global.t('wallet.activeCard')).toBe('Đang kích hoạt')

    setAppLocale('en')
    expect(i18n.global.t('admin.pos.qrModalTitle')).toBe('VietQR Payment')
    expect(i18n.global.t('wallet.depositModal.titleSepay')).toBe(
      'VietQR SePay 24/7 Bank Transfer Top-Up',
    )
    expect(i18n.global.t('wallet.activeCard')).toBe('Active')

    // Reset back to vi
    setAppLocale('vi')
    expect(i18n.global.t('wallet.activeCard')).toBe('Đang kích hoạt')
  })
})
