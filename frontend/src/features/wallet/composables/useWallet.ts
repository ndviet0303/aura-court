import { ref, computed } from 'vue'

export interface VenueWallet {
  id: string
  venue_id: number
  venue_name: string
  address: string
  balance: number
  is_active: boolean
  currency: string
  gradient_type: 'primary' | 'secondary' | 'neutral'
}

export interface Transaction {
  id: string
  type: 'booking' | 'topup' | 'pos' | 'refund'
  title: string
  venue_name: string
  description: string
  date: string
  time: string
  amount: number
  status: 'success' | 'pending' | 'failed'
}

export function useWallet() {
  const loading = ref(false)
  const processing = ref(false)
  const notice = ref('')
  const error = ref('')

  const transactionFilter = ref<'all' | 'expense' | 'topup' | 'refund'>('all')
  const selectedVenueFilter = ref<string>('all')

  const isDepositModalOpen = ref(false)
  const isQrModalOpen = ref(false)
  const selectedWalletForAction = ref<VenueWallet | null>(null)

  const depositAmount = ref<number>(500000)
  const depositMethod = ref<'vietqr' | 'vnpay' | 'momo' | 'card'>('vietqr')
  const depositTargetWalletId = ref<string>('aura-center')

  const quickAmounts = [100000, 200000, 500000, 1000000, 2000000]

  const wallets = ref<VenueWallet[]>([
    {
      id: 'aura-center',
      venue_id: 1,
      venue_name: 'Aura Center (Quận 1)',
      address: 'Số 12 Nguyễn Thị Minh Khai, P. Bến Nghé, Q.1',
      balance: 1500000,
      is_active: true,
      currency: 'VND',
      gradient_type: 'primary',
    },
    {
      id: 'riverside-club',
      venue_id: 2,
      venue_name: 'Riverside Club (Bình Thạnh)',
      address: 'Số 48 Ung Văn Khiêm, P.25, Q. Bình Thạnh',
      balance: 4500000,
      is_active: true,
      currency: 'VND',
      gradient_type: 'secondary',
    },
    {
      id: 'district1-hub',
      venue_id: 3,
      venue_name: 'District 1 Hub',
      address: 'Số 88 Lê Thánh Tôn, P. Bến Nghé, Q.1',
      balance: 0,
      is_active: true,
      currency: 'VND',
      gradient_type: 'neutral',
    },
  ])

  const transactions = ref<Transaction[]>([
    {
      id: 'TXN-9801',
      type: 'topup',
      title: 'Nạp tiền Ví Aura Center',
      venue_name: 'Aura Center (Quận 1)',
      description: 'Chuyển khoản VietQR thành công',
      date: '30/08/2026',
      time: '09:15',
      amount: 500000,
      status: 'success',
    },
    {
      id: 'TXN-9800',
      type: 'booking',
      title: 'Thanh toán Sân VIP 1 (19:00 - 21:00)',
      venue_name: 'Aura Center (Quận 1)',
      description: 'Trừ số dư ví thành viên',
      date: '29/08/2026',
      time: '18:30',
      amount: -250000,
      status: 'success',
    },
    {
      id: 'TXN-9799',
      type: 'pos',
      title: 'Mua Nước Pocari & Cầu Yonex AS-50',
      venue_name: 'Riverside Club',
      description: 'Quẹt ví tại quầy Bar POS',
      date: '28/08/2026',
      time: '20:10',
      amount: -120000,
      status: 'success',
    },
  ])

  const totalBalance = computed(() => {
    return wallets.value.reduce((sum, w) => sum + w.balance, 0)
  })

  const filteredTransactions = computed(() => {
    return transactions.value.filter((t) => {
      if (transactionFilter.value === 'expense' && t.amount > 0) return false
      if (transactionFilter.value === 'topup' && t.type !== 'topup') return false
      if (transactionFilter.value === 'refund' && t.type !== 'refund') return false
      if (selectedVenueFilter.value !== 'all' && !t.venue_name.includes(selectedVenueFilter.value)) return false
      return true
    })
  })

  function showNotice(msg: string) {
    notice.value = msg
    setTimeout(() => {
      if (notice.value === msg) notice.value = ''
    }, 4000)
  }

  function openDepositModal(wallet?: VenueWallet) {
    if (wallet) {
      depositTargetWalletId.value = wallet.id
      selectedWalletForAction.value = wallet
    }
    isDepositModalOpen.value = true
  }

  function openQrModal(wallet: VenueWallet) {
    selectedWalletForAction.value = wallet
    isQrModalOpen.value = true
  }

  function confirmDeposit() {
    processing.value = true
    setTimeout(() => {
      const target = wallets.value.find((w) => w.id === depositTargetWalletId.value)
      if (target) target.balance += depositAmount.value
      transactions.value.unshift({
        id: `TXN-${Date.now().toString().slice(-4)}`,
        type: 'topup',
        title: `Nạp tiền ${target?.venue_name || 'Ví'}`,
        venue_name: target?.venue_name || 'Aura Center',
        description: `Nạp qua ${depositMethod.value.toUpperCase()}`,
        date: new Date().toLocaleDateString('vi-VN'),
        time: new Date().toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' }),
        amount: depositAmount.value,
        status: 'success',
      })
      isDepositModalOpen.value = false
      processing.value = false
      showNotice(`Đã nạp thành công ${depositAmount.value.toLocaleString('vi-VN')} ₫ vào ví!`)
    }, 1000)
  }

  return {
    loading,
    processing,
    notice,
    error,
    transactionFilter,
    selectedVenueFilter,
    isDepositModalOpen,
    isQrModalOpen,
    selectedWalletForAction,
    depositAmount,
    depositMethod,
    depositTargetWalletId,
    quickAmounts,
    wallets,
    transactions,
    totalBalance,
    filteredTransactions,
    showNotice,
    openDepositModal,
    openQrModal,
    confirmDeposit,
  }
}
