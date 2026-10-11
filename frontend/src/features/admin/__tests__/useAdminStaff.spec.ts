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
    addOrgMember: vi.fn(),
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
          role: 'venue_manager',
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

  it('passes venue_id and venue_ids when inviting staff', async () => {
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

    vi.mocked(adminApi.addOrgMember).mockResolvedValue({ member: { id: 2 } })

    const { inviteStaff } = useAdminStaff()
    await inviteStaff({
      name: 'New Staff',
      email: 'staff@test.local',
      role: 'Manager',
      venueId: 42,
      permissions: ['pricing', 'schedule'],
    })

    expect(adminApi.createOrgInvitation).toHaveBeenCalledWith(
      88,
      expect.objectContaining({
        email: 'staff@test.local',
        role: 'venue_manager',
        venue_id: 42,
        venue_ids: [42],
        permissions: ['pricing', 'schedule'],
      }),
    )
  })

  it('passes venue_id and venue_ids when updating member role and grants', async () => {
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

    const { updateMemberRole } = useAdminStaff()
    await updateMemberRole(1, {
      role: 'venue_manager',
      status: 'active',
      venueId: 43,
      venueIds: [43],
    })

    expect(adminApi.updateOrgMember).toHaveBeenCalledWith(
      88,
      1,
      expect.objectContaining({
        role: 'venue_manager',
        status: 'active',
        venue_id: 43,
        venue_ids: [43],
      }),
    )
  })

  it('correctly maps venue_id and venueName from permissions', async () => {
    const { staffList, loadStaffData } = useAdminStaff()
    await loadStaffData()

    expect(staffList.value.length).toBe(1)
    expect(staffList.value[0]?.venueId).toBe(42)
    expect(staffList.value[0]?.venueName).toBe('Sân Cầu Lông Alpha')
  })

  it('sends pending staff invitation via createOrgInvitation with venue scope', async () => {
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

    vi.mocked(adminApi.createOrgInvitation).mockResolvedValue({
      invitation: { id: 101, status: 'pending' },
    })

    const { inviteStaff } = useAdminStaff()
    await inviteStaff({
      name: 'Nguyen Van A',
      email: 'a@test.com',
      role: 'Receptionist',
      venueId: 42,
      venueIds: [42],
      permissions: { venue_id: 42 },
    })

    expect(adminApi.createOrgInvitation).toHaveBeenCalledWith(
      99,
      expect.objectContaining({
        email: 'a@test.com',
        role: 'cashier',
        venue_id: 42,
        venue_ids: [42],
      }),
    )
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
