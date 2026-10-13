import { ref, computed } from 'vue'
import { useRoute } from 'vue-router'
import { useBookingStore } from '@/stores/booking'
import { useAuthStore } from '@/stores/auth'
import { errorMessage } from '@/core/utils/errorMessage'
import { tournamentApi } from '@/features/tournament/api/tournamentApi'
import type { Booking } from '../types/booking'
import type { ReservationPaymentAttempt } from '../api/bookingApi'
import type { Court } from '@/features/court/types/court'
import type { TournamentItem } from '@/features/tournament/types/tournament'

export function usePaymentProcessing() {
  const route = useRoute()
  const store = useBookingStore()
  const auth = useAuthStore()

  const loading = ref(false)
  const isPaying = ref(false)
  const status = ref('pending_payment')
  const error = ref('')
  const copyNotice = ref('')
  const showSuccessModal = ref(false)
  const walletBalance = ref(0)
  const directPaymentAttempt = ref<ReservationPaymentAttempt | null>(null)
  const directPaymentError = ref('')
  const directPaymentLoading = ref(false)
  const directPaymentRefreshing = ref(false)
  let directPaymentPoller: ReturnType<typeof setInterval> | undefined

  // Tournament specific state
  const tournament = ref<TournamentItem | null>(null)
  const athleteInfo = ref<{
    name?: string
    phone?: string
    partnerName?: string
    notes?: string
  }>({})

  // Coupon
  const couponCode = ref('')
  const couponLoading = ref(false)
  const couponError = ref('')
  const couponSuccess = ref('')

  const payment = computed(() => store.payment)
  const booking = computed<Booking | null>(() => store.current)
  const isDirectOwnerPayment = computed(() => booking.value?.payment_model === 'direct_owner_v1')

  const isTournament = computed(() => {
    return (
      !!tournament.value ||
      store.payment?.type === 'tournament' ||
      !!route.query.tournament_uuid ||
      !!route.query.tournament_id ||
      (typeof route.params.code === 'string' && route.params.code.startsWith('TRN-'))
    )
  })

  const court = computed<Court | null>(() => {
    if (booking.value?.court) return booking.value.court
    if (store.selectedCourt) {
      return store.courts.find((c: Court) => c.id === store.selectedCourt) || null
    }
    return null
  })

  // '—' marks a detail we do not have; never show a stand-in person.
  const playerDetails = computed(() => {
    if (isTournament.value && athleteInfo.value.name) {
      return {
        name: athleteInfo.value.name,
        email: auth.user?.email || '—',
        phone: athleteInfo.value.phone || '—',
        partnerName: athleteInfo.value.partnerName || '',
      }
    }
    return {
      name: auth.user?.name || '—',
      email: auth.user?.email || '—',
      phone: auth.user?.phone || '—',
      partnerName: '',
    }
  })

  const paymentAmount = computed(() => {
    if (isTournament.value && tournament.value) {
      return parseFeeAmount(tournament.value.entry_fee)
    }
    return booking.value?.total_amount || store.payment?.amount || 0
  })

  async function loadBalance() {
    try {
      const bal = await store.fetchWalletBalance()
      walletBalance.value = bal
    } catch {
      walletBalance.value = store.walletBalance
    }
  }

  async function applyCoupon() {
    if (!couponCode.value.trim()) return
    couponLoading.value = true
    couponError.value = ''
    couponSuccess.value = ''

    try {
      // Booking coupons only: tournament registration charges the published fee, so the
      // summary card offers no coupon there.
      await store.applyCoupon(couponCode.value.trim())
      couponSuccess.value = 'Áp dụng mã giảm giá thành công!'
    } catch (e) {
      couponError.value = errorMessage(e, 'Mã giảm giá không hợp lệ hoặc đã hết hạn.')
    } finally {
      couponLoading.value = false
    }
  }

  async function handleWalletPay() {
    if (isDirectOwnerPayment.value) {
      await refreshDirectPaymentStatus()
      return
    }
    isPaying.value = true
    error.value = ''
    try {
      if (isTournament.value) {
        // Only the loaded tournament, whose fee this page showed, may be charged.
        const tourneyId = tournament.value?.uuid || tournament.value?.id
        if (!tourneyId) {
          throw new Error('Không tìm thấy thông tin giải đấu để đăng ký.')
        }
        const res = await tournamentApi.register(tourneyId, {
          name:
            athleteInfo.value.name ||
            (route.query.name as string) ||
            (route.query.athlete_name as string) ||
            auth.user?.name,
          phone: athleteInfo.value.phone || (route.query.phone as string) || auth.user?.phone,
          partner:
            athleteInfo.value.partnerName ||
            (route.query.partner as string) ||
            (route.query.partner_name as string) ||
            undefined,
          notes: athleteInfo.value.notes || (route.query.notes as string) || undefined,
        })
        if (res?.receipt && store.payment) {
          store.payment.receipt = res.receipt
          store.payment.participant = res.participant
          store.payment.code = res.receipt.receipt_code || store.payment.code
        }
        status.value = 'confirmed'
        showSuccessModal.value = true
        await loadBalance()
      } else {
        const code = booking.value?.code || (route.params.code as string | undefined)
        if (code) {
          const paymentData = await store.loadPayment(code)
          if (paymentData.status === 'confirmed' || paymentData.booking?.status === 'confirmed') {
            status.value = 'confirmed'
            showSuccessModal.value = true
            await loadBalance()
            return
          }
        }
        throw new Error('Đơn đặt sân chưa được thanh toán thành công hoặc đã hết hạn.')
      }
    } catch (e) {
      error.value = errorMessage(e, 'Thanh toán từ ví thất bại. Vui lòng thử lại.')
    } finally {
      isPaying.value = false
    }
  }

  function copyToClipboard(text: string, label: string) {
    if (navigator.clipboard) {
      navigator.clipboard.writeText(text).then(() => {
        copyNotice.value = `Đã sao chép ${label}`
        setTimeout(() => {
          copyNotice.value = ''
        }, 2500)
      })
    }
  }

  // Same digits registration charges for a "300.000 ₫ / VĐV" fee; no fee means free entry.
  function parseFeeAmount(fee?: string | number | null): number {
    const digits = String(fee ?? '').replace(/\D/g, '')
    return digits ? parseInt(digits, 10) : 0
  }

  async function initPaymentSession() {
    loading.value = true
    error.value = ''

    try {
      const tourneyUuid = (route.query.tournament_uuid || route.query.tournament_id) as
        string | undefined
      const codeParam = route.params.code as string | undefined

      // Check if tournament flow
      if (
        tourneyUuid ||
        (codeParam && codeParam.startsWith('TRN-')) ||
        route.query.type === 'tournament'
      ) {
        // Never let an earlier session stand in for this tournament, even if loading fails.
        store.payment = null
        await loadBalance()
        const identifier =
          tourneyUuid || (codeParam ? codeParam.replace(/^TRN-/, '').split('-')[0] : '')
        if (!identifier) throw new Error('Không tìm thấy thông tin giải đấu để đăng ký.')
        // No stand-in tournament on failure: without the real one there is no fee to show.
        const tourney = await tournamentApi.getById(identifier)
        tournament.value = tourney

        athleteInfo.value = {
          name:
            (route.query.name as string) || (route.query.athlete_name as string) || auth.user?.name,
          phone: (route.query.phone as string) || auth.user?.phone || undefined,
          partnerName:
            (route.query.partner as string) || (route.query.partner_name as string) || undefined,
          notes: (route.query.notes as string) || undefined,
        }

        const fee = parseFeeAmount(tourney.entry_fee)
        const tourneyCode =
          codeParam && /^ORD/i.test(codeParam) ? codeParam.replace(/\s+/g, '') : `ORD${tourney.id}`

        store.payment = {
          code: tourneyCode,
          amount: fee,
          bank: 'Ví Aura',
          accountNumber: 'WALLET',
          qrUrl: '',
          expiresAt: '',
          type: 'tournament',
          title: tourney.name,
          tournament: tourney,
          athleteDetails: athleteInfo.value,
        }
      } else if (codeParam && codeParam.startsWith('BK')) {
        try {
          const paymentData = await store.loadPayment(codeParam)
          if (paymentData.status === 'confirmed' || paymentData.booking?.status === 'confirmed') {
            status.value = 'confirmed'
            showSuccessModal.value = true
          } else if (paymentData.payment_model === 'direct_owner_v1') {
            await createDirectPaymentAttempt(codeParam)
          } else {
            await loadBalance()
            status.value = 'pending_payment'
          }
        } catch {
          store.restorePayment()
          status.value = 'pending_payment'
          if (store.current?.payment_model === 'direct_owner_v1') {
            await createDirectPaymentAttempt(codeParam)
          } else {
            await loadBalance()
          }
        }
      } else {
        store.restorePayment()
        await loadBalance()
      }
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể tải thông tin thanh toán.')
    } finally {
      loading.value = false
    }
  }

  function startTimers() {
    return initPaymentSession()
  }

  async function createDirectPaymentAttempt(code: string) {
    directPaymentLoading.value = true
    directPaymentError.value = ''
    try {
      const response = await store.createBookingPayment(code)
      directPaymentAttempt.value = response.payment_attempt
      if (response.booking.status === 'confirmed' || response.payment_attempt.status === 'paid') {
        status.value = 'confirmed'
        showSuccessModal.value = true
        stopDirectPaymentPolling()
      } else if (response.payment_attempt.status === 'pending') {
        startDirectPaymentPolling()
      } else {
        stopDirectPaymentPolling()
      }
    } catch (cause) {
      directPaymentError.value = errorMessage(
        cause,
        'Không thể tải thông tin thanh toán trực tiếp.',
      )
      stopDirectPaymentPolling()
    } finally {
      directPaymentLoading.value = false
    }
  }

  async function refreshDirectPaymentStatus() {
    const code = booking.value?.code || (route.params.code as string | undefined)
    if (!code || directPaymentRefreshing.value) return
    directPaymentRefreshing.value = true
    directPaymentError.value = ''
    try {
      const response = (await store.refreshPaymentStatus()) as {
        status: string
        payment_attempt?: ReservationPaymentAttempt | null
      } | null
      if (response?.payment_attempt) {
        directPaymentAttempt.value = response.payment_attempt
      }
      if (response?.status === 'confirmed' || response?.payment_attempt?.status === 'paid') {
        status.value = 'confirmed'
        showSuccessModal.value = true
        stopDirectPaymentPolling()
      } else if (response?.payment_attempt?.status === 'expired') {
        stopDirectPaymentPolling()
      }
    } catch (cause) {
      directPaymentError.value = errorMessage(cause, 'Không thể cập nhật trạng thái thanh toán.')
    } finally {
      directPaymentRefreshing.value = false
    }
  }

  function startDirectPaymentPolling() {
    stopDirectPaymentPolling()
    directPaymentPoller = setInterval(() => {
      void refreshDirectPaymentStatus()
    }, 5000)
  }

  function stopDirectPaymentPolling() {
    if (directPaymentPoller) clearInterval(directPaymentPoller)
    directPaymentPoller = undefined
  }

  function stopTimers() {
    stopDirectPaymentPolling()
  }

  return {
    loading,
    isPaying,
    isTournament,
    tournament,
    athleteInfo,
    payment,
    booking,
    court,
    playerDetails,
    walletBalance,
    paymentAmount,
    directPaymentAttempt,
    directPaymentError,
    directPaymentLoading,
    directPaymentRefreshing,
    isDirectOwnerPayment,
    status,
    error,
    copyNotice,
    showSuccessModal,
    couponCode,
    couponLoading,
    couponError,
    couponSuccess,
    applyCoupon,
    handleWalletPay,
    refreshDirectPaymentStatus,
    createDirectPaymentAttempt,
    copyToClipboard,
    startTimers,
    stopTimers,
  }
}
