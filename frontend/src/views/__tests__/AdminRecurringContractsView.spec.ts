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
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    vi.mocked(apiV2.get).mockResolvedValue({ data: [] })
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
