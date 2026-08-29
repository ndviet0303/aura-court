import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import V2PricingCalendarView from '../V2PricingCalendarView.vue'
import { i18n } from '@/i18n'
import { apiV2 } from '@/lib/api'

vi.mock('vue-router', () => ({
  useRoute: () => ({ path: '/admin/pricing' }),
  RouterLink: {
    props: ['to'],
    template: '<a :href="typeof to === \'object\' ? to.path : to"><slot /></a>',
  },
}))

vi.mock('@/lib/api', () => ({
  apiV2: {
    get: vi.fn(),
    post: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
  errorMessage: (err: unknown, fallback: string) => fallback,
}))

describe('V2PricingCalendarView', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()

    vi.mocked(apiV2.get).mockImplementation(async (url: string) => {
      if (url === '/pricing/calendar') {
        return {
          rules: [
            {
              id: 1,
              name: 'Tết Nguyên Đán 2025',
              type: 'holiday',
              priority: 1,
              price: 156000,
              adjustment_type: 'percentage_add',
              adjustment_value: 30,
              is_active: true,
              version: 3,
            },
            {
              id: 2,
              name: 'Giờ vàng tối',
              type: 'peak',
              priority: 2,
              price: 180000,
              adjustment_type: 'fixed',
              adjustment_value: 180000,
              starts_at: '17:00',
              ends_at: '21:00',
              weekdays: [1, 2, 3, 4, 5],
              is_active: true,
              version: 1,
            },
          ],
        }
      }
      return {}
    })
  })

  it('renders title, priority stack, and interactive calendar', async () => {
    const wrapper = mount(V2PricingCalendarView, {
      global: {
        plugins: [i18n],
      },
    })
    await flushPromises()

    expect(wrapper.text()).toContain('Thiết lập Ma trận Giá linh hoạt')
    expect(wrapper.text()).toContain('Độ ưu tiên giá')
    expect(wrapper.text()).toContain('Lịch giá')
  })

  it('opens simulator modal and calculates quote correctly', async () => {
    const wrapper = mount(V2PricingCalendarView, {
      global: {
        plugins: [i18n],
      },
    })
    await flushPromises()

    const simBtn = wrapper.findAll('button').find((b) => b.text().includes('Mô phỏng báo giá'))
    expect(simBtn).toBeDefined()
    await simBtn?.trigger('click')

    expect(wrapper.text()).toContain('Trình Mô Phỏng & Báo Giá Tự Động')
  })

  it('opens preset templates modal', async () => {
    const wrapper = mount(V2PricingCalendarView, {
      global: {
        plugins: [i18n],
      },
    })
    await flushPromises()

    const presetBtn = wrapper.findAll('button').find((b) => b.text().includes('Mẫu cài đặt'))
    expect(presetBtn).toBeDefined()
    await presetBtn?.trigger('click')

    expect(wrapper.text()).toContain('Mẫu Cấu Hình Ma Trận Giá Tiêu Chuẩn')
  })
})
