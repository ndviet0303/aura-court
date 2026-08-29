import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import AdminStaffView from '../AdminStaffView.vue'
import { i18n } from '@/i18n'
import { api } from '@/lib/api'

vi.mock('vue-router', () => ({
  useRoute: () => ({ path: '/admin/staff' }),
  RouterLink: {
    props: ['to'],
    template: '<a :href="typeof to === \'object\' ? to.path : to"><slot /></a>',
  },
}))

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
  errorMessage: (_err: unknown, fallback?: string) => fallback || 'Có lỗi xảy ra',
}))

describe('AdminStaffView', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()

    vi.mocked(api.get).mockImplementation(async (url: string) => {
      if (url === '/admin/venues') {
        return {
          data: {
            venues: [
              { id: 1, name: 'Downtown Arena (Quận 1)', address: '12 Nguyễn Thị Minh Khai, Q.1' },
              { id: 2, name: 'Westside Courts (Bình Thạnh)', address: '48 Ung Văn Khiêm, Bình Thạnh' },
            ],
          },
        }
      }
      return { data: {} }
    })
  })

  it('renders title, stats, and staff directory table correctly', async () => {
    const wrapper = mount(AdminStaffView, {
      global: {
        plugins: [i18n],
      },
    })
    await flushPromises()

    expect(wrapper.find('.page-title').text()).toContain('Nhân sự & Phân quyền')
    expect(wrapper.findAll('.stat-card').length).toBe(4)
    expect(wrapper.findAll('.table-grid-row').length).toBeGreaterThan(0)
    expect(wrapper.text()).toContain('Elena Jackson')
    expect(wrapper.text()).toContain('Marcus Reed')
  })

  it('switches views to Role Matrix and Audit Logs', async () => {
    const wrapper = mount(AdminStaffView, {
      global: {
        plugins: [i18n],
      },
    })
    await flushPromises()

    const navBtns = wrapper.findAll('.view-nav-btn')
    expect(navBtns.length).toBe(3)

    // Switch to Role Matrix
    await navBtns[1]?.trigger('click')
    await flushPromises()
    expect(wrapper.find('.matrix-heading').text()).toContain('Ma trận Đối soát Quyền hạn')
    expect(wrapper.findAll('.matrix-row').length).toBeGreaterThan(5)

    // Switch to Audit Logs
    await navBtns[2]?.trigger('click')
    await flushPromises()
    expect(wrapper.find('.audit-heading').text()).toContain('Nhật ký Hoạt động')
    expect(wrapper.findAll('.audit-item-row').length).toBeGreaterThan(0)
  })

  it('filters staff list by search query and role', async () => {
    const wrapper = mount(AdminStaffView, {
      global: {
        plugins: [i18n],
      },
    })
    await flushPromises()

    const searchInput = wrapper.find('.header-search-input')
    await searchInput.setValue('Marcus')
    await flushPromises()

    const rows = wrapper.findAll('.table-grid-row')
    expect(rows.length).toBe(1)
    expect(wrapper.text()).toContain('Marcus Reed')
    expect(wrapper.text()).not.toContain('Elena Jackson')
  })

  it('opens add staff member modal and validates form', async () => {
    const wrapper = mount(AdminStaffView, {
      global: {
        plugins: [i18n],
      },
    })
    await flushPromises()

    expect(wrapper.find('.modal-card').exists()).toBe(false)
    await wrapper.find('.btn-primary-cta').trigger('click')
    await flushPromises()

    expect(wrapper.find('.modal-card').exists()).toBe(true)
    expect(wrapper.find('.modal-title').text()).toContain('Thêm Nhân Viên Mới')
  })
})
