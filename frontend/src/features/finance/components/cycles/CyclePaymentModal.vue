<script setup lang="ts">
import { ref, computed } from 'vue'
import { AuraButton } from '@/shared/components'
import type { MonthlyInvoiceData } from '../../types/finance'

const props = defineProps<{
  show: boolean
  invoice: MonthlyInvoiceData | null
  paying?: boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'confirmPay'): void
}>()

const copiedField = ref<string | null>(null)

function formatOrderMemo(invoiceId: number | string): string {
  const num = parseInt(String(invoiceId).replace(/\D+/g, ''), 10) || 1
  const numStr = String(num)
  const padded = numStr.length < 3 ? numStr.padStart(3, '0') : numStr.slice(0, 11)
  return `ORD${padded}`
}

const transferMemo = computed(() => {
  if (!props.invoice) return 'ORD001'
  return formatOrderMemo(props.invoice.id)
})

function formatMoney(amount: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0)
}

function formatDate(dateStr: string): string {
  if (!dateStr) return ''
  try {
    const d = new Date(dateStr)
    return d.toLocaleDateString('vi-VN', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
    })
  } catch {
    return dateStr.slice(0, 10)
  }
}

async function copyToClipboard(text: string, fieldName: string) {
  try {
    await navigator.clipboard.writeText(text)
    copiedField.value = fieldName
    setTimeout(() => {
      if (copiedField.value === fieldName) {
        copiedField.value = null
      }
    }, 2000)
  } catch {
    // Fallback
  }
}
</script>

<template>
  <div v-if="show && invoice" class="modal-backdrop modal-payment" @click.self="emit('close')">
    <div class="modal-card modal-md">
      <!-- Modal Header -->
      <div class="modal-header">
        <div class="modal-title-wrap">
          <span class="material-symbols-outlined modal-icon">qr_code_2</span>
          <h3 class="modal-title">Thanh Toán Hóa Đơn Định Kỳ</h3>
        </div>
        <button type="button" class="modal-close-btn" @click="emit('close')" aria-label="Đóng">
          <span class="material-symbols-outlined close-icon">close</span>
        </button>
      </div>

      <!-- Modal Body -->
      <div class="modal-body payment-modal-body">
        <!-- QR Code Display Box -->
        <div class="qr-presentation-card">
          <div class="qr-box">
            <img
              :src="`https://api.vietqr.io/image/970423-83403032005-compact2.png?amount=${invoice.amount}&addInfo=${transferMemo}&accountName=AURA COURT`"
              alt="VietQR Monthly Invoice"
              loading="eager"
            />
          </div>
          <div class="qr-guidance">
            <span class="material-symbols-outlined qr-guide-icon">mobile_friendly</span>
            <p class="qr-guide-text">Mở App Ngân hàng hoặc Ví MoMo để quét mã VietQR tự động điền cú pháp <strong>{{ transferMemo }}</strong></p>
          </div>
        </div>

        <!-- Transfer Information Details -->
        <div class="invoice-summary-box">
          <div class="info-row">
            <span class="info-lbl">Ngân hàng thụ hưởng:</span>
            <strong class="info-val">MBBank (Ngân hàng Quân Đội)</strong>
          </div>

          <div class="info-row">
            <span class="info-lbl">Chủ tài khoản:</span>
            <strong class="info-val">AURA COURT - CONG TY THE THAO AURA</strong>
          </div>

          <div class="info-row info-row-copyable">
            <span class="info-lbl">Số tài khoản:</span>
            <div class="val-with-copy">
              <strong class="info-val font-mono">83403032005</strong>
              <button
                type="button"
                class="btn-copy-inline"
                @click="copyToClipboard('83403032005', 'account')"
                title="Sao chép số tài khoản"
              >
                <span class="material-symbols-outlined copy-icon">
                  {{ copiedField === 'account' ? 'check' : 'content_copy' }}
                </span>
                <span>{{ copiedField === 'account' ? 'Đã chép' : 'Chép' }}</span>
              </button>
            </div>
          </div>

          <div class="info-row info-row-copyable">
            <span class="info-lbl">Số tiền thanh toán:</span>
            <div class="val-with-copy">
              <strong class="info-val text-pink font-mono">{{ formatMoney(invoice.amount) }}</strong>
              <button
                type="button"
                class="btn-copy-inline"
                @click="copyToClipboard(String(invoice.amount), 'amount')"
                title="Sao chép số tiền"
              >
                <span class="material-symbols-outlined copy-icon">
                  {{ copiedField === 'amount' ? 'check' : 'content_copy' }}
                </span>
                <span>{{ copiedField === 'amount' ? 'Đã chép' : 'Chép' }}</span>
              </button>
            </div>
          </div>

          <div class="info-row info-row-copyable">
            <span class="info-lbl">Nội dung chuyển khoản:</span>
            <div class="val-with-copy">
              <strong class="info-val font-mono tag-memo">{{ transferMemo }}</strong>
              <button
                type="button"
                class="btn-copy-inline"
                @click="copyToClipboard(transferMemo, 'memo')"
                title="Sao chép nội dung chuyển khoản"
              >
                <span class="material-symbols-outlined copy-icon">
                  {{ copiedField === 'memo' ? 'check' : 'content_copy' }}
                </span>
                <span>{{ copiedField === 'memo' ? 'Đã chép' : 'Chép' }}</span>
              </button>
            </div>
          </div>

          <div class="info-row">
            <span class="info-lbl">Kỳ thanh toán:</span>
            <span class="info-val-sub">{{ formatDate(invoice.period_start) }} ➔ {{ formatDate(invoice.period_end) }}</span>
          </div>
        </div>
      </div>

      <!-- Modal Footer -->
      <div class="modal-footer">
        <AuraButton
          variant="outline"
          size="md"
          @click="emit('close')"
        >
          <span>Hủy Bỏ</span>
        </AuraButton>

        <AuraButton
          variant="primary"
          size="md"
          icon="verified"
          class="btn-confirm-pay"
          :loading="paying"
          :disabled="paying"
          @click="emit('confirmPay')"
        >
          <span>{{ paying ? 'Đang xử lý...' : '✓ Xác Nhận Đã Chuyển Khoản' }}</span>
        </AuraButton>
      </div>
    </div>
  </div>
</template>

<style scoped>
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.75);
  backdrop-filter: blur(8px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 10000;
  padding: 20px 16px;
  overflow-y: auto;
}

.modal-card {
  width: 100%;
  max-width: 480px;
  max-height: min(92vh, 760px);
  background: var(--surface);
  border-radius: 20px;
  border: 1px solid var(--outline);
  box-shadow: 0 24px 60px rgba(0, 0, 0, 0.4);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  margin: auto;
  animation: modalFadeIn 0.2s ease-out;
}

@keyframes modalFadeIn {
  from {
    opacity: 0;
    transform: scale(0.96) translateY(8px);
  }
  to {
    opacity: 1;
    transform: scale(1) translateY(0);
  }
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 20px;
  border-bottom: 1px solid var(--outline);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.03));
  flex-shrink: 0;
}

.modal-title-wrap {
  display: flex;
  align-items: center;
  gap: 8px;
}

.modal-icon {
  font-family: 'Material Symbols Outlined' !important;
  font-size: 22px;
  color: var(--pink-dark, #db2777);
  line-height: 1;
}

.modal-title {
  margin: 0;
  font-size: 16px;
  font-weight: 800;
  color: var(--ink);
}

.modal-close-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 30px;
  height: 30px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--muted);
  cursor: pointer;
  transition: all 0.15s;
}

.modal-close-btn:hover {
  color: var(--ink);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.05));
  border-color: var(--pink-dark, #db2777);
}

.close-icon {
  font-family: 'Material Symbols Outlined' !important;
  font-size: 18px;
  line-height: 1;
}

.payment-modal-body {
  padding: 16px 20px;
  display: flex;
  flex-direction: column;
  gap: 14px;
  overflow-y: auto;
}

.qr-presentation-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  padding: 12px;
  border-radius: 14px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  border: 1px solid var(--outline);
}

.qr-box {
  width: 190px;
  height: 190px;
  border-radius: 12px;
  border: 1px solid rgba(0, 0, 0, 0.08);
  background: #fff;
  padding: 6px;
  box-shadow: 0 4px 14px rgba(0, 0, 0, 0.06);
}

.qr-box img {
  width: 100%;
  height: 100%;
  object-fit: contain;
}

.qr-guidance {
  display: flex;
  align-items: center;
  gap: 6px;
  text-align: center;
  color: var(--muted);
}

.qr-guide-icon {
  font-family: 'Material Symbols Outlined' !important;
  font-size: 16px;
  color: var(--pink-dark, #db2777);
  flex-shrink: 0;
  line-height: 1;
}

.qr-guide-text {
  margin: 0;
  font-size: 12px;
  line-height: 1.4;
}

.qr-guide-text strong {
  color: var(--pink-dark, #db2777);
}

.invoice-summary-box {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 14px;
  border-radius: 14px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
  border: 1px solid var(--outline);
}

.info-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
  font-size: 12.5px;
}

.info-lbl {
  font-size: 12px;
  color: var(--muted);
}

.info-val {
  font-size: 12.5px;
  font-weight: 700;
  color: var(--ink);
}

.info-val-sub {
  font-size: 12px;
  font-weight: 600;
  color: var(--muted);
}

.text-pink {
  color: var(--pink-dark, #db2777);
  font-size: 14px;
}

.tag-memo {
  background: rgba(219, 39, 119, 0.12);
  color: var(--pink-dark, #db2777);
  border: 1px solid rgba(219, 39, 119, 0.25);
  padding: 2px 7px;
  border-radius: 6px;
  font-weight: 800;
  letter-spacing: 0.5px;
}

.val-with-copy {
  display: flex;
  align-items: center;
  gap: 6px;
}

.btn-copy-inline {
  display: inline-flex;
  align-items: center;
  gap: 3px;
  padding: 3px 8px;
  border-radius: 6px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--muted);
  font-size: 11px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s;
}

.btn-copy-inline:hover {
  color: var(--ink);
  border-color: var(--pink-dark, #db2777);
}

.copy-icon {
  font-family: 'Material Symbols Outlined' !important;
  font-size: 13px;
  line-height: 1;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  align-items: center;
  gap: 10px;
  padding: 14px 20px;
  border-top: 1px solid var(--outline);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.03));
  flex-shrink: 0;
}
</style>
