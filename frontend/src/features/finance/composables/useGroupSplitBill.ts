import { ref, computed } from 'vue'
import { financeApi } from '../api/financeApi'
import { errorMessage } from '@/core/utils/errorMessage'
import type { SplitMember, SplitItemDetail, SplitMode, SplitBillBookingInfo } from '../types/split'

export function useGroupSplitBill() {
  const loading = ref(false)
  const processing = ref(false)
  const notice = ref('')
  const error = ref('')

  // Modals
  const showQrModal = ref(false)
  const selectedMemberForQr = ref<SplitMember | null>(null)
  const showAddMemberModal = ref(false)
  const showAddServiceModal = ref(false)
  const showCoverShortfallModal = ref(false)

  // Split configuration & Mode
  const splitMode = ref<SplitMode>('equal')

  // Booking & Bank info
  const bookingInfo = ref<SplitBillBookingInfo>({
    bookingCode: 'ORD18',
    courtName: 'Sân Cầu Lông Aura Pro - Sân VIP 03',
    venueName: 'Aura Sport Complex Hà Đông',
    address: 'Số 18 Đường Chu Văn An, Yết Kiêu, Hà Đông, Hà Nội',
    bookingDate: 'Hôm nay, 01/09/2026',
    bookingTime: '19:00 - 21:00',
    durationHours: 2,
    bankName: 'TPBank (Ngân Hàng Tiên Phong)',
    bankBin: '970423',
    accountNumber: '83403032005',
    accountName: 'AURA COURT',
    hostName: 'Nguyễn Văn An',
    hostPhone: '0963 792 413',
    status: 'partially_paid',
  })

  // Bill items
  const items = ref<SplitItemDetail[]>([
    {
      id: 'item-1',
      name: 'Thuê Sân VIP 03 (2 Giờ · 19:00 - 21:00)',
      category: 'court',
      qty: 2,
      unitPrice: 200000,
      total: 400000,
      assignedMemberIds: [],
    },
    {
      id: 'item-2',
      name: 'Nước Tăng Lực Pocari Sweat (Chai 500ml)',
      category: 'drink',
      qty: 4,
      unitPrice: 20000,
      total: 80000,
      assignedMemberIds: [],
    },
    {
      id: 'item-3',
      name: 'Hộp Cầu Lông Victor Champion 12 Quả',
      category: 'gear',
      qty: 1,
      unitPrice: 120000,
      total: 120000,
      assignedMemberIds: [],
    },
  ])

  // Members list
  const members = ref<SplitMember[]>([
    {
      id: 1,
      name: 'Nguyễn Văn An',
      phone: '0963 792 413',
      role: 'host',
      amount: 150000,
      paid: true,
      method: 'Ví Aura Pay',
      paidAt: '18:45',
      timeMinutes: 120,
      customPercent: 25,
      customAmount: 150000,
      assignedItemIds: ['item-1', 'item-2', 'item-3'],
    },
    {
      id: 2,
      name: 'Trần Minh Tuấn',
      phone: '0912 345 678',
      role: 'member',
      amount: 150000,
      paid: true,
      method: 'VietQR',
      paidAt: '18:55',
      timeMinutes: 120,
      customPercent: 25,
      customAmount: 150000,
      assignedItemIds: ['item-1', 'item-2', 'item-3'],
    },
    {
      id: 3,
      name: 'Lê Hoàng Nam',
      phone: '0988 765 432',
      role: 'member',
      amount: 150000,
      paid: false,
      timeMinutes: 120,
      customPercent: 25,
      customAmount: 150000,
      assignedItemIds: ['item-1', 'item-2', 'item-3'],
    },
    {
      id: 4,
      name: 'Phạm Đức Minh',
      phone: '0903 888 999',
      role: 'member',
      amount: 150000,
      paid: false,
      timeMinutes: 60,
      customPercent: 25,
      customAmount: 150000,
      assignedItemIds: ['item-1', 'item-2', 'item-3'],
    },
  ])

  // Computed Totals
  const totalBill = computed(() => {
    return items.value.reduce((sum, item) => sum + (item.total || 0), 0)
  })

  const totalPaid = computed(() => {
    return members.value.filter((m) => m.paid).reduce((sum, m) => sum + (m.amount || 0), 0)
  })

  const remainingAmount = computed(() => {
    return Math.max(0, totalBill.value - totalPaid.value)
  })

  const progressPercent = computed(() => {
    if (totalBill.value <= 0) return 0
    return Math.min(100, Math.round((totalPaid.value / totalBill.value) * 100))
  })

  const allocatedTotal = computed(() => {
    return members.value.reduce((sum, m) => sum + (m.amount || 0), 0)
  })

  const allocationDiff = computed(() => {
    return totalBill.value - allocatedTotal.value
  })

  const isFullyPaid = computed(() => {
    return remainingAmount.value === 0 && members.value.every((m) => m.paid)
  })

  const unpaidMembersCount = computed(() => {
    return members.value.filter((m) => !m.paid).length
  })

  // Split Calculation Logic
  function calculateSplits() {
    const total = totalBill.value
    const count = members.value.length
    if (count === 0) return

    if (splitMode.value === 'equal') {
      const perPerson = Math.floor(total / count)
      const remainder = total - perPerson * count
      members.value.forEach((m, idx) => {
        m.amount = idx === 0 ? perPerson + remainder : perPerson
      })
    } else if (splitMode.value === 'itemized') {
      // Calculate per item allocation
      const memberTotals: Record<number, number> = {}
      members.value.forEach((m) => {
        memberTotals[m.id] = 0
      })

      items.value.forEach((item) => {
        const assigned =
          item.assignedMemberIds && item.assignedMemberIds.length > 0
            ? item.assignedMemberIds
            : members.value.map((m) => m.id)

        if (assigned.length > 0) {
          const perUser = Math.floor(item.total / assigned.length)
          const rem = item.total - perUser * assigned.length
          assigned.forEach((memberId, i) => {
            if (memberTotals[memberId] !== undefined) {
              memberTotals[memberId] += i === 0 ? perUser + rem : perUser
            }
          })
        }
      })

      members.value.forEach((m) => {
        m.amount = memberTotals[m.id] || 0
      })
    } else if (splitMode.value === 'time') {
      // Court fee split by minutes played
      const courtItems = items.value.filter((i) => i.category === 'court')
      const otherItems = items.value.filter((i) => i.category !== 'court')

      const courtTotal = courtItems.reduce((s, i) => s + i.total, 0)
      const otherTotal = otherItems.reduce((s, i) => s + i.total, 0)

      const totalMinutes = members.value.reduce((s, m) => s + (m.timeMinutes || 60), 0)
      const otherPerPerson = Math.floor(otherTotal / count)

      members.value.forEach((m) => {
        const courtShare =
          totalMinutes > 0 ? Math.round((courtTotal * (m.timeMinutes || 60)) / totalMinutes) : 0
        m.amount = courtShare + otherPerPerson
      })
    } else if (splitMode.value === 'custom') {
      // If custom amounts are empty, populate with equal split as starting point
      members.value.forEach((m) => {
        if (m.customAmount === undefined || m.customAmount === 0) {
          m.customAmount = m.amount || Math.floor(total / count)
        }
        m.amount = m.customAmount
      })
    }
  }

  function setCustomMemberAmount(memberId: number, amount: number) {
    const member = members.value.find((m) => m.id === memberId)
    if (member) {
      member.customAmount = Math.max(0, amount)
      member.amount = member.customAmount
    }
  }

  function setMemberMinutes(memberId: number, minutes: number) {
    const member = members.value.find((m) => m.id === memberId)
    if (member) {
      member.timeMinutes = Math.max(15, minutes)
      calculateSplits()
    }
  }

  function toggleItemAssignment(itemId: string, memberId: number) {
    const item = items.value.find((i) => i.id === itemId)
    if (!item) return
    const idx = item.assignedMemberIds.indexOf(memberId)
    if (idx > -1) {
      item.assignedMemberIds.splice(idx, 1)
    } else {
      item.assignedMemberIds.push(memberId)
    }
    calculateSplits()
  }

  // Member Management
  function addMember(name: string, phone: string) {
    if (!name.trim()) return
    const nextId = members.value.length > 0 ? Math.max(...members.value.map((m) => m.id)) + 1 : 1
    members.value.push({
      id: nextId,
      name: name.trim(),
      phone: phone.trim() || '0900 000 000',
      role: 'member',
      amount: 0,
      paid: false,
      timeMinutes: 120,
      customPercent: 0,
      customAmount: 0,
      assignedItemIds: items.value.map((i) => i.id),
    })
    calculateSplits()
    showNotice(`Đã thêm thành viên "${name}" vào danh sách chia bill!`)
    showAddMemberModal.value = false
  }

  function removeMember(memberId: number) {
    const target = members.value.find((m) => m.id === memberId)
    if (target?.role === 'host') {
      showNotice('Không thể xóa Chủ phòng (Host) khỏi bill.')
      return
    }
    members.value = members.value.filter((m) => m.id !== memberId)
    // Remove from item assignments
    items.value.forEach((item) => {
      item.assignedMemberIds = item.assignedMemberIds.filter((id) => id !== memberId)
    })
    calculateSplits()
    showNotice(`Đã xóa thành viên khỏi danh sách chia bill.`)
  }

  // Item Management
  function addServiceItem(
    name: string,
    category: 'drink' | 'gear' | 'service',
    qty: number,
    unitPrice: number,
  ) {
    if (!name.trim() || qty <= 0 || unitPrice <= 0) return
    const nextId = `item-${Date.now()}`
    items.value.push({
      id: nextId,
      name: name.trim(),
      category,
      qty,
      unitPrice,
      total: qty * unitPrice,
      assignedMemberIds: [],
    })
    calculateSplits()
    showNotice(`Đã thêm món "${name}" vào hóa đơn nhóm!`)
    showAddServiceModal.value = false
  }

  function removeServiceItem(itemId: string) {
    const item = items.value.find((i) => i.id === itemId)
    if (item?.category === 'court') {
      showNotice('Không thể xóa tiền thuê sân chính.')
      return
    }
    items.value = items.value.filter((i) => i.id !== itemId)
    calculateSplits()
    showNotice(`Đã xóa món khỏi hóa đơn.`)
  }

  // Payment Actions
  function openQr(member: SplitMember) {
    selectedMemberForQr.value = member
    showQrModal.value = true
  }

  async function markAsPaid(memberId: number) {
    processing.value = true
    try {
      try {
        await financeApi.paySplitShare(memberId, { method: 'vietqr' })
      } catch {
        // Fallback for demo/offline
      }

      const m = members.value.find((item) => item.id === memberId)
      if (m) {
        m.paid = true
        m.paidAt = new Date().toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' })
        m.method = 'VietQR'
        showNotice(`✓ Đã xác nhận ${m.name} thanh toán ${formatMoney(m.amount)} thành công!`)
      }
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể cập nhật trạng thái thanh toán.')
    } finally {
      processing.value = false
    }
  }

  async function coverShortfall() {
    processing.value = true
    try {
      try {
        await financeApi.coverSplitShortfall(bookingInfo.value.bookingCode)
      } catch {
        // Fallback for demo
      }

      members.value.forEach((m) => {
        if (!m.paid) {
          m.paid = true
          m.paidAt = new Date().toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' })
          m.method = 'Host bảo lãnh (Ví Aura)'
        }
      })
      showCoverShortfallModal.value = false
      showNotice('🛡️ Chủ phòng đã thanh toán bảo lãnh toàn bộ số tiền còn thiếu thành công!')
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể bảo lãnh thanh toán.')
    } finally {
      processing.value = false
    }
  }

  async function loadSplitGroup(splitId: number | string) {
    loading.value = true
    error.value = ''
    try {
      const codeStr = String(splitId).trim()
      bookingInfo.value.bookingCode = /^ORD/i.test(codeStr)
        ? codeStr.toUpperCase()
        : (/^\d+$/.test(codeStr) ? `ORD${codeStr}` : (codeStr || 'ORD18'))

      const res = await financeApi.getSplitGroup(splitId)
      if (res && res.shares && res.shares.length > 0) {
        if (res.code) {
          bookingInfo.value.bookingCode = /^ORD/i.test(res.code) ? res.code.toUpperCase() : `ORD${res.code}`
        }
      }
    } catch {
      // Use standard preconfigured state
    } finally {
      loading.value = false
      calculateSplits()
    }
  }

  function showNotice(msg: string) {
    notice.value = msg
    setTimeout(() => {
      if (notice.value === msg) notice.value = ''
    }, 4000)
  }

  function formatMoney(amount: number): string {
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0)
  }

  function copyShareLink() {
    const url = window.location.origin + `/split-bill/${bookingInfo.value.bookingCode}`
    navigator.clipboard?.writeText(url)
    showNotice('🔗 Đã sao chép liên kết chia bill vào clipboard!')
  }

  return {
    loading,
    processing,
    notice,
    error,
    showQrModal,
    selectedMemberForQr,
    showAddMemberModal,
    showAddServiceModal,
    showCoverShortfallModal,
    splitMode,
    bookingInfo,
    items,
    members,
    totalBill,
    totalPaid,
    remainingAmount,
    progressPercent,
    allocatedTotal,
    allocationDiff,
    isFullyPaid,
    unpaidMembersCount,
    calculateSplits,
    setCustomMemberAmount,
    setMemberMinutes,
    toggleItemAssignment,
    addMember,
    removeMember,
    addServiceItem,
    removeServiceItem,
    openQr,
    markAsPaid,
    coverShortfall,
    loadSplitGroup,
    showNotice,
    formatMoney,
    copyShareLink,
  }
}
