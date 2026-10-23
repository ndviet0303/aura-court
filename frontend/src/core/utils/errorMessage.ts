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
  // Module 05 venue packages (D4, D8).
  PACKAGE_REQUEST_PENDING: {
    vi: 'Cơ sở đang có yêu cầu thanh toán gói khác chưa hết hạn. Hãy thanh toán yêu cầu đó hoặc chờ nó hết hạn.',
    en: 'This venue has another package payment request that has not expired. Pay it or wait until it expires.',
    key: 'errors.packageRequestPending',
  },
  PACKAGE_TERM_LOCKED: {
    vi: 'Gia hạn phải cùng kỳ hạn với gói đang dùng.',
    en: 'A renewal must use the term of the active package.',
    key: 'errors.packageTermLocked',
  },
  PACKAGE_PRICE_UNAVAILABLE: {
    vi: 'Kỳ hạn này chưa có giá áp dụng.',
    en: 'This term has no applicable price yet.',
    key: 'errors.packagePriceUnavailable',
  },
  PACKAGE_NOT_ACTIVE: {
    vi: 'Cơ sở không có gói đang hoạt động để chấm dứt.',
    en: 'This venue has no active package to terminate.',
    key: 'errors.packageNotActive',
  },
  TERMINATION_NOT_CONFIRMED: {
    vi: 'Vui lòng xác nhận mất thời gian còn lại và không được hoàn tiền trước khi chấm dứt gói.',
    en: 'Confirm that the remaining time is lost and nothing is refunded before terminating the package.',
    key: 'errors.terminationNotConfirmed',
  },
  VENUE_CLOSED: {
    vi: 'Cơ sở này đã đóng vĩnh viễn.',
    en: 'This venue is permanently closed.',
    key: 'errors.venueClosed',
  },
  PACKAGE_PAYMENT_UNAVAILABLE: {
    vi: 'Chưa thể nhận phí gói lúc này. Vui lòng thử lại sau.',
    en: 'Package fees cannot be received right now. Please try again later.',
    key: 'errors.packagePaymentUnavailable',
  },
  CHECK_IN_TOO_EARLY: {
    vi: 'Chỉ được check-in sớm tối đa 10 phút trước giờ bắt đầu.',
    en: 'Check-in opens at most 10 minutes before the start time.',
    key: 'errors.checkInTooEarly',
  },
  CHECK_IN_WINDOW_CLOSED: {
    vi: 'Đơn đã hết thời gian sử dụng; chủ cơ sở điều chỉnh nếu khách đã nhận sân.',
    en: 'The booked time is over; the owner adjusts the record if the customer took the court.',
    key: 'errors.checkInWindowClosed',
  },
  COURT_OUT_OF_SERVICE: {
    vi: 'Sân đang ngừng phục vụ, không bàn giao được.',
    en: 'The court is out of service and cannot be handed over.',
    key: 'errors.courtOutOfService',
  },
  BOOKING_NOT_CONFIRMED: {
    vi: 'Chỉ thao tác với đơn đã xác nhận.',
    en: 'Only confirmed bookings can be handled.',
    key: 'errors.bookingNotConfirmed',
  },
  NOT_CHECKED_IN: {
    vi: 'Đơn chưa check-in nên chưa thể check-out.',
    en: 'The booking is not checked in, so it cannot be checked out.',
    key: 'errors.notCheckedIn',
  },
  ALREADY_NO_SHOW: {
    vi: 'Đơn đã được ghi nhận Không đến.',
    en: 'The booking is already recorded as a no-show.',
    key: 'errors.alreadyNoShow',
  },
  ALREADY_COMPLETED: {
    vi: 'Đơn đã hoàn thành, không check-in lại.',
    en: 'The booking is completed and cannot be checked in again.',
    key: 'errors.alreadyCompleted',
  },
  ALREADY_CHECKED_IN: {
    vi: 'Đơn đã check-in, không ghi nhận Không đến.',
    en: 'The booking is checked in, so it cannot be a no-show.',
    key: 'errors.alreadyCheckedIn',
  },
  NO_SHOW_TOO_EARLY: {
    vi: 'Chỉ ghi nhận Không đến sau khi hết thời gian đặt.',
    en: 'A no-show is recorded only after the booked time is over.',
    key: 'errors.noShowTooEarly',
  },
  BOOKING_NOT_FOUND: {
    vi: 'Không tìm thấy đơn đặt sân trong tổ chức.',
    en: 'No booking with this code in the organization.',
    key: 'errors.bookingNotFound',
  },
  VENUE_NOT_ASSIGNED: {
    vi: 'Bạn không được thao tác tại cơ sở này.',
    en: 'You are not assigned to this venue.',
    key: 'errors.venueNotAssigned',
  },
  VENUE_NOT_FOUND: {
    vi: 'Không tìm thấy cơ sở trong tổ chức.',
    en: 'No such venue in the organization.',
    key: 'errors.venueNotFound',
  },
  AFFECTED_CASE_OPEN: {
    vi: 'Đơn đang có case ảnh hưởng chưa xử lý xong, không thể ghi vắng mặt.',
    en: 'The booking has an open affected-booking case, so it cannot be a no-show.',
    key: 'errors.affectedCaseOpen',
  },
  ADJUSTMENT_NO_CHANGE: {
    vi: 'Trạng thái và thời điểm không thay đổi.',
    en: 'The status and times did not change.',
    key: 'errors.adjustmentNoChange',
  },
  CUSTOMER_NOT_FOUND: {
    vi: 'Khách hàng chưa đặt sân tại cơ sở này.',
    en: 'This customer has not booked at this venue.',
    key: 'errors.customerNotFound',
  },
  CONVERSATION_FORBIDDEN: {
    vi: 'Bạn không có quyền truy cập cuộc trò chuyện này.',
    en: 'You cannot access this conversation.',
    key: 'errors.conversationForbidden',
  },
  CHAT_WITH_SELF: {
    vi: 'Không thể trò chuyện với chính mình hoặc cơ sở mình đang quản lý.',
    en: 'You cannot chat with yourself or a venue you run.',
    key: 'errors.chatWithSelf',
  },
  VOUCHER_NEEDS_VENUE_CHAT: {
    vi: 'Chỉ tặng voucher trong cuộc trò chuyện giữa khách và cơ sở.',
    en: 'Vouchers can only be gifted in a customer–venue chat.',
    key: 'errors.voucherNeedsVenueChat',
  },
  VOUCHER_OWNER_ONLY: {
    vi: 'Chỉ chủ cơ sở được tặng voucher.',
    en: 'Only the venue owner can gift vouchers.',
    key: 'errors.voucherOwnerOnly',
  },
  VOUCHER_NOT_GIFTABLE: {
    vi: 'Mã không thuộc voucher riêng đang hiệu lực của cơ sở hoặc đã được dùng.',
    en: 'The code is not a running private voucher of this venue, or it is already used.',
    key: 'errors.voucherNotGiftable',
  },
  FEED_NOT_AUTHOR: {
    vi: 'Chỉ tác giả mới được sửa hoặc xoá nội dung này.',
    en: 'Only the author can edit or delete this content.',
    key: 'errors.feedNotAuthor',
  },
  REPORT_DUPLICATE: {
    vi: 'Bạn đã báo cáo nội dung này và báo cáo đang chờ xử lý.',
    en: 'You already reported this content and the report is pending.',
    key: 'errors.reportDuplicate',
  },
  REPORT_OWN_CONTENT: {
    vi: 'Bạn không thể báo cáo nội dung của chính mình.',
    en: 'You cannot report your own content.',
    key: 'errors.reportOwnContent',
  },
  CONTENT_NOT_HIDDEN: {
    vi: 'Nội dung không ở trạng thái ẩn nên không cần khôi phục.',
    en: 'The content is not hidden, so there is nothing to restore.',
    key: 'errors.contentNotHidden',
  },
  REVIEW_NOT_COMPLETED: {
    vi: 'Chỉ đánh giá được đơn đã hoàn thành (đã check-out).',
    en: 'Only completed (checked-out) bookings can be reviewed.',
    key: 'errors.reviewNotCompleted',
  },
  REVIEW_WINDOW_CLOSED: {
    vi: 'Đã quá 7 ngày kể từ khi check-out nên không thể đánh giá.',
    en: 'More than 7 days have passed since check-out, so it can no longer be reviewed.',
    key: 'errors.reviewWindowClosed',
  },
  REVIEW_EXISTS: {
    vi: 'Đơn này đã có đánh giá (kể cả đánh giá đã xoá).',
    en: 'This booking already has a review (including a deleted one).',
    key: 'errors.reviewExists',
  },
  REVIEW_EDIT_CLOSED: {
    vi: 'Đã quá 7 ngày kể từ lần gửi đầu nên không sửa được đánh giá.',
    en: 'More than 7 days have passed since the review was sent, so it can no longer be edited.',
    key: 'errors.reviewEditClosed',
  },
  REVIEW_NOT_AUTHOR: {
    vi: 'Chỉ người viết mới được sửa hoặc xoá đánh giá.',
    en: 'Only the author can edit or delete the review.',
    key: 'errors.reviewNotAuthor',
  },
  REVIEW_REPLY_FORBIDDEN: {
    vi: 'Chỉ chủ tổ chức sở hữu cơ sở mới được phản hồi đánh giá.',
    en: 'Only the owner of the venue’s organization can reply to reviews.',
    key: 'errors.reviewReplyForbidden',
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
  return (
    String(msg).toLowerCase().includes('not verified') ||
    String(msg).toLowerCase().includes('chưa được xác thực')
  )
}

export function resolveErrorDescriptor(
  error: unknown,
  fallback?: string,
  locale?: string,
): StructuredErrorDescriptor {
  const activeLocale = getEffectiveLocale(locale)
  const defaultFallback =
    fallback ||
    (activeLocale === 'en'
      ? 'An error occurred. Please try again.'
      : 'Đã có lỗi xảy ra. Vui lòng thử lại.')

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
        ? activeLocale === 'en'
          ? 'The server encountered an issue. Please try again later.'
          : 'Máy chủ đang gặp sự cố. Vui lòng thử lại sau.'
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
    const rawMsg = err.message || err.data?.message || err.response?.data?.message || ''
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
      message =
        activeLocale === 'en'
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
      supportCode: isRaw ? (status ? `SRV-${status}` : 'SRV-500') : code ? String(code) : null,
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

export function errorMessage(error: unknown, fallback?: string, locale?: string): string {
  const descriptor = resolveErrorDescriptor(error, fallback, locale)
  return descriptor.message
}
