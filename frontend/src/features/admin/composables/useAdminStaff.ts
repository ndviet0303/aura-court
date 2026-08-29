import { ref, computed } from 'vue'

export interface StaffMember {
  id: number
  name: string
  email: string
  phone: string
  role: 'Owner' | 'Manager' | 'Receptionist' | 'Technician' | 'Referee'
  venueName: string
  venueId: number
  status: 'active' | 'suspended' | 'invited'
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
  const activeView = ref<'directory' | 'roles' | 'audit'>('directory')
  const searchQuery = ref('')
  const selectedRoleFilter = ref<string>('all')
  const selectedVenueFilter = ref<string>('all')
  const showInviteModal = ref(false)
  const notice = ref('')
  const error = ref('')

  const staffList = ref<StaffMember[]>([
    {
      id: 1,
      name: 'Elena Jackson',
      email: 'elena.j@auracourt.vn',
      phone: '0901 888 999',
      role: 'Owner',
      venueName: 'Tất cả cơ sở',
      venueId: 0,
      status: 'active',
      avatar: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=100&auto=format&fit=crop&q=80',
      lastActive: 'Vừa xong',
      permissionsCount: 28,
    },
    {
      id: 2,
      name: 'Marcus Reed',
      email: 'marcus.reed@auracourt.vn',
      phone: '0912 345 678',
      role: 'Manager',
      venueName: 'Downtown Arena (Quận 1)',
      venueId: 1,
      status: 'active',
      avatar: 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=100&auto=format&fit=crop&q=80',
      lastActive: '5 phút trước',
      permissionsCount: 22,
    },
    {
      id: 3,
      name: 'Nguyễn Thị Bích Trâm',
      email: 'tram.nguyen@auracourt.vn',
      phone: '0933 112 233',
      role: 'Receptionist',
      venueName: 'Downtown Arena (Quận 1)',
      venueId: 1,
      status: 'active',
      lastActive: '15 phút trước',
      permissionsCount: 12,
    },
    {
      id: 4,
      name: 'Trần Văn Hải',
      email: 'hai.tran@auracourt.vn',
      phone: '0988 990 011',
      role: 'Technician',
      venueName: 'Westside Courts (Bình Thạnh)',
      venueId: 2,
      status: 'active',
      lastActive: '2 giờ trước',
      permissionsCount: 8,
    },
    {
      id: 5,
      name: 'Phạm Đình Trọng',
      email: 'trong.referee@auracourt.vn',
      phone: '0977 445 566',
      role: 'Referee',
      venueName: 'Tất cả cơ sở',
      venueId: 0,
      status: 'active',
      lastActive: '1 ngày trước',
      permissionsCount: 6,
    },
  ])

  const auditLogs = ref<AuditLogItem[]>([
    {
      id: 101,
      actorName: 'Elena Jackson',
      actorRole: 'Owner',
      action: 'Cập nhật bảng giá Giờ Vàng',
      target: 'Sân 1 - Downtown Arena',
      timestamp: '10:45 AM, Hôm nay',
      ip: '118.69.182.45',
      status: 'success',
    },
    {
      id: 102,
      actorName: 'Marcus Reed',
      actorRole: 'Manager',
      action: 'Duyệt hoàn tiền đơn #BK-9902',
      target: 'Ví khách: Nguyễn Minh Tuấn',
      timestamp: '09:20 AM, Hôm nay',
      ip: '14.169.32.12',
      status: 'success',
    },
    {
      id: 103,
      actorName: 'Nguyễn Thị Bích Trâm',
      actorRole: 'Receptionist',
      action: 'Khoá sân khẩn cấp do sự cố đèn LED',
      target: 'Sân 4 - Westside Courts',
      timestamp: '18:10 PM, Hôm qua',
      ip: '14.169.32.12',
      status: 'warning',
    },
    {
      id: 104,
      actorName: 'Hệ thống Auth',
      actorRole: 'System',
      action: 'Phát hiện đăng nhập sai mật khẩu 5 lần',
      target: 'Tài khoản: trong.referee@auracourt.vn',
      timestamp: '02:15 AM, Hôm qua',
      ip: '45.119.82.1',
      status: 'danger',
    },
  ])

  const stats = computed(() => ({
    totalStaff: 18,
    activeManagers: 4,
    receptionistsOnline: 8,
    systemRoles: 5,
  }))

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

  function inviteStaff(member: { name: string; email: string; phone: string; role: StaffMember['role'] }) {
    staffList.value.push({
      id: Date.now(),
      name: member.name,
      email: member.email,
      phone: member.phone,
      role: member.role,
      venueName: 'Downtown Arena (Quận 1)',
      venueId: 1,
      status: 'invited',
      lastActive: 'Chưa kích hoạt',
      permissionsCount: 10,
    })
    showInviteModal.value = false
    showNotice(`Đã gửi thư mời gia nhập tới ${member.email}!`)
  }

  return {
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
    inviteStaff,
  }
}
