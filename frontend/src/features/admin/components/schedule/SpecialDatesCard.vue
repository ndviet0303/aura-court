<script setup lang="ts">
import type { SpecialDateItem } from '../../composables/useAdminSchedule'

defineProps<{
  specialDates: SpecialDateItem[]
}>()

const emit = defineEmits<{
  (e: 'add'): void
}>()
</script>

<template>
  <div class="special-dates-card">
    <div class="card-header">
      <div>
        <h3 class="title">🎉 Ngày Đặc Biệt & Sự Kiện (Holidays / Events)</h3>
        <p class="sub">Lịch hoạt động riêng biệt cho các dịp lễ Tết, giải đấu nội bộ hoặc sửa chữa cụm sân.</p>
      </div>

      <button class="btn-add-circle" @click="emit('add')">
        + Thêm ngày đặc biệt
      </button>
    </div>

    <div class="dates-list">
      <div v-for="d in specialDates" :key="d.id" class="date-row">
        <div class="date-info">
          <strong class="date-name">{{ d.name }}</strong>
          <span class="date-val font-mono">📅 {{ d.date }} · {{ d.hours || 'Theo lịch chuẩn' }}</span>
        </div>
        <span class="type-tag" :class="`type-${d.type}`">
          {{ d.type === 'holiday' ? 'Ngày Lễ' : 'Giờ Đặc Biệt' }}
        </span>
      </div>
    </div>
  </div>
</template>

<style scoped>
.special-dates-card {
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

.btn-add-circle {
  padding: 8px 16px;
  border-radius: 8px;
  border: 0;
  background: var(--pink-dark, #db2777);
  color: #fff;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
  white-space: nowrap;
}

.dates-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.date-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 14px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
}

.date-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
  font-size: 13px;
}

.date-val {
  font-size: 11px;
  color: var(--muted);
}

.type-tag {
  font-size: 11px;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 4px;
}

.type-holiday { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
.type-special_hours { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
</style>
