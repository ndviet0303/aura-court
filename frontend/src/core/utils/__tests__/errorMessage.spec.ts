import { describe, it, expect, beforeEach } from 'vitest'
import {
  errorMessage,
  resolveErrorDescriptor,
  isRawInfrastructureMessage,
  isUnverifiedEmailError,
  translateMessage,
  ERROR_CODE_MAP,
} from '../errorMessage'
import { setAppLocale, i18n } from '@/i18n'
import { useErrorModal } from '@/core/composables/useErrorModal'

describe('errorMessage and resolveErrorDescriptor (Tasks 4.1, 4.2, 4.3)', () => {
  beforeEach(() => {
    setAppLocale('vi')
  })

  describe('Task 4.1: Stable business error codes', () => {
    it('translates CAPABILITY_DISABLED in both Vietnamese and English', () => {
      const err = { code: 'CAPABILITY_DISABLED', status: 404 }

      const descVi = resolveErrorDescriptor(err, undefined, 'vi')
      expect(descVi.message).toBe('Tính năng này chưa được kích hoạt cho tổ chức được chọn.')
      expect(descVi.code).toBe('CAPABILITY_DISABLED')

      const descEn = resolveErrorDescriptor(err, undefined, 'en')
      expect(descEn.message).toBe('This feature is not enabled for the selected organization.')
      expect(descEn.code).toBe('CAPABILITY_DISABLED')
    })

    it('translates ORGANIZATION_ACCESS_DENIED and ORGANIZATION_MISMATCH', () => {
      const deniedVi = errorMessage({ code: 'ORGANIZATION_ACCESS_DENIED' }, undefined, 'vi')
      expect(deniedVi).toBe('Bạn không có quyền truy cập vào tổ chức này.')

      const deniedEn = errorMessage({ code: 'ORGANIZATION_ACCESS_DENIED' }, undefined, 'en')
      expect(deniedEn).toBe('You do not have access to this organization.')

      const mismatchVi = errorMessage({ code: 'ORGANIZATION_MISMATCH' }, undefined, 'vi')
      expect(mismatchVi).toBe('Ngữ cảnh tổ chức không hợp lệ hoặc bị thiếu.')

      const mismatchEn = errorMessage({ code: 'ORGANIZATION_MISMATCH' }, undefined, 'en')
      expect(mismatchEn).toBe('Organization context is invalid or missing.')
    })

    it('translates UNAUTHORIZED, FORBIDDEN, NOT_FOUND, RESOURCE_CONFLICT, VALIDATION_ERROR, and SERVER_ERROR', () => {
      const codes = [
        'UNAUTHORIZED',
        'FORBIDDEN',
        'NOT_FOUND',
        'RESOURCE_CONFLICT',
        'VALIDATION_ERROR',
        'SERVER_ERROR',
        'NETWORK_ERROR',
      ] as const

      for (const code of codes) {
        const msgVi = errorMessage({ code }, undefined, 'vi')
        const msgEn = errorMessage({ code }, undefined, 'en')

        expect(msgVi).toBe(ERROR_CODE_MAP[code]?.vi)
        expect(msgEn).toBe(ERROR_CODE_MAP[code]?.en)
      }
    })
  })

  describe('Task 4.2: Safe infrastructure detection and sanitization', () => {
    it('detects raw SQL queries, PDO exceptions, and stack traces', () => {
      expect(
        isRawInfrastructureMessage('SQLSTATE[23505]: Unique violation: 7 ERROR: duplicate key'),
      ).toBe(true)
      expect(
        isRawInfrastructureMessage(
          'PDOException: Connection refused in /var/www/vendor/laravel/framework',
        ),
      ).toBe(true)
      expect(isRawInfrastructureMessage('SELECT * FROM users WHERE id = 1')).toBe(true)
      expect(
        isRawInfrastructureMessage('Stack trace:\n#0 /app/Http/Controllers/PosController.php(42)'),
      ).toBe(true)
      expect(isRawInfrastructureMessage('Call to undefined method App\\Models\\User::fake()')).toBe(
        true,
      )
      expect(isRawInfrastructureMessage('Tính năng này chưa được kích hoạt')).toBe(false)
      expect(isRawInfrastructureMessage('Khung giờ bạn chọn đã có người đặt')).toBe(false)
    })

    it('sanitizes raw infrastructure errors and never leaks internal details', () => {
      const rawError = {
        message:
          'SQLSTATE[42S02]: Base table or view not found: 1146 Table "booking.venues" doesn\'t exist at /vendor/laravel/framework/src/Database.php:123',
        status: 500,
      }

      const descVi = resolveErrorDescriptor(rawError, undefined, 'vi')
      expect(descVi.isRawInfrastructure).toBe(true)
      expect(descVi.message).not.toContain('SQLSTATE')
      expect(descVi.message).not.toContain('booking.venues')
      expect(descVi.message).not.toContain('Database.php')
      expect(descVi.message).toBe('Máy chủ đang gặp sự cố. Vui lòng thử lại sau.')
      expect(descVi.supportCode).toBe('SRV-500')

      const descEn = resolveErrorDescriptor(rawError, undefined, 'en')
      expect(descEn.isRawInfrastructure).toBe(true)
      expect(descEn.message).toBe('The server encountered an issue. Please try again later.')
    })

    it('sanitizes string-based database error', () => {
      const msg = 'PDOException: Connection refused in vendor/laravel at line 42'
      const sanitizedVi = errorMessage(msg, undefined, 'vi')
      expect(sanitizedVi).toBe('Máy chủ đang gặp sự cố. Vui lòng thử lại sau.')

      const sanitizedEn = errorMessage(msg, undefined, 'en')
      expect(sanitizedEn).toBe('The server encountered an issue. Please try again later.')
    })
  })

  describe('Task 4.3: useErrorModal deduplication and state preservation', () => {
    it('coalesces concurrent failures into a single open dialog without duplicate stacking', () => {
      const { isOpen, descriptor, showError } = useErrorModal()

      expect(isOpen.value).toBe(false)

      // Simulate 3 concurrent failed requests from parallel loading
      showError({ code: 'CAPABILITY_DISABLED' })
      expect(isOpen.value).toBe(true)
      expect(descriptor.value?.code).toBe('CAPABILITY_DISABLED')

      showError({ code: 'NETWORK_ERROR' })
      // Dialog remains open (not stacked) and updates descriptor to latest
      expect(isOpen.value).toBe(true)
      expect(descriptor.value?.code).toBe('NETWORK_ERROR')
    })

    it('preserves operationKey across retries for mutation idempotency without duplicating sales', async () => {
      const { isOpen, operationKey, showError, retry } = useErrorModal()

      let attempts = 0
      const mockMutation = async () => {
        attempts++
      }

      // Initial mutation fails with stable logical key
      showError(new Error('Transient network glitch'), {
        operationKey: 'ORDER-MUTATION-UUID-999',
        onRetry: mockMutation,
      })

      expect(isOpen.value).toBe(true)
      expect(operationKey.value).toBe('ORDER-MUTATION-UUID-999')

      // Retry executes the handler using the retained key
      await retry()

      expect(attempts).toBe(1)
      expect(isOpen.value).toBe(false)
      // Logical key is preserved, not corrupted or discarded
      expect(operationKey.value).toBe('ORDER-MUTATION-UUID-999')
    })
  })

  describe('Validation errors and unverified email', () => {
    it('extracts and translates validation details', () => {
      const err = {
        errors: {
          email: ['The given data was invalid.'],
          time: ['The selected court-time is no longer available.'],
        },
      }

      const desc = resolveErrorDescriptor(err, undefined, 'vi')
      expect(desc.details).toContain('Dữ liệu cung cấp không hợp lệ. Vui lòng kiểm tra lại.')
      expect(desc.details).toContain(
        'Khung giờ bạn chọn trên sân này đã có người đặt hoặc bị trùng lịch. Vui lòng chọn khung giờ khác.',
      )
    })

    it('identifies unverified email errors', () => {
      expect(isUnverifiedEmailError('Your email address is not verified.')).toBe(true)
      expect(isUnverifiedEmailError({ message: 'Địa chỉ email của bạn chưa được xác thực' })).toBe(
        true,
      )
      expect(isUnverifiedEmailError('Other error')).toBe(false)
    })
  })
})
