import { ref, computed, onMounted } from 'vue'
import { adminApi } from '../api/adminApi'
import { errorMessage } from '@/core/utils/errorMessage'

export interface OrgVenue {
  id: number
  name: string
  address?: string
  is_active: boolean
  courts_count?: number
}

export interface OrgMember {
  id: number
  user_id: number
  role: 'organization_owner' | 'venue_manager' | 'cashier' | 'staff' | 'customer'
  status: 'active' | 'suspended' | string
  user?: {
    id: number
    name: string
    email: string
    phone?: string
    avatar_url?: string
  }
}

export interface Organization {
  id: number
  name: string
  slug: string
  tax_code?: string
  support_email?: string
  phone?: string
  address?: string
  brand_color?: string
  logo_url?: string
  status: string
  auto_approve_booking?: boolean
  resale_fee_percent?: number
  currency?: string
  timezone?: string
  settings?: Record<string, any>
  venues?: OrgVenue[]
  members?: OrgMember[]
}

export function useOrganizationOwner() {
  const loading = ref(false)
  const orgSwitchLoading = ref(false)
  const saving = ref(false)
  const notice = ref('')
  const error = ref('')

  const activeTab = ref<'profile' | 'venues' | 'members' | 'policies' | 'summary'>('profile')

  const organizationsList = ref<Organization[]>([])
  const selectedOrgId = ref<number>(1)

  const currentOrg = ref<Organization>({
    id: 1,
    name: '',
    slug: '',
    status: 'active',
    brand_color: '#6B2D3F',
    auto_approve_booking: true,
    resale_fee_percent: 5,
    currency: 'VND',
    timezone: 'Asia/Ho_Chi_Minh',
    venues: [],
    members: [],
  })

  const brandColorOptions = [
    { label: 'Burgundy Luxury', value: '#6B2D3F' },
    { label: 'Gold Champagne', value: '#C9A15A' },
    { label: 'Aura Mauve', value: '#78555e' },
    { label: 'Emerald Pro', value: '#10b981' },
    { label: 'Royal Sapphire', value: '#2563eb' },
    { label: 'Midnight Obsidian', value: '#18181b' },
  ]

  const timezoneOptions = [
    { label: 'Asia/Ho_Chi_Minh (UTC+7)', value: 'Asia/Ho_Chi_Minh' },
    { label: 'Asia/Bangkok (UTC+7)', value: 'Asia/Bangkok' },
    { label: 'Asia/Singapore (UTC+8)', value: 'Asia/Singapore' },
    { label: 'Asia/Tokyo (UTC+9)', value: 'Asia/Tokyo' },
  ]

  const currencyOptions = [
    { label: 'VND (Việt Nam Đồng)', value: 'VND' },
    { label: 'USD (US Dollar)', value: 'USD' },
  ]

  // Computed KPIs
  const totalVenuesCount = computed(() => currentOrg.value.venues?.length || 0)
  const activeVenuesCount = computed(
    () => currentOrg.value.venues?.filter((v) => v.is_active).length || 0,
  )
  const totalCourtsCount = computed(() =>
    (currentOrg.value.venues || []).reduce((acc, v) => acc + (v.courts_count || 0), 0),
  )
  const totalMembersCount = computed(() => currentOrg.value.members?.length || 0)
  const ownersCount = computed(
    () => currentOrg.value.members?.filter((m) => m.role === 'organization_owner').length || 0,
  )
  const managersCount = computed(
    () => currentOrg.value.members?.filter((m) => m.role === 'venue_manager').length || 0,
  )
  const staffCount = computed(
    () =>
      currentOrg.value.members?.filter((m) => m.role === 'staff' || m.role === 'cashier').length ||
      0,
  )

  function showNotice(msg: string) {
    notice.value = msg
    setTimeout(() => {
      if (notice.value === msg) notice.value = ''
    }, 4000)
  }

  async function loadOrgData(targetOrgId?: number) {
    if (organizationsList.value.length === 0) {
      loading.value = true
    } else {
      orgSwitchLoading.value = true
    }
    error.value = ''

    try {
      const orgRes = await adminApi.getOrganizations<{
        organizations?: Organization[]
        organization?: Organization
      }>()
      const list = orgRes?.organizations || (orgRes?.organization ? [orgRes.organization] : [])
      organizationsList.value = list

      const orgToLoad = targetOrgId
        ? list.find((o) => o.id === targetOrgId) || list[0]
        : list.find((o) => o.id === selectedOrgId.value) || list[0]

      if (orgToLoad) {
        selectedOrgId.value = orgToLoad.id
        currentOrg.value = {
          ...currentOrg.value,
          ...orgToLoad,
        }
      }

      if (currentOrg.value.id) {
        const [orgDetailRes, membersRes] = await Promise.allSettled([
          adminApi.getOrganization<{ organization: Organization }>(currentOrg.value.id),
          adminApi.getOrgMembers<{ members: OrgMember[] }>(currentOrg.value.id),
        ])

        if (orgDetailRes.status === 'fulfilled' && orgDetailRes.value?.organization) {
          const detail = orgDetailRes.value.organization
          currentOrg.value = {
            ...currentOrg.value,
            ...detail,
          }
        }

        if (membersRes.status === 'fulfilled' && membersRes.value?.members) {
          currentOrg.value.members = membersRes.value.members
        }

        const orgVenuesList =
          orgDetailRes.status === 'fulfilled' && orgDetailRes.value?.organization?.venues
            ? orgDetailRes.value.organization.venues
            : []

        const matchedVenues = orgVenuesList.filter(
          (v: any) => !v.organization_id || v.organization_id === currentOrg.value.id,
        )
        currentOrg.value.venues = matchedVenues.map((v: any) => ({
          id: v.id,
          name: v.name,
          address: v.address,
          is_active: v.is_active ?? true,
          courts_count: Array.isArray(v.courts) ? v.courts.length : v.courts_count || 0,
        }))
      }
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể tải dữ liệu tổ chức.')
    } finally {
      loading.value = false
      orgSwitchLoading.value = false
    }
  }

  async function switchOrganization(orgId: number) {
    if (selectedOrgId.value === orgId && !loading.value) return
    selectedOrgId.value = orgId
    await loadOrgData(orgId)
  }

  async function saveProfile(payload: Partial<Organization>) {
    saving.value = true
    try {
      const res = await adminApi.updateOrganization<{ organization: Organization }>(
        currentOrg.value.id,
        payload,
      )
      if (res?.organization) {
        Object.assign(currentOrg.value, res.organization)
      } else {
        Object.assign(currentOrg.value, payload)
      }
      showNotice('Đã cập nhật thông tin doanh nghiệp thành công!')
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể cập nhật thông tin doanh nghiệp.')
      throw e
    } finally {
      saving.value = false
    }
  }

  async function savePolicies(payload: Partial<Organization>) {
    saving.value = true
    try {
      const res = await adminApi.updateOrganization<{ organization: Organization }>(
        currentOrg.value.id,
        payload,
      )
      if (res?.organization) {
        Object.assign(currentOrg.value, res.organization)
      } else {
        Object.assign(currentOrg.value, payload)
      }
      showNotice('Đã lưu cấu hình chính sách vận hành thành công!')
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể lưu chính sách vận hành.')
      throw e
    } finally {
      saving.value = false
    }
  }

  async function inviteMember(email: string, role: any) {
    saving.value = true
    try {
      // BR-17: an invitation, never a direct add; venues are assigned once the invitee accepts.
      await adminApi.createOrgInvitation(currentOrg.value.id, { email, role })
      showNotice(`Đã gửi lời mời tới ${email}. Thành viên sẽ xuất hiện sau khi chấp nhận.`)
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể gửi lời mời.')
      throw e
    } finally {
      saving.value = false
    }
  }

  async function updateMemberRole(memberId: number, payload: { role: string; status?: string }) {
    saving.value = true
    try {
      const res = await adminApi.updateOrgMember<{ member: OrgMember }>(
        currentOrg.value.id,
        memberId,
        payload,
      )
      if (res?.member && currentOrg.value.members) {
        const idx = currentOrg.value.members.findIndex((m) => m.id === memberId)
        if (idx >= 0) {
          currentOrg.value.members[idx] = res.member
        }
      }
      showNotice('Đã cập nhật vai trò thành viên thành công!')
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể cập nhật vai trò thành viên.')
      throw e
    } finally {
      saving.value = false
    }
  }

  async function removeMember(memberId: number) {
    saving.value = true
    try {
      await adminApi.removeOrgMember(currentOrg.value.id, memberId)
      if (currentOrg.value.members) {
        currentOrg.value.members = currentOrg.value.members.filter((m) => m.id !== memberId)
      }
      showNotice('Đã xóa thành viên khỏi tổ chức thành công!')
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể xóa thành viên.')
      throw e
    } finally {
      saving.value = false
    }
  }

  onMounted(() => {
    loadOrgData()
  })

  return {
    loading,
    orgSwitchLoading,
    saving,
    notice,
    error,
    activeTab,
    organizationsList,
    selectedOrgId,
    currentOrg,
    brandColorOptions,
    timezoneOptions,
    currencyOptions,
    totalVenuesCount,
    activeVenuesCount,
    totalCourtsCount,
    totalMembersCount,
    ownersCount,
    managersCount,
    staffCount,
    showNotice,
    loadOrgData,
    switchOrganization,
    saveProfile,
    savePolicies,
    inviteMember,
    updateMemberRole,
    removeMember,
  }
}
