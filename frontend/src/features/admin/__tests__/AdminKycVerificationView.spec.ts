import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import AdminKycVerificationView from '@/features/admin/views/AdminKycVerificationView.vue'
import { i18n } from '@/i18n'
import { apiV2 } from '@/core/api/client'

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

    const testKycPayload = [
      {
        id: 1,
        kyc_code: 'KYC-2026-0892',
        business_type: 'enterprise',
        business_name: 'Smash City Arena',
        legal_name: 'CÔNG TY TNHH THỂ THAO SMASH CITY',
        tax_code: '0316589921',
        tax_status: 'Đang hoạt động',
        representative_name: 'NGUYEN VAN AN',
        id_card_number: '079092008892',
        bank_name: 'MBBank',
        bank_code: 'MBBANK',
        bank_account_number: '0909 888 999',
        bank_account_holder: 'CONG TY TNHH THE THAO SMASH CITY',
        napas_match_status: 'matched',
        court_scale: 12,
        status: 'pending',
        checklist_state: {
          rep_identity_matched: true,
          business_license_valid: true,
          bank_account_matched: true,
          brand_name_discrepancy_accepted: false,
          facility_inspected: true,
        },
      },
      {
        id: 2,
        kyc_code: 'KYC-2026-0891',
        business_type: 'business_household',
        business_name: 'Sân Cầu Lông Tân Bình Sport',
        legal_name: 'HỘ KINH DOANH TÂN BÌNH SPORT',
        tax_code: '8392104921',
        tax_status: 'Đang hoạt động',
        representative_name: 'TRAN THI BICH',
        id_card_number: '079194002341',
        bank_name: 'Techcombank',
        bank_code: 'TCB',
        bank_account_number: '1903 666 888',
        bank_account_holder: 'TRAN THI BICH',
        napas_match_status: 'matched',
        court_scale: 6,
        status: 'pending',
      },
    ]

    vi.mocked(apiV2.get).mockImplementation(((url: string) => {
      if (url.includes('/admin/kyc/stats')) {
        return Promise.resolve({
          stats: {
            pending_count: 2,
            ai_confidence_rate: 98.5,
            avg_processing_time_minutes: 12,
            flagged_count: 0,
            approved_count: 10,
            needs_supplement_count: 1,
          },
        })
      }
      return Promise.resolve({ data: testKycPayload })
    }) as any)

    vi.mocked(apiV2.post).mockResolvedValue({
      message: 'Hồ sơ KYC đã được phê duyệt và kích hoạt chủ sân thành công.',
      data: testKycPayload[0],
    })
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
    await flushPromises()

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
