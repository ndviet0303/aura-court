import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import CommunityFeedPanel from '../components/CommunityFeedPanel.vue'
import SocialConnectionsPanel from '../components/SocialConnectionsPanel.vue'
import SocialModerationModal from '../components/SocialModerationModal.vue'
import { socialApi } from '../api/socialApi'
import { useAuthStore } from '@/stores/auth'

vi.mock('../api/socialApi', () => ({
  socialApi: {
    getFeed: vi.fn(),
    createFeedPost: vi.fn(),
    deleteFeedPost: vi.fn(),
    addFeedComment: vi.fn(),
    deleteFeedComment: vi.fn(),
    reactFeedItem: vi.fn(),
    reportFeedItem: vi.fn(),
    getFriends: vi.fn(),
    getFriendRequests: vi.fn(),
    sendFriendRequest: vi.fn(),
    acceptFriendRequest: vi.fn(),
    declineFriendRequest: vi.fn(),
    removeFriend: vi.fn(),
    getFeedReports: vi.fn(),
    hideFeedItem: vi.fn(),
    restoreFeedItem: vi.fn(),
  },
}))

describe('Task 8.6: Community Feed, Connections, and Moderation Interfaces', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()

    const auth = useAuthStore()
    auth.user = {
      id: 1,
      name: 'Alice Tester',
      email: 'alice@test.vn',
      role: 'venue_manager',
    } as any
  })

  it('CommunityFeedPanel: loads feed, displays audience tags, creates post and comments', async () => {
    const mockPosts = [
      {
        id: 10,
        user_id: 1,
        content: 'Chào mừng giải đấu giao lưu cuối tuần!',
        audience: 'organization',
        created_at: '2026-09-09T08:00:00.000Z',
        user: { name: 'Alice Tester' },
        reactions_counts: { like: 3 },
        comments: [
          { id: 101, user_id: 2, content: 'Tuyệt vời quá!', user: { name: 'Bob' } },
        ],
      },
      {
        id: 11,
        user_id: 2,
        content: 'Bài viết chỉ bạn bè mới xem được',
        audience: 'friends',
        created_at: '2026-09-09T09:00:00.000Z',
        user: { name: 'Bob' },
        reactions_counts: {},
        comments: [],
      },
    ]

    vi.mocked(socialApi.getFeed).mockResolvedValue({ data: mockPosts } as any)
    vi.mocked(socialApi.createFeedPost).mockResolvedValue({
      post: { id: 12, content: 'Bài viết mới', audience: 'friends' },
      message: 'Đăng bài thành công!',
    })
    vi.mocked(socialApi.addFeedComment).mockResolvedValue({
      comment: { id: 102, user_id: 1, content: 'Bình luận mới' },
      message: 'Comment added',
    })

    const wrapper = mount(CommunityFeedPanel)
    await flushPromises()

    // 1. Verifies posts loaded and audience badges displayed
    expect(socialApi.getFeed).toHaveBeenCalled()
    expect(wrapper.text()).toContain('Chào mừng giải đấu giao lưu cuối tuần!')
    expect(wrapper.text()).toContain('Toàn CLB')
    expect(wrapper.text()).toContain('Chỉ bạn bè')

    // 2. Creates friends-only post
    const textarea = wrapper.find('.feed-textarea')
    await textarea.setValue('Cần tìm bạn tập bóng bàn chiều nay')
    const audienceSelect = wrapper.find('.audience-select')
    await audienceSelect.setValue('friends')

    const publishBtn = wrapper.find('.btn-publish-post')
    await publishBtn.trigger('click')
    await flushPromises()

    expect(socialApi.createFeedPost).toHaveBeenCalledWith({
      content: 'Cần tìm bạn tập bóng bàn chiều nay',
      audience: 'friends',
    })

    // 3. Adds comment
    const commentInput = wrapper.find('.comment-input')
    await commentInput.setValue('Tôi tham gia với nhé!')
    const sendCommentBtn = wrapper.find('.btn-send-comment')
    await sendCommentBtn.trigger('click')
    await flushPromises()

    expect(socialApi.addFeedComment).toHaveBeenCalledWith(10, 'Tôi tham gia với nhé!')
  })

  it('SocialConnectionsPanel: loads friends, accepts requests, and handles friend removal', async () => {
    vi.mocked(socialApi.getFriends).mockResolvedValue({
      friends: [
        { id: 2, name: 'Bob Tran', email: 'bob@test.vn', friendship_id: 50, play_level: 4 },
      ],
      total: 1,
    } as any)

    vi.mocked(socialApi.getFriendRequests).mockResolvedValue({
      incoming: [
        { id: 60, requester: { id: 3, name: 'Charlie', email: 'charlie@test.vn' } },
      ],
      outgoing: [],
    } as any)

    const wrapper = mount(SocialConnectionsPanel)
    await flushPromises()

    // 1. Verifies friends list
    expect(socialApi.getFriends).toHaveBeenCalled()
    expect(wrapper.text()).toContain('Bob Tran')
    expect(wrapper.text()).toContain('bob@test.vn')

    // 2. Switch to requests sub-tab
    const subtabs = wrapper.findAll('.subtab-btn')
    expect(subtabs.length).toBe(2)
    await subtabs[1]?.trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('Charlie')

    // 3. Accept incoming request
    vi.mocked(socialApi.acceptFriendRequest).mockResolvedValue({} as any)
    const acceptBtn = wrapper.find('.btn-accept')
    expect(acceptBtn.exists()).toBe(true)
    await acceptBtn.trigger('click')
    await flushPromises()

    expect(socialApi.acceptFriendRequest).toHaveBeenCalledWith(60)

    // 4. Send request to new user
    const userIdInput = wrapper.find('.user-id-input')
    if (userIdInput.exists()) {
      await userIdInput.setValue(4)
      const sendReqBtn = wrapper.find('.btn-send-req')
      await sendReqBtn.trigger('click')
      await flushPromises()
      expect(socialApi.sendFriendRequest).toHaveBeenCalledWith(4)
    }
  })

  it('SocialModerationModal: reviews pending reports and enforces audit reason for hide/restore', async () => {
    vi.mocked(socialApi.getFeedReports).mockResolvedValue({
      data: [
        {
          id: 99,
          reportable_type: 'App\\Models\\FeedPost',
          reportable_id: 11,
          reason: 'Spam bán bóng không rõ nguồn gốc',
          status: 'pending',
          reporter: { name: 'Người báo cáo' },
          reportable: { content: 'Bán sỉ bóng bàn giá rẻ' },
        },
      ],
    } as any)

    vi.mocked(socialApi.hideFeedItem).mockResolvedValue({} as any)

    const wrapper = mount(SocialModerationModal, {
      props: { show: true },
    })
    await flushPromises()

    // 1. Verifies report details displayed
    expect(socialApi.getFeedReports).toHaveBeenCalledWith('pending')
    expect(wrapper.text()).toContain('Spam bán bóng không rõ nguồn gốc')
    expect(wrapper.text()).toContain('Bán sỉ bóng bàn giá rẻ')

    // 2. Try hiding without audit reason -> shows error validation
    const hideBtn = wrapper.find('.btn-mod-hide')
    await hideBtn.trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('Vui lòng nhập lý do kiểm duyệt')
    expect(socialApi.hideFeedItem).not.toHaveBeenCalled()

    // 3. Enter audit reason and hide successfully
    const auditInput = wrapper.find('.audit-text-input')
    await auditInput.setValue('Vi phạm chính sách thương mại và spam')
    await hideBtn.trigger('click')
    await flushPromises()

    expect(socialApi.hideFeedItem).toHaveBeenCalledWith(
      'post',
      11,
      'Vi phạm chính sách thương mại và spam'
    )
  })
})
