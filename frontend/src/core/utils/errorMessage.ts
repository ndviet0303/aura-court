import { getCurrentLocale, type AppLocale } from '@/i18n'

export interface StructuredErrorDescriptor {
  code: string | number | null
  messageKey: string | null
  message: string
  details: string[]
  isRawInfrastructure: boolean
  supportCode: string | null
}

const BILINGUAL_TRANSLATIONS: Record<string, { vi: string; en: string }> = {
  'Your email address is not verified.': {
    vi: 'Địa chỉ email của bạn chưa được xác thực. Vui lòng xác thực email để thực hiện đặt sân.',
    en: 'Your email address is not verified. Please verify your email to make a booking.',
  },
  'Unauthenticated.': {
    vi: 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.',
    en: 'Your session has expired. Please log in again.',
  },
  'This action is unauthorized.': {
    vi: 'Bạn không có quyền thực hiện thao tác này.',
    en: 'You do not have permission to perform this action.',
  },
  'CSRF token mismatch.': {
    vi: 'Phiên làm việc đã hết hạn. Vui lòng tải lại trang.',
    en: 'Session expired. Please refresh the page.',
  },
  'Too Many Requests': {
    vi: 'Bạn đã gửi quá nhiều yêu cầu. Vui lòng thử lại sau giây lát.',
    en: 'Too many requests. Please try again shortly.',
  },
  'Too Many Attempts.': {
    vi: 'Bạn đã thử quá số lần cho phép. Vui lòng đợi trong giây lát.',
    en: 'Too many attempts. Please wait a moment.',
  },
  'The given data was invalid.': {
    vi: 'Dữ liệu cung cấp không hợp lệ. Vui lòng kiểm tra lại.',
    en: 'The provided data is invalid. Please check again.',
  },
  'Server Error': {
    vi: 'Máy chủ đang gặp sự cố. Vui lòng thử lại sau.',
    en: 'The server encountered an issue. Please try again later.',
  },
  'The selected court-time is no longer available.': {
    vi: 'Khung giờ bạn chọn trên sân này đã có người đặt hoặc bị trùng lịch. Vui lòng chọn khung giờ khác.',
    en: 'The selected court time is no longer available. Please choose another time.',
  },
  'A recurring series may not exceed a 12-month horizon.': {
    vi: 'Hợp đồng định kỳ không được vượt quá thời hạn 12 tháng.',
    en: 'A recurring series may not exceed a 12-month horizon.',
  },
  'A recurring series may not exceed 104 occurrences.': {
    vi: 'Số buổi trong hợp đồng định kỳ không được vượt quá 104 buổi.',
    en: 'A recurring series may not exceed 104 occurrences.',
  },
  'Frequency must be weekly, biweekly or monthly.': {
    vi: 'Tần suất phải là hàng tuần, 2 tuần/lần hoặc hàng tháng.',
    en: 'Frequency must be weekly, biweekly or monthly.',
  },
  'Price quote has already been accepted.': {
    vi: 'Báo giá đã được chấp nhận trước đó.',
    en: 'Price quote has already been accepted.',
  },
  'Price quote has expired.': {
    vi: 'Báo giá đã hết hạn. Vui lòng thử lại.',
    en: 'Price quote has expired. Please try again.',
  },
  'This capability is not enabled for the organization.': {
    vi: 'Tính năng này chưa được kích hoạt cho tổ chức được chọn.',
    en: 'This feature is not enabled for the selected organization.',
  },
  'Unauthorized access to organization.': {
    vi: 'Bạn không có quyền truy cập vào tổ chức này.',
    en: 'You do not have access to this organization.',
  },
  'Organization context required.': {
    vi: 'Ngữ cảnh tổ chức không hợp lệ hoặc bị thiếu.',
    en: 'Organization context is invalid or missing.',
  },
  'No query results for model': {
    vi: 'Không tìm thấy dữ liệu yêu cầu trong hệ thống.',
    en: 'The requested resource was not found in the system.',
  },
  'Lỗi kết nối mạng. Vui lòng kiểm tra lại đường truyền.': {
    vi: 'Lỗi kết nối mạng. Vui lòng kiểm tra lại đường truyền.',
    en: 'Network connection error. Please check your connection.',
  },
}

export const ERROR_CODE_MAP: Record<string, { vi: string; en: string; key: string }> = {
  CAPABILITY_DISABLED: {
    vi: 'Tính năng này chưa được kích hoạt cho tổ chức được chọn.',
    en: 'This feature is not enabled for the selected organization.',
    key: 'errors.capabilityDisabled',
  },
  ORGANIZATION_ACCESS_DENIED: {
    vi: 'Bạn không có quyền truy cập vào tổ chức này.',
    en: 'You do not have access to this organization.',
    key: 'errors.organizationAccessDenied',
  },
  ORGANIZATION_MISMATCH: {
    vi: 'Ngữ cảnh tổ chức không hợp lệ hoặc bị thiếu.',
    en: 'Organization context is invalid or missing.',
    key: 'errors.organizationMismatch',
  },
  UNAUTHORIZED: {
    vi: 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.',
    en: 'Your session has expired. Please log in again.',
    key: 'errors.unauthorized',
  },
  FORBIDDEN: {
    vi: 'Bạn không có quyền thực hiện thao tác này.',
    en: 'You do not have permission to perform this action.',
    key: 'errors.forbidden',
  },
  VALIDATION_ERROR: {
    vi: 'Dữ liệu cung cấp không hợp lệ. Vui lòng kiểm tra lại.',
    en: 'The provided data is invalid. Please check and try again.',
    key: 'errors.validationError',
  },
  RESOURCE_CONFLICT: {
    vi: 'Tài nguyên đã bị thay đổi hoặc có xung đột. Vui lòng thử lại.',
    en: 'The resource has changed or there is a conflict. Please try again.',
    key: 'errors.resourceConflict',
  },
  NOT_FOUND: {
    vi: 'Không tìm thấy dữ liệu yêu cầu trong hệ thống.',
    en: 'The requested resource was not found in the system.',
    key: 'errors.notFound',
  },
  NETWORK_ERROR: {
    vi: 'Lỗi kết nối mạng. Vui lòng kiểm tra lại đường truyền.',
    en: 'Network connection error. Please check your connection.',
    key: 'errors.networkError',
  },
  SERVER_ERROR: {
    vi: 'Máy chủ đang gặp sự cố. Vui lòng thử lại sau.',
    en: 'The server encountered an issue. Please try again later.',
    key: 'errors.serverError',
  },
}

export function isRawInfrastructureMessage(msg: string): boolean {
  if (!msg || typeof msg !== 'string') return false
  const lower = msg.toLowerCase()
  return (
    lower.includes('sqlstate') ||
    lower.includes('pdoexception') ||
    lower.includes('syntax error or access violation') ||
    lower.includes('connection refused') ||
    lower.includes('select ') ||
    lower.includes('insert into') ||
    lower.includes('update ') ||
    lower.includes('delete from') ||
    lower.includes('stack trace') ||
    lower.includes('vendor/laravel') ||
    lower.includes('call to undefined') ||
    lower.includes('uncaught exception') ||
    /at\s+\S+\.php:\d+/i.test(msg)
  )
}

function getEffectiveLocale(explicitLocale?: string): AppLocale {
  if (explicitLocale === 'vi' || explicitLocale === 'en') return explicitLocale
  try {
    return getCurrentLocale()
  } catch {
    return 'vi'
  }
}

export function translateMessage(msg: string, locale?: string): string {
  const activeLocale = getEffectiveLocale(locale)

  if (isRawInfrastructureMessage(msg)) {
    return activeLocale === 'en'
      ? 'The server encountered an issue. Please try again later.'
      : 'Máy chủ đang gặp sự cố. Vui lòng thử lại sau.'
  }

  if (BILINGUAL_TRANSLATIONS[msg]) {
    return BILINGUAL_TRANSLATIONS[msg][activeLocale]
  }

  for (const [key, translated] of Object.entries(BILINGUAL_TRANSLATIONS)) {
    if (msg.includes(key)) {
      return translated[activeLocale]
    }
  }

  if (msg.includes('No query results for model')) {
    return activeLocale === 'en'
      ? 'The requested resource was not found in the system.'
      : 'Không tìm thấy dữ liệu yêu cầu trong hệ thống.'
  }

  return msg
}

export function isUnverifiedEmailError(error: unknown): boolean {
  if (!error) return false
  const msg = typeof error === 'string' ? error : (error as Record<string, any>)?.message || ''
  return String(msg).toLowerCase().includes('not verified') || String(msg).toLowerCase().includes('chưa được xác thực')
}

export function resolveErrorDescriptor(
  error: unknown,
  fallback?: string,
  locale?: string
): StructuredErrorDescriptor {
  const activeLocale = getEffectiveLocale(locale)
  const defaultFallback = fallback || (activeLocale === 'en' ? 'An error occurred. Please try again.' : 'Đã có lỗi xảy ra. Vui lòng thử lại.')

  if (!error) {
    return {
      code: null,
      messageKey: 'errors.unknown',
      message: defaultFallback,
      details: [],
      isRawInfrastructure: false,
      supportCode: null,
    }
  }

  if (typeof error === 'string') {
    const isRaw = isRawInfrastructureMessage(error)
    return {
      code: isRaw ? 'SERVER_ERROR' : null,
      messageKey: isRaw ? 'errors.serverError' : null,
      message: isRaw
        ? (activeLocale === 'en' ? 'The server encountered an issue. Please try again later.' : 'Máy chủ đang gặp sự cố. Vui lòng thử lại sau.')
        : translateMessage(error, activeLocale),
      details: [],
      isRawInfrastructure: isRaw,
      supportCode: isRaw ? 'SRV-500' : null,
    }
  }

  if (typeof error === 'object') {
    const err = error as Record<string, any>
    const code = (err.code as string | number) || null
    const status = (err.status as number) || null
    let rawMsg = err.message || err.data?.message || err.response?.data?.message || ''
    const isRaw = isRawInfrastructureMessage(String(rawMsg))

    // Extract validation details if available
    const details: string[] = []
    if (err.errors && typeof err.errors === 'object') {
      const fieldErrors = Object.values(err.errors as Record<string, string[]>)
      const allMessages = fieldErrors
        .flat()
        .filter((m): m is string => typeof m === 'string' && m.trim().length > 0)
        .map((m) => translateMessage(m, activeLocale))
      details.push(...allMessages)
    }

    // Determine messageKey and translated message
    let messageKey: string | null = null
    let message = ''

    if (isRaw) {
      messageKey = 'errors.serverError'
      message = activeLocale === 'en'
        ? 'The server encountered an issue. Please try again later.'
        : 'Máy chủ đang gặp sự cố. Vui lòng thử lại sau.'
    } else if (code && ERROR_CODE_MAP[String(code)]) {
      const mapped = ERROR_CODE_MAP[String(code)]!
      messageKey = mapped.key
      message = mapped[activeLocale]
    } else if (details.length > 0) {
      messageKey = 'errors.validationError'
      message = details.join(' ')
    } else if (rawMsg) {
      message = translateMessage(rawMsg, activeLocale)
    } else {
      message = defaultFallback
    }

    return {
      code: code || (status ? String(status) : null),
      messageKey,
      message,
      details,
      isRawInfrastructure: isRaw,
      supportCode: isRaw ? (status ? `SRV-${status}` : 'SRV-500') : (code ? String(code) : null),
    }
  }

  return {
    code: null,
    messageKey: 'errors.unknown',
    message: defaultFallback,
    details: [],
    isRawInfrastructure: false,
    supportCode: null,
  }
}

export function errorMessage(
  error: unknown,
  fallback?: string,
  locale?: string
): string {
  const descriptor = resolveErrorDescriptor(error, fallback, locale)
  return descriptor.message
}
