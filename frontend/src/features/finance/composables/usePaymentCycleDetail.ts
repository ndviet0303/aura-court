import { ref, watch, onScopeDispose } from 'vue'
import { apiV2 } from '@/core/api/client'
import { errorMessage } from '@/core/utils/errorMessage'
import type { MonthlyInvoiceData, OccurrenceItem } from '../types/finance'
import type { ReservationPaymentAttempt } from '@/features/booking/api/bookingApi'

export type { MonthlyInvoiceData, OccurrenceItem }

export function usePaymentCycleDetail(invoiceId: number | string) {
  const loading = ref(false)
  const notice = ref('')
  const error = ref('')

  const invoice = ref<MonthlyInvoiceData | null>(null)
  const occurrences = ref<OccurrenceItem[]>([])
  const showPaymentModal = ref(false)
  const paymentAttempt = ref<ReservationPaymentAttempt | null>(null)
  const refreshing = ref(false)

  async function loadInvoice(silent = false) {
    if (!silent) loading.value = true
    error.value = ''
    try {
      const res = await apiV2.get<{
        invoice: MonthlyInvoiceData
        occurrences: OccurrenceItem[]
        payment_attempt: ReservationPaymentAttempt | null
      }>(`/monthly-invoices/${invoiceId}`)
      if (res && res.invoice) {
        invoice.value = res.invoice
        occurrences.value = res.occurrences || []
        paymentAttempt.value = res.payment_attempt ?? null
      } else {
        error.value = 'Không tìm thấy thông tin kỳ thanh toán.'
      }
    } catch (e) {
      error.value = errorMessage(e, 'Không thể tải chi tiết kỳ thanh toán.')
    } finally {
      if (!silent) loading.value = false
    }
  }

  // Direct-owner invoices confirm on a verified bank receipt (server webhook), not on a
  // client-declared reference. Poll the invoice while the QR is on screen so the UI reflects
  // the confirmation the moment the receiving event lands.
  async function refreshStatus() {
    refreshing.value = true
    try {
      await loadInvoice(true)
    } finally {
      refreshing.value = false
    }
  }

  let pollTimer: number | undefined
  function stopPolling() {
    if (pollTimer) {
      window.clearInterval(pollTimer)
      pollTimer = undefined
    }
  }
  function startPolling() {
    stopPolling()
    pollTimer = window.setInterval(() => {
      void refreshStatus()
    }, 5000)
  }

  watch(showPaymentModal, (open) => {
    if (open && paymentAttempt.value?.status === 'pending') startPolling()
    else stopPolling()
  })
  watch(
    () => paymentAttempt.value?.status,
    (status) => {
      if (status !== 'pending') stopPolling()
    },
  )
  onScopeDispose(stopPolling)


  function printInvoice() {
    window.print()
  }

  async function copyInvoiceLink(): Promise<boolean> {
    try {
      await navigator.clipboard.writeText(window.location.href)
      notice.value = 'Đã sao chép liên kết hóa đơn vào clipboard!'
      return true
    } catch {
      notice.value = 'Không thể sao chép liên kết.'
      return false
    }
  }

  return {
    loading,
    notice,
    error,
    invoice,
    occurrences,
    showPaymentModal,
    loadInvoice,
    printInvoice,
    copyInvoiceLink,
    paymentAttempt,
    refreshing,
    refreshStatus,
  }
}
