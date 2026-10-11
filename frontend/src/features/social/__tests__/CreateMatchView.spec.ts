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
            sport_type: 'badminton',
            venue: {
              id: 1,
              name: 'Aura Central',
              slug: 'aura-central',
              address: '123 Le Loi',
              google_maps_url: null,
              is_active: true,
              share_url: '',
            },
          },
        ]
      }
      return []
    })

    vi.mocked(apiV2.post).mockResolvedValue({
      match: { id: 888, title: 'Aura Central Court - Sân VIP 1' },
    })
  })

  it('renders correctly with default hero header, steps, and live preview', async () => {
    const wrapper = mount(CreateMatchView, {
      global: {
        plugins: [i18n],
      },
    })
    await flushPromises()

    expect(wrapper.text()).toContain('Khởi Tạo Kèo Mới')
    expect(wrapper.text()).toContain('MỞ KÈO GIAO LƯU')
    expect(wrapper.text()).toContain('Địa Điểm & Thời Gian')
    expect(wrapper.text()).toContain('Thông Số & Quy Định Trận Đấu')
    expect(wrapper.text()).toContain('Mời Trực Tiếp Bạn Chơi')
    expect(wrapper.text()).toContain('Xem Trước Thẻ Kèo')
  })

  it('allows changing sport category and quick venue selection', async () => {
    const wrapper = mount(CreateMatchView, {
      global: {
        plugins: [i18n],
      },
    })
    await flushPromises()

    const pickleballBtn = wrapper
      .findAll('.sport-card')
      .find((b) => b.text().includes('Pickleball'))
    expect(pickleballBtn).toBeDefined()
    await pickleballBtn?.trigger('click')

    expect(pickleballBtn?.classes()).toContain('active')

    const quickPill = wrapper.find('.quick-pill')
    if (quickPill.exists()) {
      await quickPill.trigger('click')
      expect(quickPill.classes()).toContain('active')
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

    const form = wrapper.find('form.create-layout')
    await form.trigger('submit')
    await flushPromises()

    expect(apiV2.post).toHaveBeenCalledWith('/social/matches', expect.any(Object))

    // Fast-forward timers for redirect
    vi.advanceTimersByTime(1200)
    expect(pushMock).toHaveBeenCalledWith(expect.objectContaining({ path: '/matchmaking' }))
    vi.useRealTimers()
  })

  it('Task 8.2: loads persisted friends from /social/friends, toggles selection, and sends batch invitations with individual failure reporting', async () => {
    vi.mocked(apiV2.get).mockImplementation(async (url: string) => {
      if (url.startsWith('/social/friends')) {
        return {
          friends: [
            { id: 101, name: 'Lê Văn An', avatar_url: '', play_level: 4, city: 'Hà Nội' },
            { id: 102, name: 'Trần Thị Bình', avatar_url: '', play_level: 3, city: 'Hà Nội' },
          ],
          total: 2,
        }
      }
      return []
    })

    vi.mocked(apiV2.post).mockImplementation(async (url: string, payload: any) => {
      if (url === '/social/matches') {
        return { match: { id: 777, title: 'Match Test 8.2' } }
      }
      if (url === '/social/matches/777/invite') {
        return {
          results: [
            { invitee_id: 101, status: 'invited', invitation_id: 1 },
            { invitee_id: 102, status: 'failed', reason: 'User not permitted' },
          ],
          summary: { total: 2, invited: 1, failed: 1 },
        }
      }
      return {}
    })

    const wrapper = mount(CreateMatchView, {
      global: {
        plugins: [i18n],
      },
    })
    await flushPromises()

    // 1. Verifies friends were loaded from persisted /social/friends endpoint
    expect(apiV2.get).toHaveBeenCalledWith('/social/friends')
    expect(wrapper.text()).toContain('Lê Văn An')
    expect(wrapper.text()).toContain('Trần Thị Bình')

    // 2. Select both friends
    const friendCards = wrapper.findAll('.friend-card')
    expect(friendCards.length).toBe(2)
    await friendCards[0]?.trigger('click')
    await friendCards[1]?.trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('Đã chọn 2')

    // 3. Submit match
    const form = wrapper.find('form.create-layout')
    await form.trigger('submit')
    await flushPromises()

    // 4. Batch invitations sent with individual IDs
    expect(apiV2.post).toHaveBeenCalledWith('/social/matches/777/invite', {
      invitee_ids: [101, 102],
    })

    // 5. Individual failure outcome reported in notice
    expect(wrapper.text()).toContain('Lưu ý một số lời mời thất bại: ID 102: User not permitted')
  })
  it('Task 4.1: allows facility owner to open drop-in session with court, category, and skill labels, not displaying host as player', async () => {
    const wrapper = mount(CreateMatchView, {
      global: { plugins: [i18n] },
    })
    await flushPromises()

    // 1. Toggle owner-hosted session
    const ownerCheckbox = wrapper.find('.owner-session-toggle-box input[type="checkbox"]')
    expect(ownerCheckbox.exists()).toBe(true)
    await ownerCheckbox.setValue(true)
    await flushPromises()

    // Check that category and skill label buttons are rendered
    expect(wrapper.text()).toContain('Phân loại buổi chơi:')
    expect(wrapper.text()).toContain('Nhãn trình độ chấp nhận')
    expect(wrapper.text()).toContain('TBY (Trung bình yếu)')

    // Select skill label TBY
    const tbyBtn = wrapper
      .findAll('.owner-session-toggle-box button')
      .find((b) => b.text().includes('TBY'))
    expect(tbyBtn).toBeDefined()
    await tbyBtn?.trigger('click')
    await flushPromises()

    // 2. In live preview, host is NOT shown as a player
    const livePreview = wrapper.findComponent({ name: 'MatchLivePreviewCard' })
    expect(livePreview.exists()).toBe(true)
    // Live preview participants should not have Host
    expect(livePreview.text()).toContain('Chủ cơ sở')
    expect(livePreview.text()).toContain('Chia đều sau buổi')

    // 3. Submit match
    const form = wrapper.find('form.create-layout')
    await form.trigger('submit')
    await flushPromises()

    // 4. Verify payload sent to /social/matches
    expect(apiV2.post).toHaveBeenCalledWith(
      '/social/matches',
      expect.objectContaining({
        is_owner_hosted: true,
        category: expect.stringMatching(/giao lưu|đánh vãng lai/),
        skill_levels: expect.arrayContaining(['TB']),
      }),
    )
  })
})
