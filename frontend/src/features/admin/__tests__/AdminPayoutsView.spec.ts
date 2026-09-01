import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import AdminPayoutsView from '@/features/finance/views/AdminPayoutsView.vue'
import { i18n } from '@/i18n'
import { api, apiV2 } from '@/core/api/client'

vi.mock('vue-router', () => ({
  useRoute: () => ({ path: '/admin/payouts' }),
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
  errorMessage: (_err: unknown, fallback?: string) => fallback || 'Có lỗi xảy ra',
}))

describe('AdminPayoutsView', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()

    const mockGet = async (url: string) => {
      if (url.includes('/finance/settlements')) {
        return {
          data: [
            {
              id: 1,
              period_start: '2026-08-01',
              period_end: '2026-08-15',
              gross_amount: 12500000,
              provider_fee_amount: 250000,
              commission_amount: 375000,
              tenant_payable: 11875000,
              status: 'settled',
              eligible_at: '2026-08-17',
            },
            {
              id: 2,
              period_start: '2026-08-16',
              period_end: '2026-08-31',
              gross_amount: 15000000,
              provider_fee_amount: 300000,
              commission_amount: 450000,
              tenant_payable: 14250000,
              status: 'eligible',
              eligible_at: '2026-09-02',
            },
          ],
        }
      }
      if (url.includes('/finance/payouts')) {
        return {
          data: [
            {
              id: 1,
              reference: 'WD-0923-01',
              created_at: '2026-08-18',
              completed_at: '2026-08-18',
              amount: 11875000,
              status: 'paid',
              beneficiary: {
                bank_code: 'VCB',
                bank_name: 'Vietcombank',
                account_number: '0123456789',
                account_holder: 'CONG TY TNHH SMASH',
              },
            },
            {
              id: 2,
              reference: 'WD-1023-01',
              created_at: '2026-09-01',
              completed_at: null,
              amount: 14250000,
              status: 'approved',
              beneficiary: {
                bank_code: 'VCB',
                bank_name: 'Vietcombank',
                account_number: '0123456789',
                account_holder: 'CONG TY TNHH SMASH',
              },
            },
          ],
        }
      }
      if (url.includes('/finance/beneficiaries')) {
        return [
          {
            id: 1,
            bank_code: 'VCB',
            bank_name: 'Vietcombank',
            account_number: '0123456789',
            account_holder: 'Cong Ty TNHH Smash',
            tax_id: '0316888999',
            is_verified: true,
            status: 'verified',
          },
        ]
      }
      return { data: [] }
    }

    vi.mocked(api.get).mockImplementation(mockGet)
    vi.mocked(apiV2.get).mockImplementation(mockGet)

    const mockPost = async (url: string, payload: any) => {
      if (url.includes('/finance/payouts') && !url.includes('/approve') && !url.includes('/submit') && !url.includes('/status')) {
        return {
          payout: {
            id: 3,
            reference: 'WD-000003',
            amount: 1000000,
            status: 'draft',
            created_at: '2026-09-02',
          },
        }
      }
      if (url.includes('/finance/beneficiaries')) {
        return {
          beneficiary: {
            id: 1,
            ...payload,
            is_verified: true,
            status: 'verified',
          },
        }
      }
      if (url.includes('/approve')) {
        return { payout: { id: 2, status: 'approved' } }
      }
      if (url.includes('/submit')) {
        return { payout: { id: 2, status: 'submitted' } }
      }
      if (url.includes('/snapshot')) {
        return {
          settlement: {
            id: 3,
            period_start: payload.period_start,
            period_end: payload.period_end,
            gross_amount: 0,
            tenant_payable: 0,
            status: 'pending',
          },
        }
      }
      return {}
    }

    vi.mocked(api.post).mockImplementation(mockPost)
    vi.mocked(apiV2.post).mockImplementation(mockPost)
  })

  it('renders header, settlement metrics, table and beneficiary info correctly', async () => {
    const wrapper = mount(AdminPayoutsView, {
      global: {
        plugins: [i18n],
      },
    })
    await flushPromises()

    expect(wrapper.find('.page-title').text()).toContain('Báo cáo Doanh thu & Rút tiền')
    expect(wrapper.find('.gross-box').text()).toContain('TỔNG DOANH THU')
    expect(wrapper.find('.fee-box').text()).toContain('PHÍ & HOA HỒNG')
    expect(wrapper.find('.net-box').text()).toContain('THỰC NHẬN')
    expect(wrapper.text()).toContain('KYC')
    expect(wrapper.text()).toContain('Vietcombank')
    expect(wrapper.text()).toContain('WD-0923-01')
  })

  it('switches tabs and renders visual 5-step stepper and settlement periods', async () => {
    const wrapper = mount(AdminPayoutsView, {
      global: {
        plugins: [i18n],
      },
    })
    await flushPromises()

    // Switch to settlements tab
    const tabBtns = wrapper.findAll('.view-nav-btn')
    expect(tabBtns.length).toBe(3)
    await tabBtns[1].trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('Kỳ Đối Soát Doanh Thu')
    expect(wrapper.text()).toContain('2026')

    // Switch to flow tab
    await tabBtns[2].trigger('click')
    await flushPromises()

    expect(wrapper.findAll('.step-node').length).toBe(5)
    expect(wrapper.text()).toContain('Tạo Lệnh')
    expect(wrapper.text()).toContain('Phê Duyệt')
    expect(wrapper.text()).toContain('Gửi Lệnh')
    expect(wrapper.text()).toContain('Đang Chi Trả')
    expect(wrapper.text()).toContain('Hoàn Tất')
  })

  it('opens create payout modal and handles submission', async () => {
    const wrapper = mount(AdminPayoutsView, {
      global: {
        plugins: [i18n],
      },
    })
    await flushPromises()

    const openBtn = wrapper.find('.btn-primary-action')
    await openBtn.trigger('click')

    expect(wrapper.find('.aura-modal-card').exists()).toBe(true)
    expect(wrapper.find('.modal-title').text()).toContain('Tạo Lệnh Rút Tiền')

    // Submit form
    await wrapper.find('.modal-form').trigger('submit.prevent')
    await flushPromises()

    expect(wrapper.text()).toContain('thành công')
  })

  it('opens edit beneficiary modal and updates bank information', async () => {
    const wrapper = mount(AdminPayoutsView, {
      global: {
        plugins: [i18n],
      },
    })
    await flushPromises()

    const editBtn = wrapper.find('.btn-update-beneficiary')
    await editBtn.trigger('click')

    expect(wrapper.find('.modal-title').text()).toContain('Cập Nhật Tài Khoản Thụ Hưởng')

    const inputs = wrapper.findAll('.form-input')
    const accountInput = inputs[0]
    const holderInput = inputs[1]
    expect(accountInput).toBeDefined()
    expect(holderInput).toBeDefined()
    if (accountInput) await accountInput.setValue('9876543210')
    if (holderInput) await holderInput.setValue('AURA SPORTS ENTERPRISE')

    await wrapper.find('.modal-form').trigger('submit.prevent')
    await flushPromises()

    expect(wrapper.text()).toContain('thành công')
  })
})
