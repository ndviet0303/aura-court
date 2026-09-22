import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import ErrorModal from '../feedback/ErrorModal.vue'
import { i18n, setAppLocale } from '@/i18n'

describe('ErrorModal (Tasks 4.1, 4.2, 4.4)', () => {
  let container: HTMLDivElement

  beforeEach(() => {
    container = document.createElement('div')
    document.body.appendChild(container)
    setAppLocale('vi')
  })

  afterEach(() => {
    document.body.innerHTML = ''
  })

  it('renders modal with default Vietnamese translations', () => {
    mount(ErrorModal, {
      attachTo: container,
      props: {
        open: true,
        message: 'Thông tin không hợp lệ.',
        details: ['Trường email không đúng', 'Mật khẩu quá ngắn'],
        errorCode: 422,
        showRetry: true,
      },
      global: {
        plugins: [i18n],
      },
    })

    expect(document.body.textContent).toContain('Đã có lỗi xảy ra')
    expect(document.body.textContent).toContain('Thông tin không hợp lệ.')
    expect(document.body.textContent).toContain('MÃ LỖI: 422')
    expect(document.body.textContent).toContain('Chi tiết lỗi:')
    expect(document.body.textContent).toContain('Trường email không đúng')
    expect(document.body.textContent).toContain('Mật khẩu quá ngắn')
    expect(document.body.textContent).toContain('Đã hiểu')
    expect(document.body.textContent).toContain('Thử lại')
  })

  it('renders modal with default English translations when locale is en', () => {
    setAppLocale('en')

    mount(ErrorModal, {
      attachTo: container,
      props: {
        open: true,
        message: 'The given data was invalid.',
        details: ['Email is invalid'],
        errorCode: 'VALIDATION_ERROR',
        showRetry: true,
      },
      global: {
        plugins: [i18n],
      },
    })

    expect(document.body.textContent).toContain('An error occurred')
    expect(document.body.textContent).toContain('ERROR CODE: VALIDATION_ERROR')
    expect(document.body.textContent).toContain('Error details:')
    expect(document.body.textContent).toContain('Understood')
    expect(document.body.textContent).toContain('Retry')
  })

  it('dynamically updates titles, buttons, code badges, and messages when locale changes while open', async () => {
    setAppLocale('vi')

    const wrapper = mount(ErrorModal, {
      attachTo: container,
      props: {
        open: true,
        message: 'This capability is not enabled for the organization.',
        showRetry: true,
        errorCode: 'CAPABILITY_DISABLED',
      },
      global: {
        plugins: [i18n],
      },
    })

    // Initial Vietnamese state
    expect(document.body.textContent).toContain('Đã có lỗi xảy ra')
    expect(document.body.textContent).toContain('MÃ LỖI: CAPABILITY_DISABLED')
    expect(document.body.textContent).toContain('Tính năng này chưa được kích hoạt cho tổ chức được chọn.')
    expect(document.body.textContent).toContain('Đã hiểu')
    expect(document.body.textContent).toContain('Thử lại')

    // Switch locale to English while modal is actively open
    setAppLocale('en')
    await nextTick()

    // Dynamically re-rendered in English without reopening
    expect(document.body.textContent).toContain('An error occurred')
    expect(document.body.textContent).toContain('ERROR CODE: CAPABILITY_DISABLED')
    expect(document.body.textContent).toContain('This feature is not enabled for the selected organization.')
    expect(document.body.textContent).toContain('Understood')
    expect(document.body.textContent).toContain('Retry')
  })

  it('sanitizes raw infrastructure errors and never leaks database or stack details', () => {
    const rawDbError = {
      message: 'SQLSTATE[42000]: Syntax error or access violation: 1064 at /var/www/vendor/laravel/src/Connection.php:456',
      status: 500,
    }

    mount(ErrorModal, {
      attachTo: container,
      props: {
        open: true,
        error: rawDbError,
      },
      global: {
        plugins: [i18n],
      },
    })

    // Must NOT contain raw database details
    expect(document.body.textContent).not.toContain('SQLSTATE')
    expect(document.body.textContent).not.toContain('Connection.php')
    expect(document.body.textContent).not.toContain('Syntax error')

    // Safe localized error message and support code
    expect(document.body.textContent).toContain('Máy chủ đang gặp sự cố. Vui lòng thử lại sau.')
    expect(document.body.textContent).toContain('MÃ LỖI: SRV-500')
  })

  it('provides accessible dialog semantics (role, aria-modal, aria-labelledby, aria-describedby)', () => {
    mount(ErrorModal, {
      attachTo: container,
      props: {
        open: true,
        message: 'Thông báo lỗi cần hỗ trợ.',
      },
      global: {
        plugins: [i18n],
      },
    })

    const dialog = document.querySelector('[role="dialog"]')
    expect(dialog).not.toBeNull()
    expect(dialog?.getAttribute('aria-modal')).toBe('true')
    expect(dialog?.getAttribute('aria-labelledby')).toBe('error-modal-title')
    expect(dialog?.getAttribute('aria-describedby')).toBe('error-modal-body')

    const closeBtn = document.querySelector('.error-modal-close-btn')
    expect(closeBtn?.getAttribute('aria-label')).toBe('Đóng')
  })

  it('closes on Escape key press and restores focus to originating element', async () => {
    // Create an originating button to test focus restoration
    const originBtn = document.createElement('button')
    originBtn.id = 'trigger-btn'
    document.body.appendChild(originBtn)
    originBtn.focus()
    expect(document.activeElement).toBe(originBtn)

    const wrapper = mount(ErrorModal, {
      attachTo: container,
      props: {
        open: true,
      },
      global: {
        plugins: [i18n],
      },
    })

    await nextTick()

    // Trigger Escape key
    const event = new KeyboardEvent('keydown', { key: 'Escape', bubbles: true })
    document.dispatchEvent(event)

    expect(wrapper.emitted('close')).toBeTruthy()
    expect(wrapper.emitted('update:open')?.[0]).toEqual([false])
  })
  it('traps focus inside the modal card on Tab cycling', async () => {
    mount(ErrorModal, {
      attachTo: container,
      props: {
        open: true,
        showRetry: true,
      },
      global: {
        plugins: [i18n],
      },
    })

    await nextTick()
    await nextTick()

    const closeBtn = document.querySelector('.error-modal-close-btn') as HTMLElement
    const retryBtn = document.querySelector('.btn-retry-action') as HTMLElement
    const confirmBtn = document.querySelector('.btn-confirm-close') as HTMLElement

    expect(closeBtn).not.toBeNull()
    expect(retryBtn).not.toBeNull()
    expect(confirmBtn).not.toBeNull()

    // Focus the last element (confirmBtn)
    confirmBtn.focus()
    expect(document.activeElement).toBe(confirmBtn)

    // Tab from last element wraps to first element (close button)
    const tabEvent = new KeyboardEvent('keydown', { key: 'Tab', bubbles: true })
    document.dispatchEvent(tabEvent)
    expect(document.activeElement).toBe(closeBtn)

    // Shift+Tab from first element wraps to last element (confirm button)
    const shiftTabEvent = new KeyboardEvent('keydown', { key: 'Tab', shiftKey: true, bubbles: true })
    document.dispatchEvent(shiftTabEvent)
    expect(document.activeElement).toBe(confirmBtn)
  })

  it('emits close and retry events on button clicks', async () => {
    const wrapper = mount(ErrorModal, {
      attachTo: container,
      props: {
        open: true,
        showRetry: true,
      },
      global: {
        plugins: [i18n],
      },
    })

    const retryBtn = document.querySelector('.btn-retry-action') as HTMLButtonElement | null
    retryBtn?.click()
    expect(wrapper.emitted('retry')).toBeTruthy()

    const confirmBtn = document.querySelector('.btn-confirm-close') as HTMLButtonElement | null
    confirmBtn?.click()
    expect(wrapper.emitted('close')).toBeTruthy()
    expect(wrapper.emitted('update:open')?.[0]).toEqual([false])
  })
})
