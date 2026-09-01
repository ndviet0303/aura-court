import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import CreateMatchView from '../views/CreateMatchView.vue'
import { i18n } from '@/i18n'
import { api, apiV2 } from '@/core/api/client'

const pushMock = vi.fn()

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: pushMock }),
  useRoute: () => ({ path: '/matchmaking/create', query: {} }),
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

describe('CreateMatchView', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()

    vi.mocked(api.get).mockImplementation(async (url: string) => {
      if (url.startsWith('/courts')) {
        return [
          {
            id: 1,
            name: 'Sân VIP 1',
            venue: { id: 1, name: 'Aura Central', slug: 'aura-central', address: '123 Le Loi', google_maps_url: null, is_active: true, share_url: '' },
          },
        ]
      }
      return []
    })

    vi.mocked(apiV2.post).mockResolvedValue({
      match: { id: 888, title: 'Aura Central Court - Sân VIP 1' },
    })
  })

  it('renders correctly with default hero header and match form', async () => {
    const wrapper = mount(CreateMatchView, {
      global: {
        plugins: [i18n],
      },
    })
    await flushPromises()

    expect(wrapper.text()).toContain('Khởi Tạo Kèo Mới')
    expect(wrapper.text()).toContain('INITIATE PLAY')
    expect(wrapper.text()).toContain('Địa Điểm & Thời Gian')
    expect(wrapper.text()).toContain('Thông Số Trận Đấu')
    expect(wrapper.text()).toContain('Mạng Lưới Bạn Bè (Invite Network)')
  })

  it('allows changing sport category and quick venue selection', async () => {
    const wrapper = mount(CreateMatchView, {
      global: {
        plugins: [i18n],
      },
    })
    await flushPromises()

    const pickleballBtn = wrapper.findAll('.sport-select-card').find((b) => b.text().includes('Pickleball'))
    expect(pickleballBtn).toBeDefined()
    await pickleballBtn?.trigger('click')

    expect(pickleballBtn?.classes()).toContain('active')

    const quickChip = wrapper.find('.quick-venue-pill')
    if (quickChip.exists()) {
      await quickChip.trigger('click')
      expect(quickChip.classes()).toContain('active')
    }
  })

  it('submits match successfully and triggers redirect', async () => {
    vi.useFakeTimers()
    const wrapper = mount(CreateMatchView, {
      global: {
        plugins: [i18n],
      },
    })
    await flushPromises()

    const form = wrapper.find('form.create-match-form')
    await form.trigger('submit')
    await flushPromises()

    expect(apiV2.post).toHaveBeenCalledWith('/social/matches', expect.any(Object))

    // Fast-forward timers for redirect
    vi.advanceTimersByTime(1200)
    expect(pushMock).toHaveBeenCalledWith(expect.objectContaining({ path: '/matchmaking' }))
    vi.useRealTimers()
  })
})
