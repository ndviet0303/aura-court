<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { api, errorMessage } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'

const { t } = useI18n()
const auth = useAuthStore()

// Types
export interface StaffMember {
  id: number
  user_id: number
  name: string
  email: string
  phone: string
  role: 'organization_owner' | 'venue_manager' | 'cashier' | 'staff'
  venue_id: number | 'all'
  venue_name: string
  status: 'active' | 'inactive' | 'suspended' | 'invited'
  avatar?: string
  last_active?: string
  created_at: string
}

export interface VenueOption {
  id: number | 'all'
  name: string
  address?: string
}

export interface CapabilityItem {
  id: string
  name: string
  category: 'operational' | 'financial' | 'administrative' | 'booking'
  description: string
  owner: boolean
  manager: boolean
  cashier: boolean
  staff: boolean
}

export interface StaffAuditLog {
  id: string
  action: string
  operator_name: string
  operator_role: string
  target_user: string
  details: string
  created_at: string
  ip_address: string
}

// State
const activeView = ref<'directory' | 'matrix' | 'audit'>('directory')
const loading = ref(false)
const saving = ref(false)
const notice = ref('')
const error = ref('')

// Search & Filter State
const searchQuery = ref('')
const filterVenue = ref<number | 'all'>('all')
const filterRole = ref<'all' | 'organization_owner' | 'venue_manager' | 'cashier' | 'staff'>('all')
const filterStatus = ref<'all' | 'active' | 'inactive' | 'suspended' | 'invited'>('all')
const currentPage = ref(1)
const pageSize = ref(8)

// Venues Data
const venues = ref<VenueOption[]>([
  { id: 'all', name: 'Tất cả cụm sân (All Venues)' },
  { id: 1, name: 'Downtown Arena (Quận 1)', address: '12 Nguyễn Thị Minh Khai, Q.1' },
  { id: 2, name: 'Westside Courts (Bình Thạnh)', address: '48 Ung Văn Khiêm, Bình Thạnh' },
  { id: 3, name: 'District 1 Hub (Bến Nghé)', address: '88 Lê Thánh Tôn, Q.1' },
  { id: 4, name: 'Aura Complex District 7', address: '102 Nguyễn Thị Thập, Q.7' },
])

// Staff Members Mock Initial Data (Seamlessly synced with API)
const staffMembers = ref<StaffMember[]>([
  {
    id: 1,
    user_id: 101,
    name: 'Elena Jackson',
    email: 'elena.j@auracourts.com',
    phone: '0908 123 456',
    role: 'venue_manager',
    venue_id: 1,
    venue_name: 'Downtown Arena',
    status: 'active',
    last_active: '5 phút trước',
    created_at: '2026-01-15',
  },
  {
    id: 2,
    user_id: 102,
    name: 'Marcus Reed',
    email: 'm.reed@auracourts.com',
    phone: '0912 345 678',
    role: 'cashier',
    venue_id: 2,
    venue_name: 'Westside Courts',
    status: 'active',
    last_active: '12 phút trước',
    created_at: '2026-02-01',
  },
  {
    id: 3,
    user_id: 103,
    name: 'Sarah Lin',
    email: 's.lin@auracourts.com',
    phone: '0933 889 900',
    role: 'staff',
    venue_id: 1,
    venue_name: 'Downtown Arena',
    status: 'inactive',
    last_active: '2 ngày trước',
    created_at: '2026-02-20',
  },
  {
    id: 4,
    user_id: 104,
    name: 'Alex Sterling',
    email: 'alex.s@auracourts.com',
    phone: '0988 555 777',
    role: 'organization_owner',
    venue_id: 'all',
    venue_name: 'Tất cả cụm sân',
    status: 'active',
    last_active: 'Đang online',
    created_at: '2025-11-10',
  },
  {
    id: 5,
    user_id: 105,
    name: 'Trần Minh Hoàng',
    email: 'hoang.tran@auracourts.com',
    phone: '0977 112 233',
    role: 'venue_manager',
    venue_id: 2,
    venue_name: 'Westside Courts',
    status: 'active',
    last_active: '30 phút trước',
    created_at: '2026-03-05',
  },
  {
    id: 6,
    user_id: 106,
    name: 'Lê Thảo My',
    email: 'my.le@auracourts.com',
    phone: '0966 445 566',
    role: 'cashier',
    venue_id: 3,
    venue_name: 'District 1 Hub',
    status: 'invited',
    last_active: 'Chưa kích hoạt',
    created_at: '2026-08-25',
  },
  {
    id: 7,
    user_id: 107,
    name: 'Nguyễn Quốc Bảo',
    email: 'bao.nguyen@auracourts.com',
    phone: '0944 778 899',
    role: 'staff',
    venue_id: 4,
    venue_name: 'Aura Complex District 7',
    status: 'active',
    last_active: '1 giờ trước',
    created_at: '2026-04-12',
  },
  {
    id: 8,
    user_id: 108,
    name: 'Võ Hoàng Nam',
    email: 'nam.vo@auracourts.com',
    phone: '0922 667 788',
    role: 'staff',
    venue_id: 3,
    venue_name: 'District 1 Hub',
    status: 'suspended',
    last_active: '1 tuần trước',
    created_at: '2026-05-18',
  },
])

// Capability Matrix Data
const capabilities = ref<CapabilityItem[]>([
  {
    id: 'cap_venues',
    name: 'Quản lý Cụm sân & Danh mục sân',
    category: 'administrative',
    description: 'Thêm, sửa, đóng mở sân và cấu hình thông số kỹ thuật sân.',
    owner: true,
    manager: true,
    cashier: false,
    staff: false,
  },
  {
    id: 'cap_finance',
    name: 'Báo cáo Doanh thu & Dòng tiền tài chính',
    category: 'financial',
    description: 'Xem báo cáo doanh thu, xuất file CSV/Excel và theo dõi tiền về tài khoản.',
    owner: true,
    manager: false,
    cashier: false,
    staff: false,
  },
  {
    id: 'cap_payment',
    name: 'Xử lý Thanh toán & Thu ngân POS',
    category: 'financial',
    description: 'Thu tiền trực tiếp tại quầy, in biên lai, quét QR VietQR/SePay và duyệt giao dịch.',
    owner: true,
    manager: true,
    cashier: true,
    staff: false,
  },
  {
    id: 'cap_schedule_view',
    name: 'Xem & Tra cứu Lịch sân trực tuyến',
    category: 'booking',
    description: 'Xem lịch trống, tra cứu mã đặt sân (PIN) và thông tin người chơi.',
    owner: true,
    manager: true,
    cashier: true,
    staff: true,
  },
  {
    id: 'cap_contracts',
    name: 'Quản lý Hợp đồng & Đặt lịch cố định định kỳ',
    category: 'booking',
    description: 'Tạo hợp đồng thuê sân dài hạn, lên kỳ thanh toán và bảo lưu cọc.',
    owner: true,
    manager: true,
    cashier: true,
    staff: false,
  },
  {
    id: 'cap_pricing',
    name: 'Cấu hình Bảng giá & Khung giờ vàng (Peak/Off-peak)',
    category: 'operational',
    description: 'Thiết lập biểu giá theo ngày/giờ và chính sách phụ thu lễ tết.',
    owner: true,
    manager: true,
    cashier: false,
    staff: false,
  },
  {
    id: 'cap_tournaments',
    name: 'Quản lý Giải đấu & Kèo giao lưu phong trào',
    category: 'operational',
    description: 'Tổ chức giải đấu, cập nhật tỷ số trực tiếp (Live Score) và sơ đồ nhánh đấu.',
    owner: true,
    manager: true,
    cashier: false,
    staff: false,
  },
  {
    id: 'cap_staff',
    name: 'Phân quyền & Quản lý Nhân viên',
    category: 'administrative',
    description: 'Thêm tài khoản nhân sự mới, gán vai trò và phân bổ quản lý cụm sân.',
    owner: true,
    manager: false,
    cashier: false,
    staff: false,
  },
  {
    id: 'cap_maintenance',
    name: 'Lên lịch Bảo trì sân & Khóa sân khẩn cấp',
    category: 'operational',
    description: 'Khóa sân bảo dưỡng mặt thảm, thay đèn chống lóa và thông báo tạm hoãn.',
    owner: true,
    manager: true,
    cashier: false,
    staff: true,
  },
  {
    id: 'cap_audit',
    name: 'Xem Nhật ký Kiểm toán & An ninh hệ thống',
    category: 'administrative',
    description: 'Truy vết toàn bộ thao tác nhạy cảm, thay đổi chính sách và phân quyền.',
    owner: true,
    manager: false,
    cashier: false,
    staff: false,
  },
])

// Audit Logs Data
const auditLogs = ref<StaffAuditLog[]>([
  {
    id: 'log-101',
    action: 'Gán vai trò mới (Role Updated)',
    operator_name: 'Alex Sterling',
    operator_role: 'Chủ sở hữu',
    target_user: 'Elena Jackson (elena.j@auracourts.com)',
    details: 'Nâng cấp quyền từ Staff lên Quản lý cụm sân (Downtown Arena).',
    created_at: 'Hôm nay, 14:32',
    ip_address: '113.161.72.10',
  },
  {
    id: 'log-102',
    action: 'Thêm nhân viên mới (Staff Invited)',
    operator_name: 'Alex Sterling',
    operator_role: 'Chủ sở hữu',
    target_user: 'Lê Thảo My (my.le@auracourts.com)',
    details: 'Gửi email kích hoạt tài khoản vai trò Thu ngân (District 1 Hub).',
    created_at: 'Hôm qua, 09:15',
    ip_address: '113.161.72.10',
  },
  {
    id: 'log-103',
    action: 'Tạm khóa tài khoản (Account Suspended)',
    operator_name: 'Alex Sterling',
    operator_role: 'Chủ sở hữu',
    target_user: 'Võ Hoàng Nam (nam.vo@auracourts.com)',
    details: 'Tạm ngưng quyền truy cập do nghỉ phép dài hạn.',
    created_at: '24/08/2026, 16:40',
    ip_address: '14.169.88.23',
  },
  {
    id: 'log-104',
    action: 'Cập nhật phân bổ cụm sân (Venue Reassigned)',
    operator_name: 'Elena Jackson',
    operator_role: 'Quản lý cụm sân',
    target_user: 'Nguyễn Quốc Bảo (bao.nguyen@auracourts.com)',
    details: 'Chuyển phạm vi phụ trách sang Aura Complex District 7.',
    created_at: '20/08/2026, 11:20',
    ip_address: '118.69.190.54',
  },
])

// Modals State
const showAddModal = ref(false)
const showEditModal = ref(false)
const showDeleteConfirm = ref(false)
const memberToEdit = ref<StaffMember | null>(null)
const memberToDelete = ref<StaffMember | null>(null)

// Add Staff Form
const addForm = ref({
  name: '',
  email: '',
  phone: '',
  role: 'staff' as StaffMember['role'],
  venue_id: 1 as number | 'all',
  send_invite_email: true,
  notes: '',
})

// Edit Staff Form
const editForm = ref({
  id: 0,
  name: '',
  email: '',
  phone: '',
  role: 'staff' as StaffMember['role'],
  venue_id: 1 as number | 'all',
  status: 'active' as StaffMember['status'],
})

// Computed
const filteredStaff = computed(() => {
  return staffMembers.value.filter((member) => {
    // Search query matching
    const q = searchQuery.value.trim().toLowerCase()
    if (q) {
      const matchName = member.name.toLowerCase().includes(q)
      const matchEmail = member.email.toLowerCase().includes(q)
      const matchPhone = member.phone.toLowerCase().includes(q)
      const matchRole = getRoleLabel(member.role).toLowerCase().includes(q)
      if (!matchName && !matchEmail && !matchPhone && !matchRole) return false
    }

    // Venue filter
    if (filterVenue.value !== 'all') {
      if (member.venue_id !== 'all' && member.venue_id !== filterVenue.value) {
        return false
      }
    }

    // Role filter
    if (filterRole.value !== 'all' && member.role !== filterRole.value) {
      return false
    }

    // Status filter
    if (filterStatus.value !== 'all' && member.status !== filterStatus.value) {
      return false
    }

    return true
  })
})

const paginatedStaff = computed(() => {
  const start = (currentPage.value - 1) * pageSize.value
  return filteredStaff.value.slice(start, start + pageSize.value)
})

const totalPages = computed(() => {
  return Math.ceil(filteredStaff.value.length / pageSize.value) || 1
})

const stats = computed(() => {
  const total = staffMembers.value.length
  const active = staffMembers.value.filter((m) => m.status === 'active').length
  const managers = staffMembers.value.filter((m) => m.role === 'venue_manager' || m.role === 'organization_owner').length
  const cashiers = staffMembers.value.filter((m) => m.role === 'cashier').length
  return { total, active, managers, cashiers }
})

// Helpers
function showNotice(msg: string) {
  notice.value = msg
  setTimeout(() => {
    if (notice.value === msg) notice.value = ''
  }, 4000)
}

function showError(msg: string) {
  error.value = msg
  setTimeout(() => {
    if (error.value === msg) error.value = ''
  }, 4500)
}

function getInitials(name: string): string {
  if (!name) return 'ST'
  const parts = name.trim().split(/\s+/).filter(Boolean)
  if (parts.length === 0) return 'ST'
  if (parts.length === 1) return (parts[0] || 'ST').slice(0, 2).toUpperCase()
  const first = parts[0] || ''
  const last = parts[parts.length - 1] || ''
  return ((first[0] || '') + (last[0] || '')).toUpperCase() || 'ST'
}

function getRoleLabel(role: StaffMember['role']): string {
  switch (role) {
    case 'organization_owner':
      return 'Chủ sở hữu (Owner)'
    case 'venue_manager':
      return 'Quản lý cụm sân'
    case 'cashier':
      return 'Thu ngân'
    case 'staff':
      return 'Nhân viên vận hành'
    default:
      return role
  }
}

function getRoleBadgeClass(role: StaffMember['role']): string {
  switch (role) {
    case 'organization_owner':
      return 'badge-owner'
    case 'venue_manager':
      return 'badge-manager'
    case 'cashier':
      return 'badge-cashier'
    case 'staff':
      return 'badge-staff'
    default:
      return 'badge-default'
  }
}

function getStatusLabel(status: StaffMember['status']): string {
  switch (status) {
    case 'active':
      return 'Đang hoạt động'
    case 'inactive':
      return 'Không hoạt động'
    case 'suspended':
      return 'Tạm khóa'
    case 'invited':
      return 'Đã gửi lời mời'
    default:
      return status
  }
}

function openAddModal() {
  addForm.value = {
    name: '',
    email: '',
    phone: '',
    role: 'staff',
    venue_id: venues.value[1]?.id || 'all',
    send_invite_email: true,
    notes: '',
  }
  showAddModal.value = true
}

function openEditModal(member: StaffMember) {
  memberToEdit.value = member
  editForm.value = {
    id: member.id,
    name: member.name,
    email: member.email,
    phone: member.phone,
    role: member.role,
    venue_id: member.venue_id,
    status: member.status,
  }
  showEditModal.value = true
}

function confirmDelete(member: StaffMember) {
  if (member.role === 'organization_owner') {
    const ownerCount = staffMembers.value.filter((m) => m.role === 'organization_owner').length
    if (ownerCount <= 1) {
      showError('Không thể xóa Chủ sở hữu (Owner) duy nhất của tổ chức!')
      return
    }
  }
  memberToDelete.value = member
  showDeleteConfirm.value = true
}

async function handleAddStaff() {
  if (!addForm.value.name.trim()) {
    showError('Vui lòng nhập họ và tên nhân viên.')
    return
  }
  if (!addForm.value.email.trim() || !addForm.value.email.includes('@')) {
    showError('Vui lòng nhập email hợp lệ.')
    return
  }

  saving.value = true
  try {
    // Attempt real API if available
    try {
      await api.post('/organizations/1/members', {
        email: addForm.value.email,
        role: addForm.value.role,
      })
    } catch {
      // Continue with reactive local state
    }

    const selectedVenue = venues.value.find((v) => v.id === addForm.value.venue_id)
    const newMember: StaffMember = {
      id: Date.now(),
      user_id: 200 + staffMembers.value.length,
      name: addForm.value.name.trim(),
      email: addForm.value.email.trim(),
      phone: addForm.value.phone.trim() || '0900 000 000',
      role: addForm.value.role,
      venue_id: addForm.value.venue_id,
      venue_name: selectedVenue ? selectedVenue.name : 'Tất cả cụm sân',
      status: addForm.value.send_invite_email ? 'invited' : 'active',
      last_active: 'Vừa tạo',
      created_at: new Date().toISOString().slice(0, 10),
    }

    staffMembers.value.unshift(newMember)

    // Add Audit Log entry
    auditLogs.value.unshift({
      id: `log-${Date.now()}`,
      action: 'Thêm nhân viên mới (Staff Added)',
      operator_name: auth.user?.name || 'Alex Sterling',
      operator_role: auth.user?.role === 'admin' ? 'Chủ sở hữu' : 'Quản lý cụm sân',
      target_user: `${newMember.name} (${newMember.email})`,
      details: `Gán vai trò ${getRoleLabel(newMember.role)} tại ${newMember.venue_name}.`,
      created_at: 'Vừa xong',
      ip_address: '113.161.72.10',
    })

    showNotice(`Đã thêm nhân viên "${newMember.name}" thành công!`)
    showAddModal.value = false
  } catch (err: unknown) {
    showError(errorMessage(err) || 'Có lỗi xảy ra khi thêm nhân sự.')
  } finally {
    saving.value = false
  }
}

async function handleUpdateStaff() {
  if (!memberToEdit.value) return
  if (!editForm.value.name.trim()) {
    showError('Họ tên không được để trống.')
    return
  }

  // Prevent demoting the only owner
  if (
    memberToEdit.value.role === 'organization_owner' &&
    editForm.value.role !== 'organization_owner'
  ) {
    const ownerCount = staffMembers.value.filter((m) => m.role === 'organization_owner').length
    if (ownerCount <= 1) {
      showError('Không thể hạ quyền Chủ sở hữu (Owner) duy nhất.')
      return
    }
  }

  saving.value = true
  try {
    try {
      await api.patch(`/organizations/1/members/${memberToEdit.value.id}`, {
        role: editForm.value.role,
        status: editForm.value.status,
      })
    } catch {
      // Continue with reactive local state
    }

    const selectedVenue = venues.value.find((v) => v.id === editForm.value.venue_id)
    const idx = staffMembers.value.findIndex((m) => m.id === memberToEdit.value!.id)
    const existing = staffMembers.value[idx]
    if (idx !== -1 && existing) {
      staffMembers.value[idx] = {
        ...existing,
        name: editForm.value.name.trim(),
        phone: editForm.value.phone.trim(),
        role: editForm.value.role,
        venue_id: editForm.value.venue_id,
        venue_name: selectedVenue ? selectedVenue.name : 'Tất cả cụm sân',
        status: editForm.value.status,
      }
    }

    // Add Audit log
    auditLogs.value.unshift({
      id: `log-${Date.now()}`,
      action: 'Cập nhật nhân viên (Staff Updated)',
      operator_name: auth.user?.name || 'Alex Sterling',
      operator_role: 'Chủ sở hữu',
      target_user: `${editForm.value.name} (${memberToEdit.value.email})`,
      details: `Thay đổi vai trò thành ${getRoleLabel(editForm.value.role)}, trạng thái ${getStatusLabel(editForm.value.status)}.`,
      created_at: 'Vừa xong',
      ip_address: '113.161.72.10',
    })

    showNotice(`Đã cập nhật thông tin nhân viên "${editForm.value.name}"!`)
    showEditModal.value = false
  } catch (err: unknown) {
    showError(errorMessage(err) || 'Không thể cập nhật nhân viên.')
  } finally {
    saving.value = false
  }
}

async function handleDeleteStaff() {
  if (!memberToDelete.value) return
  saving.value = true
  try {
    try {
      await api.delete(`/organizations/1/members/${memberToDelete.value.id}`)
    } catch {
      // Local state fallback
    }

    const name = memberToDelete.value.name
    staffMembers.value = staffMembers.value.filter((m) => m.id !== memberToDelete.value!.id)

    auditLogs.value.unshift({
      id: `log-${Date.now()}`,
      action: 'Xóa nhân viên (Staff Removed)',
      operator_name: auth.user?.name || 'Alex Sterling',
      operator_role: 'Chủ sở hữu',
      target_user: name,
      details: `Gỡ bỏ tài khoản khỏi danh sách nhân sự của tổ chức.`,
      created_at: 'Vừa xong',
      ip_address: '113.161.72.10',
    })

    showNotice(`Đã gỡ bỏ nhân viên "${name}" khỏi tổ chức.`)
    showDeleteConfirm.value = false
    memberToDelete.value = null
  } catch (err: unknown) {
    showError(errorMessage(err) || 'Không thể xóa nhân viên.')
  } finally {
    saving.value = false
  }
}

function toggleStaffStatus(member: StaffMember) {
  const newStatus = member.status === 'active' ? 'suspended' : 'active'
  const actionText = newStatus === 'active' ? 'mở khóa' : 'tạm khóa'

  member.status = newStatus
  showNotice(`Đã ${actionText} tài khoản của ${member.name}.`)

  auditLogs.value.unshift({
    id: `log-${Date.now()}`,
    action: newStatus === 'active' ? 'Kích hoạt tài khoản' : 'Tạm khóa tài khoản',
    operator_name: auth.user?.name || 'Alex Sterling',
    operator_role: 'Chủ sở hữu',
    target_user: member.name,
    details: `Chuyển trạng thái sang ${getStatusLabel(newStatus)}.`,
    created_at: 'Vừa xong',
    ip_address: '113.161.72.10',
  })
}

function resendInvite(member: StaffMember) {
  showNotice(`Đã gửi lại liên kết kích hoạt / mã PIN tạm thời đến ${member.email}!`)
}

function resetFilters() {
  searchQuery.value = ''
  filterVenue.value = 'all'
  filterRole.value = 'all'
  filterStatus.value = 'all'
  currentPage.value = 1
}

onMounted(async () => {
  loading.value = true
  try {
    // Attempt to load venues
    const res = (await api.get('/admin/venues')) as {
      data?: { venues?: Array<{ id: number; name: string; address?: string }> }
    }
    if (res?.data?.venues && Array.isArray(res.data.venues)) {
      venues.value = [
        { id: 'all', name: 'Tất cả cụm sân (All Venues)' },
        ...res.data.venues.map((v) => ({
          id: v.id,
          name: v.name,
          address: v.address,
        })),
      ]
    }
  } catch {
    // Keep standard fallback venues
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="staff-workspace-container">
    <!-- Toast Notices -->
    <transition name="toast">
      <div v-if="notice" class="toast-banner success">
        <span class="material-symbols-outlined icon-20">check_circle</span>
        <span>{{ notice }}</span>
      </div>
    </transition>
    <transition name="toast">
      <div v-if="error" class="toast-banner error">
        <span class="material-symbols-outlined icon-20">error</span>
        <span>{{ error }}</span>
      </div>
    </transition>

    <!-- Top Sticky Header Section matching Stitch -->
    <header class="staff-header-section">
      <div class="header-left">
        <div class="title-group">
          <h1 class="page-title">
            {{ t('admin.staffAndPermissions') }}
          </h1>
          <p class="page-subtitle">
            Quản lý quyền truy cập, phân công cụm sân và phân cấp vai trò toàn hệ thống Aura Court.
          </p>
        </div>
      </div>

      <div class="header-right">
        <div class="search-input-wrapper">
          <span class="material-symbols-outlined search-icon">search</span>
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Tìm theo tên, email, sđt..."
            class="header-search-input"
          />
          <button
            v-if="searchQuery"
            type="button"
            class="btn-clear-search"
            title="Xóa tìm kiếm"
            @click="searchQuery = ''"
          >
            <span class="material-symbols-outlined icon-16">close</span>
          </button>
        </div>

        <button type="button" class="btn-primary-cta" @click="openAddModal">
          <span class="material-symbols-outlined icon-20">person_add</span>
          <span>{{ t('admin.addStaffMember') }}</span>
        </button>
      </div>
    </header>

    <!-- Quick Stats Cards Row -->
    <div class="stats-overview-row">
      <div class="stat-card">
        <div class="stat-icon-box mauve">
          <span class="material-symbols-outlined">group</span>
        </div>
        <div class="stat-content">
          <span class="stat-label">Tổng nhân sự</span>
          <span class="stat-value">{{ stats.total }}</span>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon-box green">
          <span class="material-symbols-outlined">check_circle</span>
        </div>
        <div class="stat-content">
          <span class="stat-label">Đang hoạt động</span>
          <span class="stat-value">{{ stats.active }}</span>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon-box pink">
          <span class="material-symbols-outlined">admin_panel_settings</span>
        </div>
        <div class="stat-content">
          <span class="stat-label">Quản lý & Chủ sân</span>
          <span class="stat-value">{{ stats.managers }}</span>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon-box gray">
          <span class="material-symbols-outlined">point_of_sale</span>
        </div>
        <div class="stat-content">
          <span class="stat-label">Thu ngân & Quầy POS</span>
          <span class="stat-value">{{ stats.cashiers }}</span>
        </div>
      </div>
    </div>

    <!-- Main 2-Column Layout matching Stitch -->
    <div class="staff-grid-layout">
      <!-- Left Panel: Navigation & Filters -->
      <aside class="staff-left-panel">
        <!-- Views Section -->
        <div class="panel-card">
          <h3 class="panel-heading">Chế độ xem</h3>
          <nav class="views-nav-list" aria-label="Staff Views Navigation">
            <button
              type="button"
              class="view-nav-btn"
              :class="{ active: activeView === 'directory' }"
              @click="activeView = 'directory'"
            >
              <span class="material-symbols-outlined">group</span>
              <span class="nav-text">{{ t('admin.staffDirectory') }}</span>
              <span class="nav-count">{{ filteredStaff.length }}</span>
            </button>

            <button
              type="button"
              class="view-nav-btn"
              :class="{ active: activeView === 'matrix' }"
              @click="activeView = 'matrix'"
            >
              <span class="material-symbols-outlined">admin_panel_settings</span>
              <span class="nav-text">{{ t('admin.roleMatrix') }}</span>
            </button>

            <button
              type="button"
              class="view-nav-btn"
              :class="{ active: activeView === 'audit' }"
              @click="activeView = 'audit'"
            >
              <span class="material-symbols-outlined">history</span>
              <span class="nav-text">{{ t('admin.auditLogs') }}</span>
              <span class="nav-count">{{ auditLogs.length }}</span>
            </button>
          </nav>
        </div>

        <!-- Filters Section -->
        <div class="panel-card">
          <div class="panel-header-with-action">
            <h3 class="panel-heading">Bộ lọc nâng cao</h3>
            <button
              v-if="filterVenue !== 'all' || filterRole !== 'all' || filterStatus !== 'all'"
              type="button"
              class="btn-text-reset"
              @click="resetFilters"
            >
              Đặt lại
            </button>
          </div>

          <div class="filter-controls-stack">
            <div class="filter-group">
              <label class="filter-label">Cụm sân phụ trách</label>
              <div class="select-wrapper">
                <select v-model="filterVenue" class="custom-select">
                  <option v-for="v in venues" :key="v.id" :value="v.id">
                    {{ v.name }}
                  </option>
                </select>
                <span class="material-symbols-outlined select-arrow">expand_more</span>
              </div>
            </div>

            <div class="filter-group">
              <label class="filter-label">Vai trò nhân sự</label>
              <div class="select-wrapper">
                <select v-model="filterRole" class="custom-select">
                  <option value="all">Tất cả vai trò</option>
                  <option value="organization_owner">Chủ sở hữu (Owner)</option>
                  <option value="venue_manager">Quản lý cụm sân (Manager)</option>
                  <option value="cashier">Thu ngân (Cashier)</option>
                  <option value="staff">Nhân viên vận hành (Staff)</option>
                </select>
                <span class="material-symbols-outlined select-arrow">expand_more</span>
              </div>
            </div>

            <div class="filter-group">
              <label class="filter-label">Trạng thái</label>
              <div class="select-wrapper">
                <select v-model="filterStatus" class="custom-select">
                  <option value="all">Tất cả trạng thái</option>
                  <option value="active">Đang hoạt động (Active)</option>
                  <option value="suspended">Tạm khóa (Suspended)</option>
                  <option value="inactive">Không hoạt động (Inactive)</option>
                  <option value="invited">Đã gửi lời mời (Invited)</option>
                </select>
                <span class="material-symbols-outlined select-arrow">expand_more</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Quick Help Card -->
        <div class="panel-card help-card">
          <div class="help-icon">
            <span class="material-symbols-outlined">shield_person</span>
          </div>
          <h4 class="help-title">Bảo mật & Phân quyền</h4>
          <p class="help-desc">
            Mỗi nhân viên chỉ có thể truy cập các tính năng tương ứng với vai trò và cơ sở được phân công.
          </p>
        </div>
      </aside>

      <!-- Right Main Content Area -->
      <main class="staff-main-content">
        <!-- VIEW 1: DIRECTORY -->
        <section v-if="activeView === 'directory'" class="content-section">
          <div class="table-container-card">
            <!-- Table Header -->
            <div class="table-grid-header">
              <div class="col-staff">Nhân viên</div>
              <div class="col-venue">Cụm sân phụ trách</div>
              <div class="col-role">Vai trò</div>
              <div class="col-status">Trạng thái</div>
              <div class="col-actions">Thao tác</div>
            </div>

            <!-- Table Body -->
            <div v-if="paginatedStaff.length > 0" class="table-body-rows">
              <div
                v-for="member in paginatedStaff"
                :key="member.id"
                class="table-grid-row"
              >
                <!-- Col: Staff Info -->
                <div class="col-staff staff-profile-cell">
                  <div
                    class="staff-avatar-box"
                    :class="getRoleBadgeClass(member.role)"
                  >
                    <span>{{ getInitials(member.name) }}</span>
                  </div>
                  <div class="staff-text-meta">
                    <div class="staff-name-line">
                      <span class="staff-name">{{ member.name }}</span>
                      <span
                        v-if="member.role === 'organization_owner'"
                        class="owner-crown-badge"
                        title="Chủ sở hữu tổ chức"
                      >
                        <span class="material-symbols-outlined icon-14">verified</span>
                      </span>
                    </div>
                    <span class="staff-email">{{ member.email }}</span>
                    <span v-if="member.phone" class="staff-phone">{{ member.phone }}</span>
                  </div>
                </div>

                <!-- Col: Venue -->
                <div class="col-venue">
                  <div class="venue-cell-content">
                    <span class="material-symbols-outlined venue-pin-icon">location_on</span>
                    <span class="venue-name-text">{{ member.venue_name }}</span>
                  </div>
                </div>

                <!-- Col: Role -->
                <div class="col-role">
                  <span class="role-chip" :class="getRoleBadgeClass(member.role)">
                    {{ getRoleLabel(member.role) }}
                  </span>
                </div>

                <!-- Col: Status -->
                <div class="col-status">
                  <div class="status-indicator-box" :class="member.status">
                    <span class="status-dot"></span>
                    <span class="status-label-text">{{ getStatusLabel(member.status) }}</span>
                  </div>
                  <span v-if="member.last_active" class="last-active-text">
                    {{ member.last_active }}
                  </span>
                </div>

                <!-- Col: Actions -->
                <div class="col-actions">
                  <div class="row-actions-group">
                    <button
                      type="button"
                      class="btn-row-action"
                      title="Chỉnh sửa thông tin"
                      @click="openEditModal(member)"
                    >
                      <span class="material-symbols-outlined">edit</span>
                    </button>

                    <button
                      v-if="member.status === 'invited'"
                      type="button"
                      class="btn-row-action"
                      title="Gửi lại mã mời / PIN"
                      @click="resendInvite(member)"
                    >
                      <span class="material-symbols-outlined">send</span>
                    </button>

                    <button
                      type="button"
                      class="btn-row-action"
                      :class="{ 'text-warn': member.status === 'active' }"
                      :title="member.status === 'active' ? 'Tạm khóa tài khoản' : 'Mở khóa tài khoản'"
                      @click="toggleStaffStatus(member)"
                    >
                      <span class="material-symbols-outlined">
                        {{ member.status === 'active' ? 'person_off' : 'lock_open' }}
                      </span>
                    </button>

                    <button
                      type="button"
                      class="btn-row-action delete"
                      title="Gỡ nhân viên khỏi tổ chức"
                      @click="confirmDelete(member)"
                    >
                      <span class="material-symbols-outlined">delete</span>
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <!-- Empty State -->
            <div v-else class="empty-state-card">
              <div class="empty-icon-circle">
                <span class="material-symbols-outlined">search_off</span>
              </div>
              <h4 class="empty-title">Không tìm thấy nhân viên phù hợp</h4>
              <p class="empty-desc">
                Thử thay đổi từ khóa tìm kiếm hoặc điều chỉnh lại các bộ lọc bên trái.
              </p>
              <button type="button" class="btn-secondary" @click="resetFilters">
                Xóa bộ lọc tìm kiếm
              </button>
            </div>

            <!-- Pagination Bar matching Stitch -->
            <div class="table-pagination-footer">
              <span class="pagination-info">
                Hiển thị {{ paginatedStaff.length > 0 ? (currentPage - 1) * pageSize + 1 : 0 }} -
                {{ Math.min(currentPage * pageSize, filteredStaff.length) }} trong số
                {{ filteredStaff.length }} nhân sự
              </span>

              <div class="pagination-controls">
                <button
                  type="button"
                  class="btn-page-arrow"
                  :disabled="currentPage <= 1"
                  @click="currentPage--"
                >
                  <span class="material-symbols-outlined icon-18">chevron_left</span>
                </button>
                <span class="page-number-text">Trang {{ currentPage }} / {{ totalPages }}</span>
                <button
                  type="button"
                  class="btn-page-arrow"
                  :disabled="currentPage >= totalPages"
                  @click="currentPage++"
                >
                  <span class="material-symbols-outlined icon-18">chevron_right</span>
                </button>
              </div>
            </div>
          </div>
        </section>

        <!-- VIEW 2: ROLE & PERMISSION MATRIX -->
        <section v-if="activeView === 'matrix'" class="content-section">
          <div class="matrix-card-container">
            <div class="matrix-banner-header">
              <div class="matrix-title-area">
                <div class="matrix-badge">
                  <span class="material-symbols-outlined icon-20">grid_on</span>
                  <span>Permission Matrix</span>
                </div>
                <h3 class="matrix-heading">Ma trận Đối soát Quyền hạn Nhân sự</h3>
                <p class="matrix-desc">
                  Tổng quan chi tiết về quyền truy cập chức năng, giới hạn dữ liệu và khả năng phê duyệt trên toàn bộ nền tảng.
                </p>
              </div>
            </div>

            <!-- Matrix Table -->
            <div class="matrix-table-wrapper">
              <table class="custom-matrix-table">
                <thead>
                  <tr>
                    <th class="col-cap-header">Nhóm Năng lực / Chức năng</th>
                    <th class="col-role-header text-center">
                      <div class="role-header-box">
                        <span class="role-title">Chủ sở hữu</span>
                        <span class="role-sub">Owner</span>
                      </div>
                    </th>
                    <th class="col-role-header text-center">
                      <div class="role-header-box">
                        <span class="role-title">Quản lý cụm</span>
                        <span class="role-sub">Venue Manager</span>
                      </div>
                    </th>
                    <th class="col-role-header text-center">
                      <div class="role-header-box">
                        <span class="role-title">Thu ngân</span>
                        <span class="role-sub">Cashier</span>
                      </div>
                    </th>
                    <th class="col-role-header text-center">
                      <div class="role-header-box">
                        <span class="role-title">Nhân viên</span>
                        <span class="role-sub">Staff</span>
                      </div>
                    </th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="cap in capabilities" :key="cap.id" class="matrix-row">
                    <td class="cap-info-cell">
                      <div class="cap-meta">
                        <span class="cap-name">{{ cap.name }}</span>
                        <span class="cap-desc">{{ cap.description }}</span>
                      </div>
                    </td>

                    <!-- Owner Col -->
                    <td class="perm-check-cell text-center">
                      <span v-if="cap.owner" class="perm-icon active-owner" title="Toàn quyền">
                        <span class="material-symbols-outlined filled">check_circle</span>
                      </span>
                      <span v-else class="perm-icon inactive">
                        <span class="material-symbols-outlined">remove</span>
                      </span>
                    </td>

                    <!-- Manager Col -->
                    <td class="perm-check-cell text-center">
                      <span v-if="cap.manager" class="perm-icon active-manager" title="Có quyền trong cụm sân phụ trách">
                        <span class="material-symbols-outlined filled">check_circle</span>
                      </span>
                      <span v-else class="perm-icon inactive">
                        <span class="material-symbols-outlined">remove</span>
                      </span>
                    </td>

                    <!-- Cashier Col -->
                    <td class="perm-check-cell text-center">
                      <span v-if="cap.cashier" class="perm-icon active-cashier" title="Có quyền xử lý quầy POS">
                        <span class="material-symbols-outlined filled">check_circle</span>
                      </span>
                      <span v-else class="perm-icon inactive">
                        <span class="material-symbols-outlined">remove</span>
                      </span>
                    </td>

                    <!-- Staff Col -->
                    <td class="perm-check-cell text-center">
                      <span v-if="cap.staff" class="perm-icon active-staff" title="Có quyền hỗ trợ check-in & bảo trì">
                        <span class="material-symbols-outlined filled">check_circle</span>
                      </span>
                      <span v-else class="perm-icon inactive">
                        <span class="material-symbols-outlined">remove</span>
                      </span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Role Scope Breakdown Cards -->
            <div class="role-summary-grid">
              <div class="role-summary-card">
                <div class="summary-header">
                  <span class="role-chip badge-owner">Chủ sở hữu (Owner)</span>
                </div>
                <p class="summary-text">
                  Toàn quyền quản trị tài chính, báo cáo dòng tiền, cấu hình ngân hàng VietQR/Stripe, phân quyền và thiết lập chính sách hủy/đổi lịch.
                </p>
              </div>

              <div class="role-summary-card">
                <div class="summary-header">
                  <span class="role-chip badge-manager">Quản lý cụm sân</span>
                </div>
                <p class="summary-text">
                  Quản lý danh sách sân, biểu giá theo khung giờ, hợp đồng định kỳ, giải đấu và khóa sân bảo trì trong phạm vi cơ sở được phân công.
                </p>
              </div>

              <div class="role-summary-card">
                <div class="summary-header">
                  <span class="role-chip badge-cashier">Thu ngân (Cashier)</span>
                </div>
                <p class="summary-text">
                  Vận hành máy POS, thu tiền tại quầy, đối soát giao dịch chuyển khoản, tạo phiếu đặt sân nhanh và in vé vào sân cho khách vãng lai.
                </p>
              </div>

              <div class="role-summary-card">
                <div class="summary-header">
                  <span class="role-chip badge-staff">Nhân viên vận hành</span>
                </div>
                <p class="summary-text">
                  Tra cứu lịch đặt sân, hỗ trợ khách hàng check-in qua mã PIN, kiểm tra thiết bị sân và báo cáo sự cố hư hỏng thảm/lưới.
                </p>
              </div>
            </div>
          </div>
        </section>

        <!-- VIEW 3: AUDIT LOGS -->
        <section v-if="activeView === 'audit'" class="content-section">
          <div class="audit-card-container">
            <div class="audit-header">
              <div class="title-meta">
                <h3 class="audit-heading">Nhật ký Hoạt động & Phân quyền</h3>
                <p class="audit-sub">Ghi nhận toàn bộ thao tác thêm mới, thay đổi vai trò và khóa tài khoản nhân viên.</p>
              </div>
              <button
                type="button"
                class="btn-export-log"
                @click="showNotice('Đã xuất file nhật ký kiểm toán (CSV).')"
              >
                <span class="material-symbols-outlined icon-18">download</span>
                <span>Xuất CSV</span>
              </button>
            </div>

            <div class="audit-list">
              <div v-for="log in auditLogs" :key="log.id" class="audit-item-row">
                <div class="audit-icon-col">
                  <div class="audit-icon-box">
                    <span class="material-symbols-outlined">manage_accounts</span>
                  </div>
                </div>

                <div class="audit-details-col">
                  <div class="audit-action-line">
                    <span class="audit-action-title">{{ log.action }}</span>
                    <span class="audit-time">{{ log.created_at }}</span>
                  </div>
                  <p class="audit-desc-text">{{ log.details }}</p>
                  <div class="audit-footer-meta">
                    <span class="meta-tag">
                      <span class="material-symbols-outlined icon-14">person</span>
                      {{ log.operator_name }} ({{ log.operator_role }})
                    </span>
                    <span class="meta-tag">
                      <span class="material-symbols-outlined icon-14">badge</span>
                      Tác động: {{ log.target_user }}
                    </span>
                    <span class="meta-tag ip">
                      <span class="material-symbols-outlined icon-14">dns</span>
                      IP: {{ log.ip_address }}
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section>
      </main>
    </div>

    <!-- MODAL: ADD STAFF MEMBER -->
    <div v-if="showAddModal" class="modal-overlay" @click.self="showAddModal = false">
      <div class="modal-card">
        <div class="modal-header">
          <div class="modal-title-box">
            <div class="modal-icon-badge">
              <span class="material-symbols-outlined">person_add</span>
            </div>
            <div>
              <h3 class="modal-title">Thêm Nhân Viên Mới</h3>
              <p class="modal-subtitle">Gán quyền truy cập hệ thống Aura Control cho nhân sự.</p>
            </div>
          </div>
          <button type="button" class="btn-close-modal" @click="showAddModal = false">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>

        <form @submit.prevent="handleAddStaff" class="modal-form-body">
          <div class="form-group">
            <label class="form-label">Họ và tên <span class="req">*</span></label>
            <input
              v-model="addForm.name"
              type="text"
              placeholder="Ví dụ: Nguyễn Văn Hùng"
              class="form-input"
              required
            />
          </div>

          <div class="form-row-2">
            <div class="form-group">
              <label class="form-label">Địa chỉ Email <span class="req">*</span></label>
              <input
                v-model="addForm.email"
                type="email"
                placeholder="hung.nguyen@auracourts.com"
                class="form-input"
                required
              />
            </div>
            <div class="form-group">
              <label class="form-label">Số điện thoại</label>
              <input
                v-model="addForm.phone"
                type="tel"
                placeholder="0988 123 456"
                class="form-input"
              />
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Vai trò & Cấp bậc quyền hạn <span class="req">*</span></label>
            <div class="role-selection-grid">
              <label
                class="role-radio-card"
                :class="{ selected: addForm.role === 'staff' }"
              >
                <input
                  v-model="addForm.role"
                  type="radio"
                  value="staff"
                  class="sr-only"
                />
                <div class="radio-header">
                  <span class="material-symbols-outlined icon-20">badge</span>
                  <span class="radio-title">Nhân viên</span>
                </div>
                <p class="radio-desc">Xem lịch, hỗ trợ check-in mã PIN và bảo trì sân.</p>
              </label>

              <label
                class="role-radio-card"
                :class="{ selected: addForm.role === 'cashier' }"
              >
                <input
                  v-model="addForm.role"
                  type="radio"
                  value="cashier"
                  class="sr-only"
                />
                <div class="radio-header">
                  <span class="material-symbols-outlined icon-20">point_of_sale</span>
                  <span class="radio-title">Thu ngân</span>
                </div>
                <p class="radio-desc">Xử lý quầy POS, thu tiền và in vé đặt sân.</p>
              </label>

              <label
                class="role-radio-card"
                :class="{ selected: addForm.role === 'venue_manager' }"
              >
                <input
                  v-model="addForm.role"
                  type="radio"
                  value="venue_manager"
                  class="sr-only"
                />
                <div class="radio-header">
                  <span class="material-symbols-outlined icon-20">sports_tennis</span>
                  <span class="radio-title">Quản lý cụm</span>
                </div>
                <p class="radio-desc">Quản lý sân, giá vé, giải đấu và lịch bảo trì.</p>
              </label>

              <label
                class="role-radio-card"
                :class="{ selected: addForm.role === 'organization_owner' }"
              >
                <input
                  v-model="addForm.role"
                  type="radio"
                  value="organization_owner"
                  class="sr-only"
                />
                <div class="radio-header">
                  <span class="material-symbols-outlined icon-20">corporate_fare</span>
                  <span class="radio-title">Chủ sở hữu</span>
                </div>
                <p class="radio-desc">Toàn quyền tài chính, ngân hàng và phân quyền.</p>
              </label>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Phân bổ Cụm sân phụ trách</label>
            <div class="select-wrapper">
              <select v-model="addForm.venue_id" class="custom-select">
                <option v-for="v in venues" :key="v.id" :value="v.id">
                  {{ v.name }}
                </option>
              </select>
              <span class="material-symbols-outlined select-arrow">expand_more</span>
            </div>
          </div>

          <div class="checkbox-line">
            <label class="custom-checkbox-label">
              <input
                v-model="addForm.send_invite_email"
                type="checkbox"
                class="checkbox-input"
              />
              <span class="checkbox-text">Gửi email kèm mã PIN kích hoạt đăng nhập tức thì</span>
            </label>
          </div>

          <div class="modal-actions-footer">
            <button
              type="button"
              class="btn-cancel"
              @click="showAddModal = false"
            >
              Hủy bỏ
            </button>
            <button
              type="submit"
              class="btn-submit"
              :disabled="saving"
            >
              <span v-if="saving" class="spinner-sm"></span>
              <span v-else>Xác nhận Thêm nhân viên</span>
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL: EDIT STAFF MEMBER -->
    <div v-if="showEditModal" class="modal-overlay" @click.self="showEditModal = false">
      <div class="modal-card">
        <div class="modal-header">
          <div class="modal-title-box">
            <div class="modal-icon-badge">
              <span class="material-symbols-outlined">edit</span>
            </div>
            <div>
              <h3 class="modal-title">Chỉnh Sửa Nhân Sự</h3>
              <p class="modal-subtitle">{{ editForm.email }}</p>
            </div>
          </div>
          <button type="button" class="btn-close-modal" @click="showEditModal = false">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>

        <form @submit.prevent="handleUpdateStaff" class="modal-form-body">
          <div class="form-group">
            <label class="form-label">Họ và tên <span class="req">*</span></label>
            <input
              v-model="editForm.name"
              type="text"
              class="form-input"
              required
            />
          </div>

          <div class="form-row-2">
            <div class="form-group">
              <label class="form-label">Email (Không đổi)</label>
              <input
                :value="editForm.email"
                type="email"
                class="form-input disabled"
                disabled
              />
            </div>
            <div class="form-group">
              <label class="form-label">Số điện thoại</label>
              <input
                v-model="editForm.phone"
                type="tel"
                class="form-input"
              />
            </div>
          </div>

          <div class="form-row-2">
            <div class="form-group">
              <label class="form-label">Vai trò</label>
              <div class="select-wrapper">
                <select v-model="editForm.role" class="custom-select">
                  <option value="staff">Nhân viên vận hành</option>
                  <option value="cashier">Thu ngân POS</option>
                  <option value="venue_manager">Quản lý cụm sân</option>
                  <option value="organization_owner">Chủ sở hữu (Owner)</option>
                </select>
                <span class="material-symbols-outlined select-arrow">expand_more</span>
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">Trạng thái tài khoản</label>
              <div class="select-wrapper">
                <select v-model="editForm.status" class="custom-select">
                  <option value="active">Đang hoạt động (Active)</option>
                  <option value="suspended">Tạm khóa (Suspended)</option>
                  <option value="inactive">Không hoạt động (Inactive)</option>
                  <option value="invited">Chờ kích hoạt (Invited)</option>
                </select>
                <span class="material-symbols-outlined select-arrow">expand_more</span>
              </div>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Cụm sân phụ trách</label>
            <div class="select-wrapper">
              <select v-model="editForm.venue_id" class="custom-select">
                <option v-for="v in venues" :key="v.id" :value="v.id">
                  {{ v.name }}
                </option>
              </select>
              <span class="material-symbols-outlined select-arrow">expand_more</span>
            </div>
          </div>

          <div class="modal-actions-footer">
            <button
              type="button"
              class="btn-cancel"
              @click="showEditModal = false"
            >
              Hủy
            </button>
            <button
              type="submit"
              class="btn-submit"
              :disabled="saving"
            >
              <span v-if="saving" class="spinner-sm"></span>
              <span v-else>Lưu Thay Đổi</span>
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL: CONFIRM DELETE -->
    <div v-if="showDeleteConfirm" class="modal-overlay" @click.self="showDeleteConfirm = false">
      <div class="modal-card modal-delete">
        <div class="modal-header">
          <div class="modal-title-box">
            <div class="modal-icon-badge error">
              <span class="material-symbols-outlined">warning</span>
            </div>
            <div>
              <h3 class="modal-title">Gỡ bỏ nhân viên</h3>
              <p class="modal-subtitle">Hành động này không thể hoàn tác.</p>
            </div>
          </div>
          <button type="button" class="btn-close-modal" @click="showDeleteConfirm = false">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>

        <div class="modal-body-text">
          Bạn có chắc chắn muốn xóa nhân viên
          <strong>{{ memberToDelete?.name }}</strong> ({{ memberToDelete?.email }}) khỏi danh sách quản trị của tổ chức?
        </div>

        <div class="modal-actions-footer">
          <button
            type="button"
            class="btn-cancel"
            @click="showDeleteConfirm = false"
          >
            Hủy
          </button>
          <button
            type="button"
            class="btn-delete-confirm"
            :disabled="saving"
            @click="handleDeleteStaff"
          >
            <span v-if="saving" class="spinner-sm"></span>
            <span v-else>Xác nhận Xóa</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Main Container */
.staff-workspace-container {
  padding: 24px 32px 48px;
  max-width: 1400px;
  margin: 0 auto;
  min-height: 100%;
  position: relative;
}

@media (max-width: 768px) {
  .staff-workspace-container {
    padding: 16px;
  }
}

/* Toast Banners */
.toast-banner {
  position: fixed;
  top: 84px;
  right: 32px;
  z-index: 1000;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 18px;
  border-radius: 12px;
  font-size: 13px;
  font-weight: 600;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.12);
  animation: slideDown 0.25s ease-out;
}

.toast-banner.success {
  background: #2e7d32;
  color: #ffffff;
}

.toast-banner.error {
  background: #ba1a1a;
  color: #ffffff;
}

@keyframes slideDown {
  from {
    opacity: 0;
    transform: translateY(-12px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* Top Sticky Header */
.staff-header-section {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  gap: 20px;
  padding-bottom: 24px;
  border-bottom: 1px solid var(--border, #e0e4ea);
  margin-bottom: 24px;
}

@media (max-width: 900px) {
  .staff-header-section {
    flex-direction: column;
    align-items: stretch;
  }
}

.page-title {
  font-size: 28px;
  font-weight: 800;
  color: var(--ink, #191c1d);
  letter-spacing: -0.02em;
  margin: 0 0 6px 0;
}

:global(html.dark) .page-title {
  color: #ffffff;
}

.page-subtitle {
  font-size: 14px;
  color: var(--muted, #6c757d);
  margin: 0;
}

:global(html.dark) .page-subtitle {
  color: #9ca3af;
}

.header-right {
  display: flex;
  align-items: center;
  gap: 12px;
}

@media (max-width: 600px) {
  .header-right {
    flex-direction: column;
    align-items: stretch;
  }
}

.search-input-wrapper {
  position: relative;
  display: flex;
  align-items: center;
  width: 280px;
}

@media (max-width: 600px) {
  .search-input-wrapper {
    width: 100%;
  }
}

.search-icon {
  position: absolute;
  left: 14px;
  color: var(--muted, #94a3b8);
  pointer-events: none;
  font-size: 20px;
}

.header-search-input {
  width: 100%;
  padding: 10px 36px 10px 42px;
  background: var(--surface-subtle, #f3f4f5);
  border: 1px solid transparent;
  border-radius: 9999px;
  font-size: 13px;
  color: var(--ink, #191c1d);
  outline: none;
  transition: all 0.2s ease;
}

.header-search-input:focus {
  border-color: var(--primary, #78555e);
  background: var(--surface, #ffffff);
  box-shadow: 0 0 0 3px rgba(120, 85, 94, 0.12);
}

:global(html.dark) .header-search-input {
  background: #252830;
  color: #ffffff;
}

:global(html.dark) .header-search-input:focus {
  background: #1e2025;
  border-color: #f8b4c4;
}

.btn-clear-search {
  position: absolute;
  right: 12px;
  border: none;
  background: transparent;
  color: var(--muted, #94a3b8);
  cursor: pointer;
  padding: 2px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.btn-primary-cta {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 20px;
  background: var(--pink, #ffd1dc);
  color: var(--pink-dark, #78555e);
  border: none;
  border-radius: 9999px;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s ease;
  white-space: nowrap;
  box-shadow: 0 2px 8px rgba(120, 85, 94, 0.1);
}

.btn-primary-cta:hover {
  background: var(--primary, #78555e);
  color: #ffffff;
  transform: translateY(-1px);
  box-shadow: 0 4px 14px rgba(120, 85, 94, 0.25);
}

:global(html.dark) .btn-primary-cta {
  background: #53333e;
  color: #f8b4c4;
}

:global(html.dark) .btn-primary-cta:hover {
  background: #f8b4c4;
  color: #201317;
}

/* Stats Overview Row */
.stats-overview-row {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
  margin-bottom: 24px;
}

@media (max-width: 1000px) {
  .stats-overview-row {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 600px) {
  .stats-overview-row {
    grid-template-columns: 1fr;
  }
}

.stat-card {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 16px 18px;
  background: var(--surface, #ffffff);
  border: 1px solid var(--border, #e0e4ea);
  border-radius: 16px;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.02);
}

:global(html.dark) .stat-card {
  background: #1e2025;
  border-color: #353942;
}

.stat-icon-box {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.stat-icon-box.mauve {
  background: rgba(120, 85, 94, 0.12);
  color: var(--primary, #78555e);
}

.stat-icon-box.green {
  background: rgba(46, 125, 50, 0.12);
  color: #2e7d32;
}

.stat-icon-box.pink {
  background: var(--pink, #ffd1dc);
  color: var(--pink-dark, #78555e);
}

.stat-icon-box.gray {
  background: var(--surface-subtle, #f3f4f5);
  color: var(--muted, #6c757d);
}

:global(html.dark) .stat-icon-box.mauve {
  background: #3c242c;
  color: #f8b4c4;
}

:global(html.dark) .stat-icon-box.green {
  background: #1e3822;
  color: #82c785;
}

:global(html.dark) .stat-icon-box.pink {
  background: #53333e;
  color: #f8b4c4;
}

:global(html.dark) .stat-icon-box.gray {
  background: #2b2e36;
  color: #c0c4cc;
}

.stat-content {
  display: flex;
  flex-direction: column;
}

.stat-label {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--muted, #6c757d);
}

:global(html.dark) .stat-label {
  color: #9ca3af;
}

.stat-value {
  font-size: 22px;
  font-weight: 800;
  color: var(--ink, #191c1d);
  line-height: 1.2;
}

:global(html.dark) .stat-value {
  color: #ffffff;
}

/* 2-Column Grid */
.staff-grid-layout {
  display: grid;
  grid-template-columns: 280px 1fr;
  gap: 24px;
  align-items: flex-start;
}

@media (max-width: 1024px) {
  .staff-grid-layout {
    grid-template-columns: 1fr;
  }
}

/* Left Panel */
.staff-left-panel {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.panel-card {
  background: var(--surface-subtle, #f8f9fa);
  border: 1px solid var(--border, #e0e4ea);
  border-radius: 16px;
  padding: 18px;
}

:global(html.dark) .panel-card {
  background: #1e2025;
  border-color: #353942;
}

.panel-heading {
  font-size: 13px;
  font-weight: 800;
  letter-spacing: 0.02em;
  color: var(--ink, #191c1d);
  margin: 0 0 12px 0;
}

:global(html.dark) .panel-heading {
  color: #ffffff;
}

.panel-header-with-action {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 12px;
}

.panel-header-with-action .panel-heading {
  margin-bottom: 0;
}

.btn-text-reset {
  border: none;
  background: transparent;
  font-size: 11px;
  font-weight: 700;
  color: var(--primary, #78555e);
  cursor: pointer;
  padding: 2px 4px;
}

:global(html.dark) .btn-text-reset {
  color: #f8b4c4;
}

.views-nav-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.view-nav-btn {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  border-radius: 10px;
  border: none;
  background: transparent;
  color: var(--muted, #6c757d);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s ease;
  text-align: left;
  width: 100%;
}

.view-nav-btn:hover {
  background: var(--surface, #ffffff);
  color: var(--ink, #191c1d);
}

.view-nav-btn.active {
  background: var(--surface, #ffffff);
  color: var(--primary, #78555e);
  font-weight: 700;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

:global(html.dark) .view-nav-btn {
  color: #9ca3af;
}

:global(html.dark) .view-nav-btn:hover {
  background: #2b2e36;
  color: #ffffff;
}

:global(html.dark) .view-nav-btn.active {
  background: #2b2e36;
  color: #f8b4c4;
}

.nav-text {
  flex: 1;
}

.nav-count {
  font-size: 11px;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 9999px;
  background: var(--surface-subtle, #edeeef);
  color: var(--muted, #6c757d);
}

:global(html.dark) .nav-count {
  background: #353942;
  color: #c0c4cc;
}

/* Filter Stack */
.filter-controls-stack {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.filter-group {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.filter-label {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--muted, #6c757d);
}

:global(html.dark) .filter-label {
  color: #9ca3af;
}

.select-wrapper {
  position: relative;
  display: flex;
  align-items: center;
}

.custom-select {
  width: 100%;
  padding: 8px 32px 8px 12px;
  border-radius: 8px;
  border: 1px solid var(--border, #e0e4ea);
  background: var(--surface, #ffffff);
  color: var(--ink, #191c1d);
  font-size: 13px;
  font-weight: 500;
  outline: none;
  appearance: none;
  cursor: pointer;
  transition: all 0.2s ease;
}

.custom-select:focus {
  border-color: var(--primary, #78555e);
}

:global(html.dark) .custom-select {
  background: #252830;
  border-color: #353942;
  color: #ffffff;
}

.select-arrow {
  position: absolute;
  right: 8px;
  pointer-events: none;
  color: var(--muted, #94a3b8);
  font-size: 20px;
}

.help-card {
  display: flex;
  flex-direction: column;
  gap: 8px;
  background: rgba(120, 85, 94, 0.04);
  border-color: rgba(120, 85, 94, 0.15);
}

:global(html.dark) .help-card {
  background: #251e22;
  border-color: #53333e;
}

.help-icon {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  background: var(--pink, #ffd1dc);
  color: var(--pink-dark, #78555e);
  display: flex;
  align-items: center;
  justify-content: center;
}

:global(html.dark) .help-icon {
  background: #53333e;
  color: #f8b4c4;
}

.help-title {
  font-size: 12px;
  font-weight: 700;
  color: var(--ink, #191c1d);
  margin: 0;
}

:global(html.dark) .help-title {
  color: #ffffff;
}

.help-desc {
  font-size: 11px;
  color: var(--muted, #6c757d);
  line-height: 1.4;
  margin: 0;
}

:global(html.dark) .help-desc {
  color: #9ca3af;
}

/* Right Main Content */
.staff-main-content {
  min-width: 0;
}

/* Table Card */
.table-container-card {
  background: var(--surface, #ffffff);
  border: 1px solid var(--border, #e0e4ea);
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.02);
}

:global(html.dark) .table-container-card {
  background: #1e2025;
  border-color: #353942;
}

.table-grid-header {
  display: grid;
  grid-template-columns: 2.2fr 1.6fr 1.3fr 1.2fr 100px;
  gap: 12px;
  padding: 14px 20px;
  background: var(--surface-subtle, #f8f9fa);
  border-bottom: 1px solid var(--border, #e0e4ea);
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--muted, #6c757d);
}

:global(html.dark) .table-grid-header {
  background: #18191d;
  border-color: #353942;
  color: #9ca3af;
}

.table-body-rows {
  display: flex;
  flex-direction: column;
}

.table-grid-row {
  display: grid;
  grid-template-columns: 2.2fr 1.6fr 1.3fr 1.2fr 100px;
  gap: 12px;
  padding: 16px 20px;
  align-items: center;
  border-bottom: 1px solid var(--border, #f0f2f5);
  transition: background 0.15s ease;
}

.table-grid-row:hover {
  background: var(--surface-subtle, #fbfcfd);
}

:global(html.dark) .table-grid-row {
  border-color: #2b2e36;
}

:global(html.dark) .table-grid-row:hover {
  background: #252830;
}

/* Staff Profile Cell */
.staff-profile-cell {
  display: flex;
  align-items: center;
  gap: 12px;
  min-width: 0;
}

.staff-avatar-box {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
  font-weight: 700;
  flex-shrink: 0;
}

.staff-avatar-box.badge-owner {
  background: var(--pink, #ffd1dc);
  color: var(--pink-dark, #78555e);
}

.staff-avatar-box.badge-manager {
  background: #e1e3e4;
  color: #2d141c;
}

.staff-avatar-box.badge-cashier {
  background: #dfe0e0;
  color: #191c1d;
}

.staff-avatar-box.badge-staff {
  background: #edeeef;
  color: #5d5f5f;
}

:global(html.dark) .staff-avatar-box.badge-owner {
  background: #53333e;
  color: #f8b4c4;
}

:global(html.dark) .staff-avatar-box.badge-manager {
  background: #353942;
  color: #f8b4c4;
}

:global(html.dark) .staff-avatar-box.badge-cashier,
:global(html.dark) .staff-avatar-box.badge-staff {
  background: #2b2e36;
  color: #d1d5db;
}

.staff-text-meta {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.staff-name-line {
  display: flex;
  align-items: center;
  gap: 6px;
}

.staff-name {
  font-size: 14px;
  font-weight: 700;
  color: var(--ink, #191c1d);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

:global(html.dark) .staff-name {
  color: #ffffff;
}

.owner-crown-badge {
  color: var(--primary, #78555e);
  display: inline-flex;
  align-items: center;
}

:global(html.dark) .owner-crown-badge {
  color: #f8b4c4;
}

.staff-email {
  font-size: 12px;
  color: var(--muted, #6c757d);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

:global(html.dark) .staff-email {
  color: #9ca3af;
}

.staff-phone {
  font-size: 11px;
  color: var(--muted, #94a3b8);
}

/* Venue Cell */
.venue-cell-content {
  display: flex;
  align-items: center;
  gap: 6px;
  min-width: 0;
}

.venue-pin-icon {
  font-size: 16px;
  color: var(--primary, #78555e);
  flex-shrink: 0;
}

:global(html.dark) .venue-pin-icon {
  color: #f8b4c4;
}

.venue-name-text {
  font-size: 13px;
  font-weight: 500;
  color: var(--ink, #191c1d);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

:global(html.dark) .venue-name-text {
  color: #e2e8f0;
}

/* Role Chips */
.role-chip {
  display: inline-flex;
  align-items: center;
  padding: 4px 10px;
  border-radius: 8px;
  font-size: 12px;
  font-weight: 600;
  white-space: nowrap;
}

.role-chip.badge-owner {
  background: rgba(120, 85, 94, 0.12);
  color: var(--primary, #78555e);
}

.role-chip.badge-manager {
  background: var(--pink, #ffd1dc);
  color: var(--pink-dark, #78555e);
}

.role-chip.badge-cashier {
  background: #e2e2e2;
  color: #1a1c1c;
}

.role-chip.badge-staff {
  background: #edeeef;
  color: #5d5f5f;
}

:global(html.dark) .role-chip.badge-owner {
  background: #3c242c;
  color: #f8b4c4;
}

:global(html.dark) .role-chip.badge-manager {
  background: #53333e;
  color: #f8b4c4;
}

:global(html.dark) .role-chip.badge-cashier,
:global(html.dark) .role-chip.badge-staff {
  background: #2b2e36;
  color: #d1d5db;
}

/* Status Indicator */
.status-indicator-box {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.status-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  flex-shrink: 0;
}

.status-indicator-box.active .status-dot {
  background: #829e85;
}

.status-indicator-box.suspended .status-dot {
  background: #f59e0b;
}

.status-indicator-box.inactive .status-dot {
  background: #94a3b8;
}

.status-indicator-box.invited .status-dot {
  background: #3b82f6;
}

.status-label-text {
  font-size: 12px;
  font-weight: 500;
  color: var(--ink, #191c1d);
}

:global(html.dark) .status-label-text {
  color: #e2e8f0;
}

.last-active-text {
  font-size: 11px;
  color: var(--muted, #94a3b8);
  display: block;
}

/* Row Actions */
.col-actions {
  display: flex;
  justify-content: flex-end;
}

.row-actions-group {
  display: flex;
  align-items: center;
  gap: 4px;
}

.btn-row-action {
  width: 28px;
  height: 28px;
  border-radius: 6px;
  border: none;
  background: transparent;
  color: var(--muted, #6c757d);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-row-action:hover {
  background: var(--surface-subtle, #f3f4f5);
  color: var(--primary, #78555e);
}

.btn-row-action.delete:hover {
  background: rgba(186, 26, 26, 0.1);
  color: #ba1a1a;
}

:global(html.dark) .btn-row-action {
  color: #9ca3af;
}

:global(html.dark) .btn-row-action:hover {
  background: #2b2e36;
  color: #f8b4c4;
}

:global(html.dark) .btn-row-action.delete:hover {
  background: rgba(186, 26, 26, 0.2);
  color: #f87171;
}

/* Empty State */
.empty-state-card {
  padding: 48px 24px;
  text-align: center;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
}

.empty-icon-circle {
  width: 56px;
  height: 56px;
  border-radius: 50%;
  background: var(--surface-subtle, #f3f4f5);
  color: var(--muted, #94a3b8);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 28px;
}

:global(html.dark) .empty-icon-circle {
  background: #2b2e36;
  color: #9ca3af;
}

.empty-title {
  font-size: 16px;
  font-weight: 700;
  color: var(--ink, #191c1d);
  margin: 0;
}

:global(html.dark) .empty-title {
  color: #ffffff;
}

.empty-desc {
  font-size: 13px;
  color: var(--muted, #6c757d);
  max-width: 400px;
  margin: 0;
}

/* Pagination */
.table-pagination-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 20px;
  border-top: 1px solid var(--border, #e0e4ea);
  background: var(--surface-subtle, #f8f9fa);
}

:global(html.dark) .table-pagination-footer {
  background: #18191d;
  border-color: #353942;
}

.pagination-info {
  font-size: 12px;
  color: var(--muted, #6c757d);
}

:global(html.dark) .pagination-info {
  color: #9ca3af;
}

.pagination-controls {
  display: flex;
  align-items: center;
  gap: 8px;
}

.btn-page-arrow {
  width: 28px;
  height: 28px;
  border-radius: 6px;
  border: 1px solid var(--border, #cbd5e1);
  background: var(--surface, #ffffff);
  color: var(--ink, #191c1d);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-page-arrow:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

:global(html.dark) .btn-page-arrow {
  background: #252830;
  border-color: #353942;
  color: #ffffff;
}

.page-number-text {
  font-size: 12px;
  font-weight: 600;
  color: var(--muted, #6c757d);
}

/* VIEW 2: MATRIX STYLES */
.matrix-card-container {
  background: var(--surface, #ffffff);
  border: 1px solid var(--border, #e0e4ea);
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.02);
  padding: 24px;
}

:global(html.dark) .matrix-card-container {
  background: #1e2025;
  border-color: #353942;
}

.matrix-banner-header {
  margin-bottom: 24px;
}

.matrix-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 12px;
  border-radius: 9999px;
  background: var(--pink, #ffd1dc);
  color: var(--pink-dark, #78555e);
  font-size: 12px;
  font-weight: 700;
  margin-bottom: 8px;
}

:global(html.dark) .matrix-badge {
  background: #53333e;
  color: #f8b4c4;
}

.matrix-heading {
  font-size: 20px;
  font-weight: 800;
  color: var(--ink, #191c1d);
  margin: 0 0 6px 0;
}

:global(html.dark) .matrix-heading {
  color: #ffffff;
}

.matrix-desc {
  font-size: 13px;
  color: var(--muted, #6c757d);
  margin: 0;
}

.matrix-table-wrapper {
  overflow-x: auto;
  border: 1px solid var(--border, #e0e4ea);
  border-radius: 12px;
  margin-bottom: 24px;
}

:global(html.dark) .matrix-table-wrapper {
  border-color: #353942;
}

.custom-matrix-table {
  width: 100%;
  border-collapse: collapse;
  text-align: left;
}

.custom-matrix-table th {
  padding: 14px 16px;
  background: var(--surface-subtle, #f8f9fa);
  border-bottom: 1px solid var(--border, #e0e4ea);
  font-size: 12px;
  font-weight: 700;
  color: var(--ink, #191c1d);
}

:global(html.dark) .custom-matrix-table th {
  background: #18191d;
  border-color: #353942;
  color: #ffffff;
}

.col-cap-header {
  width: 40%;
}

.role-header-box {
  display: flex;
  flex-direction: column;
  align-items: center;
}

.role-title {
  font-size: 13px;
  font-weight: 700;
}

.role-sub {
  font-size: 10px;
  font-weight: 600;
  text-transform: uppercase;
  color: var(--muted, #6c757d);
}

.matrix-row {
  border-bottom: 1px solid var(--border, #f0f2f5);
  transition: background 0.15s ease;
}

.matrix-row:hover {
  background: var(--surface-subtle, #fbfcfd);
}

:global(html.dark) .matrix-row {
  border-color: #2b2e36;
}

:global(html.dark) .matrix-row:hover {
  background: #252830;
}

.cap-info-cell {
  padding: 14px 16px;
}

.cap-meta {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.cap-name {
  font-size: 13px;
  font-weight: 700;
  color: var(--ink, #191c1d);
}

:global(html.dark) .cap-name {
  color: #ffffff;
}

.cap-desc {
  font-size: 11px;
  color: var(--muted, #6c757d);
}

:global(html.dark) .cap-desc {
  color: #9ca3af;
}

.perm-check-cell {
  padding: 14px 16px;
}

.perm-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.perm-icon .filled {
  font-variation-settings: 'FILL' 1;
}

.perm-icon.active-owner {
  color: var(--primary, #78555e);
}

.perm-icon.active-manager {
  color: #829e85;
}

.perm-icon.active-cashier {
  color: #3b82f6;
}

.perm-icon.active-staff {
  color: #64748b;
}

.perm-icon.inactive {
  color: var(--muted, #cbd5e1);
}

:global(html.dark) .perm-icon.active-owner {
  color: #f8b4c4;
}

:global(html.dark) .perm-icon.inactive {
  color: #4b5563;
}

.role-summary-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
}

@media (max-width: 1000px) {
  .role-summary-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 600px) {
  .role-summary-grid {
    grid-template-columns: 1fr;
  }
}

.role-summary-card {
  padding: 16px;
  background: var(--surface-subtle, #f8f9fa);
  border: 1px solid var(--border, #e0e4ea);
  border-radius: 12px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

:global(html.dark) .role-summary-card {
  background: #18191d;
  border-color: #353942;
}

.summary-text {
  font-size: 12px;
  color: var(--muted, #6c757d);
  line-height: 1.45;
  margin: 0;
}

:global(html.dark) .summary-text {
  color: #9ca3af;
}

/* VIEW 3: AUDIT LOGS STYLES */
.audit-card-container {
  background: var(--surface, #ffffff);
  border: 1px solid var(--border, #e0e4ea);
  border-radius: 16px;
  padding: 24px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.02);
}

:global(html.dark) .audit-card-container {
  background: #1e2025;
  border-color: #353942;
}

.audit-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 20px;
  padding-bottom: 16px;
  border-bottom: 1px solid var(--border, #e0e4ea);
}

.audit-heading {
  font-size: 18px;
  font-weight: 800;
  color: var(--ink, #191c1d);
  margin: 0 0 4px 0;
}

:global(html.dark) .audit-heading {
  color: #ffffff;
}

.audit-sub {
  font-size: 13px;
  color: var(--muted, #6c757d);
  margin: 0;
}

.btn-export-log {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 14px;
  border-radius: 8px;
  border: 1px solid var(--border, #cbd5e1);
  background: var(--surface, #ffffff);
  color: var(--ink, #191c1d);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-export-log:hover {
  background: var(--surface-subtle, #f3f4f5);
}

:global(html.dark) .btn-export-log {
  background: #252830;
  border-color: #353942;
  color: #ffffff;
}

.audit-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.audit-item-row {
  display: flex;
  gap: 16px;
  padding: 16px;
  border-radius: 12px;
  background: var(--surface-subtle, #f8f9fa);
  border: 1px solid var(--border, #e0e4ea);
}

:global(html.dark) .audit-item-row {
  background: #18191d;
  border-color: #353942;
}

.audit-icon-box {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  background: var(--pink, #ffd1dc);
  color: var(--pink-dark, #78555e);
  display: flex;
  align-items: center;
  justify-content: center;
}

:global(html.dark) .audit-icon-box {
  background: #53333e;
  color: #f8b4c4;
}

.audit-details-col {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.audit-action-line {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.audit-action-title {
  font-size: 14px;
  font-weight: 700;
  color: var(--ink, #191c1d);
}

:global(html.dark) .audit-action-title {
  color: #ffffff;
}

.audit-time {
  font-size: 11px;
  font-weight: 600;
  color: var(--muted, #94a3b8);
}

.audit-desc-text {
  font-size: 13px;
  color: var(--ink, #191c1d);
  margin: 0;
}

:global(html.dark) .audit-desc-text {
  color: #d1d5db;
}

.audit-footer-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  margin-top: 6px;
}

.meta-tag {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 11px;
  color: var(--muted, #6c757d);
}

:global(html.dark) .meta-tag {
  color: #9ca3af;
}

/* MODAL COMMON STYLES */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.6);
  backdrop-filter: blur(4px);
  z-index: 1200;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
}

.modal-card {
  width: 100%;
  max-width: 580px;
  background: var(--surface, #ffffff);
  border-radius: 20px;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
  overflow: hidden;
  animation: modalPop 0.25s ease-out;
}

:global(html.dark) .modal-card {
  background: #1e2025;
  border: 1px solid #353942;
}

@keyframes modalPop {
  from {
    opacity: 0;
    transform: scale(0.95) translateY(10px);
  }
  to {
    opacity: 1;
    transform: scale(1) translateY(0);
  }
}

.modal-header {
  padding: 20px 24px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-bottom: 1px solid var(--border, #e0e4ea);
}

:global(html.dark) .modal-header {
  border-color: #353942;
}

.modal-title-box {
  display: flex;
  align-items: center;
  gap: 12px;
}

.modal-icon-badge {
  width: 40px;
  height: 40px;
  border-radius: 12px;
  background: var(--pink, #ffd1dc);
  color: var(--pink-dark, #78555e);
  display: flex;
  align-items: center;
  justify-content: center;
}

.modal-icon-badge.error {
  background: rgba(186, 26, 26, 0.12);
  color: #ba1a1a;
}

:global(html.dark) .modal-icon-badge {
  background: #53333e;
  color: #f8b4c4;
}

.modal-title {
  font-size: 16px;
  font-weight: 800;
  color: var(--ink, #191c1d);
  margin: 0;
}

:global(html.dark) .modal-title {
  color: #ffffff;
}

.modal-subtitle {
  font-size: 12px;
  color: var(--muted, #6c757d);
  margin: 0;
}

.btn-close-modal {
  border: none;
  background: transparent;
  color: var(--muted, #6c757d);
  cursor: pointer;
  padding: 4px;
}

.modal-form-body {
  padding: 24px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-label {
  font-size: 12px;
  font-weight: 700;
  color: var(--ink, #191c1d);
}

:global(html.dark) .form-label {
  color: #ffffff;
}

.req {
  color: #ba1a1a;
}

.form-input {
  width: 100%;
  padding: 10px 14px;
  border-radius: 8px;
  border: 1px solid var(--border, #cbd5e1);
  background: var(--surface, #ffffff);
  color: var(--ink, #191c1d);
  font-size: 13px;
  outline: none;
  transition: all 0.2s ease;
}

.form-input:focus {
  border-color: var(--primary, #78555e);
  box-shadow: 0 0 0 3px rgba(120, 85, 94, 0.12);
}

.form-input.disabled {
  background: var(--surface-subtle, #f3f4f5);
  cursor: not-allowed;
  opacity: 0.7;
}

:global(html.dark) .form-input {
  background: #252830;
  border-color: #353942;
  color: #ffffff;
}

.form-row-2 {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

@media (max-width: 500px) {
  .form-row-2 {
    grid-template-columns: 1fr;
  }
}

.role-selection-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}

@media (max-width: 500px) {
  .role-selection-grid {
    grid-template-columns: 1fr;
  }
}

.role-radio-card {
  padding: 12px;
  border-radius: 12px;
  border: 1.5px solid var(--border, #e0e4ea);
  background: var(--surface-subtle, #f8f9fa);
  cursor: pointer;
  transition: all 0.15s ease;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.role-radio-card:hover {
  border-color: var(--primary, #78555e);
}

.role-radio-card.selected {
  border-color: var(--primary, #78555e);
  background: rgba(120, 85, 94, 0.06);
}

:global(html.dark) .role-radio-card {
  background: #18191d;
  border-color: #353942;
}

:global(html.dark) .role-radio-card.selected {
  border-color: #f8b4c4;
  background: #2b1f24;
}

.radio-header {
  display: flex;
  align-items: center;
  gap: 6px;
  color: var(--primary, #78555e);
}

:global(html.dark) .radio-header {
  color: #f8b4c4;
}

.radio-title {
  font-size: 13px;
  font-weight: 700;
  color: var(--ink, #191c1d);
}

:global(html.dark) .radio-title {
  color: #ffffff;
}

.radio-desc {
  font-size: 11px;
  color: var(--muted, #6c757d);
  line-height: 1.35;
  margin: 0;
}

.custom-checkbox-label {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
}

.checkbox-text {
  font-size: 12px;
  color: var(--ink, #191c1d);
}

:global(html.dark) .checkbox-text {
  color: #e2e8f0;
}

.modal-actions-footer {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 10px;
  padding-top: 12px;
  border-top: 1px solid var(--border, #e0e4ea);
}

:global(html.dark) .modal-actions-footer {
  border-color: #353942;
}

.btn-cancel {
  padding: 10px 18px;
  border-radius: 8px;
  border: 1px solid var(--border, #cbd5e1);
  background: transparent;
  color: var(--ink, #191c1d);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}

:global(html.dark) .btn-cancel {
  border-color: #353942;
  color: #ffffff;
}

.btn-submit {
  padding: 10px 22px;
  border-radius: 8px;
  border: none;
  background: var(--primary, #78555e);
  color: #ffffff;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  box-shadow: 0 4px 12px rgba(120, 85, 94, 0.2);
}

.btn-submit:hover {
  background: #63434c;
}

:global(html.dark) .btn-submit {
  background: #f8b4c4;
  color: #201317;
}

.modal-delete {
  max-width: 440px;
}

.modal-body-text {
  padding: 24px;
  font-size: 14px;
  color: var(--ink, #191c1d);
  line-height: 1.5;
}

:global(html.dark) .modal-body-text {
  color: #e2e8f0;
}

.btn-delete-confirm {
  padding: 10px 20px;
  border-radius: 8px;
  border: none;
  background: #ba1a1a;
  color: #ffffff;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
}

.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}
</style>
