<script setup lang="ts">
import type { MaintenanceBlock } from '../../composables/useAdminSchedule'

defineProps<{
  blocks: MaintenanceBlock[]
}>()

const emit = defineEmits<{
  (e: 'scheduleDowntime'): void
}>()
</script>

<template>
  <div class="maintenance-card">
    <div class="card-header">
      <div>
        <h3 class="title">🔒 Lịch Bảo Trì & Tạm Khoá Sân (Maintenance Downtime)</h3>
        <p class="sub">Khoá tạm thời một hoặc nhiều sân phục vụ thay thảm, sửa đèn, vệ sinh định kỳ.</p>
      </div>

      <button class="btn-schedule-downtime-cta" @click="emit('scheduleDowntime')">
        🔒 Lên lịch bảo trì
      </button>
    </div>

    <div class="blocks-list">
      <div v-for="b in blocks" :key="b.id" class="block-row">
        <div class="block-info">
          <strong>{{ b.reason }}</strong>
          <span class="block-court">📍 {{ b.court_name }}</span>
          <span class="block-time font-mono">⏰ {{ b.starts_at }} ➔ {{ b.ends_at }}</span>
        </div>
        <span class="block-status-badge">Đang khoá</span>
      </div>
    </div>
  </div>
</template>

<style scoped>
.maintenance-card {
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

.btn-schedule-downtime-cta {
  padding: 8px 16px;
  border-radius: 8px;
  border: 0;
  background: #f59e0b;
  color: #fff;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
  white-space: nowrap;
}

.blocks-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.block-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 14px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface-secondary, rgba(0, 0, 0, 0.02));
}

.block-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
  font-size: 13px;
}

.block-court, .block-time {
  font-size: 11px;
  color: var(--muted);
}

.block-status-badge {
  font-size: 11px;
  font-weight: 700;
  color: #ef4444;
  background: rgba(239, 68, 68, 0.1);
  padding: 2px 8px;
  border-radius: 4px;
}
</style>
