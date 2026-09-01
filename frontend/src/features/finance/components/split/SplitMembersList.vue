<script setup lang="ts">
import { AuraButton } from '@/shared/components'
import type { SplitMember, SplitMode, SplitItemDetail } from '../../types/split'

defineProps<{
  members: SplitMember[]
  mode: SplitMode
  items: SplitItemDetail[]
  allocationDiff: number
}>()

const emit = defineEmits<{
  (e: 'openQr', member: SplitMember): void
  (e: 'markPaid', memberId: number): void
  (e: 'removeMember', memberId: number): void
  (e: 'updateCustomAmount', memberId: number, val: number): void
  (e: 'updateMinutes', memberId: number, val: number): void
  (e: 'addMember'): void
}>()

function formatMoney(amount: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0)
}
</script>

<template>
  <div class="split-members-list">
    <div class="members-header">
      <div class="header-titles">
        <div class="title-wrap">
          <span class="material-symbols-outlined header-ico">group</span>
          <h3 class="title">Danh Sách Thành Viên Tham Gia ({{ members.length }})</h3>
        </div>
        <p class="sub">Từng người có thể quét mã VietQR riêng hoặc chủ phòng xác nhận khi nhận tiền mặt.</p>
      </div>

      <AuraButton
        variant="secondary"
        size="sm"
        icon="person_add"
        @click="emit('addMember')"
      >
        <span>Thêm Bạn Chơi</span>
      </AuraButton>
    </div>

    <!-- Allocation Difference Alert in Custom Mode -->
    <div v-if="mode === 'custom' && allocationDiff !== 0" class="custom-diff-alert">
      <span class="material-symbols-outlined alert-ico">warning</span>
      <span v-if="allocationDiff > 0">
        Tổng số tiền đã nhập còn thiếu <strong>{{ formatMoney(allocationDiff) }}</strong> so với hóa đơn chung.
      </span>
      <span v-else>
        Tổng số tiền đã nhập vượt quá <strong>{{ formatMoney(Math.abs(allocationDiff)) }}</strong> so với hóa đơn chung.
      </span>
    </div>

    <div class="members-grid">
      <div
        v-for="m in members"
        :key="m.id"
        class="member-split-card"
        :class="{ 'is-paid': m.paid }"
      >
        <div class="m-top-row">
          <div class="m-user">
            <div class="avatar-circle">
              <img v-if="m.avatar" :src="m.avatar" :alt="m.name" />
              <span v-else>{{ m.name.charAt(0) }}</span>
            </div>
            <div class="m-names">
              <div class="name-line">
                <strong class="name">{{ m.name }}</strong>
                <span class="role-tag" :class="{ 'role-tag--host': m.role === 'host' }">
                  {{ m.role === 'host' ? '👑 Chủ phòng' : 'Thành viên' }}
                </span>
              </div>
              <span class="phone-sub">{{ m.phone }}</span>
            </div>
          </div>

          <button
            v-if="m.role !== 'host'"
            type="button"
            class="btn-del-member"
            title="Xóa thành viên"
            @click="emit('removeMember', m.id)"
          >
            <span class="material-symbols-outlined del-icon">close</span>
          </button>
        </div>

        <!-- Mode-Specific Input / Breakdown -->
        <div v-if="mode === 'time'" class="m-mode-control">
          <span class="control-label">Thời gian đánh:</span>
          <div class="time-chips">
            <button
              v-for="mins in [30, 60, 90, 120]"
              :key="mins"
              type="button"
              class="time-chip"
              :class="{ active: (m.timeMinutes || 120) === mins }"
              @click="emit('updateMinutes', m.id, mins)"
            >
              {{ mins }}p
            </button>
          </div>
        </div>

        <div v-else-if="mode === 'custom'" class="m-mode-control">
          <span class="control-label">Số tiền đóng:</span>
          <div class="custom-input-wrap">
            <input
              type="number"
              step="10000"
              min="0"
              class="custom-amount-input font-mono"
              :value="m.amount"
              @input="emit('updateCustomAmount', m.id, Number(($event.target as HTMLInputElement).value))"
            />
            <span class="input-unit">đ</span>
          </div>
        </div>

        <div class="m-amount-row">
          <span class="amount-lbl">Phần tiền cần đóng:</span>
          <strong class="m-amount font-mono">{{ formatMoney(m.amount) }}</strong>
        </div>

        <div class="m-status-row">
          <div class="status-indicator">
            <span v-if="m.paid" class="status-paid">
              <span class="material-symbols-outlined st-ico">check_circle</span>
              <span>Đã đóng ({{ m.method || 'VietQR' }} · {{ m.paidAt || '19:00' }})</span>
            </span>
            <span v-else class="status-unpaid">
              <span class="material-symbols-outlined st-ico">schedule</span>
              <span>Chưa thanh toán</span>
            </span>
          </div>

          <div v-if="!m.paid" class="m-actions">
            <AuraButton
              variant="outline"
              size="sm"
              icon="qr_code_2"
              @click="emit('openQr', m)"
            >
              <span>VietQR</span>
            </AuraButton>
            <AuraButton
              variant="secondary"
              size="sm"
              icon="check"
              @click="emit('markPaid', m.id)"
            >
              <span>Đã Nhận</span>
            </AuraButton>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.split-members-list {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.members-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
}

.title-wrap {
  display: flex;
  align-items: center;
  gap: 8px;
}

.header-ico {
  font-size: 20px;
  color: var(--primary, #E58E9F);
}

.title {
  margin: 0;
  font-size: 16px;
  font-weight: 800;
  color: var(--on-surface, #FAF7F3);
}

.sub {
  margin: 2px 0 0;
  font-size: 12px;
  color: var(--muted, #9e939a);
}

.custom-diff-alert {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 14px;
  border-radius: 10px;
  background: rgba(245, 158, 11, 0.12);
  border: 1px solid rgba(245, 158, 11, 0.3);
  color: #fbbf24;
  font-size: 12.5px;
}

.alert-ico {
  font-size: 18px;
}

.members-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(290px, 1fr));
  gap: 14px;
}

.member-split-card {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 16px;
  border-radius: 14px;
  border: 1px solid var(--border, var(--outline, #382f35));
  background: var(--surface, #1e191c);
  transition: all 0.2s;
}

.member-split-card.is-paid {
  border-color: rgba(16, 185, 129, 0.35);
  background: rgba(16, 185, 129, 0.03);
}

.m-top-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.m-user {
  display: flex;
  align-items: center;
  gap: 10px;
}

.avatar-circle {
  width: 38px;
  height: 38px;
  border-radius: 50%;
  overflow: hidden;
  background: var(--surface-container, rgba(229, 142, 159, 0.12));
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 800;
  color: var(--primary, #E58E9F);
  font-size: 14px;
  border: 1px solid var(--border, transparent);
}

.avatar-circle img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.m-names {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.name-line {
  display: flex;
  align-items: center;
  gap: 6px;
}

.name {
  font-size: 14px;
  font-weight: 750;
  color: var(--on-surface, #FAF7F3);
}

.role-tag {
  font-size: 10px;
  font-weight: 700;
  padding: 2px 6px;
  border-radius: 4px;
  background: rgba(255, 255, 255, 0.08);
  color: var(--muted, #9e939a);
}

.role-tag--host {
  background: rgba(201, 161, 90, 0.15);
  color: var(--accent, #C9A15A);
  border: 1px solid rgba(201, 161, 90, 0.3);
}

.phone-sub {
  font-size: 11px;
  color: var(--muted, #9e939a);
}

.btn-del-member {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 26px;
  height: 26px;
  border-radius: 6px;
  border: 0;
  background: rgba(255, 255, 255, 0.05);
  color: var(--muted, #9e939a);
  cursor: pointer;
}

.btn-del-member:hover {
  background: rgba(239, 68, 68, 0.15);
  color: #ef4444;
}

.del-icon {
  font-size: 16px;
}

.m-mode-control {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 6px 10px;
  border-radius: 8px;
  background: var(--surface-container, rgba(0, 0, 0, 0.2));
  font-size: 11.5px;
}

.control-label {
  color: var(--muted, #9e939a);
}

.time-chips {
  display: flex;
  gap: 4px;
}

.time-chip {
  padding: 2px 7px;
  border-radius: 4px;
  border: 1px solid var(--border, var(--outline, #382f35));
  background: var(--surface, #1e191c);
  color: var(--muted, #9e939a);
  font-size: 11px;
  cursor: pointer;
}

.time-chip.active {
  border-color: var(--primary, #E58E9F);
  background: var(--primary, #E58E9F);
  color: #1c0a10;
  font-weight: 700;
}

.custom-input-wrap {
  display: flex;
  align-items: center;
  gap: 4px;
}

.custom-amount-input {
  width: 90px;
  padding: 3px 6px;
  border-radius: 6px;
  border: 1px solid var(--border, var(--outline, #382f35));
  background: var(--surface, #1e191c);
  color: var(--on-surface, #FAF7F3);
  font-size: 12px;
  text-align: right;
}

.input-unit {
  font-size: 12px;
  color: var(--muted, #9e939a);
}

.m-amount-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-top: 4px;
}

.amount-lbl {
  font-size: 12px;
  color: var(--muted, #9e939a);
}

.m-amount {
  font-size: 16px;
  font-weight: 800;
  color: var(--primary, #E58E9F);
}

.m-status-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-top: 10px;
  border-top: 1px solid var(--border, rgba(255, 255, 255, 0.06));
  flex-wrap: wrap;
  gap: 8px;
}

.status-indicator {
  font-size: 12px;
}

.status-paid {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  color: #10b981;
  font-weight: 700;
}

.status-unpaid {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  color: #f59e0b;
  font-weight: 700;
}

.st-ico {
  font-size: 15px;
}

.m-actions {
  display: flex;
  gap: 6px;
}
</style>
