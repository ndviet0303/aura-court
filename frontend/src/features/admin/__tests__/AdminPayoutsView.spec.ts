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

    vi.mocked(api.get).mockImplementation(async (url: string) => {
      if (url.includes('/finance/settlements')) {
        return {
          data: [
            {
              id: 1,
              period_end: '2023-10-15',
              gross_amount: 12500000,
              platform_fee: 375000,
              gateway_fee: 250000,
              net_amount: 11875000,
              status: 'settled',
            },
            {
              id: 2,
              period_end: '2023-10-16',
              gross_amount: 15000000,
              platform_fee: 450000,
              gateway_fee: 300000,
              net_amount: 14250000,
              status: 'settled',
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
              created_at: '2023-10-01',
              completed_at: '2023-10-02',
              amount: 50000000,
              status: 'paid',
              beneficiary: {
                bank_code: 'VCB',
                account_number: '0123456789',
                account_holder: 'CONG TY TNHH SMASH',
              },
            },
            {
              id: 2,
              reference: 'WD-1023-01',
              created_at: '2023-10-18',
              completed_at: null,
              amount: 25000000,
              status: 'processing',
              beneficiary: {
                bank_code: 'VCB',
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
            account_number: '0123456789',
            account_holder: 'Cong Ty TNHH Smash',
            tax_id: '0316888999',
          },
        ]
      }
      if (url.includes('/finance/exceptions')) {
        return {
          data: [],
        }
      }
      return { data: {} }
    })
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
    expect(wrapper.text()).toContain('KYC VERIFIED')
    expect(wrapper.text()).toContain('Vietcombank')
  })

  it('renders visual 5-step stepper and payout records', async () => {
    const wrapper = mount(AdminPayoutsView, {
      global: {
        plugins: [i18n],
      },
    })
    await flushPromises()

    expect(wrapper.findAll('.step-node').length).toBe(5)
    expect(wrapper.text()).toContain('Tạo lệnh')
    expect(wrapper.text()).toContain('Duyệt')
    expect(wrapper.text()).toContain('Gửi yêu cầu')
    expect(wrapper.text()).toContain('Đang chi trả')
    expect(wrapper.text()).toContain('Hoàn tất')

    expect(wrapper.text()).toContain('WD-0923-01')
    expect(wrapper.text()).toContain('WD-1023-01')
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

    // Set valid payout amount
    const amountInput = wrapper.find('.form-input[type="number"]')
    await amountInput.setValue(1000000)

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
    const accountInput = inputs[1]
    const holderInput = inputs[2]
    expect(accountInput).toBeDefined()
    expect(holderInput).toBeDefined()
    if (accountInput) await accountInput.setValue('9876543210')
    if (holderInput) await holderInput.setValue('AURA SPORTS ENTERPRISE')

    await wrapper.find('.modal-form').trigger('submit.prevent')
    await flushPromises()

    expect(wrapper.text()).toContain('thành công')
  })
})
