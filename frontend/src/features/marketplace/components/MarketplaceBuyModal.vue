<script setup lang="ts">
import type { ResaleListing } from '../types/marketplace'
import { AuraButton } from '@/shared/components'

defineProps<{
  show: boolean
  listing: ResaleListing | null
  paymentMethod: 'wallet' | 'transfer'
  processing?: boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'update:paymentMethod', method: 'wallet' | 'transfer'): void
  (e: 'confirm'): void
}>()

function formatMoney(amount: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0)
}
</script>

<template>
  <div v-if="show && listing" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card modal-md">
      <!-- Modal Header -->
      <div class="modal-header">
        <div class="modal-title-wrap">
          <span class="material-symbols-outlined modal-icon">verified_user</span>
          <h3 class="modal-title">Xác Nhận Mua Lại Suất Chơi</h3>
        </div>
        <button type="button" class="modal-close-btn" @click="emit('close')">
          <span class="material-symbols-outlined">close</span>
        </button>
      </div>

      <!-- Modal Body -->
      <div class="modal-body">
        <!-- Target Court Summary -->
        <div class="target-card">
          <div class="target-head">
            <strong class="target-court">{{ listing.courtName }}</strong>
            <span class="badge-escrow">
              <span class="material-symbols-outlined icon-14">shield</span>
              <span>Aura Escrow Bảo Vệ</span>
            </span>
          </div>
          <div class="target-details">
            <span class="detail-item">
              <span class="material-symbols-outlined icon-14">location_on</span>
              <span>{{ listing.venueName }}</span>
            </span>
            <span class="detail-item">
              <span class="material-symbols-outlined icon-14">calendar_today</span>
              <span>{{ listing.dateStr }}</span>
            </span>
            <span class="detail-item highlight">
              <span class="material-symbols-outlined icon-14">schedule</span>
              <span class="font-mono">{{ listing.timeSlot }}</span>
            </span>
          </div>
        </div>

        <!-- Payment Method Selection -->
        <div class="payment-method-block">
          <label class="block-label">Phương thức thanh toán:</label>
          <div class="methods-grid">
            <label class="method-option" :class="{ selected: paymentMethod === 'wallet' }">
              <input
                type="radio"
                name="resale_payment"
                value="wallet"
                :checked="paymentMethod === 'wallet'"
                @change="emit('update:paymentMethod', 'wallet')"
              />
              <span class="material-symbols-outlined method-icon">account_balance_wallet</span>
              <div class="method-text">
                <span class="method-name">Ví Aura Club</span>
                <span class="method-sub">Khấu trừ số dư ví tức thì</span>
              </div>
            </label>

            <label class="method-option" :class="{ selected: paymentMethod === 'transfer' }">
              <input
                type="radio"
                name="resale_payment"
                value="transfer"
                :checked="paymentMethod === 'transfer'"
                @change="emit('update:paymentMethod', 'transfer')"
              />
              <span class="material-symbols-outlined method-icon">qr_code_2</span>
              <div class="method-text">
                <span class="method-name">VietQR / Chuyển khoản</span>
                <span class="method-sub">Quét mã ngân hàng 24/7</span>
              </div>
            </label>
          </div>
        </div>

        <!-- Price Breakdown -->
        <div class="price-breakdown">
          <div class="p-row">
            <span>Giá gốc lịch sân:</span>
            <span class="orig-price-strike">{{ formatMoney(listing.originalPrice) }}</span>
          </div>
          <div class="p-row">
            <span>Giá chuyển nhượng:</span>
            <strong class="price-main font-mono">{{ formatMoney(listing.resalePrice) }}</strong>
          </div>
          <div class="p-row">
            <span>Phí trung gian Escrow:</span>
            <span class="text-green">0 ₫ (Miễn phí)</span>
          </div>
          <div class="p-row total-row">
            <strong>Tổng thanh toán:</strong>
            <strong class="total-price font-mono">{{ formatMoney(listing.resalePrice) }}</strong>
          </div>
        </div>

        <!-- Escrow Protection Guarantee -->
        <div class="escrow-notice-box">
          <span class="material-symbols-outlined shield-ico">verified</span>
          <div class="notice-txt">
            <strong>Cam kết hoàn tiền 100%:</strong>
            <span>
              Tiền sẽ được giữ an toàn trên hệ thống Aura Escrow và chỉ giải ngân sau khi bạn hoàn
              tất buổi chơi thành công.</span
            >
          </div>
        </div>
      </div>

      <!-- Modal Footer -->
      <div class="modal-footer">
        <AuraButton variant="secondary" size="md" @click="emit('close')"> Đóng </AuraButton>
        <AuraButton
          variant="primary"
          size="md"
          :loading="processing"
          icon="verified"
          @click="emit('confirm')"
        >
          Thanh Toán {{ formatMoney(listing.resalePrice) }}
        </AuraButton>
      </div>
    </div>
  </div>
</template>

<style scoped>
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(15, 12, 14, 0.7);
  backdrop-filter: blur(8px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 16px;
}

.modal-card {
  width: 100%;
  max-width: 520px;
  background: var(--surface);
  border: 1px solid var(--outline);
  border-radius: 24px;
  box-shadow: 0 20px 50px rgba(0, 0, 0, 0.25);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  animation: modalIn 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes modalIn {
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
  align-items: center;
  justify-content: space-between;
  padding: 20px 24px;
  border-bottom: 1px solid var(--outline);
}

.modal-title-wrap {
  display: flex;
  align-items: center;
  gap: 8px;
}

.modal-icon {
  font-size: 22px;
  color: var(--accent, #c9a15a);
}

.modal-title {
  margin: 0;
  font-size: 17px;
  font-weight: 800;
  color: var(--ink);
}

.modal-close-btn {
  background: transparent;
  border: 0;
  color: var(--muted);
  cursor: pointer;
  padding: 4px;
  display: flex;
  align-items: center;
  border-radius: 8px;
  transition: all 0.15s ease;
}

.modal-close-btn:hover {
  background: var(--surface-subtle, rgba(0, 0, 0, 0.05));
  color: var(--ink);
}

.modal-body {
  padding: 20px 24px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.target-card {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 14px 16px;
  border-radius: 16px;
  background: var(--surface-subtle, rgba(0, 0, 0, 0.02));
  border: 1px solid var(--outline);
}

.target-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.target-court {
  font-size: 15.5px;
  color: var(--ink);
}

.badge-escrow {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 11px;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 6px;
  background: rgba(16, 185, 129, 0.1);
  color: #10b981;
}

.target-details {
  display: flex;
  align-items: center;
  gap: 14px;
  flex-wrap: wrap;
  font-size: 12px;
  color: var(--muted);
}

.detail-item {
  display: inline-flex;
  align-items: center;
  gap: 4px;
}

.detail-item.highlight {
  color: var(--primary, #6b2d3f);
  font-weight: 700;
}

html.dark .detail-item.highlight {
  color: var(--accent, #c9a15a);
}

.icon-14 {
  font-size: 14px;
}

.payment-method-block {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.block-label {
  font-size: 12.5px;
  font-weight: 700;
  color: var(--ink);
}

.methods-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}

.method-option {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 12px;
  border-radius: 12px;
  border: 1px solid var(--outline);
  background: var(--surface);
  cursor: pointer;
  transition: all 0.2s ease;
}

.method-option input {
  margin-top: 3px;
}

.method-option.selected {
  border-color: var(--primary, #6b2d3f);
  background: rgba(107, 45, 63, 0.04);
}

html.dark .method-option.selected {
  border-color: var(--accent, #c9a15a);
  background: rgba(201, 161, 90, 0.06);
}

.method-icon {
  font-size: 20px;
  color: var(--accent, #c9a15a);
}

.method-text {
  display: flex;
  flex-direction: column;
}

.method-name {
  font-size: 12.5px;
  font-weight: 700;
  color: var(--ink);
}

.method-sub {
  font-size: 10.5px;
  color: var(--muted);
}

.price-breakdown {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 14px;
  border-radius: 14px;
  background: var(--surface-subtle, rgba(0, 0, 0, 0.02));
  border: 1px solid var(--outline);
  font-size: 13px;
}

.p-row {
  display: flex;
  justify-content: space-between;
  color: var(--muted);
}

.orig-price-strike {
  text-decoration: line-through;
}

.price-main {
  color: var(--ink);
  font-size: 14px;
}

.text-green {
  color: #10b981;
  font-weight: 700;
}

.total-row {
  padding-top: 8px;
  border-top: 1px solid var(--outline);
  color: var(--ink);
  font-size: 14px;
}

.total-price {
  font-size: 18px;
  color: var(--primary, #6b2d3f);
}

html.dark .total-price {
  color: var(--accent, #c9a15a);
}

.escrow-notice-box {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 12px 14px;
  border-radius: 12px;
  background: rgba(16, 185, 129, 0.06);
  border: 1px solid rgba(16, 185, 129, 0.2);
}

.shield-ico {
  font-size: 20px;
  color: #10b981;
}

.notice-txt {
  font-size: 11.5px;
  color: var(--muted);
  line-height: 1.5;
}

.notice-txt strong {
  color: #10b981;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  padding: 16px 24px;
  border-top: 1px solid var(--outline);
  background: var(--surface-subtle, rgba(0, 0, 0, 0.01));
}

.btn-cancel {
  padding: 10px 18px;
  border-radius: 12px;
  border: 1px solid var(--outline);
  background: transparent;
  color: var(--ink);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}

.btn-primary {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 10px 22px;
  border-radius: 12px;
  border: 0;
  background: var(--primary, #6b2d3f);
  color: #ffffff;
  font-size: 13.5px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-primary:hover:not(:disabled) {
  background: var(--primary-hover, #562332);
}

.spin-icon {
  animation: spin 1s linear infinite;
  font-size: 18px;
}

@keyframes spin {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}
</style>
