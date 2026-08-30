import { ref, computed } from 'vue'
import { financeApi } from '../api/financeApi'
import { errorMessage } from '@/core/utils/errorMessage'

export interface SplitMember {
  id: number
  name: string
  phone: string
  role: 'host' | 'member'
  amount: number
  paid: boolean
  method?: string
  paidAt?: string
  avatar: string
  timeMinutes: number
  customPercent?: number
}

export interface SplitItemDetail {
  id: string
  name: string
  category: 'court' | 'service' | 'drink'
  qty: number
  unitPrice: number
  total: number
}

export type SplitMode = 'equal' | 'itemized' | 'custom' | 'time'

export function useGroupSplitBill() {
  const loading = ref(false)
  const processing = ref(false)
  const bookingCode = ref('')
  const courtName = ref('')
  const venueName = ref('')
  const bookingDate = ref('')
  const bookingTime = ref('')
  const bankName = ref('MBBank')
  const accountNumber = ref('')
  const accountName = ref('')

  const splitMode = ref<SplitMode>('equal')
  const showQrModal = ref(false)
  const selectedMemberForQr = ref<SplitMember | null>(null)
  const notice = ref('')
  const error = ref('')

  const items = ref<SplitItemDetail[]>([])
  const members = ref<SplitMember[]>([])

  const totalBill = computed(() => items.value.reduce((sum, item) => sum + (item.total || 0), 0))

  const totalPaid = computed(() => {
    return members.value.filter((m) => m.paid).reduce((sum, m) => sum + (m.amount || 0), 0)
  })

  const remainingAmount = computed(() => {
    return Math.max(0, totalBill.value - totalPaid.value)
  })

  const progressPercent = computed(() => {
    if (totalBill.value <= 0) return 0
    return Math.round((totalPaid.value / totalBill.value) * 100)
  })

  function calculateSplits() {
    const total = totalBill.value
    const count = members.value.length
    if (count === 0) return

    if (splitMode.value === 'equal') {
      const perPerson = Math.round(total / count)
      members.value.forEach((m) => {
        m.amount = perPerson
      })
    }
  }

  async function loadSplitGroup(splitId: number | string) {
    loading.value = true
    error.value = ''
    try {
      const res = await financeApi.getSplitGroup(splitId)
      if (res) {
        bookingCode.value = res.code || String(splitId)
      }
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể tải thông tin chia tiền hóa đơn.')
    } finally {
      loading.value = false
    }
  }

  function showNotice(msg: string) {
    notice.value = msg
    setTimeout(() => {
      if (notice.value === msg) notice.value = ''
    }, 4000)
  }

  function openQr(member: SplitMember) {
    selectedMemberForQr.value = member
    showQrModal.value = true
  }

  async function markAsPaid(memberId: number) {
    processing.value = true
    try {
      await financeApi.paySplitShare(memberId, { method: 'vietqr' })
      const m = members.value.find((item) => item.id === memberId)
      if (m) {
        m.paid = true
        m.paidAt = new Date().toLocaleTimeString('vi-VN')
        m.method = 'VietQR'
        showNotice(`Đã xác nhận ${m.name} thanh toán thành công!`)
      }
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể xác nhận thanh toán.')
    } finally {
      processing.value = false
    }
  }

  return {
    loading,
    processing,
    bookingCode,
    courtName,
    venueName,
    bookingDate,
    bookingTime,
    bankName,
    accountNumber,
    accountName,
    splitMode,
    showQrModal,
    selectedMemberForQr,
    notice,
    error,
    items,
    totalBill,
    members,
    totalPaid,
    remainingAmount,
    progressPercent,
    loadSplitGroup,
    calculateSplits,
    showNotice,
    openQr,
    markAsPaid,
  }
}
