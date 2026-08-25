import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import ErrorModal from '../ErrorModal.vue'

describe('ErrorModal', () => {
  let container: HTMLDivElement

  beforeEach(() => {
    container = document.createElement('div')
    document.body.appendChild(container)
  })

  afterEach(() => {
    document.body.innerHTML = ''
  })

  it('renders modal when open is true', () => {
    mount(ErrorModal, {
      attachTo: container,
      props: {
        open: true,
        title: 'Lỗi xác thực',
        message: 'Thông tin không hợp lệ.',
        details: ['Trường email không đúng', 'Mật khẩu quá ngắn'],
        errorCode: 422,
      },
    })

    expect(document.body.textContent).toContain('Lỗi xác thực')
    expect(document.body.textContent).toContain('Thông tin không hợp lệ.')
    expect(document.body.textContent).toContain('MÃ LỖI: 422')
    expect(document.body.textContent).toContain('Trường email không đúng')
    expect(document.body.textContent).toContain('Mật khẩu quá ngắn')
  })

  it('emits close event when close button is clicked', async () => {
    const wrapper = mount(ErrorModal, {
      attachTo: container,
      props: {
        open: true,
      },
    })

    const closeBtn = document.querySelector('.error-modal-close-btn') as HTMLButtonElement | null
    expect(closeBtn).not.toBeNull()
    closeBtn?.click()

    expect(wrapper.emitted('close')).toBeTruthy()
    expect(wrapper.emitted('update:open')?.[0]).toEqual([false])
  })

  it('emits retry event when retry button is clicked', async () => {
    const wrapper = mount(ErrorModal, {
      attachTo: container,
      props: {
        open: true,
        showRetry: true,
      },
    })

    const retryBtn = document.querySelector('.btn-retry-action') as HTMLButtonElement | null
    expect(retryBtn).not.toBeNull()
    retryBtn?.click()

    expect(wrapper.emitted('retry')).toBeTruthy()
  })
})
