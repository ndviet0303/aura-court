import { ref } from 'vue'
import { apiV2 } from '@/core/api/client'
import { errorMessage } from '@/core/utils/errorMessage'
import type { MonthlyInvoiceData, OccurrenceItem } from '../types/finance'

export type { MonthlyInvoiceData, OccurrenceItem }

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
    error.value = ''
    try {
      const res = await apiV2.get<{ invoice: MonthlyInvoiceData; occurrences: OccurrenceItem[] }>(
        `/monthly-invoices/${invoiceId}`
      )
      if (res && res.invoice) {
        invoice.value = res.invoice
        occurrences.value = res.occurrences || []
      } else {
        error.value = 'Không tìm thấy thông tin kỳ thanh toán.'
      }
    } catch (e) {
      error.value = errorMessage(e, 'Không thể tải chi tiết kỳ thanh toán.')
    } finally {
      loading.value = false
    }
  }

  function formatOrderMemo(id: number | string): string {
    const cleaned = String(id).replace(/\D+/g, '')
    let num = 1
    if (cleaned.length >= 3) {
      num = parseInt(cleaned.slice(-8), 10) || 1
    } else if (cleaned.length > 0) {
      num = parseInt(cleaned, 10) || 1
    } else {
      num = (parseInt(String(id).replace(/[^0-9a-fA-F]/g, '').slice(-4), 16) % 900000) + 1000
    }
    const numStr = String(num)
    const padded = numStr.length < 3 ? numStr.padStart(3, '0') : numStr.slice(0, 11)
    return `ORD${padded}`
  }

  async function payInvoice() {
    if (!invoice.value) return
    paying.value = true
    error.value = ''
    try {
      const key = invoice.value.uuid || invoice.value.id
      const memo = formatOrderMemo(key)
      await apiV2.post(`/monthly-invoices/${key}/pay`, {
        payment_reference: `vietqr-${invoice.value.id}-${memo}-${Date.now()}`,
      })
      invoice.value.status = 'paid'
      invoice.value.payment_reference = memo
      showPaymentModal.value = false
      notice.value = `Thanh toán kỳ hóa đơn #${memo} thành công qua VietQR!`
    } catch (e) {
      error.value = errorMessage(e, 'Thanh toán thất bại. Vui lòng thử lại sau.')
    } finally {
      paying.value = false
    }
  }

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
    paying,
    notice,
    error,
    invoice,
    occurrences,
    showPaymentModal,
    loadInvoice,
    payInvoice,
    printInvoice,
    copyInvoiceLink,
  }
}
