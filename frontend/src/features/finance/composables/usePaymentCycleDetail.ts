import { ref, computed } from 'vue'
import { apiV2 } from '@/core/api/client'
import { errorMessage } from '@/core/utils/errorMessage'

export interface MonthlyInvoiceData {
  id: number
  organization_id: number
  recurring_series_id: number
  period_start: string
  period_end: string
  due_at: string
  amount: number
  status: 'pending' | 'paid' | 'overdue'
  series?: {
    id: number
    court_id: number
    frequency: string
    status: string
    court?: {
      id: number
      name: string
      venue?: { id: number; name: string; address?: string }
    }
  }
}

export interface OccurrenceItem {
  id: number
  recurring_series_id: number
  sequence: number
  starts_at: string
  ends_at: string
  amount: number
  status: string
  court_name?: string
}

export function usePaymentCycleDetail(invoiceId: number | string) {
  const loading = ref(false)
  const paying = ref(false)
  const notice = ref('')
  const error = ref('')

  const invoice = ref<MonthlyInvoiceData | null>(null)
  const occurrences = ref<OccurrenceItem[]>([])
  const showPaymentModal = ref(false)

  async function loadInvoice() {
    loading.value = true
    try {
      const res = await apiV2.get<{ invoice: MonthlyInvoiceData; occurrences: OccurrenceItem[] }>(
        `/monthly-invoices/${invoiceId}`
      )
      if (res && res.invoice) {
        invoice.value = res.invoice
        occurrences.value = res.occurrences || []
      }
    } catch (e) {
      error.value = errorMessage(e, 'Không thể tải chi tiết kỳ thanh toán.')
    } finally {
      loading.value = false
    }
  }

  async function payInvoice() {
    if (!invoice.value) return
    paying.value = true
    try {
      await apiV2.post(`/monthly-invoices/${invoice.value.id}/pay`, {
        payment_reference: `vietqr-${invoice.value.id}-${Date.now()}`,
      })
      invoice.value.status = 'paid'
      showPaymentModal.value = false
      notice.value = 'Thanh toán kỳ hóa đơn thành công!'
    } catch (e) {
      error.value = errorMessage(e, 'Thanh toán thất bại.')
    } finally {
      paying.value = false
    }
  }

  return {
    loading,
    paying,
    notice,
    error,
    invoice,
    occurrences,
    showPaymentModal,
    loadInvoice,
    payInvoice,
  }
}
