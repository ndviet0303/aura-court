import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import AdminKycVerificationView from '@/features/admin/views/AdminKycVerificationView.vue'
import { i18n } from '@/i18n'

vi.mock('vue-router', () => ({
  useRoute: () => ({ path: '/admin/kyc' }),
  RouterLink: {
    props: ['to'],
    template: '<a :href="typeof to === \'object\' ? to.path : to"><slot /></a>',
  },
}))

vi.mock('@/core/api/client', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
  apiV2: {
    get: vi.fn(),
    post: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
  errorMessage: (err: unknown, fallback: string) => fallback,
}))

describe('AdminKycVerificationView', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('renders header, 4 KPI stats cards and queue list properly', async () => {
    const wrapper = mount(AdminKycVerificationView, {
      global: {
        plugins: [i18n],
      },
    })

    await flushPromises()

    expect(wrapper.text()).toContain('Phê Duyệt KYC & Thẩm Định Chủ Sân')
    expect(wrapper.text()).toContain('Hồ sơ Chờ thẩm định')
    expect(wrapper.text()).toContain('Độ tin cậy AI OCR & NAPAS')
    expect(wrapper.text()).toContain('Thời gian xử lý trung bình')
    expect(wrapper.text()).toContain('Cảnh báo sai lệch / Rủi ro')

    // Initial selected item from queue
    expect(wrapper.text()).toContain('Smash City Arena')
    expect(wrapper.text()).toContain('KYC-2026-0892')
    expect(wrapper.text()).toContain('1. Xác minh Định danh Người Đại diện (CCCD)')
    expect(wrapper.text()).toContain('2. Giấy Phép Đăng Ký Kinh Doanh (GPKD)')
    expect(wrapper.text()).toContain('3. Xác Thực Tài Khoản Thụ Hưởng (NAPAS 24/7)')
    expect(wrapper.text()).toContain('4. Thẩm Định Cơ Sở Vật Chất & Mặt Bằng Sân')
  })

  it('filters queue list by search query', async () => {
    const wrapper = mount(AdminKycVerificationView, {
      global: {
        plugins: [i18n],
      },
    })

    await flushPromises()

    const searchInput = wrapper.find('.search-input')
    await searchInput.setValue('Tân Bình')

    expect(wrapper.text()).toContain('Sân Cầu Lông Tân Bình Sport')
  })

  it('allows approving host kyc and displays notice alert', async () => {
    const wrapper = mount(AdminKycVerificationView, {
      global: {
        plugins: [i18n],
      },
    })

    await flushPromises()

    const approveBtn = wrapper.find('.btn-action.approve')
    expect(approveBtn.exists()).toBe(true)

    await approveBtn.trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('Hồ sơ KYC đã được phê duyệt và kích hoạt chủ sân thành công.')
  })
})
