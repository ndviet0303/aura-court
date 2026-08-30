import { ref, onMounted } from 'vue'
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
  role: 'organization_owner' | 'venue_manager' | 'cashier' | 'staff'
  status: string
  user?: {
    id: number
    name: string
    email: string
    phone?: string
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
  cancellation_grace_hours?: number
  venues?: OrgVenue[]
  members?: OrgMember[]
}

export function useOrganizationOwner() {
  const loading = ref(false)
  const saving = ref(false)
  const notice = ref('')
  const error = ref('')

  const activeTab = ref<'profile' | 'venues' | 'members' | 'policies'>('profile')

  const currentOrg = ref<Organization>({
    id: 1,
    name: '',
    slug: '',
    status: 'active',
    venues: [],
    members: [],
  })

  const brandColorOptions = [
    { label: 'Aura Mauve (Mặc định)', value: '#78555e' },
    { label: 'Deep Wine', value: '#5e3e47' },
    { label: 'Sage Olive', value: '#8ba89f' },
    { label: 'Dusty Lavender', value: '#9e97ab' },
    { label: 'Emerald Pro', value: '#386a55' },
  ]

  function showNotice(msg: string) {
    notice.value = msg
    setTimeout(() => {
      if (notice.value === msg) notice.value = ''
    }, 4000)
  }

  async function loadOrgData() {
    loading.value = true
    error.value = ''
    try {
      const orgRes = await adminApi.getOrganizations<{ organizations?: Organization[]; organization?: Organization }>()
      const list = orgRes?.organizations || (orgRes?.organization ? [orgRes.organization] : [])
      if (list.length > 0 && list[0]) {
        currentOrg.value = {
          ...currentOrg.value,
          ...list[0],
        }
      }

      // Load members & venues
      if (currentOrg.value.id) {
        const [membersRes, venuesRes] = await Promise.allSettled([
          adminApi.getOrgMembers<{ members: OrgMember[] }>(currentOrg.value.id),
          adminApi.getVenues(),
        ])

        if (membersRes.status === 'fulfilled' && membersRes.value?.members) {
          currentOrg.value.members = membersRes.value.members
        }
        if (venuesRes.status === 'fulfilled' && Array.isArray(venuesRes.value)) {
          currentOrg.value.venues = venuesRes.value.map((v: any) => ({
            id: v.id,
            name: v.name,
            address: v.address,
            is_active: v.is_active ?? true,
            courts_count: v.courts?.length ?? 0,
          }))
        }
      }
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể tải thông tin tổ chức.')
    } finally {
      loading.value = false
    }
  }

  async function saveProfile(payload: Partial<Organization>) {
    saving.value = true
    try {
      await adminApi.updateOrganization(currentOrg.value.id, payload)
      Object.assign(currentOrg.value, payload)
      showNotice('Đã cập nhật thông tin doanh nghiệp thành công!')
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể cập nhật thông tin doanh nghiệp.')
    } finally {
      saving.value = false
    }
  }

  async function savePolicies(payload: Partial<Organization>) {
    saving.value = true
    try {
      await adminApi.updateOrganization(currentOrg.value.id, { settings: payload })
      Object.assign(currentOrg.value, payload)
      showNotice('Đã lưu cấu hình chính sách vận hành!')
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể lưu chính sách.')
    } finally {
      saving.value = false
    }
  }

  async function inviteMember(email: string, role: any) {
    saving.value = true
    try {
      const res = await adminApi.addOrgMember<{ member: OrgMember }>(currentOrg.value.id, { email, role })
      if (res?.member) {
        currentOrg.value.members = [...(currentOrg.value.members || []), res.member]
      }
      showNotice(`Đã gửi lời mời thành viên tới ${email}!`)
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể thêm thành viên.')
    } finally {
      saving.value = false
    }
  }

  onMounted(() => {
    loadOrgData()
  })

  return {
    loading,
    saving,
    notice,
    error,
    activeTab,
    currentOrg,
    brandColorOptions,
    showNotice,
    loadOrgData,
    saveProfile,
    savePolicies,
    inviteMember,
  }
}
