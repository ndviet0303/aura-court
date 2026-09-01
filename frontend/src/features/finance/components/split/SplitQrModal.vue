<script setup lang="ts">
import { ref } from 'vue'
import { AuraButton } from '@/shared/components'
import type { SplitMember, SplitBillBookingInfo } from '../../types/split'

const props = defineProps<{
  show: boolean
  member: SplitMember | null
  booking: SplitBillBookingInfo
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'confirmPaid', memberId: number): void
}>()

const copiedField = ref('')

function formatMoney(amount: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0)
}

function copyText(val: string, field: string) {
  navigator.clipboard?.writeText(val)
  copiedField.value = field
  setTimeout(() => {
    if (copiedField.value === field) copiedField.value = ''
  }, 2500)
}
function getTransferMemo(code: string, name: string): string {
  const normalizedCode = /^ORD/i.test(code) ? code.toUpperCase() : (/^\d+$/.test(code) ? `ORD${code}` : code)
  return `${normalizedCode} ${name}`.trim()
}
</script>

<template>
  <div v-if="show && member" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card">
      <div class="modal-header">
        <div class="header-title-wrap">
          <span class="material-symbols-outlined header-icon">qr_code_2</span>
          <h3 class="modal-title">Mã VietQR Thanh Toán Cho {{ member.name }}</h3>
        </div>
        <button class="modal-close-btn" @click="emit('close')">
          <span class="material-symbols-outlined">close</span>
        </button>
      </div>

      <div class="modal-body qr-modal-body">
        <div class="member-target-info">
          <div class="target-user">
            <span class="m-avatar">{{ member.name.charAt(0) }}</span>
            <div class="m-meta">
              <strong>{{ member.name }}</strong>
              <span>{{ member.phone }}</span>
            </div>
          </div>
          <div class="target-price">
            <span class="p-lbl">Số tiền cần chuyển:</span>
            <strong class="target-amount font-mono">{{ formatMoney(member.amount) }}</strong>
          </div>
        </div>

        <div class="qr-code-box">
          <img
            :src="`https://api.vietqr.io/image/${booking.bankBin}-${booking.accountNumber}-compact2.png?amount=${member.amount}&addInfo=${encodeURIComponent(getTransferMemo(booking.bookingCode, member.name))}&accountName=${encodeURIComponent(booking.accountName)}`"
            alt="VietQR Split Bill"
          />
        </div>

        <div class="bank-details-box">
          <div class="b-row">
            <span class="b-lbl">Ngân hàng thụ hưởng:</span>
            <strong class="b-val">{{ booking.bankName }}</strong>
          </div>

          <div class="b-row">
            <span class="b-lbl">Số tài khoản:</span>
            <div class="copyable-val">
              <strong class="b-val font-mono">{{ booking.accountNumber }}</strong>
              <button class="btn-copy-chip" @click="copyText(booking.accountNumber, 'acc')">
                {{ copiedField === 'acc' ? '✓ Đã chép' : 'Sao chép' }}
              </button>
            </div>
          </div>

          <div class="b-row">
            <span class="b-lbl">Chủ tài khoản:</span>
            <strong class="b-val">{{ booking.accountName }}</strong>
          </div>

          <div class="b-row">
            <span class="b-lbl">Nội dung chuyển khoản:</span>
            <div class="copyable-val">
              <strong class="b-val font-mono text-primary">{{ getTransferMemo(booking.bookingCode, member.name) }}</strong>
              <button
                class="btn-copy-chip"
                @click="copyText(getTransferMemo(booking.bookingCode, member.name), 'memo')"
              >
                {{ copiedField === 'memo' ? '✓ Đã chép' : 'Sao chép' }}
              </button>
            </div>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <AuraButton variant="outline" size="md" @click="emit('close')">
          <span>Đóng</span>
        </AuraButton>
        <AuraButton
          variant="primary"
          size="md"
          icon="check_circle"
          @click="emit('confirmPaid', member.id); emit('close')"
        >
          <span>Xác Nhận Đã Nhận Tiền</span>
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
  backdrop-filter: blur(6px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
  padding: 16px;
}

.modal-card {
  width: 100%;
  max-width: 460px;
  background: var(--surface, #1e191c);
  border-radius: 18px;
  border: 1px solid var(--border, var(--outline, #382f35));
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 20px;
  border-bottom: 1px solid var(--border, rgba(255, 255, 255, 0.08));
}

.header-title-wrap {
  display: flex;
  align-items: center;
  gap: 8px;
}

.header-icon {
  color: var(--primary, #E58E9F);
  font-size: 22px;
}

.modal-title {
  margin: 0;
  font-size: 15px;
  font-weight: 750;
  color: var(--on-surface, #FAF7F3);
}

.modal-close-btn {
  background: transparent;
  border: 0;
  color: var(--muted, #9e939a);
  cursor: pointer;
  display: flex;
  align-items: center;
}

.modal-close-btn:hover {
  color: var(--on-surface, #FAF7F3);
}

.qr-modal-body {
  padding: 18px 20px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 16px;
}

.member-target-info {
  width: 100%;
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 14px;
  border-radius: 12px;
  background: var(--surface-container, rgba(255, 255, 255, 0.03));
  border: 1px solid var(--border, var(--outline, #382f35));
  box-sizing: border-box;
}

.target-user {
  display: flex;
  align-items: center;
  gap: 8px;
}

.m-avatar {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: var(--primary, #E58E9F);
  color: #1c0a10;
  font-weight: 800;
  display: flex;
  align-items: center;
  justify-content: center;
}

.m-meta {
  display: flex;
  flex-direction: column;
  font-size: 12px;
}

.m-meta span {
  font-size: 10.5px;
  color: var(--muted, #9e939a);
}

.target-price {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
}

.p-lbl {
  font-size: 10.5px;
  color: var(--muted, #9e939a);
}

.target-amount {
  font-size: 16px;
  font-weight: 800;
  color: var(--primary, #E58E9F);
}

.qr-code-box {
  width: 220px;
  height: 220px;
  border-radius: 14px;
  background: #ffffff;
  padding: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
}

.qr-code-box img {
  width: 100%;
  height: 100%;
  object-fit: contain;
}

.bank-details-box {
  width: 100%;
  display: flex;
  flex-direction: column;
  gap: 8px;
  font-size: 12.5px;
  padding: 12px 14px;
  border-radius: 12px;
  background: var(--surface-container, rgba(0, 0, 0, 0.2));
  border: 1px solid var(--border, var(--outline, #382f35));
  box-sizing: border-box;
}

.b-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
}

.b-lbl {
  color: var(--muted, #9e939a);
}

.b-val {
  color: var(--on-surface, #FAF7F3);
}

.text-primary {
  color: var(--primary, #E58E9F);
}

.copyable-val {
  display: flex;
  align-items: center;
  gap: 6px;
}

.btn-copy-chip {
  padding: 2px 7px;
  border-radius: 4px;
  border: 1px solid var(--border, var(--outline, #382f35));
  background: rgba(255, 255, 255, 0.05);
  color: var(--muted, #9e939a);
  font-size: 10.5px;
  cursor: pointer;
}

.btn-copy-chip:hover {
  color: var(--primary, #E58E9F);
  border-color: var(--primary, #E58E9F);
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding: 14px 20px;
  border-top: 1px solid var(--border, rgba(255, 255, 255, 0.08));
}
</style>
