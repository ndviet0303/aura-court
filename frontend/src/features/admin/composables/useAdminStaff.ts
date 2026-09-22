import { ref, computed, onMounted } from 'vue'
import { adminApi } from '../api/adminApi'
import { errorMessage } from '@/core/utils/errorMessage'

export interface StaffMember {
  id: number
  userId: number
  name: string
  email: string
  phone: string
  role: 'Owner' | 'Manager' | 'Receptionist' | 'Technician' | 'Referee' | 'Staff' | string
  rawRole: string
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
  rawDate: string
  ip: string
  status: 'success' | 'warning' | 'danger'
}

export interface VenueOption {
  id: number
  name: string
}

export function useAdminStaff() {
  const loading = ref(false)
  const saving = ref(false)
  const activeView = ref<'directory' | 'roles' | 'audit'>('directory')

  // Directory Filters
  const searchQuery = ref('')
  const selectedRoleFilter = ref<string>('all')
  const selectedStatusFilter = ref<string>('all')
  const selectedVenueFilter = ref<string | number>('all')

  // Audit Log Filters
  const auditSearchQuery = ref('')
  const auditActionFilter = ref<string>('all')
  const auditDateFilter = ref<string>('')

  // Modals State
  const showInviteModal = ref(false)
  const showEditModal = ref(false)
  const showRemoveModal = ref(false)
  const selectedMemberForEdit = ref<StaffMember | null>(null)
  const selectedMemberForRemove = ref<StaffMember | null>(null)

  // Feedback State
  const notice = ref('')
  const error = ref('')

  // Data Collections
  const staffList = ref<StaffMember[]>([])
  const auditLogs = ref<AuditLogItem[]>([])
  const venuesList = ref<VenueOption[]>([])
  const currentOrgId = ref<number>(1)

  function mapRoleLabel(role: string): StaffMember['role'] {
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
      case 'staff':
      case 'Staff':
        return 'Staff'
      case 'technician':
      case 'Technician':
        return 'Technician'
      case 'referee':
      case 'Referee':
        return 'Referee'
      default:
        return role || 'Staff'
    }
  }

  function mapRoleToBackend(role: string): string {
    switch (role) {
      case 'Owner':
      case 'organization_owner':
        return 'organization_owner'
      case 'Manager':
      case 'venue_manager':
        return 'venue_manager'
      case 'Receptionist':
      case 'cashier':
        return 'cashier'
      default:
        return 'staff'
    }
  }

  function calculatePermCount(role: string): number {
    switch (role) {
      case 'organization_owner':
      case 'Owner':
        return 28
      case 'venue_manager':
      case 'Manager':
        return 22
      case 'cashier':
      case 'Receptionist':
        return 14
      case 'technician':
      case 'Technician':
        return 8
      case 'referee':
      case 'Referee':
        return 6
      default:
        return 10
    }
  }

  function showNotice(msg: string) {
    notice.value = msg
    setTimeout(() => {
      if (notice.value === msg) notice.value = ''
    }, 4000)
  }

  async function loadStaffData() {
    loading.value = true
    error.value = ''
    try {
      const [membersRes, logsRes, venuesRes] = await Promise.allSettled([
        adminApi.getOrgMembers<{ members: any[] }>(currentOrgId.value),
        adminApi.getAuditLogs<any>(),
        adminApi.getVenues(),
      ])

      // Venues
      if (venuesRes.status === 'rejected') {
        error.value = errorMessage(venuesRes.reason, 'Không thể tải danh sách cơ sở.')
      } else if (venuesRes.value) {
        const rawVenues = Array.isArray(venuesRes.value) ? venuesRes.value : (venuesRes.value as any).data || []
        venuesList.value = rawVenues.map((v: any) => ({
          id: v.id,
          name: v.name,
        }))
      }

      // Members
      if (membersRes.status === 'rejected') {
        error.value = errorMessage(membersRes.reason, 'Không thể tải danh sách nhân sự.')
      } else if (membersRes.status === 'fulfilled') {
        const raw = (membersRes.value as any)?.members || (Array.isArray(membersRes.value) ? membersRes.value : [])
        staffList.value = raw.map((m: any) => {
          const venueFound = venuesList.value.find((v) => v.id === m.venue_id)
          return {
            id: m.id,
            userId: m.user_id || m.user?.id || 0,
            name: m.user?.name || m.name || 'Nhân viên',
            email: m.user?.email || m.email || '',
            phone: m.user?.phone || m.phone || '—',
            role: mapRoleLabel(m.role),
            rawRole: m.role,
            venueName: m.venue?.name || venueFound?.name || 'Tất cả cơ sở',
            venueId: m.venue_id || 0,
            status: m.status || 'active',
            avatar: m.user?.avatar || m.user?.avatar_url,
            lastActive: m.updated_at ? new Date(m.updated_at).toLocaleDateString('vi-VN') : '—',
            permissionsCount: calculatePermCount(m.role),
          }
        })
      }

      // Audit Logs
      if (logsRes.status === 'fulfilled') {
        const rawLogs = (logsRes.value as any)?.data || (Array.isArray(logsRes.value) ? logsRes.value : [])
        const list = Array.isArray(rawLogs) ? rawLogs : []
        auditLogs.value = list.map((log: any): AuditLogItem => ({
          id: Number(log.id) || 0,
          actorName: String(log.user?.name || log.actor || 'Hệ thống'),
          actorRole: String(log.role || log.user?.role || 'Staff'),
          action: String(log.action || log.description || 'Hoạt động hệ thống'),
          target: String(log.target || log.resource || log.auditable_type || '—'),
          timestamp: log.created_at ? new Date(log.created_at).toLocaleString('vi-VN') : '—',
          rawDate: log.created_at ? (new Date(log.created_at).toISOString().split('T')[0] ?? '') : '',
          ip: String(log.ip || log.ip_address || '127.0.0.1'),
          status: (log.status === 'warning' || log.status === 'danger' || log.status === 'success')
            ? log.status
            : (log.action?.includes('delete') || log.action?.includes('khoá') ? 'warning' : 'success'),
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
      receptionistsOnline: list.filter((s) => s.role === 'Receptionist' || s.role === 'Staff').length,
      systemRoles: 5,
    }
  })

  const filteredStaff = computed(() => {
    return staffList.value.filter((staff) => {
      // Role Filter
      if (selectedRoleFilter.value !== 'all') {
        const mappedFilter = mapRoleLabel(selectedRoleFilter.value)
        if (staff.role !== mappedFilter && staff.rawRole !== selectedRoleFilter.value) {
          return false
        }
      }

      // Status Filter
      if (selectedStatusFilter.value !== 'all') {
        if (staff.status !== selectedStatusFilter.value) {
          return false
        }
      }

      // Venue Filter
      if (selectedVenueFilter.value !== 'all') {
        const vId = Number(selectedVenueFilter.value)
        if (staff.venueId !== vId && vId !== 0) {
          return false
        }
      }

      // Search Query
      if (searchQuery.value.trim()) {
        const q = searchQuery.value.trim().toLowerCase()
        const matchName = staff.name.toLowerCase().includes(q)
        const matchEmail = staff.email.toLowerCase().includes(q)
        const matchPhone = staff.phone.includes(q)
        if (!matchName && !matchEmail && !matchPhone) {
          return false
        }
      }

      return true
    })
  })

  const filteredAuditLogs = computed(() => {
    return auditLogs.value.filter((log) => {
      // Date Filter
      if (auditDateFilter.value) {
        if (log.rawDate && log.rawDate !== auditDateFilter.value) {
          return false
        }
      }

      // Action Filter
      if (auditActionFilter.value !== 'all') {
        const act = log.action.toLowerCase()
        if (auditActionFilter.value === 'auth' && !act.includes('login') && !act.includes('đăng nhập') && !act.includes('xác thực')) return false
        if (auditActionFilter.value === 'pricing' && !act.includes('giá') && !act.includes('price') && !act.includes('sân')) return false
        if (auditActionFilter.value === 'payment' && !act.includes('tiền') && !act.includes('thanh toán') && !act.includes('hoàn')) return false
        if (auditActionFilter.value === 'staff' && !act.includes('nhân') && !act.includes('quyền') && !act.includes('member')) return false
      }

      // Search Query
      if (auditSearchQuery.value.trim()) {
        const q = auditSearchQuery.value.trim().toLowerCase()
        const matchActor = log.actorName.toLowerCase().includes(q)
        const matchAction = log.action.toLowerCase().includes(q)
        const matchTarget = log.target.toLowerCase().includes(q)
        const matchIp = log.ip.includes(q)
        if (!matchActor && !matchAction && !matchTarget && !matchIp) {
          return false
        }
      }

      return true
    })
  })

  async function inviteStaff(member: { name: string; email: string; phone?: string; role: StaffMember['role']; venueId?: number }) {
    saving.value = true
    error.value = ''
    try {
      const backendRole = mapRoleToBackend(member.role)
      await adminApi.addOrgMember(currentOrgId.value, {
        email: member.email,
        role: backendRole,
      })
      await loadStaffData()
      showInviteModal.value = false
      showNotice(`Đã gửi thư mời gia nhập tới ${member.email}!`)
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể mời nhân sự mới.')
      throw e
    } finally {
      saving.value = false
    }
  }

  function openEditModal(member: StaffMember) {
    selectedMemberForEdit.value = member
    showEditModal.value = true
  }

  async function updateMemberRole(memberId: number, payload: { role: string; status?: string }) {
    saving.value = true
    error.value = ''
    try {
      const backendRole = mapRoleToBackend(payload.role)
      await adminApi.updateOrgMember(currentOrgId.value, memberId, {
        role: backendRole,
        status: payload.status,
      })
      await loadStaffData()
      showEditModal.value = false
      selectedMemberForEdit.value = null
      showNotice('Đã cập nhật vai trò và trạng thái thành công!')
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể cập nhật nhân sự.')
      throw e
    } finally {
      saving.value = false
    }
  }

  function openRemoveModal(member: StaffMember) {
    selectedMemberForRemove.value = member
    showRemoveModal.value = true
  }

  async function removeMember(memberId: number) {
    saving.value = true
    error.value = ''
    try {
      await adminApi.removeOrgMember(currentOrgId.value, memberId)
      await loadStaffData()
      showRemoveModal.value = false
      selectedMemberForRemove.value = null
      showNotice('Đã xoá nhân sự khỏi hệ thống thành công!')
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể xoá nhân sự.')
      throw e
    } finally {
      saving.value = false
    }
  }

  async function toggleMemberStatus(member: StaffMember) {
    const newStatus = member.status === 'active' ? 'suspended' : 'active'
    await updateMemberRole(member.id, {
      role: member.rawRole,
      status: newStatus,
    })
  }

  onMounted(() => {
    loadStaffData()
  })

  return {
    loading,
    saving,
    activeView,
    searchQuery,
    selectedRoleFilter,
    selectedStatusFilter,
    selectedVenueFilter,
    auditSearchQuery,
    auditActionFilter,
    auditDateFilter,
    showInviteModal,
    showEditModal,
    showRemoveModal,
    selectedMemberForEdit,
    selectedMemberForRemove,
    notice,
    error,
    staffList,
    auditLogs,
    venuesList,
    stats,
    filteredStaff,
    filteredAuditLogs,
    showNotice,
    loadStaffData,
    inviteStaff,
    openEditModal,
    updateMemberRole,
    openRemoveModal,
    removeMember,
    toggleMemberStatus,
  }
}
