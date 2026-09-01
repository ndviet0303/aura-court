import { ref, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useBookingStore } from '@/stores/booking'
import { useAuthStore } from '@/stores/auth'
import { errorMessage } from '@/core/utils/errorMessage'
import { tournamentApi } from '@/features/tournament/api/tournamentApi'
import type { Booking } from '../types/booking'
import type { Court } from '@/features/court/types/court'
import type { TournamentItem } from '@/features/tournament/types/tournament'

export function usePaymentProcessing() {
  const route = useRoute()
  const router = useRouter()
  const store = useBookingStore()
  const auth = useAuthStore()

  const loading = ref(false)
  const status = ref('pending_payment')
  const error = ref('')
  const copyNotice = ref('')
  const selectedMethod = ref<'qr' | 'card'>('qr')
  const showSuccessModal = ref(false)

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

  // Stripe Card Form
  const cardNumber = ref('')
  const cardExpiry = ref('')
  const cardCvc = ref('')
  const cardName = ref('')
  const isCardProcessing = ref(false)

  const remainingSeconds = ref(900) // 15 mins default
  let timer: number | undefined
  let countdownTimer: number | undefined

  const payment = computed(() => store.payment)
  const booking = computed<Booking | null>(() => store.current)

  const isTournament = computed(() => {
    return !!tournament.value || store.payment?.type === 'tournament' || !!route.query.tournament_uuid || !!route.query.tournament_id || (typeof route.params.code === 'string' && route.params.code.startsWith('TRN-'))
  })

  const court = computed<Court | null>(() => {
    if (booking.value?.court) return booking.value.court
    if (store.selectedCourt) {
      return store.courts.find((c: Court) => c.id === store.selectedCourt) || null
    }
    return null
  })

  const playerDetails = computed(() => {
    if (isTournament.value && athleteInfo.value.name) {
      return {
        name: athleteInfo.value.name,
        email: auth.user?.email || 'athlete@aura.com',
        phone: athleteInfo.value.phone || '+84 (0) 90 123 4567',
        partnerName: athleteInfo.value.partnerName || '',
      }
    }
    return {
      name: auth.user?.name || 'Alexander Mitchell',
      email: auth.user?.email || 'a.mitchell@aura.com',
      phone: auth.user?.phone || '+84 (0) 90 123 4567',
      partnerName: '',
    }
  })

  const countdownFormatted = computed(() => {
    const m = Math.floor(remainingSeconds.value / 60)
    const s = remainingSeconds.value % 60
    return `${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`
  })

  async function checkPaymentStatus() {
    try {
      if (isTournament.value) return
      const res = await store.refreshPaymentStatus()
      if (res?.status === 'confirmed' || res?.status === 'completed') {
        status.value = 'confirmed'
        showSuccessModal.value = true
        clearInterval(timer)
      }
    } catch {
      // polling error ignored
    }
  }

  async function applyCoupon() {
    if (!couponCode.value.trim()) return
    couponLoading.value = true
    couponError.value = ''
    couponSuccess.value = ''

    try {
      if (isTournament.value) {
        if (couponCode.value.trim().toUpperCase() === 'AURA2026' || couponCode.value.trim().toUpperCase() === 'VIP') {
          if (store.payment) {
            store.payment.amount = Math.max(0, store.payment.amount - 50000)
            couponSuccess.value = 'Đã áp dụng giảm giá 50.000 ₫ lệ phí giải!'
          }
        } else {
          couponError.value = 'Mã giảm giá không hợp lệ cho giải đấu này.'
        }
      } else {
        await store.applyCoupon(couponCode.value.trim())
        couponSuccess.value = 'Áp dụng mã giảm giá thành công!'
      }
    } catch (e) {
      couponError.value = errorMessage(e, 'Mã giảm giá không hợp lệ hoặc đã hết hạn.')
    } finally {
      couponLoading.value = false
    }
  }

  async function handleStripePay() {
    isCardProcessing.value = true
    error.value = ''
    try {
      if (isTournament.value) {
        // Simulate Stripe transaction processing
        await new Promise((resolve) => setTimeout(resolve, 1000))
        status.value = 'confirmed'
        showSuccessModal.value = true
      } else {
        await store.stripePay({
          cardNumber: cardNumber.value,
          cardExpiry: cardExpiry.value,
          cardCvc: cardCvc.value,
          cardName: cardName.value,
        })
        status.value = 'confirmed'
        showSuccessModal.value = true
      }
    } catch (e) {
      error.value = errorMessage(e, 'Thanh toán thẻ thất bại. Vui lòng thử lại.')
    } finally {
      isCardProcessing.value = false
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

  function parseFeeAmount(feeStr?: string): number {
    if (!feeStr) return 300000
    const cleaned = feeStr.replace(/[^\d]/g, '')
    const parsed = parseInt(cleaned, 10)
    return isNaN(parsed) || parsed <= 0 ? 300000 : parsed
  }

  async function initPaymentSession() {
    loading.value = true
    error.value = ''

    try {
      const tourneyUuid = (route.query.tournament_uuid || route.query.tournament_id) as string | undefined
      const codeParam = route.params.code as string | undefined

      // Check if tournament flow
      if (tourneyUuid || (codeParam && codeParam.startsWith('TRN-')) || route.query.type === 'tournament') {
        const identifier = tourneyUuid || (codeParam ? codeParam.replace(/^TRN-/, '').split('-')[0] : '')
        
        if (identifier) {
          try {
            const tourneyData = await tournamentApi.getById(identifier)
            tournament.value = tourneyData
          } catch {
            // Fallback default tournament
            tournament.value = {
              id: 1,
              name: 'Aura Tournament Championship 2026',
              sport: 'badminton',
              format: 'single_elimination',
              status: 'open',
              prize_pool: '15.000.000 ₫',
              dates: 'Tháng 09/2026',
              venue: 'Aura Sport Arena',
              city: 'Hồ Chí Minh',
              current_slots: 12,
              max_slots: 16,
              entry_fee: '300.000 ₫ / VĐV',
              image_url: 'https://images.unsplash.com/photo-1626224583764-f87db24ac4ea?w=800&auto=format&fit=crop&q=80',
              version: 1,
            }
          }
        }

        // Parse athlete info
        athleteInfo.value = {
          name: (route.query.name as string) || (route.query.athlete_name as string) || auth.user?.name || 'Vận Động Viên Aura',
          phone: (route.query.phone as string) || auth.user?.phone || '0901234567',
          partnerName: (route.query.partner as string) || (route.query.partner_name as string) || undefined,
          notes: (route.query.notes as string) || undefined,
        }

        const fee = parseFeeAmount(tournament.value?.entry_fee)
        const tourneyIdNum = tournament.value?.id || 1
        const tourneyCode = codeParam && /^ORD/i.test(codeParam)
          ? codeParam.replace(/\s+/g, '')
          : `ORD${tourneyIdNum}`

        store.payment = {
          code: tourneyCode,
          amount: fee,
          bank: 'TPBank',
          accountNumber: '83403032005',
          qrUrl: `https://api.vietqr.io/image/970423-83403032005-compact2.png?amount=${fee}&addInfo=${encodeURIComponent(tourneyCode)}&accountName=${encodeURIComponent('AURA COURT')}`,
          expiresAt: new Date(Date.now() + 15 * 60 * 1000).toISOString(),
          type: 'tournament',
          title: tournament.value?.name || 'Lệ Phí Giải Đấu Aura',
          tournament: tournament.value,
          athleteDetails: athleteInfo.value,
        }
      } else if (codeParam && codeParam.startsWith('BK-')) {
        // Booking code
        try {
          await store.loadPayment(codeParam)
        } catch {
          store.restorePayment()
        }
      } else {
        store.restorePayment()
      }
    } catch (e: unknown) {
      error.value = errorMessage(e, 'Không thể tải thông tin thanh toán.')
    } finally {
      loading.value = false
      setupTimers()
    }
  }

  function setupTimers() {
    if (payment.value?.expiresAt) {
      const exp = new Date(payment.value.expiresAt).getTime()
      remainingSeconds.value = Math.max(0, Math.floor((exp - Date.now()) / 1000))
    }

    clearInterval(countdownTimer)
    countdownTimer = window.setInterval(() => {
      if (remainingSeconds.value > 0) {
        remainingSeconds.value--
      } else {
        clearInterval(countdownTimer)
      }
    }, 1000)

    clearInterval(timer)
    if (!isTournament.value) {
      timer = window.setInterval(checkPaymentStatus, 4000)
    }
  }

  function startTimers() {
    initPaymentSession()
  }

  function stopTimers() {
    clearInterval(timer)
    clearInterval(countdownTimer)
  }

  return {
    loading,
    isTournament,
    tournament,
    athleteInfo,
    payment,
    booking,
    court,
    playerDetails,
    status,
    error,
    copyNotice,
    selectedMethod,
    showSuccessModal,
    couponCode,
    couponLoading,
    couponError,
    couponSuccess,
    cardNumber,
    cardExpiry,
    cardCvc,
    cardName,
    isCardProcessing,
    remainingSeconds,
    countdownFormatted,
    applyCoupon,
    handleStripePay,
    copyToClipboard,
    startTimers,
    stopTimers,
  }
}
