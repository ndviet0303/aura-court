import { describe, it, expect, beforeEach, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import InvitationsView from '../views/InvitationsView.vue'
import { i18n } from '@/i18n'
import { notificationApi } from '../api/notificationApi'

vi.mock('../api/notificationApi', () => ({
  notificationApi: {
    myInvitations: vi.fn(),
    acceptInvitation: vi.fn(),
    declineInvitation: vi.fn(),
  },
}))

const invitation = {
  id: 5,
  role: 'staff',
  expires_at: '2026-10-22T03:00:00Z',
  organization: { id: 1, name: 'Aura Org' },
  inviter: { id: 2, name: 'Owner Alice' },
  venue: { id: 3, name: { vi: 'Sân Alpha', en: 'Alpha Court' } },
}

describe('InvitationsView', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.mocked(notificationApi.myInvitations).mockResolvedValue({ invitations: [invitation] })
  })

  it('lists pending invitations and removes one once accepted', async () => {
    vi.mocked(notificationApi.acceptInvitation).mockResolvedValue({ message: 'OK' })
    const wrapper = mount(InvitationsView, { global: { plugins: [i18n] } })
    await flushPromises()

    expect(wrapper.text()).toContain('Aura Org')
    expect(wrapper.text()).toContain('Sân Alpha')

    await wrapper.get('[data-testid="accept-invitation"]').trigger('click')
    await flushPromises()

    expect(notificationApi.acceptInvitation).toHaveBeenCalledWith(5)
    expect(wrapper.find('[data-testid="invitation-card"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('OK')
  })

  it('declines an invitation', async () => {
    vi.mocked(notificationApi.declineInvitation).mockResolvedValue({ message: 'Declined' })
    const wrapper = mount(InvitationsView, { global: { plugins: [i18n] } })
    await flushPromises()

    await wrapper.get('[data-testid="decline-invitation"]').trigger('click')
    await flushPromises()

    expect(notificationApi.declineInvitation).toHaveBeenCalledWith(5)
    expect(notificationApi.acceptInvitation).not.toHaveBeenCalled()
  })
})
