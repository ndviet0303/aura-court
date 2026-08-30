<script setup lang="ts">
import { RouterLink } from 'vue-router'

defineProps<{
  totalCount: number
}>()
</script>

<template>
  <div class="booking-hero-card">
    <div class="hero-titles">
      <span class="badge font-mono">MY AURA RESERVATIONS</span>
      <h1 class="page-title">Quản Lý Lịch Đặt Sân Của Tôi</h1>
      <p class="page-sub">Theo dõi lịch thi đấu sắp tới, check-in mã QR tại sân và xem lại lịch sử giao dịch.</p>
    </div>

    <div class="hero-actions">
      <RouterLink to="/courts" class="btn-new-booking">
        ➕ Đặt Thêm Sân Mới
      </RouterLink>
      <RouterLink to="/booking/recurring" class="btn-recurring-booking">
        📑 Đặt Lịch Cố Định Tháng
      </RouterLink>
    </div>
  </div>
</template>

<style scoped>
.booking-hero-card {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 16px;
  padding: 24px;
  border-radius: 20px;
  border: 1px solid var(--outline);
  background: var(--surface);
}

.hero-titles {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.badge {
  font-size: 11px;
  font-weight: 800;
  color: var(--pink-dark, #db2777);
  background: rgba(236, 72, 153, 0.1);
  padding: 3px 8px;
  border-radius: 6px;
  width: fit-content;
}

.page-title {
  margin: 0;
  font-size: 22px;
  font-weight: 800;
}

.page-sub {
  margin: 0;
  font-size: 13px;
  color: var(--muted);
}

.hero-actions {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

.btn-new-booking {
  padding: 10px 18px;
  border-radius: 10px;
  border: 0;
  background: var(--pink-dark, #db2777);
  color: #fff;
  font-size: 13px;
  font-weight: 700;
  text-decoration: none;
  cursor: pointer;
}

.btn-recurring-booking {
  padding: 10px 18px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 13px;
  font-weight: 600;
  text-decoration: none;
  cursor: pointer;
}
</style>
