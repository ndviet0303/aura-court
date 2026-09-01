<script setup lang="ts">
import { AuraButton } from '@/shared/components'
import type { SplitMember } from '../../types/split'

defineProps<{
  show: boolean
  remainingAmount: number
  unpaidMembers: SplitMember[]
  processing: boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'confirm'): void
}>()

function formatMoney(amount: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0)
}
</script>

<template>
  <div v-if="show" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card">
      <div class="modal-header">
        <div class="header-title-wrap">
          <span class="material-symbols-outlined header-icon text-accent">verified_user</span>
          <h3 class="modal-title">Bảo Lãnh Thanh Toán Phần Tiền Còn Thiếu</h3>
        </div>
        <button class="modal-close-btn" @click="emit('close')">
          <span class="material-symbols-outlined">close</span>
        </button>
      </div>

      <div class="modal-body">
        <div class="shortfall-hero">
          <span class="hero-lbl">Số tiền cần bảo lãnh thanh toán:</span>
          <strong class="hero-amount font-mono">{{ formatMoney(remainingAmount) }}</strong>
          <p class="hero-desc">
            Chủ phòng sẽ thanh toán trước phần tiền này qua Ví Aura để đảm bảo giữ sân và không bị hủy đơn. Bạn có thể thu lại sau từ các bạn bè.
          </p>
        </div>

        <div class="unpaid-list-box">
          <span class="unpaid-title">Danh sách {{ unpaidMembers.length }} thành viên chưa hoàn tất:</span>
          <div class="unpaid-rows">
            <div v-for="m in unpaidMembers" :key="m.id" class="unpaid-row">
              <div class="u-user">
                <span class="u-dot"></span>
                <span class="u-name">{{ m.name }}</span>
              </div>
              <strong class="u-cost font-mono">{{ formatMoney(m.amount) }}</strong>
            </div>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <AuraButton variant="outline" size="md" @click="emit('close')">
          <span>Hủy</span>
        </AuraButton>
        <AuraButton
          variant="gold"
          size="md"
          icon="check_circle"
          :loading="processing"
          @click="emit('confirm')"
        >
          <span>Xác Nhận Bảo Lãnh ({{ formatMoney(remainingAmount) }})</span>
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
  max-width: 480px;
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

.text-accent {
  color: var(--accent, #C9A15A);
}

.header-icon {
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
}

.modal-body {
  padding: 18px 20px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.shortfall-hero {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  padding: 16px;
  border-radius: 14px;
  background: rgba(201, 161, 90, 0.08);
  border: 1px solid rgba(201, 161, 90, 0.25);
  gap: 4px;
}

.hero-lbl {
  font-size: 12px;
  color: var(--muted, #9e939a);
}

.hero-amount {
  font-size: 24px;
  font-weight: 850;
  color: var(--accent, #C9A15A);
}

.hero-desc {
  margin: 6px 0 0;
  font-size: 12px;
  color: var(--on-surface, #FAF7F3);
  line-height: 1.4;
}

.unpaid-list-box {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 12px 14px;
  border-radius: 12px;
  background: var(--surface-container, rgba(0, 0, 0, 0.2));
  border: 1px solid var(--border, var(--outline, #382f35));
}

.unpaid-title {
  font-size: 11.5px;
  font-weight: 700;
  color: var(--muted, #9e939a);
  text-transform: uppercase;
}

.unpaid-rows {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.unpaid-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 13px;
}

.u-user {
  display: flex;
  align-items: center;
  gap: 6px;
}

.u-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #f59e0b;
}

.u-name {
  color: var(--on-surface, #FAF7F3);
}

.u-cost {
  color: var(--primary, #E58E9F);
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding: 14px 20px;
  border-top: 1px solid var(--border, rgba(255, 255, 255, 0.08));
}
</style>
