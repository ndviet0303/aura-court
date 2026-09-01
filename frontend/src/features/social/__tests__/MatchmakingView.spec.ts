import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import MatchmakingView from '../views/MatchmakingView.vue'
import { i18n } from '@/i18n'
import { apiV2 } from '@/core/api/client'

const pushMock = vi.fn()

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: pushMock }),
  useRoute: () => ({ path: '/matchmaking', query: {} }),
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

describe('MatchmakingView', () => {
  const mockMatches = [
    {
      id: 1,
      title: 'Tìm 2 bạn đánh đôi nam/nữ cầu lông trình độ khá - Tối nay 19h Duy Tân',
      starts_at: '2026-09-02T19:00:00.000000Z',
      ends_at: '2026-09-02T21:00:00.000000Z',
      city: 'Hà Nội',
      min_skill: 3,
      max_skill: 5,
      required_participants: 4,
      joined_count: 2,
      creator_id: 5,
      creator: { id: 5, name: 'Khách hàng 01' },
      venue: { name: 'Aura Court Cầu Giấy', city: 'Hà Nội' },
    },
    {
      id: 2,
      title: 'Giao lưu Pickleball đôi nam - Sân Aura Quần Ngựa chiều Chủ Nhật',
      starts_at: '2026-09-04T16:00:00.000000Z',
      ends_at: '2026-09-04T18:00:00.000000Z',
      city: 'Hà Nội',
      min_skill: 1,
      max_skill: 3,
      required_participants: 4,
      joined_count: 3,
      creator_id: 6,
      creator: { id: 6, name: 'Khách hàng 02' },
      venue: { name: 'Aura Arena Quần Ngựa', city: 'Hà Nội' },
    },
  ]

  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()

    vi.mocked(apiV2.get).mockImplementation(async (url: string) => {
      if (url.startsWith('/social/matches')) {
        return { matches: mockMatches }
      }
      return {}
    })

    vi.mocked(apiV2.post).mockResolvedValue({ success: true })
  })

  it('renders hero stats, filters, and match cards', async () => {
    const wrapper = mount(MatchmakingView, {
      global: {
        plugins: [i18n],
      },
    })
    await flushPromises()

    expect(wrapper.text()).toContain('Ghép Trận & Tìm Kèo Giao Lưu')
    expect(wrapper.text()).toContain('Tổng Kèo Giao Lưu')
    expect(wrapper.text()).toContain('Đang Tuyển Người')
    expect(wrapper.text()).toContain('Tất Cả Kèo')
    expect(wrapper.text()).toContain('Cầu lông')
    expect(wrapper.text()).toContain('Pickleball')

    const cards = wrapper.findAll('.match-card')
    expect(cards.length).toBe(2)
    expect(cards[0]?.text()).toContain('Tìm 2 bạn đánh đôi nam/nữ cầu lông')
  })

  it('filters matches by sport chips and search input', async () => {
    const wrapper = mount(MatchmakingView, {
      global: {
        plugins: [i18n],
      },
    })
    await flushPromises()

    // Click Pickleball chip
    const pickleChip = wrapper.findAll('.sport-chip').find((c) => c.text().includes('Pickleball'))
    expect(pickleChip).toBeDefined()
    await pickleChip?.trigger('click')
    await flushPromises()

    const cards = wrapper.findAll('.match-card')
    expect(cards.length).toBe(1)
    expect(cards[0]?.text()).toContain('Giao lưu Pickleball đôi nam')

    // Search query
    const searchInput = wrapper.find('.search-input')
    await searchInput.setValue('Quần Ngựa')
    await flushPromises()

    expect(wrapper.findAll('.match-card').length).toBe(1)
  })

  it('switches to Partner Finder tab', async () => {
    const wrapper = mount(MatchmakingView, {
      global: {
        plugins: [i18n],
      },
    })
    await flushPromises()

    const partnerTab = wrapper.findAll('.tab-btn').find((b) => b.text().includes('Tìm Bạn Ghép Cặp'))
    expect(partnerTab).toBeDefined()
    await partnerTab?.trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('Vận Động Viên Tìm Bạn Đấu')
    expect(wrapper.findAll('.partner-card').length).toBeGreaterThan(0)
  })

  it('opens Join Match modal on click and confirms joining', async () => {
    const wrapper = mount(MatchmakingView, {
      global: {
        plugins: [i18n],
      },
    })
    await flushPromises()

    const joinBtn = wrapper.findAll('.match-card button').find((b) => b.text().includes('Tham Gia Kèo Này'))
    expect(joinBtn).toBeDefined()
    await joinBtn?.trigger('click')
    await flushPromises()

    // Modal should be visible
    expect(wrapper.find('.modal-card').exists()).toBe(true)
    expect(wrapper.find('.modal-title').text()).toContain('Tham Gia Kèo Giao Lưu')

    // Confirm join
    const confirmBtn = wrapper.findAll('.modal-footer button').find((b) => b.text().includes('Xác Nhận Tham Gia'))
    expect(confirmBtn).toBeDefined()
    await confirmBtn?.trigger('click')
    await flushPromises()

    expect(apiV2.post).toHaveBeenCalledWith('/social/matches/1/join')
  })
})
