import { describe, it, expect } from 'vitest'
import { errorMessage, isUnverifiedEmailError } from '../errorMessage'

describe('errorMessage utility', () => {
  it('translates unverified email message from backend', () => {
    const error = { message: 'Your email address is not verified.' }
    expect(errorMessage(error)).toBe(
      'Địa chỉ email của bạn chưa được xác thực. Vui lòng xác thực email để thực hiện đặt sân.'
    )
    expect(isUnverifiedEmailError(error)).toBe(true)
  })

  it('translates common unauthenticated and csrf errors', () => {
    expect(errorMessage({ message: 'Unauthenticated.' })).toBe(
      'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.'
    )
    expect(errorMessage('CSRF token mismatch.')).toBe(
      'Phiên làm việc đã hết hạn. Vui lòng tải lại trang.'
    )
  })

  it('joins multiple validation field errors', () => {
    const error = {
      errors: {
        court_id: ['Sân không hợp lệ.'],
        slots: ['Khung giờ đã có người đặt.'],
      },
    }
    const msg = errorMessage(error)
    expect(msg).toContain('Sân không hợp lệ.')
    expect(msg).toContain('Khung giờ đã có người đặt.')
  })

  it('returns fallback message when error is empty', () => {
    expect(errorMessage(null)).toBe('Đã có lỗi xảy ra. Vui lòng thử lại.')
  })
})
