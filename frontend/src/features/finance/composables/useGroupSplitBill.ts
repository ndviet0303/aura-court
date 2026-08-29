import { ref, computed } from 'vue'

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
  const bookingCode = ref('AC-88924')
  const courtName = ref('Sân 3 - VIP BWF Taraflex')
  const venueName = ref('Aura Court Central - Quận 1')
  const bookingDate = ref('Thứ Năm, 28/08/2026')
  const bookingTime = ref('19:00 — 21:00 (2 tiếng)')
  const bankName = ref('TPBank')
  const accountNumber = ref('83403032005')
  const accountName = ref('AURA COURT')

  const splitMode = ref<SplitMode>('equal')
  const showQrModal = ref(false)
  const selectedMemberForQr = ref<SplitMember | null>(null)
  const notice = ref('')

  const items = ref<SplitItemDetail[]>([
    { id: '1', name: 'Thuê sân 3 (VIP Taraflex - 2 giờ)', category: 'court', qty: 2, unitPrice: 220000, total: 440000 },
    { id: '2', name: '2 Hộp cầu lông Yonex Aerosensa 50', category: 'service', qty: 2, unitPrice: 60000, total: 120000 },
    { id: '3', name: '4 Chai nước ion Pocari Sweat 500ml', category: 'drink', qty: 4, unitPrice: 10000, total: 40000 },
  ])

  const totalBill = computed(() => items.value.reduce((sum, item) => sum + item.total, 0))

  const members = ref<SplitMember[]>([
    {
      id: 1,
      name: 'Nguyễn Hoàng Long',
      phone: '0988 888 888',
      role: 'host',
      amount: 150000,
      paid: true,
      method: 'Aura Pay',
      paidAt: '10:30:15',
      avatar: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=100&auto=format&fit=crop&q=80',
      timeMinutes: 120,
      customPercent: 25,
    },
    {
      id: 2,
      name: 'Trần Quốc Bảo',
      phone: '0912 345 678',
      role: 'member',
      amount: 150000,
      paid: true,
      method: 'VietQR',
      paidAt: '10:38:42',
      avatar: 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=100&auto=format&fit=crop&q=80',
      timeMinutes: 120,
      customPercent: 25,
    },
    {
      id: 3,
      name: 'Lê Minh Tuấn',
      phone: '0933 112 233',
      role: 'member',
      amount: 150000,
      paid: false,
      avatar: '',
      timeMinutes: 120,
      customPercent: 25,
    },
    {
      id: 4,
      name: 'Phạm Đức Anh',
      phone: '0977 445 566',
      role: 'member',
      amount: 150000,
      paid: false,
      avatar: '',
      timeMinutes: 120,
      customPercent: 25,
    },
  ])

  const totalPaid = computed(() => {
    return members.value.filter((m) => m.paid).reduce((sum, m) => sum + m.amount, 0)
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

  function markAsPaid(memberId: number) {
    const m = members.value.find((item) => item.id === memberId)
    if (m) {
      m.paid = true
      m.paidAt = new Date().toLocaleTimeString('vi-VN')
      m.method = 'VietQR'
      showNotice(`Đã đánh dấu ${m.name} đã hoàn tất thanh toán!`)
    }
  }

  return {
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
    items,
    totalBill,
    members,
    totalPaid,
    remainingAmount,
    progressPercent,
    calculateSplits,
    showNotice,
    openQr,
    markAsPaid,
  }
}
