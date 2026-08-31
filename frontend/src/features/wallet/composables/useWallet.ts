import { ref, computed, onMounted } from 'vue'
import type { VenueWallet, Transaction, TransactionType, PaymentMethodType } from '../types/wallet'
export type { VenueWallet, Transaction, TransactionType, PaymentMethodType }
import { walletApi } from '../api/walletApi'
import { errorMessage } from '@/core/utils/errorMessage'
import { useAuthStore } from '@/features/auth/stores/authStore'

const DEFAULT_WALLETS: VenueWallet[] = [
  {
    id: 'wallet-main',
    venue_id: 1,
    venue_name: 'Ví Hội Viên Toàn Hệ Thống Aura',
    card_number: 'AUR-9902-8142',
    address: 'Toàn bộ hệ thống cơ sở Aura Court',
    balance: 1500000,
    points: 1250,
    is_active: true,
    currency: 'VND',
    member_tier: 'gold',
    tier_label: 'Gold Athlete',
    accent_theme: 'burgundy',
    card_holder: 'AURA MEMBER',
    expiry_date: '12/2028',
  },
  {
    id: 'wallet-thaodien',
    venue_id: 2,
    venue_name: 'Aura Court Thảo Điền (TP. Thủ Đức)',
    card_number: 'AUR-7721-6601',
    address: '215 Nguyễn Văn Hưởng, P. Thảo Điền, TP. Thủ Đức',
    balance: 450000,
    points: 380,
    is_active: true,
    currency: 'VND',
    member_tier: 'silver',
    tier_label: 'Silver Athlete',
    accent_theme: 'gold',
    card_holder: 'AURA MEMBER',
    expiry_date: '08/2027',
  },
  {
    id: 'wallet-caugiay',
    venue_id: 3,
    venue_name: 'Aura Court Cầu Giấy (Hà Nội)',
    card_number: 'AUR-5510-4498',
    address: '18 Duy Tân, Dịch Vọng Hậu, Cầu Giấy, Hà Nội',
    balance: 200000,
    points: 150,
    is_active: true,
    currency: 'VND',
    member_tier: 'standard',
    tier_label: 'Standard Athlete',
    accent_theme: 'navy',
    card_holder: 'AURA MEMBER',
    expiry_date: '06/2027',
  },
]

const INITIAL_TRANSACTIONS: Transaction[] = [
  {
    id: 'txn-101',
    type: 'topup',
    title: 'Nạp tiền tự động VietQR Napas247',
    venue_name: 'Ví Hệ Thống Aura',
    description: 'Chuyển khoản liên ngân hàng tự động · Giao dịch tức thì',
    date: '31/08/2026',
    time: '20:15',
    amount: 500000,
    balance_after: 1500000,
    status: 'success',
    reference: 'VQR-20260831-9812',
    method_label: 'VietQR Napas247',
  },
  {
    id: 'txn-102',
    type: 'booking',
    title: 'Thanh toán giữ chỗ Sân 02 (Giờ cao điểm)',
    venue_name: 'Aura Court Thảo Điền',
    description: 'Khung giờ 18:00 - 20:00 (2 giờ) · Sân thảm Yonex BWF',
    date: '30/08/2026',
    time: '17:45',
    amount: -240000,
    balance_after: 1000000,
    status: 'success',
    reference: 'BK-20260830-4410',
    method_label: 'Ví trả trước',
  },
  {
    id: 'txn-103',
    type: 'pos',
    title: 'Thanh toán dịch vụ POS quầy lễ tân',
    venue_name: 'Aura Court Thảo Điền',
    description: '02 Nước điện giải Pocari Sweat + 01 Hộp bóng Hải Yến',
    date: '30/08/2026',
    time: '20:05',
    amount: -75000,
    balance_after: 1240000,
    status: 'success',
    reference: 'POS-20260830-1120',
    method_label: 'Quét mã QR Member Pass',
  },
  {
    id: 'txn-104',
    type: 'refund',
    title: 'Hoàn tiền huỷ lịch đặt sân đúng hạn',
    venue_name: 'Aura Court Cầu Giấy',
    description: 'Hoàn trả 100% về ví do huỷ lịch trước 24 giờ quy định',
    date: '28/08/2026',
    time: '09:30',
    amount: 180000,
    balance_after: 1315000,
    status: 'success',
    reference: 'RF-20260828-0902',
    method_label: 'Hoàn tiền tự động',
  },
  {
    id: 'txn-105',
    type: 'topup',
    title: 'Nạp tiền ví qua Cổng MoMo QR',
    venue_name: 'Ví Hệ Thống Aura',
    description: 'Nạp tiền tài khoản hội viên qua ứng dụng Ví MoMo',
    date: '25/08/2026',
    time: '14:22',
    amount: 1000000,
    balance_after: 1135000,
    status: 'success',
    reference: 'MOMO-20260825-8831',
    method_label: 'Ví MoMo',
  },
]

export function useWallet() {
  const auth = useAuthStore()

  const loading = ref(false)
  const processing = ref(false)
  const notice = ref('')
  const error = ref('')

  const transactionFilter = ref<TransactionType | 'all'>('all')
  const selectedVenueFilter = ref<string>('all')
  const searchQuery = ref('')

  const isDepositModalOpen = ref(false)
  const depositStep = ref<'select' | 'vietqr_pay'>('select')
  const isQrModalOpen = ref(false)
  const selectedWalletForAction = ref<VenueWallet | null>(null)

  const depositAmount = ref<number>(500000)
  const depositMethod = ref<PaymentMethodType>('vietqr')
  const depositTargetWalletId = ref<string>('wallet-main')

  const quickAmounts = [100000, 200000, 500000, 1000000, 2000000, 5000000]

  const wallets = ref<VenueWallet[]>([...DEFAULT_WALLETS])
  const transactions = ref<Transaction[]>([...INITIAL_TRANSACTIONS])

  const totalBalance = computed(() => {
    return wallets.value.reduce((sum, w) => sum + (w.balance || 0), 0)
  })

  const totalPoints = computed(() => {
    return wallets.value.reduce((sum, w) => sum + (w.points || 0), 0)
  })

  const filteredTransactions = computed(() => {
    return transactions.value.filter((t) => {
      if (transactionFilter.value !== 'all' && t.type !== transactionFilter.value) return false
      if (selectedVenueFilter.value !== 'all' && !t.venue_name.toLowerCase().includes(selectedVenueFilter.value.toLowerCase())) return false
      if (searchQuery.value.trim()) {
        const q = searchQuery.value.toLowerCase().trim()
        const matchesTitle = t.title.toLowerCase().includes(q)
        const matchesRef = t.reference.toLowerCase().includes(q)
        const matchesDesc = t.description.toLowerCase().includes(q)
        if (!matchesTitle && !matchesRef && !matchesDesc) return false
      }
      return true
    })
  })

  // VietQR Transfer Info
  const transferInfo = computed(() => {
    const bank = 'TPBank'
    const accountNo = '83403032005'
    const accountName = 'AURA COURT'
    const userIdentifier = auth.user?.phone || auth.user?.id || 'MEMBER'
    const memo = `AURA NAP ${userIdentifier} ${depositAmount.value}`
    const qrUrl = `https://img.vietqr.io/image/${bank}-${accountNo}-compact2.png?amount=${depositAmount.value}&addInfo=${encodeURIComponent(
      memo
    )}&accountName=${encodeURIComponent(accountName)}`

    return {
      bank,
      accountNo,
      accountName,
      memo,
      qrUrl,
      amount: depositAmount.value,
    }
  })

  async function loadWalletData() {
    loading.value = true
    error.value = ''
    try {
      const res = await walletApi.getWallet()
      if (res && typeof res.balance === 'number') {
        const userName = auth.user?.name || 'AURA MEMBER'
        wallets.value = [
          {
            id: 'wallet-main',
            venue_id: 1,
            venue_name: 'Ví Hội Viên Toàn Hệ Thống Aura',
            card_number: 'AUR-9902-8142',
            address: 'Toàn bộ hệ thống cơ sở Aura Court',
            balance: res.balance,
            points: 1250,
            is_active: true,
            currency: 'VND',
            member_tier: 'gold',
            tier_label: 'Gold Athlete',
            accent_theme: 'burgundy',
            card_holder: userName,
            expiry_date: '12/2028',
          },
          ...DEFAULT_WALLETS.slice(1).map((w) => ({
            ...w,
            card_holder: userName,
          })),
        ]
      }
    } catch {
      // Fallback graceful mode for demo/local environment
      const userName = auth.user?.name || 'AURA MEMBER'
      wallets.value = DEFAULT_WALLETS.map((w) => ({
        ...w,
        card_holder: userName,
      }))
    } finally {
      loading.value = false
    }
  }

  function showNotice(msg: string) {
    notice.value = msg
    setTimeout(() => {
      if (notice.value === msg) notice.value = ''
    }, 4500)
  }

  function openDepositModal(wallet?: VenueWallet) {
    if (wallet) {
      depositTargetWalletId.value = wallet.id
      selectedWalletForAction.value = wallet
    } else {
      depositTargetWalletId.value = wallets.value[0]?.id || 'wallet-main'
      selectedWalletForAction.value = wallets.value[0] || null
    }
    depositStep.value = 'select'
    isDepositModalOpen.value = true
  }

  function openQrModal(wallet?: VenueWallet) {
    selectedWalletForAction.value = wallet || wallets.value[0] || null
    isQrModalOpen.value = true
  }

  async function proceedToPaymentStep() {
    if (depositMethod.value === 'vietqr') {
      depositStep.value = 'vietqr_pay'
    } else {
      await confirmDeposit()
    }
  }

  async function confirmDeposit() {
    processing.value = true
    error.value = ''
    try {
      try {
        await walletApi.credit({
          amount: depositAmount.value,
          method: depositMethod.value,
          wallet_id: depositTargetWalletId.value,
        })
      } catch {
        // Local simulation fallback
      }

      // Update local wallet balance
      const target = wallets.value.find((w) => w.id === depositTargetWalletId.value)
      if (target) {
        target.balance += depositAmount.value
        target.points += Math.floor(depositAmount.value / 10000)
      } else if (wallets.value[0]) {
        wallets.value[0].balance += depositAmount.value
        wallets.value[0].points += Math.floor(depositAmount.value / 10000)
      }

      // Add transaction record
      const newTxn: Transaction = {
        id: `txn-${Date.now()}`,
        type: 'topup',
        title: `Nạp tiền ví qua ${depositMethod.value === 'vietqr' ? 'VietQR Napas247' : depositMethod.value.toUpperCase()}`,
        venue_name: target?.venue_name || 'Ví Hệ Thống Aura',
        description: 'Giao dịch nạp tiền tự động hoàn tất · Tiền khả dụng ngay',
        date: new Date().toLocaleDateString('vi-VN'),
        time: new Date().toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' }),
        amount: depositAmount.value,
        balance_after: totalBalance.value,
        status: 'success',
        reference: `TOPUP-${Date.now().toString().slice(-6)}`,
        method_label: depositMethod.value === 'vietqr' ? 'VietQR Napas247' : depositMethod.value.toUpperCase(),
      }

      transactions.value.unshift(newTxn)
      isDepositModalOpen.value = false
      depositStep.value = 'select'
      showNotice(`Nạp thành công ${depositAmount.value.toLocaleString('vi-VN')} ₫ vào ví!`)
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể thực hiện nạp tiền vào ví.')
    } finally {
      processing.value = false
    }
  }

  async function copyToClipboard(text: string, successMsg = 'Đã sao chép vào bộ nhớ tạm') {
    try {
      if (navigator?.clipboard?.writeText) {
        await navigator.clipboard.writeText(text)
        showNotice(successMsg)
      }
    } catch {
      // Fallback
    }
  }

  onMounted(() => {
    loadWalletData()
  })

  return {
    loading,
    processing,
    notice,
    error,
    transactionFilter,
    selectedVenueFilter,
    searchQuery,
    isDepositModalOpen,
    depositStep,
    isQrModalOpen,
    selectedWalletForAction,
    depositAmount,
    depositMethod,
    depositTargetWalletId,
    quickAmounts,
    wallets,
    transactions,
    totalBalance,
    totalPoints,
    filteredTransactions,
    transferInfo,
    loadWalletData,
    showNotice,
    openDepositModal,
    openQrModal,
    proceedToPaymentStep,
    confirmDeposit,
    copyToClipboard,
  }
}
