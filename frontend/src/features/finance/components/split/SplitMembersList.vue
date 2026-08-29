<script setup lang="ts">
import type { SplitMember } from '../../composables/useGroupSplitBill'

defineProps<{
  members: SplitMember[]
}>()

const emit = defineEmits<{
  (e: 'openQr', member: SplitMember): void
  (e: 'markPaid', memberId: number): void
}>()

function formatMoney(amount: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0)
}
</script>

<template>
  <div class="split-members-list">
    <div class="members-header">
      <h3 class="title">Danh Sách Thành Viên Tham Gia ({{ members.length }})</h3>
      <span class="sub">Quét mã QR từng người hoặc thanh toán tự động qua ví</span>
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
              <strong class="name">{{ m.name }}</strong>
              <span class="role-tag">{{ m.role === 'host' ? '👑 Chủ đơn' : 'Thành viên' }}</span>
            </div>
          </div>

          <strong class="m-amount text-pink">{{ formatMoney(m.amount) }}</strong>
        </div>

        <div class="m-status-row">
          <span v-if="m.paid" class="status-paid">
            ✓ Đã đóng ({{ m.method }} · {{ m.paidAt }})
          </span>
          <span v-else class="status-unpaid">
            ⏳ Chưa thanh toán
          </span>

          <div class="m-actions">
            <button
              v-if="!m.paid"
              class="btn-qr"
              @click="emit('openQr', m)"
            >
              📱 Mã QR
            </button>
            <button
              v-if="!m.paid"
              class="btn-mark-paid"
              @click="emit('markPaid', m.id)"
            >
              ✓ Xác nhận
            </button>
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
  gap: 14px;
}

.members-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  flex-wrap: wrap;
  gap: 8px;
}

.title {
  margin: 0;
  font-size: 15px;
  font-weight: 700;
}

.sub {
  font-size: 12px;
  color: var(--muted);
}

.members-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 12px;
}

.member-split-card {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 14px;
  border-radius: 12px;
  border: 1px solid var(--outline);
  background: var(--surface);
}

.member-split-card.is-paid {
  border-color: rgba(16, 185, 129, 0.4);
  background: rgba(16, 185, 129, 0.02);
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
  width: 36px;
  height: 36px;
  border-radius: 50%;
  overflow: hidden;
  background: rgba(236, 72, 153, 0.1);
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  color: var(--pink-dark, #db2777);
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

.name {
  font-size: 13px;
}

.role-tag {
  font-size: 10px;
  color: var(--muted);
}

.m-amount {
  font-size: 15px;
}

.text-pink {
  color: var(--pink-dark, #db2777);
}

.m-status-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-top: 8px;
  border-top: 1px solid var(--outline);
  font-size: 11px;
}

.status-paid {
  color: #10b981;
  font-weight: 700;
}

.status-unpaid {
  color: #f59e0b;
  font-weight: 700;
}

.m-actions {
  display: flex;
  gap: 6px;
}

.btn-qr, .btn-mark-paid {
  padding: 4px 8px;
  border-radius: 6px;
  border: 1px solid var(--outline);
  background: var(--surface);
  font-size: 11px;
  font-weight: 600;
  cursor: pointer;
}

.btn-qr:hover {
  border-color: var(--pink-dark, #db2777);
}

.btn-mark-paid:hover {
  border-color: #10b981;
  color: #10b981;
}
</style>
