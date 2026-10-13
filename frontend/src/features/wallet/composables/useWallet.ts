import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import type {
  VenueWallet,
  Transaction,
  TransactionType,
  PaymentMethodType,
  CardDetails,
} from '../types/wallet'
export type { VenueWallet, Transaction, TransactionType, PaymentMethodType, CardDetails }
import { walletApi } from '../api/walletApi'
import { errorMessage } from '@/core/utils/errorMessage'
import { useAuthStore } from '@/features/auth/stores/authStore'
import { useBookingStore } from '@/stores/booking'

export interface ActiveDeposit {
  id: number
  reference: string
  organization_id: number
  amount: number
  currency: string
  status: string
  provider: string
  transfer_info: {
    bank: string
    account_number: string
    account_name: string
    code: string
    amount: number
    qr_url: string
  }
}

export function useWallet(organizationId?: number) {
  const auth = useAuthStore()
  const bookingStore = useBookingStore()

  const loading = ref(false)
  const processing = ref(false)
  const notice = ref('')
  const error = ref('')

  // Filters & Search
  const transactionFilter = ref<TransactionType | 'all'>('all')
  const selectedVenueFilter = ref<string>('all')
  const searchQuery = ref<string>('')

  // Modals state
  const isDepositModalOpen = ref(false)
  const depositStep = ref<'select' | 'sepay_pay' | 'stripe_pay'>('select')
  const isQrModalOpen = ref(false)
  const selectedWalletForAction = ref<VenueWallet | null>(null)

  // Deposit Form State
  const depositAmount = ref<number>(200000)
  const depositMethod = ref<PaymentMethodType>('sepay')
  const depositTargetWalletId = ref<string>('wallet-main')
  const activeDeposit = ref<ActiveDeposit | null>(null)

  // Stripe card state
  const cardNumber = ref('')
  const cardExpiry = ref('')
  const cardCvc = ref('')
  const cardName = ref('')

  // Quick Amount Presets
  const quickAmounts = [100000, 200000, 500000, 1000000, 2000000, 5000000]

  // Data
  const wallets = ref<VenueWallet[]>([])
  const transactions = ref<Transaction[]>([])

  const totalBalance = computed(() => {
    return wallets.value.reduce((sum, w) => sum + (w.balance || 0), 0)
  })

  const totalPoints = computed(() => {
    return wallets.value.reduce((sum, w) => sum + (w.points || 0), 0)
  })

  const filteredTransactions = computed(() => {
    return transactions.value.filter((t) => {
      if (transactionFilter.value !== 'all' && t.type !== transactionFilter.value) return false
      if (
        selectedVenueFilter.value !== 'all' &&
        !t.venue_name.toLowerCase().includes(selectedVenueFilter.value.toLowerCase())
      )
        return false
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

  // SePay VietQR Transfer Info from server or computed fallback
  const transferInfo = computed(() => {
    if (activeDeposit.value?.transfer_info) {
      const info = activeDeposit.value.transfer_info
      return {
        bank: info.bank,
        accountNo: info.account_number,
        accountName: info.account_name,
        memo: info.code,
        qrUrl: info.qr_url,
        amount: info.amount,
      }
    }

    const bank = 'TPBank'
    const accountNo = '83403032005'
    const accountName = 'AURA COURT'
    const userIdentifier = auth.user?.phone || auth.user?.id || 'MEMBER'
    const memo = `ORD NAP ${userIdentifier}`
    const qrUrl = `https://img.vietqr.io/image/${bank}-${accountNo}-compact2.png?amount=${depositAmount.value}&addInfo=${encodeURIComponent(
      memo,
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
      if (
        organizationId !== undefined &&
        (!Number.isSafeInteger(organizationId) || organizationId <= 0)
      ) {
        throw new Error('Không tìm thấy tổ chức cho ví được chọn.')
      }
      const res = await walletApi.getWallet(organizationId)
      const userName = auth.user?.name || 'AURA MEMBER'
      const realBal = typeof res?.balance === 'number' ? res.balance : 0

      wallets.value = [
        {
          id: res.id,
          organization_id: res.organization_id,
          venue_id: 1,
          venue_name: 'Ví Hội Viên Hệ Thống Aura Court',
          card_number: `AUR-9902-${String(auth.user?.id || 1).padStart(4, '0')}`,
          address: 'Toàn bộ hệ thống cơ sở Aura Court',
          balance: realBal,
          points: res.points,
          is_active: true,
          currency: res?.currency || 'VND',
          tier_label: res.membership_tier,
          accent_theme: 'burgundy',
          card_holder: userName,
          expiry_date: '12/2028',
        },
      ]

      if (Array.isArray(res?.transactions)) {
        transactions.value = res.transactions.map((t: any) => ({
          id: `txn-${t.id}`,
          type: t.type === 'credit' ? 'topup' : 'booking',
          title: t.type === 'credit' ? 'Nạp tiền ví Aura' : 'Thanh toán đặt sân',
          venue_name: 'Hệ Thống Aura Court',
          description:
            t.description || (t.type === 'credit' ? 'Nạp tiền vào ví' : 'Trừ số dư đặt sân'),
          date: t.created_at ? new Date(t.created_at).toLocaleDateString('vi-VN') : '',
          time: t.created_at
            ? new Date(t.created_at).toLocaleTimeString('vi-VN', {
                hour: '2-digit',
                minute: '2-digit',
              })
            : '',
          amount: t.type === 'credit' ? t.amount : -t.amount,
          balance_after: t.balance_after ?? realBal,
          status: 'success',
          reference: t.reference || `TXN-${t.id}`,
          method_label: 'Ví Aura',
        }))
      }

      bookingStore.walletBalance = realBal
    } catch (e: unknown) {
      // Prohibit fallback balances: on error, do not fabricate synthetic balances or default cards
      wallets.value = []
      bookingStore.walletBalance = 0
      error.value = errorMessage(e, 'Không thể tải thông tin số dư ví từ máy chủ.')
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
    activeDeposit.value = null
    depositStep.value = 'select'
    isDepositModalOpen.value = true
  }

  function openQrModal(wallet?: VenueWallet) {
    selectedWalletForAction.value = wallet || wallets.value[0] || null
    isQrModalOpen.value = true
  }

  async function proceedToPaymentStep() {
    if (depositMethod.value === 'sepay') {
      processing.value = true
      error.value = ''
      try {
        const selectedWallet = wallets.value.find(
          (wallet) => wallet.id === depositTargetWalletId.value,
        )
        if (!selectedWallet) throw new Error('Vui lòng chọn ví trước khi nạp tiền.')
        const initRes = await walletApi.initiateDeposit({
          amount: depositAmount.value,
          organization_id: selectedWallet.organization_id,
          provider: 'sepay',
        })
        activeDeposit.value = initRes
        depositStep.value = 'sepay_pay'
      } catch (e: unknown) {
        error.value = errorMessage(e, 'Không thể khởi tạo giao dịch nạp tiền. Vui lòng thử lại.')
      } finally {
        processing.value = false
      }
    } else {
      error.value = 'Nạp tiền qua Stripe chưa được cấu hình. Vui lòng sử dụng chuyển khoản VietQR.'
    }
  }

  async function confirmDeposit() {
    processing.value = true
    error.value = ''
    try {
      if (depositMethod.value !== 'sepay') {
        throw new Error(
          'Nạp tiền qua Stripe chưa được cấu hình. Vui lòng sử dụng chuyển khoản VietQR.',
        )
      }

      if (activeDeposit.value?.reference) {
        const statusRes = await walletApi.getDepositStatus(activeDeposit.value.reference)
        if (statusRes.status === 'succeeded') {
          await finishSucceededDeposit(statusRes.amount)
          return
        }

        if (statusRes.status === 'failed') {
          error.value = 'Giao dịch nạp tiền đã thất bại hoặc hết hạn.'
          isDepositModalOpen.value = false
          depositStep.value = 'select'
          activeDeposit.value = null
          return
        }

        // Pending status
        isDepositModalOpen.value = false
        depositStep.value = 'select'
        showNotice(
          'Giao dịch đang chờ ngân hàng/SePay xác thực. Số dư sẽ tự động được cộng khi hệ thống nhận được giao dịch.',
        )
        return
      }

      await loadWalletData()
      isDepositModalOpen.value = false
      depositStep.value = 'select'
      showNotice('Giao dịch sẽ được cộng vào ví sau khi SePay xác nhận chuyển khoản.')
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể kiểm tra trạng thái nạp tiền.')
    } finally {
      processing.value = false
    }
  }

  async function finishSucceededDeposit(amount: number) {
    await loadWalletData()
    isDepositModalOpen.value = false
    depositStep.value = 'select'
    showNotice(`Nạp tiền thành công! Đã cộng ${amount.toLocaleString('vi-VN')} VND vào ví.`)
    activeDeposit.value = null
  }

  // Poll while the SePay QR is on screen so the webhook credit closes the modal by itself.
  let depositPoll: ReturnType<typeof setInterval> | undefined

  function stopDepositPolling() {
    if (depositPoll) clearInterval(depositPoll)
    depositPoll = undefined
  }

  async function pollDepositStatus(reference: string) {
    try {
      const statusRes = await walletApi.getDepositStatus(reference)
      if (statusRes.status === 'succeeded' && activeDeposit.value?.reference === reference) {
        await finishSucceededDeposit(statusRes.amount)
      }
    } catch {
      // Transient errors keep polling; the manual confirm button surfaces them.
    }
  }

  watch(
    () =>
      isDepositModalOpen.value && depositStep.value === 'sepay_pay'
        ? (activeDeposit.value?.reference ?? null)
        : null,
    (reference) => {
      stopDepositPolling()
      if (reference) depositPoll = setInterval(() => pollDepositStatus(reference), 5000)
    },
  )

  onUnmounted(stopDepositPolling)

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
    activeDeposit,
    cardNumber,
    cardExpiry,
    cardCvc,
    cardName,
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
