<script setup lang="ts">
import { AuraButton } from '@/shared/components'
import OwnerPaymentInstructionsPanel from '@/features/booking/components/OwnerPaymentInstructionsPanel.vue'
import type { MonthlyInvoiceData } from '../../types/finance'
import type { ReservationPaymentAttempt } from '@/features/booking/api/bookingApi'

defineProps<{
  show: boolean
  invoice: MonthlyInvoiceData | null
  paymentAttempt?: ReservationPaymentAttempt | null
  refreshing?: boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'refresh'): void
}>()
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
        <OwnerPaymentInstructionsPanel
          v-if="paymentAttempt && paymentAttempt.status !== 'expired'"
          :attempt="paymentAttempt"
          :reservation-status="invoice.status === 'paid' ? 'confirmed' : 'held'"
          :loading="false"
          :refreshing="!!refreshing"
          :error="''"
          @refresh="emit('refresh')"
        />
        <div v-else class="unavailable-instructions" role="status">
          <p v-if="invoice.status === 'guaranteed'">
            Lượt chơi được bảo lãnh theo chính sách; kỳ hóa đơn chưa được xác minh đã thanh toán.
            Vui lòng liên hệ chủ sân để đối soát.
          </p>
          <p v-else-if="invoice.status === 'zero_payable'">
            Kỳ này không phát sinh tiền phải chuyển; không có hướng dẫn chuyển khoản.
          </p>
          <p v-else>
            Chưa có hướng dẫn chuyển khoản hợp lệ cho kỳ này. Không chuyển tiền theo số tài khoản
            khác; hãy cập nhật trạng thái hoặc liên hệ chủ sân.
          </p>
        </div>
      </div>

      <!-- Modal Footer -->
      <div class="modal-footer">
        <AuraButton variant="outline" size="md" @click="emit('close')">
          <span>Đóng</span>
        </AuraButton>
        <AuraButton
          v-if="!paymentAttempt || paymentAttempt.status === 'expired'"
          variant="outline"
          size="md"
          :disabled="refreshing"
          @click="emit('refresh')"
        >
          <span>{{ refreshing ? 'Đang cập nhật...' : 'Cập nhật trạng thái' }}</span>
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

.unavailable-instructions {
  border: 1px solid var(--outline);
  border-radius: 12px;
  padding: 16px;
  color: var(--muted);
  font-size: 13px;
  line-height: 1.5;
}

.unavailable-instructions p {
  margin: 0;
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
