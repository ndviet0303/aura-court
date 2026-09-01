const TRANSLATIONS: Record<string, string> = {
  'Your email address is not verified.': 'Địa chỉ email của bạn chưa được xác thực. Vui lòng xác thực email để thực hiện đặt sân.',
  'Unauthenticated.': 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.',
  'This action is unauthorized.': 'Bạn không có quyền thực hiện thao tác này.',
  'CSRF token mismatch.': 'Phiên làm việc đã hết hạn. Vui lòng tải lại trang.',
  'Too Many Requests': 'Bạn đã gửi quá nhiều yêu cầu. Vui lòng thử lại sau giây lát.',
  'Too Many Attempts.': 'Bạn đã thử quá số lần cho phép. Vui lòng đợi trong giây lát.',
  'The given data was invalid.': 'Dữ liệu cung cấp không hợp lệ. Vui lòng kiểm tra lại.',
  'Server Error': 'Máy chủ đang gặp sự cố. Vui lòng thử lại sau.',
  'The selected court-time is no longer available.': 'Khung giờ bạn chọn trên sân này đã có người đặt hoặc bị trùng lịch. Vui lòng chọn khung giờ khác.',
  'A recurring series may not exceed a 12-month horizon.': 'Hợp đồng định kỳ không được vượt quá thời hạn 12 tháng.',
  'A recurring series may not exceed 104 occurrences.': 'Số buổi trong hợp đồng định kỳ không được vượt quá 104 buổi.',
  'Frequency must be weekly, biweekly or monthly.': 'Tần suất phải là hàng tuần, 2 tuần/lần hoặc hàng tháng.',
  'Price quote has already been accepted.': 'Báo giá đã được chấp nhận trước đó.',
  'Price quote has expired.': 'Báo giá đã hết hạn. Vui lòng thử lại.',
}

function translateMessage(msg: string): string {
  if (TRANSLATIONS[msg]) return TRANSLATIONS[msg]
  for (const [key, translated] of Object.entries(TRANSLATIONS)) {
    if (msg.includes(key)) return translated
  }
  if (msg.includes('No query results for model')) {
    return 'Không tìm thấy dữ liệu yêu cầu trong hệ thống.'
  }
  return msg
}

export function isUnverifiedEmailError(error: unknown): boolean {
  if (!error) return false
  const msg = typeof error === 'string' ? error : (error as Record<string, any>)?.message || ''
  return String(msg).toLowerCase().includes('not verified') || String(msg).toLowerCase().includes('chưa được xác thực')
}

export function errorMessage(
  error: unknown,
  fallback: string = 'Đã có lỗi xảy ra. Vui lòng thử lại.'
): string {
  if (!error) return fallback
  if (typeof error === 'string') return translateMessage(error)

  if (typeof error === 'object') {
    const err = error as Record<string, any>

    // Handle Laravel validation errors object: { errors: { field: ['message1', 'message2'] } }
    if (err.errors && typeof err.errors === 'object') {
      const fieldErrors = Object.values(err.errors as Record<string, string[]>)
      const allMessages = fieldErrors
        .flat()
        .filter((msg): msg is string => typeof msg === 'string' && msg.trim().length > 0)
        .map(translateMessage)

      if (allMessages.length > 0) {
        return allMessages.join(' ')
      }
    }

    // Direct error message on payload
    const rawMsg = err.message || err.data?.message || err.response?.data?.message
    if (typeof rawMsg === 'string' && rawMsg.trim().length > 0) {
      return translateMessage(rawMsg)
    }
  }

  return fallback
}
