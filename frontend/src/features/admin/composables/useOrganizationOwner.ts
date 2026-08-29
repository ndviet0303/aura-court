import { ref, computed } from 'vue'
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
    name: 'Aura Badminton Club',
    slug: 'aura-badminton-club',
    tax_code: '0318294021',
    support_email: 'support@auraclub.vn',
    phone: '+84 908 123 456',
    address: 'Tầng 5, Tòa nhà Aura Center, Số 12 Nguyễn Thị Minh Khai, P. Bến Nghé, Quận 1, TP. Hồ Chí Minh',
    brand_color: '#78555e',
    logo_url: '/aura/badge-logo.png',
    status: 'active',
    auto_approve_booking: true,
    resale_fee_percent: 5,
    cancellation_grace_hours: 12,
    venues: [
      { id: 1, name: 'Aura Center (Quận 1)', address: '12 Nguyễn Thị Minh Khai, Q.1', is_active: true, courts_count: 8 },
      { id: 2, name: 'Riverside Club (Bình Thạnh)', address: '48 Ung Văn Khiêm, Bình Thạnh', is_active: true, courts_count: 12 },
      { id: 3, name: 'District 1 Hub (Bến Nghé)', address: '88 Lê Thánh Tôn, Q.1', is_active: true, courts_count: 6 },
    ],
    members: [
      { id: 1, user_id: 101, role: 'organization_owner', status: 'active', user: { id: 101, name: 'Alex Sterling', email: 'alex@auraclub.vn', phone: '0908123456' } },
      { id: 2, user_id: 102, role: 'venue_manager', status: 'active', user: { id: 102, name: 'Trần Minh Hoàng', email: 'hoang.tran@auraclub.vn', phone: '0912345678' } },
      { id: 3, user_id: 103, role: 'cashier', status: 'active', user: { id: 103, name: 'Lê Thảo My', email: 'my.le@auraclub.vn', phone: '0933889900' } },
    ],
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

  function saveProfile(payload: Partial<Organization>) {
    Object.assign(currentOrg.value, payload)
    showNotice('Đã cập nhật thông tin doanh nghiệp thành công!')
  }

  function savePolicies(payload: Partial<Organization>) {
    Object.assign(currentOrg.value, payload)
    showNotice('Đã lưu cấu hình chính sách vận hành!')
  }

  function inviteMember(email: string, role: any) {
    currentOrg.value.members?.push({
      id: Date.now(),
      user_id: Date.now(),
      role,
      status: 'pending',
      user: { id: Date.now(), name: email.split('@')[0] || 'Thành viên mới', email },
    })
    showNotice(`Đã gửi lời mời thành viên tới ${email}!`)
  }

  return {
    loading,
    saving,
    notice,
    error,
    activeTab,
    currentOrg,
    brandColorOptions,
    showNotice,
    saveProfile,
    savePolicies,
    inviteMember,
  }
}
