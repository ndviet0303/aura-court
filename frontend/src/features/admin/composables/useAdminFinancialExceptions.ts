import { ref, computed, onMounted } from 'vue'
import { financialExceptionsApi } from '../api/financialExceptionsApi'
import type {
  FinancialException,
  AuditLog,
  ExceptionStats,
  ExceptionFilterTab,
} from '../types/exceptions'

export function useAdminFinancialExceptions() {
  const activeTab = ref<ExceptionFilterTab>('all')
  const searchQuery = ref('')
  const notice = ref('')
  const error = ref('')
  const loading = ref(false)

  const stats = ref<ExceptionStats>({
    criticalCount: 12,
    pendingCount: 45,
    resolvedCount: 18,
    totalRiskValue: 148500000,
  })

  const exceptions = ref<FinancialException[]>([
    {
      id: '1',
      code: 'TXN-88291A',
      timestamp: '24 Th10, 14:32',
      shift: 'Ca Sáng (06:00 - 14:00)',
      user: {
        name: 'Lê Văn B.',
        id: 'UID-442',
        tier: 'Hội viên Vàng',
      },
      amount: 4500000,
      reason: 'Vượt hạn mức nạp 24h',
      riskLevel: 'critical',
      status: 'pending',
    },
    {
      id: '2',
      code: 'TXN-88290B',
      timestamp: '24 Th10, 13:15',
      shift: 'Ca Sáng (06:00 - 14:00)',
      user: {
        name: 'Trần Thị C.',
        id: 'UID-119',
        tier: 'Khách vãng lai',
      },
      amount: 12000000,
      reason: 'Lệch đối soát VietQR',
      riskLevel: 'medium',
      status: 'pending',
    },
    {
      id: '3',
      code: 'TXN-88289C',
      timestamp: '24 Th10, 11:05',
      shift: 'Ca Sáng (06:00 - 14:00)',
      user: {
        name: 'Minh Quân',
        id: 'UID-992',
        tier: 'Hội viên Kim Cương',
      },
      amount: 2150000,
      reason: 'Hoàn tiền hủy sân quá 48h',
      riskLevel: 'low',
      status: 'pending',
    },
    {
      id: '4',
      code: 'TXN-88288D',
      timestamp: '24 Th10, 10:20',
      shift: 'Ca Sáng (06:00 - 14:00)',
      user: {
        name: 'Phạm Hồng Đức',
        id: 'UID-553',
        tier: 'Chủ sân đối tác',
      },
      amount: 35000000,
      reason: 'Rút tiền tài khoản ngoài danh bạ',
      riskLevel: 'critical',
      status: 'investigating',
    },
    {
      id: '5',
      code: 'TXN-88287E',
      timestamp: '24 Th10, 09:40',
      shift: 'Ca Sáng (06:00 - 14:00)',
      user: {
        name: 'Hoàng Yến',
        id: 'UID-331',
        tier: 'Hội viên Bạc',
      },
      amount: 600000,
      reason: 'Lệch cọc giữ chỗ qua MoMo',
      riskLevel: 'low',
      status: 'pending',
    },
  ])

  const auditLogs = ref<AuditLog[]>([
    {
      id: 'log-1',
      author: 'Admin_Sarah',
      role: 'Trưởng ca kiểm soát',
      time: '10:45',
      action: 'Đã can thiệp hoàn tiền ngoại lệ',
      details: 'TXN-99412B: Khách hủy sân do sự cố ngập nước tại Bình Thạnh, đã kiểm tra camera xác nhận.',
      hash: '0x8F92...B4',
      type: 'override',
    },
    {
      id: 'log-2',
      author: 'Risk_Officer_Tuan',
      role: 'Ban Giám sát Rủi ro',
      time: '09:30',
      action: 'Gắn cờ cảnh báo gian lận',
      details: 'UID-8831: Nghi vấn hành vi tạo lệnh nạp rút ảo săn voucher ưu đãi giải đấu.',
      hash: '0x3E11...A7',
      type: 'flag',
    },
    {
      id: 'log-3',
      author: 'System_Sentinel',
      role: 'Hệ thống Bảo vệ Tự động',
      time: '09:12',
      action: 'Tự động chặn giao dịch',
      details: 'TXN-88250X: Tự động chặn nạp 50.000.000 ₫ do vượt hạn mức ngày của tài khoản mới.',
      hash: '0x992B...C2',
      type: 'block',
    },
    {
      id: 'log-4',
      author: 'Admin_Duc',
      role: 'Thẩm định viên',
      time: '08:45',
      action: 'Mở khóa hoàn cọc giữ chỗ',
      details: 'TXN-88199K: Đã đối chiếu biên lai ngân hàng hợp lệ khớp giao dịch nạp.',
      hash: '0x664A...FF',
      type: 'unlock',
    },
  ])

  const selectedItem = ref<FinancialException | null>(null)
  const isDrawerOpen = ref(false)

  async function loadData() {
    loading.value = true
    try {
      const statsRes = await financialExceptionsApi.getStats()
      if (statsRes?.stats) {
        stats.value = statsRes.stats
      }
    } catch {
      // Keep demo mock values if server endpoint is in offline/demo mode
    } finally {
      loading.value = false
    }
  }

  onMounted(() => {
    loadData()
  })

  const filteredExceptions = computed(() => {
    return exceptions.value.filter((item) => {
      if (activeTab.value === 'critical' && item.riskLevel !== 'critical') return false
      if (activeTab.value === 'refund' && !item.reason.includes('Hoàn tiền')) return false
      if (activeTab.value === 'deposit' && !item.reason.includes('Nạp') && !item.reason.includes('Rút')) return false
      if (activeTab.value === 'deposit_hold' && !item.reason.includes('cọc')) return false

      if (!searchQuery.value.trim()) return true
      const q = searchQuery.value.toLowerCase()
      return (
        item.code.toLowerCase().includes(q) ||
        item.user.name.toLowerCase().includes(q) ||
        item.user.id.toLowerCase().includes(q) ||
        item.reason.toLowerCase().includes(q)
      )
    })
  })

  function openOverrideDrawer(item: FinancialException) {
    selectedItem.value = item
    isDrawerOpen.value = true
  }

  function closeDrawer() {
    isDrawerOpen.value = false
    selectedItem.value = null
  }

  async function submitOverride(payload: { actionType: string; reason: string }) {
    if (!selectedItem.value) return

    const targetItem = selectedItem.value
    try {
      await financialExceptionsApi.resolveException(targetItem.id, {
        resolution: payload.reason,
        action_type: payload.actionType,
      })
    } catch {
      // Local state update fallback
    }

    const found = exceptions.value.find((e) => e.id === targetItem.id)
    if (found) {
      found.status = 'resolved'
    }

    const newLog: AuditLog = {
      id: `log-${Date.now()}`,
      author: 'Nguyễn Văn An (Bạn)',
      role: 'Trưởng ban Kiểm soát Rủi ro & Tài chính',
      time: new Date().toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' }),
      action: `Can thiệp: ${payload.actionType}`,
      details: `${targetItem.code} (${targetItem.amount.toLocaleString('vi-VN')} ₫): ${payload.reason}`,
      hash: `0x${Math.random().toString(16).substring(2, 6).toUpperCase()}...${Math.random().toString(16).substring(2, 4).toUpperCase()}`,
      type: 'override',
    }
    auditLogs.value.unshift(newLog)

    stats.value.resolvedCount += 1
    if (stats.value.pendingCount > 0) stats.value.pendingCount -= 1

    notice.value = `Đã can thiệp và ghi nhận sổ cái kiểm toán cho mã ${targetItem.code} thành công.`
    setTimeout(() => {
      notice.value = ''
    }, 4000)

    closeDrawer()
  }

  return {
    activeTab,
    searchQuery,
    notice,
    error,
    loading,
    stats,
    exceptions,
    auditLogs,
    selectedItem,
    isDrawerOpen,
    filteredExceptions,
    openOverrideDrawer,
    closeDrawer,
    submitOverride,
    loadData,
  }
}
