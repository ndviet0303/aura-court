import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useAdminStaff } from '../composables/useAdminStaff'
import type { Venue } from '@/features/court/types/court'
import { adminApi } from '../api/adminApi'
import { useAuthStore } from '@/stores/auth'

vi.mock('../api/adminApi', () => ({
  adminApi: {
    getOrgMembers: vi.fn(),
    getAuditLogs: vi.fn(),
    getVenues: vi.fn(),
    getOrgInvitations: vi.fn(),
    updateOrgMember: vi.fn(),
    removeOrgMember: vi.fn(),
    createOrgInvitation: vi.fn(),
    revokeOrgInvitation: vi.fn(),
  },
}))

describe('useAdminStaff (D08)', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()

    vi.mocked(adminApi.getOrgMembers).mockResolvedValue({
      members: [
        {
          id: 1,
          user_id: 10,
          role: 'staff',
          status: 'active',
          permissions: {
            venue_id: 42,
            venue_ids: [42],
            functions: ['pricing', 'schedule'],
          },
          user: { id: 10, name: 'Manager Bob', email: 'bob@aura.test' },
        },
      ],
    })

    vi.mocked(adminApi.getVenues).mockResolvedValue([
      { id: 42, name: { vi: 'Sân Cầu Lông Alpha', en: 'Alpha Court' } } as unknown as Venue,
      { id: 43, name: { vi: 'Sân Cầu Lông Beta', en: 'Beta Court' } } as unknown as Venue,
    ])
    vi.mocked(adminApi.getAuditLogs).mockResolvedValue({ data: [] })
    vi.mocked(adminApi.getOrgInvitations).mockResolvedValue({
      invitations: [
        { id: 7, email: 'pending@aura.test', role: 'staff', status: 'pending', expires_at: null },
      ],
    })
  })

  it('loads the organization invitations next to its members', async () => {
    const { loadStaffData, invitations } = useAdminStaff()
    await loadStaffData()

    expect(invitations.value).toEqual([
      { id: 7, email: 'pending@aura.test', role: 'staff', status: 'pending', expiresAt: null },
    ])
  })

  it('uses active organization from auth capabilities instead of hardcoded 1', async () => {
    const auth = useAuthStore()
    auth.user = {
      id: 1,
      name: 'Owner Alice',
      email: 'alice@aura.test',
      role: 'customer',
      capabilities: {
        active_organization_id: 88,
      },
    }

    const { currentOrgId, loadStaffData } = useAdminStaff()
    expect(currentOrgId.value).toBe(88)

    await loadStaffData()
    expect(adminApi.getOrgMembers).toHaveBeenCalledWith(88)
  })

  it('switches organization and reloads staff data for the target organization', async () => {
    const auth = useAuthStore()
    auth.user = {
      id: 1,
      name: 'Owner Alice',
      email: 'alice@aura.test',
      role: 'customer',
      capabilities: {
        active_organization_id: 88,
      },
    }

    const { currentOrgId, switchOrganization } = useAdminStaff()
    await switchOrganization(99)

    expect(currentOrgId.value).toBe(99)
    expect(adminApi.getOrgMembers).toHaveBeenCalledWith(99)
  })

  it('invites staff with only an email and venue scope (no role)', async () => {
    const auth = useAuthStore()
    auth.user = {
      id: 1,
      name: 'Owner Alice',
      email: 'alice@aura.test',
      role: 'customer',
      capabilities: {
        active_organization_id: 88,
      },
    }

    const { inviteStaff } = useAdminStaff()
    await inviteStaff({ email: 'staff@test.local', venueIds: [42, 43] })

    expect(adminApi.createOrgInvitation).toHaveBeenCalledWith(88, {
      email: 'staff@test.local',
      venue_ids: [42, 43],
    })
  })

  it('updates a staff member status and venue access without a role', async () => {
    const auth = useAuthStore()
    auth.user = {
      id: 1,
      name: 'Owner Alice',
      email: 'alice@aura.test',
      role: 'customer',
      capabilities: {
        active_organization_id: 88,
      },
    }

    vi.mocked(adminApi.updateOrgMember).mockResolvedValue({ member: { id: 1 } })

    const { updateMember } = useAdminStaff()
    await updateMember(1, { status: 'active', venueIds: [] })

    expect(adminApi.updateOrgMember).toHaveBeenCalledWith(88, 1, {
      status: 'active',
      venue_ids: [],
    })
  })

  it('ends staff status and shows the server message', async () => {
    const auth = useAuthStore()
    auth.user = {
      id: 1,
      name: 'Owner Alice',
      email: 'alice@aura.test',
      role: 'customer',
      capabilities: {
        active_organization_id: 88,
      },
    }
    vi.mocked(adminApi.removeOrgMember).mockResolvedValue({
      message: 'Đã chấm dứt tư cách nhân viên.',
    })

    const { removeMember, notice } = useAdminStaff()
    await removeMember(1)

    expect(adminApi.removeOrgMember).toHaveBeenCalledWith(88, 1)
    expect(notice.value).toBe('Đã chấm dứt tư cách nhân viên.')
  })

  it('correctly maps venue_id and venueName from permissions', async () => {
    const { staffList, loadStaffData } = useAdminStaff()
    await loadStaffData()

    expect(staffList.value.length).toBe(1)
    expect(staffList.value[0]?.venueId).toBe(42)
    expect(staffList.value[0]?.venueName).toBe('Sân Cầu Lông Alpha')
  })

  it('revokes staff invitation and reloads staff list', async () => {
    const auth = useAuthStore()
    auth.user = {
      id: 1,
      name: 'Owner',
      email: 'owner@org.com',
      role: 'customer',
      capabilities: {
        active_organization_id: 99,
      },
    }

    vi.mocked(adminApi.revokeOrgInvitation).mockResolvedValue({
      message: 'Đã thu hồi lời mời thành công.',
    })

    const { revokeInvitation } = useAdminStaff()
    await revokeInvitation(101)

    expect(adminApi.revokeOrgInvitation).toHaveBeenCalledWith(99, 101)
    expect(adminApi.getOrgMembers).toHaveBeenCalledWith(99)
  })

  it('correctly maps invited staff member status in staffList', async () => {
    vi.mocked(adminApi.getOrgMembers).mockResolvedValue({
      members: [
        {
          id: 5,
          user_id: 50,
          role: 'staff',
          status: 'invited',
          permissions: null,
          user: { id: 50, name: 'Invited Staff', email: 'invited@alpha.local' },
        },
      ],
    })

    const { staffList, loadStaffData } = useAdminStaff()
    await loadStaffData()

    expect(staffList.value.length).toBe(1)
    expect(staffList.value[0]?.status).toBe('invited')
    expect(staffList.value[0]?.role).toBe('Staff')
  })
})
