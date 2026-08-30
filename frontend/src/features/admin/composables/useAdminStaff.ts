import { ref, computed, onMounted } from 'vue'
import { adminApi } from '../api/adminApi'
import { errorMessage } from '@/core/utils/errorMessage'

export interface StaffMember {
  id: number
  name: string
  email: string
  phone: string
  role: 'Owner' | 'Manager' | 'Receptionist' | 'Technician' | 'Referee' | string
  venueName: string
  venueId: number
  status: 'active' | 'suspended' | 'invited' | string
  avatar?: string
  lastActive: string
  permissionsCount: number
}

export interface AuditLogItem {
  id: number
  actorName: string
  actorRole: string
  action: string
  target: string
  timestamp: string
  ip: string
  status: 'success' | 'warning' | 'danger'
}

export function useAdminStaff() {
  const loading = ref(false)
  const activeView = ref<'directory' | 'roles' | 'audit'>('directory')
  const searchQuery = ref('')
  const selectedRoleFilter = ref<string>('all')
  const selectedVenueFilter = ref<string>('all')
  const showInviteModal = ref(false)
  const notice = ref('')
  const error = ref('')

  const staffList = ref<StaffMember[]>([])
  const auditLogs = ref<AuditLogItem[]>([])

  function mapRoleLabel(role: string): string {
    switch (role) {
      case 'organization_owner':
      case 'Owner':
        return 'Owner'
      case 'venue_manager':
      case 'Manager':
        return 'Manager'
      case 'cashier':
      case 'Receptionist':
        return 'Receptionist'
      default:
        return role || 'Staff'
    }
  }

  async function loadStaffData() {
    loading.value = true
    error.value = ''
    try {
      const [membersRes, logsRes] = await Promise.allSettled([
        adminApi.getOrgMembers<{ members: any[] }>(),
        adminApi.getAuditLogs<any>(),
      ])

      if (membersRes.status === 'fulfilled') {
        const raw = membersRes.value?.members || (Array.isArray(membersRes.value) ? membersRes.value : [])
        staffList.value = raw.map((m: any) => ({
          id: m.id,
          name: m.user?.name || 'Nhân viên',
          email: m.user?.email || '',
          phone: m.user?.phone || '—',
          role: mapRoleLabel(m.role),
          venueName: m.venue?.name || 'Tất cả cơ sở',
          venueId: m.venue_id || 0,
          status: m.status || 'active',
          avatar: m.user?.avatar,
          lastActive: m.updated_at ? new Date(m.updated_at).toLocaleDateString('vi-VN') : '—',
          permissionsCount: m.role === 'organization_owner' ? 28 : m.role === 'venue_manager' ? 22 : 12,
        }))
      }

      if (logsRes.status === 'fulfilled' && logsRes.value?.data) {
        const list = Array.isArray(logsRes.value.data) ? logsRes.value.data : []
        auditLogs.value = list.map((log: any) => ({
          id: log.id,
          actorName: log.user?.name || log.actor || 'Hệ thống',
          actorRole: log.role || 'Staff',
          action: log.action || log.description || '',
          target: log.target || log.resource || '',
          timestamp: log.created_at ? new Date(log.created_at).toLocaleString('vi-VN') : '—',
          ip: log.ip || log.ip_address || '—',
          status: log.status || 'success',
        }))
      }
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể tải danh sách nhân sự.')
    } finally {
      loading.value = false
    }
  }

  const stats = computed(() => {
    const list = staffList.value
    return {
      totalStaff: list.length,
      activeManagers: list.filter((s) => s.role === 'Manager' || s.role === 'Owner').length,
      receptionistsOnline: list.filter((s) => s.role === 'Receptionist').length,
      systemRoles: 4,
    }
  })

  const filteredStaff = computed(() => {
    return staffList.value.filter((staff) => {
      const matchRole = selectedRoleFilter.value === 'all' || staff.role === selectedRoleFilter.value
      const q = searchQuery.value.trim().toLowerCase()
      const matchSearch =
        !q ||
        staff.name.toLowerCase().includes(q) ||
        staff.email.toLowerCase().includes(q) ||
        staff.phone.includes(q)
      return matchRole && matchSearch
    })
  })

  function showNotice(msg: string) {
    notice.value = msg
    setTimeout(() => {
      if (notice.value === msg) notice.value = ''
    }, 4000)
  }

  async function inviteStaff(member: { name: string; email: string; phone: string; role: StaffMember['role'] }) {
    loading.value = true
    try {
      await adminApi.addOrgMember(1, {
        email: member.email,
        role: member.role === 'Owner' ? 'organization_owner' : member.role === 'Manager' ? 'venue_manager' : 'cashier',
      })
      await loadStaffData()
      showInviteModal.value = false
      showNotice(`Đã gửi thư mời gia nhập tới ${member.email}!`)
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể mời nhân sự mới.')
    } finally {
      loading.value = false
    }
  }

  onMounted(() => {
    loadStaffData()
  })

  return {
    loading,
    activeView,
    searchQuery,
    selectedRoleFilter,
    selectedVenueFilter,
    showInviteModal,
    notice,
    error,
    staffList,
    auditLogs,
    stats,
    filteredStaff,
    showNotice,
    loadStaffData,
    inviteStaff,
  }
}
