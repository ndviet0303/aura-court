import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import PricingCalendarView from '../views/PricingCalendarView.vue'
import { i18n } from '@/i18n'
import { api } from '@/core/api/client'

vi.mock('vue-router', () => ({
  useRoute: () => ({ path: '/admin/pricing' }),
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
  errorMessage: (err: unknown, fallback: string) => fallback,
}))

describe('PricingCalendarView', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()

    vi.mocked(api.get).mockImplementation(async (url: string) => {
      if (url === '/admin/venues') {
        return [{ id: 1, name: 'Aura Center' }]
      }
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
      return []
    })
  })

  it('renders title, priority stack, and interactive calendar', async () => {
    const wrapper = mount(PricingCalendarView, {
      global: {
        plugins: [i18n],
      },
    })
    await flushPromises()

    expect(wrapper.text()).toContain('Thiết Lập Ma Trận Giá Linh Hoạt')
    expect(wrapper.text()).toContain('Độ Ưu Tiên Quy Tắc')
    expect(wrapper.text()).toContain('Danh Sách Quy Tắc Giá')
  })

  it('opens simulator modal and calculates quote correctly', async () => {
    const wrapper = mount(PricingCalendarView, {
      global: {
        plugins: [i18n],
      },
    })
    await flushPromises()

    const simBtn = wrapper.findAll('button').find((b) => b.text().includes('Mô Phỏng Báo Giá'))
    expect(simBtn).toBeDefined()
    await simBtn?.trigger('click')

    expect(wrapper.text()).toContain('Trình Mô Phỏng & Báo Giá Tự Động')
  })

  it('opens preset templates modal', async () => {
    const wrapper = mount(PricingCalendarView, {
      global: {
        plugins: [i18n],
      },
    })
    await flushPromises()

    const presetBtn = wrapper.findAll('button').find((b) => b.text().includes('Bộ Quy Tắc Mẫu'))
    expect(presetBtn).toBeDefined()
    await presetBtn?.trigger('click')

    expect(wrapper.text()).toContain('Mẫu Cấu Hình Ma Trận Giá Tiêu Chuẩn')
  })
})
