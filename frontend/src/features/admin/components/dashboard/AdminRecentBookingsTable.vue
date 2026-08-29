<script setup lang="ts">
import type { AdminBooking } from '../../composables/useAdminDashboard'

defineProps<{
  bookings: AdminBooking[]
  search: string
  status: string
}>()

const emit = defineEmits<{
  (e: 'update:search', val: string): void
  (e: 'update:status', val: string): void
}>()

function formatMoney(amount: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0)
}
</script>

<template>
  <div class="recent-bookings-card">
    <div class="card-header">
      <div class="header-titles">
        <h3 class="title">📋 Đơn Đặt Sân Mới Nhất</h3>
        <p class="sub">Theo dõi các đơn đặt sân trực tuyến, giờ chơi và trạng thái thanh toán.</p>
      </div>

      <div class="filters-row">
        <input
          :value="search"
          type="text"
          class="search-input"
          placeholder="Tìm mã đơn, tên khách..."
          @input="emit('update:search', ($event.target as HTMLInputElement).value)"
        />

        <select
          :value="status"
          class="status-select"
          @change="emit('update:status', ($event.target as HTMLSelectElement).value)"
        >
          <option value="">Tất cả trạng thái</option>
          <option value="confirmed">Đã xác nhận</option>
          <option value="pending">Chờ thanh toán</option>
          <option value="cancelled">Đã huỷ</option>
        </select>
      </div>
    </div>

    <div class="bookings-table">
      <div class="table-hdr">
        <span>Mã Đơn</span>
        <span>Khách Hàng</span>
        <span>Sân & Giờ Chơi</span>
        <span>Tổng Tiền</span>
        <span>Trạng Thái</span>
      </div>

      <div class="table-body">
        <div v-for="b in bookings" :key="b.id" class="booking-row">
          <strong class="font-mono text-pink">{{ b.code }}</strong>
          <div class="user-col">
            <strong>{{ b.user?.name || 'Khách vãng lai' }}</strong>
            <span class="email-sub">{{ b.user?.email }}</span>
          </div>
          <div class="court-col">
            <strong>{{ b.court?.name }}</strong>
            <span class="slot-sub font-mono">{{ b.slots?.[0]?.starts_at }} ➔ {{ b.slots?.[0]?.ends_at }}</span>
          </div>
          <strong class="font-mono">{{ formatMoney(b.total_amount) }}</strong>
          <span class="status-pill" :class="`status-${b.status}`">
            ● {{ b.status === 'confirmed' ? 'Đã thanh toán' : 'Chờ xử lý' }}
          </span>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.recent-bookings-card {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 20px;
  border-radius: 16px;
  border: 1px solid var(--outline);
  background: var(--surface);
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: 12px;
}

.title {
  margin: 0;
  font-size: 16px;
  font-weight: 700;
}

.sub {
  margin: 4px 0 0;
  font-size: 12px;
  color: var(--muted);
}

.filters-row {
  display: flex;
  gap: 10px;
}

.search-input, .status-select {
  padding: 8px 12px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: var(--surface);
  font-size: 12px;
}

.bookings-table {
  display: flex;
  flex-direction: column;
  border-radius: 12px;
  border: 1px solid var(--outline);
  overflow: hidden;
}

.table-hdr {
  display: grid;
  grid-template-columns: 1.2fr 1.8fr 2fr 1.2fr 1.2fr;
  padding: 12px 16px;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.03));
  border-bottom: 1px solid var(--outline);
  font-size: 12px;
  font-weight: 700;
  color: var(--muted);
}

.booking-row {
  display: grid;
  grid-template-columns: 1.2fr 1.8fr 2fr 1.2fr 1.2fr;
  padding: 12px 16px;
  align-items: center;
  border-bottom: 1px solid var(--outline);
  font-size: 13px;
}

.booking-row:last-child {
  border-bottom: 0;
}

.text-pink { color: var(--pink-dark, #db2777); }

.user-col, .court-col {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.email-sub, .slot-sub {
  font-size: 11px;
  color: var(--muted);
}

.status-pill {
  font-size: 11px;
  font-weight: 700;
  padding: 3px 8px;
  border-radius: 6px;
}

.status-confirmed { background: rgba(16, 185, 129, 0.1); color: #10b981; }
.status-pending { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
</style>
