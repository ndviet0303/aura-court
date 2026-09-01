import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import AdminRecurringContractsView from '../AdminRecurringContractsView.vue'
import { i18n } from '@/i18n'
import { api, apiV2 } from '@/core/api/client'

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: vi.fn() }),
  useRoute: () => ({ path: '/admin/contracts' }),
  RouterLink: {
    props: ['to'],
    template: '<a :href="typeof to === \'object\' ? to.path : to"><slot /></a>',
  },
}))

vi.mock('@/core/api/client', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
  },
  apiV2: {
    get: vi.fn(),
    post: vi.fn(),
  },
  errorMessage: (_err: unknown, fallback: string) => fallback,
}))

describe('AdminRecurringContractsView', () => {
  const sampleContracts = [
    {
      id: 1,
      contract_code: 'CTR-2026-001',
      customer_name: 'CLB Cầu Lông Passion',
      phone: '0909 112 233',
      court_name: 'Sân 1 (Sân Huấn Luyện)',
      venue_name: 'Downtown Arena (Quận 1)',
      time_slot: '18:00 - 20:00',
      weekday: 'Thứ 2, 4, 6',
      start_date: '2026-01-01',
      end_date: '2026-12-31',
      total_monthly_amount: 4800000,
      deposit_amount: 5000000,
      status: 'active',
      payment_cycles: [
        { id: 101, cycle_name: 'Kỳ 01/2026', due_date: '05/01/2026', amount: 4800000, status: 'paid', invoice_code: 'INV-2026-0101' },
      ],
    },
    {
      id: 2,
      contract_code: 'CTR-2026-002',
      customer_name: 'CLB Smash Pro',
      phone: '0918 334 455',
      court_name: 'Sân 4 (Thảm Yonex Đạt Chuẩn)',
      venue_name: 'Downtown Arena (Quận 1)',
      time_slot: '19:00 - 21:00',
      weekday: 'Thứ 3, 5, 7',
      start_date: '2026-02-01',
      end_date: '2026-08-01',
      total_monthly_amount: 5200000,
      deposit_amount: 5000000,
      status: 'overdue',
      payment_cycles: [
        { id: 201, cycle_name: 'Kỳ 02/2026', due_date: '05/02/2026', amount: 5200000, status: 'overdue', invoice_code: 'INV-2026-0202' },
      ],
    },
    {
      id: 3,
      contract_code: 'CTR-2026-003',
      customer_name: 'Công ty FPT Software',
      phone: '0988 776 655',
      court_name: 'Sân 2 (Sân Thi Đấu VIP)',
      venue_name: 'Westside Courts (Bình Thạnh)',
      time_slot: '17:30 - 19:30',
      weekday: 'Thứ 4, Thứ 6',
      start_date: '2026-03-01',
      end_date: '2027-03-01',
      total_monthly_amount: 3600000,
      deposit_amount: 4000000,
      status: 'active',
      payment_cycles: [],
    },
  ]

  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    vi.mocked(apiV2.get).mockResolvedValue({ data: sampleContracts })
  })

  it('renders header, title, action buttons, and KPI stat cards correctly', async () => {
    const wrapper = mount(AdminRecurringContractsView, {
      global: {
        plugins: [i18n],
      },
    })
    await flushPromises()

    expect(wrapper.text()).toContain('Quản Lý Hợp Đồng Đặt Sân Định Kỳ')
    expect(wrapper.text()).toContain('Tạo Hợp Đồng Mới')
    expect(wrapper.text()).toContain('Xuất Báo Cáo Excel')
    expect(wrapper.text()).toContain('Tổng Hợp Đồng Đang Chạy')
    expect(wrapper.text()).toContain('Doanh Thu Định Kỳ (MRR)')
    expect(wrapper.text()).toContain('Kỳ Thanh Toán Cần Xử Lý')
    expect(wrapper.text()).toContain('Lấp Đầy Giờ Vàng')
  })

  it('filters contracts when changing status tabs', async () => {
    const wrapper = mount(AdminRecurringContractsView, {
      global: {
        plugins: [i18n],
      },
    })
    await flushPromises()

    // Click "Quá hạn thanh toán" tab
    const overdueTab = wrapper.findAll('.tab-pill').find((btn) => btn.text().includes('Quá hạn thanh toán'))
    expect(overdueTab).toBeDefined()
    await overdueTab?.trigger('click')

    // Table rows should filter to overdue items
    expect(wrapper.text()).toContain('CLB Smash Pro')
    expect(wrapper.text()).not.toContain('CLB Cầu Lông Passion')
  })

  it('filters contracts using search input', async () => {
    const wrapper = mount(AdminRecurringContractsView, {
      global: {
        plugins: [i18n],
      },
    })
    await flushPromises()

    const searchInput = wrapper.find('.search-input')
    await searchInput.setValue('FPT')

    expect(wrapper.text()).toContain('Công ty FPT Software')
    expect(wrapper.text()).not.toContain('CLB Cầu Lông Passion')
  })

  it('opens detail modal when clicking view button', async () => {
    const wrapper = mount(AdminRecurringContractsView, {
      global: {
        plugins: [i18n],
      },
    })
    await flushPromises()

    const viewButton = wrapper.find('[data-testid="btn-view-detail"]')
    expect(viewButton.exists()).toBe(true)
    await viewButton.trigger('click')

    expect(wrapper.find('.modal-card').exists()).toBe(true)
    expect(wrapper.text()).toContain('Lịch Sử Các Kỳ Hóa Đơn Hàng Tháng')
  })

  it('allows creating a new recurring contract via modal', async () => {
    const wrapper = mount(AdminRecurringContractsView, {
      global: {
        plugins: [i18n],
      },
    })
    await flushPromises()

    // Open create modal
    const createBtn = wrapper.find('.btn-primary-pink')
    await createBtn.trigger('click')

    expect(wrapper.text()).toContain('Tạo Hợp Đồng Đặt Sân Định Kỳ')

    // Fill form
    const inputs = wrapper.findAll('.form-input')
    const nameInput = inputs[0]
    const phoneInput = inputs[1]
    expect(nameInput).toBeDefined()
    expect(phoneInput).toBeDefined()
    await nameInput?.setValue('CLB Victor Elite')
    await phoneInput?.setValue('0911.222.333')

    // Submit form
    await wrapper.find('form').trigger('submit.prevent')

    expect(wrapper.text()).toContain('CLB Victor Elite')
    expect(wrapper.find('.toast-floating-banner').text()).toContain('Đã tạo thành công hợp đồng định kỳ mới')
  })
})
